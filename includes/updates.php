<?php
/**
 * Hook plugin updater from git repository
 *
 * @package WP_Redirects
 */

namespace WP_Redirects;

/**
 * Fetch and return plugin data from declared remote
 *
 * @since 0.3.2
 *
 * @param false|object|array $default_result default data to return if fetch fails
 */
add_filter(
	'plugin_update_remote_data_' . plugin_file(),
	function ($default_result = '') {
		$remote_data = wp_remote_get(
			plugin_update_uri(),
			[
				'timeout' => 10,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if (
			is_wp_error($remote_data)
			|| 200 !== wp_remote_retrieve_response_code($remote_data)
			|| empty(wp_remote_retrieve_body($remote_data))
		) {
			return $default_result;
		}

		$remote_data = json_decode(wp_remote_retrieve_body($remote_data));

		$remote_data->package = $remote_data->download_link;
		$remote_data->sections = (array) $remote_data->sections;
		$remote_data->banners = (array) $remote_data->banners;

		return $remote_data;
	}
);

/**
 * Filters the response for the current WordPress.org Plugin Installation API request.
 *
 * Returning a non-false value will effectively short-circuit the WordPress.org API request.
 *
 * If `$action` is 'query_plugins' or 'plugin_information', an object MUST be passed.
 * If `$action` is 'hot_tags' or 'hot_categories', an array should be passed.
 *
 * @since 2.7.0
 *
 * @param false|object|array $result The result object or array. Default false.
 * @param string             $action The type of information being requested from the Plugin Installation API.
 * @param object             $args   Plugin API arguments.
 */
add_filter(
	'plugins_api',
	function ($result, $action, $args) {
		if ('plugin_information' !== $action) {
			return $result;
		}

		if (plugin_slug() !== $args->slug) {
			return $result;
		}

		$result = apply_filters('plugin_update_remote_data_' . plugin_file(), $result);
		$result->slug = plugin_slug();

		return $result;
	},
	10,
	3
);

/**
 * Fires once activated plugins have loaded.
 *
 * Pluggable functions are also available at this point in the loading order.
 *
 * @since 1.5.0
 */
add_filter(
	'update_plugins_' . wp_parse_url(sanitize_url(plugin_update_uri()), PHP_URL_HOST),
	function ($update, $plugin_data, $plugin_file) {
		if (plugin_file() !== $plugin_file) {
			return $update;
		}

		$remote_data = apply_filters('plugin_update_remote_data_' . plugin_file(), $update);

		if (!$remote_data) {
			return $update;
		}

		if (version_compare(get_bloginfo('version'), $remote_data->requires, '<')) {
			return $update;
		}

		if (version_compare(PHP_VERSION, $remote_data->requires_php, '<')) {
			return $update;
		}

		$remote_data->slug = plugin_slug();
		$remote_data->url = $plugin_data['PluginURI'];

		return (array) $remote_data;
	},
	10,
	4
);

/**
 * Fires when the upgrader process is complete.
 *
 * See also {@see 'upgrader_package_options'}.
 *
 * @since 3.6.0
 * @since 3.7.0 Added to WP_Upgrader::run().
 * @since 4.6.0 `$translations` was added as a possible argument to `$hook_extra`.
 *
 * @param \WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a
 *                                Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
 * @param array       $hook_extra {
 *     Array of bulk item update data.
 *
 *     @type string $action       Type of action. Default 'update'.
 *     @type string $type         Type of update process. Accepts 'plugin', 'theme', 'translation', or 'core'.
 *     @type bool   $bulk         Whether the update process is a bulk update. Default true.
 *     @type array  $plugins      Array of the basename paths of the plugins' main files .
 *     @type array  $themes       The theme slugs .
 *     @type array  $translations {
 *          Array of translations update data .
 *
 *          @type string $language The locale the translation is for .
 *          @type string $type     Type of translation . Accepts 'plugin', 'theme', or 'core' .
 *          @type string $slug     Text domain the translation is for . The slug of a theme / plugin or
 *                                 'default' for core translations .
 *          @type string $version  The version of a theme, plugin, or core .
 *     }
 * }
 */
add_action(
	'upgrader_process_complete',
	function ($upgrader_object, $options) {
		$basename_file = plugin_file();

		if (
			isset($options['action']) && 'update' === $options['action']
			&& isset($options['type']) && 'plugin' === $options['type']
		) {
			if (\in_array(plugin_file(), $options['plugins'], true)) {
				// delete plugin data.
				plugin_data(true);
			}
		}
	},
	10,
	2
);