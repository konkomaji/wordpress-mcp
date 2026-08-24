<?php
/**
 * Argument validation and coercion against a tool's declared inputSchema.
 *
 * MCP clients are supposed to validate arguments before calling, but a tool on
 * a public endpoint cannot rely on that. Validating centrally means a bad call
 * fails with "id must be an integer, got string" instead of a PHP TypeError
 * five frames deep inside a WordPress core function.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and coerces tool arguments.
 */
class WPMCP_Validator {

	/**
	 * Validate arguments against a tool definition, returning coerced values.
	 *
	 * @param array $tool Tool definition (with inputSchema).
	 * @param array $args Incoming arguments.
	 * @return array Coerced arguments.
	 * @throws WPMCP_Tool_Exception When a required argument is missing or a value is the wrong shape.
	 */
	public static function check( $tool, $args ) {
		$schema = isset( $tool['inputSchema'] ) && is_array( $tool['inputSchema'] ) ? $tool['inputSchema'] : [];
		$props  = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : [];
		$req    = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : [];
		$name   = $tool['name'] ?? 'tool';

		foreach ( $req as $key ) {
			if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] || '' === $args[ $key ] ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					sprintf( 'Missing required argument "%s" for %s.', $key, $name ),
					self::describe_expectation( $key, $props[ $key ] ?? [] ),
					[ 'argument' => $key, 'required' => $req ]
				);
			}
		}

		$out = $args;
		foreach ( $args as $key => $value ) {
			if ( ! isset( $props[ $key ]['type'] ) || null === $value ) {
				continue;
			}
			$out[ $key ] = self::coerce( $name, $key, $value, (string) $props[ $key ]['type'] );
		}

		return $out;
	}

	/**
	 * Coerce a single value to its declared type, or fail with a clear message.
	 *
	 * @param string $tool  Tool name.
	 * @param string $key   Argument name.
	 * @param mixed  $value Value.
	 * @param string $type  Declared JSON Schema type.
	 * @return mixed
	 * @throws WPMCP_Tool_Exception When the value cannot be coerced.
	 */
	private static function coerce( $tool, $key, $value, $type ) {
		switch ( $type ) {
			case 'integer':
				if ( is_int( $value ) ) {
					return $value;
				}
				if ( is_numeric( $value ) && (string) (int) $value === (string) $value ) {
					return (int) $value;
				}
				if ( is_numeric( $value ) ) {
					return (int) $value;
				}
				self::type_fail( $tool, $key, 'an integer', $value );
				break;

			case 'number':
				if ( is_int( $value ) || is_float( $value ) ) {
					return $value;
				}
				if ( is_numeric( $value ) ) {
					return (float) $value;
				}
				self::type_fail( $tool, $key, 'a number', $value );
				break;

			case 'boolean':
				return WPMCP_Util::bool( $value );

			case 'string':
				if ( is_string( $value ) ) {
					return $value;
				}
				if ( is_int( $value ) || is_float( $value ) ) {
					return (string) $value;
				}
				if ( is_bool( $value ) ) {
					return $value ? 'true' : 'false';
				}
				self::type_fail( $tool, $key, 'a string', $value );
				break;

			case 'array':
				if ( is_array( $value ) ) {
					return $value;
				}
				// A comma-separated string is a common and harmless shorthand.
				if ( is_string( $value ) ) {
					return WPMCP_Util::to_array( $value );
				}
				self::type_fail( $tool, $key, 'an array', $value );
				break;

			case 'object':
				if ( is_array( $value ) ) {
					return $value;
				}
				if ( is_object( $value ) ) {
					return (array) $value;
				}
				// Some clients stringify nested objects.
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						return $decoded;
					}
				}
				self::type_fail( $tool, $key, 'an object (key/value map)', $value );
				break;
		}

		return $value;
	}

	/**
	 * Raise a consistent type error.
	 *
	 * @param string $tool     Tool name.
	 * @param string $key      Argument name.
	 * @param string $expected Human description of the expected type.
	 * @param mixed  $value    Offending value.
	 * @throws WPMCP_Tool_Exception Always.
	 */
	private static function type_fail( $tool, $key, $expected, $value ) {
		WPMCP_Errors::fail(
			WPMCP_Errors::INVALID_ARGUMENT,
			sprintf( 'Argument "%s" for %s must be %s, got %s.', $key, $tool, $expected, gettype( $value ) ),
			sprintf( 'Send "%s" as %s.', $key, $expected ),
			[ 'argument' => $key, 'expected' => $expected, 'received_type' => gettype( $value ) ]
		);
	}

	/**
	 * Build a hint describing what an argument should look like.
	 *
	 * @param string $key  Argument name.
	 * @param array  $prop Property schema.
	 * @return string
	 */
	private static function describe_expectation( $key, $prop ) {
		$type = $prop['type'] ?? 'value';
		$desc = $prop['description'] ?? '';
		$hint = sprintf( 'Pass "%s" (%s).', $key, $type );
		return $desc ? $hint . ' ' . $desc : $hint;
	}

	/**
	 * Assert that a value is one of an allowed set.
	 *
	 * @param string $key     Argument name.
	 * @param mixed  $value   Value.
	 * @param array  $allowed Allowed values.
	 * @param mixed  $default Value to return when empty; null makes it required.
	 * @return mixed
	 * @throws WPMCP_Tool_Exception When outside the set.
	 */
	public static function one_of( $key, $value, $allowed, $default = null ) {
		if ( null === $value || '' === $value ) {
			if ( null !== $default ) {
				return $default;
			}
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				sprintf( '"%s" is required.', $key ),
				sprintf( 'Allowed values: %s.', implode( ', ', $allowed ) ),
				[ 'argument' => $key, 'allowed' => $allowed ]
			);
		}
		if ( ! in_array( $value, $allowed, true ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Invalid value "%s" for "%s".', is_scalar( $value ) ? $value : gettype( $value ), $key ),
				sprintf( 'Allowed values: %s.', implode( ', ', $allowed ) ),
				[ 'argument' => $key, 'allowed' => $allowed ]
			);
		}
		return $value;
	}

	/**
	 * Fetch a post of an expected type, or fail with a useful message.
	 *
	 * @param int    $id        Post ID.
	 * @param string $post_type Expected type, or '' for any.
	 * @param string $label     Human label used in the message.
	 * @return WP_Post
	 * @throws WPMCP_Tool_Exception When missing or the wrong type.
	 */
	public static function require_post( $id, $post_type = '', $label = 'Content' ) {
		$id   = (int) $id;
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( '%s %d was not found.', $label, $id ),
				'Check the ID with list_content (or list_products for products).',
				[ 'id' => $id ]
			);
		}
		if ( $post_type && $post->post_type !== $post_type ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '%d is a "%s", not a %s.', $id, $post->post_type, $label ),
				sprintf( 'Pass the ID of a %s.', $label ),
				[ 'id' => $id, 'actual_type' => $post->post_type, 'expected_type' => $post_type ]
			);
		}
		return $post;
	}
}
