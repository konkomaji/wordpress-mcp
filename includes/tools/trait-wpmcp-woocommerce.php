<?php
/**
 * WooCommerce catalogue tools: the full product lifecycle (create, read,
 * update, delete, duplicate, bulk), variations and attributes, categories,
 * images, inventory, coupons, store settings, and reporting.
 *
 * Order and customer data lives in a separate capability group — see
 * trait-wpmcp-orders.php — because it is personal data and money movement.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `woocommerce` capability group.
 */
trait WPMCP_WooCommerce_Tools {

	/**
	 * WooCommerce catalogue tool definitions.
	 *
	 * @return array
	 */
	private function defs_woocommerce() {
		return [
			[
				'group'       => 'woocommerce',
				'name'        => 'list_products',
				'description' => 'List/search WooCommerce products with rich filters: category, tag, type, status, stock status, featured, on-sale, SKU, price range, search term, and ordering. Returns id, name, sku, type, price, stock, categories, image, and SEO field presence.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'       => [ 'type' => 'string' ],
						'sku'          => [ 'type' => 'string', 'description' => 'Exact or partial SKU.' ],
						'category'     => [ 'type' => 'string', 'description' => 'product_cat slug (comma-separated for several).' ],
						'tag'          => [ 'type' => 'string', 'description' => 'product_tag slug.' ],
						'type'         => [ 'type' => 'string', 'description' => 'simple|variable|grouped|external|variation.' ],
						'status'       => [ 'type' => 'string', 'description' => "publish|draft|pending|private|any. Default 'publish'." ],
						'stock_status' => [ 'type' => 'string', 'description' => 'instock|outofstock|onbackorder.' ],
						'featured'     => [ 'type' => 'boolean' ],
						'on_sale'      => [ 'type' => 'boolean' ],
						'min_price'    => [ 'type' => 'number' ],
						'max_price'    => [ 'type' => 'number' ],
						'orderby'      => [ 'type' => 'string', 'description' => 'date|title|price|popularity|rating|menu_order|sku.' ],
						'order'        => [ 'type' => 'string', 'description' => 'ASC|DESC.' ],
						'limit'        => [ 'type' => 'integer', 'description' => 'Default 50, max 200.' ],
						'paged'        => [ 'type' => 'integer' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'get_product',
				'description' => 'Complete product record: descriptions, type, SKU, pricing (regular/sale/sale window), full inventory state, dimensions and weight, shipping and tax class, categories/tags, attributes, variation IDs, gallery, downloads, upsells/cross-sells, ratings, and SEO fields.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'  => [ 'type' => 'integer' ],
						'sku' => [ 'type' => 'string', 'description' => 'Look the product up by SKU instead of ID.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'create_product',
				'description' => 'Create a product of any type (simple, variable, grouped, external) with the full field set in one call: content, pricing, inventory, shipping, tax, categories/tags, attributes, images, downloads, linked products, and normalised SEO fields. For a variable product, declare variation attributes here then call generate_product_variations or save_product_variation.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => self::product_write_schema( true ),
					'required'   => [ 'name' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'update_product',
				'description' => 'Update any field of an existing product: name, slug, status, visibility, featured flag, descriptions, SKU, regular/sale price and sale window, stock management, dimensions, shipping class, tax, categories/tags, attributes, images, downloads, upsells/cross-sells, purchase note, reviews, and SEO. Only supplied fields change.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => self::product_write_schema( false ),
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'delete_product',
				'description' => 'Delete a product. force=true permanently deletes (also removing its variations); otherwise it goes to trash.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer' ],
						'force' => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'duplicate_product',
				'description' => 'Duplicate a product with all meta, attributes, and variations, as a draft. Useful for building a variant of an existing listing without retyping it.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'   => [ 'type' => 'integer' ],
						'name' => [ 'type' => 'string', 'description' => 'Name for the copy.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'bulk_update_products',
				'description' => 'Apply one change across many products at once — the core of catalogue-wide merchandising. Adjust prices by percentage or fixed amount (regular or sale), start/end a sale, set stock status or quantity, change status/visibility/featured, add or remove categories/tags, or set a tax/shipping class. Target by ids or by a category/type/status query. DRY RUN BY DEFAULT.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids'             => [ 'type' => 'array', 'description' => 'Explicit product IDs. Takes precedence over the filters.' ],
						'category'        => [ 'type' => 'string', 'description' => 'Filter: product_cat slug.' ],
						'tag'             => [ 'type' => 'string', 'description' => 'Filter: product_tag slug.' ],
						'type'            => [ 'type' => 'string', 'description' => 'Filter: product type.' ],
						'status'          => [ 'type' => 'string', 'description' => "Filter: post status. Default 'publish'." ],
						'stock_status'    => [ 'type' => 'string', 'description' => 'Filter: current stock status.' ],
						'limit'           => [ 'type' => 'integer', 'description' => 'Max products to touch. Default 100, max 1000.' ],
						'price_adjust'    => [ 'type' => 'object', 'description' => 'Price change: {field: regular|sale, mode: percent|fixed|set, value: number, round: 2|0.99}. percent uses value as +/-%, fixed adds/subtracts, set assigns.' ],
						'set_sale_price'  => [ 'type' => 'string', 'description' => 'Set an explicit sale price on every matched product.' ],
						'clear_sale'      => [ 'type' => 'boolean', 'description' => 'End the sale: clears sale price and sale dates.' ],
						'sale_from'       => [ 'type' => 'string', 'description' => 'Sale start date (with a sale price).' ],
						'sale_to'         => [ 'type' => 'string', 'description' => 'Sale end date.' ],
						'set'             => [ 'type' => 'object', 'description' => 'Direct field writes: status, catalog_visibility, featured, stock_status, stock_quantity, manage_stock, backorders, tax_class, tax_status, shipping_class, purchase_note, reviews_allowed, menu_order.' ],
						'add_categories'  => [ 'type' => 'array', 'description' => 'Category slugs/ids to append.' ],
						'remove_categories' => [ 'type' => 'array', 'description' => 'Category slugs/ids to remove.' ],
						'add_tags'        => [ 'type' => 'array' ],
						'remove_tags'     => [ 'type' => 'array' ],
						'include_variations' => [ 'type' => 'boolean', 'description' => 'Also apply pricing/stock changes to each variation. Default true.' ],
						'dry_run'         => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to write.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'list_product_variations',
				'description' => 'List every variation of a variable product with its attribute combination, SKU, prices, stock, weight/dimensions, image, and enabled state.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'product_id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'product_id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'save_product_variation',
				'description' => 'Create or update a single variation of a variable product. Provide variation_id to update, or attributes (map of attribute name => value) to create. Supports SKU, regular/sale price and window, stock management, weight/dimensions, shipping class, image, description, downloads, and enabled state.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'     => [ 'type' => 'integer' ],
						'variation_id'   => [ 'type' => 'integer', 'description' => 'Omit to create a new variation.' ],
						'attributes'     => [ 'type' => 'object', 'description' => 'Map of attribute name (e.g. "pa_color" or "Size") => value slug. Empty string means "any".' ],
						'sku'            => [ 'type' => 'string' ],
						'regular_price'  => [ 'type' => 'string' ],
						'sale_price'     => [ 'type' => 'string' ],
						'sale_from'      => [ 'type' => 'string' ],
						'sale_to'        => [ 'type' => 'string' ],
						'manage_stock'   => [ 'type' => 'boolean' ],
						'stock_quantity' => [ 'type' => 'integer' ],
						'stock_status'   => [ 'type' => 'string' ],
						'backorders'     => [ 'type' => 'string', 'description' => 'no|notify|yes.' ],
						'weight'         => [ 'type' => 'string' ],
						'length'         => [ 'type' => 'string' ],
						'width'          => [ 'type' => 'string' ],
						'height'         => [ 'type' => 'string' ],
						'shipping_class' => [ 'type' => 'string', 'description' => 'Shipping class slug.' ],
						'image_id'       => [ 'type' => 'integer', 'description' => 'Existing attachment ID for the variation image.' ],
						'image'          => [ 'type' => 'object', 'description' => 'Variation image from a fresh source. Keys: id | url | base64 | path, plus filename, alt, title, caption. Downloaded, deduplicated and named after the variation.' ],
						'description'    => [ 'type' => 'string' ],
						'enabled'        => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'product_id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'bulk_assign_variation_images',
				'description' => 'Give every variation of a variable product its own image in one call, by mapping an attribute value to an image. Send { "red": "https://.../red.jpg", "blue": { "base64": "...", "filename": "blue.jpg" } } and every variation whose colour is red gets the red photo. Each image is ingested once and reused across matching variations. Dry run by default.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'    => [ 'type' => 'integer' ],
						'attribute'     => [ 'type' => 'string', 'description' => 'Attribute to match on, e.g. "pa_color" or "Color". Omit when the product has a single attribute.' ],
						'images'        => [ 'type' => 'object', 'description' => 'Map of attribute value (slug or label) => image source. Each value is a URL string, an attachment ID, or an object with id | url | base64 | path.' ],
						'add_to_gallery'=> [ 'type' => 'boolean', 'description' => 'Also append each ingested image to the parent product gallery. Default false.' ],
						'overwrite'     => [ 'type' => 'boolean', 'description' => 'Replace images on variations that already have one. Default false.' ],
						'max_dimension' => [ 'type' => 'integer', 'description' => 'Downscale incoming images. Default 2000.' ],
						'convert'       => [ 'type' => 'string', 'description' => 'webp | avif | jpg | png. Omit to keep the source format.' ],
						'dry_run'       => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to apply.' ],
					],
					'required'   => [ 'product_id', 'images' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'delete_product_variation',
				'description' => 'Permanently delete one variation of a variable product.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'variation_id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'variation_id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'generate_product_variations',
				'description' => 'Build every missing combination of a variable product\'s variation attributes in one call, optionally seeding each with a price and stock settings. Existing combinations are skipped, so it is safe to re-run after adding an attribute value.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'    => [ 'type' => 'integer' ],
						'regular_price' => [ 'type' => 'string', 'description' => 'Price to seed each new variation with.' ],
						'manage_stock'  => [ 'type' => 'boolean' ],
						'stock_quantity'=> [ 'type' => 'integer' ],
						'max'           => [ 'type' => 'integer', 'description' => 'Safety cap on how many variations to create. Default 100, max 500.' ],
					],
					'required'   => [ 'product_id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'list_product_attributes',
				'description' => 'List global product attributes (taxonomy attributes such as pa_color) with their terms, plus the attribute set of a specific product when product_id is given.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'product_id' => [ 'type' => 'integer', 'description' => 'Optional: also return this product\'s own attributes.' ] ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'save_product_attribute',
				'description' => 'Create or update a global product attribute (e.g. Colour, Size) and its terms in one call. Terms passed here are created if missing, so a whole attribute vocabulary can be set up before building variable products.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'         => [ 'type' => 'string', 'description' => 'Human label, e.g. "Colour".' ],
						'slug'         => [ 'type' => 'string', 'description' => 'Attribute slug without the pa_ prefix. Derived from name when omitted.' ],
						'terms'        => [ 'type' => 'array', 'description' => 'Term names to ensure exist, e.g. ["Red","Blue"].' ],
						'type'         => [ 'type' => 'string', 'description' => 'select|text. Default select.' ],
						'order_by'     => [ 'type' => 'string', 'description' => 'menu_order|name|name_num|id. Default menu_order.' ],
						'has_archives' => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'name' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'list_product_categories',
				'description' => 'List product categories with id, name, slug, parent, description, product count, thumbnail, display type, and SEO fields.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search' => [ 'type' => 'string' ],
						'parent' => [ 'type' => 'integer' ],
						'limit'  => [ 'type' => 'integer', 'description' => 'Default 200.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'save_product_category',
				'description' => 'Create or update a product category including its parent, description, thumbnail image, display type, and SEO fields. Provide term_id to update.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'term_id'      => [ 'type' => 'integer', 'description' => 'Omit to create.' ],
						'name'         => [ 'type' => 'string' ],
						'slug'         => [ 'type' => 'string' ],
						'parent'       => [ 'type' => 'integer' ],
						'description'  => [ 'type' => 'string' ],
						'thumbnail_id' => [ 'type' => 'integer', 'description' => 'Attachment ID for the category image.' ],
						'display_type' => [ 'type' => 'string', 'description' => 'default|products|subcategories|both.' ],
						'seo'          => [ 'type' => 'object', 'description' => 'title, description, focus_keyword.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'delete_product_category',
				'description' => 'Delete a product category. Products keep their remaining categories.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'term_id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'term_id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'manage_product_images',
				'description' => 'Set a product\'s main image and gallery. Each image can come from an existing attachment ID, a URL the server downloads, raw base64 bytes, or a file already on the server. Renames files after the product for image SEO, writes alt/title/caption, skips bytes the library already holds, and can downscale or convert to WebP on the way in. Gallery order is what you send. One bad source does not lose the rest.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'        => [ 'type' => 'integer' ],
						'main'              => [ 'type' => 'object', 'description' => 'Main product image. Keys: id | url | base64 | path, plus filename, alt, title, caption, description.' ],
						'gallery'           => [ 'type' => 'array', 'description' => 'Gallery images, in the order they should appear. Each item is either an attachment ID, a URL string, or the same object shape as main.' ],
						'mode'              => [ 'type' => 'string', 'description' => 'How gallery combines with what is already there: replace (default), append, or prepend.' ],
						'remove_ids'        => [ 'type' => 'array', 'description' => 'Attachment IDs to drop from the gallery.' ],
						'reorder'           => [ 'type' => 'array', 'description' => 'Attachment IDs in the exact order wanted. Applied after everything else.' ],
						'detach_main'       => [ 'type' => 'boolean', 'description' => 'Remove the main image entirely.' ],
						'seo_filenames'     => [ 'type' => 'boolean', 'description' => 'Rename incoming files after the product, e.g. black-cotton-hoodie-2.jpg. Default true.' ],
						'auto_alt'          => [ 'type' => 'boolean', 'description' => 'Fill any missing alt text with the product name. Default true.' ],
						'dedup'             => [ 'type' => 'boolean', 'description' => 'Reuse an existing attachment when the bytes are identical. Default true.' ],
						'max_dimension'     => [ 'type' => 'integer', 'description' => 'Downscale incoming images so neither side exceeds this. Default 2000. Pass 0 to keep full size.' ],
						'convert'           => [ 'type' => 'string', 'description' => 'Convert incoming images: webp | avif | jpg | png. Omit to keep the source format.' ],
						'quality'           => [ 'type' => 'integer', 'description' => 'Encoder quality 1-100 when resizing or converting. Default 82.' ],
						'continue_on_error' => [ 'type' => 'boolean', 'description' => 'Keep going when one source fails and report it. Default true.' ],
						'image_id'          => [ 'type' => 'integer', 'description' => 'Legacy: attachment ID for the main image.' ],
						'image_url'         => [ 'type' => 'string', 'description' => 'Legacy: URL for the main image.' ],
						'gallery_ids'       => [ 'type' => 'array', 'description' => 'Legacy: attachment IDs, replacing the gallery.' ],
						'gallery_urls'      => [ 'type' => 'array', 'description' => 'Legacy: URLs appended to the gallery.' ],
						'alt'               => [ 'type' => 'string', 'description' => 'Legacy: alt text for the main image.' ],
						'gallery_alts'      => [ 'type' => 'array', 'description' => 'Legacy: alt text per gallery image, in order.' ],
					],
					'required'   => [ 'product_id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'update_inventory',
				'description' => 'Stock control across many products or variations at once: set or adjust quantities, switch stock status, toggle stock management, set backorder policy and low-stock thresholds. Accepts a per-item list ([{id, quantity}]) or one setting applied to a filtered set.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'items'          => [ 'type' => 'array', 'description' => 'Per-item updates: [{id, quantity, stock_status, sku}]. id may be a product or variation.' ],
						'ids'            => [ 'type' => 'array', 'description' => 'Apply the same settings to these product/variation IDs.' ],
						'category'       => [ 'type' => 'string', 'description' => 'Instead of ids: every product in this category slug.' ],
						'adjust_by'      => [ 'type' => 'integer', 'description' => 'Add (or subtract) this amount from current stock.' ],
						'stock_quantity' => [ 'type' => 'integer', 'description' => 'Set an absolute quantity.' ],
						'stock_status'   => [ 'type' => 'string', 'description' => 'instock|outofstock|onbackorder.' ],
						'manage_stock'   => [ 'type' => 'boolean' ],
						'backorders'     => [ 'type' => 'string', 'description' => 'no|notify|yes.' ],
						'low_stock_amount' => [ 'type' => 'integer' ],
						'dry_run'        => [ 'type' => 'boolean', 'description' => 'Default false for explicit item lists, true for category-wide changes.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'inventory_report',
				'description' => 'Stock health across the catalogue: out-of-stock, on-backorder, below the low-stock threshold, unmanaged stock, products with no price, and total inventory value at cost of regular price.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'low_stock_threshold' => [ 'type' => 'integer', 'description' => 'Override the store setting.' ],
						'limit'               => [ 'type' => 'integer', 'description' => 'Products to scan. Default 500, max 5000.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'product_seo_audit',
				'description' => 'SEO audit across published products: missing meta description, missing focus keyword, missing image alt, thin descriptions, duplicate titles, plus commerce-specific gaps (no image, no price, no category, no short description).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'Products to scan. Default 500.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'generate_product_schema',
				'description' => 'Build Merchant-grade Product structured data for one product or the whole catalogue: identifiers (gtin, mpn, sku), brand, images, category, colour/size/material, price with a validity date, stock, shipping policy, return policy, ratings and reviews. Variable products become a ProductGroup with every variation as a hasVariant offer. Policy facts can be saved once and reused on later runs. Returns the JSON-LD; pass apply=true to store it.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'product_id'       => [ 'type' => 'integer', 'description' => 'A single product.' ],
						'ids'              => [ 'type' => 'array', 'description' => 'Several products.' ],
						'all'              => [ 'type' => 'boolean', 'description' => 'Walk every published product instead.' ],
						'limit'            => [ 'type' => 'integer', 'description' => 'With all: products per run. Default 50, max 500.' ],
						'offset'           => [ 'type' => 'integer', 'description' => 'With all: where to resume. Default 0.' ],
						'brand'            => [ 'type' => 'string', 'description' => 'Brand name, when the product has no brand taxonomy term.' ],
						'gtin'             => [ 'type' => 'string', 'description' => 'GTIN/EAN/UPC. Only sensible for a single product.' ],
						'mpn'              => [ 'type' => 'string', 'description' => 'Manufacturer part number. Only sensible for a single product.' ],
						'condition'        => [ 'type' => 'string', 'description' => 'NewCondition (default), UsedCondition, RefurbishedCondition, DamagedCondition.' ],
						'seller'           => [ 'type' => 'string', 'description' => 'Selling organisation. Defaults to the site name.' ],
						'price_valid_days' => [ 'type' => 'integer', 'description' => 'How long the price is guaranteed. Default 365.' ],
						'shipping'         => [ 'type' => 'object', 'description' => 'Shipping policy: rate, currency, country, handling_days_min/max, transit_days_min/max, free_over.' ],
						'returns'          => [ 'type' => 'object', 'description' => 'Return policy: days, country, fees (free|paid), method (ReturnByMail|ReturnInStore).' ],
						'include_reviews'  => [ 'type' => 'boolean', 'description' => 'Embed recent reviews. Default true.' ],
						'max_reviews'      => [ 'type' => 'integer', 'description' => 'How many reviews to embed. Default 5, max 20.' ],
						'save_defaults'    => [ 'type' => 'boolean', 'description' => 'Remember brand, seller, condition, shipping and returns for later runs. Default false.' ],
						'apply'            => [ 'type' => 'boolean', 'description' => 'Write the JSON-LD onto each product. Default false.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'product_seo_fix',
				'description' => 'Repair what product_seo_audit reports, in bulk: write missing SEO titles and meta descriptions from the product\'s own facts, fill missing image alt text, and generate Product schema. Only touches what is actually missing, keeps every result inside Google\'s pixel budget, and is a dry run by default so the copy can be reviewed first.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'fix'                  => [ 'type' => 'array', 'description' => 'What to repair: seo_title, meta_description, image_alt, schema, focus_keyword. Default: seo_title, meta_description, image_alt.' ],
						'ids'                  => [ 'type' => 'array', 'description' => 'Specific product IDs. Omit to walk published products.' ],
						'title_template'       => [ 'type' => 'string', 'description' => 'Default "{title} {separator} {site}". Placeholders: {title} {category} {brand} {sku} {price} {site} {tagline} {separator}.' ],
						'description_template' => [ 'type' => 'string', 'description' => 'Omit to write a description from the product\'s short description, trimmed to fit.' ],
						'separator'            => [ 'type' => 'string', 'description' => 'What {separator} renders as. Default |.' ],
						'limit'                => [ 'type' => 'integer', 'description' => 'Products per run. Default 50, max 500.' ],
						'offset'               => [ 'type' => 'integer', 'description' => 'Where to resume. Default 0.' ],
						'dry_run'              => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to apply.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'list_coupons',
				'description' => 'List discount coupons with code, type, amount, usage counts and limits, expiry, and product/category restrictions.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search' => [ 'type' => 'string' ],
						'limit'  => [ 'type' => 'integer', 'description' => 'Default 50.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'save_coupon',
				'description' => 'Create or update a discount coupon: code, discount type and amount, expiry, minimum/maximum spend, usage limits, free shipping, individual-use, and product/category include or exclude lists.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'                   => [ 'type' => 'integer', 'description' => 'Omit to create.' ],
						'code'                 => [ 'type' => 'string' ],
						'discount_type'        => [ 'type' => 'string', 'description' => 'percent|fixed_cart|fixed_product.' ],
						'amount'               => [ 'type' => 'string' ],
						'description'          => [ 'type' => 'string' ],
						'date_expires'         => [ 'type' => 'string' ],
						'individual_use'       => [ 'type' => 'boolean' ],
						'usage_limit'          => [ 'type' => 'integer' ],
						'usage_limit_per_user' => [ 'type' => 'integer' ],
						'free_shipping'        => [ 'type' => 'boolean' ],
						'minimum_amount'       => [ 'type' => 'string' ],
						'maximum_amount'       => [ 'type' => 'string' ],
						'product_ids'          => [ 'type' => 'array' ],
						'excluded_product_ids' => [ 'type' => 'array' ],
						'product_categories'   => [ 'type' => 'array', 'description' => 'Category IDs the coupon applies to.' ],
						'excluded_product_categories' => [ 'type' => 'array' ],
						'exclude_sale_items'   => [ 'type' => 'boolean' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'delete_coupon',
				'description' => 'Delete a coupon permanently.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'store_report',
				'description' => 'Aggregate store performance for a period: gross and net sales, order count, average order value, items sold, refunds, and the best-selling products. No customer-identifying data is returned.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'period'     => [ 'type' => 'string', 'description' => 'today|week|month|last_month|year|custom. Default month.' ],
						'start_date' => [ 'type' => 'string', 'description' => 'For period=custom.' ],
						'end_date'   => [ 'type' => 'string' ],
						'top_limit'  => [ 'type' => 'integer', 'description' => 'How many best-sellers to return. Default 10.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'get_store_settings',
				'description' => 'Read the store configuration an optimisation pass cares about: currency, store address, selling and shipping locations, units, stock thresholds, catalogue and review options, tax settings, and the active payment/shipping method names.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'update_store_settings',
				'description' => 'Update store configuration. Only a safe, explicit whitelist of WooCommerce settings can be written (currency, address, units, stock thresholds and notifications, catalogue behaviour, review options, tax display) — payment gateway credentials and similar are deliberately not writable here.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'settings' => [ 'type' => 'object', 'description' => 'Map of setting key => value. Call get_store_settings to see the writable keys.' ],
					],
					'required'   => [ 'settings' ],
				],
			],
		];
	}

	/**
	 * Shared property schema for create_product / update_product.
	 *
	 * @param bool $create Whether this is the create variant.
	 * @return array
	 */
	private static function product_write_schema( $create ) {
		$props = [
			'name'              => [ 'type' => 'string' ],
			'type'              => [ 'type' => 'string', 'description' => 'simple|variable|grouped|external. Default simple.' ],
			'slug'              => [ 'type' => 'string' ],
			'status'            => [ 'type' => 'string', 'description' => 'publish|draft|pending|private.' ],
			'catalog_visibility'=> [ 'type' => 'string', 'description' => 'visible|catalog|search|hidden.' ],
			'featured'          => [ 'type' => 'boolean' ],
			'description'       => [ 'type' => 'string', 'description' => 'Long description (HTML allowed).' ],
			'short_description' => [ 'type' => 'string' ],
			'sku'               => [ 'type' => 'string' ],
			'regular_price'     => [ 'type' => 'string' ],
			'sale_price'        => [ 'type' => 'string' ],
			'sale_from'         => [ 'type' => 'string', 'description' => 'Sale start date.' ],
			'sale_to'           => [ 'type' => 'string', 'description' => 'Sale end date.' ],
			'tax_status'        => [ 'type' => 'string', 'description' => 'taxable|shipping|none.' ],
			'tax_class'         => [ 'type' => 'string' ],
			'manage_stock'      => [ 'type' => 'boolean' ],
			'stock_quantity'    => [ 'type' => 'integer' ],
			'stock_status'      => [ 'type' => 'string', 'description' => 'instock|outofstock|onbackorder.' ],
			'backorders'        => [ 'type' => 'string', 'description' => 'no|notify|yes.' ],
			'low_stock_amount'  => [ 'type' => 'integer' ],
			'sold_individually' => [ 'type' => 'boolean' ],
			'weight'            => [ 'type' => 'string' ],
			'length'            => [ 'type' => 'string' ],
			'width'             => [ 'type' => 'string' ],
			'height'            => [ 'type' => 'string' ],
			'shipping_class'    => [ 'type' => 'string', 'description' => 'Shipping class slug.' ],
			'virtual'           => [ 'type' => 'boolean' ],
			'downloadable'      => [ 'type' => 'boolean' ],
			'downloads'         => [ 'type' => 'array', 'description' => 'Array of {name, file} for downloadable products.' ],
			'download_limit'    => [ 'type' => 'integer' ],
			'download_expiry'   => [ 'type' => 'integer' ],
			'categories'        => [ 'type' => 'array', 'description' => 'Category names, slugs, or IDs.' ],
			'tags'              => [ 'type' => 'array', 'description' => 'Tag names, slugs, or IDs.' ],
			'attributes'        => [ 'type' => 'array', 'description' => 'Array of {name, options[], visible, variation}. Use a global attribute slug (e.g. "pa_color") or a free-text name for a custom attribute.' ],
			'default_attributes'=> [ 'type' => 'object', 'description' => 'Map attribute name => default value, for variable products.' ],
			'image_id'          => [ 'type' => 'integer', 'description' => 'Main image attachment ID.' ],
			'gallery_ids'       => [ 'type' => 'array', 'description' => 'Gallery attachment IDs.' ],
			'upsell_ids'        => [ 'type' => 'array' ],
			'cross_sell_ids'    => [ 'type' => 'array' ],
			'grouped_products'  => [ 'type' => 'array', 'description' => 'Child product IDs for a grouped product.' ],
			'external_url'      => [ 'type' => 'string', 'description' => 'For external/affiliate products.' ],
			'button_text'       => [ 'type' => 'string' ],
			'purchase_note'     => [ 'type' => 'string' ],
			'menu_order'        => [ 'type' => 'integer' ],
			'reviews_allowed'   => [ 'type' => 'boolean' ],
			'meta'              => [ 'type' => 'object', 'description' => 'Arbitrary product meta key => value.' ],
			'seo'               => [ 'type' => 'object', 'description' => 'Normalised SEO fields (title, description, focus_keyword, …).' ],
		];
		if ( ! $create ) {
			$props = array_merge( [ 'id' => [ 'type' => 'integer' ] ], $props );
		}
		return $props;
	}

	/* =====================================================================
	 * WooCommerce handlers
	 * ===================================================================== */

	/**
	 * Ensure WooCommerce is loaded before touching its API.
	 *
	 * @throws WPMCP_Tool_Exception When WooCommerce is not active.
	 */
	private function require_woo() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::DEPENDENCY_MISSING,
				'WooCommerce is not active on this site.',
				'Install and activate WooCommerce, then call list_products again.'
			);
		}
	}

	/**
	 * Load a product object or fail with a clear message.
	 *
	 * @param int  $id       Product or variation ID.
	 * @param bool $variation Whether a variation is acceptable.
	 * @return WC_Product
	 * @throws WPMCP_Tool_Exception When not found.
	 */
	private function get_wc_product( $id, $variation = true ) {
		$this->require_woo();
		$product = wc_get_product( (int) $id );
		if ( ! $product ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Product %d was not found.', (int) $id ),
				'Use list_products to find the right ID, or pass sku to get_product.',
				[ 'id' => (int) $id ]
			);
		}
		if ( ! $variation && $product->is_type( 'variation' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '%d is a variation, not a product.', (int) $id ),
				'Pass the parent product ID, or use save_product_variation for variations.'
			);
		}
		return $product;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_products( $args ) {
		$this->require_woo();
		$query = [
			'post_type'      => 'product',
			'post_status'    => $args['status'] ?? 'publish',
			'posts_per_page' => min( (int) ( $args['limit'] ?? 50 ) ?: 50, 200 ),
			'paged'          => (int) ( $args['paged'] ?? 1 ),
			'order'          => ( isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC',
		];
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = $args['search'];
		}

		$tax_query = [];
		if ( ! empty( $args['category'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => WPMCP_Util::to_array( $args['category'] ),
			];
		}
		if ( ! empty( $args['tag'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => WPMCP_Util::to_array( $args['tag'] ),
			];
		}
		if ( ! empty( $args['type'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => WPMCP_Util::to_array( $args['type'] ),
			];
		}
		if ( WPMCP_Util::bool( $args['featured'] ?? null ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
			];
		}
		if ( $tax_query ) {
			$tax_query['relation'] = 'AND';
			$query['tax_query']    = $tax_query;
		}

		$meta_query = [];
		if ( ! empty( $args['sku'] ) ) {
			$meta_query[] = [
				'key'     => '_sku',
				'value'   => $args['sku'],
				'compare' => 'LIKE',
			];
		}
		if ( ! empty( $args['stock_status'] ) ) {
			$meta_query[] = [
				'key'   => '_stock_status',
				'value' => $args['stock_status'],
			];
		}
		if ( isset( $args['min_price'] ) || isset( $args['max_price'] ) ) {
			$price = [
				'key'     => '_price',
				'type'    => 'DECIMAL(10,2)',
				'compare' => 'BETWEEN',
				'value'   => [
					isset( $args['min_price'] ) ? (float) $args['min_price'] : 0,
					isset( $args['max_price'] ) ? (float) $args['max_price'] : PHP_INT_MAX,
				],
			];
			$meta_query[] = $price;
		}
		if ( $meta_query ) {
			$meta_query['relation'] = 'AND';
			$query['meta_query']    = $meta_query;
		}

		switch ( $args['orderby'] ?? 'date' ) {
			case 'price':
				$query['orderby']  = 'meta_value_num';
				$query['meta_key'] = '_price';
				break;
			case 'popularity':
				$query['orderby']  = 'meta_value_num';
				$query['meta_key'] = 'total_sales';
				break;
			case 'rating':
				$query['orderby']  = 'meta_value_num';
				$query['meta_key'] = '_wc_average_rating';
				break;
			case 'sku':
				$query['orderby']  = 'meta_value';
				$query['meta_key'] = '_sku';
				break;
			case 'title':
			case 'menu_order':
			case 'date':
				$query['orderby'] = $args['orderby'] ?? 'date';
				break;
		}

		if ( WPMCP_Util::bool( $args['on_sale'] ?? null ) && function_exists( 'wc_get_product_ids_on_sale' ) ) {
			$on_sale = wc_get_product_ids_on_sale();
			if ( ! $on_sale ) {
				return [ 'total' => 0, 'pages' => 0, 'items' => [] ];
			}
			$query['post__in'] = $on_sale;
		}

		$q   = new WP_Query( $query );
		$out = [];
		foreach ( $q->posts as $p ) {
			$product = wc_get_product( $p->ID );
			if ( ! $product ) {
				continue;
			}
			$seo   = WPMCP_SEO::get_post_seo( $p->ID );
			$out[] = [
				'id'                   => $p->ID,
				'name'                 => $product->get_name(),
				'sku'                  => $product->get_sku(),
				'type'                 => $product->get_type(),
				'status'               => $product->get_status(),
				'permalink'            => get_permalink( $p->ID ),
				'price'                => $product->get_price(),
				'regular_price'        => $product->get_regular_price(),
				'sale_price'           => $product->get_sale_price(),
				'on_sale'              => $product->is_on_sale(),
				'stock_status'         => $product->get_stock_status(),
				'stock_quantity'       => $product->get_stock_quantity(),
				'featured'             => $product->is_featured(),
				'categories'           => wp_get_post_terms( $p->ID, 'product_cat', [ 'fields' => 'names' ] ),
				'image'                => $product->get_image_id() ? wp_get_attachment_url( $product->get_image_id() ) : null,
				'total_sales'          => (int) get_post_meta( $p->ID, 'total_sales', true ),
				'word_count'           => WPMCP_Util::word_count( wp_strip_all_tags( $product->get_description() ) ),
				'has_meta_description' => '' !== $seo['description'],
				'has_focus_keyword'    => '' !== $seo['focus_keyword'],
			];
		}
		return [
			'total' => $q->found_posts,
			'pages' => $q->max_num_pages,
			'items' => $out,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_product( $args ) {
		$this->require_woo();
		$id = (int) ( $args['id'] ?? 0 );
		if ( ! $id && ! empty( $args['sku'] ) ) {
			$id = (int) wc_get_product_id_by_sku( (string) $args['sku'] );
			if ( ! $id ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'No product with SKU "%s".', $args['sku'] ),
					'Search for it with list_products using the sku filter.'
				);
			}
		}
		if ( ! $id ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'Pass either id or sku.', 'get_product needs one of id or sku.' );
		}
		return $this->product_payload( $this->get_wc_product( $id ) );
	}

	/**
	 * Build the full product representation returned by get_product.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private function product_payload( $product ) {
		$id       = $product->get_id();
		$image_id = $product->get_image_id();

		$attributes = [];
		foreach ( $product->get_attributes() as $attribute ) {
			$attributes[] = [
				'name'      => $attribute->get_name(),
				'label'     => wc_attribute_label( $attribute->get_name() ),
				'taxonomy'  => $attribute->is_taxonomy(),
				'visible'   => $attribute->get_visible(),
				'variation' => $attribute->get_variation(),
				'options'   => $attribute->is_taxonomy()
					? wp_get_post_terms( $id, $attribute->get_name(), [ 'fields' => 'slugs' ] )
					: $attribute->get_options(),
			];
		}

		$gallery = [];
		foreach ( $product->get_gallery_image_ids() as $gid ) {
			$gallery[] = [
				'id'  => (int) $gid,
				'url' => wp_get_attachment_url( $gid ),
				'alt' => (string) get_post_meta( $gid, '_wp_attachment_image_alt', true ),
			];
		}

		$downloads = [];
		foreach ( $product->get_downloads() as $download ) {
			$downloads[] = [
				'id'   => $download->get_id(),
				'name' => $download->get_name(),
				'file' => $download->get_file(),
			];
		}

		return [
			'id'                 => $id,
			'name'               => $product->get_name(),
			'slug'               => $product->get_slug(),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'featured'           => $product->is_featured(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'permalink'          => get_permalink( $id ),
			'sku'                => $product->get_sku(),
			'description'        => $product->get_description(),
			'short_description'  => $product->get_short_description(),
			'pricing'            => [
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'on_sale'       => $product->is_on_sale(),
				'sale_from'     => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date( 'Y-m-d' ) : null,
				'sale_to'       => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date( 'Y-m-d' ) : null,
				'currency'      => get_option( 'woocommerce_currency', 'USD' ),
			],
			'tax'                => [
				'status' => $product->get_tax_status(),
				'class'  => $product->get_tax_class(),
			],
			'inventory'          => [
				'manage_stock'     => $product->get_manage_stock(),
				'stock_quantity'   => $product->get_stock_quantity(),
				'stock_status'     => $product->get_stock_status(),
				'backorders'       => $product->get_backorders(),
				'low_stock_amount' => $product->get_low_stock_amount(),
				'sold_individually'=> $product->get_sold_individually(),
			],
			'shipping'           => [
				'weight'         => $product->get_weight(),
				'length'         => $product->get_length(),
				'width'          => $product->get_width(),
				'height'         => $product->get_height(),
				'shipping_class' => $product->get_shipping_class(),
				'virtual'        => $product->is_virtual(),
			],
			'downloadable'       => $product->is_downloadable(),
			'downloads'          => $downloads,
			'download_limit'     => $product->get_download_limit(),
			'download_expiry'    => $product->get_download_expiry(),
			'categories'         => wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] ),
			'category_ids'       => wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'ids' ] ),
			'tags'               => wp_get_post_terms( $id, 'product_tag', [ 'fields' => 'names' ] ),
			'attributes'         => $attributes,
			'default_attributes' => $product->get_default_attributes(),
			'variation_ids'      => $product->is_type( 'variable' ) ? $product->get_children() : [],
			'upsell_ids'         => $product->get_upsell_ids(),
			'cross_sell_ids'     => $product->get_cross_sell_ids(),
			'grouped_products'   => $product->is_type( 'grouped' ) ? $product->get_children() : [],
			'external_url'       => $product->is_type( 'external' ) ? $product->get_product_url() : '',
			'button_text'        => $product->is_type( 'external' ) ? $product->get_button_text() : '',
			'purchase_note'      => $product->get_purchase_note(),
			'menu_order'         => $product->get_menu_order(),
			'reviews'            => [
				'allowed'        => $product->get_reviews_allowed(),
				'count'          => $product->get_review_count(),
				'average_rating' => $product->get_average_rating(),
			],
			'total_sales'        => (int) get_post_meta( $id, 'total_sales', true ),
			'image'              => $image_id ? [
				'id'  => (int) $image_id,
				'url' => wp_get_attachment_url( $image_id ),
				'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
			] : null,
			'gallery'            => $gallery,
			'seo'                => WPMCP_SEO::get_post_seo( $id ),
			'schema'             => get_post_meta( $id, WPMCP_Frontend::JSONLD_META, true ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_create_product( $args ) {
		$this->require_woo();
		$type      = strtolower( (string) ( $args['type'] ?? 'simple' ) );
		$classes   = [
			'simple'   => 'WC_Product_Simple',
			'variable' => 'WC_Product_Variable',
			'grouped'  => 'WC_Product_Grouped',
			'external' => 'WC_Product_External',
		];
		if ( ! isset( $classes[ $type ] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Unknown product type "%s".', $type ),
				'Allowed types: ' . implode( ', ', array_keys( $classes ) ) . '.'
			);
		}
		$class   = $classes[ $type ];
		$product = new $class();
		$this->apply_product_fields( $product, $args );
		$id = $this->save_wc_object( $product, 'product' );

		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_post_seo( $id, $args['seo'] );
		}
		return [
			'success'   => true,
			'id'        => $id,
			'type'      => $type,
			'permalink' => get_permalink( $id ),
			'product'   => $this->product_payload( wc_get_product( $id ) ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_product( $args ) {
		$product = $this->get_wc_product( (int) $args['id'], false );

		// Changing type requires rebuilding the object as the new class.
		if ( ! empty( $args['type'] ) && $args['type'] !== $product->get_type() ) {
			wp_set_object_terms( $product->get_id(), sanitize_key( $args['type'] ), 'product_type' );
			$product = wc_get_product( $product->get_id() );
		}

		$this->apply_product_fields( $product, $args );
		$id = $this->save_wc_object( $product, 'product' );

		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_post_seo( $id, $args['seo'] );
		}
		return [
			'success' => true,
			'id'      => $id,
			'product' => $this->product_payload( wc_get_product( $id ) ),
		];
	}

	/**
	 * Write every supplied field onto a product object.
	 *
	 * Kept in one place so create_product and update_product cannot drift
	 * apart, and so a WooCommerce validation failure (bad price, duplicate
	 * SKU) surfaces as a typed tool error rather than an uncaught exception.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $args    Incoming arguments.
	 * @throws WPMCP_Tool_Exception On invalid values.
	 */
	private function apply_product_fields( $product, $args ) {
		$simple = [
			'name'               => 'set_name',
			'slug'               => 'set_slug',
			'status'             => 'set_status',
			'catalog_visibility' => 'set_catalog_visibility',
			'description'        => 'set_description',
			'short_description'  => 'set_short_description',
			'sku'                => 'set_sku',
			'regular_price'      => 'set_regular_price',
			'sale_price'         => 'set_sale_price',
			'tax_status'         => 'set_tax_status',
			'tax_class'          => 'set_tax_class',
			'stock_status'       => 'set_stock_status',
			'backorders'         => 'set_backorders',
			'weight'             => 'set_weight',
			'length'             => 'set_length',
			'width'              => 'set_width',
			'height'             => 'set_height',
			'shipping_class'     => 'set_shipping_class_id',
			'purchase_note'      => 'set_purchase_note',
			'external_url'       => 'set_product_url',
			'button_text'        => 'set_button_text',
		];
		$bools = [
			'featured'          => 'set_featured',
			'manage_stock'      => 'set_manage_stock',
			'sold_individually' => 'set_sold_individually',
			'virtual'           => 'set_virtual',
			'downloadable'      => 'set_downloadable',
			'reviews_allowed'   => 'set_reviews_allowed',
		];
		$ints = [
			'stock_quantity'   => 'set_stock_quantity',
			'low_stock_amount' => 'set_low_stock_amount',
			'download_limit'   => 'set_download_limit',
			'download_expiry'  => 'set_download_expiry',
			'menu_order'       => 'set_menu_order',
		];

		try {
			foreach ( $simple as $key => $setter ) {
				if ( ! isset( $args[ $key ] ) || ! method_exists( $product, $setter ) ) {
					continue;
				}
				if ( 'shipping_class' === $key ) {
					$term = get_term_by( 'slug', (string) $args[ $key ], 'product_shipping_class' );
					if ( ! $term ) {
						WPMCP_Errors::fail(
							WPMCP_Errors::NOT_FOUND,
							sprintf( 'Shipping class "%s" does not exist.', $args[ $key ] ),
							'Create it in WooCommerce > Settings > Shipping > Classes first.'
						);
					}
					$product->set_shipping_class_id( $term->term_id );
					continue;
				}
				$product->$setter( $args[ $key ] );
			}
			foreach ( $bools as $key => $setter ) {
				if ( isset( $args[ $key ] ) && method_exists( $product, $setter ) ) {
					$product->$setter( WPMCP_Util::bool( $args[ $key ] ) );
				}
			}
			foreach ( $ints as $key => $setter ) {
				if ( isset( $args[ $key ] ) && method_exists( $product, $setter ) ) {
					$product->$setter( (int) $args[ $key ] );
				}
			}
			if ( isset( $args['sale_from'] ) ) {
				$product->set_date_on_sale_from( $args['sale_from'] ? WPMCP_Util::date( $args['sale_from'] ) : '' );
			}
			if ( isset( $args['sale_to'] ) ) {
				$product->set_date_on_sale_to( $args['sale_to'] ? WPMCP_Util::date( $args['sale_to'] ) : '' );
			}
			if ( isset( $args['image_id'] ) ) {
				$product->set_image_id( (int) $args['image_id'] );
			}
			if ( isset( $args['gallery_ids'] ) ) {
				$product->set_gallery_image_ids( array_map( 'intval', WPMCP_Util::to_array( $args['gallery_ids'] ) ) );
			}
			foreach ( [ 'upsell_ids' => 'set_upsell_ids', 'cross_sell_ids' => 'set_cross_sell_ids' ] as $key => $setter ) {
				if ( isset( $args[ $key ] ) && method_exists( $product, $setter ) ) {
					$product->$setter( array_map( 'intval', WPMCP_Util::to_array( $args[ $key ] ) ) );
				}
			}
			if ( isset( $args['grouped_products'] ) && method_exists( $product, 'set_children' ) ) {
				$product->set_children( array_map( 'intval', WPMCP_Util::to_array( $args['grouped_products'] ) ) );
			}
			if ( isset( $args['categories'] ) ) {
				$product->set_category_ids( $this->resolve_term_ids( WPMCP_Util::to_array( $args['categories'] ), 'product_cat', true ) );
			}
			if ( isset( $args['tags'] ) ) {
				$product->set_tag_ids( $this->resolve_term_ids( WPMCP_Util::to_array( $args['tags'] ), 'product_tag', true ) );
			}
			if ( isset( $args['attributes'] ) ) {
				$product->set_attributes( $this->build_attributes( (array) $args['attributes'] ) );
			}
			if ( isset( $args['default_attributes'] ) ) {
				$defaults = [];
				foreach ( (array) $args['default_attributes'] as $name => $value ) {
					$defaults[ $this->normalise_attribute_key( $name ) ] = (string) $value;
				}
				$product->set_default_attributes( $defaults );
			}
			if ( isset( $args['downloads'] ) ) {
				$downloads = [];
				foreach ( (array) $args['downloads'] as $download ) {
					if ( empty( $download['file'] ) ) {
						continue;
					}
					$obj = new WC_Product_Download();
					$obj->set_name( (string) ( $download['name'] ?? basename( $download['file'] ) ) );
					$obj->set_file( (string) $download['file'] );
					$obj->set_id( md5( (string) $download['file'] ) );
					$downloads[] = $obj;
				}
				$product->set_downloads( $downloads );
			}
			if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
				foreach ( $args['meta'] as $key => $value ) {
					$product->update_meta_data( (string) $key, $value );
				}
			}
		} catch ( WPMCP_Tool_Exception $e ) {
			throw $e;
		} catch ( Throwable $e ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'WooCommerce rejected a product value: ' . $e->getMessage(),
				'Check price formats (plain numbers, no currency symbol), that the SKU is unique, and that enum values match WooCommerce (e.g. stock_status=instock).'
			);
		}
	}

	/**
	 * Persist a WooCommerce data object, converting failures into tool errors.
	 *
	 * @param WC_Data $object Object with a save() method.
	 * @param string  $label  Human label for messages.
	 * @return int Saved ID.
	 * @throws WPMCP_Tool_Exception When the save fails.
	 */
	private function save_wc_object( $object, $label = 'record' ) {
		try {
			$id = $object->save();
		} catch ( Throwable $e ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::TOOL_FAILED,
				sprintf( 'Could not save %s: %s', $label, $e->getMessage() ),
				'A duplicate SKU or an invalid price is the usual cause.'
			);
		}
		if ( ! $id ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, sprintf( 'WooCommerce did not save the %s.', $label ) );
		}
		return (int) $id;
	}

	/**
	 * Turn an attribute argument list into WC_Product_Attribute objects.
	 *
	 * @param array $input Array of {name, options, visible, variation}.
	 * @return array
	 */
	private function build_attributes( $input ) {
		$position   = 0;
		$attributes = [];
		foreach ( $input as $raw ) {
			if ( ! is_array( $raw ) || empty( $raw['name'] ) ) {
				continue;
			}
			$name      = (string) $raw['name'];
			$options   = WPMCP_Util::to_array( $raw['options'] ?? [] );
			$attribute = new WC_Product_Attribute();
			$taxonomy  = $this->normalise_attribute_key( $name );

			if ( taxonomy_exists( $taxonomy ) ) {
				$term_ids = [];
				foreach ( $options as $option ) {
					$term = get_term_by( 'slug', sanitize_title( $option ), $taxonomy );
					if ( ! $term ) {
						$term = get_term_by( 'name', $option, $taxonomy );
					}
					if ( ! $term ) {
						$created = wp_insert_term( (string) $option, $taxonomy );
						if ( ! is_wp_error( $created ) ) {
							$term_ids[] = (int) $created['term_id'];
						}
						continue;
					}
					$term_ids[] = (int) $term->term_id;
				}
				$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
				$attribute->set_name( $taxonomy );
				$attribute->set_options( $term_ids );
			} else {
				$attribute->set_id( 0 );
				$attribute->set_name( $name );
				$attribute->set_options( $options );
			}

			$attribute->set_position( $position++ );
			$attribute->set_visible( WPMCP_Util::bool( $raw['visible'] ?? true, true ) );
			$attribute->set_variation( WPMCP_Util::bool( $raw['variation'] ?? false ) );
			$attributes[] = $attribute;
		}
		return $attributes;
	}

	/**
	 * Map a human attribute name to its taxonomy key when one exists.
	 *
	 * @param string $name Attribute name or taxonomy.
	 * @return string
	 */
	private function normalise_attribute_key( $name ) {
		$name = (string) $name;
		if ( 0 === strpos( $name, 'pa_' ) ) {
			return $name;
		}
		$candidate = wc_attribute_taxonomy_name( sanitize_title( $name ) );
		return taxonomy_exists( $candidate ) ? $candidate : $name;
	}

	/**
	 * Resolve a mixed list of term ids/slugs/names into term IDs.
	 *
	 * @param array  $values   Mixed identifiers.
	 * @param string $taxonomy Taxonomy.
	 * @param bool   $create   Create missing terms by name.
	 * @return array
	 */
	private function resolve_term_ids( $values, $taxonomy, $create = false ) {
		$ids = [];
		foreach ( $values as $value ) {
			if ( is_numeric( $value ) && term_exists( (int) $value, $taxonomy ) ) {
				$ids[] = (int) $value;
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( (string) $value ), $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'name', (string) $value, $taxonomy );
			}
			if ( $term ) {
				$ids[] = (int) $term->term_id;
				continue;
			}
			if ( $create ) {
				$created = wp_insert_term( (string) $value, $taxonomy );
				if ( ! is_wp_error( $created ) ) {
					$ids[] = (int) $created['term_id'];
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_product( $args ) {
		$product = $this->get_wc_product( (int) $args['id'], false );
		$force   = WPMCP_Util::bool( $args['force'] ?? null );
		if ( $force && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child ) {
					$child->delete( true );
				}
			}
		}
		if ( ! $product->delete( $force ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'WooCommerce could not delete the product.' );
		}
		return [ 'success' => true, 'id' => (int) $args['id'], 'permanent' => $force ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_duplicate_product( $args ) {
		$product = $this->get_wc_product( (int) $args['id'], false );
		if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) {
			$path = WP_PLUGIN_DIR . '/woocommerce/includes/admin/class-wc-admin-duplicate-product.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
		if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::DEPENDENCY_MISSING,
				'WooCommerce product duplication is unavailable on this install.',
				'Use create_product with the values from get_product instead.'
			);
		}
		$duplicator = new WC_Admin_Duplicate_Product();
		$copy       = $duplicator->product_duplicate( $product );
		if ( ! $copy ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'Duplication failed.' );
		}
		if ( ! empty( $args['name'] ) ) {
			$copy->set_name( (string) $args['name'] );
			$copy->save();
		}
		return [
			'success'   => true,
			'id'        => $copy->get_id(),
			'source_id' => $product->get_id(),
			'name'      => $copy->get_name(),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_bulk_update_products( $args ) {
		$this->require_woo();
		$ids = array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) );
		if ( ! $ids ) {
			$query = [
				'post_type'      => 'product',
				'post_status'    => $args['status'] ?? 'publish',
				'posts_per_page' => min( (int) ( $args['limit'] ?? 100 ) ?: 100, 1000 ),
				'fields'         => 'ids',
			];
			$tax_query = [];
			foreach ( [ 'category' => 'product_cat', 'tag' => 'product_tag', 'type' => 'product_type' ] as $key => $tax ) {
				if ( ! empty( $args[ $key ] ) ) {
					$tax_query[] = [
						'taxonomy' => $tax,
						'field'    => 'slug',
						'terms'    => WPMCP_Util::to_array( $args[ $key ] ),
					];
				}
			}
			if ( $tax_query ) {
				$query['tax_query'] = $tax_query;
			}
			if ( ! empty( $args['stock_status'] ) ) {
				$query['meta_query'] = [
					[
						'key'   => '_stock_status',
						'value' => $args['stock_status'],
					],
				];
			}
			$q   = new WP_Query( $query );
			$ids = array_map( 'intval', $q->posts );
		}
		if ( ! $ids ) {
			return [ 'matched' => 0, 'updated' => 0, 'results' => [] ];
		}

		// A catalogue-wide price change is exactly the kind of thing that must
		// be previewed before it runs, so this defaults to a dry run.
		$dry        = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$set        = isset( $args['set'] ) && is_array( $args['set'] ) ? $args['set'] : [];
		$adjust     = isset( $args['price_adjust'] ) && is_array( $args['price_adjust'] ) ? $args['price_adjust'] : [];
		$variations = WPMCP_Util::bool( $args['include_variations'] ?? null, true );
		$results    = [];
		$updated    = 0;

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				$results[] = [ 'id' => $id, 'ok' => false, 'error' => 'not a product' ];
				continue;
			}
			$targets = [ $product ];
			if ( $variations && $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child ) {
						$targets[] = $child;
					}
				}
			}

