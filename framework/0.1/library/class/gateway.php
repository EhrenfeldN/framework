<?php

//--------------------------------------------------
// http://www.phpprime.com/doc/setup/gateways/
//--------------------------------------------------

	// TODO: Update to oAuth (see documentation for notes)

	if (config::get('gateway.active') !== true) {
		exit_with_error('Gateway disabled.');
	}

	class gateway_base extends check {

		//--------------------------------------------------
		// Variables

			private $config = array();
			private $exit_on_error = true;
			private $response_data = NULL;
			private $response_mime = NULL;
			private $response_code = NULL;
			private $response_error = NULL;

		//--------------------------------------------------
		// Setup

			public function __construct() {

				//--------------------------------------------------
				// Parse the config

					$include_path = APP_ROOT . '/library/gateway/default.ini';
					if (is_file($include_path)) {
						$this->config = parse_ini_file($include_path, true);
					}

					$include_path = APP_ROOT . '/library/gateway/' . config::get('gateway.server') . '.ini';
					if (is_file($include_path)) {

						$config_extra = parse_ini_file($include_path, true);
						foreach ($config_extra as $section => $values) {
							if (is_array($values)) {
								foreach ($values as $key => $value) {
									$this->config[$section][$key] = $value;
								}
							}
						}

					}

			}

			function exit_on_error_set($exit_on_error = NULL) {
				if ($exit_on_error !== NULL) {
					$this->exit_on_error = $exit_on_error;
				}
				return $this->exit_on_error;
			}

			function error_get() {
				return $this->response_error;
			}

			function get_all() {

				$gateway_urls = array();
				$gateway_dirs = array(
						'framework' => FRAMEWORK_ROOT . '/library/gateway/',
						'app' => APP_ROOT . '/gateway/',
					);

				$gateway_hide = array('framework-file', 'js-code', 'payment');

				foreach ($gateway_dirs as $gateway_source => $gateway_dir) {
					if ($handle = opendir($gateway_dir)) {
						while (false !== ($file = readdir($handle))) {
							if (is_file($gateway_dir . $file) && preg_match('/^([a-zA-Z0-9_\-]+)\.php$/', $file, $matches) && ($gateway_source == 'app' || !in_array($matches[1], $gateway_hide))) {

								$gateway_urls[$matches[1]] = gateway_url($matches[1]);

							}
						}
					}
				}

				asort($gateway_urls);

				return $gateway_urls;

			}

			function connection_test($gateway_name) {
				$this->_client_get($gateway_name, NULL, true);
			}

		//--------------------------------------------------
		// Client support

			function string_get($gateway_name, $data = NULL) {

				//--------------------------------------------------
				// Call

					$this->_client_get($gateway_name, $data);

				//--------------------------------------------------
				// Return

					return $this->response_data;

			}

			function array_get($gateway_name, $data = NULL) {

				//--------------------------------------------------
				// Call

					$this->_client_get($gateway_name, $data);

				//--------------------------------------------------
				// Parse

					if ($this->response_mime != 'application/x-www-form-urlencoded') {
						exit_with_error('Did not return valid data from "' . $gateway_name . '"', $this->response_data);
					}

					parse_str($this->response_data, $this->response_data);

					if (ini_get('magic_quotes_gpc')) {
						$this->response_data = strip_slashes_deep($this->response_data);
					}

					if (!is_array($this->response_data)) {
						exit_with_error('Did not return array data from "' . $gateway_name . '"', $this->response_data);
					}

				//--------------------------------------------------
				// Return

					return $this->response_data;

			}

			private function _client_get($gateway_name, $data = NULL, $connection_test = false) {

				//--------------------------------------------------
				// Check tables

					$this->_check_tables();

				//--------------------------------------------------
				// Client details

					if (isset($this->config['client']['name'])) {
						$client_name = $this->config['client']['name'];
					} else {
						exit_with_error('Missing client name');
					}

					if (isset($this->config['client']['key'])) {
						$client_key = $this->config['client']['key'];
					} else {
						exit_with_error('Missing client name');
					}

				//--------------------------------------------------
				// Get gateway details

					$now = new timestamp();

					$gateway_host = $this->_gateway_config($gateway_name, 'host');
					$gateway_log = $this->_gateway_config($gateway_name, 'log');

					$host_domain = $this->_host_config($gateway_host, 'domain');
					$host_port = $this->_host_config($gateway_host, 'port');

					$gateway_url = ($host_port == 80 ? 'http://' : 'https://') . $host_domain . ':' . $host_port . '/a/api/' . rawurlencode($gateway_name) . '/?client=' . rawurlencode($client_name);

				//--------------------------------------------------
				// Debug

					if (function_exists('debug_note')) {
						debug_note('API Call: ' . $gateway_url);
					}

				//--------------------------------------------------
				// Log

					$log_id = NULL;

					if ($gateway_log) {

						//--------------------------------------------------
						// Cleanup

							$db = db_get();

							$sql = 'DELETE FROM
										' . DB_PREFIX . 'gateway_log
									WHERE
										request_date < ?';

							$parameters = array();
							$parameters[] = array('s', date('Y-m-d H:i:s', strtotime('-1 month')));

							$db->query($sql, $parameters);

						//--------------------------------------------------
						// Add

							$db->insert(DB_PREFIX . 'gateway_log', array(
									'id'           => '',
									'gateway'      => $gateway_name,
									'request_url'  => $gateway_url,
									'request_data' => debug_dump($data),
									'request_date' => $now,
								));

							$log_id = $db->insert_id();

					}

				//--------------------------------------------------
				// Get a temporary key

					$service_pass = '';

					$timeout_new = 2;
					$timeout_old = ini_set('default_socket_timeout', $timeout_new);

					$error_script = APP_ROOT . '/library/gateway/error.sh';

					for ($k = 1; $k <= 3; $k++) {

						$handle = @fopen($gateway_url, 'r'); // Check the '/etc/hosts' file if the DNS is having problems
						if ($handle) {

							while (!feof($handle)) {
								$service_pass .= fgets($handle, 4096);
							}
							fclose($handle);

							break;

						} else {

							if ($k == 1 && is_file($error_script)) {
								$error_output = "\n\n" . shell_exec(escapeshellcmd($error_script) . ' ' . escapeshellarg($host_domain) . ' ' . escapeshellarg($host_port));
							} else {
								$error_output = '';
							}

							report_add('Connection issue on try ' . $k  . ' (' . $gateway_name . ')' . "\n\n" . $gateway_url . $error_output);

						}

					}

					ini_set('default_socket_timeout', $timeout_old);

				//--------------------------------------------------
				// Check for errors

					if ($service_pass == '') {

						$notice = 'Could not return the challenge hash from the gateway (' . $gateway_name . ')';

						$error_url = config::get('gateway.error_url');

						if ($error_url) {

							report_add($notice . "\n\n" . $gateway_url);

							redirect($error_url);

						} else {

							exit_with_error($notice, $gateway_url);

						}

					}

					if ($connection_test) {
						return true;
					}

				//--------------------------------------------------
				// Build the query URL

					$pass = hash('sha256', ($service_pass . $client_key)); // Needs to be a fast hash

					$gateway_url_pass = $gateway_url . '&pass=' . rawurlencode($pass);

				//--------------------------------------------------
				// Send request to gateway

					$this->response_data = '';

					$socket = new socket();
					$socket->exit_on_error_set(false);

					if ($data === NULL) {

						$socket->post($gateway_url_pass);

					} else if (!is_array($data)) {

						$socket->post($gateway_url_pass, $data);

					} else {

						foreach (config::get('gateway.default_values', array()) as $name => $value) {
							$socket->value_set($name, $value);
						}

						foreach ($data as $key => $value) {
							$socket->value_set($key, $value);
						}

						$socket->post($gateway_url_pass);

					}

					$this->response_data = $socket->response_data_get();
					$this->response_mime = $socket->response_mime_get();
					$this->response_code = $socket->response_code_get();

				//--------------------------------------------------
				// Debug

					if (function_exists('debug_note')) {
						debug_note('API Returned: ' . $gateway_url . "\n\n" . $this->response_data);
					}

				//--------------------------------------------------
				// Log

					if ($log_id !== NULL) {

						$sql = 'UPDATE
										' . DB_PREFIX . 'gateway_log
									SET
										response_code = ?,
										response_mime = ?,
										response_data = ?,
										response_date = ?
									WHERE
										id = ?';

						$parameters = array();
						$parameters[] = array('s', $this->response_code);
						$parameters[] = array('s', $this->response_mime);
						$parameters[] = array('s', $this->response_data);
						$parameters[] = array('s', $now);
						$parameters[] = array('i', $log_id);

						$db->query($sql, $parameters);

					}

				//--------------------------------------------------
				// Error test - quick and dirty

					$this->response_error = NULL;

					if ($this->response_code !== 200) {

						if ($this->response_mime == 'application/xml' && preg_match('/<error message="(.*)" \/>/', $this->response_data, $matches)) {
							$this->response_error = 'Error from gateway: ' . html_decode($matches[1]);
						} else {
							$this->response_error = 'Gateway error (' . $this->response_code . ') from "' . $gateway_name . '"';
						}

						if ($this->exit_on_error) {
							exit_with_error($this->response_error, $socket->response_full_get());
						} else {
							$this->response_data = NULL;
							$this->response_mime = NULL;
						}

					}

			}

		//--------------------------------------------------
		// Running of an api (server support)

			public function run($api, $version = 1, $sub_path = NULL) {

				//--------------------------------------------------
				// Config

					ini_set('memory_limit', '1024M');

					config::set('output.gateway', $api);

				//--------------------------------------------------
				// Run API

					$api = new api($api, $version, $sub_path, $this);
					return $api->run_wrapper();

			}

			public function index() {

				//--------------------------------------------------
				// Gateways

					$gateway_urls = $this->get_all();

				//--------------------------------------------------
				// Run setup

					$include_path = APP_ROOT . '/library/setup/setup.php';
					if (is_file($include_path)) {
						script_run_once($include_path);
					}

				//--------------------------------------------------
				// Response

					$html = '
						<h1>Gateways</h1>';

					if (isset($gateway_urls['maintenance'])) {
						$gateway_url = $gateway_urls['maintenance'];
						$html .= '
							<p><a href="' . html($gateway_url) . '">Maintenance</a></p>';
					}

					$html .= '
						<ul>';

					foreach ($gateway_urls as $gateway_name => $gateway_url) {
						if ($gateway_name == 'maintenance') {
							continue;
						}
						$html .= '
								<li><a href="' . html($gateway_url) . '">' . html($gateway_name) . '</a></li>';
					}

					$html .= '
						</ul>';

					$response = response_get('html');
					$response->title_set('Gateways');
					$response->view_add_html($html);
					$response->send();

			}

		//--------------------------------------------------
		// Config

			private function _config_get($type, $key, $default = NULL) {
				if (isset($this->config[$type][$key])) {
					return $this->config[$type][$key];
				} else if ($default !== NULL) {
					return $default;
				} else {
					exit_with_error('Cannot return "' . $key . '" from the "' . $type . '"');
				}
			}

			private function _gateway_config($gateway_name, $detail, $default = NULL) {
				return $this->_config_get('gateways', $gateway_name . '-' . $detail, $default);
			}

			private function _host_config($host, $detail, $default = NULL) {
				return $this->_config_get('hosts', $host . '-' . $detail, $default);
			}

			private function _client_config($client, $detail, $default = NULL) {
				return $this->_config_get('clients', $client . '-' . $detail, $default);
			}

		//--------------------------------------------------
		// Table support

			public function _check_tables() {

				if (config::get('debug.level') > 0) {

					debug_require_db_table(DB_PREFIX . 'gateway_log', '
							CREATE TABLE [TABLE] (
								id int(11) NOT NULL auto_increment,
								gateway tinytext NOT NULL,
								request_url tinytext NOT NULL,
								request_data text NOT NULL,
								request_date datetime NOT NULL,
								response_code tinytext NOT NULL,
								response_mime tinytext NOT NULL,
								response_data text NOT NULL,
								response_date datetime NOT NULL,
								PRIMARY KEY  (id),
								KEY request_date (request_date)
							);');

					debug_require_db_table(DB_PREFIX . 'gateway_pass', '
							CREATE TABLE [TABLE] (
								client varchar(30) NOT NULL,
								pass varchar(32) NOT NULL,
								gateway varchar(30) NOT NULL,
								created datetime NOT NULL,
								used datetime NOT NULL,
								KEY client (client, pass, gateway)
							);');

				}

			}

	}

//--------------------------------------------------
// API Class

	class api_base extends check {

		//--------------------------------------------------
		// Variables

			private $version;
			private $api;
			private $sub_path;
			private $gateway;
			private $mode;
			private $client_ref;

		//--------------------------------------------------
		// Setup

			public function __construct($api, $version = 1, $sub_path = NULL, $gateway = NULL, $mode = 'wrapper') {

				//--------------------------------------------------
				// Clean sub path

					if ($sub_path == '') {
						$sub_path = NULL;
					} else {
						if (substr($sub_path, 0, 1) != '/') {
							$sub_path = '/' . $sub_path;
						}
						if (substr($sub_path, -1) != '/') {
							$sub_path .= '/';
						}
					}

				//--------------------------------------------------
				// Store

					$this->version = $version;
					$this->api = $api;
					$this->sub_path = $sub_path;
					$this->gateway = $gateway;
					$this->mode = $mode;
					$this->client_ref = '';

			}

			protected function sub_path_get() {
				return $this->sub_path;
			}

		//--------------------------------------------------
		// Return values to client

			protected function return_array($data) {
				mime_set('application/x-www-form-urlencoded');
				exit(http_build_query($data));
			}

			protected function return_xml($xml) {
				mime_set('application/xml');
				exit($xml);
			}

			protected function return_error($error) {
				http_response_code(500);
				$this->return_xml('<error message="' . xml($error) . '" />');
			}

		//--------------------------------------------------
		// Errors

			protected function error_fatal($error) {
				if ($error !== NULL) {

					report_add($error, 'error');

					if (REQUEST_MODE == 'cli' || config::get('debug.level') > 0) {
						echo ucfirst($this->api) . ' - Fatal Error:' . "\n";
						echo ' ' . $error . "\n\n";
					}

				}
				exit();
			}

			protected function error_harmless($error) {
				if ($error !== NULL) {

					report_add($error, 'error');

					if (REQUEST_MODE == 'cli' || config::get('debug.level') > 0) {
						echo ucfirst($this->api) . ' - Harmless Error:' . "\n";
						echo ' ' . $error . "\n\n";
					}

				}
				return false;
			}

		//--------------------------------------------------
		// Client support

			protected function client_ref_get() {
				return $this->client_ref;
			}

			protected function client_verify() {

				//--------------------------------------------------
				// Supplied details

					$pass = request('pass');
					$client = request('client');

					$now = new timestamp();

				//--------------------------------------------------
				// Check tables

					if ($this->gateway) {
						$this->gateway->_check_tables();
					}

				//--------------------------------------------------
				// Kill old passwords

					$db = db_get();

					$sql = 'DELETE FROM
								' . DB_PREFIX . 'gateway_pass
							WHERE
								created < ?';

					$parameters = array();
					$parameters[] = array('s', date('Y-m-d H:i:s', strtotime('-3 days')));

					$db->query($sql, $parameters);

				//--------------------------------------------------
				// If no password is supplied

					if ($pass == '') {

						//--------------------------------------------------
						// Check valid client

							if ($client == '') {
								$this->return_error('Client name not specified');
							}

						//--------------------------------------------------
						// Create new key/pass

							if ($this->gateway) {
								$client_key = $this->gateway->_client_config($client, 'key');
							} else {
								exit_with_error('You need to call the API with a gateway object');
							}

							$db_key = random_key(32);

							$db_pass = hash('sha256', ($db_key . $client_key)); // Needs to be a fast hash

						//--------------------------------------------------
						// Store

							$db->insert(DB_PREFIX . 'gateway_pass', array(
									'client'  => $client,
									'pass'    => $db_pass,
									'gateway' => $this->api,
									'created' => $now,
									'used'    => '0000-00-00 00:00:00',
								));

						//--------------------------------------------------
						// Return

							mime_set('text/plain');
							exit($db_key);

					}

				//--------------------------------------------------
				// Test the password

					$sql = 'UPDATE
								' . DB_PREFIX . 'gateway_pass
							SET
								used = ?
							WHERE
								client = ? AND
								pass = ? AND
								gateway = ? AND
								used = "0000-00-00 00:00:00"
							LIMIT
								1';

					$parameters = array();
					$parameters[] = array('s', $now);
					$parameters[] = array('s', $client);
					$parameters[] = array('s', $pass);
					$parameters[] = array('s', $this->api);

					$db->query($sql, $parameters);

					if ($db->affected_rows() != 1) {
						$this->return_error('Invalid Password');
					}

				//--------------------------------------------------
				// Store the details

					$this->client_ref = $client;

			}

		//--------------------------------------------------
		// Data

			protected function data($name) {

				//--------------------------------------------------
				// Get data supplied to gateway... typically XML

					if ($name !== NULL) {

						$data = request($name);

					} else {

						$data = '';

						$fd = fopen('php://input', 'r');
						while (!feof($fd)) {
							$data .= fread($fd, 1024);
						}
						fclose($fd);

					}

				//--------------------------------------------------
				// Return

					return $data;

			}

		//--------------------------------------------------
		// Run

			public function run() {
			}

			public function run_wrapper() {

				//--------------------------------------------------
				// Only works in wrapper mode

					if ($this->mode != 'wrapper') {
						exit_with_error('Cannot call run_wrapper() at this time.');
					}

				//--------------------------------------------------
				// API

					$gateway = $this;

					$api_path = APP_ROOT . '/gateway/v' . intval($this->version) . '/' . safe_file_name($this->api) . '.php';
					$api_object = str_replace('-', '_', $this->api) . '_v' . intval($this->version) . '_api';
					$api_framework = false;

					if ($this->version == 1 && !is_file($api_path)) {
						$api_path = APP_ROOT . '/gateway/' . safe_file_name($this->api) . '.php';
						$api_object = str_replace('-', '_', $this->api) . '_api';
					}

					if (!is_file($api_path)) {
						$api_path = FRAMEWORK_ROOT . '/library/gateway/' . safe_file_name($this->api) . '.php';
						$api_framework = true;
					}

				//--------------------------------------------------
				// Includes

					if (!$api_framework || $this->api == 'maintenance') {
						$include_path = APP_ROOT . '/library/setup/setup.php';
						if (is_file($include_path)) {
							script_run_once($include_path);
						}
					}

					if (is_file($api_path)) {
						require($api_path); // Don't use script_run() jail, as we need access to $this
					} else {
						return false;
					}

				//--------------------------------------------------
				// Object mode support

					if (class_exists($api_object)) {

						$api = new $api_object($this->api, $this->version, $this->sub_path, $this->gateway, 'run');

						$result = $api->run();

					} else {

						$result = true;

					}

				//--------------------------------------------------
				// Success

					return $result;

			}

	}

?>