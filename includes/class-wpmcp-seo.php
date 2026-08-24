<?php
/**
 * Engine-agnostic SEO layer. Detects Yoast SEO or Rank Math and reads/writes a
 * single normalised field set so the MCP client never has to know which plugin
 * is installed.
 *
 * Normalised SEO fields:
 *   title, description, focus_keyword, canonical, noindex (bool),
 *   nofollow (bool), og_title, og_description, og_image, twitter_title,
 *   twitter_description, twitter_image
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO provider abstraction.
 */
class WPMCP_SEO {

	/**
	 * Detect the active SEO engine.
	 *
	 * @return string yoast|rankmath|none
	 */
	public static function provider() {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		return 'none';
	}

	/**
	 * Human-readable engine status, for diagnostics.
	 *
	 * @return array
	 */
	public static function status() {
		$provider = self::provider();
		return [
			'active_provider' => $provider,
			'yoast_active'    => ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ),
			'rankmath_active' => ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ),
			'note'            => 'none' === $provider
				? 'No supported SEO plugin detected. SEO writes are stored as standard post meta but may not render until Yoast or Rank Math is active.'
				: sprintf( 'Writing through %s field keys.', $provider ),
		];
	}

	/**
	 * Read normalised SEO fields for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_post_seo( $post_id ) {
		$post_id  = (int) $post_id;
		$provider = self::provider();

		if ( 'rankmath' === $provider ) {
			$robots = get_post_meta( $post_id, 'rank_math_robots', true );
			$robots = is_array( $robots ) ? $robots : [];
			return [
				'provider'            => 'rankmath',
				'title'               => (string) get_post_meta( $post_id, 'rank_math_title', true ),
				'description'         => (string) get_post_meta( $post_id, 'rank_math_description', true ),
				'focus_keyword'       => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
				'canonical'           => (string) get_post_meta( $post_id, 'rank_math_canonical_url', true ),
				'noindex'             => in_array( 'noindex', $robots, true ),
				'nofollow'            => in_array( 'nofollow', $robots, true ),
				'og_title'            => (string) get_post_meta( $post_id, 'rank_math_facebook_title', true ),
				'og_description'      => (string) get_post_meta( $post_id, 'rank_math_facebook_description', true ),
				'twitter_title'       => (string) get_post_meta( $post_id, 'rank_math_twitter_title', true ),
				'twitter_description' => (string) get_post_meta( $post_id, 'rank_math_twitter_description', true ),
				'og_image'            => (string) get_post_meta( $post_id, 'rank_math_facebook_image', true ),
				'og_image_id'         => (int) get_post_meta( $post_id, 'rank_math_facebook_image_id', true ),
				'twitter_image'       => (string) get_post_meta( $post_id, 'rank_math_twitter_image', true ),
				'twitter_image_id'    => (int) get_post_meta( $post_id, 'rank_math_twitter_image_id', true ),
			];
		}

		// Yoast (and the "none" fallback, which uses Yoast-style keys).
		$noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		return [
			'provider'            => $provider,
			'title'               => (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ),
			'description'         => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
			'focus_keyword'       => (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
			'canonical'           => (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
			'noindex'             => '1' === (string) $noindex,
			'nofollow'            => '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ),
			'og_title'            => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ),
			'og_description'      => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ),
			'twitter_title'       => (string) get_post_meta( $post_id, '_yoast_wpseo_twitter-title', true ),
			'twitter_description' => (string) get_post_meta( $post_id, '_yoast_wpseo_twitter-description', true ),
			'og_image'            => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true ),
			'og_image_id'         => (int) get_post_meta( $post_id, '_yoast_wpseo_opengraph-image-id', true ),
			'twitter_image'       => (string) get_post_meta( $post_id, '_yoast_wpseo_twitter-image', true ),
			'twitter_image_id'    => (int) get_post_meta( $post_id, '_yoast_wpseo_twitter-image-id', true ),
		];
	}

	/**
	 * Write normalised SEO fields to a post. Only supplied keys are changed.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $fields  Subset of the normalised field set.
	 * @return array The post's SEO after the write.
	 */
	public static function set_post_seo( $post_id, $fields ) {
		$post_id  = (int) $post_id;
		$provider = self::provider();
		$fields   = self::normalise_image_fields( $fields );

		if ( 'rankmath' === $provider ) {
			$map = [
				'title'               => 'rank_math_title',
				'description'         => 'rank_math_description',
				'focus_keyword'       => 'rank_math_focus_keyword',
				'canonical'           => 'rank_math_canonical_url',
				'og_title'            => 'rank_math_facebook_title',
				'og_description'      => 'rank_math_facebook_description',
				'twitter_title'       => 'rank_math_twitter_title',
				'twitter_description' => 'rank_math_twitter_description',
			];
			foreach ( $map as $field => $meta_key ) {
				if ( array_key_exists( $field, $fields ) ) {
					update_post_meta( $post_id, $meta_key, sanitize_text_field( $fields[ $field ] ) );
				}
			}
			self::write_image_field( $post_id, $fields, 'og_image', 'rank_math_facebook_image', 'rank_math_facebook_image_id' );
			self::write_image_field( $post_id, $fields, 'twitter_image', 'rank_math_twitter_image', 'rank_math_twitter_image_id' );
			if ( array_key_exists( 'noindex', $fields ) || array_key_exists( 'nofollow', $fields ) ) {
				$robots = get_post_meta( $post_id, 'rank_math_robots', true );
				$robots = is_array( $robots ) ? $robots : [];
				$robots = self::toggle_robot( $robots, 'noindex', $fields, ! empty( $fields['noindex'] ) );
				$robots = self::toggle_robot( $robots, 'nofollow', $fields, ! empty( $fields['nofollow'] ) );
				if ( ! in_array( 'noindex', $robots, true ) && ! in_array( 'index', $robots, true ) ) {
					$robots[] = 'index';
				}
				update_post_meta( $post_id, 'rank_math_robots', array_values( array_unique( $robots ) ) );
			}
		} else {
			$map = [
				'title'               => '_yoast_wpseo_title',
				'description'         => '_yoast_wpseo_metadesc',
				'focus_keyword'       => '_yoast_wpseo_focuskw',
				'canonical'           => '_yoast_wpseo_canonical',
				'og_title'            => '_yoast_wpseo_opengraph-title',
				'og_description'      => '_yoast_wpseo_opengraph-description',
				'twitter_title'       => '_yoast_wpseo_twitter-title',
				'twitter_description' => '_yoast_wpseo_twitter-description',
			];
			foreach ( $map as $field => $meta_key ) {
				if ( array_key_exists( $field, $fields ) ) {
					update_post_meta( $post_id, $meta_key, sanitize_text_field( $fields[ $field ] ) );
				}
			}
			self::write_image_field( $post_id, $fields, 'og_image', '_yoast_wpseo_opengraph-image', '_yoast_wpseo_opengraph-image-id' );
			self::write_image_field( $post_id, $fields, 'twitter_image', '_yoast_wpseo_twitter-image', '_yoast_wpseo_twitter-image-id' );
			if ( array_key_exists( 'noindex', $fields ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', ! empty( $fields['noindex'] ) ? '1' : '2' );
			}
			if ( array_key_exists( 'nofollow', $fields ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', ! empty( $fields['nofollow'] ) ? '1' : '0' );
			}
		}

		return self::get_post_seo( $post_id );
	}

	/**
	 * Read normalised SEO fields for a taxonomy term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array
	 */
	public static function get_term_seo( $term_id, $taxonomy ) {
		$term_id  = (int) $term_id;
		$provider = self::provider();

		if ( 'rankmath' === $provider ) {
			return [
				'provider'      => 'rankmath',
				'title'         => (string) get_term_meta( $term_id, 'rank_math_title', true ),
				'description'   => (string) get_term_meta( $term_id, 'rank_math_description', true ),
				'focus_keyword' => (string) get_term_meta( $term_id, 'rank_math_focus_keyword', true ),
			];
		}

		// Yoast stores term SEO in a single option keyed by taxonomy + term ID.
		$meta = get_option( 'wpseo_taxonomy_meta', [] );
		$row  = $meta[ $taxonomy ][ $term_id ] ?? [];
		return [
			'provider'      => $provider,
			'title'         => (string) ( $row['wpseo_title'] ?? '' ),
			'description'   => (string) ( $row['wpseo_desc'] ?? '' ),
			'focus_keyword' => (string) ( $row['wpseo_focuskw'] ?? '' ),
		];
	}

	/**
	 * Write normalised SEO fields to a taxonomy term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $fields   Subset of title|description|focus_keyword.
	 * @return array The term's SEO after the write.
	 */
	public static function set_term_seo( $term_id, $taxonomy, $fields ) {
		$term_id  = (int) $term_id;
		$provider = self::provider();

		if ( 'rankmath' === $provider ) {
			if ( array_key_exists( 'title', $fields ) ) {
				update_term_meta( $term_id, 'rank_math_title', sanitize_text_field( $fields['title'] ) );
			}
			if ( array_key_exists( 'description', $fields ) ) {
				update_term_meta( $term_id, 'rank_math_description', sanitize_text_field( $fields['description'] ) );
			}
			if ( array_key_exists( 'focus_keyword', $fields ) ) {
				update_term_meta( $term_id, 'rank_math_focus_keyword', sanitize_text_field( $fields['focus_keyword'] ) );
			}
			return self::get_term_seo( $term_id, $taxonomy );
		}

		$meta = get_option( 'wpseo_taxonomy_meta', [] );
		if ( ! isset( $meta[ $taxonomy ] ) ) {
			$meta[ $taxonomy ] = [];
		}
		if ( ! isset( $meta[ $taxonomy ][ $term_id ] ) ) {
			$meta[ $taxonomy ][ $term_id ] = [];
		}
		if ( array_key_exists( 'title', $fields ) ) {
			$meta[ $taxonomy ][ $term_id ]['wpseo_title'] = sanitize_text_field( $fields['title'] );
		}
		if ( array_key_exists( 'description', $fields ) ) {
			$meta[ $taxonomy ][ $term_id ]['wpseo_desc'] = sanitize_text_field( $fields['description'] );
		}
		if ( array_key_exists( 'focus_keyword', $fields ) ) {
			$meta[ $taxonomy ][ $term_id ]['wpseo_focuskw'] = sanitize_text_field( $fields['focus_keyword'] );
		}
		update_option( 'wpseo_taxonomy_meta', $meta );
		return self::get_term_seo( $term_id, $taxonomy );
	}

	/**
	 * Accept a social image as an attachment ID, an ID-carrying field, or a
	 * URL, and end up with both the URL and the ID either way. Both Yoast and
	 * Rank Math want the pair, and only having one of them is what makes a
	 * social image silently fail to render.
	 *
	 * @param array $fields Incoming normalised fields.
	 * @return array
	 */
	private static function normalise_image_fields( $fields ) {
		foreach ( [ 'og_image', 'twitter_image' ] as $field ) {
			$id_field = $field . '_id';

			if ( array_key_exists( $id_field, $fields ) && ! array_key_exists( $field, $fields ) ) {
				$url = wp_get_attachment_url( (int) $fields[ $id_field ] );
				if ( $url ) {
					$fields[ $field ] = $url;
				}
				continue;
			}
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}

			$value = $fields[ $field ];
			// A bare number means "use this attachment".
			if ( is_numeric( $value ) ) {
				$id  = (int) $value;
				$url = wp_get_attachment_url( $id );
				if ( $url ) {
					$fields[ $field ]    = $url;
					$fields[ $id_field ] = $id;
				}
				continue;
			}
			if ( ! array_key_exists( $id_field, $fields ) && is_string( $value ) && '' !== $value ) {
				$id = attachment_url_to_postid( $value );
				if ( $id ) {
					$fields[ $id_field ] = $id;
				}
			}
		}
		return $fields;
	}

	/**
	 * Write one social image URL and its attachment ID.
	 *
	 * @param int    $post_id  Post ID.
	 * @param array  $fields   Normalised fields.
	 * @param string $field    Field name (og_image|twitter_image).
	 * @param string $url_key  Provider meta key for the URL.
	 * @param string $id_key   Provider meta key for the attachment ID.
	 */
	private static function write_image_field( $post_id, $fields, $field, $url_key, $id_key ) {
		if ( array_key_exists( $field, $fields ) ) {
			$url = (string) $fields[ $field ];
			if ( '' === $url ) {
				delete_post_meta( $post_id, $url_key );
				delete_post_meta( $post_id, $id_key );
				return;
			}
			update_post_meta( $post_id, $url_key, esc_url_raw( $url ) );
		}
		if ( array_key_exists( $field . '_id', $fields ) ) {
			$id = (int) $fields[ $field . '_id' ];
			if ( $id ) {
				update_post_meta( $post_id, $id_key, $id );
			} else {
				delete_post_meta( $post_id, $id_key );
			}
		}
	}

	/**
	 * Helper to add/remove a directive from a Rank Math robots array.
	 *
	 * @param array  $robots    Current robots directives.
	 * @param string $directive Directive name.
	 * @param array  $fields    Incoming fields (presence check).
	 * @param bool   $on        Whether to enable the directive.
	 * @return array
	 */
	private static function toggle_robot( $robots, $directive, $fields, $on ) {
		if ( ! array_key_exists( $directive, $fields ) ) {
			return $robots;
		}
		$robots = array_values( array_diff( $robots, [ $directive ] ) );
		if ( $on ) {
			$robots[] = $directive;
			if ( 'noindex' === $directive ) {
				$robots = array_values( array_diff( $robots, [ 'index' ] ) );
			}
		}
		return $robots;
	}
}
