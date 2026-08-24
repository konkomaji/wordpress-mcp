<?php
/**
 * Operation journal — the undo layer.
 *
 * Files have backups and posts have revisions, but a bulk reprice, a site-wide
 * search-and-replace or a batch of SEO writes had nothing behind them. Every
 * instrumented write records the value it is about to overwrite; the recording
 * is opened and closed automatically around each tool call and thrown away
 * when a tool turns out not to have changed anything.
 *
 * Undo replays those records in reverse.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records and reverses site changes made through MCP tools.
 */
class WPMCP_Journal {

	/**
	 * Option holding the list of stored operations, newest first.
	 */
	const INDEX_OPTION = 'wpmcp_operations';

	/**
	 * Option name prefix for one operation's revert records.
	 */
	const ENTRY_PREFIX = 'wpmcp_op_';

	/**
	 * How many operations to keep before the oldest is dropped.
	 */
	const KEEP = 40;

	/**
	 * Ceiling on revert records in a single operation. Past this the operation
	 * is marked incomplete rather than silently losing the tail.
	 */
	const MAX_RECORDS = 1200;

	/**
	 * The operation currently being recorded, if any.
	 *
	 * @var array|null
	 */
	private static $current = null;

	/**
	 * Signatures already recorded in this operation, so the first (oldest)
	 * value for a given target wins and a loop that writes the same key twice
	 * still reverts to where it started.
	 *
	 * @var array<string,bool>
	 */
	private static $seen = [];

	/**
	 * Begin recording. Cheap: nothing is stored unless a tool records something.
	 *
	 * @param string $tool Tool name.
	 * @param array  $args Arguments, for the operation summary.
	 */
	public static function open( $tool, $args = [] ) {
		self::$current = [
			'tool'      => (string) $tool,
			'args'      => self::summarise_args( $args ),
			'started'   => gmdate( 'c' ),
			'records'   => [],
			'complete'  => true,
			'notes'     => [],
		];
		self::$seen = [];
	}

	/**
	 * Whether an operation is being recorded right now.
	 *
	 * @return bool
	 */
	public static function is_open() {
		return null !== self::$current;
	}

	/**
	 * Stop recording without storing anything.
	 */
	public static function discard() {
		self::$current = null;
		self::$seen    = [];
	}

	/**
	 * Store the operation, if it recorded anything worth reverting.
	 *
	 * @param array $summary Extra detail from the tool's own result.
	 * @return string Operation ID, or '' when nothing was recorded.
	 */
	public static function close( $summary = [] ) {
		$op = self::$current;
		self::discard();

		if ( ! $op || ! $op['records'] ) {
			return '';
		}

		$id            = self::next_id();
		$op['id']      = $id;
		$op['summary'] = $summary;

		update_option( self::ENTRY_PREFIX . $id, $op, false );

		$index = self::index();
		array_unshift(
			$index,
			[
				'id'       => $id,
				'tool'     => $op['tool'],
				'time'     => $op['started'],
				'changes'  => count( $op['records'] ),
				'complete' => $op['complete'],
				'undone'   => false,
				'args'     => $op['args'],
			]
		);
		self::write_index( $index );
		self::prune( $index );

		return $id;
	}

	/**
	 * Add a free-text note to the current operation.
	 *
	 * @param string $note Note.
	 */
	public static function note( $note ) {
		if ( self::$current && count( self::$current['notes'] ) < 20 ) {
			self::$current['notes'][] = (string) $note;
		}
	}

	/**
	 * Push one revert record, first-write-wins per target.
	 *
	 * @param string $signature Unique key for the target.
	 * @param array  $record    Revert instruction.
	 */
	private static function push( $signature, $record ) {
		if ( null === self::$current || isset( self::$seen[ $signature ] ) ) {
			return;
		}
		if ( count( self::$current['records'] ) >= self::MAX_RECORDS ) {
			self::$current['complete'] = false;
			return;
		}
		self::$seen[ $signature ]  = true;
		self::$current['records'][] = $record;
	}

	/**
	 * Snapshot a post meta value before it is written.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	public static function post_meta( $post_id, $key ) {
		if ( null === self::$current ) {
			return;
		}
		$post_id = (int) $post_id;
		$exists  = metadata_exists( 'post', $post_id, $key );
		self::push(
			'pm:' . $post_id . ':' . $key,
			[
				'type'    => 'post_meta',
				'id'      => $post_id,
				'key'     => (string) $key,
				'value'   => $exists ? get_post_meta( $post_id, $key, true ) : null,
				'existed' => $exists,
			]
		);
	}

	/**
	 * Snapshot several post meta keys at once.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $keys    Meta keys.
	 */
	public static function post_meta_keys( $post_id, $keys ) {
		foreach ( (array) $keys as $key ) {
			self::post_meta( $post_id, $key );
		}
	}

