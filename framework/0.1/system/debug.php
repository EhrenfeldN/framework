<?php

//--------------------------------------------------
// Default config

	config::set_default('debug.run', false); // Check things during processing.
	config::set_default('debug.show', true); // Only relevant when running.
	config::set_default('debug.email', '');

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
		echo '<pre>';
		echo var_export($variable, true); // view:add_debug() if were not in a view.
		echo '</pre>';
	}

//--------------------------------------------------
// Debug notes

	function debug_note_add($note) {
		debug_note_add_html(nl2br(str_replace(' ', '&nbsp;', html($note))));
	}

	function debug_note_add_html($note_html) {

		//--------------------------------------------------
		// Time position

			$time_end = explode(' ', microtime());
			$time_end = ((float)$time_end[0] + (float)$time_end[1]);

			$time = round(($time_end - config::get('debug.start_time')), 3);

		//--------------------------------------------------
		// Note

			config::array_push('debug.notes', array(
					'html' => $note_html,
					'time' => $time,
				));

	}

//--------------------------------------------------
// Stage debugging

	if (config::get('debug.run')) {

		//--------------------------------------------------
		// Config

			$config = config::get_all();

			ksort($config);

			$config_html = 'Configuration:<br />';
			foreach ($config as $key => $value) {
				$config_html .= '
					&nbsp; <strong>' . html($key) . '</strong>: ' . html(var_export($value, true)) . '<br />';
			}
			$config_html .= '<br />';

			debug_note_add_html($config_html);

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
			$css_block = 'margin: 1em 0; padding: 1em; background: #FFF; color: #000; border: 1px solid #000; clear: both;';
			$css_para = 'text-align: left; padding: 0; margin: 0; ' . $css_text;

		//--------------------------------------------------
		// Time taken

			$time_end = explode(' ', microtime());
			$time_end = ((float)$time_end[0] + (float)$time_end[1]);

			$time = round(($time_end - config::get('debug.start_time')), 3);

			$html_output = '
				<div style="' . html($css_block) . '">
					<p style="' . html($css_para) . '">Time Elapsed: ' . html($time) . '</p>
					<p style="' . html($css_para) . '">Query time: ' . html(config::get('debug.query_time')) . '</p>
				</div>';

		//--------------------------------------------------
		// Notes

			$notes = config::get('debug.notes');

			foreach ($notes as $note) {
				$html_output .= '
					<div style="' . html($css_block) . '">
						<p style="' . html($css_para) . '">' . $note['html'] . '</p>
						<p style="' . html($css_para) . '">Time Elapsed: ' . html($note['time']) . '</p>
					</div>';
			}

		//--------------------------------------------------
		// Wrapper

			$html_output = "\n\n<!-- START OF DEBUG -->\n\n" . '
				<div style="margin: 1em 1em 0 1em; padding: 0; clear: both;">
					<p style="' . html($css_para) . '"><a href="#" style="color: #AAA; ' . html($css_text) . '" onclick="document.getElementById(\'htmlDebugOutput\').style.display = (document.getElementById(\'htmlDebugOutput\').style.display == \'block\' ? \'none\' : \'block\'); return false;">+</a></p>
					<div style="display: none;" id="htmlDebugOutput">
						' . $html_output . '
					</div>
				</div>' . "\n\n<!-- END OF DEBUG -->\n\n";

		//--------------------------------------------------
		// Add

			$pos = strpos(strtolower($buffer), '</body>');
			if ($pos !== false) {

		 		return substr($buffer, 0, $pos) . $html_output . substr($buffer, $pos);

			} else {

				if (config::get('output.mime') == 'application/xhtml+xml') {
					config::set('output.mime', 'text/html');
				}

		 		return $buffer . $html_output;

			}

	}

?>