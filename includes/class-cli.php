<?php
/**
 * WP-CLI integration for the Redirects plugin.
 *
 * Provides commands to list, add, delete, import/export, and test redirect rules.
 *
 * @package WP_Redirects
 * @since 0.1.0
 */

/**
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace WP_Redirects;
use WP_CLI;

// Exit if WP-CLI is not available.
if ( ! \defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP-CLI commands for Redirects plugin.
 *
 * @since 0.1.0
 */
final class CLI {
	private $storage;

	/**
	 * Constructor.
	 *
	 * Initializes storage.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->storage = new Storage();
	}

	/**
	 * List all redirect rules.
	 *
	 * Prints a table with `from`, `to`, and `type`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sr-redirect list
	 *
	 * @since 0.1.0
	 *
	 * @param array $_args       Positional arguments (unused).
	 * @param array $_assoc_args Associative arguments (unused).
	 * @return void
	 */
	public function list( $_args, $_assoc_args ) {
		$rules = $this->storage->get_rules();
		if ( ! $rules ) {
			WP_CLI::log( 'No redirects.' );
			return;
		}

		$items = array_map(
			fn($r) => [
				'from' => $r['from'],
				'to' => $r['to'],
				'type' => (int) $r['type'],
			],
			$rules
		);

		WP_CLI\Utils\format_items( 'table', $items, ['from', 'to', 'type'] );
	}

	/**
	 * Add or update a redirect rule.
	 *
	 * If a rule with the same `from` already exists, it is replaced.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<path>]
	 * : Source path to match (may include `*` wildcards). Must start with `/`.
	 *
	 * [--to=<url>]
	 * : Target URL or path. Supports `$1`, `$2`... for wildcard captures.
	 *
	 * [--type=<code>]
	 * : HTTP status code: 301 or 302. Default: 301.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sr-redirect add --from=/old --to=/new --type=301
	 *     wp sr-redirect add --from=/old/* --to=/new/$1 --type=301
	 *
	 * @since 0.1.0
	 *
	 * @param array $_args      Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function add( $_args, $assoc_args ) {
		$from = isset( $assoc_args['from'] ) ? $this->storage->normalize_from( (string) $assoc_args['from'] ) : '';
		$to   = isset( $assoc_args['to'] ) ? esc_url_raw( (string) $assoc_args['to'] ) : '';
		$type = isset( $assoc_args['type'] ) ? (int) $assoc_args['type'] : 301;

		// Validate required fields.
		if ( ! $from || ! $to ) {
			WP_CLI::error( 'Required: --from and --to' );
		}

		// Validate allowed redirect types.
		if ( ! in_array( $type, array( 301, 302 ), true ) ) {
			WP_CLI::error( '--type must be 301 or 302' );
		}

		$rules = $this->storage->get_rules();

		// Replace a rule if `from` matches an existing entry, otherwise append.
		$replaced = false;
		foreach ( $rules as $i => $r ) {
			if ( ( $r['from'] ?? '' ) === $from ) {
				$rules[ $i ] = [
					'from' => $from,
					'to' => $to,
					'type' => $type,
				];
				$replaced    = true;
				break;
			}
		}

		if ( ! $replaced ) {
			$rules[] = [
				'from' => $from,
				'to' => $to,
				'type' => $type,
			];
		}

		// Persist rules and report outcome.
		$this->storage->save_rules( $rules );
		WP_CLI::success( $replaced ? "Updated: $from" : "Added: $from" );
	}

	/**
	 * Delete a redirect rule by its `from` value.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<path>]
	 * : Source path to delete (exact match, including any `*`).
	 *
	 * ## EXAMPLES
	 *
	 *     wp sr-redirect delete --from=/old
	 *     wp sr-redirect delete --from=/old/*
	 *
	 * @since 0.1.0
	 *
	 * @param array $_args      Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( $_args, $assoc_args ) {
		$from = isset( $assoc_args['from'] ) ? $this->storage->normalize_from( (string) $assoc_args['from'] ) : '';
		if ( ! $from ) {
			WP_CLI::error( 'Required: --from' );
		}

		$rules  = $this->storage->get_rules();
		$before = \count( $rules );

		// Remove rules whose `from` equals the requested value.
		$rules = array_values(
			array_filter(
				$rules,
				fn( $r ) => ( $r['from'] ?? '' ) !== $from
			)
		);

		// If count didn't change, nothing was removed.
		if ( \count( $rules ) === $before ) {
			WP_CLI::warning( "Not found: $from" );
			return;
		}

		$this->storage->save_rules( $rules );
		WP_CLI::success( "Deleted: $from" );
	}

	/**
	 * Export redirect rules to a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Output file path (e.g. redirects.json).
	 *
	 * ## EXAMPLES
	 *
	 *     wp sr-redirect export --file=redirects.json
	 *
	 * @since 0.1.0
	 *
	 * @param array $_args      Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function export( $_args, $assoc_args ) {
		$file = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : '';
		if ( ! $file ) {
			WP_CLI::error( 'Required: --file=redirects.json' );
		}

		$rules = $this->storage->get_rules();
		$json  = wp_json_encode( $rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $json === false ) {
			WP_CLI::error( 'Failed to encode JSON' );
		}

		if ( file_put_contents( $file, $json ) === false ) {
			WP_CLI::error( "Failed to write: $file" );
		}

		WP_CLI::success( "Exported to: $file" );
	}

	/**
	 * Import redirect rules from a JSON file.
	 *
	 * The JSON must decode into an array of rules with keys: `from`, `to`, `type`.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Input file path (e.g. redirects.json).
	 *
	 * ## EXAMPLES
	 *
	 *     wp sr-redirect import --file=redirects.json
	 *
	 * @since 0.1.0
	 *
	 * @param array $_args      Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function import( $_args, $assoc_args ) {
		$file = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : '';
		if ( ! $file ) {
			WP_CLI::error( 'Required: --file=redirects.json' );
		}

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "File not found: $file" );
		}

		$raw = file_get_contents( $file );
		if ( $raw === false ) {
			WP_CLI::error( "Failed to read: $file" );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( 'Invalid JSON format (expected array)' );
		}

		$this->storage->save_rules( $data );
		WP_CLI::success( "Imported from: $file" );
	}

	/**
	 * Dry-run a redirect match for a given URL or path.
	 *
	 * This command does not send headers; it only shows what rule would match and
	 * what the resulting target URL would be (including query string behavior).
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : URL or path to test (e.g. `/old/abc?x=1` or `https://example.com/old/abc?x=1`).
	 *
	 * ## EXAMPLES
	 *
	 *     wp sr-redirect test --url='/old/abc?utm=1'
	 *     wp sr-redirect test --url='https://site.test/old/abc?utm=1'
	 *
	 * @since 0.1.0
	 *
	 * @param array $_args      Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function test( $_args, $assoc_args ) {
		$url = isset( $assoc_args['url'] ) ? (string) $assoc_args['url'] : '';
		if ( $url === '' ) {
			WP_CLI::error( "Required: --url='/path?x=1'" );
		}

		$match = match_redirect( $url );
		if ( ! $match ) {
			WP_CLI::log( 'No match.' );
			return;
		}

		WP_CLI::log( 'Matched from: ' . $match['matched_from'] );
		WP_CLI::log( 'Type: ' . $match['type'] );
		WP_CLI::log( 'Target: ' . $match['target'] );
	}
}
