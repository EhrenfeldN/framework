<?php

	function class_autoload($class_name) {

		//--------------------------------------------------
		// Non overrides

			if (in_array($class_name, array('controller', 'view', 'template'))) {
				return false;
			}

		//--------------------------------------------------
		// Paths

			if (prefix_match('controller_', $class_name)) {

				$base_mode = true;

				$class_file_name = str_replace('_', '-', substr($class_name, 11));

				$paths = array(
						APP_ROOT . '/library/controller/' . $class_file_name . '.php',
						FRAMEWORK_ROOT . '/library/controller/' . $class_file_name . '.php',
					);

			} else {

				$base_mode = (substr($class_name, -5) == '_base');

				if ($base_mode) {
					$class_file_name = str_replace('_', '-', substr($class_name, 0, -5)); // Drop base suffix - no file name should use it
				} else {
					$class_file_name = str_replace('_', '-', $class_name);
				}

				$class_file_name = strtolower($class_file_name);

				if (($pos = strpos($class_file_name, '\\')) !== false) {
					$folder = substr($class_file_name, 0, $pos);
					$class_file_name = substr($class_file_name, ($pos + 1));
				} else if (($pos = strpos($class_file_name, '-')) !== false) {
					$folder = substr($class_file_name, 0, $pos);
				} else {
					$folder = $class_file_name;
				}

				$paths = array();

				if (!$base_mode) {
					$paths[] = APP_ROOT . '/library/class/' . $class_file_name . '.php';
					$paths[] = APP_ROOT . '/library/class/' . $folder . '/' . $class_file_name . '.php';
					$paths[] = APP_ROOT . '/library/vendors/' . $folder . '/' . $class_file_name . '.php';
				}

				$paths[] = FRAMEWORK_ROOT . '/library/class/' . $class_file_name . '.php';
				$paths[] = FRAMEWORK_ROOT . '/library/class/' . $folder . '/' . $class_file_name . '.php';
				$paths[] = FRAMEWORK_ROOT . '/vendors/' . $folder . '/' . $class_file_name . '.php';

			}

		//--------------------------------------------------
		// Run

			foreach ($paths as $path) {
				if (is_file($path)) {

					require_once($path);

					if (class_exists($class_name)) {
						return true;
					}

				}
			}

		//--------------------------------------------------
		// Base support

			if (!$base_mode && !class_exists($class_name) && class_exists($class_name . '_base')) {
				class_alias($class_name . '_base', $class_name); // Since 5.3.0
				return true;
			}

		//--------------------------------------------------
		// Error

			if (config::get('debug.level') > 0) {

				$note_html = '<strong>Autoload</strong> ' . html($class_name) . ':<br />';

				foreach ($paths as $path) {
					$note_html .= '&#xA0; ' . html($path) . '<br />';
				}

				debug_note_html($note_html, 'H');

			}

	}

	spl_autoload_register('class_autoload');

?>