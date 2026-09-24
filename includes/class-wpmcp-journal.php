<?php
/**
 * Operation journal: the undo layer.
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
			'label'     => '',
			'key_id'    => WPMCP_Keys::current() ? WPMCP_Keys::current()['id'] : '',
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

		// An operation with only notes is still stored: those notes say what
		// the call changed that undo cannot put back, and dropping them would
		// leave the change looking as if it never happened.
		if ( ! $op || ( ! $op['records'] && ! $op['notes'] ) ) {
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
				'notes'    => $op['notes'],
				'label'    => $op['label'] ?? '',
				'key_id'   => $op['key_id'] ?? '',
				'undone'   => false,
				'args'     => $op['args'],
			]
		);
		self::write_index( $index );
		self::prune( $index );

		return $id;
	}

	/**
	 * Record something the current operation changed that undo cannot put
	 * back, e.g. "Users changed by save_user are not restored by undo." Undo
	 * reports these notes and treats the restore as partial, so nobody is told
	 * everything was reverted when it was not.
	 *
	 * @param string $note Note.
	 */
	public static function note( $note ) {
		if ( self::$current && count( self::$current['notes'] ) < 20 && ! in_array( (string) $note, self::$current['notes'], true ) ) {
			self::$current['notes'][] = (string) $note;
		}
	}

	/**
	 * Attach a human label to the current operation, such as a restore point
	 * name. Unlike a note, a label does not mark the undo as partial.
	 *
	 * @param string $label Label.
	 */
	public static function label( $label ) {
		if ( self::$current ) {
			self::$current['label'] = (string) $label;
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
	 * Snapshot the SEO plugin's own meta keys for a post, whichever engine is
	 * active, so an SEO write can be reversed without the caller knowing them.
	 *
	 * The raw keys are recorded rather than the normalised field set: replaying
	 * normalised fields through set_post_seo() wrote every field back, which
	 * created overrides that never existed (Yoast noindex=2, an explicit
	 * "index" robots entry in Rank Math). Raw keys put back exactly what was
	 * there, and delete what was not.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function post_seo( $post_id ) {
		if ( null === self::$current ) {
			return;
		}
		$post_id = (int) $post_id;
		$meta    = [];
		foreach ( WPMCP_SEO::post_meta_keys() as $key ) {
			$exists        = metadata_exists( 'post', $post_id, $key );
			$meta[ $key ] = [
				'existed' => $exists,
				'value'   => $exists ? get_post_meta( $post_id, $key, true ) : null,
			];
		}
		// One record per post rather than one per key, so a restore point over
		// a few hundred posts stays inside MAX_RECORDS.
		self::push(
			'seo:' . $post_id,
			[
				'type' => 'post_meta_set',
				'id'   => $post_id,
				'meta' => $meta,
			]
		);
	}

	/**
	 * Snapshot a term's SEO storage before it is written: term meta for Rank
	 * Math, or the single wpseo_taxonomy_meta option Yoast keeps for all terms.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug (unused for Rank Math).
	 */
	public static function term_seo( $term_id, $taxonomy = '' ) {
		if ( null === self::$current ) {
			return;
		}
		unset( $taxonomy );
		if ( 'rankmath' === WPMCP_SEO::provider() ) {
			foreach ( WPMCP_SEO::term_meta_keys() as $key ) {
				self::term_meta( (int) $term_id, $key );
			}
			return;
		}
		self::option( 'wpseo_taxonomy_meta' );
	}

	/**
	 * Ask undo to rebuild the rewrite rules once everything else is restored.
	 * Call this BEFORE snapshotting the options that shape the rules: records
	 * are replayed newest first, so an earlier record runs last.
	 */
	public static function rewrite_flush() {
		if ( null === self::$current ) {
			return;
		}
		self::push( 'rewrite_flush', [ 'type' => 'rewrite_flush', 'id' => 0 ] );
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
				'Call list_operations to see what can still be undone. Only the most recent ' . self::KEEP . ' are kept.',
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

		$notes = isset( $op['notes'] ) && is_array( $op['notes'] ) ? array_values( $op['notes'] ) : [];

		return [
			'id'           => $id,
			'tool'         => $op['tool'],
			'restored'     => $restored,
			'failed'       => $failed,
			// Partial whenever something is still as the operation left it: the
			// recording overflowed, a record failed, or the tool noted changes
			// that have no revert record at all.
			'partial'      => empty( $op['complete'] ) || $failed || $notes,
			'truncated'    => empty( $op['complete'] ),
			'not_restored' => $notes,
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

			case 'post_meta_set':
				foreach ( (array) $record['meta'] as $key => $entry ) {
					if ( empty( $entry['existed'] ) ) {
						delete_post_meta( (int) $record['id'], $key );
					} else {
						update_post_meta( (int) $record['id'], $key, wp_slash( $entry['value'] ) );
					}
				}
				break;

			case 'post_seo':
				// Legacy record from before raw SEO keys were snapshotted.
				WPMCP_SEO::set_post_seo( (int) $record['id'], $record['fields'] );
				break;

			case 'rewrite_flush':
				// The restored permalink options are only read by WP_Rewrite
				// on init, so re-initialise it and do a hard flush (which also
				// rewrites .htaccess where there is one). Taxonomy and post
				// type permastructs were registered at init with the old
				// front, though, so the stored rules are then dropped and
				// WordPress rebuilds them cleanly on the next request.
				global $wp_rewrite;
				if ( $wp_rewrite instanceof WP_Rewrite ) {
					$wp_rewrite->init();
				}
				flush_rewrite_rules();
				delete_option( 'rewrite_rules' );
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
					WPMCP_Errors::fail(
						WPMCP_Errors::DEPENDENCY_MISSING,
						'WooCommerce is not active, so the product cannot be restored.',
						'Activate WooCommerce and run undo_operation again.'
					);
				}
				$product = wc_get_product( (int) $record['id'] );
				if ( ! $product ) {
					WPMCP_Errors::fail(
						WPMCP_Errors::NOT_FOUND,
						sprintf( 'Product %d no longer exists, so it cannot be restored.', (int) $record['id'] ),
						'The rest of the operation is still restored. Recreate the product if it is needed.'
					);
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
