<?php
/**
 * WooCommerce order and customer tools.
 *
 * These live in their own capability group (`wc_orders`, off by default)
 * because they expose personal data (names, emails, addresses, purchase
 * history) and because refunds move real money. A content/SEO engagement
 * never needs them.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `wc_orders` capability group.
 */
trait WPMCP_Orders_Tools {

	/**
	 * Order and customer tool definitions.
	 *
	 * @return array
	 */
	private function defs_orders() {
		return [
			[
				'group'       => 'wc_orders',
				'name'        => 'list_orders',
				'description' => 'List orders with status, date, totals, item count, payment method, and customer name. Filter by status, date range, customer, product purchased, or search term. Contains personal data.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'status'      => [ 'type' => 'string', 'description' => 'pending|processing|on-hold|completed|cancelled|refunded|failed|any. Comma-separate for several. Default any.' ],
						'after'       => [ 'type' => 'string', 'description' => 'Orders created after this date.' ],
						'before'      => [ 'type' => 'string', 'description' => 'Orders created before this date.' ],
						'customer_id' => [ 'type' => 'integer' ],
						'customer_email' => [ 'type' => 'string' ],
						'product_id'  => [ 'type' => 'integer', 'description' => 'Only orders containing this product.' ],
						'search'      => [ 'type' => 'string' ],
						'limit'       => [ 'type' => 'integer', 'description' => 'Default 25, max 200.' ],
						'page'        => [ 'type' => 'integer' ],
					],
				],
			],
			[
				'group'       => 'wc_orders',
				'name'        => 'get_order',
				'description' => 'Full order record: line items with quantities and totals, shipping and tax lines, coupons used, billing and shipping addresses, payment method, customer note, order notes, and any refunds.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'wc_orders',
				'name'        => 'update_order',
				'description' => 'Update an order: change status (triggers WooCommerce\'s normal emails and stock handling), edit the customer note, update billing or shipping address fields, or set order meta such as a tracking number.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'             => [ 'type' => 'integer' ],
						'status'         => [ 'type' => 'string', 'description' => 'pending|processing|on-hold|completed|cancelled|refunded|failed.' ],
						'status_note'    => [ 'type' => 'string', 'description' => 'Note recorded alongside the status change.' ],
						'customer_note'  => [ 'type' => 'string' ],
						'billing'        => [ 'type' => 'object', 'description' => 'Any of first_name, last_name, company, address_1, address_2, city, state, postcode, country, email, phone.' ],
						'shipping'       => [ 'type' => 'object', 'description' => 'Same fields as billing, minus email/phone.' ],
						'meta'           => [ 'type' => 'object', 'description' => 'Order meta key => value, e.g. tracking number.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'wc_orders',
				'name'        => 'add_order_note',
				'description' => 'Add a note to an order. customer_note=true emails it to the customer; otherwise it is a private staff note.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'            => [ 'type' => 'integer' ],
						'note'          => [ 'type' => 'string' ],
						'customer_note' => [ 'type' => 'boolean', 'description' => 'Default false (private note).' ],
					],
					'required'   => [ 'id', 'note' ],
				],
			],
			[
				'group'       => 'wc_orders',
				'name'        => 'refund_order',
				'description' => 'Create a refund against an order. IRREVERSIBLE and moves money when via_gateway=true. By default it records a manual refund only (no gateway API call) and does not restock. Pass amount for a partial refund, or omit it to refund the remaining total.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'          => [ 'type' => 'integer' ],
						'amount'      => [ 'type' => 'string', 'description' => 'Refund amount. Omit to refund everything not already refunded.' ],
						'reason'      => [ 'type' => 'string' ],
						'restock'     => [ 'type' => 'boolean', 'description' => 'Return the items to stock. Default false.' ],
						'via_gateway' => [ 'type' => 'boolean', 'description' => 'Ask the payment gateway to refund for real. Default false (manual record only).' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'wc_orders',
				'name'        => 'list_customers',
				'description' => 'List customers with name, email, registration date, order count, and lifetime spend. Contains personal data.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'  => [ 'type' => 'string' ],
						'orderby' => [ 'type' => 'string', 'description' => 'registered|spend|orders. Default registered.' ],
						'limit'   => [ 'type' => 'integer', 'description' => 'Default 25, max 200.' ],
						'page'    => [ 'type' => 'integer' ],
					],
				],
			],
			[
				'group'       => 'wc_orders',
				'name'        => 'get_customer',
				'description' => 'One customer in full: profile, billing and shipping addresses, order count, lifetime spend, average order value, and recent orders.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer' ],
						'email' => [ 'type' => 'string', 'description' => 'Look the customer up by email instead of ID.' ],
					],
				],
			],
			[
				'group'       => 'wc_orders',
				'name'        => 'customer_insights',
				'description' => 'Retention and value analysis over a period: new versus returning customers, repeat purchase rate, average order value, and the highest-value customers.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'period'     => [ 'type' => 'string', 'description' => 'today|week|month|last_month|year|custom. Default month.' ],
						'start_date' => [ 'type' => 'string' ],
						'end_date'   => [ 'type' => 'string' ],
						'top_limit'  => [ 'type' => 'integer', 'description' => 'How many top customers to return. Default 10.' ],
					],
				],
			],
		];
	}

	/* =====================================================================
	 * Order handlers
	 * ===================================================================== */

	/**
	 * Load an order or fail cleanly.
	 *
	 * @param int $id Order ID.
	 * @return WC_Order
	 * @throws WPMCP_Tool_Exception When missing.
	 */
	private function get_wc_order( $id ) {
		$this->require_woo();
		$order = wc_get_order( (int) $id );
		if ( ! $order ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Order %d was not found.', (int) $id ),
				'Find the order ID with list_orders.',
				[ 'id' => (int) $id ]
			);
		}
		return $order;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_orders( $args ) {
		$this->require_woo();
		$limit = min( (int) ( $args['limit'] ?? 25 ) ?: 25, 200 );
		$query = [
			'limit'    => $limit,
			'page'     => max( 1, (int) ( $args['page'] ?? 1 ) ),
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		];

		if ( ! empty( $args['status'] ) && 'any' !== $args['status'] ) {
			$query['status'] = array_map(
				function ( $status ) {
					return 'wc-' . WPMCP_Util::unprefix( trim( $status ), 'wc-' );
				},
				WPMCP_Util::to_array( $args['status'] )
			);
		}
		if ( ! empty( $args['customer_id'] ) ) {
			$query['customer_id'] = (int) $args['customer_id'];
		}
		if ( ! empty( $args['customer_email'] ) ) {
			$query['billing_email'] = (string) $args['customer_email'];
		}
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = (string) $args['search'];
		}
		$after  = ! empty( $args['after'] ) ? substr( WPMCP_Util::date( $args['after'] ), 0, 10 ) : '';
		$before = ! empty( $args['before'] ) ? substr( WPMCP_Util::date( $args['before'] ), 0, 10 ) : '';
		if ( $after && $before ) {
			$query['date_created'] = $after . '...' . $before;
		} elseif ( $after ) {
			$query['date_created'] = '>=' . $after;
		} elseif ( $before ) {
			$query['date_created'] = '<=' . $before;
		}

		$result = wc_get_orders( $query );
		$orders = is_object( $result ) ? $result->orders : (array) $result;
		$total  = is_object( $result ) ? (int) $result->total : count( $orders );
		$pages  = is_object( $result ) ? (int) $result->max_num_pages : 1;

		$product_filter = (int) ( $args['product_id'] ?? 0 );
		$out            = [];

		foreach ( $orders as $order ) {
			if ( $product_filter ) {
				$has = false;
				foreach ( $order->get_items() as $item ) {
					if ( (int) $item->get_product_id() === $product_filter || (int) $item->get_variation_id() === $product_filter ) {
						$has = true;
						break;
					}
				}
				if ( ! $has ) {
					continue;
				}
			}
			$out[] = [
				'id'             => $order->get_id(),
				'number'         => $order->get_order_number(),
				'status'         => $order->get_status(),
				'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
				'total'          => $order->get_total(),
				'currency'       => $order->get_currency(),
				'items'          => $order->get_item_count(),
				'payment_method' => $order->get_payment_method_title(),
				'customer_id'    => $order->get_customer_id(),
				'customer'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email'          => $order->get_billing_email(),
				'refunded'       => $order->get_total_refunded(),
			];
		}

		return [
			'total'  => $total,
			'pages'  => $pages,
			'count'  => count( $out ),
			'orders' => $out,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_order( $args ) {
		$order = $this->get_wc_order( (int) $args['id'] );

		$items = [];
		foreach ( $order->get_items() as $item ) {
			$items[] = [
				'item_id'      => $item->get_id(),
				'name'         => $item->get_name(),
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'sku'          => $item->get_product() ? $item->get_product()->get_sku() : '',
				'quantity'     => $item->get_quantity(),
				'subtotal'     => $item->get_subtotal(),
				'total'        => $item->get_total(),
				'meta'         => wp_list_pluck( $item->get_formatted_meta_data(), 'display_value', 'display_key' ),
			];
		}

		$shipping_lines = [];
		foreach ( $order->get_items( 'shipping' ) as $line ) {
			$shipping_lines[] = [
				'method' => $line->get_method_title(),
				'total'  => $line->get_total(),
			];
		}

		$notes = [];
		foreach ( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) as $note ) {
			$notes[] = [
				'id'            => $note->id,
				'date'          => $note->date_created ? $note->date_created->date( 'c' ) : null,
				'author'        => $note->added_by,
				'customer_note' => (bool) $note->customer_note,
				'content'       => $note->content,
			];
		}

		$refunds = [];
		foreach ( $order->get_refunds() as $refund ) {
			$refunds[] = [
				'id'     => $refund->get_id(),
				'amount' => $refund->get_amount(),
				'reason' => $refund->get_reason(),
				'date'   => $refund->get_date_created() ? $refund->get_date_created()->date( 'c' ) : null,
			];
		}

		return [
			'id'             => $order->get_id(),
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'currency'       => $order->get_currency(),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
			'date_paid'      => $order->get_date_paid() ? $order->get_date_paid()->date( 'c' ) : null,
			'customer_id'    => $order->get_customer_id(),
			'customer_note'  => $order->get_customer_note(),
			'payment_method' => $order->get_payment_method_title(),
			'transaction_id' => $order->get_transaction_id(),
			'billing'        => $order->get_address( 'billing' ),
			'shipping'       => $order->get_address( 'shipping' ),
			'items'          => $items,
			'shipping_lines' => $shipping_lines,
			'coupons'        => $order->get_coupon_codes(),
			'totals'         => [
				'subtotal'       => $order->get_subtotal(),
				'discount'       => $order->get_discount_total(),
				'shipping'       => $order->get_shipping_total(),
				'tax'            => $order->get_total_tax(),
				'total'          => $order->get_total(),
				'total_refunded' => $order->get_total_refunded(),
			],
			'notes'          => $notes,
			'refunds'        => $refunds,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_order( $args ) {
		$order = $this->get_wc_order( (int) $args['id'] );
		WPMCP_Journal::note( sprintf( 'Changes to order %d are not restored by undo. The order notes record what changed.', $order->get_id() ) );

		try {
			if ( isset( $args['customer_note'] ) ) {
				$order->set_customer_note( (string) $args['customer_note'] );
			}
			foreach ( [ 'billing', 'shipping' ] as $type ) {
				if ( empty( $args[ $type ] ) || ! is_array( $args[ $type ] ) ) {
					continue;
				}
				foreach ( $args[ $type ] as $field => $value ) {
					$setter = 'set_' . $type . '_' . sanitize_key( $field );
					if ( method_exists( $order, $setter ) ) {
						$order->$setter( $value );
					}
				}
			}
			if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
				foreach ( $args['meta'] as $key => $value ) {
					$order->update_meta_data( (string) $key, $value );
				}
			}
			$order->save();

			if ( ! empty( $args['status'] ) ) {
				$status  = WPMCP_Util::unprefix( trim( (string) $args['status'] ), 'wc-' );
				$allowed = array_map(
					function ( $key ) {
						return WPMCP_Util::unprefix( $key, 'wc-' );
					},
					array_keys( wc_get_order_statuses() )
				);
				if ( ! in_array( $status, $allowed, true ) ) {
					WPMCP_Errors::fail(
						WPMCP_Errors::INVALID_ARGUMENT,
						sprintf( 'Unknown order status "%s".', $status ),
						'Allowed: ' . implode( ', ', $allowed ) . '.'
					);
				}
				$order->update_status( $status, (string) ( $args['status_note'] ?? '' ), true );
			}
		} catch ( WPMCP_Tool_Exception $e ) {
			throw $e;
		} catch ( Throwable $e ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'Order update failed: ' . $e->getMessage() );
		}

		return [
			'success' => true,
			'id'      => $order->get_id(),
			'status'  => $order->get_status(),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_add_order_note( $args ) {
		$order   = $this->get_wc_order( (int) $args['id'] );
		WPMCP_Journal::note( sprintf( 'The note added to order %d is not removed by undo, and a customer note has already been emailed.', $order->get_id() ) );
		$note_id = $order->add_order_note(
			(string) $args['note'],
			WPMCP_Util::bool( $args['customer_note'] ?? null ) ? 1 : 0,
			false
		);
		if ( ! $note_id ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'The note could not be added.' );
		}
		return [ 'success' => true, 'order_id' => $order->get_id(), 'note_id' => $note_id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_refund_order( $args ) {
		$order = $this->get_wc_order( (int) $args['id'] );

		$remaining = (float) $order->get_total() - (float) $order->get_total_refunded();
		$amount    = isset( $args['amount'] ) ? (float) $args['amount'] : $remaining;

		if ( $amount <= 0 ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'There is nothing left to refund on this order.',
				sprintf( 'Order total %s, already refunded %s.', $order->get_total(), $order->get_total_refunded() )
			);
		}
		if ( $amount > $remaining ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Refund of %s exceeds the %s still refundable on this order.', $amount, $remaining ),
				'Lower the amount, or omit it to refund the remaining balance.',
				[ 'requested' => $amount, 'refundable' => $remaining ]
			);
		}

		$via_gateway = WPMCP_Util::bool( $args['via_gateway'] ?? null );
		WPMCP_Journal::note( sprintf( 'The refund on order %d cannot be undone. Money sent back through a payment gateway stays refunded.', $order->get_id() ) );
		$refund      = wc_create_refund(
			[
				'order_id'       => $order->get_id(),
				'amount'         => wc_format_decimal( $amount ),
				'reason'         => (string) ( $args['reason'] ?? '' ),
				'restock_items'  => WPMCP_Util::bool( $args['restock'] ?? null ),
				'refund_payment' => $via_gateway,
			]
		);

		if ( is_wp_error( $refund ) ) {
			WPMCP_Errors::from_wp_error(
				$refund,
				WPMCP_Errors::UPSTREAM_FAILED,
				$via_gateway ? 'The payment gateway refused the refund. Try via_gateway=false to record it manually and refund in the gateway dashboard.' : ''
			);
		}

		return [
			'success'      => true,
			'order_id'     => $order->get_id(),
			'refund_id'    => $refund->get_id(),
			'amount'       => $refund->get_amount(),
			'via_gateway'  => $via_gateway,
			'order_status' => $order->get_status(),
			'note'         => $via_gateway ? 'Refund sent to the payment gateway.' : 'Recorded as a manual refund; no money was moved by the gateway.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_customers( $args ) {
		$this->require_woo();
		$limit = min( (int) ( $args['limit'] ?? 25 ) ?: 25, 200 );
		$query = [
			'role'   => 'customer',
			'number' => $limit,
			'paged'  => max( 1, (int) ( $args['page'] ?? 1 ) ),
		];
		if ( ! empty( $args['search'] ) ) {
			$query['search']         = '*' . $args['search'] . '*';
			$query['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}

		$users = get_users( $query );
		$out   = [];
		foreach ( $users as $user ) {
			$customer = new WC_Customer( $user->ID );
			$out[]    = [
				'id'           => $user->ID,
				'name'         => $user->display_name,
				'email'        => $user->user_email,
				'registered'   => $user->user_registered,
				'order_count'  => (int) $customer->get_order_count(),
				'total_spent'  => (float) $customer->get_total_spent(),
				'country'      => $customer->get_billing_country(),
			];
		}

		$orderby = $args['orderby'] ?? 'registered';
		if ( 'spend' === $orderby ) {
			usort(
				$out,
				function ( $a, $b ) {
					return $b['total_spent'] <=> $a['total_spent'];
				}
			);
		} elseif ( 'orders' === $orderby ) {
			usort(
				$out,
				function ( $a, $b ) {
					return $b['order_count'] <=> $a['order_count'];
				}
			);
		}

		return [ 'count' => count( $out ), 'customers' => $out ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_customer( $args ) {
		$this->require_woo();
		$id = (int) ( $args['id'] ?? 0 );
		if ( ! $id && ! empty( $args['email'] ) ) {
			$user = get_user_by( 'email', (string) $args['email'] );
			$id   = $user ? $user->ID : 0;
		}
		if ( ! $id ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				'No customer matched.',
				'Pass a customer id, or an email that belongs to a registered account. Guest orders have no customer record. Find them with list_orders and customer_email.'
			);
		}
		$user = get_userdata( $id );
		if ( ! $user ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'User %d does not exist.', $id ) );
		}

		$customer = new WC_Customer( $id );
		$orders   = wc_get_orders(
			[
				'customer_id' => $id,
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			]
		);
		$recent = [];
		foreach ( $orders as $order ) {
			$recent[] = [
				'id'     => $order->get_id(),
				'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
				'status' => $order->get_status(),
				'total'  => $order->get_total(),
			];
		}

		$count = (int) $customer->get_order_count();
		$spent = (float) $customer->get_total_spent();

		return [
			'id'            => $id,
			'name'          => $user->display_name,
			'email'         => $user->user_email,
			'username'      => $user->user_login,
			'registered'    => $user->user_registered,
			'billing'       => $customer->get_billing(),
			'shipping'      => $customer->get_shipping(),
			'order_count'   => $count,
			'total_spent'   => round( $spent, 2 ),
			'average_order' => $count ? round( $spent / $count, 2 ) : 0,
			'recent_orders' => $recent,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_customer_insights( $args ) {
		$this->require_woo();
		list( $start, $end ) = $this->resolve_period( $args );

		// Bounded, for the same reason as store_report.
		$cap    = 5000;
		$orders = wc_get_orders(
			[
				'limit'        => $cap,
				'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
				'date_created' => $start . '...' . $end,
				'orderby'      => 'date',
				'order'        => 'DESC',
			]
		);

		$by_customer = [];
		$guest       = 0;
		$revenue     = 0.0;

		foreach ( $orders as $order ) {
			$total    = (float) $order->get_total();
			$revenue += $total;
			$key      = $order->get_customer_id() ?: 'guest:' . $order->get_billing_email();
			if ( ! $order->get_customer_id() && ! $order->get_billing_email() ) {
				$guest++;
				continue;
			}
			if ( ! isset( $by_customer[ $key ] ) ) {
				$by_customer[ $key ] = [
					'customer_id' => $order->get_customer_id(),
					'name'        => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email'       => $order->get_billing_email(),
					'orders'      => 0,
					'spent'       => 0.0,
				];
			}
			$by_customer[ $key ]['orders']++;
			$by_customer[ $key ]['spent'] += $total;
		}

		$repeat = 0;
		foreach ( $by_customer as $row ) {
			if ( $row['orders'] > 1 ) {
				$repeat++;
			}
		}

		$top = array_values( $by_customer );
		usort(
			$top,
			function ( $a, $b ) {
				return $b['spent'] <=> $a['spent'];
			}
		);
		foreach ( $top as &$row ) {
			$row['spent'] = round( $row['spent'], 2 );
		}
		unset( $row );

		$customers = count( $by_customer );
		$count     = count( $orders );

		return [
			'period'               => [ 'start' => $start, 'end' => $end ],
			'currency'             => get_option( 'woocommerce_currency', 'USD' ),
			'orders'               => $count,
			'revenue'              => round( $revenue, 2 ),
			'unique_customers'     => $customers,
			'anonymous_orders'     => $guest,
			'repeat_customers'     => $repeat,
			'repeat_rate_percent'  => $customers ? round( ( $repeat / $customers ) * 100, 1 ) : 0,
			'average_order_value'  => $count ? round( $revenue / $count, 2 ) : 0,
			'revenue_per_customer' => $customers ? round( $revenue / $customers, 2 ) : 0,
			'top_customers'        => array_slice( $top, 0, (int) ( $args['top_limit'] ?? 10 ) ?: 10 ),
			'truncated'            => $count >= $cap,
			'note'                 => $count >= $cap
				? sprintf( 'Based on the %d most recent orders in the period; there are more. Narrow the period for exact figures.', $cap )
				: '',
		];
	}
}