	/**
	 * Snapshot the normalised SEO field set for a post, whichever engine is
	 * active, so an SEO write can be reversed without knowing the meta keys.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function post_seo( $post_id ) {
		if ( null === self::$current ) {
			return;
		}
		$post_id = (int) $post_id;
		$seo     = WPMCP_SEO::get_post_seo( $post_id );
		unset( $seo['provider'] );
		self::push(
			'seo:' . $post_id,
			[
				'type'   => 'post_seo',
				'id'     => $post_id,
				'fields' => $seo,
			]
		);
	}

	/**
	 * Snapshot post table columns before they are written.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $fields  Column names, e.g. post_content, post_title.
	 */
	public static function post_fields( $post_id, $fields ) {
		if ( null === self::$current ) {
			return;
		}
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$values = [];
		foreach ( (array) $fields as $field ) {
			if ( isset( $post->$field ) ) {
				$values[ $field ] = $post->$field;
			}
		}
		if ( ! $values ) {
			return;
		}
		self::push(
			'post:' . $post_id . ':' . implode( ',', array_keys( $values ) ),
			[
				'type'   => 'post',
				'id'     => $post_id,
				'fields' => $values,
			]
		);
	}

	/**
	 * Snapshot an option before it is written.
	 *
	 * @param string $name Option name.
	 */
	public static function option( $name ) {
		if ( null === self::$current ) {
			return;
		}
		$sentinel = '__wpmcp_absent__';
		$value    = get_option( $name, $sentinel );
		self::push(
			'opt:' . $name,
			[
				'type'    => 'option',
				'name'    => (string) $name,
				'value'   => $sentinel === $value ? null : $value,
				'existed' => $sentinel !== $value,
			]
		);
	}

	/**
	 * Snapshot a term meta value before it is written.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 */
	public static function term_meta( $term_id, $key ) {
		if ( null === self::$current ) {
			return;
		}
		$term_id = (int) $term_id;
		$exists  = metadata_exists( 'term', $term_id, $key );
		self::push(
			'tm:' . $term_id . ':' . $key,
			[
				'type'    => 'term_meta',
				'id'      => $term_id,
				'key'     => (string) $key,
				'value'   => $exists ? get_term_meta( $term_id, $key, true ) : null,
				'existed' => $exists,
			]
		);
	}

	/**
	 * Snapshot WooCommerce product fields through the CRUD layer, so the undo
	 * goes back through WooCommerce's own setters rather than poking meta.
	 *
	 * @param int   $product_id Product or variation ID.
	 * @param array $fields     Field names, e.g. regular_price, stock_quantity.
	 */
	public static function product( $product_id, $fields ) {
		if ( null === self::$current || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( (int) $product_id );
		if ( ! $product ) {
			return;
		}
		$values = [];
		foreach ( (array) $fields as $field ) {
			$getter = 'get_' . $field;
			if ( ! method_exists( $product, $getter ) ) {
				continue;
			}
			$value = $product->$getter();
			if ( $value instanceof DateTimeInterface ) {
				$value = $value->format( 'Y-m-d H:i:s' );
			}
			if ( is_object( $value ) ) {
				continue;
			}
			$values[ $field ] = $value;
		}
		if ( ! $values ) {
			return;
		}
		self::push(
			'wc:' . (int) $product_id . ':' . implode( ',', array_keys( $values ) ),
			[
				'type'   => 'product',
				'id'     => (int) $product_id,
				'fields' => $values,
			]
		);
	}

	/**
	 * The stored operation index, newest first.
	 *
	 * @return array<int,array>
	 */
	public static function index() {
		$index = get_option( self::INDEX_OPTION, [] );
		return is_array( $index ) ? $index : [];
	}

	/**
	 * Persist the index.
	 *
	 * @param array $index Index rows.
	 */
	private static function write_index( $index ) {
		update_option( self::INDEX_OPTION, array_values( $index ), false );
	}

	/**
	 * Load one operation in full.
	 *
	 * @param string $id Operation ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$op = get_option( self::ENTRY_PREFIX . sanitize_key( $id ), null );
		return is_array( $op ) ? $op : null;
	}

	/**
	 * Reverse an operation.
	 *
	 * @param string $id    Operation ID.
	 * @param bool   $force Apply even when the recording was incomplete.
	 * @return array Result detail.
	 * @throws WPMCP_Tool_Exception When the operation is unknown or unsafe to undo.
	 */
	public static function undo( $id, $force = false ) {
		$id = sanitize_key( $id );
		$op = self::get( $id );
		if ( ! $op ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No operation "%s" is stored.', $id ),
				'Call list_operations to see what can still be undone — only the most recent ' . self::KEEP . ' are kept.',
				[ 'id' => $id ]
			);
		}

		$row = self::index_row( $id );
		if ( $row && ! empty( $row['undone'] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				sprintf( 'Operation %s has already been undone.', $id ),
				'Undoing it twice would re-apply stale values. Check list_operations for the current state.',
				[ 'id' => $id ]
			);
		}

		if ( empty( $op['complete'] ) && ! $force ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				sprintf( 'Operation %s was too large to record in full, so undoing it would only restore part of the change.', $id ),
				'Pass force=true to restore the part that was recorded, understanding the rest stays as it is.',
				[ 'id' => $id, 'recorded' => count( $op['records'] ) ]
			);
		}

