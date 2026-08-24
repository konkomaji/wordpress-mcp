<?php
/**
 * Shared media ingest and image processing engine.
 *
 * Every route that puts an image into the library — upload_media, product
 * images, variation images, bulk imports — goes through ingest() so they all
 * get the same behaviour: a validated source, an SEO-friendly filename,
 * duplicate detection, optional downscaling and format conversion, and the
 * full descriptive field set written in one pass.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media ingest, de-duplication, and image processing.
 */
class WPMCP_Media {

	/**
	 * Meta key holding the SHA-1 of the bytes as they are stored on disk. Used
	 * to spot two attachments that hold the same image.
	 */
	const HASH_META = '_wpmcp_file_hash';

	/**
	 * Meta key holding the SHA-1 of the bytes as they arrived, before any
	 * downscaling or conversion. Dedup matches on this so re-sending the same
	 * source does not download and re-encode it a second time.
	 */
	const SOURCE_HASH_META = '_wpmcp_source_hash';

	/**
	 * Meta key holding the URL an attachment was sideloaded from.
	 */
	const SOURCE_META = '_wpmcp_source_url';

	/**
	 * Meta key holding the path of the pre-optimisation backup, so an
	 * optimisation can be undone.
	 */
	const BACKUP_META = '_wpmcp_original_file';