			$changes = [];
			try {
				foreach ( $targets as $target ) {
					$before = [
						'regular_price' => $target->get_regular_price(),
						'sale_price'    => $target->get_sale_price(),
					];

					if ( $adjust ) {
						$field   = ( 'sale' === ( $adjust['field'] ?? 'regular' ) ) ? 'sale' : 'regular';
						$current = 'sale' === $field ? $target->get_sale_price() : $target->get_regular_price();
						$base    = ( '' === $current || null === $current ) ? (float) $target->get_regular_price() : (float) $current;
						$value   = (float) ( $adjust['value'] ?? 0 );
						switch ( $adjust['mode'] ?? 'percent' ) {
							case 'fixed':
								$new = $base + $value;
								break;
							case 'set':
								$new = $value;
								break;
							default:
								$new = $base * ( 1 + ( $value / 100 ) );
						}
						$new = max( 0, $new );
						if ( isset( $adjust['round'] ) && 0.99 === (float) $adjust['round'] ) {
							$new = floor( $new ) + 0.99;
						} else {
							$new = round( $new, (int) ( $adjust['round'] ?? 2 ) );
						}
						$new = wc_format_decimal( $new );
						if ( 'sale' === $field ) {
							$target->set_sale_price( $new );
						} else {
							$target->set_regular_price( $new );
						}
					}

					if ( isset( $args['set_sale_price'] ) ) {
						$target->set_sale_price( wc_format_decimal( $args['set_sale_price'] ) );
					}
					if ( WPMCP_Util::bool( $args['clear_sale'] ?? null ) ) {
						$target->set_sale_price( '' );
						$target->set_date_on_sale_from( '' );
						$target->set_date_on_sale_to( '' );
					}
					if ( ! empty( $args['sale_from'] ) ) {
						$target->set_date_on_sale_from( WPMCP_Util::date( $args['sale_from'] ) );
					}
					if ( ! empty( $args['sale_to'] ) ) {
						$target->set_date_on_sale_to( WPMCP_Util::date( $args['sale_to'] ) );
					}
					if ( $set ) {
						$this->apply_product_fields( $target, $set );
					}

					$after = [
						'regular_price' => $target->get_regular_price(),
						'sale_price'    => $target->get_sale_price(),
					];
					if ( $before !== $after ) {
						$changes[] = [
							'id'     => $target->get_id(),
							'before' => $before,
							'after'  => $after,
						];
					}
					if ( ! $dry ) {
						WPMCP_Journal::product(
							$target->get_id(),
							[ 'regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to', 'stock_status', 'catalog_visibility', 'featured' ]
						);
						$target->save();
						WPMCP_Progress::tick( 1, $target->get_name() );
					}
				}

				if ( ! $dry ) {
					foreach ( [ 'add_categories' => [ 'product_cat', true ], 'remove_categories' => [ 'product_cat', false ], 'add_tags' => [ 'product_tag', true ], 'remove_tags' => [ 'product_tag', false ] ] as $key => $conf ) {
						if ( empty( $args[ $key ] ) ) {
							continue;
						}
						$term_ids = $this->resolve_term_ids( WPMCP_Util::to_array( $args[ $key ] ), $conf[0], $conf[1] );
						if ( ! $term_ids ) {
							continue;
						}
						if ( $conf[1] ) {
							wp_set_object_terms( $id, $term_ids, $conf[0], true );
						} else {
							wp_remove_object_terms( $id, $term_ids, $conf[0] );
						}
					}
					$updated++;
				}
				$results[] = [
					'id'      => $id,
					'name'    => $product->get_name(),
					'ok'      => true,
					'changes' => $changes,
				];
			} catch ( Throwable $e ) {
				$results[] = [ 'id' => $id, 'ok' => false, 'error' => $e->getMessage() ];
			}
		}

		return [
			'matched' => count( $ids ),
			'updated' => $updated,
			'dry_run' => $dry,
			'note'    => $dry ? 'Dry run — no products were written. Call again with dry_run=false to apply.' : 'Applied.',
			'results' => $results,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_product_variations( $args ) {
		$product = $this->get_wc_product( (int) $args['product_id'], false );
		if ( ! $product->is_type( 'variable' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Product %d is a "%s", not a variable product.', $product->get_id(), $product->get_type() ),
				'Only variable products have variations. Set type=variable with update_product first.'
			);
		}
		$out = [];
		foreach ( $product->get_children() as $vid ) {
			$variation = wc_get_product( $vid );
			if ( ! $variation ) {
				continue;
			}
			$out[] = $this->variation_payload( $variation );
		}
		return [
			'product_id' => $product->get_id(),
			'count'      => count( $out ),
			'variations' => $out,
		];
	}

	/**
	 * Representation of one variation.
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	private function variation_payload( $variation ) {
		$image_id = $variation->get_image_id();
		return [
			'variation_id'   => $variation->get_id(),
			'attributes'     => $variation->get_attributes(),
			'sku'            => $variation->get_sku(),
			'price'          => $variation->get_price(),
			'regular_price'  => $variation->get_regular_price(),
			'sale_price'     => $variation->get_sale_price(),
			'on_sale'        => $variation->is_on_sale(),
			'manage_stock'   => $variation->get_manage_stock(),
			'stock_quantity' => $variation->get_stock_quantity(),
			'stock_status'   => $variation->get_stock_status(),
			'backorders'     => $variation->get_backorders(),
			'weight'         => $variation->get_weight(),
			'dimensions'     => [
				'length' => $variation->get_length(),
				'width'  => $variation->get_width(),
				'height' => $variation->get_height(),
			],
			'shipping_class' => $variation->get_shipping_class(),
			'description'    => $variation->get_description(),
			'enabled'        => 'publish' === $variation->get_status(),
			'image'          => $image_id ? wp_get_attachment_url( $image_id ) : null,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_product_variation( $args ) {
		$parent = $this->get_wc_product( (int) $args['product_id'], false );
		if ( ! $parent->is_type( 'variable' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Product %d is not a variable product.', $parent->get_id() ),
				'Call update_product with type=variable and variation attributes first.'
			);
		}

		if ( ! empty( $args['variation_id'] ) ) {
			$variation = wc_get_product( (int) $args['variation_id'] );
			if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Variation %d was not found.', (int) $args['variation_id'] ) );
			}
		} else {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
		}

		try {
			if ( isset( $args['attributes'] ) ) {
				$attributes = [];
				foreach ( (array) $args['attributes'] as $name => $value ) {
					$attributes[ $this->normalise_attribute_key( $name ) ] = (string) $value;
				}
				$variation->set_attributes( $attributes );
			}
			foreach ( [ 'sku' => 'set_sku', 'regular_price' => 'set_regular_price', 'sale_price' => 'set_sale_price', 'stock_status' => 'set_stock_status', 'backorders' => 'set_backorders', 'weight' => 'set_weight', 'length' => 'set_length', 'width' => 'set_width', 'height' => 'set_height', 'description' => 'set_description' ] as $key => $setter ) {
				if ( isset( $args[ $key ] ) ) {
					$variation->$setter( $args[ $key ] );
				}
			}
			if ( isset( $args['manage_stock'] ) ) {
				$variation->set_manage_stock( WPMCP_Util::bool( $args['manage_stock'] ) );
			}
			if ( isset( $args['stock_quantity'] ) ) {
				$variation->set_stock_quantity( (int) $args['stock_quantity'] );
			}
			if ( isset( $args['image'] ) && is_array( $args['image'] ) ) {
				$errors  = [];
				$ingest  = $this->ingest_product_image(
					$args['image'],
					[ 'post_id' => $variation->get_parent_id(), 'max_dimension' => 2000, 'quality' => 82 ],
					$parent->get_name(),
					$parent->get_name(),
					0,
					false,
					$errors,
					'variation'
				);
				if ( $ingest ) {
					$variation->set_image_id( (int) $ingest['id'] );
				}
			} elseif ( isset( $args['image_id'] ) ) {
				$variation->set_image_id( (int) $args['image_id'] );
			}
			if ( isset( $args['sale_from'] ) ) {
				$variation->set_date_on_sale_from( $args['sale_from'] ? WPMCP_Util::date( $args['sale_from'] ) : '' );
			}
			if ( isset( $args['sale_to'] ) ) {
				$variation->set_date_on_sale_to( $args['sale_to'] ? WPMCP_Util::date( $args['sale_to'] ) : '' );
			}
			if ( isset( $args['shipping_class'] ) ) {
				$term = get_term_by( 'slug', (string) $args['shipping_class'], 'product_shipping_class' );
				if ( $term ) {
					$variation->set_shipping_class_id( $term->term_id );
				}
			}
			if ( isset( $args['enabled'] ) ) {
				$variation->set_status( WPMCP_Util::bool( $args['enabled'], true ) ? 'publish' : 'private' );
			}
		} catch ( Throwable $e ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'WooCommerce rejected a variation value: ' . $e->getMessage(),
				'Attribute values must be term slugs of the parent product\'s variation attributes.'
			);
		}

		$vid = $this->save_wc_object( $variation, 'variation' );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $parent->get_id() );
		}
		return [
			'success'   => true,
			'variation' => $this->variation_payload( wc_get_product( $vid ) ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_product_variation( $args ) {
		$this->require_woo();
		$variation = wc_get_product( (int) $args['variation_id'] );
		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Variation %d was not found.', (int) $args['variation_id'] ) );
		}
		$parent_id = $variation->get_parent_id();
		if ( ! $variation->delete( true ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'Variation could not be deleted.' );
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $parent_id );
		}
		return [ 'success' => true, 'variation_id' => (int) $args['variation_id'] ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_generate_product_variations( $args ) {
		$parent = $this->get_wc_product( (int) $args['product_id'], false );
		if ( ! $parent->is_type( 'variable' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Product %d is not a variable product.', $parent->get_id() ),
				'Set type=variable and add attributes with variation=true first.'
			);
		}

		$axes = [];
		foreach ( $parent->get_attributes() as $attribute ) {
			if ( ! $attribute->get_variation() ) {
				continue;
			}
			$name    = $attribute->get_name();
			$options = $attribute->is_taxonomy()
				? wp_get_post_terms( $parent->get_id(), $name, [ 'fields' => 'slugs' ] )
				: array_map( 'sanitize_title', $attribute->get_options() );
			if ( $options ) {
				$axes[ $name ] = array_values( $options );
			}
		}
		if ( ! $axes ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'This product has no attributes marked "used for variations".',
				'Add attributes with variation=true via update_product, then re-run.'
			);
		}

		// Cartesian product of every variation axis.
		$combos = [ [] ];
		foreach ( $axes as $name => $options ) {
			$next = [];
			foreach ( $combos as $combo ) {
				foreach ( $options as $option ) {
					$next[] = array_merge( $combo, [ $name => $option ] );
				}
			}
			$combos = $next;
		}

		$existing = [];
		foreach ( $parent->get_children() as $vid ) {
			$variation = wc_get_product( $vid );
			if ( $variation ) {
				$attrs = $variation->get_attributes();
				ksort( $attrs );
				$existing[ wp_json_encode( $attrs ) ] = true;
			}
		}

		$max     = min( (int) ( $args['max'] ?? 100 ) ?: 100, 500 );
		$created = [];
		$skipped = 0;

		foreach ( $combos as $combo ) {
			$key = $combo;
			ksort( $key );
			if ( isset( $existing[ wp_json_encode( $key ) ] ) ) {
				$skipped++;
				continue;
			}
			if ( count( $created ) >= $max ) {
				break;
			}
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( $combo );
			if ( isset( $args['regular_price'] ) ) {
				$variation->set_regular_price( (string) $args['regular_price'] );
			}
			if ( isset( $args['manage_stock'] ) ) {
				$variation->set_manage_stock( WPMCP_Util::bool( $args['manage_stock'] ) );
			}
			if ( isset( $args['stock_quantity'] ) ) {
				$variation->set_stock_quantity( (int) $args['stock_quantity'] );
			}
			$vid       = $this->save_wc_object( $variation, 'variation' );
			$created[] = [ 'variation_id' => $vid, 'attributes' => $combo ];
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $parent->get_id() );
		}

		return [
			'product_id'       => $parent->get_id(),
			'axes'             => $axes,
			'possible_total'   => count( $combos ),
			'already_existed'  => $skipped,
			'created'          => count( $created ),
			'remaining'        => max( 0, count( $combos ) - $skipped - count( $created ) ),
			'variations'       => $created,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_product_attributes( $args ) {
		$this->require_woo();
		$global = [];
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
			$terms    = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);
			$global[] = [
				'id'       => (int) $tax->attribute_id,
				'name'     => $tax->attribute_label,
				'slug'     => $tax->attribute_name,
				'taxonomy' => $taxonomy,
				'type'     => $tax->attribute_type,
				'order_by' => $tax->attribute_orderby,
				'terms'    => is_wp_error( $terms ) ? [] : array_map(
					function ( $t ) {
						return [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count ];
					},
					$terms
				),
			];
		}

		$result = [ 'global_attributes' => $global ];

		if ( ! empty( $args['product_id'] ) ) {
			$product              = $this->get_wc_product( (int) $args['product_id'] );
			$result['product_id'] = $product->get_id();
			$own                  = [];
			foreach ( $product->get_attributes() as $attribute ) {
				$own[] = [
					'name'      => $attribute->get_name(),
					'label'     => wc_attribute_label( $attribute->get_name() ),
					'taxonomy'  => $attribute->is_taxonomy(),
					'visible'   => $attribute->get_visible(),
					'variation' => $attribute->get_variation(),
					'options'   => $attribute->is_taxonomy()
						? wp_get_post_terms( $product->get_id(), $attribute->get_name(), [ 'fields' => 'slugs' ] )
						: $attribute->get_options(),
				];
			}
			$result['product_attributes'] = $own;
		}

		return $result;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_product_attribute( $args ) {
		$this->require_woo();
		$label = (string) $args['name'];
		$slug  = sanitize_title( (string) ( $args['slug'] ?? $label ) );
		$slug = WPMCP_Util::unprefix( $slug, 'pa_' );
		if ( strlen( $slug ) > 28 ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'Attribute slug must be 28 characters or fewer (WooCommerce limit).',
				'Pass a shorter slug explicitly.'
			);
		}

		$existing_id = 0;
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			if ( $tax->attribute_name === $slug ) {
				$existing_id = (int) $tax->attribute_id;
				break;
			}
		}

		$data = [
			'name'         => $label,
			'slug'         => $slug,
			'type'         => (string) ( $args['type'] ?? 'select' ),
			'order_by'     => (string) ( $args['order_by'] ?? 'menu_order' ),
			'has_archives' => WPMCP_Util::bool( $args['has_archives'] ?? null ),
		];

		$result = $existing_id ? wc_update_attribute( $existing_id, $data ) : wc_create_attribute( $data );
		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::TOOL_FAILED, 'Attribute names must be unique.' );
		}
		$attribute_id = $existing_id ?: (int) $result;
		$taxonomy     = wc_attribute_taxonomy_name( $slug );

		// The taxonomy is only registered on the next request, so register it
		// now to be able to insert terms in this same call.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, [ 'product' ], [ 'hierarchical' => false, 'show_ui' => false ] );
		}

		$terms = [];
		foreach ( WPMCP_Util::to_array( $args['terms'] ?? [] ) as $term_name ) {
			$term = get_term_by( 'name', (string) $term_name, $taxonomy );
			if ( ! $term ) {
				$created = wp_insert_term( (string) $term_name, $taxonomy );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$term = get_term( (int) $created['term_id'], $taxonomy );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$terms[] = [ 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug ];
			}
		}

		return [
			'success'      => true,
			'attribute_id' => $attribute_id,
			'taxonomy'     => $taxonomy,
			'created'      => ! $existing_id,
			'terms'        => $terms,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_product_categories( $args ) {
		$this->require_woo();
		$query = [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => min( (int) ( $args['limit'] ?? 200 ) ?: 200, 1000 ),
		];
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = $args['search'];
		}
		if ( isset( $args['parent'] ) ) {
			$query['parent'] = (int) $args['parent'];
		}
		$terms = get_terms( $query );
		if ( is_wp_error( $terms ) ) {
			WPMCP_Errors::from_wp_error( $terms );
		}
		$out = [];
		foreach ( $terms as $t ) {
			$thumb_id = (int) get_term_meta( $t->term_id, 'thumbnail_id', true );
			$out[]    = [
				'term_id'      => $t->term_id,
				'name'         => $t->name,
				'slug'         => $t->slug,
				'parent'       => $t->parent,
				'description'  => $t->description,
				'count'        => $t->count,
				'link'         => get_term_link( $t ),
				'thumbnail'    => $thumb_id ? wp_get_attachment_url( $thumb_id ) : null,
				'display_type' => (string) get_term_meta( $t->term_id, 'display_type', true ),
				'seo'          => WPMCP_SEO::get_term_seo( $t->term_id, 'product_cat' ),
			];
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_product_category( $args ) {
		$this->require_woo();
		if ( ! empty( $args['term_id'] ) ) {
			$term_id = (int) $args['term_id'];
			$fields  = [];
			foreach ( [ 'name', 'description' ] as $key ) {
				if ( isset( $args[ $key ] ) ) {
					$fields[ $key ] = $args[ $key ];
				}
			}
			if ( isset( $args['slug'] ) ) {
				$fields['slug'] = sanitize_title( $args['slug'] );
			}
			if ( isset( $args['parent'] ) ) {
				$fields['parent'] = (int) $args['parent'];
			}
			if ( $fields ) {
				$result = wp_update_term( $term_id, 'product_cat', $fields );
				if ( is_wp_error( $result ) ) {
					WPMCP_Errors::from_wp_error( $result );
				}
			}
		} else {
			if ( empty( $args['name'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'name is required to create a category.' );
			}
			$create = [
				'description' => $args['description'] ?? '',
				'parent'      => (int) ( $args['parent'] ?? 0 ),
			];
			if ( ! empty( $args['slug'] ) ) {
				$create['slug'] = sanitize_title( $args['slug'] );
			}
			$result = wp_insert_term( (string) $args['name'], 'product_cat', $create );
			if ( is_wp_error( $result ) ) {
				WPMCP_Errors::from_wp_error( $result );
			}
			$term_id = (int) $result['term_id'];
		}

		if ( isset( $args['thumbnail_id'] ) ) {
			update_term_meta( $term_id, 'thumbnail_id', (int) $args['thumbnail_id'] );
		}
		if ( ! empty( $args['display_type'] ) ) {
			update_term_meta( $term_id, 'display_type', sanitize_key( $args['display_type'] ) );
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_term_seo( $term_id, 'product_cat', $args['seo'] );
		}

		return [
			'success' => true,
			'term_id' => $term_id,
			'seo'     => WPMCP_SEO::get_term_seo( $term_id, 'product_cat' ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_product_category( $args ) {
		$this->require_woo();
		$result = wp_delete_term( (int) $args['term_id'], 'product_cat' );
		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result );
		}
		if ( ! $result ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'Category not found or could not be deleted.' );
		}
		return [ 'success' => true, 'term_id' => (int) $args['term_id'] ];
	}
	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_bulk_assign_variation_images( $args ) {
		$parent = $this->get_wc_product( (int) $args['product_id'], false );
		if ( ! $parent->is_type( 'variable' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '"%s" is a %s product, not a variable one.', $parent->get_name(), $parent->get_type() ),
				'Only variable products have variations. Use manage_product_images for a simple product.',
				[ 'type' => $parent->get_type() ]
			);
		}

		$map = (array) $args['images'];
		if ( ! $map ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'images must map at least one attribute value to an image.' );
		}

		$attribute = $this->resolve_variation_attribute( $parent, (string) ( $args['attribute'] ?? '' ) );
		$dry       = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$overwrite = WPMCP_Util::bool( $args['overwrite'] ?? null );

		// Normalise the keys so "Red", "red" and "pa_color-red" all match.
		$normalised = [];
		foreach ( $map as $value => $source ) {
			$normalised[ sanitize_title( (string) $value ) ] = $source;
		}

		$children = $parent->get_children();
		$planned  = [];
		$errors   = [];
		$cache    = [];
		$applied  = 0;
		$gallery  = array_map( 'intval', $parent->get_gallery_image_ids() );
		$to_gallery = WPMCP_Util::bool( $args['add_to_gallery'] ?? null );

		foreach ( $children as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}
			$attrs = $variation->get_attributes();
			$value = '';
			foreach ( $attrs as $key => $val ) {
				if ( sanitize_title( $this->normalise_attribute_key( $key ) ) === sanitize_title( $attribute ) ) {
					$value = (string) $val;
					break;
				}
			}
			$slug = sanitize_title( $value );
			if ( '' === $slug || ! isset( $normalised[ $slug ] ) ) {
				continue;
			}
			$has = (int) $variation->get_image_id();
			if ( $has && ! $overwrite ) {
				$planned[] = [
					'variation_id' => (int) $child_id,
					'value'        => $value,
					'skipped'      => 'already has an image; pass overwrite=true to replace it',
				];
				continue;
			}

			if ( $dry ) {
				$planned[] = [
					'variation_id' => (int) $child_id,
					'value'        => $value,
					'will_use'     => is_array( $normalised[ $slug ] ) ? array_intersect_key( $normalised[ $slug ], array_flip( [ 'id', 'url', 'path', 'filename' ] ) ) : $normalised[ $slug ],
				];
				continue;
			}

			// Ingest each distinct source once, then share the attachment
			// across every variation that maps to it.
			if ( ! isset( $cache[ $slug ] ) ) {
				$result = $this->ingest_product_image(
					$normalised[ $slug ],
					[
						'post_id'       => $parent->get_id(),
						'max_dimension' => isset( $args['max_dimension'] ) ? max( 0, (int) $args['max_dimension'] ) : 2000,
						'convert'       => strtolower( trim( (string) ( $args['convert'] ?? '' ) ) ),
						'quality'       => 82,
					],
					$parent->get_name() . ' ' . $value,
					$parent->get_name() . ' - ' . $value,
					0,
					true,
					$errors,
					'variation:' . $value
				);
				$cache[ $slug ] = $result ? (int) $result['id'] : 0;
			}
			if ( ! $cache[ $slug ] ) {
				continue;
			}

			$variation->set_image_id( $cache[ $slug ] );
			$this->save_wc_object( $variation, 'variation' );
			$applied++;
			if ( $to_gallery && ! in_array( $cache[ $slug ], $gallery, true ) ) {
				$gallery[] = $cache[ $slug ];
			}
			$planned[] = [
				'variation_id' => (int) $child_id,
				'value'        => $value,
				'image_id'     => $cache[ $slug ],
				'image_url'    => wp_get_attachment_url( $cache[ $slug ] ),
			];
		}

		if ( ! $dry && $to_gallery ) {
			$gallery = array_values( array_diff( array_unique( $gallery ), [ (int) $parent->get_image_id() ] ) );
			$parent->set_gallery_image_ids( $gallery );
			$this->save_wc_object( $parent, 'product' );
		}

		$unmatched = array_values( array_diff( array_keys( $normalised ), array_map( 'sanitize_title', wp_list_pluck( $planned, 'value' ) ) ) );

		return [
			'dry_run'          => $dry,
			'product_id'       => $parent->get_id(),
			'attribute'        => $attribute,
			'variations'       => count( $children ),
			'assigned'         => $applied,
			'results'          => $planned,
			'unmatched_values' => $unmatched,
			'errors'           => $errors,
			'next_step'        => $dry
				? 'Dry run — nothing was changed. Check each variation lines up with the right image, then call again with dry_run=false.'
				: ( $unmatched ? 'Some image keys matched no variation — check they use the same attribute values as the product.' : 'Every matching variation now has its own image.' ),
		];
	}

	/**
	 * Work out which attribute a variation image map keys on.
	 *
	 * @param WC_Product $parent    Variable product.
	 * @param string     $requested Caller-supplied attribute name, or ''.
	 * @return string Normalised attribute key.
	 * @throws WPMCP_Tool_Exception When it cannot be resolved unambiguously.
	 */
	private function resolve_variation_attribute( $parent, $requested ) {
		$used = [];
		foreach ( $parent->get_attributes() as $key => $attribute ) {
			$is_variation = is_object( $attribute ) && method_exists( $attribute, 'get_variation' ) ? $attribute->get_variation() : true;
			if ( $is_variation ) {
				$used[] = $this->normalise_attribute_key( $key );
			}
		}

		if ( '' !== $requested ) {
			$want = $this->normalise_attribute_key( $requested );
			if ( ! in_array( $want, $used, true ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::INVALID_ARGUMENT,
					sprintf( '"%s" is not one of this product\'s variation attributes.', $requested ),
					sprintf( 'Available: %s.', implode( ', ', $used ) ?: 'none' ),
					[ 'available' => $used ]
				);
			}
			return $want;
		}

		if ( 1 !== count( $used ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'This product varies on more than one attribute, so the images map is ambiguous.',
				sprintf( 'Pass attribute= one of: %s.', implode( ', ', $used ) ?: 'none' ),
				[ 'available' => $used ]
			);
		}
		return $used[0];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_product_images( $args ) {
		$product = $this->get_wc_product( (int) $args['product_id'] );
		$name    = $product->get_name();

		$convert = strtolower( trim( (string) ( $args['convert'] ?? '' ) ) );
		if ( '' !== $convert && ! WPMCP_Media::mime_for( $convert ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '"%s" is not a format this tool can write.', $convert ),
				'Use webp, avif, jpg or png — or omit convert to keep each source format.',
				[ 'accepted' => [ 'webp', 'avif', 'jpg', 'png' ] ]
			);
		}

		$opts = [
			'post_id'       => $product->get_id(),
			'dedup'         => ! isset( $args['dedup'] ) || WPMCP_Util::bool( $args['dedup'], true ),
			'max_dimension' => isset( $args['max_dimension'] ) ? max( 0, (int) $args['max_dimension'] ) : 2000,
			'convert'       => $convert,
			'quality'       => (int) ( $args['quality'] ?? 82 ),
		];

		$seo_name = ( ! isset( $args['seo_filenames'] ) || WPMCP_Util::bool( $args['seo_filenames'], true ) ) ? $name : '';
		$auto_alt = ( ! isset( $args['auto_alt'] ) || WPMCP_Util::bool( $args['auto_alt'], true ) ) ? $name : '';
		$lenient  = ! isset( $args['continue_on_error'] ) || WPMCP_Util::bool( $args['continue_on_error'], true );

		$errors   = [];
		$ingested = [];

		// --- Main image -------------------------------------------------
		$main_spec = null;
		if ( isset( $args['main'] ) && is_array( $args['main'] ) ) {
			$main_spec = $args['main'];
		} elseif ( ! empty( $args['image_url'] ) ) {
			$main_spec = [ 'url' => (string) $args['image_url'] ];
		} elseif ( isset( $args['image_id'] ) && (int) $args['image_id'] > 0 ) {
			$main_spec = [ 'id' => (int) $args['image_id'] ];
		}
		if ( null !== $main_spec && isset( $args['alt'] ) && ! isset( $main_spec['alt'] ) ) {
			$main_spec['alt'] = (string) $args['alt'];
		}

		$main_id = (int) $product->get_image_id();
		if ( null !== $main_spec ) {
			$result = $this->ingest_product_image( $main_spec, $opts, $seo_name, $auto_alt, 0, $lenient, $errors, 'main' );
			if ( $result ) {
				$main_id    = (int) $result['id'];
				$ingested[] = array_merge( $result, [ 'role' => 'main' ] );
			}
		}
		if ( WPMCP_Util::bool( $args['detach_main'] ?? null ) ) {
			$main_id = 0;
		}

		// --- Gallery ----------------------------------------------------
		$existing = array_map( 'intval', $product->get_gallery_image_ids() );
		$mode     = strtolower( (string) ( $args['mode'] ?? '' ) );
		$specs    = [];
		$touched  = false;

		if ( isset( $args['gallery'] ) ) {
			foreach ( WPMCP_Util::to_array( $args['gallery'] ) as $item ) {
				$specs[] = $item;
			}
			$touched = true;
			if ( '' === $mode ) {
				$mode = 'replace';
			}
		}
		// Legacy shape: gallery_ids replaced the gallery, gallery_urls were
		// appended to it. Keep both meanings working.
		if ( isset( $args['gallery_ids'] ) ) {
			foreach ( WPMCP_Util::to_array( $args['gallery_ids'] ) as $gid ) {
				$specs[] = [ 'id' => (int) $gid ];
			}
			$touched = true;
			if ( '' === $mode ) {
				$mode = 'replace';
			}
		}
		if ( isset( $args['gallery_urls'] ) ) {
			foreach ( WPMCP_Util::to_array( $args['gallery_urls'] ) as $url ) {
				$specs[] = [ 'url' => (string) $url ];
			}
			$touched = true;
			if ( '' === $mode ) {
				$mode = 'append';
			}
		}
		if ( ! in_array( $mode, [ 'replace', 'append', 'prepend' ], true ) ) {
			$mode = 'replace';
		}

		$fresh = [];
		foreach ( $specs as $index => $spec ) {
			$result = $this->ingest_product_image( $spec, $opts, $seo_name, $auto_alt, $index + 2, $lenient, $errors, 'gallery' );
			if ( $result ) {
				$fresh[]    = (int) $result['id'];
				$ingested[] = array_merge( $result, [ 'role' => 'gallery' ] );
			}
		}

		if ( ! $touched ) {
			$gallery = $existing;
		} elseif ( 'append' === $mode ) {
			$gallery = array_merge( $existing, $fresh );
		} elseif ( 'prepend' === $mode ) {
			$gallery = array_merge( $fresh, $existing );
		} else {
			$gallery = $fresh;
		}

		$remove = array_map( 'intval', WPMCP_Util::to_array( $args['remove_ids'] ?? [] ) );
		if ( $remove ) {
			$gallery = array_diff( $gallery, $remove );
		}
		$gallery = array_values( array_unique( array_filter( array_map( 'intval', $gallery ) ) ) );
		if ( $main_id ) {
			// The main image is shown on its own; leaving it in the gallery too
			// just renders the same photo twice.
			$gallery = array_values( array_diff( $gallery, [ $main_id ] ) );
		}

		$reorder = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['reorder'] ?? [] ) ) ) );
		if ( $reorder ) {
			$wanted  = array_values( array_intersect( $reorder, $gallery ) );
			$gallery = array_merge( $wanted, array_values( array_diff( $gallery, $wanted ) ) );
		}

		// Legacy per-position alt text, applied to the final order.
		$alts = WPMCP_Util::to_array( $args['gallery_alts'] ?? [] );
		foreach ( $gallery as $index => $gid ) {
			if ( isset( $alts[ $index ] ) && '' !== $alts[ $index ] ) {
				update_post_meta( $gid, '_wp_attachment_image_alt', sanitize_text_field( $alts[ $index ] ) );
			} elseif ( '' !== $auto_alt && '' === (string) get_post_meta( $gid, '_wp_attachment_image_alt', true ) ) {
				update_post_meta( $gid, '_wp_attachment_image_alt', sanitize_text_field( $auto_alt ) );
			}
		}
		if ( $main_id && '' !== $auto_alt && '' === (string) get_post_meta( $main_id, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $main_id, '_wp_attachment_image_alt', sanitize_text_field( $auto_alt ) );
		}

		WPMCP_Journal::product( $product->get_id(), [ 'image_id' ] );
		WPMCP_Journal::post_meta( $product->get_id(), '_product_image_gallery' );
		$product->set_image_id( $main_id ? $main_id : '' );
		$product->set_gallery_image_ids( $gallery );
		$this->save_wc_object( $product, 'product' );

		$missing_alt = [];
		foreach ( array_filter( array_merge( [ $main_id ], $gallery ) ) as $aid ) {
			if ( '' === (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) ) {
				$missing_alt[] = (int) $aid;
			}
		}

		return [
			'success'      => true,
			'product_id'   => $product->get_id(),
			'image_id'     => $main_id ? $main_id : null,
			'image'        => $main_id ? WPMCP_Media::summary( $main_id ) : null,
			'gallery'      => $gallery,
			'gallery_urls' => array_map( 'wp_get_attachment_url', $gallery ),
			'mode'         => $touched ? $mode : 'unchanged',
			'ingested'     => $ingested,
			'errors'       => $errors,
			'missing_alt'  => $missing_alt,
			'next_step'    => $errors
				? 'Some sources failed — see errors. Everything else was saved; re-send just the failures.'
				: ( $missing_alt ? 'Images without alt text are listed in missing_alt. Set them with set_image_alt or bulk_set_image_alt.' : 'Images saved. Run product_seo_audit to confirm nothing else is missing.' ),
		];
	}

	/**
	 * Normalise one image spec and put it into the library.
	 *
	 * A spec may be an attachment ID, a URL string, or an object with
	 * id|url|base64|path plus descriptive fields.
	 *
	 * @param mixed  $spec     The caller's image spec.
	 * @param array  $opts     Shared ingest options.
	 * @param string $seo_name Product name to rename the file after, or ''.
	 * @param string $auto_alt Fallback alt text, or ''.
	 * @param int    $position 0 for the main image, 2+ for gallery slots.
	 * @param bool   $lenient  Collect the failure instead of aborting.
	 * @param array  $errors   Collected failures, by reference.
	 * @param string $role     Label used in the error report.
	 * @return array|null Attachment summary, or null when the source failed.
	 * @throws WPMCP_Tool_Exception When a source fails and $lenient is false.
	 */
	private function ingest_product_image( $spec, $opts, $seo_name, $auto_alt, $position, $lenient, &$errors, $role ) {
		if ( is_numeric( $spec ) ) {
			$spec = [ 'id' => (int) $spec ];
		} elseif ( is_string( $spec ) ) {
			$spec = [ 'url' => $spec ];
		}
		if ( ! is_array( $spec ) || ! $spec ) {
			$errors[] = [ 'role' => $role, 'error' => 'Empty or unreadable image spec.', 'code' => WPMCP_Errors::INVALID_ARGUMENT ];
			return null;
		}

		$source = array_intersect_key( $spec, array_flip( [ 'id', 'url', 'base64', 'path', 'filename' ] ) );
		$fields = array_intersect_key( $spec, array_flip( [ 'filename', 'alt', 'title', 'caption', 'description' ] ) );

		if ( '' !== $seo_name && empty( $spec['filename'] ) ) {
			// black-cotton-hoodie.jpg for the main shot, then -2, -3 ... for
			// the gallery, which is what a human would have named them.
			$fields['seo_name'] = $position > 0 ? $seo_name . ' ' . $position : $seo_name;
		}
		if ( '' !== $auto_alt && empty( $spec['alt'] ) ) {
			$fields['alt'] = $auto_alt;
		}

		try {
			return WPMCP_Media::ingest( $source, array_merge( $opts, $fields ) );
		} catch ( WPMCP_Tool_Exception $e ) {
			if ( ! $lenient ) {
				throw $e;
			}
			$errors[] = [
				'role'   => $role,
				'source' => $spec['url'] ?? ( $spec['path'] ?? ( isset( $spec['id'] ) ? 'attachment ' . (int) $spec['id'] : 'base64' ) ),
				'error'  => $e->getMessage(),
				'code'   => $e->get_error_code(),
				'hint'   => $e->get_hint(),
			];
			return null;
		}
	}

	/**
	 * Download a remote file into the media library.
	 *
	 * Kept as a thin wrapper over the shared ingest engine so older call sites
	 * keep working.
	 *
	 * @param string $url     Source URL.
	 * @param int    $post_id Parent post.
	 * @return int Attachment ID.
	 * @throws WPMCP_Tool_Exception On download failure.
	 */
	private function sideload_to_media( $url, $post_id = 0 ) {
		$result = WPMCP_Media::ingest( [ 'url' => $url ], [ 'post_id' => (int) $post_id ] );
		return (int) $result['id'];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_inventory( $args ) {
		$this->require_woo();

		$items = [];
		if ( ! empty( $args['items'] ) && is_array( $args['items'] ) ) {
			foreach ( $args['items'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$id = (int) ( $item['id'] ?? 0 );
				if ( ! $id && ! empty( $item['sku'] ) ) {
					$id = (int) wc_get_product_id_by_sku( (string) $item['sku'] );
				}
				if ( $id ) {
					$items[] = [ 'id' => $id, 'settings' => $item ];
				}
			}
			$dry = WPMCP_Util::bool( $args['dry_run'] ?? null );
		} else {
			$ids = array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) );
			if ( ! $ids && ! empty( $args['category'] ) ) {
				$q   = new WP_Query(
					[
						'post_type'      => 'product',
						'post_status'    => 'any',
						'posts_per_page' => 1000,
						'fields'         => 'ids',
						'tax_query'      => [
							[
								'taxonomy' => 'product_cat',
								'field'    => 'slug',
								'terms'    => WPMCP_Util::to_array( $args['category'] ),
							],
						],
					]
				);
				$ids = array_map( 'intval', $q->posts );
				// A whole-category stock change gets a dry run unless told otherwise.
				$dry = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
			} else {
				$dry = WPMCP_Util::bool( $args['dry_run'] ?? null );
			}
			foreach ( $ids as $id ) {
				$items[] = [ 'id' => $id, 'settings' => $args ];
			}
		}

		if ( ! $items ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'Nothing to update.',
				'Pass items ([{id, quantity}]), ids, or a category slug.'
			);
		}

		$results = [];
		$updated = 0;
		foreach ( $items as $entry ) {
			$product = wc_get_product( $entry['id'] );
			if ( ! $product ) {
				$results[] = [ 'id' => $entry['id'], 'ok' => false, 'error' => 'not found' ];
				continue;
			}
			$settings = $entry['settings'];
			$before   = [
				'stock_quantity' => $product->get_stock_quantity(),
				'stock_status'   => $product->get_stock_status(),
			];
			try {
				if ( isset( $settings['manage_stock'] ) ) {
					$product->set_manage_stock( WPMCP_Util::bool( $settings['manage_stock'] ) );
				}
				$quantity = null;
				if ( isset( $settings['quantity'] ) ) {
					$quantity = (int) $settings['quantity'];
				} elseif ( isset( $settings['stock_quantity'] ) ) {
					$quantity = (int) $settings['stock_quantity'];
				} elseif ( isset( $settings['adjust_by'] ) ) {
					$quantity = (int) $product->get_stock_quantity() + (int) $settings['adjust_by'];
				}
				if ( null !== $quantity ) {
					if ( ! $product->get_manage_stock() ) {
						$product->set_manage_stock( true );
					}
					$product->set_stock_quantity( max( 0, $quantity ) );
					// Keep status coherent with the new quantity unless told.
					if ( ! isset( $settings['stock_status'] ) ) {
						$product->set_stock_status( $quantity > 0 ? 'instock' : 'outofstock' );
					}
				}
				if ( ! empty( $settings['stock_status'] ) ) {
					$product->set_stock_status( (string) $settings['stock_status'] );
				}
				if ( ! empty( $settings['backorders'] ) ) {
					$product->set_backorders( (string) $settings['backorders'] );
				}
				if ( isset( $settings['low_stock_amount'] ) ) {
					$product->set_low_stock_amount( (int) $settings['low_stock_amount'] );
				}

				$after = [
					'stock_quantity' => $product->get_stock_quantity(),
					'stock_status'   => $product->get_stock_status(),
				];
				if ( ! $dry ) {
					WPMCP_Journal::product(
						$product->get_id(),
						[ 'stock_quantity', 'stock_status', 'manage_stock', 'backorders', 'low_stock_amount' ]
					);
					$product->save();
					$updated++;
					WPMCP_Progress::tick( 1, $product->get_name() );
				}
				$results[] = [
					'id'     => $entry['id'],
					'sku'    => $product->get_sku(),
					'name'   => $product->get_name(),
					'ok'     => true,
					'before' => $before,
					'after'  => $after,
				];
			} catch ( Throwable $e ) {
				$results[] = [ 'id' => $entry['id'], 'ok' => false, 'error' => $e->getMessage() ];
			}
		}

		return [
			'matched' => count( $items ),
			'updated' => $updated,
			'dry_run' => $dry,
			'results' => $results,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_inventory_report( $args ) {
		$this->require_woo();
		$threshold = isset( $args['low_stock_threshold'] )
			? (int) $args['low_stock_threshold']
			: (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		$limit = min( (int) ( $args['limit'] ?? 500 ) ?: 500, 5000 );

		$q = new WP_Query(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
			]
		);

		$out_of_stock = [];
		$backorder    = [];
		$low_stock    = [];
		$unmanaged    = 0;
		$no_price     = [];
		$value        = 0.0;
		$units        = 0;

		foreach ( $q->posts as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$row = [
				'id'       => (int) $pid,
				'name'     => $product->get_name(),
				'sku'      => $product->get_sku(),
				'quantity' => $product->get_stock_quantity(),
			];
			$status = $product->get_stock_status();
			if ( 'outofstock' === $status ) {
				$out_of_stock[] = $row;
			} elseif ( 'onbackorder' === $status ) {
				$backorder[] = $row;
			}
			if ( $product->get_manage_stock() ) {
				$qty = (int) $product->get_stock_quantity();
				if ( $qty <= $threshold && 'outofstock' !== $status ) {
					$low_stock[] = $row;
				}
				$units += max( 0, $qty );
				$value += max( 0, $qty ) * (float) $product->get_regular_price();
			} else {
				$unmanaged++;
			}
			if ( '' === (string) $product->get_price() && ! $product->is_type( 'variable' ) && ! $product->is_type( 'grouped' ) ) {
				$no_price[] = $row;
			}
			clean_post_cache( $pid );
		}

		return [
			'scanned'             => count( $q->posts ),
			'total_published'     => $q->found_posts,
			'low_stock_threshold' => $threshold,
			'currency'            => get_option( 'woocommerce_currency', 'USD' ),
			'summary'             => [
				'out_of_stock'    => count( $out_of_stock ),
				'on_backorder'    => count( $backorder ),
				'low_stock'       => count( $low_stock ),
				'stock_unmanaged' => $unmanaged,
				'missing_price'   => count( $no_price ),
				'units_in_stock'  => $units,
				'inventory_value' => round( $value, 2 ),
			],
			'out_of_stock'        => array_slice( $out_of_stock, 0, 100 ),
			'on_backorder'        => array_slice( $backorder, 0, 100 ),
			'low_stock'           => array_slice( $low_stock, 0, 100 ),
			'missing_price'       => array_slice( $no_price, 0, 100 ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_product_seo_audit( $args ) {
		$this->require_woo();
		$limit = min( (int) ( $args['limit'] ?? 500 ) ?: 500, 5000 );
		$q     = new WP_Query(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
			]
		);

		$counts = [
			'missing_meta_description' => 0,
			'missing_focus_keyword'    => 0,
			'missing_seo_title'        => 0,
			'missing_image'            => 0,
			'missing_image_alt'        => 0,
			'thin_description'         => 0,
			'missing_short_description'=> 0,
			'missing_price'            => 0,
			'no_category'              => 0,
			'no_schema'                => 0,
			'missing_social_image'     => 0,
			'duplicate_description'    => 0,
		];
		$description_hashes = [];
		$titles  = [];
		$worst   = [];
		$scanned = 0;

		WPMCP_Progress::start( 'product_seo_audit', count( $q->posts ), 'auditing products' );

		foreach ( $q->posts as $pid ) {
			if ( WPMCP_Progress::should_stop() ) {
				break;
			}
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$scanned++;
			WPMCP_Progress::tick();
			$seo    = WPMCP_SEO::get_post_seo( $pid );
			$issues = [];

			if ( '' === $seo['description'] ) {
				$counts['missing_meta_description']++;
				$issues[] = 'meta description';
			}
			if ( '' === $seo['focus_keyword'] ) {
				$counts['missing_focus_keyword']++;
				$issues[] = 'focus keyword';
			}
			if ( '' === $seo['title'] ) {
				$counts['missing_seo_title']++;
				$issues[] = 'SEO title';
			}
			$image_id = $product->get_image_id();
			if ( ! $image_id ) {
				$counts['missing_image']++;
				$issues[] = 'product image';
			} elseif ( ! get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) {
				$counts['missing_image_alt']++;
				$issues[] = 'image alt';
			}
			if ( WPMCP_Util::word_count( wp_strip_all_tags( $product->get_description() ) ) < 100 ) {
				$counts['thin_description']++;
				$issues[] = 'thin description';
			}
			if ( '' === trim( wp_strip_all_tags( $product->get_short_description() ) ) ) {
				$counts['missing_short_description']++;
				$issues[] = 'short description';
			}
			if ( '' === (string) $product->get_price() ) {
				$counts['missing_price']++;
				$issues[] = 'price';
			}
			if ( ! wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'ids' ] ) ) {
				$counts['no_category']++;
				$issues[] = 'category';
			}
			if ( ! get_post_meta( $pid, WPMCP_Frontend::JSONLD_META, true ) ) {
				$counts['no_schema']++;
			}
			if ( '' === (string) ( $seo['og_image'] ?? '' ) && ! $image_id ) {
				$counts['missing_social_image']++;
			}
			// Boilerplate copied across a range is thin content in Google's
			// eyes even when each product is genuinely different.
			$body = WPMCP_Util::lower( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $product->get_description() ) ) ) );
			if ( '' !== $body ) {
				$fingerprint = md5( mb_substr( $body, 0, 300 ) );
				if ( isset( $description_hashes[ $fingerprint ] ) ) {
					$counts['duplicate_description']++;
					$issues[] = 'duplicate description';
					$description_hashes[ $fingerprint ][] = (int) $pid;
				} else {
					$description_hashes[ $fingerprint ] = [ (int) $pid ];
				}
			}

			$titles[ $product->get_name() ] = ( $titles[ $product->get_name() ] ?? 0 ) + 1;
			if ( count( $issues ) >= 3 && count( $worst ) < 50 ) {
				$worst[] = [
					'id'     => (int) $pid,
					'name'   => $product->get_name(),
					'issues' => $issues,
				];
			}
			clean_post_cache( $pid );
		}

		$dupes = array_keys(
			array_filter(
				$titles,
				function ( $c ) {
					return $c > 1;
				}
			)
		);

		return array_merge(
			[
				'engine'           => WPMCP_SEO::provider(),
				'scanned'          => $scanned,
				'total_published'  => $q->found_posts,
			],
			$counts,
			[
				'duplicate_titles'       => $dupes,
				'duplicate_descriptions' => array_values(
					array_filter(
						$description_hashes,
						function ( $group ) {
							return count( $group ) > 1;
						}
					)
				),
				'worst_offenders'        => $worst,
				'next_step'              => 'product_seo_fix repairs missing titles, descriptions, alt text and schema in bulk — run it with dry_run=true first. generate_product_schema adds the brand, identifier and policy fields Merchant Center wants.',
			]
		);
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_generate_product_schema( $args ) {
		$this->require_woo();

		$opts = array_intersect_key(
			$args,
			array_flip( [ 'brand', 'gtin', 'mpn', 'condition', 'seller', 'price_valid_days', 'shipping', 'returns', 'include_reviews', 'max_reviews' ] )
		);

		if ( WPMCP_Util::bool( $args['save_defaults'] ?? null ) ) {
			// Identifiers belong to one product; policy facts belong to the shop.
			$keep = array_diff_key( $opts, array_flip( [ 'gtin', 'mpn' ] ) );
			WPMCP_Schema::save_defaults( array_merge( WPMCP_Schema::defaults(), $keep ) );
		}

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( ! empty( $args['product_id'] ) ) {
			$ids[] = (int) $args['product_id'];
		}
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$limit  = min( max( 1, (int) ( $args['limit'] ?? 50 ) ), 500 );
		$total  = count( $ids );

		if ( ! $ids ) {
			if ( ! WPMCP_Util::bool( $args['all'] ?? null ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					'No products selected.',
					'Pass product_id, ids, or all=true.',
					[ 'accepted' => [ 'product_id', 'ids', 'all' ] ]
				);
			}
			$q     = new WP_Query(
				[
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => $limit,
					'offset'                 => $offset,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_term_cache' => false,
				]
			);
			$ids   = $q->posts;
			$total = (int) $q->found_posts;
		} else {
			$ids = array_slice( array_unique( $ids ), 0, $limit );
		}

		$apply    = WPMCP_Util::bool( $args['apply'] ?? null );
		$results  = [];
		$applied  = 0;
		$warned   = 0;

		foreach ( $ids as $pid ) {
			try {
				$built = WPMCP_Schema::product( (int) $pid, $opts );
			} catch ( WPMCP_Tool_Exception $e ) {
				$results[] = [ 'id' => (int) $pid, 'error' => $e->getMessage(), 'code' => $e->get_error_code() ];
				continue;
			}
			if ( $built['warnings'] ) {
				$warned++;
			}
			$row = [
				'id'       => (int) $pid,
				'name'     => get_the_title( $pid ),
				'type'     => $built['schema']['@type'],
				'warnings' => $built['warnings'],
			];
			if ( $apply ) {
				$json = wp_json_encode( $built['schema'] );
				if ( false === $json ) {
					$row['error'] = 'The generated schema could not be encoded to JSON.';
				} else {
					WPMCP_Journal::post_meta( (int) $pid, WPMCP_Frontend::JSONLD_META );
					update_post_meta( (int) $pid, WPMCP_Frontend::JSONLD_META, wp_slash( $json ) );
					$applied++;
					$row['applied'] = true;
				}
			}
			// One product returns the whole object; a sweep would be unreadable.
			if ( 1 === count( $ids ) ) {
				$row['schema'] = $built['schema'];
			}
			$results[] = $row;
		}

		$next = $offset + count( $ids );

		return [
			'applied'         => $apply ? $applied : 0,
			'count'           => count( $results ),
			'with_warnings'   => $warned,
			'results'         => $results,
			'stored_defaults' => WPMCP_Schema::defaults(),
			'total'           => $total,
			'next_offset'     => $next < $total ? $next : null,
			'next_step'       => $apply
				? ( $next < $total ? sprintf( 'Call again with offset=%d for the next batch.', $next ) : 'Validate a product URL in Google\'s Rich Results Test to confirm.' )
				: 'Nothing was written. Review the warnings, supply the missing policy facts, then call again with apply=true.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_product_seo_fix( $args ) {
		$this->require_woo();

		$allowed = [ 'seo_title', 'meta_description', 'image_alt', 'schema', 'focus_keyword' ];
		$fix     = array_values( array_intersect( WPMCP_Util::to_array( $args['fix'] ?? [] ), $allowed ) );
		if ( ! $fix ) {
			$fix = [ 'seo_title', 'meta_description', 'image_alt' ];
		}

		$dry    = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$limit  = min( max( 1, (int) ( $args['limit'] ?? 50 ) ), 500 );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$sep    = (string) ( $args['separator'] ?? '|' );
		$title_template = (string) ( $args['title_template'] ?? '{title} {separator} {site}' );
		$desc_template  = (string) ( $args['description_template'] ?? '' );

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( $ids ) {
			$posts = array_slice( $ids, $offset, $limit );
			$total = count( $ids );
		} else {
			$q     = new WP_Query(
				[
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => $limit,
					'offset'                 => $offset,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_term_cache' => false,
				]
			);
			$posts = $q->posts;
			$total = (int) $q->found_posts;
		}

		$changes = [];
		$counts  = array_fill_keys( $allowed, 0 );

		WPMCP_Progress::start( 'product_seo_fix', count( $posts ), 'repairing product SEO' );
		$stopped = false;

		foreach ( $posts as $index => $pid ) {
			if ( WPMCP_Progress::should_stop() ) {
				$stopped = true;
				$posts   = array_slice( $posts, 0, $index );
				break;
			}
			WPMCP_Progress::tick();
			$pid     = (int) $pid;
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$seo    = WPMCP_SEO::get_post_seo( $pid );
			$fields = [];
			$row    = [ 'id' => $pid, 'name' => $product->get_name(), 'fixed' => [] ];

			if ( in_array( 'seo_title', $fix, true ) && '' === $seo['title'] ) {
				$value = $this->render_seo_template( $title_template, $pid, $sep );
				if ( '' !== $value ) {
					$value            = $this->trim_to_serp_width( $value, 'title' );
					$fields['title']  = $value;
					$row['fixed'][]   = 'seo_title';
					$row['seo_title'] = $value;
					$counts['seo_title']++;
				}
			}

			if ( in_array( 'meta_description', $fix, true ) && '' === $seo['description'] ) {
				$value = '' !== $desc_template
					? $this->render_seo_template( $desc_template, $pid, $sep )
					: $this->product_description_copy( $product );
				if ( '' !== $value ) {
					$value                  = $this->trim_to_serp_width( $value, 'description' );
					$fields['description']  = $value;
					$row['fixed'][]         = 'meta_description';
					$row['meta_description'] = $value;
					$counts['meta_description']++;
				}
			}

			if ( in_array( 'focus_keyword', $fix, true ) && '' === $seo['focus_keyword'] ) {
				$fields['focus_keyword']  = WPMCP_Util::lower( $product->get_name() );
				$row['fixed'][]           = 'focus_keyword';
				$row['focus_keyword']     = $fields['focus_keyword'];
				$counts['focus_keyword']++;
			}

			if ( in_array( 'image_alt', $fix, true ) ) {
				$alts = [];
				foreach ( array_filter( array_merge( [ (int) $product->get_image_id() ], array_map( 'intval', $product->get_gallery_image_ids() ) ) ) as $aid ) {
					if ( '' !== (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) ) {
						continue;
					}
					$alts[ $aid ] = $product->get_name();
					if ( ! $dry ) {
						WPMCP_Journal::post_meta( $aid, '_wp_attachment_image_alt' );
						update_post_meta( $aid, '_wp_attachment_image_alt', sanitize_text_field( $product->get_name() ) );
					}
				}
				if ( $alts ) {
					$row['fixed'][]    = 'image_alt';
					$row['image_alt']  = $alts;
					$counts['image_alt'] += count( $alts );
				}
			}

			if ( in_array( 'schema', $fix, true ) && ! get_post_meta( $pid, WPMCP_Frontend::JSONLD_META, true ) ) {
				$built = WPMCP_Schema::product( $pid, [] );
				if ( ! $dry ) {
					$json = wp_json_encode( $built['schema'] );
					if ( false !== $json ) {
						WPMCP_Journal::post_meta( $pid, WPMCP_Frontend::JSONLD_META );
						update_post_meta( $pid, WPMCP_Frontend::JSONLD_META, wp_slash( $json ) );
					}
				}
				$row['fixed'][]         = 'schema';
				$row['schema_type']     = $built['schema']['@type'];
				$row['schema_warnings'] = $built['warnings'];
				$counts['schema']++;
			}

			if ( $fields && ! $dry ) {
				WPMCP_Journal::post_seo( $pid );
				WPMCP_SEO::set_post_seo( $pid, $fields );
			}
			if ( $row['fixed'] ) {
				$changes[] = $row;
			}
		}

		$next = $offset + count( $posts );

		if ( $stopped ) {
			return array_merge(
				[
					'dry_run' => $dry,
					'scanned' => count( $posts ),
					'changed' => count( $changes ),
					'by_fix'  => array_filter( $counts ),
					'changes' => $changes,
					'total'   => $total,
				],
				WPMCP_Progress::stopped_early( $offset + count( $posts ), $total, 'product_seo_fix' )
			);
		}

		return [
			'dry_run'     => $dry,
			'scanned'     => count( $posts ),
			'changed'     => count( $changes ),
			'by_fix'      => array_filter( $counts ),
			'changes'     => $changes,
			'total'       => $total,
			'next_offset' => $next < $total ? $next : null,
			'next_step'   => $dry
				? 'Dry run — nothing was written. The copy above is what will be saved; adjust the templates if it reads poorly, then call again with dry_run=false.'
				: ( $next < $total ? sprintf( 'Call again with offset=%d for the next batch.', $next ) : 'Re-run product_seo_audit to confirm what is left.' ),
		];
	}

	/**
	 * A meta description built from the product's own copy.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function product_description_copy( $product ) {
		$text = wp_strip_all_tags( strip_shortcodes( $product->get_short_description() ) );
		if ( '' === trim( $text ) ) {
			$text = wp_strip_all_tags( strip_shortcodes( $product->get_description() ) );
		}
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( '' === $text ) {
			return '';
		}
		return $text;
	}

	/**
	 * Trim text to what Google will actually render, cutting on a word boundary.
	 *
	 * @param string $text  Text to trim.
	 * @param string $field title|description.
	 * @return string
	 */
	private function trim_to_serp_width( $text, $field ) {
		$measure = $this->measure_serp_text( $text, $field, 'desktop' );
		if ( empty( $measure['truncated'] ) ) {
			return $text;
		}
		$words = explode( ' ', $text );
		while ( count( $words ) > 1 ) {
			array_pop( $words );
			$candidate = implode( ' ', $words );
			$check     = $this->measure_serp_text( $candidate, $field, 'desktop' );
			if ( empty( $check['truncated'] ) ) {
				return rtrim( $candidate, " ,.;:-|" );
			}
		}
		return $text;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_coupons( $args ) {
		$this->require_woo();
		$query = [
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => min( (int) ( $args['limit'] ?? 50 ) ?: 50, 200 ),
		];
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = $args['search'];
		}
		$q   = new WP_Query( $query );
		$out = [];
		foreach ( $q->posts as $p ) {
			$coupon = new WC_Coupon( $p->ID );
			$out[]  = $this->coupon_payload( $coupon );
		}
		return [ 'count' => count( $out ), 'coupons' => $out ];
	}

	/**
	 * Coupon representation.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return array
	 */
	private function coupon_payload( $coupon ) {
		$expires = $coupon->get_date_expires();
		return [
			'id'                   => $coupon->get_id(),
			'code'                 => $coupon->get_code(),
			'discount_type'        => $coupon->get_discount_type(),
			'amount'               => $coupon->get_amount(),
			'description'          => $coupon->get_description(),
			'date_expires'         => $expires ? $expires->date( 'Y-m-d' ) : null,
			'usage_count'          => $coupon->get_usage_count(),
			'usage_limit'          => $coupon->get_usage_limit(),
			'usage_limit_per_user' => $coupon->get_usage_limit_per_user(),
			'individual_use'       => $coupon->get_individual_use(),
			'free_shipping'        => $coupon->get_free_shipping(),
			'minimum_amount'       => $coupon->get_minimum_amount(),
			'maximum_amount'       => $coupon->get_maximum_amount(),
			'product_ids'          => $coupon->get_product_ids(),
			'excluded_product_ids' => $coupon->get_excluded_product_ids(),
			'product_categories'   => $coupon->get_product_categories(),
			'excluded_product_categories' => $coupon->get_excluded_product_categories(),
			'exclude_sale_items'   => $coupon->get_exclude_sale_items(),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_coupon( $args ) {
		$this->require_woo();
		$id = (int) ( $args['id'] ?? 0 );
		if ( ! $id && empty( $args['code'] ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'code is required to create a coupon.' );
		}
		$coupon = new WC_Coupon( $id ?: '' );

		try {
			foreach ( [ 'code' => 'set_code', 'discount_type' => 'set_discount_type', 'amount' => 'set_amount', 'description' => 'set_description', 'minimum_amount' => 'set_minimum_amount', 'maximum_amount' => 'set_maximum_amount' ] as $key => $setter ) {
				if ( isset( $args[ $key ] ) ) {
					$coupon->$setter( $args[ $key ] );
				}
			}
			foreach ( [ 'individual_use' => 'set_individual_use', 'free_shipping' => 'set_free_shipping', 'exclude_sale_items' => 'set_exclude_sale_items' ] as $key => $setter ) {
				if ( isset( $args[ $key ] ) ) {
					$coupon->$setter( WPMCP_Util::bool( $args[ $key ] ) );
				}
			}
			foreach ( [ 'usage_limit' => 'set_usage_limit', 'usage_limit_per_user' => 'set_usage_limit_per_user' ] as $key => $setter ) {
				if ( isset( $args[ $key ] ) ) {
					$coupon->$setter( (int) $args[ $key ] );
				}
			}
			foreach ( [ 'product_ids' => 'set_product_ids', 'excluded_product_ids' => 'set_excluded_product_ids', 'product_categories' => 'set_product_categories', 'excluded_product_categories' => 'set_excluded_product_categories' ] as $key => $setter ) {
				if ( isset( $args[ $key ] ) ) {
					$coupon->$setter( array_map( 'intval', WPMCP_Util::to_array( $args[ $key ] ) ) );
				}
			}
			if ( isset( $args['date_expires'] ) ) {
				$coupon->set_date_expires( $args['date_expires'] ? WPMCP_Util::date( $args['date_expires'] ) : null );
			}
		} catch ( Throwable $e ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'WooCommerce rejected a coupon value: ' . $e->getMessage(),
				'discount_type must be percent, fixed_cart, or fixed_product.'
			);
		}

		$saved_id = $this->save_wc_object( $coupon, 'coupon' );
		return [
			'success' => true,
			'coupon'  => $this->coupon_payload( new WC_Coupon( $saved_id ) ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_coupon( $args ) {
		$this->require_woo();
		$id = (int) $args['id'];
		$p  = get_post( $id );
		if ( ! $p || 'shop_coupon' !== $p->post_type ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Coupon %d was not found.', $id ) );
		}
		wp_delete_post( $id, true );
		return [ 'success' => true, 'id' => $id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_store_report( $args ) {
		$this->require_woo();
		list( $start, $end ) = $this->resolve_period( $args );

		$statuses = [ 'wc-completed', 'wc-processing', 'wc-on-hold' ];
		// Bounded rather than -1: a year of orders on a busy store is enough to
		// exhaust the memory limit and return nothing at all.
		$cap    = 5000;
		$orders = wc_get_orders(
			[
				'limit'        => $cap,
				'status'       => $statuses,
				'date_created' => $start . '...' . $end,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'objects',
			]
		);

		$gross    = 0.0;
		$refunded = 0.0;
		$items    = 0;
		$sellers  = [];
		$count    = 0;

		foreach ( $orders as $order ) {
			$count++;
			$gross    += (float) $order->get_total();
			$refunded += (float) $order->get_total_refunded();
			foreach ( $order->get_items() as $item ) {
				$qty    = (int) $item->get_quantity();
				$items += $qty;
				$pid    = (int) $item->get_product_id();
				if ( ! isset( $sellers[ $pid ] ) ) {
					$sellers[ $pid ] = [
						'product_id' => $pid,
						'name'       => $item->get_name(),
						'quantity'   => 0,
						'revenue'    => 0.0,
					];
				}
				$sellers[ $pid ]['quantity'] += $qty;
				$sellers[ $pid ]['revenue']  += (float) $item->get_total();
			}
		}

		usort(
			$sellers,
			function ( $a, $b ) {
				return $b['quantity'] <=> $a['quantity'];
			}
		);
		foreach ( $sellers as &$seller ) {
			$seller['revenue'] = round( $seller['revenue'], 2 );
		}
		unset( $seller );

		return [
			'period'        => [ 'start' => $start, 'end' => $end ],
			'currency'      => get_option( 'woocommerce_currency', 'USD' ),
			'orders'        => $count,
			'gross_sales'   => round( $gross, 2 ),
			'refunds'       => round( $refunded, 2 ),
			'net_sales'     => round( $gross - $refunded, 2 ),
			'items_sold'    => $items,
			'average_order_value' => $count ? round( $gross / $count, 2 ) : 0,
			'top_sellers'   => array_slice( $sellers, 0, (int) ( $args['top_limit'] ?? 10 ) ?: 10 ),
			'truncated'     => count( $orders ) >= $cap,
			'note'          => count( $orders ) >= $cap
				? sprintf( 'Counts the %d most recent orders in completed, processing and on-hold status — the period holds more. Narrow the period for exact totals. No customer data included.', $cap )
				: 'Counts orders in completed, processing, and on-hold status. No customer data included.',
		];
	}

	/**
	 * Resolve a reporting period into start/end dates.
	 *
	 * @param array $args Args with period/start_date/end_date.
	 * @return array [start, end] as Y-m-d.
	 */
	private function resolve_period( $args ) {
		$period = $args['period'] ?? 'month';
		$today  = current_time( 'Y-m-d' );
		switch ( $period ) {
			case 'today':
				return [ $today, $today ];
			case 'week':
				return [ gmdate( 'Y-m-d', strtotime( '-7 days' ) ), $today ];
			case 'year':
				return [ gmdate( 'Y-01-01' ), $today ];
			case 'last_month':
				return [ gmdate( 'Y-m-01', strtotime( 'first day of last month' ) ), gmdate( 'Y-m-t', strtotime( 'last day of last month' ) ) ];
			case 'custom':
				$start = WPMCP_Util::date( $args['start_date'] ?? '' );
				$end   = WPMCP_Util::date( $args['end_date'] ?? '' );
				if ( '' === $start || '' === $end ) {
					WPMCP_Errors::fail(
						WPMCP_Errors::MISSING_ARGUMENT,
						'period=custom needs both start_date and end_date.',
						'Use YYYY-MM-DD dates.'
					);
				}
				return [ substr( $start, 0, 10 ), substr( $end, 0, 10 ) ];
			case 'month':
			default:
				return [ gmdate( 'Y-m-01' ), $today ];
		}
	}

	/**
	 * Store settings that may be read and written through MCP. Payment gateway
	 * credentials and anything holding a secret are deliberately excluded.
	 *
	 * @return array
	 */
	private static function writable_store_settings() {
		return [
			'woocommerce_currency',
			'woocommerce_currency_pos',
			'woocommerce_price_thousand_sep',
			'woocommerce_price_decimal_sep',
			'woocommerce_price_num_decimals',
			'woocommerce_store_address',
			'woocommerce_store_address_2',
			'woocommerce_store_city',
			'woocommerce_store_postcode',
			'woocommerce_default_country',
			'woocommerce_allowed_countries',
			'woocommerce_ship_to_countries',
			'woocommerce_weight_unit',
			'woocommerce_dimension_unit',
			'woocommerce_manage_stock',
			'woocommerce_notify_low_stock',
			'woocommerce_notify_no_stock',
			'woocommerce_notify_low_stock_amount',
			'woocommerce_notify_no_stock_amount',
			'woocommerce_hide_out_of_stock_items',
			'woocommerce_stock_format',
			'woocommerce_enable_reviews',
			'woocommerce_review_rating_verification_label',
			'woocommerce_review_rating_required',
			'woocommerce_enable_review_rating',
			'woocommerce_shop_page_display',
			'woocommerce_category_archive_display',
			'woocommerce_default_catalog_orderby',
			'woocommerce_cart_redirect_after_add',
			'woocommerce_enable_ajax_add_to_cart',
			'woocommerce_enable_coupons',
			'woocommerce_calc_taxes',
			'woocommerce_prices_include_tax',
			'woocommerce_tax_display_shop',
			'woocommerce_tax_display_cart',
			'woocommerce_enable_guest_checkout',
			'woocommerce_enable_checkout_login_reminder',
		];
	}

	/**
	 * @return array
	 */
	private function tool_get_store_settings() {
		$this->require_woo();
		$settings = [];
		foreach ( self::writable_store_settings() as $key ) {
			$settings[ $key ] = get_option( $key );
		}

		$gateways = [];
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gateway ) {
				$gateways[] = [ 'id' => $gateway->id, 'title' => $gateway->get_title() ];
			}
		}

		$shipping = [];
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
				$methods = [];
				foreach ( (array) ( $zone['shipping_methods'] ?? [] ) as $method ) {
					$methods[] = [
						'id'      => $method->id,
						'title'   => $method->get_title(),
						'enabled' => 'yes' === $method->enabled,
					];
				}
				$shipping[] = [
					'zone_name' => $zone['zone_name'],
					'regions'   => wp_list_pluck( (array) ( $zone['zone_locations'] ?? [] ), 'code' ),
					'methods'   => $methods,
				];
			}
		}

		return [
			'settings'         => $settings,
			'writable_keys'    => self::writable_store_settings(),
			'payment_gateways' => $gateways,
			'shipping_zones'   => $shipping,
			'wc_version'       => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_store_settings( $args ) {
		$this->require_woo();
		$incoming = (array) $args['settings'];
		$allowed  = self::writable_store_settings();
		$written  = [];
		$rejected = [];

		foreach ( $incoming as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				$rejected[] = $key;
				continue;
			}
			update_option( $key, $value );
			$written[ $key ] = $value;
		}

		if ( ! $written && $rejected ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				'None of the supplied settings are writable through MCP.',
				'Call get_store_settings to see writable_keys.',
				[ 'rejected' => $rejected ]
			);
		}

		return [
			'success'  => true,
			'updated'  => $written,
			'rejected' => $rejected,
			'note'     => $rejected ? 'Some keys are not in the writable whitelist and were ignored.' : '',
		];
	}
}
