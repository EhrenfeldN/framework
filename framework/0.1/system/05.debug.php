<?php

//--------------------------------------------------
// Setup

	//--------------------------------------------------
	// Notes

		config::set('debug.notes', array());

	//--------------------------------------------------
	// Start time

		$start_time = explode(' ', microtime());
		$start_time = ((float)$start_time[0] + (float)$start_time[1]);

		config::set('debug.start_time', $start_time);

		unset($start_time);

	//--------------------------------------------------
	// Query time

		config::set('debug.query_time', 0);

//--------------------------------------------------
// Quick debug print of a variable

	function debug($variable) {

		$called_from = debug_backtrace();
		echo '<strong>' . substr(str_replace(ROOT, '', $called_from[0]['file']), 1) . '</strong>';
		echo ' (line <strong>' . $called_from[0]['line'] . '</strong>)';

		echo '<pre>';
		echo print_r($variable, true); // view:add_debug() if were not in a view.
		echo '</pre>';

	}

//--------------------------------------------------
// Debug notes

	function debug_note_add($note) {
		debug_note_add_html(nl2br(str_replace(' ', '&nbsp;', html($note))));
	}

	function debug_note_add_html($note_html) {

		//--------------------------------------------------
		// Suppression

			if (!config::get('debug.run')) {
				return;
			}

		//--------------------------------------------------
		// Called from

			$called_from = debug_backtrace();

			if (substr($called_from[0]['file'], -30) == 'framework/0.1/system/debug.php') {
				$called_from_id = 1;
			} else {
				$called_from_id = 0;
			}

			$call_from_file = $called_from[$called_from_id]['file'];
			$call_from_line = $called_from[$called_from_id]['line'];

			$system_call = (substr($call_from_file, 0, strlen(ROOT_FRAMEWORK)) == ROOT_FRAMEWORK);

		//--------------------------------------------------
		// Time position

			if (!$system_call) {

				$note_html = '&nbsp; ' . str_replace("\n", "\n&nbsp; ", $note_html);
				$note_html = '<strong>' . str_replace(ROOT, '', $call_from_file) . '</strong> (line ' . $call_from_line . '):<br />' . $note_html;

				$time_end = explode(' ', microtime());
				$time_end = ((float)$time_end[0] + (float)$time_end[1]);

				$time = round(($time_end - config::get('debug.start_time')), 4);

			} else {

				$note_html = str_replace(ROOT_APP, '/app', $note_html);
				$note_html = str_replace(ROOT_FILE, '/file', $note_html);
				$note_html = str_replace(ROOT_PUBLIC, '/public', $note_html);
				$note_html = str_replace(ROOT_VENDOR, '/vendor', $note_html);
				$note_html = str_replace(ROOT_FRAMEWORK, '/framework', $note_html);

				$time = NULL;

			}

		//--------------------------------------------------
		// Note

			config::array_push('debug.notes', array(
					'html' => $note_html,
					'time' => $time,
				));

	}

//--------------------------------------------------
// Error reporting

	function exit_with_error($message, $hidden_info = NULL) {
		exit($message);
	}

	function add_report($message, $type = 'notice') {

		//--------------------------------------------------
		// Email

			$email = new email();
			$email->send(config::get('email.error'));

	}

//--------------------------------------------------
// Show configuration

	function debug_show_config($prefix = '') {

		$config = config::get_all($prefix);

		ksort($config);

		$config_html = array($prefix == '' ? 'Configuration:' : ucfirst($prefix) . ' configuration:');
		foreach ($config as $key => $value) {
			if (in_array($key, array('db.pass', 'debug.notes', 'view.variables', 'output.html', 'output.css_types', 'output.head_html'))) {
				$value_html = '???';
			} else {
				$value_html = html(print_r($value, true));
			}
			$config_html[] = '&nbsp; <strong>' . html(($prefix == '' ? '' : $prefix . '.') . $key) . '</strong>: ' . $value_html;
		}

		debug_note_add_html(implode($config_html, '<br />' . "\n"));

	}

//--------------------------------------------------
// Print variables

	function debug_show_array($array, $label = 'Array') {

		$variables_html = array(html($label . ':'));
		foreach ($array as $key => $value) {
			if (substr($key, 0, 1) != '_' && substr($key, 0, 5) != 'HTTP_' && !in_array($key, array('GLOBALS'))) {
				$variables_html[] = '&nbsp; <strong>' . html($key) . '</strong>: ' . html(print_r($value, true));
			}
		}

		debug_note_add_html(implode($variables_html, '<br />' . "\n"));

	}

//--------------------------------------------------
// Stage debugging

	if (config::get('debug.run')) {

		//--------------------------------------------------
		// Config

			debug_show_config();

	}

//--------------------------------------------------
// Debug shutdown

	function debug_shutdown($buffer) {

		//--------------------------------------------------
		// Suppression

			if (!config::get('debug.run') || !config::get('debug.show')) {
				return $buffer;
			}

		//--------------------------------------------------
		// Default CSS

			$css_text = 'font-size: 12px; font-family: verdana; font-weight: normal; text-align: left; text-decoration: none;';
			$css_block = 'margin: 5px 0; padding: 5px; background: #FFF; color: #000; border: 1px solid #000; clear: both;';
			$css_para = 'text-align: left; padding: 0; margin: 0; ' . $css_text;

		//--------------------------------------------------
		// Time taken

			$time_end = explode(' ', microtime());
			$time_end = ((float)$time_end[0] + (float)$time_end[1]);

			$time = round(($time_end - config::get('debug.start_time')), 4);

			$output_html = '
				<div style="' . html($css_block) . '">
					<p style="' . html($css_para) . '">Elapsed time: ' . html($time) . '</p>
					<p style="' . html($css_para) . '">Query time: ' . html(config::get('debug.query_time')) . '</p>
				</div>';

		//--------------------------------------------------
		// Notes

			$notes = config::get('debug.notes');

			foreach ($notes as $note) {
				$output_html .= '
					<div style="' . html($css_block) . '">
						<p style="' . html($css_para) . '">' . $note['html'] . '</p>';
				if ($note['time'] !== NULL) {
					$output_html .= '
						<p style="' . html($css_para) . '">Time Elapsed: ' . html($note['time']) . '</p>';
				}
				$output_html .= '
					</div>';
			}

		//--------------------------------------------------
		// Wrapper

			$output_html = "\n\n<!-- START OF DEBUG -->\n\n" . '
				<div style="margin: 10px 5px 0 5px; padding: 0; clear: both;">
					<p style="' . html($css_para) . '"><a href="#" style="color: #AAA; ' . html($css_text) . '" onclick="document.getElementById(\'debug_output\').style.display = (document.getElementById(\'debug_output\').style.display == \'block\' ? \'none\' : \'block\'); return false;">+</a></p>
					<div style="display: block;" id="debug_output">
						' . $output_html . '
					</div>
				</div>' . "\n\n<!-- END OF DEBUG -->\n\n";

		//--------------------------------------------------
		// Add

			$pos = strpos(strtolower($buffer), '</body>');
			if ($pos !== false) {

		 		return substr($buffer, 0, $pos) . $output_html . substr($buffer, $pos);

			} else {

				if (config::get('output.mime') == 'application/xhtml+xml') {
					config::set('output.mime', 'text/html');
				}

		 		return $buffer . $output_html;

			}

	}

?>