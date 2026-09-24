<?php
/**
 * Product structured data.
 *
 * A bare Product/Offer pair validates but earns nothing: Google's product rich
 * results and Merchant Center free listings both want an identifier, a brand,
 * a price validity window, and shipping and returns policy. This builds the
 * full object from what WooCommerce already knows, plus the few policy facts
 * only the merchant can supply.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds Schema.org Product and ProductGroup JSON-LD.
 */
class WPMCP_Schema {

	/**
	 * Option holding site-wide shipping/returns/brand defaults, so the policy
	 * facts only have to be given once.
	 */
	const DEFAULTS_OPTION = 'wpmcp_product_schema_defaults';

	/**
	 * Taxonomies a brand is commonly stored in, across the popular brand plugins.
	 *
	 * @var array<int,string>
	 */
	const BRAND_TAXONOMIES = [ 'product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand' ];

	/**
	 * Stored defaults, merged over the empty shape.
	 *
	 * @return array
	 */
	public static function defaults() {
		$saved = get_option( self::DEFAULTS_OPTION, [] );
		return is_array( $saved ) ? $saved : [];
	}

	/**
	 * Persist defaults for later runs.
	 *
	 * @param array $values Options to remember.
	 */
	public static function save_defaults( $values ) {
		update_option( self::DEFAULTS_OPTION, $values );
	}

