<?php
/**
 * Media library tools: optimisation, thumbnail regeneration, duplicate and
 * orphan detection, and bulk alt text.
 *
 * Part of the `content` capability group, since these read and write the media
 * library, which is content.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media tools.
 */
trait WPMCP_Media_Tools {

	/**
	 * Tool definitions for the media group.
	 *
	 * @return array<int,array>
	 */
	private function defs_media() {
		return [
			[
				'group'       => 'content',
				'name'        => 'optimize_image',
				'description' => 'Shrink images that are already in the library: downscale oversized files, convert to WebP or AVIF, and re-encode at a chosen quality. Works on specific attachment IDs or sweeps the heaviest images on the site. The pre-optimisation file is kept so restore_image can undo it, and an image is never replaced by a larger one. Dry run by default.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids'               => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Attachment IDs to optimise. Omit to sweep the largest images automatically.' ],
						'min_kb'            => [ 'type' => 'integer', 'description' => 'When sweeping, only touch files above this size. Default 200.' ],
						'limit'             => [ 'type' => 'integer', 'description' => 'When sweeping, how many images to process. Default 25, max 200.' ],
						'max_dimension'     => [ 'type' => 'integer', 'description' => 'Downscale so neither side exceeds this many pixels. Default 2000. Pass 0 to leave dimensions alone.' ],
						'convert'           => [ 'type' => 'string', 'description' => 'webp|avif|jpg|png. Omit to keep the current format.' ],
						'quality'           => [ 'type' => 'integer', 'description' => 'Encoder quality 1-100. Default 82.' ],
						'keep_original'     => [ 'type' => 'boolean', 'description' => 'Keep the pre-optimisation file for restore_image. Default true.' ],
						'update_references' => [ 'type' => 'boolean', 'description' => 'When a conversion changes the URL, rewrite the old URL in post content and Elementor data. Default false.' ],
						'dry_run'           => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to apply.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'get_image_bytes',
				'description' => 'Return an image from the media library as an inline picture, so it can actually be looked at rather than described. Use it before writing alt text, product descriptions or captions: alt text written from a filename is a guess, alt text written from the image is not. Also the way to check the right photo is on the right product. Images are downscaled and re-encoded for transfer; the original is untouched.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'            => [ 'type' => 'integer', 'description' => 'Attachment ID.' ],
						'ids'           => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Several attachment IDs, up to 5.' ],
						'post_id'       => [ 'type' => 'integer', 'description' => 'Fetch this post or product\'s featured image instead.' ],
						'product_id'    => [ 'type' => 'integer', 'description' => 'Fetch a product\'s main image and gallery.' ],
						'max_dimension' => [ 'type' => 'integer', 'description' => 'Longest side in pixels. Default 768, max 1536. Smaller is faster and usually enough.' ],
						'quality'       => [ 'type' => 'integer', 'description' => 'Encoder quality 1-100. Default 72.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'restore_image',
				'description' => 'Undo optimize_image for one attachment by putting the pre-optimisation file back and regenerating its sizes.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Attachment ID.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'regenerate_thumbnails',
				'description' => 'Rebuild the generated image sizes for attachments: the fix after switching themes, changing image size settings, or optimising originals. Processes a batch at a time and returns the offset to continue from.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids'            => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Specific attachment IDs. Omit to walk the whole library.' ],
						'parent_post_id' => [ 'type' => 'integer', 'description' => 'Only attachments belonging to this post or product.' ],
						'offset'         => [ 'type' => 'integer', 'description' => 'Where to resume a library-wide run. Default 0.' ],
						'limit'          => [ 'type' => 'integer', 'description' => 'Images per batch. Default 25, max 100.' ],
						'missing_only'   => [ 'type' => 'boolean', 'description' => 'Only rebuild attachments with no size metadata. Default false.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'find_duplicate_media',
				'description' => 'Find images stored more than once: identical bytes uploaded under different names, which is what happens when the same product photo is imported repeatedly. Groups them by content hash and names the copy worth keeping (the one actually in use).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit'      => [ 'type' => 'integer', 'description' => 'Attachments to scan. Default 500, max 3000.' ],
						'mime_prefix'=> [ 'type' => 'string', 'description' => 'Restrict to a mime prefix. Default image/.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'find_unused_media',
				'description' => 'Find attachments nothing references: not a featured image, not in a product gallery, not embedded in any post content or page-builder layout. Also flags database rows whose file is missing from disk. Read-only: it reports but does not delete.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit'        => [ 'type' => 'integer', 'description' => 'Attachments to scan. Default 300, max 2000.' ],
						'offset'       => [ 'type' => 'integer', 'description' => 'Where to resume. Default 0.' ],
						'older_than_days' => [ 'type' => 'integer', 'description' => 'Only consider attachments older than this, so a fresh upload mid-edit is not flagged. Default 7.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'bulk_set_image_alt',
				'description' => 'Write alt text across many images at once from a template. Placeholders: {title}, {filename}, {parent_title}, {caption}, {site}, {category}. Targets images with no alt by default (the single biggest accessibility and image-SEO gap on most sites). Dry run by default.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'template'       => [ 'type' => 'string', 'description' => 'Alt text template, e.g. "{parent_title} - {title}".' ],
						'parent_post_type' => [ 'type' => 'string', 'description' => 'Only images attached to this post type, e.g. product.' ],
						'parent_post_id' => [ 'type' => 'integer', 'description' => 'Only images attached to this post.' ],
						'only_missing'   => [ 'type' => 'boolean', 'description' => 'Skip images that already have alt text. Default true.' ],
						'limit'          => [ 'type' => 'integer', 'description' => 'Images per run. Default 100, max 1000.' ],
						'offset'         => [ 'type' => 'integer', 'description' => 'Where to resume. Default 0.' ],
						'dry_run'        => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to apply.' ],
					],
					'required'   => [ 'template' ],
				],
			],
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_optimize_image( $args ) {
		$dry     = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$max     = isset( $args['max_dimension'] ) ? max( 0, (int) $args['max_dimension'] ) : 2000;
		$convert = strtolower( trim( (string) ( $args['convert'] ?? '' ) ) );
		$quality = (int) ( $args['quality'] ?? 82 );

		if ( '' !== $convert && ! WPMCP_Media::mime_for( $convert ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '"%s" is not a format this tool can write.', $convert ),
				'Use webp, avif, jpg or png, or omit convert to keep the current format.',
				[ 'accepted' => [ 'webp', 'avif', 'jpg', 'png' ] ]
			);
		}
		if ( '' !== $convert && ! wp_image_editor_supports( [ 'mime_type' => WPMCP_Media::mime_for( $convert ) ] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::DEPENDENCY_MISSING,
				sprintf( 'This server cannot encode %s.', $convert ),
				'Its GD or Imagick build lacks support. Try webp, or optimise without converting.',
				[ 'format' => $convert ]
			);
		}

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( ! $ids ) {
			$ids = $this->heaviest_attachments(
				max( 1, (int) ( $args['min_kb'] ?? 200 ) ) * 1024,
				min( max( 1, (int) ( $args['limit'] ?? 25 ) ), 200 )
			);
		}
		$ids = array_slice( $ids, 0, 200 );

		if ( ! $ids ) {
			return [
				'planned'   => [],
				'processed' => [],
				'message'   => 'No images matched. Everything above the size threshold is already optimised, or the threshold is too high.',
			];
		}

		if ( $dry ) {
			$planned = [];
			foreach ( $ids as $id ) {
				$file      = get_attached_file( $id );
				$meta      = wp_get_attachment_metadata( $id );
				$planned[] = [
					'id'       => $id,
					'filename' => $file ? basename( $file ) : '',
					'bytes'    => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : null,
					'width'    => isset( $meta['width'] ) ? (int) $meta['width'] : null,
					'height'   => isset( $meta['height'] ) ? (int) $meta['height'] : null,
					'will'     => array_values(
						array_filter(
							[
								$max && ! empty( $meta['width'] ) && ( $meta['width'] > $max || $meta['height'] > $max ) ? sprintf( 'downscale to %dpx', $max ) : '',
								'' !== $convert ? 'convert to ' . $convert : '',
								sprintf( 're-encode at quality %d', $quality ),
							]
						)
					),
				];
			}
			return [
				'dry_run'   => true,
				'count'     => count( $planned ),
				'planned'   => $planned,
				'message'   => 'Dry run: nothing was changed. Review the list, then call again with dry_run=false.',
				'next_step' => '' !== $convert
					? 'Converting changes each file URL. Pass update_references=true to rewrite the old URLs in content, or leave it off; the original file stays on disk so existing references keep working.'
					: 'Call again with dry_run=false to apply.',
			];
		}

		WPMCP_Journal::note( 'Image files rewritten by optimize_image are not restored by undo_operation. Use restore_image on each attachment, which puts back the backed-up original.' );
		$opts = [
			'max_dimension' => $max,
			'convert'       => $convert,
			'quality'       => $quality,
			'keep_original' => ! isset( $args['keep_original'] ) || WPMCP_Util::bool( $args['keep_original'], true ),
		];

		$results  = [];
		$saved    = 0;
		$rewrites = [];
		WPMCP_Progress::start( 'optimize_image', count( $ids ), 'optimising images' );
		foreach ( $ids as $id ) {
			if ( WPMCP_Progress::should_stop() ) {
				$results[] = [ 'stopped_early' => true, 'note' => 'Ran out of execution time; the images already processed are saved. Call again to continue.' ];
				break;
			}
			WPMCP_Progress::tick( 1, (string) $id );
			try {
				$result = WPMCP_Media::optimize_attachment( $id, $opts );
			} catch ( WPMCP_Tool_Exception $e ) {
				$results[] = [ 'id' => $id, 'error' => $e->getMessage(), 'code' => $e->get_error_code() ];
				continue;
			}
			if ( ! empty( $result['saved_bytes'] ) ) {
				$saved += (int) $result['saved_bytes'];
			}
			if ( ! empty( $result['url_changed'] ) ) {
				$rewrites[ $result['before']['url'] ] = $result['after']['url'];
			}
			$results[] = $result;
		}

		$reference_updates = null;
		if ( $rewrites && WPMCP_Util::bool( $args['update_references'] ?? null ) ) {
			$reference_updates = $this->rewrite_media_urls( $rewrites );
		}

		return [
			'dry_run'           => false,
			'count'             => count( $results ),
			'saved_bytes'       => $saved,
			'saved_readable'    => size_format( $saved ),
			'results'           => $results,
			'url_changes'       => $rewrites ? $rewrites : null,
			'reference_updates' => $reference_updates,
			'next_step'         => $rewrites && null === $reference_updates
				? 'File URLs changed. The old files are still on disk so nothing is broken, but call optimize_image again with update_references=true, or use search_replace_content, to point content at the new URLs.'
				: 'Run image_optimization_report to confirm the remaining weight.',
		];
	}

	/**
	 * Attachment IDs whose file exceeds a byte threshold, heaviest first.
	 *
	 * @param int $min_bytes Threshold.
	 * @param int $limit     How many to return.
	 * @return array<int,int>
	 */
	private function heaviest_attachments( $min_bytes, $limit ) {
		$candidates = get_posts(
			[
				'post_type'              => 'attachment',
				'post_mime_type'         => 'image',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
			]
		);

		$sized = [];
		foreach ( $candidates as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) {
				continue;
			}
			$bytes = (int) filesize( $file );
			if ( $bytes >= $min_bytes ) {
				$sized[ (int) $id ] = $bytes;
			}
		}
		arsort( $sized );
		return array_slice( array_keys( $sized ), 0, $limit );
	}

	/**
	 * Replace old media URLs with new ones in post content and Elementor data.
	 *
	 * Only these two stores are touched: both hold plain strings, so a straight
	 * replace is safe. Serialised meta is left alone.
	 *
	 * @param array<string,string> $map Old URL => new URL.
	 * @return array
	 */
	private function rewrite_media_urls( $map ) {
		global $wpdb;
		$content_rows = 0;
		$meta_rows    = 0;

		foreach ( $map as $old => $new ) {
			if ( ! $old || ! $new || $old === $new ) {
				continue;
			}
			$content_rows += (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_content = REPLACE( post_content, %s, %s ) WHERE post_content LIKE %s",
					$old,
					$new,
					'%' . $wpdb->esc_like( $old ) . '%'
				)
			);
			// Elementor stores its layout as a JSON string, so the same URL
			// appears there with its slashes escaped. Rewrite both forms.
			$pairs = [
				[ $old, $new ],
				[ str_replace( '/', '\\/', $old ), str_replace( '/', '\\/', $new ) ],
			];
			foreach ( $pairs as $pair ) {
				$meta_rows += (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE( meta_value, %s, %s ) WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
						$pair[0],
						$pair[1],
						'%' . $wpdb->esc_like( $pair[0] ) . '%'
					)
				);
			}
		}

		wp_cache_flush();

		return [
			'posts_updated'          => $content_rows,
			'elementor_rows_updated' => $meta_rows,
			'note'                   => 'Post content and Elementor layouts were rewritten. Serialised theme or builder meta was not touched. Check those manually if an image is still missing.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_image_bytes( $args ) {
		$max     = min( max( 64, (int) ( $args['max_dimension'] ?? 768 ) ), 1536 );
		$quality = min( max( 1, (int) ( $args['quality'] ?? 72 ) ), 100 );

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( ! empty( $args['id'] ) ) {
			$ids[] = (int) $args['id'];
		}
		if ( ! empty( $args['post_id'] ) ) {
			$thumb = get_post_thumbnail_id( (int) $args['post_id'] );
			if ( ! $thumb ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'Post %d has no featured image.', (int) $args['post_id'] ),
					'Pass an attachment id instead, or set a featured image with set_featured_image.'
				);
			}
			$ids[] = (int) $thumb;
		}
		if ( ! empty( $args['product_id'] ) ) {
			$product = $this->get_wc_product( (int) $args['product_id'] );
			$ids     = array_merge( $ids, array_filter( array_merge( [ (int) $product->get_image_id() ], array_map( 'intval', $product->get_gallery_image_ids() ) ) ) );
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( ! $ids ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'No image selected.',
				'Pass id, ids, post_id or product_id.',
				[ 'accepted' => [ 'id', 'ids', 'post_id', 'product_id' ] ]
			);
		}
		// Five images is already a large payload; more would be refused by the
		// transport rather than helping.
		$ids = array_slice( $ids, 0, 5 );

