<?php
/**
 * Redirects Admin Page
 * 
 * @package WP_Redirects
 * @since 0.1.0
 */

/**
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace WP_Redirects;

use function in_array;
use function is_array;

// Don't load directly.
if (!\defined('ABSPATH')) {
	die('-1');
}

/**
 * Detect conflicting plugins and deactivate this plugin if found.
 */
add_action('admin_init', function () {
	if (!current_user_can('activate_plugins')) {
		return;
	}

	$conflicts = [
		'redirection/redirection.php',
	];

	foreach ($conflicts as $plugin_file) {
		if (is_plugin_active($plugin_file)) {
			deactivate_plugins(plugin_file());

			add_action('admin_notices', function () use ($plugin_file) {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						\printf(
							/* translators: %1$s: plugin name, %2$s: conflicting plugin file */
							esc_html__(
								'%1$s was deactivated because it conflicts with %2$s. Only one redirect manager can be active at a time.',
								'redirects'
							),
							'<strong>Redirects</strong>',
							'<strong>' . esc_html($plugin_file) . '</strong>'
						);
						?>
					</p>
				</div>
				<?php
			});

			return;
		}
	}
});

/**
 * Admin page
 */
add_action(
	'admin_menu',
	function () {
		add_management_page(
			__('Redirects', 'redirects'),
			__('Redirects', 'redirects'),
			'manage_options',
			'redirects',
			__NAMESPACE__ . '\admin_page'
		);
	}
);

