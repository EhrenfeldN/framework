<?php

//--------------------------------------------------
// Start time

	define('FRAMEWORK_START', microtime(true));

	function log_shutdown() {
		if (!defined('FRAMEWORK_END')) {
			define('FRAMEWORK_END', number_format(round((microtime(true) - FRAMEWORK_START), 4), 4));
		}
		if (function_exists('apache_note')) {
			apache_note('TIME_INFO', FRAMEWORK_END);
		}
	}

	register_shutdown_function('log_shutdown');

//--------------------------------------------------
// Error reporting

	error_reporting(E_ALL); // Don't you dare change this (instead you should learn to program properly).

//--------------------------------------------------
// Version

	if (defined('FRAMEWORK_VERSION')) {
		if (FRAMEWORK_VERSION != 0.1) {
			exit('Version "' . FRAMEWORK_VERSION . '" has been requested, but version "0.1" has been included.');
		}
	} else {
		define('FRAMEWORK_VERSION', 0.1);
	}

//--------------------------------------------------
// Request mode

	if (!defined('REQUEST_MODE')) {
		define('REQUEST_MODE', 'http');
	}

//--------------------------------------------------
// Default paths

	if (!defined('ROOT')) {
		define('ROOT', dirname(dirname(dirname(__FILE__))));
	}

	if (!defined('APP_ROOT'))        define('APP_ROOT',        ROOT     . '/app');
	if (!defined('VIEW_ROOT'))       define('VIEW_ROOT',       APP_ROOT . '/view');
	if (!defined('PUBLIC_ROOT'))     define('PUBLIC_ROOT',     APP_ROOT . '/public');
	if (!defined('CONTROLLER_ROOT')) define('CONTROLLER_ROOT', APP_ROOT . '/controller');

//--------------------------------------------------
// Framework path

	if (!defined('FRAMEWORK_ROOT')) {
		define('FRAMEWORK_ROOT', dirname(__FILE__));
	}

//--------------------------------------------------
// Includes

	require_once(FRAMEWORK_ROOT . '/includes/01.function.php');
	require_once(FRAMEWORK_ROOT . '/includes/02.config.php');
	require_once(FRAMEWORK_ROOT . '/includes/03.autoload.php');
	require_once(FRAMEWORK_ROOT . '/includes/04.database.php');
	require_once(FRAMEWORK_ROOT . '/includes/05.debug.php');

//--------------------------------------------------
// Initialisation done

	config::set('debug.time_init', debug_time_elapsed());

	if (config::get('debug.level') >= 4) {
		debug_progress('Init');
	}

//--------------------------------------------------
// Process request

	if (!defined('FRAMEWORK_INIT_ONLY') || FRAMEWORK_INIT_ONLY !== true) {

		//--------------------------------------------------
		// Page setup

			try {

				//--------------------------------------------------
				// Buffer to catch output from setup/controller.

					if (SERVER != 'live') {

						$output = ob_get_clean_all();
						if ($output != '') {
							exit('Pre framework output "' . $output . '"');
						}
						unset($output);

					}

					ob_start();

				//--------------------------------------------------
				// Controller and Routes

					require_once(FRAMEWORK_ROOT . '/includes/06.controller.php');
					require_once(FRAMEWORK_ROOT . '/includes/07.routes.php');

				//--------------------------------------------------
				// Include setup

					if (config::get('debug.level') >= 4) {
						debug_progress('Before Setup');
					}

					$include_path = APP_ROOT . '/library/setup/setup.php';
					if (is_file($include_path)) {
						script_run_once($include_path);
					}

				//--------------------------------------------------
				// Process

					if (config::get('debug.level') >= 4) {
						debug_progress('Before Controller');
					}

					require_once(FRAMEWORK_ROOT . '/includes/08.process.php');

				//--------------------------------------------------
				// Units

					if (config::get('debug.level') >= 3) {

						$note_html  = '<strong>Units</strong>:<br />' . "\n";

						$units = config::get('debug.units');

						foreach ($units as $unit) {
							$note_html .= '&#xA0; ' . html($unit) . '<br />' . "\n";
						}
						if (count($units) == 0) {
							$note_html .= '&#xA0; <strong>none</strong>';
						}

						debug_note_html($note_html, 'H');

						unset($note_html, $units, $unit);

					}

				//--------------------------------------------------
				// Response

					$response = response_get();
					$response->setup_output_set(ob_get_clean());
					$response->send();

			} catch (error_exception $e) {

				exit_with_error($e->getMessage(), $e->getHiddenInfo());

			}

		//--------------------------------------------------
		// Cleanup

			unset($include_path, $response);

		//--------------------------------------------------
		// Debug

			if (config::get('debug.level') >= 4) {

				//--------------------------------------------------
				// Local variables

					if (config::get('debug.level') >= 5) {

						$variables_array = get_defined_vars();
						foreach ($variables_array as $key => $value) {
							if (substr($key, 0, 1) == '_' || substr($key, 0, 5) == 'HTTP_' || in_array($key, array('GLOBALS'))) {
								unset($variables_array[$key]);
							}
						}

						$variables_html = array('Variables:');
						foreach ($variables_array as $key => $value) {
							$variables_html[] = '&#xA0; <strong>' . html($key) . '</strong>: ' . html(debug_dump($value));
						}

						debug_note_html(implode($variables_html, '<br />' . "\n"));

						unset($variables_array, $variables_html, $key, $value);

					}

				//--------------------------------------------------
				// Log end

					debug_progress('End');

			}

	}

?>