		$blocks   = [];
		$images   = [];
		$problems = [];

		foreach ( $ids as $id ) {
			$rendered = $this->render_image_for_transfer( (int) $id, $max, $quality );
			if ( isset( $rendered['error'] ) ) {
				$problems[] = [ 'id' => (int) $id, 'error' => $rendered['error'] ];
				continue;
			}
			$blocks[] = [
				'type'     => 'image',
				'data'     => $rendered['base64'],
				'mimeType' => $rendered['mime'],
			];
			$images[] = [
				'id'        => (int) $id,
				'url'       => wp_get_attachment_url( $id ),
				'filename'  => basename( (string) get_attached_file( $id ) ),
				'alt'       => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'title'     => get_the_title( $id ),
				'caption'   => (string) get_post_field( 'post_excerpt', $id ),
				'attached_to' => (int) wp_get_post_parent_id( $id ) ?: null,
				'sent_as'   => sprintf( '%dx%d %s', $rendered['width'], $rendered['height'], $rendered['mime'] ),
				'sent_kb'   => (int) round( $rendered['bytes'] / 1024 ),
			];
		}

		if ( ! $images ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				'None of the requested images could be read.',
				'The files may be missing from disk; find_unused_media lists attachments in that state.',
				[ 'problems' => $problems ]
			);
		}

		return [
			'_mcp_content' => $blocks,
			'images'       => $images,
			'problems'     => $problems,
			'note'         => 'The pictures above are downscaled copies sent for viewing only; the originals are unchanged.',
			'next_step'    => 'Write alt text from what is actually in the picture, then apply it with set_image_alt or manage_product_images.',
		];
	}

	/**
	 * Produce a small copy of an attachment suitable for sending inline.
	 *
	 * @param int $id      Attachment ID.
	 * @param int $max     Longest side.
	 * @param int $quality Encoder quality.
	 * @return array
	 */
	private function render_image_for_transfer( $id, $max, $quality ) {
		WPMCP_Media::bootstrap();

		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			return [ 'error' => 'The file is missing from disk.' ];
		}
		if ( 0 !== strpos( (string) get_post_mime_type( $id ), 'image/' ) ) {
			return [ 'error' => 'Not an image: ' . get_post_mime_type( $id ) ];
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return [ 'error' => 'WordPress cannot read this image: ' . $editor->get_error_message() ];
		}

		$editor->set_quality( $quality );
		$size = $editor->get_size();
		if ( ! empty( $size['width'] ) && ( $size['width'] > $max || $size['height'] > $max ) ) {
			$editor->resize( $max, $max, false );
		}

		// JPEG for photographs, PNG kept for anything with transparency, so a
		// logo does not come back on a black square.
		$mime = 'image/png' === get_post_mime_type( $id ) ? 'image/png' : 'image/jpeg';
		$temp = wp_tempnam( 'wpmcp-view-' . $id . ( 'image/png' === $mime ? '.png' : '.jpg' ) );
		$saved = $editor->save( $temp, $mime );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return [ 'error' => 'The image could not be re-encoded for transfer.' ];
		}

		$bytes = file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$after = $editor->get_size();
		@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $saved['path'] !== $temp && file_exists( $temp ) ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		if ( false === $bytes || '' === $bytes ) {
			return [ 'error' => 'The re-encoded image could not be read back.' ];
		}

		return [
			'base64' => base64_encode( $bytes ),
			'mime'   => $saved['mime-type'] ?? $mime,
			'width'  => (int) ( $after['width'] ?? 0 ),
			'height' => (int) ( $after['height'] ?? 0 ),
			'bytes'  => strlen( $bytes ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_restore_image( $args ) {
		return WPMCP_Media::restore_attachment( (int) $args['id'] );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_regenerate_thumbnails( $args ) {
		WPMCP_Media::bootstrap();

		$limit  = min( max( 1, (int) ( $args['limit'] ?? 25 ) ), 100 );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$ids    = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		$total  = count( $ids );

		if ( ! $ids ) {
			$query = [
				'post_type'              => 'attachment',
				'post_mime_type'         => 'image',
				'post_status'            => 'inherit',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'update_post_term_cache' => false,
			];
			if ( ! empty( $args['parent_post_id'] ) ) {
				$query['post_parent'] = (int) $args['parent_post_id'];
			}
			$q     = new WP_Query( $query );
			$ids   = $q->posts;
			$total = (int) $q->found_posts;
		} else {
			$ids = array_slice( $ids, $offset, $limit );
		}

		$missing_only = WPMCP_Util::bool( $args['missing_only'] ?? null );
		$done         = [];
		$skipped      = [];

		WPMCP_Progress::start( 'regenerate_thumbnails', count( $ids ), 'regenerating sizes' );

		foreach ( $ids as $id ) {
			if ( WPMCP_Progress::should_stop() ) {
				$skipped[] = [ 'id' => (int) $id, 'reason' => 'ran out of execution time; resume from next_offset' ];
				break;
			}
			WPMCP_Progress::tick( 1, (string) $id );
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) {
				$skipped[] = [ 'id' => (int) $id, 'reason' => 'file missing on disk' ];
				continue;
			}
			$meta = wp_get_attachment_metadata( $id );
			if ( $missing_only && ! empty( $meta['sizes'] ) ) {
				$skipped[] = [ 'id' => (int) $id, 'reason' => 'already has generated sizes' ];
				continue;
			}
			$new = wp_generate_attachment_metadata( (int) $id, $file );
			if ( is_wp_error( $new ) || ! $new ) {
				$skipped[] = [ 'id' => (int) $id, 'reason' => 'WordPress could not read the image' ];
				continue;
			}
			wp_update_attachment_metadata( (int) $id, $new );
			$done[] = [
				'id'    => (int) $id,
				'sizes' => count( (array) ( $new['sizes'] ?? [] ) ),
			];
		}

		$next = $offset + count( $ids );

		return [
			'regenerated' => count( $done ),
			'results'     => $done,
			'skipped'     => $skipped,
			'total'       => $total,
			'next_offset' => $next < $total ? $next : null,
			'next_step'   => $next < $total
				? sprintf( 'More to do. Call again with offset=%d.', $next )
				: 'The whole set has been regenerated.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_find_duplicate_media( $args ) {
		$limit  = min( max( 1, (int) ( $args['limit'] ?? 500 ) ), 3000 );
		$prefix = (string) ( $args['mime_prefix'] ?? 'image' );

		$ids = get_posts(
			[
				'post_type'              => 'attachment',
				'post_mime_type'         => $prefix,
				'post_status'            => 'inherit',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			]
		);

		// Hashing every file is expensive, so group by byte size first: only
		// files of identical size can possibly be identical.
		$by_size = [];
		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) {
				continue;
			}
			$by_size[ (int) filesize( $file ) ][] = [ 'id' => (int) $id, 'file' => $file ];
		}

		$groups  = [];
		$wasted  = 0;
		$hashed  = 0;
		foreach ( $by_size as $bytes => $rows ) {
			if ( count( $rows ) < 2 ) {
				continue;
			}
			$by_hash = [];
			foreach ( $rows as $row ) {
				$hash = (string) get_post_meta( $row['id'], WPMCP_Media::HASH_META, true );
				if ( '' === $hash ) {
					$hash = WPMCP_Media::hash_file( $row['file'] );
					$hashed++;
					if ( '' !== $hash ) {
						update_post_meta( $row['id'], WPMCP_Media::HASH_META, $hash );
					}
				}
				if ( '' !== $hash ) {
					$by_hash[ $hash ][] = $row;
				}
			}
			foreach ( $by_hash as $hash => $copies ) {
				if ( count( $copies ) < 2 ) {
					continue;
				}
				$detail = [];
				foreach ( $copies as $copy ) {
					$uses     = $this->attachment_usage( $copy['id'] );
					$detail[] = [
						'id'       => $copy['id'],
						'url'      => wp_get_attachment_url( $copy['id'] ),
						'filename' => basename( $copy['file'] ),
						'used_by'  => $uses,
						'in_use'   => ! empty( $uses ),
					];
				}
				// Keep whichever copy is actually referenced; failing that, the
				// oldest, since it has had the longest to be linked to.
				usort(
					$detail,
					function ( $a, $b ) {
						if ( $a['in_use'] !== $b['in_use'] ) {
							return $a['in_use'] ? -1 : 1;
						}
						return $a['id'] <=> $b['id'];
					}
				);
				$keep     = $detail[0];
				$wasted  += $bytes * ( count( $detail ) - 1 );
				$groups[] = [
					'hash'          => $hash,
					'bytes_each'    => (int) $bytes,
					'copies'        => $detail,
					'keep_id'       => $keep['id'],
					'delete_ids'    => array_values( array_diff( wp_list_pluck( $detail, 'id' ), [ $keep['id'] ] ) ),
					'wasted_bytes'  => (int) $bytes * ( count( $detail ) - 1 ),
				];
			}
		}

		return [
			'scanned'         => count( $ids ),
			'newly_hashed'    => $hashed,
			'duplicate_sets'  => count( $groups ),
			'wasted_bytes'    => $wasted,
			'wasted_readable' => size_format( $wasted ),
			'groups'          => $groups,
			'next_step'       => $groups
				? 'Each group names a keep_id and the delete_ids that duplicate it. Repoint anything using a duplicate first, then remove them with delete_media.'
				: 'No byte-identical duplicates found in the scanned range.',
		];
	}

	/**
	 * Where an attachment is referenced.
	 *
	 * @param int $id Attachment ID.
	 * @return array<int,array> Post IDs and how each uses it.
	 */
	private function attachment_usage( $id ) {
		global $wpdb;
		$id   = (int) $id;
		$uses = [];

		$thumb_of = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 20", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		foreach ( $thumb_of as $pid ) {
			$uses[] = [ 'post_id' => (int) $pid, 'as' => 'featured image', 'title' => get_the_title( $pid ) ];
		}

		$gallery_of = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND ( meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s ) LIMIT 20", (string) $id, $id . ',%', '%,' . $id . ',%', '%,' . $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		foreach ( $gallery_of as $pid ) {
			$uses[] = [ 'post_id' => (int) $pid, 'as' => 'product gallery', 'title' => get_the_title( $pid ) ];
		}

		$file = get_attached_file( $id );
		if ( $file ) {
			// Match on the filename without extension so resized variants
			// (name-300x300.jpg) count as a reference too.
			$stem    = preg_replace( '/\.[^.]+$/', '', basename( $file ) );
			$in_body = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status NOT IN ( 'trash', 'auto-draft' ) AND post_type != 'attachment' AND post_content LIKE %s LIMIT 20", '%' . $wpdb->esc_like( $stem ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			foreach ( $in_body as $pid ) {
				$uses[] = [ 'post_id' => (int) $pid, 'as' => 'in content', 'title' => get_the_title( $pid ) ];
			}
			$in_builder = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '_elementor_data', '_et_pb_ab_subject', 'panels_data' ) AND meta_value LIKE %s LIMIT 20", '%' . $wpdb->esc_like( $stem ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			foreach ( $in_builder as $pid ) {
				$uses[] = [ 'post_id' => (int) $pid, 'as' => 'in builder layout', 'title' => get_the_title( $pid ) ];
			}
		}

		return $uses;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_find_unused_media( $args ) {
		$limit  = min( max( 1, (int) ( $args['limit'] ?? 300 ) ), 2000 );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$days   = max( 0, (int) ( $args['older_than_days'] ?? 7 ) );

		$query = [
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => $limit,
			'offset'                 => $offset,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'update_post_term_cache' => false,
		];
		if ( $days > 0 ) {
			$query['date_query'] = [ [ 'before' => sprintf( '%d days ago', $days ) ] ];
		}
		$q = new WP_Query( $query );

		$unused  = [];
		$missing = [];
		$bytes   = 0;

		WPMCP_Progress::start( 'find_unused_media', count( $q->posts ), 'checking references' );

		foreach ( $q->posts as $id ) {
			if ( WPMCP_Progress::should_stop() ) {
				break;
			}
			WPMCP_Progress::tick();
			$file   = get_attached_file( $id );
			$exists = $file && file_exists( $file );
			if ( ! $exists ) {
				$missing[] = [
					'id'       => (int) $id,
					'filename' => $file ? basename( $file ) : '',
					'title'    => get_the_title( $id ),
				];
				continue;
			}
			if ( $this->attachment_usage( $id ) ) {
				continue;
			}
			$size     = (int) filesize( $file );
			$bytes   += $size;
			$unused[] = [
				'id'       => (int) $id,
				'url'      => wp_get_attachment_url( $id ),
				'filename' => basename( $file ),
				'bytes'    => $size,
				'uploaded' => get_post_field( 'post_date', $id ),
				'parent'   => (int) wp_get_post_parent_id( $id ) ?: null,
			];
		}

		$next = $offset + count( $q->posts );

		return [
			'scanned'          => count( $q->posts ),
			'total'            => (int) $q->found_posts,
			'unused'           => $unused,
			'missing_files'    => $missing,
			'reclaimable_bytes'=> $bytes,
			'reclaimable'      => size_format( $bytes ),
			'next_offset'      => $next < (int) $q->found_posts ? $next : null,
			'caveat'           => 'Detection covers featured images, product galleries, post content and common builder layouts. An image used only from a theme option, a widget, or a CSS file will look unused here, so check before deleting.',
			'next_step'        => $next < (int) $q->found_posts ? sprintf( 'Call again with offset=%d for the next batch.', $next ) : 'Scan complete.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_bulk_set_image_alt( $args ) {
		$template = trim( (string) $args['template'] );
		if ( '' === $template ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'template must not be empty.', 'Try "{parent_title} - {title}" or "{parent_title} product photo".' );
		}

		$dry     = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$missing = ! isset( $args['only_missing'] ) || WPMCP_Util::bool( $args['only_missing'], true );
		$limit   = min( max( 1, (int) ( $args['limit'] ?? 100 ) ), 1000 );
		$offset  = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$query = [
			'post_type'              => 'attachment',
			'post_mime_type'         => 'image',
			'post_status'            => 'inherit',
			'posts_per_page'         => $limit,
			'offset'                 => $offset,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'update_post_term_cache' => false,
		];
		if ( ! empty( $args['parent_post_id'] ) ) {
			$query['post_parent'] = (int) $args['parent_post_id'];
		}
		if ( $missing ) {
			$query['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				[
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				],
			];
		}

		$parent_type = (string) ( $args['parent_post_type'] ?? '' );
		$q           = new WP_Query( $query );
		$planned     = [];
		$written     = 0;
		$skipped     = 0;

		foreach ( $q->posts as $id ) {
			$parent_id = (int) wp_get_post_parent_id( $id );
			if ( '' !== $parent_type && ( ! $parent_id || get_post_type( $parent_id ) !== $parent_type ) ) {
				$skipped++;
				continue;
			}
			$alt = $this->render_alt_template( $template, (int) $id, $parent_id );
			if ( '' === $alt ) {
				$skipped++;
				continue;
			}
			$planned[] = [
				'id'       => (int) $id,
				'filename' => basename( (string) get_attached_file( $id ) ),
				'parent'   => $parent_id ?: null,
				'alt'      => $alt,
			];
			if ( ! $dry ) {
				WPMCP_Journal::post_meta( (int) $id, '_wp_attachment_image_alt' );
				update_post_meta( (int) $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
				$written++;
			}
		}

		$next = $offset + count( $q->posts );

		return [
			'dry_run'     => $dry,
			'matched'     => count( $planned ),
			'written'     => $written,
			'skipped'     => $skipped,
			'total'       => (int) $q->found_posts,
			'changes'     => $planned,
			'next_offset' => $next < (int) $q->found_posts ? $next : null,
			'next_step'   => $dry
				? 'Dry run: nothing was written. Check the alt text reads naturally, then call again with dry_run=false.'
				: ( $next < (int) $q->found_posts ? sprintf( 'Call again with offset=%d for the next batch.', $next ) : 'All matching images now have alt text.' ),
		];
	}

	/**
	 * Fill an alt-text template for one attachment.
	 *
	 * @param string $template  Template string.
	 * @param int    $id        Attachment ID.
	 * @param int    $parent_id Parent post ID, or 0.
	 * @return string
	 */
	private function render_alt_template( $template, $id, $parent_id ) {
		$category = '';
		if ( $parent_id ) {
			$taxonomy = 'product' === get_post_type( $parent_id ) ? 'product_cat' : 'category';
			$terms    = get_the_terms( $parent_id, $taxonomy );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$category = $terms[0]->name;
			}
		}

		$values = [
			'{title}'        => get_the_title( $id ),
			'{filename}'     => str_replace( [ '-', '_' ], ' ', preg_replace( '/\.[^.]+$/', '', basename( (string) get_attached_file( $id ) ) ) ),
			'{parent_title}' => $parent_id ? get_the_title( $parent_id ) : '',
			'{caption}'      => (string) get_post_field( 'post_excerpt', $id ),
			'{site}'         => get_bloginfo( 'name' ),
			'{category}'     => $category,
		];

		$alt = strtr( $template, $values );
		// Collapse the separators left behind by an empty placeholder.
		$alt = preg_replace( '/\s*[-|–]\s*[-|–]\s*/', ' - ', $alt );
		$alt = trim( preg_replace( '/\s+/', ' ', $alt ), " -|–\t\n" );

		return sanitize_text_field( $alt );
	}
}
