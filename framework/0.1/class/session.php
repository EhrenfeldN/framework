<?php

	config::set('session.id', NULL);
	config::set_default('session.key', sha1(ENCRYPTION_KEY));
	config::set_default('session.name', config::get('cookie.prefix') . 'session');

	class session_base extends check {

		public static function set($variable, $value = NULL) {

			session::start();

			$_SESSION[$variable] = $value;

		}

		public static function get($variable, $default = NULL) {

			session::start();

			if (isset($_SESSION[$variable])) {
				return $_SESSION[$variable];
			} else {
				return $default;
			}

		}

		public static function delete($variable) {

			session::start();

			unset($_SESSION[$variable]);

		}

		public static function reset() {

			session::destroy();
			session::start();
			session_regenerate_id();

		}

		public static function destroy() {

			if (config::get('session.id') !== NULL) {

				session_destroy();

				config::set('session.id', NULL);

			}

		}

		public static function close() {

			if (config::get('session.id') !== NULL) {

				session_write_close();

				config::set('session.id', NULL);

			}

		}

		public static function start() {

			if (config::get('session.id') === NULL) { // Cannot call session_id(), as this is not reset on session_write_close().

				//--------------------------------------------------
				// Start

					session_name(config::get('session.name'));

					ini_set('session.cookie_httponly', true);

					$result = session_start(); // May warn about headers already being sent, which happens in loading object.

				//--------------------------------------------------
				// Store session ID

					config::set('session.id', session_id());

				//--------------------------------------------------
				// Check this session is for this website

					$config_key = config::get('session.key');
					$session_key = session::get('session.key');

					if ($session_key == '') {

						session::set('session.key', $config_key);

					} else if ($config_key != $session_key) {

						session::destroy();

						exit_with_error('Your session is not valid for this website', $config_key . ' != ' . $session_key);

					}

			}

		}

		final private function __construct() {
			// Being private prevents direct creation of object.
		}

		final private function __clone() {
			trigger_error('Clone of config object is not allowed.', E_USER_ERROR);
		}

	}

?>