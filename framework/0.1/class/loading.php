<?php

/***************************************************

	//--------------------------------------------------
	// Example setup

		$loading = new loading();

		$loading = new loading('profile');

		$loading = new loading(array(
				'profile'           => 'profile', // Use 'loading.profile.*' config.
				'time_out'          => (5 * 60),  // Seconds before script will timeout.
				'refresh_frequency' => 2,         // Seconds browser will wait before trying again.
				'refresh_url'       => '/../',    // If you want the user to load a different url while waiting (e.g. add a new parameter).
				'template_name'     => 'loading', // Customised loading page name (in /app/template/), see example below.
				'template_path'     => '/../',    // Customised loading page path.
				'lock_type'         => 'loading', // See lock example below.
				'lock_ref'          => NULL,
			));

	//--------------------------------------------------
	// Example usage

		// $loading->template_test();

		$loading->check(); // Will exit() with loading page if still running, return false if not running, or return the variables if there was a time-out.

		if ($form->submitted()) {
			if ($form->valid()) {

				// $loading->refresh_url_set('/../');

				$loading->start('Starting action'); // String will replace [MESSAGE] in the template, or array for multiple tags.

				sleep(5);

				$loading->update('Updating progress');

				sleep(5);

				// $loading->done();
				$loading->done('/../'); // Specify a URL if you want to redirect to a different url.
				exit();

			}
		}

	//--------------------------------------------------
	// Example with 'lock'

		$loading = new loading(array(
				'lock_type' => 'loading', // Set to use a lock, the name is passed directly to the lock helper.
				'lock_ref'  => NULL,      // Optional lock ref (e.g. pass in an ID if you want to lock a specific item).
			));

		$loading->check();

		if ($loading->start('Starting action')) {

			sleep(5);

			$loading->update('Updating progress');

			sleep(5);

			$loading->done();
			$loading->done('/../'); // Optional URL if you want to redirect users to a new page (e.g. a static thank you page)

		} else {

			// Could not open lock

		}

	//--------------------------------------------------
	// Optional template 'loading'

		/app/public/a/css/global/loading.css

			This file will be used with a template like
			the one below, or you can create your own.

		/app/template/example.ctp

			This file will be used if 'template_name' is
			set to 'example', the contents should be
			something like the following.

			<!DOCTYPE html>
			<html lang="<?= html(config::get('output.lang')) ?>" xml:lang="<?= html(config::get('output.lang')) ?>" xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<meta charset="<?= html(config::get('output.charset')) ?>" />
				<title>Loading</title>
				<link rel="stylesheet" type="text/css" href="<?= html(resources::version_path('/a/css/global/loading.css')) ?>" media="all" />
			</head>
			<body>
				<div id="page_content" role="main">
					<h1>Loading</h1>
					<p>[MESSAGE]... [[TIME_START]]</p>
				</div>
			</body>
			</html>

***************************************************/

	class loading_base extends check {

		//--------------------------------------------------
		// Variables

			private $config = array();
			private $lock = NULL;
			private $started = false;
			private $session_prefix = NULL;

		//--------------------------------------------------
		// Setup

			public function __construct($config = NULL) {
				$this->setup($config);
			}

			protected function setup($config) {

				//--------------------------------------------------
				// Profile

					if (is_string($config)) {
						$profile = $config;
					} else if (isset($config['profile'])) {
						$profile = $config['profile'];
					} else {
						$profile = NULL;
					}

				//--------------------------------------------------
				// Default config

					$default_config = array(
							'time_out' => (5 * 60),
							'refresh_frequency' => 2,
							'refresh_url' => NULL,
							'template_name' => NULL,
							'template_path' => NULL,
							'session_prefix' => NULL,
							'lock_type' => NULL,
							'lock_ref' => NULL,
						);

					$default_config = array_merge($default_config, config::get_all('loading.default'));

				//--------------------------------------------------
				// Set config

					if (!is_array($config)) {
						$config = array();
					}

					if ($profile !== NULL) {
						$config = array_merge(config::get_all('loading.' . $profile), $config);
					}

					$this->config = array_merge($default_config, $config);

				//--------------------------------------------------
				// Session prefix

					$this->session_prefix = 'loading.' . base64_encode(isset($config['ref']) ? $config['ref'] : config::get('request.path')) . '.';

			}

			public function refresh_url_set($url) {
				$this->config['refresh_url'] = $url;
			}

			public function template_test() {
				exit($this->_template_get_html());
			}

		//--------------------------------------------------
		// Process

			public function check() {

				//--------------------------------------------------
				// If still active

					if (session::get($this->session_prefix . 'started') === true) {

						if ($this->config['lock_type'] !== NULL) {

							$lock = new lock($this->config['lock_type'], $this->config['lock_ref']);

							// $lock->check() - Can't really test as the other thread has the lock.

							$time_start  = $lock->data_get('time_start');
							$time_update = $lock->data_get('time_update');
							$variables   = $lock->data_get('variables');

						} else {

							$time_start  = session::get($this->session_prefix . 'time_start');
							$time_update = session::get($this->session_prefix . 'time_update');
							$variables   = session::get($this->session_prefix . 'variables');

						}

						if (($time_start == 0) || (($time_start + $this->config['time_out']) < time())) {
							$this->_cleanup();
							return $variables;
						}

						$this->_send($time_start, $time_update, $variables);

						exit();

					}

				//--------------------------------------------------
				// Has completed, but needs to redirect

					$done_url = session::get($this->session_prefix . 'done_url');
					if ($done_url) {

						session::delete($this->session_prefix . 'done_url');

						redirect($done_url);

					}

				//--------------------------------------------------
				// Not active

					return false;

			}

			public function start($variables) {

				$time = time(); // All the same

				if ($this->config['lock_type'] !== NULL) {

					$lock = new lock($this->config['lock_type'], $this->config['lock_ref']);
					$lock->time_out_set($this->config['time_out']);

					if (!$lock->open()) {
						return false;
					}

					$this->lock = $lock;

					$this->lock->data_set(array(
							'time_start' => $time,
							'time_update' => $time,
							'variables' => $variables,
						));

				} else {

					set_time_limit($this->config['time_out']);

					session::set($this->session_prefix . 'time_start', $time);
					session::set($this->session_prefix . 'time_update', $time);
					session::set($this->session_prefix . 'variables', $variables);

				}

				session::set($this->session_prefix . 'started', true);
				session::set($this->session_prefix . 'done_url', NULL);

				session::close();

				$this->started = true;

				$this->_send($time, $time, $variables);

				return true;

			}

			public function update($variables) {

				if ($this->started) {
					if ($this->lock) {

						if ($this->lock->open()) {

							$this->lock->data_set(array(
									'time_update' => time(),
									'variables' => $variables,
								));

							return true;

						}

					} else {

						session::set($this->session_prefix . 'time_update', time());
						session::set($this->session_prefix . 'variables', $variables);

						session::close(); // So refreshing page can gain lock on session file.

						return true;

					}
				}

				return false;

			}

			public function done($done_url = NULL) {

				if ($this->started) {

					$this->_cleanup();

					if ($this->lock) {
						$this->lock->close();
					}

					if ($done_url !== NULL) {
						session::set($this->session_prefix . 'done_url', $done_url);
					}

					session::close(); // Always close, as cleanup would have re-opened

				} else if ($done_url !== NULL) {

					redirect($done_url);

				}

			}

		//--------------------------------------------------
		// Cleanup

			private function _cleanup() {
				session::delete($this->session_prefix . 'started');
				session::delete($this->session_prefix . 'time_start');
				session::delete($this->session_prefix . 'time_update');
				session::delete($this->session_prefix . 'variables');
				session::delete($this->session_prefix . 'done_url');
			}

		//--------------------------------------------------
		// Send

			private function _template_get_html() {

				//--------------------------------------------------
				// Template path

					if ($this->config['template_path'] !== NULL) {

						$template_path = $this->config['template_path'];

					} else if ($this->config['template_name'] !== NULL) {

						$template_path = APP_ROOT . '/template/' . safe_file_name($this->config['template_name']) . '.ctp';

					} else {

						return NULL;

					}

				//--------------------------------------------------
				// Path error

					if (!is_file($template_path)) {

						$this->_cleanup();

						exit_with_error('Could not return template file.', $template_path);

					}

				//--------------------------------------------------
				// Process

					ob_start();
					require_once($template_path);
					return ob_get_clean();

			}

			private function _send($time_start, $time_update, $variables) {

				//--------------------------------------------------
				// Loading contents

					$contents_html = $this->_template_get_html();

					if ($contents_html === NULL) {
						$contents_html  = '<h1>Loading</h1>' . "\n";
						$contents_html .= '<p>[MESSAGE]... [[TIME_START]]</p>';
					}

				//--------------------------------------------------
				// Variables

					if (is_array($variables)) {

						foreach ($variables as $name => $value) {

							if (strtolower(substr($name, -5)) == '_html') {
								$value_html = $value;
							} else {
								$value_html = html($value);
							}

							$contents_html = str_replace('[' . strtoupper($name) . ']', $value_html, $contents_html);

						}

					} else if (is_string($variables)) {

						$contents_html = str_replace('[MESSAGE]', html($variables), $contents_html);

					}

				//--------------------------------------------------
				// Time

					$time = time();

					$time_diff_start  = ($time - $time_start);
					$time_diff_update = ($time - $time_update);

					$time_diff_start  = str_pad(floor($time_diff_start  / 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad(intval($time_diff_start  % 60), 2, '0', STR_PAD_LEFT);
					$time_diff_update = str_pad(floor($time_diff_update / 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad(intval($time_diff_update % 60), 2, '0', STR_PAD_LEFT);

					$contents_html = str_replace('[TIME_START]', html($time_diff_start), $contents_html);
					$contents_html = str_replace('[TIME_UPDATE]', html($time_diff_update), $contents_html);

				//--------------------------------------------------
				// Refresh URL

					$refresh_url = $this->config['refresh_url'];
					if ($refresh_url === NULL) {
						$refresh_url = url();
					}

					$refresh_header = $this->config['refresh_frequency'] . '; url=' . $refresh_url;

					if (strpos($contents_html, '[URL]') !== false) { // Template contains the Meta Tag, Link, or JavaScript (with alternative).

						$contents_html = str_replace('[URL]', html($refresh_url), $contents_html);

					} else {

						$refresh_html = "\n\t" . '<meta http-equiv="refresh" content="' . html($refresh_header) . '" />' . "\n\n";

						$pos = stripos($contents_html, '</head>');
						if ($pos !== false) {

					 		$contents_html = substr($contents_html, 0, $pos) . $refresh_html . substr($contents_html, $pos);

						} else {

							$css_file = '/css/global/loading.css';
							if (is_file(ASSET_ROOT . $css_file)) {
								$css_html = '<link rel="stylesheet" type="text/css" href="' . html(resources::version_path(ASSET_URL . '/css/global/loading.css')) . '" media="all" />';
							} else {
								$css_html = '';
							}

							$contents_html = '<!DOCTYPE html>
								<html lang="' . html(config::get('output.lang')) . '" xml:lang="' . html(config::get('output.lang')) . '" xmlns="http://www.w3.org/1999/xhtml">
								<head>
									<meta charset="' . html(config::get('output.charset')) . '" />
									<title>Loading</title>
									' . $refresh_html . '
									' . $css_html . '
								</head>
								<body>
									<div id="page_content" role="main">
										' . $contents_html . '
									</div>
								</body>
								</html>';

						}

					}

				//--------------------------------------------------
				// Output, with support for output buffers

					ob_start();

					echo $contents_html;

					$output = '';
					while (ob_get_level() > 0) {
						$output = ob_get_clean() . $output;
					}
					$output = str_pad($output, 1024);

				//--------------------------------------------------
				// Disable mod_gzip or mod_deflate, to end connection

					apache_setenv('no-gzip', 1);

				//--------------------------------------------------
				// Extra

					// if (request('ModPagespeed') != 'off') {
					// 	redirect(url(array('ModPagespeed' => 'off')));
					// }

					// ini_set('zlib.output_compression', 0);
					// ini_set('implicit_flush', 1);

					ignore_user_abort(true);

				//--------------------------------------------------
				// Send output

					header('Refresh: ' . head($refresh_header));

					header('Connection: close');
					header('Content-Length: ' . head(strlen($output)));

					echo $output;

					flush();

			}

	}

?>