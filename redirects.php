<?php
/**
 * Plugin Name: Redirects
 * Plugin URI: https://github.com/lightningspirit/wp-redirects
 * Description: A lightweight redirect manager for WordPress with zero bloat. Manage 3xx redirects with wildcard support and WP-CLI.
 * Version: 0.1.2
 * Author: lightningspirit
 * Author URI: https://github.com/lightningspirit
 * Requires at least: 6.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/lightningspirit/wp-redirects/raw/main/wp-info.json
 * Text Domain: redirects
 * Domain Path: /languages
 *
 * @package WP_Redirects
 */

/**
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace WP_Redirects;

// Don't load directly.
if (!\defined('ABSPATH')) {
	die('-1');
}

/**
 * Use any URL path relative to this plugin
 *
 * @param string $path the path.
 * @return string
 */
function plugin_uri($path)
{
	return plugins_url($path, __FILE__);
}

/**
 * Use any directory relative to this plugin
 *
 * @since 0.3.3
 * @param string $path the path.
 * @return string
 */
function plugin_dir($path)
{
	return plugin_dir_path(__FILE__) . $path;
}

/**
 * Gets the plugin unique identifier
 * based on 'plugin_basename' call.
 *
 * @since 0.3.3
 * @return string
 */
function plugin_file()
{
	return plugin_basename(__FILE__);
}

/**
 * Gets the plugin basedir
 *
 * @since 0.3.3
 * @return string
 */
function plugin_slug()
{
	return dirname(plugin_file());
}

/**
 * Gets the plugin version.
 *
 * @since 0.3.3
 * @param bool $revalidate force plugin revalidation.
 * @return string
 */
function plugin_data($revalidate = false)
{
	if (true === $revalidate) {
		delete_transient('plugin_data_' . plugin_file());
	}

	$plugin_data = get_transient('plugin_data_' . plugin_file());

	if (!$plugin_data) {
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data(__FILE__);
		$plugin_data = array_intersect_key(
			$plugin_data,
			array_flip(
				array('Version', 'UpdateURI')
			)
		);

		set_transient('plugin_data' . plugin_file(), $plugin_data);
	}

	return $plugin_data;
}

/**
 * Get plugin version
 *
 * @return string|null
 */
function plugin_version()
{
	$data = plugin_data();

	if (isset($data['Version'])) {
		return $data['Version'];
	}
}

/**
 * Get plugin update URL
 *
 * @return string|null
 */
function plugin_update_uri()
{
	$data = plugin_data();

	if (isset($data['UpdateURI'])) {
		return $data['UpdateURI'];
	}
}


// Include update checker.
require_once __DIR__ . '/includes/updates.php';

// Include storage class.
require_once __DIR__ . '/includes/class-storage.php';

// Include render hooks.
require_once __DIR__ . '/includes/render-hooks.php';

/**
 * WP-CLI commands
 *
 * wp redirect list
 * wp redirect add --from=/old/* --to=/new/$1 --type=301
 * wp redirect delete --from=/old/*
 * wp redirect export --file=redirects.json
 * wp redirect import --file=redirects.json
 * wp redirect test --url='/old/abc?x=1'
 */
if (\defined('WP_CLI') && WP_CLI) {
	require_once __DIR__ . '/includes/class-cli.php';

	add_action('cli_init', function () {
		WP_CLI::add_command('redirect', CLI::class);
	});
}

/**
 * Loads plugin translations and files
 *
 * @since 0.1.0
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain(
			'redirects',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages/',
		);

		// Include required files.
		if (is_admin()) {
			require_once __DIR__ . '/includes/admin-hooks.php';
		}
	}
);

/**
 * Prevent activation if Redirection plugin is active
 *
 * @since 0.1.0
 */
register_activation_hook(
	__FILE__,
	function () {
		if (class_exists('Redirection_Admin')) {
			deactivate_plugins(plugin_basename(__FILE__));

			wp_die(
				'<strong>Redirects</strong> cannot be activated because it conflicts with Redirection.<br><br>
				Please deactivate Redirection first.',
				'Plugin conflict',
				['back_link' => true]
			);
		}
	}
);

/**
 * Flush rewrite rules on deactivation
 *
 * @since 0.1.0
 */
register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);