	/**
	 * Load the WordPress media/file admin includes an ingest needs.
	 */
	public static function bootstrap() {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	/**
	 * Reject anything WordPress would not accept as an upload, and script or
	 * executable types even if a filter widened the allowed-mime list.
	 *
	 * @param string $filename Candidate filename.
	 * @throws WPMCP_Tool_Exception When the type is not permitted.
	 */
	public static function assert_uploadable_filename( $filename ) {
		$checked = wp_check_filetype( $filename, get_allowed_mime_types() );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Disallowed file type: %s', $filename ),
				'Only standard WordPress-permitted media types may be uploaded. For images use jpg, png, gif, webp or avif.',
				[ 'filename' => $filename ]
			);
		}
		if ( preg_match( '/\.(php\d?|phtml|phps|phar|cgi|pl|py|rb|sh|bash|exe|com|bat|cmd|js|mjs|htm|html|svg|xhtml)(\.|$)/i', $filename ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				'Executable or script file types are not permitted.',
				'Rename the file to a plain image extension, or upload a rendered image instead.',
				[ 'filename' => $filename ]
			);
		}
	}

	/**
	 * Turn a caller-supplied name into a clean, descriptive, SEO-usable
	 * filename while keeping the real extension.
	 *
	 * "IMG_2831.JPG" with a base of "Black Cotton Hoodie" becomes
	 * "black-cotton-hoodie.jpg".
	 *
	 * @param string $filename Original filename (supplies the extension).
	 * @param string $base     Desired human name, without extension.
	 * @return string
	 */
	public static function seo_filename( $filename, $base ) {
		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			$ext = 'jpg';
		}
		$slug = sanitize_title( $base );
		if ( '' === $slug ) {
			return sanitize_file_name( $filename );
		}
		// Keep filenames short enough to stay readable in a URL.
		if ( strlen( $slug ) > 80 ) {
			$slug = trim( substr( $slug, 0, 80 ), '-' );
		}
		return sanitize_file_name( $slug . '.' . $ext );
	}

	/**
	 * SHA-1 of a file's bytes.
	 *
	 * @param string $path Absolute path.
	 * @return string Empty when unreadable.
	 */
	public static function hash_file( $path ) {
		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}
		$hash = sha1_file( $path );
		return $hash ? $hash : '';
	}

	/**
	 * Find an existing attachment holding identical bytes.
	 *
	 * @param string $hash SHA-1 of the candidate file.
	 * @return int Attachment ID, or 0.
	 */
	public static function find_by_hash( $hash ) {
		if ( '' === $hash ) {
			return 0;
		}
		$found = get_posts(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// Match either the bytes as they arrived or the bytes as
				// stored, so both a re-send of the same source and a re-upload
				// of a file already here are recognised.
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					[ 'key' => self::SOURCE_HASH_META, 'value' => $hash ],
					[ 'key' => self::HASH_META, 'value' => $hash ],
				],
			]
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Resolve one image source into a temporary local file.
	 *
	 * Accepted source keys, in priority order: `id` (already in the library,
	 * returned as-is), `url`, `base64`, `path`.
	 *
	 * @param array $source Source descriptor.
	 * @return array{attachment_id:int,tmp:string,filename:string,origin:string}
	 * @throws WPMCP_Tool_Exception When the source cannot be resolved.
	 */
	public static function resolve_source( $source ) {
		$blank = [
			'attachment_id' => 0,
			'tmp'           => '',
			'filename'      => '',
			'origin'        => '',
		];

		if ( ! empty( $source['id'] ) ) {
			$id = (int) $source['id'];
			if ( 'attachment' !== get_post_type( $id ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'Attachment %d does not exist.', $id ),
					'Use list_media to find the right attachment ID, or pass url / base64 / path instead.',
					[ 'id' => $id ]
				);
			}
			return array_merge( $blank, [ 'attachment_id' => $id, 'origin' => 'id' ] );
		}

		if ( ! empty( $source['url'] ) ) {
			return array_merge( $blank, self::fetch_url( (string) $source['url'] ) );
		}

		if ( ! empty( $source['base64'] ) ) {
			return array_merge( $blank, self::decode_base64( (string) $source['base64'], (string) ( $source['filename'] ?? '' ) ) );
		}

		if ( ! empty( $source['path'] ) ) {
			return array_merge( $blank, self::copy_local_path( (string) $source['path'] ) );
		}

		WPMCP_Errors::fail(
			WPMCP_Errors::MISSING_ARGUMENT,
			'No image source given.',
			'Provide one of: id (existing attachment), url (downloaded by the server), base64 (raw bytes plus filename), or path (a file already on the server).',
			[ 'accepted' => [ 'id', 'url', 'base64', 'path' ] ]
		);
	}

	/**
	 * Download a remote URL to a temp file.
	 *
	 * @param string $url Source URL.
	 * @return array
	 * @throws WPMCP_Tool_Exception On an unfetchable URL or failed download.
	 */
	private static function fetch_url( $url ) {
		self::bootstrap();
		if ( ! wp_http_validate_url( $url ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '"%s" is not a fetchable URL.', $url ),
				'The URL must be absolute, http(s), and resolve to a public host — the server refuses localhost and private ranges.',
				[ 'url' => $url ]
			);
		}
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			WPMCP_Errors::from_wp_error( $tmp, WPMCP_Errors::UPSTREAM_FAILED, 'Check the URL is publicly reachable from the server, not just from your browser.' );
		}
		$filename = sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		if ( '' === $filename || false === strpos( $filename, '.' ) ) {
			$filename = 'image-' . substr( md5( $url ), 0, 8 ) . '.jpg';
		}
		return [
			'tmp'      => $tmp,
			'filename' => $filename,
			'origin'   => $url,
		];
	}

	/**
	 * Write base64 bytes to a temp file.
	 *
	 * @param string $data     Base64 payload, with or without a data: prefix.
	 * @param string $filename Caller-supplied filename.
	 * @return array
	 * @throws WPMCP_Tool_Exception On a bad payload or a missing filename.
	 */
	private static function decode_base64( $data, $filename ) {
		self::bootstrap();

		// Accept a full data URI and recover the extension from its mime type.
		if ( 0 === strpos( $data, 'data:' ) && false !== strpos( $data, ',' ) ) {
			list( $header, $data ) = explode( ',', $data, 2 );
			if ( '' === $filename && preg_match( '#data:image/([a-z0-9.+-]+)#i', $header, $m ) ) {
				$ext      = 'jpeg' === strtolower( $m[1] ) ? 'jpg' : strtolower( $m[1] );
				$filename = 'image.' . $ext;
			}
		}
		if ( '' === $filename ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'filename is required when the source is base64.',
				'Pass filename (for example "black-cotton-hoodie.jpg") so the extension and media type can be determined.'
			);
		}
		$filename = sanitize_file_name( $filename );
		self::assert_uploadable_filename( $filename );

		$bytes = base64_decode( preg_replace( '/\s+/', '', $data ), true );
		if ( false === $bytes || '' === $bytes ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'base64 payload could not be decoded.',
				'Send standard base64 of the raw file bytes, optionally as a data: URI. Do not URL-encode it.'
			);
		}

		$tmp = wp_tempnam( $filename );
		if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not write the decoded bytes to a temporary file.', 'Check the server temp directory is writable.' );
		}
		return [
			'tmp'      => $tmp,
			'filename' => $filename,
			'origin'   => 'base64',
		];
	}

	/**
	 * Copy a file that is already on the server into a temp file.
	 *
	 * Reading arbitrary server paths is a filesystem operation, so it is gated
	 * on the filesystem capability rather than the media one.
	 *
	 * @param string $path Absolute or ABSPATH-relative path.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the path is not allowed or not readable.
	 */
	private static function copy_local_path( $path ) {
		self::bootstrap();

		if ( ! WPMCP_Settings::can( 'filesystem' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CAPABILITY_DISABLED,
				'Reading an image from a server path needs the Filesystem capability.',
				'Enable the Filesystem group on the WordPress MCP settings screen, or send the image as base64 or a URL instead.',
				[ 'group' => 'filesystem' ]
			);
		}

		$candidate = 0 === strpos( $path, '/' ) || preg_match( '#^[A-Za-z]:[\\\\/]#', $path )
			? $path
			: rtrim( ABSPATH, '/\\' ) . '/' . ltrim( $path, '/\\' );
		$real      = realpath( $candidate );
		$root      = realpath( ABSPATH );

		if ( ! $real || ! $root || 0 !== strpos( $real, $root ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				'path must point at a file inside the WordPress installation.',
				'Give a path relative to the WordPress root, or upload the bytes with base64 instead.',
				[ 'path' => $path ]
			);
		}
		if ( ! is_file( $real ) || ! is_readable( $real ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( '%s is not a readable file.', $path ), 'Check the path with file_info or list_files.' );
		}

		$filename = sanitize_file_name( basename( $real ) );
		self::assert_uploadable_filename( $filename );

		$tmp = wp_tempnam( $filename );
		if ( ! $tmp || ! copy( $real, $tmp ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not copy the file into the temp directory.' );
		}
		return [
			'tmp'      => $tmp,
			'filename' => $filename,
			'origin'   => $real,
		];
	}

	/**
	 * Put one image into the media library and describe it fully.
	 *
	 * @param array $source Source descriptor: id|url|base64|path (+ filename for base64).
	 * @param array $opts   {
	 *     Optional. Ingest options.
	 *
	 *     @type int    $post_id       Parent post to attach to.
	 *     @type string $filename      Force this filename.
	 *     @type string $seo_name      Rename the file after this human name.
	 *     @type string $alt           Alt text.
	 *     @type string $title         Attachment title.
	 *     @type string $caption       Attachment caption.
	 *     @type string $description   Attachment description.
	 *     @type bool   $dedup         Reuse an identical existing file. Default true.
	 *     @type int    $max_dimension Downscale so neither side exceeds this.
	 *     @type string $convert       Target format: webp|jpg|png. Empty to keep.
	 *     @type int    $quality       Encoder quality 1-100.
	 * }
	 * @return array Attachment summary plus what happened.
	 * @throws WPMCP_Tool_Exception On an unusable source or a failed insert.
	 */
	public static function ingest( $source, $opts = [] ) {
		self::bootstrap();

		$resolved = self::resolve_source( $source );

		// An existing attachment ID: only apply the descriptive fields.
		if ( $resolved['attachment_id'] ) {
			$id = $resolved['attachment_id'];
			self::apply_fields( $id, $opts );
			return array_merge( self::summary( $id ), [ 'reused' => true, 'action' => 'existing' ] );
		}

		$tmp      = $resolved['tmp'];
		$filename = '' !== (string) ( $opts['filename'] ?? '' ) ? sanitize_file_name( $opts['filename'] ) : $resolved['filename'];
		if ( '' !== (string) ( $opts['seo_name'] ?? '' ) ) {
			$filename = self::seo_filename( $filename, (string) $opts['seo_name'] );
		}

		try {
			self::assert_uploadable_filename( $filename );

			// The name can lie; the bytes cannot. Reject a mismatch before the
			// file ever reaches the uploads directory.
			$verify = wp_check_filetype_and_ext( $tmp, $filename );
			if ( empty( $verify['type'] ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::INVALID_ARGUMENT,
					sprintf( 'The contents of %s do not match an allowed media type.', $filename ),
					'The file is corrupt, or its extension does not match the real format. Re-export it as a plain jpg, png or webp.',
					[ 'filename' => $filename ]
				);
			}
			if ( ! empty( $verify['proper_filename'] ) ) {
				$filename = $verify['proper_filename'];
			}

			$hash = self::hash_file( $tmp );
			if ( false !== ( $opts['dedup'] ?? true ) ) {
				$existing = self::find_by_hash( $hash );
				if ( $existing ) {
					@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					self::apply_fields( $existing, $opts );
					return array_merge(
						self::summary( $existing ),
						[
							'reused' => true,
							'action' => 'deduplicated',
							'note'   => 'These exact bytes were already in the library; the existing attachment was reused instead of creating a duplicate.',
						]
					);
				}
			}

			// Process before the insert so the library never holds the
			// oversized original and its generated sizes.
			$processed = self::process_file( $tmp, $filename, $opts );
			$tmp       = $processed['path'];
			$filename  = $processed['filename'];

			$id = media_handle_sideload(
				[
					'name'     => $filename,
					'tmp_name' => $tmp,
				],
				(int) ( $opts['post_id'] ?? 0 ),
				isset( $opts['title'] ) ? (string) $opts['title'] : null
			);
			if ( is_wp_error( $id ) ) {
				WPMCP_Errors::from_wp_error( $id, WPMCP_Errors::IO_FAILED, 'Check the uploads directory is writable and has space.' );
			}
		} catch ( Throwable $e ) {
			if ( $tmp && file_exists( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			throw $e;
		}

		$id   = (int) $id;
		$file = get_attached_file( $id );
		update_post_meta( $id, self::SOURCE_HASH_META, $hash );
		update_post_meta( $id, self::HASH_META, $file ? self::hash_file( $file ) : $hash );
		if ( ! in_array( $resolved['origin'], [ '', 'base64', 'id' ], true ) ) {
			update_post_meta( $id, self::SOURCE_META, esc_url_raw( $resolved['origin'] ) );
		}
		self::apply_fields( $id, $opts );

		return array_merge(
			self::summary( $id ),
			[
				'reused'    => false,
				'action'    => 'created',
				'processed' => $processed['changes'],
			]
		);
	}

	/**
	 * Downscale and/or convert a file in place, before it becomes an attachment.
	 *
	 * @param string $path     Temp file path.
	 * @param string $filename Filename the attachment will take.
	 * @param array  $opts     Ingest options.
	 * @return array{path:string,filename:string,changes:array}
	 */
	private static function process_file( $path, $filename, $opts ) {
		$max     = (int) ( $opts['max_dimension'] ?? 0 );
		$convert = strtolower( (string) ( $opts['convert'] ?? '' ) );
		$quality = (int) ( $opts['quality'] ?? 0 );
		$changes = [];

		if ( ! $max && '' === $convert && ! $quality ) {
			return [ 'path' => $path, 'filename' => $filename, 'changes' => $changes ];
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			// Not an image WordPress can edit (a PDF, say). Leave it alone.
			return [ 'path' => $path, 'filename' => $filename, 'changes' => [ 'skipped' => 'not an editable image' ] ];
		}

		if ( $quality > 0 && $quality <= 100 ) {
			$editor->set_quality( $quality );
			$changes['quality'] = $quality;
		}

		if ( $max > 0 ) {
			$size = $editor->get_size();
			if ( ! empty( $size['width'] ) && ( $size['width'] > $max || $size['height'] > $max ) ) {
				$resized = $editor->resize( $max, $max, false );
				if ( ! is_wp_error( $resized ) ) {
					$after              = $editor->get_size();
					$changes['resized'] = sprintf( '%dx%d to %dx%d', $size['width'], $size['height'], $after['width'], $after['height'] );
				}
			}
		}

		$mime = self::mime_for( $convert );
		if ( $mime && ! wp_image_editor_supports( [ 'mime_type' => $mime ] ) ) {
			$changes['convert_skipped'] = sprintf( 'This server cannot write %s.', $convert );
			$mime                       = '';
		}

		$target = $mime
			? preg_replace( '/\.[^.]+$/', '', $path ) . '-conv.' . self::ext_for( $convert )
			: $path;

		$saved = $editor->save( $target, $mime ? $mime : null );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return [ 'path' => $path, 'filename' => $filename, 'changes' => [ 'skipped' => 'image could not be re-encoded' ] ];
		}

		if ( $mime ) {
			$changes['converted'] = $convert;
			$filename             = preg_replace( '/\.[^.]+$/', '', $filename ) . '.' . self::ext_for( $convert );
			if ( $saved['path'] !== $path && file_exists( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		return [ 'path' => $saved['path'], 'filename' => $filename, 'changes' => $changes ];
	}

	/**
	 * Mime type for a short format name.
	 *
	 * @param string $format webp|avif|jpg|jpeg|png.
	 * @return string Empty when unrecognised.
	 */
	public static function mime_for( $format ) {
		$map = [
			'webp' => 'image/webp',
			'avif' => 'image/avif',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
		];
		return $map[ strtolower( (string) $format ) ] ?? '';
	}

	/**
	 * File extension for a short format name.
	 *
	 * @param string $format Format name.
	 * @return string
	 */
	public static function ext_for( $format ) {
		$format = strtolower( (string) $format );
		return 'jpeg' === $format ? 'jpg' : $format;
	}

	/**
	 * Write alt, title, caption and description when supplied.
	 *
	 * @param int   $id   Attachment ID.
	 * @param array $opts Field values.
	 */
	public static function apply_fields( $id, $opts ) {
		$id = (int) $id;
		if ( isset( $opts['alt'] ) && '' !== (string) $opts['alt'] ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $opts['alt'] ) );
		}
		$update = [ 'ID' => $id ];
		if ( isset( $opts['title'] ) && '' !== (string) $opts['title'] ) {
			$update['post_title'] = sanitize_text_field( $opts['title'] );
		}
		if ( isset( $opts['caption'] ) ) {
			$update['post_excerpt'] = wp_kses_post( $opts['caption'] );
		}
		if ( isset( $opts['description'] ) ) {
			$update['post_content'] = wp_kses_post( $opts['description'] );
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}
	}

	/**
	 * Compact description of an attachment.
	 *
	 * @param int $id Attachment ID.
	 * @return array
	 */
	public static function summary( $id ) {
		$id   = (int) $id;
		$file = get_attached_file( $id );
		$meta = wp_get_attachment_metadata( $id );
		return [
			'id'       => $id,
			'url'      => wp_get_attachment_url( $id ),
			'filename' => $file ? basename( $file ) : '',
			'mime'     => get_post_mime_type( $id ),
			'width'    => isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height'   => isset( $meta['height'] ) ? (int) $meta['height'] : null,
			'bytes'    => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : null,
			'alt'      => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'title'    => get_the_title( $id ),
		];
	}

	/**
	 * Re-encode an attachment that is already in the library, replacing the
	 * stored file and regenerating its sizes. The pre-optimisation file is kept
	 * so the change can be undone.
	 *
	 * @param int   $id   Attachment ID.
	 * @param array $opts max_dimension, convert, quality, keep_original.
	 * @return array What changed, including the old and new URLs.
	 * @throws WPMCP_Tool_Exception When the attachment has no readable file.
	 */
	public static function optimize_attachment( $id, $opts ) {
		self::bootstrap();

		$id   = (int) $id;
		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Attachment %d has no file on disk.', $id ),
				'The database row exists but the file is missing. find_unused_media lists attachments in this state.',
				[ 'id' => $id ]
			);
		}

		$before_bytes = (int) filesize( $file );
		$before_url   = wp_get_attachment_url( $id );
		$before_meta  = wp_get_attachment_metadata( $id );

		$work = wp_tempnam( basename( $file ) );
		if ( ! $work || ! copy( $file, $work ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not stage a working copy of the image.' );
		}

		$processed = self::process_file( $work, basename( $file ), $opts );
		if ( ! empty( $processed['changes']['skipped'] ) ) {
			@unlink( $work ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return [
				'id'      => $id,
				'skipped' => $processed['changes']['skipped'],
			];
		}

		$new_bytes = file_exists( $processed['path'] ) ? (int) filesize( $processed['path'] ) : 0;

		// Never make a file bigger in the name of optimisation.
		if ( ! $new_bytes || $new_bytes >= $before_bytes ) {
			@unlink( $processed['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return [
				'id'      => $id,
				'skipped' => 'the re-encoded file was not smaller than the original',
				'bytes'   => $before_bytes,
			];
		}

		$dir       = dirname( $file );
		$converted = ! empty( $processed['changes']['converted'] );
		$dest      = $converted
			? $dir . '/' . wp_unique_filename( $dir, $processed['filename'] )
			: $file;

		// Keep the bytes we are replacing so optimisation is reversible.
		if ( false !== ( $opts['keep_original'] ?? true ) && ! get_post_meta( $id, self::BACKUP_META, true ) ) {
			$backup = $dir . '/' . wp_unique_filename( $dir, preg_replace( '/(\.[^.]+)$/', '-wpmcp-original$1', basename( $file ) ) );
			if ( copy( $file, $backup ) ) {
				update_post_meta( $id, self::BACKUP_META, $backup );
			}
		}

		if ( ! rename( $processed['path'], $dest ) ) {
			@unlink( $processed['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, sprintf( 'Could not write the optimised file for attachment %d.', $id ), 'Check the uploads directory is writable.' );
		}

		if ( $converted ) {
			// The old file and its sizes belong to the old format; nothing
			// should be left pointing at them.
			self::delete_generated_sizes( $file, $before_meta, false );
			update_attached_file( $id, $dest );
			$mime = self::mime_for( $processed['changes']['converted'] );
			if ( $mime ) {
				// wp_update_post ignores post_mime_type, so set it directly.
				global $wpdb;
				$wpdb->update( $wpdb->posts, [ 'post_mime_type' => $mime ], [ 'ID' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $id );
			}
		} else {
			// Same file, new dimensions: the old size files are stale and may
			// not be overwritten, because their names carry the old dimensions.
			self::delete_generated_sizes( $file, $before_meta, true );
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
		update_post_meta( $id, self::HASH_META, self::hash_file( $dest ) );

		return [
			'id'          => $id,
			'before'      => [ 'url' => $before_url, 'bytes' => $before_bytes ],
			'after'       => [ 'url' => wp_get_attachment_url( $id ), 'bytes' => $new_bytes ],
			'saved_bytes' => $before_bytes - $new_bytes,
			'changes'     => $processed['changes'],
			'url_changed' => $converted,
		];
	}

	/**
	 * Delete the generated size files for an attachment.
	 *
	 * @param string $file          Absolute path to the original file.
	 * @param array  $meta          Attachment metadata.
	 * @param bool   $keep_original Leave the original file in place.
	 */
	private static function delete_generated_sizes( $file, $meta, $keep_original = false ) {
		$dir = dirname( $file );
		foreach ( (array) ( $meta['sizes'] ?? [] ) as $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}
			$path = $dir . '/' . basename( $size['file'] );
			if ( file_exists( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		if ( ! $keep_original && file_exists( $file ) ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Restore the pre-optimisation file kept by optimize_attachment.
	 *
	 * @param int $id Attachment ID.
	 * @return array
	 * @throws WPMCP_Tool_Exception When no backup exists.
	 */
	public static function restore_attachment( $id ) {
		self::bootstrap();

		$id     = (int) $id;
		$backup = (string) get_post_meta( $id, self::BACKUP_META, true );
		if ( '' === $backup || ! file_exists( $backup ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No pre-optimisation backup is stored for attachment %d.', $id ),
				'A backup only exists if the image was optimised through optimize_image with keep_original left on.',
				[ 'id' => $id ]
			);
		}

		$current = get_attached_file( $id );
		$dir     = dirname( $backup );
		$restore = $dir . '/' . wp_unique_filename( $dir, preg_replace( '/-wpmcp-original(\.[^.]+)$/', '$1', basename( $backup ) ) );

		if ( ! copy( $backup, $restore ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not restore the original file.' );
		}
		if ( $current && $current !== $restore ) {
			self::delete_generated_sizes( $current, (array) wp_get_attachment_metadata( $id ) );
		}

		$mime = wp_check_filetype( $restore );
		if ( ! empty( $mime['type'] ) ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, [ 'post_mime_type' => $mime['type'] ], [ 'ID' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $id );
		}
		update_attached_file( $id, $restore );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $restore ) );
		update_post_meta( $id, self::HASH_META, self::hash_file( $restore ) );
		delete_post_meta( $id, self::BACKUP_META );
		@unlink( $backup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		return array_merge( self::summary( $id ), [ 'restored' => true ] );
	}
}
