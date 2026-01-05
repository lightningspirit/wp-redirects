<?php
/**
 * Storage functions for the Redirects plugin.
 * 
 * @package WP_Redirects
 * @since 0.1.0
 */

/**
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace WP_Redirects;

// Don't load directly.
if ( ! \defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Class Storage
 *
 * Handles storage and retrieval of redirect rules.
 *
 * @since 0.1.0
 */
final class Storage {
	/**
	 * Option key for storing redirect rules.
	 */
	public const OPTION_KEY = '_redirect_rules';
	
	/**
	 * Redirect rules stored as list:
	 * [
	 *   ['from' => '/old', 'to' => '/new', 'type' => 301],
	 *   ['from' => '/old/*', 'to' => '/new/$1', 'type' => 301],
	 * ]
	 * @return array
	 */
	public function get_rules(): array {
		$rules = get_option( self::OPTION_KEY, array() );
		return \is_array( $rules ) ? $rules : [];
	}

	/**
	 * Save redirect rules.
	 * @param array $rules
	 * @return void
	 */
	public function save_rules( array $rules ): void {
		$clean = [];
		foreach ( $rules as $r ) {
			if ( ! \is_array( $r ) ) {
				continue;
			}
			$from = isset( $r['from'] ) ? $this->normalize_from( (string) $r['from'] ) : '';
			$to   = isset( $r['to'] ) ? esc_url_raw( (string) $r['to'] ) : '';
			$type = isset( $r['type'] ) ? (int) $r['type'] : 301;

			if ( $from && $to ) {
				$clean[] = array(
					'from' => $from,
					'to'   => $to,
					'type' => \in_array( $type, [301, 302], true ) ? $type : 301,
				);
			}
		}
		update_option( self::OPTION_KEY, $clean, false );
	}

	/**
	 * Normalize 'from' path.
	 * @param string $from
	 * @return string
	 */
	public function normalize_from( string $from ): string {
		$from = trim( $from );
		if ( $from === '' ) {
			return '';
		}
		return '/' . ltrim( $from, '/' );
	}
}