function admin_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}

	$storage = new Storage();
	$rules = $storage->get_rules();

	// Allowed redirect status codes.
	$allowed_types = [301, 302, 303, 307, 308];

	// Friendly labels (fully translatable), while keeping numeric values.
	$type_labels = [
		301 => __('301 (Moved Permanently)', 'redirects'),
		302 => __('302 (Found / Temporary)', 'redirects'),
		303 => __('303 (See Other)', 'redirects'),
		307 => __('307 (Temporary Redirect)', 'redirects'),
		308 => __('308 (Permanent Redirect)', 'redirects'),
	];

	if (isset($_POST['sr_save'])) {
		check_admin_referer('sr_save_redirects');

		$new_rules = [];

		$froms = $_POST['from'] ?? array();
		$tos = $_POST['to'] ?? array();
		$types = $_POST['type'] ?? array();

		$froms = is_array($froms) ? wp_unslash($froms) : array();
		$tos = is_array($tos) ? wp_unslash($tos) : array();
		$types = is_array($types) ? wp_unslash($types) : array();

		if (is_array($froms)) {
			foreach ($froms as $i => $from) {
				$from = $storage->normalize_from((string) $from);
				$to = esc_url_raw((string) ($tos[$i] ?? ''));
				$type = (int) ($types[$i] ?? 301);

				if ($from && $to) {
					$new_rules[] = array(
						'from' => $from,
						'to' => $to,
						'type' => in_array($type, $allowed_types, true) ? $type : 301,
					);
				}
			}
		}

		$storage->save_rules($new_rules);
		$rules = $storage->get_rules();

		echo '<div class="notice notice-success is-dismissible"><p>';
		esc_html_e('Redirects saved.', 'redirects');
		echo '</p></div>';
	}
	?>

	<div class="wrap">
		<h1><?php esc_html_e('Redirects', 'redirects'); ?></h1>

		<p>
			<strong><?php esc_html_e('Wildcards:', 'redirects'); ?></strong>
			<?php esc_html_e('Use', 'redirects'); ?> <code>*</code>
			<?php esc_html_e('in “From”. Captures map to', 'redirects'); ?>
			<code>$1</code>, <code>$2</code><?php esc_html_e('… in “To”.', 'redirects'); ?>
			<?php esc_html_e('Example:', 'redirects'); ?> <code>/old/*</code> → <code>/new/$1</code><br>
			<strong><?php esc_html_e('Query string:', 'redirects'); ?></strong>
			<?php esc_html_e('Preserved automatically (e.g.', 'redirects'); ?>
			<code>?utm=...</code><?php esc_html_e(').', 'redirects'); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field('sr_save_redirects'); ?>

			<p>
				<button type="button" class="button" id="sr-add-row">
					<?php esc_html_e('Add redirect', 'redirects'); ?>
				</button>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width: 34%;">
							<?php esc_html_e('From (path, can include *)', 'redirects'); ?>
						</th>
						<th style="width: 46%;">
							<?php esc_html_e('To (URL, can include $1…)', 'redirects'); ?>
						</th>
						<th style="width: 12%;">
							<?php esc_html_e('Type', 'redirects'); ?>
						</th>
						<th style="width: 8%;">
							<?php esc_html_e('Actions', 'redirects'); ?>
						</th>
					</tr>
				</thead>

				<tbody id="sr-redirect-rows">
					<?php foreach ($rules as $r): ?>
						<tr>
							<td>
								<input type="text" name="from[]" value="<?php echo esc_attr($r['from']); ?>"
									class="regular-text" style="width:100%">
							</td>
							<td>
								<input type="text" name="to[]" value="<?php echo esc_attr($r['to']); ?>" class="regular-text"
									style="width:100%">
							</td>
							<td>
								<select name="type[]">
									<?php foreach ($allowed_types as $code): ?>
										<option value="<?php echo esc_attr((string) $code); ?>" <?php selected((int) $r['type'], $code); ?>>
											<?php echo esc_html($type_labels[$code] ?? (string) $code); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<button type="button" class="button sr-remove-row">
									<?php esc_html_e('Remove', 'redirects'); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button class="button button-primary" name="sr_save" value="1">
					<?php esc_html_e('Save Redirects', 'redirects'); ?>
				</button>
			</p>
		</form>
	</div>

	<!-- Template for new rows (keeps PHP out of JS) -->
	<script type="text/template" id="sr-row-template">
					<tr>
						<td>
							<input
								type="text"
								name="from[]"
								class="regular-text"
								style="width:100%"
								placeholder="/old/*"
							>
						</td>
						<td>
							<input
								type="text"
								name="to[]"
								class="regular-text"
								style="width:100%"
								placeholder="/new/$1 or https://example.com/new/$1"
							>
						</td>
						<td>
							<select name="type[]">
								<?php foreach ($allowed_types as $code): ?>
												<option value="<?php echo esc_attr((string) $code); ?>">
													<?php echo esc_html($type_labels[$code] ?? (string) $code); ?>
												</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<button type="button" class="button sr-remove-row"><?php esc_html_e('Remove', 'redirects'); ?></button>
						</td>
					</tr>
				</script>

	<script>
		(function () {
			const tableBody = document.getElementById('sr-redirect-rows');
			const addBtn = document.getElementById('sr-add-row');
			const tplEl = document.getElementById('sr-row-template');

			if (!tableBody || !addBtn || !tplEl) return;

			function rowHasValues(row) {
				const from = row.querySelector('input[name="from[]"]');
				const to = row.querySelector('input[name="to[]"]');

				const fromVal = (from && from.value ? from.value.trim() : '');
				const toVal = (to && to.value ? to.value.trim() : '');

				return Boolean(fromVal || toVal);
			}

			function ensureAtLeastOneRow() {
				// If there are no rows (e.g. user removed all via devtools), add one.
				const rows = tableBody.querySelectorAll('tr');
				if (rows.length === 0) {
					addRow();
				}
			}

			function addRow() {
				const html = tplEl.textContent || tplEl.innerHTML;
				tableBody.insertAdjacentHTML('beforeend', html);

				const lastRow = tableBody.querySelector('tr:last-child');
				const fromInput = lastRow ? lastRow.querySelector('input[name="from[]"]') : null;
				if (fromInput) fromInput.focus();
			}

			addBtn.addEventListener('click', function () {
				addRow();
			});

			tableBody.addEventListener('click', function (e) {
				const btn = e.target && e.target.closest ? e.target.closest('.sr-remove-row') : null;
				if (!btn) return;

				const row = btn.closest('tr');
				if (!row) return;

				const rows = tableBody.querySelectorAll('tr');

				// Prevent removing the last remaining row.
				if (rows.length <= 1) {
					alert('<?php echo esc_js(__('You must keep at least one row. Add a new row first, then remove this one.', 'redirects')); ?>');
					return;
				}

				// Confirm removal only if the row has values.
				if (rowHasValues(row)) {
					const ok = window.confirm('<?php echo esc_js(__('Remove this redirect?', 'redirects')); ?>');
					if (!ok) return;
				}

				row.remove();
				ensureAtLeastOneRow();
			});
		})();
	</script>

	<?php
}
