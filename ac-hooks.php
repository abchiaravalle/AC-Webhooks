<?php


/**
 * Plugin Name: Webhooks for CF7
 * Description: Add and manage Contact Form 7 forms and their webhook URLs in a WordPress admin panel.
 * Version: 1
 * Author: Adam Chiaravalle @ ACWebDev, LLC.
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class acwebdev_Form_Webhook_Configurator {

	private $option_name = 'acwebdev_form_webhooks';
	private $log_option_name = 'acwebdev_webhook_logs';

	public function __construct() {
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('wpcf7_mail_sent', [$this, 'send_to_webhook']);
		// Add a custom endpoint for viewing JSON payload in a new tab.
		add_action('admin_init', [$this, 'view_log_payload']);
	}

	/**
	 * Add admin menu and submenu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'acwebdev Form Webhook Configurator',
			'acwebdev Webhooks',
			'manage_options',
			'acwebdev-webhook-config',
			[$this, 'settings_page'],
			'dashicons-admin-generic',
			80
		);

		add_submenu_page(
			'acwebdev-webhook-config',
			'acwebdev Webhook Logs',
			'Webhook Logs',
			'manage_options',
			'acwebdev-webhook-logs',
			[$this, 'logs_page']
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting($this->option_name, $this->option_name);
		// Register the log option as well but keep it hidden from direct user editing.
		register_setting($this->log_option_name, $this->log_option_name);
	}

	/**
	 * Render settings page (Webhook Configurator).
	 */
	public function settings_page() {
		$webhooks = get_option($this->option_name, []);
		$cf7_forms = $this->get_cf7_forms();

		?>
		<div class="wrap">
			<h1>acwebdev Form Webhook Configurator</h1>
			<form method="post" action="options.php">
				<?php settings_fields($this->option_name); ?>
				<table class="form-table" id="webhook-config-table">
					<thead>
						<tr>
							<th>Contact Form</th>
							<th>Webhook URL</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($webhooks)): ?>
							<?php foreach ($webhooks as $index => $webhook): ?>
								<tr>
									<td>
										<select name="<?php echo esc_attr($this->option_name . '[' . $index . '][form_id]'); ?>" required>
											<?php foreach ($cf7_forms as $form_id => $form_title): ?>
												<option value="<?php echo esc_attr($form_id); ?>" <?php selected($webhook['form_id'], $form_id); ?>>
													<?php echo esc_html($form_title); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<input type="url" name="<?php echo esc_attr($this->option_name . '[' . $index . '][webhook_url]'); ?>" value="<?php echo esc_attr($webhook['webhook_url']); ?>" required>
									</td>
									<td>
										<button type="button" class="button remove-row">Remove</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				<button type="button" class="button add-row">Add Row</button>
				<?php submit_button(); ?>
			</form>
		</div>

		<script>
			console.log("Initializing Webhook Configurator page... Very Verbose: DOMContentLoaded about to fire.");

			document.addEventListener('DOMContentLoaded', function() {
				console.log("Very Verbose: Webhook Configurator page loaded, setting up row addition and removal.");

				const table = document.querySelector('#webhook-config-table tbody');
				const addRowButton = document.querySelector('.add-row');
				const cf7Forms = <?php echo json_encode($cf7_forms); ?>;

				addRowButton.addEventListener('click', function() {
					console.log("Very Verbose: Add Row button clicked.");
					const rowCount = table.children.length;
					const newRow = document.createElement('tr');
					let formOptions = '';

					for (const [formId, formTitle] of Object.entries(cf7Forms)) {
						formOptions += `<option value="${formId}">${formTitle}</option>`;
					}

					newRow.innerHTML = `
						<td>
							<select name="<?php echo $this->option_name; ?>[${rowCount}][form_id]" required>
								${formOptions}
							</select>
						</td>
						<td>
							<input type="url" name="<?php echo $this->option_name; ?>[${rowCount}][webhook_url]" value="" required>
						</td>
						<td>
							<button type="button" class="button remove-row">Remove</button>
						</td>
					`;
					table.appendChild(newRow);
				});

				table.addEventListener('click', function(event) {
					if (event.target.classList.contains('remove-row')) {
						console.log("Very Verbose: Remove Row button clicked.");
						event.target.closest('tr').remove();
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Display the logs page.
	 */
	public function logs_page() {
		$logs = get_option($this->log_option_name, []);
		?>
		<div class="wrap">
			<h1>acwebdev Webhook Logs</h1>
			<table class="widefat fixed" cellspacing="0">
				<thead>
					<tr>
						<th scope="col">Date/Time</th>
						<th scope="col">Form ID</th>
						<th scope="col">Status Code</th>
						<th scope="col">View Payload</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($logs)): ?>
						<?php foreach ($logs as $log): ?>
							<tr>
								<td><?php echo esc_html($log['timestamp']); ?></td>
								<td><?php echo esc_html($log['form_id']); ?></td>
								<td><?php echo esc_html($log['status_code']); ?></td>
								<td>
									<?php 
									$url = add_query_arg([
										'page' => 'acwebdev-webhook-logs',
										'view_payload' => 'true',
										'log_id' => $log['id']
									], admin_url('admin.php'));
									?>
									<a href="<?php echo esc_url($url); ?>" target="_blank">View JSON</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr><td colspan="4">No logs found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<script>
			console.log("Very Verbose: Logs page has loaded. Listing out logs data...");
		</script>
		<?php
	}

	/**
	 * Handle viewing the log payload in a new tab.
	 */
	public function view_log_payload() {
		if (isset($_GET['page']) && $_GET['page'] === 'acwebdev-webhook-logs' && isset($_GET['view_payload']) && $_GET['view_payload'] === 'true') {
			$log_id = isset($_GET['log_id']) ? sanitize_text_field($_GET['log_id']) : '';
			if (!empty($log_id)) {
				$logs = get_option($this->log_option_name, []);
				foreach ($logs as $log) {
					if ($log['id'] === $log_id) {
						// Output JSON in a pretty format.
						header('Content-Type: application/json');
						echo json_encode(json_decode($log['payload_json'], true), JSON_PRETTY_PRINT);
						exit;
					}
				}
			}
		}
	}

	/**
	 * Get all CF7 forms.
	 *
	 * @return array Associative array of form IDs and titles.
	 */
	private function get_cf7_forms() {
		$forms = [];
		$posts = get_posts([
			'post_type' => 'wpcf7_contact_form',
			'numberposts' => -1,
		]);

		foreach ($posts as $post) {
			$forms[$post->ID] = $post->post_title;
		}

		return $forms;
	}

	/**
	 * Send form data to the configured webhooks.
	 *
	 * @param WPCF7_ContactForm $contact_form
	 */
	public function send_to_webhook($contact_form) {
		$form_id = $contact_form->id();
		$webhooks = get_option($this->option_name, []);

		foreach ($webhooks as $webhook) {
			if ((int) $webhook['form_id'] === (int) $form_id) {
				$submission = WPCF7_Submission::get_instance();
				if (!$submission) {
					return;
				}

				$data = $submission->get_posted_data();
				$json_data = json_encode($data);

				$response = wp_remote_post($webhook['webhook_url'], [
					'method'  => 'POST',
					'headers' => [
						'Content-Type' => 'application/json',
					],
					'body'    => $json_data,
				]);

				$status_code = is_wp_error($response) ? 'WP Error' : wp_remote_retrieve_response_code($response);

				// Log it
				$this->add_log($form_id, $status_code, $json_data);

				if (is_wp_error($response)) {
					error_log('CF7 JSON Webhook Error for Form ID ' . $form_id . ': ' . $response->get_error_message());
				}
				// Removed the 'return;' so it sends to every matching webhook
			}
		}
	}

	/**
	 * Add a log entry.
	 *
	 * @param int $form_id
	 * @param string|int $status_code
	 * @param string $payload_json
	 */
	private function add_log($form_id, $status_code, $payload_json) {
		$logs = get_option($this->log_option_name, []);

		// Use a unique ID for each log. This could be something else if you like.
		$new_log = [
			'id'          => uniqid('acwebdev_log_', true),
			'timestamp'   => current_time('mysql'),
			'form_id'     => $form_id,
			'status_code' => $status_code,
			'payload_json'=> $payload_json,
		];

		$logs[] = $new_log;
		update_option($this->log_option_name, $logs);
	}
}

// Initialize the plugin
new acwebdev_Form_Webhook_Configurator();

?>