		$restored = 0;
		$failed   = [];

		// Reverse order: the last write is the first thing put back.
		foreach ( array_reverse( $op['records'] ) as $record ) {
			try {
				self::apply( $record );
				$restored++;
			} catch ( Throwable $e ) {
				$failed[] = [
					'record' => $record['type'] . ':' . ( $record['id'] ?? $record['name'] ?? '?' ),
					'error'  => $e->getMessage(),
				];
			}
		}

		$index = self::index();
		foreach ( $index as $i => $entry ) {
			if ( ( $entry['id'] ?? '' ) === $id ) {
				$index[ $i ]['undone']    = true;
				$index[ $i ]['undone_at'] = gmdate( 'c' );
			}
		}
		self::write_index( $index );

		return [
			'id'       => $id,
			'tool'     => $op['tool'],
			'restored' => $restored,
			'failed'   => $failed,
			'partial'  => empty( $op['complete'] ),
		];
	}

	/**
	 * Apply one revert record.
	 *
	 * @param array $record Revert instruction.
	 */
	private static function apply( $record ) {
		switch ( $record['type'] ) {
			case 'post_meta':
				if ( empty( $record['existed'] ) ) {
					delete_post_meta( (int) $record['id'], $record['key'] );
				} else {
					update_post_meta( (int) $record['id'], $record['key'], wp_slash( $record['value'] ) );
				}
				break;

			case 'term_meta':
				if ( empty( $record['existed'] ) ) {
					delete_term_meta( (int) $record['id'], $record['key'] );
				} else {
					update_term_meta( (int) $record['id'], $record['key'], wp_slash( $record['value'] ) );
				}
				break;

			case 'post_seo':
				WPMCP_SEO::set_post_seo( (int) $record['id'], $record['fields'] );
				break;

			case 'post':
				wp_update_post( array_merge( [ 'ID' => (int) $record['id'] ], wp_slash( $record['fields'] ) ) );
				break;

			case 'option':
				if ( empty( $record['existed'] ) ) {
					delete_option( $record['name'] );
				} else {
					update_option( $record['name'], $record['value'] );
				}
				break;

			case 'product':
				if ( ! function_exists( 'wc_get_product' ) ) {
					throw new Exception( 'WooCommerce is not active, so the product cannot be restored.' );
				}
				$product = wc_get_product( (int) $record['id'] );
				if ( ! $product ) {
					throw new Exception( 'The product no longer exists.' );
				}
				foreach ( $record['fields'] as $field => $value ) {
					$setter = 'set_' . $field;
					if ( method_exists( $product, $setter ) ) {
						$product->$setter( $value );
					}
				}
				$product->save();
				break;
		}
	}

	/**
	 * One row from the index.
	 *
	 * @param string $id Operation ID.
	 * @return array|null
	 */
	private static function index_row( $id ) {
		foreach ( self::index() as $entry ) {
			if ( ( $entry['id'] ?? '' ) === $id ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Drop the operations that have aged out, deleting their stored records.
	 *
	 * @param array $index Current index.
	 */
	private static function prune( $index ) {
		if ( count( $index ) <= self::KEEP ) {
			return;
		}
		foreach ( array_slice( $index, self::KEEP ) as $entry ) {
			if ( ! empty( $entry['id'] ) ) {
				delete_option( self::ENTRY_PREFIX . $entry['id'] );
			}
		}
		self::write_index( array_slice( $index, 0, self::KEEP ) );
	}

	/**
	 * A short, sortable, collision-resistant operation ID.
	 *
	 * @return string
	 */
	private static function next_id() {
		return gmdate( 'ymdHis' ) . substr( md5( uniqid( '', true ) ), 0, 4 );
	}

	/**
	 * Condense arguments into something readable in the operation list without
	 * carrying payloads (or secrets) into storage.
	 *
	 * @param array $args Raw arguments.
	 * @return array
	 */
	private static function summarise_args( $args ) {
		$out = [];
		foreach ( (array) $args as $key => $value ) {
			if ( in_array( $key, [ 'content', 'base64', 'image_base64', 'password', 'api_key', 'key', 'secret', 'token' ], true ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			if ( is_scalar( $value ) ) {
				$out[ $key ] = is_string( $value ) && strlen( $value ) > 120 ? substr( $value, 0, 120 ) . '…' : $value;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = sprintf( '[%d items]', count( $value ) );
			}
		}
		return $out;
	}
}