	/**
	 * Build JSON-LD for one product.
	 *
	 * @param int   $post_id Product post ID.
	 * @param array $opts    Policy and inclusion options.
	 * @return array{schema:array,warnings:array<int,string>}
	 * @throws WPMCP_Tool_Exception When the post is not a WooCommerce product.
	 */
	public static function product( $post_id, $opts = [] ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::DEPENDENCY_MISSING,
				'WooCommerce is not active, so product schema cannot be built from the catalogue.',
				'Use generate_schema with type=Product for a plain, non-WooCommerce product page.'
			);
		}
		$product = wc_get_product( (int) $post_id );
		if ( ! $product ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Product %d was not found.', (int) $post_id ), 'Use list_products to find the right ID.' );
		}

		$opts     = array_merge( self::defaults(), array_filter( $opts, function ( $v ) { return null !== $v && '' !== $v && [] !== $v; } ) );
		$warnings = [];
		$currency = get_option( 'woocommerce_currency', 'USD' );
		$url      = get_permalink( $post_id );

		$description = wp_strip_all_tags( $product->get_short_description() );
		if ( '' === trim( $description ) ) {
			$description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $product->get_description() ) ), 50, '' );
		}
		if ( '' === trim( $description ) ) {
			$warnings[] = 'The product has no description, so schema.org description is empty. Rich results need one.';
		}

		$is_group = $product->is_type( 'variable' );

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => $is_group ? 'ProductGroup' : 'Product',
			'@id'         => $url . '#product',
			'name'        => $product->get_name(),
			'description' => $description,
			'url'         => $url,
		];

		$images = self::images( $product );
		if ( $images ) {
			$schema['image'] = $images;
		} else {
			$warnings[] = 'No product image. Google will not show a product rich result without one.';
		}

		$sku = $product->get_sku();
		if ( $sku ) {
			$schema['sku'] = $sku;
		} else {
			$warnings[] = 'No SKU. Set one, or supply mpn/gtin, so the product has a stable identifier.';
		}

		$mpn = (string) ( $opts['mpn'] ?? get_post_meta( $post_id, '_mpn', true ) );
		if ( '' !== $mpn ) {
			$schema['mpn'] = $mpn;
		}

		$gtin = self::gtin( $product, (string) ( $opts['gtin'] ?? '' ) );
		if ( '' !== $gtin ) {
			// Google accepts the length-specific property; gtin is the modern
			// catch-all and is what Merchant Center reads.
			$schema['gtin'] = $gtin;
			$length         = strlen( preg_replace( '/\D/', '', $gtin ) );
			if ( in_array( $length, [ 8, 12, 13, 14 ], true ) ) {
				$schema[ 'gtin' . $length ] = $gtin;
			}
		} elseif ( '' === $mpn && ! $sku ) {
			$warnings[] = 'No identifier at all (gtin, mpn or sku). Merchant Center will reject the item.';
		}

		$brand = self::brand( $product, (string) ( $opts['brand'] ?? '' ) );
		if ( '' !== $brand ) {
			$schema['brand'] = [ '@type' => 'Brand', 'name' => $brand ];
		} else {
			$warnings[] = 'No brand found. Pass brand=, or set a brand taxonomy term, so the rich result can show it.';
		}

		$categories = wp_get_post_terms( $post_id, 'product_cat', [ 'fields' => 'names' ] );
		if ( $categories && ! is_wp_error( $categories ) ) {
			$schema['category'] = implode( ' > ', array_reverse( $categories ) );
		}

		foreach ( self::attribute_properties( $product ) as $key => $value ) {
			$schema[ $key ] = $value;
		}

		if ( $product->get_weight() ) {
			$schema['weight'] = [
				'@type'    => 'QuantitativeValue',
				'value'    => (float) $product->get_weight(),
				'unitCode' => self::weight_unit_code(),
			];
		}

		// --- Offers ---------------------------------------------------------
		if ( $is_group ) {
			$schema['productGroupID'] = $sku ? $sku : (string) $post_id;
			$varies                   = self::varies_by( $product );
			if ( $varies ) {
				$schema['variesBy'] = $varies;
			}
			$variants = self::variants( $product, $opts, $currency, $url );
			if ( $variants ) {
				$schema['hasVariant'] = $variants;
			} else {
				$warnings[] = 'This variable product has no purchasable variations, so no offers could be built.';
			}
		} else {
			$offer = self::offer( $product, $opts, $currency, $url );
			if ( $offer ) {
				$schema['offers'] = $offer;
			} else {
				$warnings[] = 'The product has no price, so no Offer was written. Rich results require one.';
			}
		}

		// --- Ratings and reviews --------------------------------------------
		if ( $product->get_review_count() ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (int) $product->get_review_count(),
				'bestRating'  => '5',
				'worstRating' => '1',
			];
			if ( false !== ( $opts['include_reviews'] ?? true ) ) {
				$reviews = self::reviews( $post_id, (int) ( $opts['max_reviews'] ?? 5 ) );
				if ( $reviews ) {
					$schema['review'] = $reviews;
				}
			}
		}

		if ( ! isset( $opts['shipping'] ) ) {
			$warnings[] = 'No shipping policy given. Merchant Center free listings want shippingDetails. Pass shipping={ rate, country, transit_days_min, transit_days_max }, or set it once with save_defaults=true.';
		}
		if ( ! isset( $opts['returns'] ) ) {
			$warnings[] = 'No return policy given. Pass returns={ days, country, fees } so hasMerchantReturnPolicy can be written.';
		}

		return [ 'schema' => $schema, 'warnings' => $warnings ];
	}

	/**
	 * Main image plus gallery, as absolute URLs.
	 *
	 * @param WC_Product $product Product.
	 * @return array<int,string>
	 */
	private static function images( $product ) {
		$ids = array_filter( array_merge( [ (int) $product->get_image_id() ], array_map( 'intval', $product->get_gallery_image_ids() ) ) );
		$out = [];
		foreach ( array_unique( $ids ) as $id ) {
			$url = wp_get_attachment_url( $id );
			if ( $url ) {
				$out[] = $url;
			}
		}
		return $out;
	}

	/**
	 * The product's GTIN, from the option, WooCommerce's own field, or the meta
	 * keys the common GTIN plugins use.
	 *
	 * @param WC_Product $product  Product.
	 * @param string     $override Caller-supplied value.
	 * @return string
	 */
	private static function gtin( $product, $override ) {
		if ( '' !== $override ) {
			return $override;
		}
		// WooCommerce 9.2+ stores a global unique ID natively.
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$native = (string) $product->get_global_unique_id();
			if ( '' !== $native ) {
				return $native;
			}
		}
		foreach ( [ '_global_unique_id', '_wpm_gtin_code', 'hwp_product_gtin', '_gtin', 'gtin' ] as $key ) {
			$value = (string) get_post_meta( $product->get_id(), $key, true );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * The product's brand, from the override, a brand taxonomy, or a meta key.
	 *
	 * @param WC_Product $product  Product.
	 * @param string     $override Caller-supplied value.
	 * @return string
	 */
	private static function brand( $product, $override ) {
		if ( '' !== $override ) {
			return $override;
		}
		foreach ( self::BRAND_TAXONOMIES as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $product->get_id(), $taxonomy );
			if ( $terms && ! is_wp_error( $terms ) ) {
				return $terms[0]->name;
			}
		}
		$attribute = $product->get_attribute( 'pa_brand' );
		if ( $attribute ) {
			return explode( ',', $attribute )[0];
		}
		return '';
	}

	/**
	 * Map colour, size and material attributes onto their schema.org properties.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,string>
	 */
	private static function attribute_properties( $product ) {
		$out = [];
		foreach ( [ 'color' => 'color', 'colour' => 'color', 'size' => 'size', 'material' => 'material', 'pattern' => 'pattern' ] as $slug => $property ) {
			if ( isset( $out[ $property ] ) ) {
				continue;
			}
			$value = $product->get_attribute( 'pa_' . $slug );
			if ( ! $value ) {
				$value = $product->get_attribute( $slug );
			}
			if ( $value ) {
				$out[ $property ] = trim( explode( ',', $value )[0] );
			}
		}
		return $out;
	}

	/**
	 * UN/CEFACT code for the store's weight unit.
	 *
	 * @return string
	 */
	private static function weight_unit_code() {
		$map = [ 'kg' => 'KGM', 'g' => 'GRM', 'lbs' => 'LBR', 'oz' => 'ONZ' ];
		return $map[ get_option( 'woocommerce_weight_unit', 'kg' ) ] ?? 'KGM';
	}

	/**
	 * Which attributes a variable product varies on, as schema.org properties.
	 *
	 * @param WC_Product $product Variable product.
	 * @return array<int,string>
	 */
	private static function varies_by( $product ) {
		$map = [ 'color' => 'color', 'colour' => 'color', 'size' => 'size', 'material' => 'material', 'pattern' => 'pattern' ];
		$out = [];
		foreach ( $product->get_attributes() as $key => $attribute ) {
			$is_variation = is_object( $attribute ) && method_exists( $attribute, 'get_variation' ) ? $attribute->get_variation() : true;
			if ( ! $is_variation ) {
				continue;
			}
			$slug = str_replace( 'pa_', '', sanitize_title( (string) $key ) );
			if ( isset( $map[ $slug ] ) && ! in_array( $map[ $slug ], $out, true ) ) {
				$out[] = $map[ $slug ];
			}
		}
		return $out;
	}

	/**
	 * Each purchasable variation as its own Product with its own Offer.
	 *
	 * @param WC_Product $product  Variable product.
	 * @param array      $opts     Policy options.
	 * @param string     $currency Store currency.
	 * @param string     $url      Product URL.
	 * @return array<int,array>
	 */
	private static function variants( $product, $opts, $currency, $url ) {
		$out = [];
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}
			$variant = [
				'@type' => 'Product',
				'name'  => $variation->get_name(),
				'sku'   => $variation->get_sku() ? $variation->get_sku() : (string) $child_id,
				'url'   => $variation->get_permalink(),
			];
			$image = $variation->get_image_id();
			if ( $image ) {
				$variant['image'] = wp_get_attachment_url( $image );
			}
			foreach ( $variation->get_attributes() as $key => $value ) {
				$slug = str_replace( 'pa_', '', sanitize_title( (string) $key ) );
				if ( in_array( $slug, [ 'color', 'colour' ], true ) ) {
					$variant['color'] = (string) $value;
				} elseif ( 'size' === $slug ) {
					$variant['size'] = (string) $value;
				} elseif ( 'material' === $slug ) {
					$variant['material'] = (string) $value;
				}
			}
			$offer = self::offer( $variation, $opts, $currency, $variation->get_permalink() );
			if ( $offer ) {
				$variant['offers'] = $offer;
			}
			$out[] = $variant;
		}
		// Keep the payload sane on catalogues with very deep variation matrices.
		return array_slice( $out, 0, 50 );
	}

	/**
	 * One Offer for a product or variation.
	 *
	 * @param WC_Product $product  Product or variation.
	 * @param array      $opts     Policy options.
	 * @param string     $currency Store currency.
	 * @param string     $url      Offer URL.
	 * @return array|null
	 */
	private static function offer( $product, $opts, $currency, $url ) {
		$price = $product->get_price();
		if ( '' === (string) $price ) {
			return null;
		}

		$offer = [
			'@type'           => 'Offer',
			'url'             => $url,
			'price'           => wc_format_decimal( $price, wc_get_price_decimals() ),
			'priceCurrency'   => $currency,
			'availability'    => self::availability( $product ),
			'itemCondition'   => 'https://schema.org/' . ( (string) ( $opts['condition'] ?? 'NewCondition' ) ),
			'priceValidUntil' => self::price_valid_until( $product, $opts ),
		];

		$seller = (string) ( $opts['seller'] ?? get_bloginfo( 'name' ) );
		if ( '' !== $seller ) {
			$offer['seller'] = [ '@type' => 'Organization', 'name' => $seller ];
		}

		$shipping = self::shipping_details( $opts, $currency );
		if ( $shipping ) {
			$offer['shippingDetails'] = $shipping;
		}
		$returns = self::return_policy( $opts );
		if ( $returns ) {
			$offer['hasMerchantReturnPolicy'] = $returns;
		}

		return $offer;
	}

	/**
	 * Availability URL for a product's stock state.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function availability( $product ) {
		if ( ! $product->is_in_stock() ) {
			return 'https://schema.org/OutOfStock';
		}
		if ( $product->is_on_backorder( 1 ) ) {
			return 'https://schema.org/BackOrder';
		}
		if ( $product->is_type( 'external' ) ) {
			return 'https://schema.org/InStock';
		}
		return 'https://schema.org/InStock';
	}

	/**
	 * When the quoted price stops being guaranteed. Google warns without it.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $opts    Options carrying price_valid_days.
	 * @return string ISO 8601 date.
	 */
	private static function price_valid_until( $product, $opts ) {
		$sale_end = $product->get_date_on_sale_to();
		if ( $sale_end ) {
			return gmdate( 'Y-m-d', $sale_end->getTimestamp() );
		}
		$days = (int) ( $opts['price_valid_days'] ?? 365 );
		return gmdate( 'Y-m-d', time() + ( max( 1, $days ) * DAY_IN_SECONDS ) );
	}

	/**
	 * OfferShippingDetails from the merchant's stated policy.
	 *
	 * @param array  $opts     Options carrying shipping.
	 * @param string $currency Store currency.
	 * @return array|null
	 */
	private static function shipping_details( $opts, $currency ) {
		$shipping = $opts['shipping'] ?? null;
		if ( ! is_array( $shipping ) || ! $shipping ) {
			return null;
		}

		$rate = isset( $shipping['rate'] ) ? (float) $shipping['rate'] : 0.0;

		$details = [
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => [
				'@type'    => 'MonetaryAmount',
				'value'    => (string) $rate,
				'currency' => (string) ( $shipping['currency'] ?? $currency ),
			],
			'shippingDestination' => [
				'@type'          => 'DefinedRegion',
				'addressCountry' => (string) ( $shipping['country'] ?? WC()->countries->get_base_country() ),
			],
			'deliveryTime'        => [
				'@type'         => 'ShippingDeliveryTime',
				'handlingTime'  => [
					'@type'    => 'QuantitativeValue',
					'minValue' => (int) ( $shipping['handling_days_min'] ?? 0 ),
					'maxValue' => (int) ( $shipping['handling_days_max'] ?? 1 ),
					'unitCode' => 'DAY',
				],
				'transitTime'   => [
					'@type'    => 'QuantitativeValue',
					'minValue' => (int) ( $shipping['transit_days_min'] ?? 1 ),
					'maxValue' => (int) ( $shipping['transit_days_max'] ?? 5 ),
					'unitCode' => 'DAY',
				],
			],
		];

		if ( isset( $shipping['free_over'] ) ) {
			$details['shippingRate']['freeShippingThreshold'] = [
				'@type'    => 'MonetaryAmount',
				'value'    => (string) (float) $shipping['free_over'],
				'currency' => (string) ( $shipping['currency'] ?? $currency ),
			];
		}

		return $details;
	}

	/**
	 * MerchantReturnPolicy from the merchant's stated policy.
	 *
	 * @param array $opts Options carrying returns.
	 * @return array|null
	 */
	private static function return_policy( $opts ) {
		$returns = $opts['returns'] ?? null;
		if ( ! is_array( $returns ) || ! $returns ) {
			return null;
		}

		$days = (int) ( $returns['days'] ?? 30 );
		if ( $days <= 0 ) {
			return [
				'@type'                => 'MerchantReturnPolicy',
				'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
				'applicableCountry'    => (string) ( $returns['country'] ?? WC()->countries->get_base_country() ),
			];
		}

		$fees = strtolower( (string) ( $returns['fees'] ?? 'free' ) );

		return [
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => (string) ( $returns['country'] ?? WC()->countries->get_base_country() ),
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
			'merchantReturnDays'   => $days,
			'returnMethod'         => 'https://schema.org/' . ( (string) ( $returns['method'] ?? 'ReturnByMail' ) ),
			'returnFees'           => 'free' === $fees
				? 'https://schema.org/FreeReturn'
				: 'https://schema.org/ReturnShippingFees',
		];
	}

	/**
	 * The most recent approved reviews, as Review objects.
	 *
	 * @param int $post_id Product ID.
	 * @param int $limit   How many to include.
	 * @return array<int,array>
	 */
	private static function reviews( $post_id, $limit ) {
		$limit = max( 0, min( $limit, 20 ) );
		if ( ! $limit ) {
			return [];
		}
		$comments = get_comments(
			[
				'post_id' => (int) $post_id,
				'status'  => 'approve',
				'type'    => 'review',
				'number'  => $limit,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			]
		);

		$out = [];
		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			if ( ! $rating ) {
				continue;
			}
			$out[] = [
				'@type'         => 'Review',
				'reviewRating'  => [
					'@type'       => 'Rating',
					'ratingValue' => (string) $rating,
					'bestRating'  => '5',
					'worstRating' => '1',
				],
				'author'        => [ '@type' => 'Person', 'name' => $comment->comment_author ],
				'datePublished' => gmdate( 'Y-m-d', strtotime( $comment->comment_date_gmt ) ),
				'reviewBody'    => wp_strip_all_tags( $comment->comment_content ),
			];
		}
		return $out;
	}
}
