<?php
/**
 * Small shared helpers used across tools: multibyte-safe counting, value
 * coercion, and PHP syntax validation.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static utility helpers.
 */
class WPMCP_Util {

	/**
	 * Count words in a string in a way that survives non-Latin scripts.
	 *
	 * str_word_count() only understands ASCII letters, so CJK, Devanagari,
	 * Cyrillic, Arabic and friends all count as zero words, which made every
	 * such page look like thin content. This splits on Unicode whitespace and
	 * additionally counts CJK ideographs individually, since they are not
	 * space-separated.
	 *
	 * @param string $text Plain text (tags already stripped).
	 * @return int
	 */
	public static function word_count( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}
		// CJK / Hiragana / Katakana / Hangul are counted per character.
		$cjk = 0;
		if ( preg_match_all( '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $text, $m ) ) {
			$cjk  = count( $m[0] );
			$text = preg_replace( '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u', ' ', $text );
		}
		$parts = preg_split( '/[\s\x{00A0}\x{3000}]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		$words = 0;
		foreach ( (array) $parts as $part ) {
			// Ignore fragments that carry no letters or digits at all.
			if ( preg_match( '/[\p{L}\p{N}]/u', $part ) ) {
				$words++;
			}
		}
		return $words + $cjk;
	}

	/**
	 * Character length that matches what a SERP actually truncates on.
	 * strlen() counts bytes, so a non-ASCII meta description read as 40%
	 * longer than it is and produced false "too long" warnings.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function len( $text ) {
		$text = (string) $text;
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Lowercase a string, multibyte-aware.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function lower( $text ) {
		$text = (string) $text;
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Interpret a loosely-typed argument as a boolean. MCP clients send
	 * "false", 0, "0" and false interchangeably.
	 *
	 * @param mixed $value   Incoming value.
	 * @param bool  $default Value when the key is absent/null.
	 * @return bool
	 */
	public static function bool( $value, $default = false ) {
		if ( null === $value ) {
			return $default;
		}
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, [ 'false', '0', 'no', 'off', '' ], true ) ) {
				return false;
			}
			return true;
		}
		return (bool) $value;
	}

	/**
	 * Validate PHP source before it is written to disk.
	 *
	 * A syntax error in a live theme or plugin file takes the whole site down
	 * with a white screen, and the agent then cannot call back in to fix it,
	 * since the endpoint is dead too. Parsing first turns a fatal into a tool error.
	 *
	 * @param string $code Full file contents.
	 * @return true|string True when parseable, else the parser message.
	 */
	public static function php_syntax_error( $code ) {
		$code = (string) $code;
		if ( false === strpos( $code, '<?php' ) && false === strpos( $code, '<?=' ) ) {
			// No PHP open tag: nothing for the parser to reject.
			return true;
		}
		try {
			// token_get_all() with TOKEN_PARSE runs the real parser without
			// executing anything, and throws ParseError on invalid source.
			token_get_all( $code, TOKEN_PARSE );
			return true;
		} catch ( ParseError $e ) {
			return $e->getMessage() . ' on line ' . $e->getLine();
		} catch ( Throwable $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Normalise a comma-separated string or array into a clean array.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	public static function to_array( $value ) {
		if ( is_array( $value ) ) {
			return array_values( $value );
		}
		if ( null === $value || '' === $value ) {
			return [];
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
	}

	/**
	 * Refuse meta keys that WordPress or this plugin treat as trusted internal
	 * state. Writing them through the generic meta tools would let a
	 * connection point an attachment at an arbitrary file (which the media
	 * tools later copy or delete), load a template from outside the theme, or
	 * change a user's role. Each has a dedicated, validated tool instead.
	 *
	 * @param string $key         Meta key.
	 * @param string $object_type post|term|user.
	 * @throws WPMCP_Tool_Exception When the key is protected.
	 */
	public static function assert_meta_key_allowed( $key, $object_type = 'post' ) {
		global $wpdb;
		$key     = (string) $key;
		$blocked = false;
		$hint    = 'Use the dedicated tool for this data instead of the generic meta tools.';

		if ( 0 === strpos( $key, '_wpmcp_' ) ) {
			$blocked = true;
			$hint    = 'This key holds WordPress MCP internal state. Use set_schema for JSON-LD and the media tools for image backups.';
		} elseif ( 'post' === $object_type && in_array( $key, [ '_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_backup_sizes', '_wp_page_template' ], true ) ) {
			$blocked = true;
			$hint    = '_wp_page_template: use update_content with the template argument. Attachment files: use the media tools (upload_media, optimize_image, restore_image).';
		} elseif ( 'user' === $object_type && ( in_array( $key, [ 'session_tokens', $wpdb->prefix . 'capabilities', $wpdb->prefix . 'user_level' ], true ) || preg_match( '/capabilities$|user_level$/', $key ) ) ) {
			$blocked = true;
			$hint    = 'Change roles with save_user.';
		}

		if ( $blocked ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				sprintf( 'The meta key "%s" is protected and cannot be written through this tool.', $key ),
				$hint,
				[ 'key' => $key ]
			);
		}
	}

	/**
	 * Whether a path is a real file or directory inside the uploads folder.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public static function in_uploads( $path ) {
		$real = realpath( (string) $path );
		$base = realpath( wp_upload_dir( null, false )['basedir'] );
		return $real && $base && 0 === strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $base ) ) );
	}

	/**
	 * Resolve a free-form value argument.
	 *
	 * Free-form arguments are advertised as strings, because several model
	 * providers reject a property schema with no type. A client that can send
	 * a native JSON value still may; one limited to strings sends JSON text
	 * with format=json. Decoding is opt-in so a literal JSON string stored in
	 * meta is never silently turned into an array.
	 *
	 * @param mixed  $value  Raw argument.
	 * @param string $format raw|json.
	 * @param string $key    Argument name, for the error message.
	 * @return mixed
	 * @throws WPMCP_Tool_Exception When format=json and the text is not JSON.
	 */
	public static function decode_value( $value, $format, $key ) {
		if ( 'json' !== $format || ! is_string( $value ) ) {
			return $value;
		}
		$decoded = json_decode( $value, true );
		if ( null === $decoded && 'null' !== trim( $value ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '"%s" is not valid JSON: %s.', $key, json_last_error_msg() ),
				sprintf( 'Send "%s" as JSON text, or set value_format=raw to store the string as-is.', $key ),
				[ 'argument' => $key ]
			);
		}
		return $decoded;
	}

	/**
	 * Strip a leading prefix from a string.
	 *
	 * ltrim() takes a character list, not a prefix, so ltrim( 'completed',
	 * 'wc-' ) returns 'ompleted'. This does what the caller actually means.
	 *
	 * @param string $value  Subject.
	 * @param string $prefix Prefix to remove when present.
	 * @return string
	 */
	public static function unprefix( $value, $prefix ) {
		$value  = (string) $value;
		$prefix = (string) $prefix;
		if ( '' !== $prefix && 0 === strpos( $value, $prefix ) ) {
			return substr( $value, strlen( $prefix ) );
		}
		return $value;
	}

	/**
	 * Resolve an ISO-ish date string to a WordPress-safe 'Y-m-d H:i:s'.
	 *
	 * @param string $value Date string.
	 * @return string Empty when unparseable.
	 */
	public static function date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}
}
