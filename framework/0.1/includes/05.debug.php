<?php

//--------------------------------------------------
// Setup

	//--------------------------------------------------
	// Setup

		config::set_default('debug.values', array());
		config::set('debug.notes', array());

	//--------------------------------------------------
	// DB variables

		config::set('debug.time_query', 0);
		config::set('debug.time_check', 0);

//--------------------------------------------------
// Error reporting

	function report_add($message, $type = 'notice') {

		//--------------------------------------------------
		// Send an email to the admin, if necessary

			$error_email = config::get('email.error');

			if (($type == 'error' || $type == 'notice') && $error_email !== NULL) {

				$email_values = config::get('debug.values', array());
				$email_values = array_merge($email_values, array('Message' => $message));

				$email = new email();
				$email->subject_set('System ' . ucfirst($type) . ': ' . config::get('output.domain'));
				$email->request_table_add($email_values);

				$email->send($error_email);

			}

		//--------------------------------------------------
		// Add report to the database

			if (config::get('db.host') !== NULL) {

				$db = new db();

				$db->insert(DB_PREFIX . 'report', array(
						'type'     => $type,
						'created'  => date('Y-m-d H:i:s'),
						'message'  => $message,
						'request'  => config::get('request.url'),
						'referrer' => config::get('request.referrer'),
						'ip'       => config::get('request.ip'),
					));

			}

	}

	function exit_with_error($message, $hidden_info = NULL) {

		//--------------------------------------------------
		// Called from

			foreach (debug_backtrace() as $called_from) {
				if (isset($called_from['file']) && !prefix_match(FRAMEWORK_ROOT, $called_from['file'])) {

					if ($hidden_info === NULL) {
						$hidden_info = '';
					} else {
						$hidden_info .= "\n\n";
					}

					$hidden_info .= $called_from['file'] . ' (line ' . $called_from['line'] . ')';

					break;

				}
			}

		//--------------------------------------------------
		// Report the error

			$error_report = $message;

			if ($hidden_info !== NULL) {
				$error_report .= "\n\n--------------------------------------------------\n\n";
				$error_report .= $hidden_info;
			}

			report_add($error_report, 'error');

		//--------------------------------------------------
		// Return the primary contacts email address.

			$contact_email = config::get('email.error');

			if (is_array($contact_email)) {
				$contact_email = reset($contact_email);
			}

		//--------------------------------------------------
		// Tell the user

			if ($contact_email != '') {
				$hidden_info = NULL; // If there is an email address, don't show the hidden info (e.g. on live).
			}

			if (php_sapi_name() == 'cli' || config::get('output.mime') == 'text/plain') {

				echo "\n" . '--------------------------------------------------' . "\n\n";
				echo 'System Error:' . "\n\n";
				echo $message . "\n\n";

				if ($hidden_info !== NULL) {
					echo $hidden_info . "\n\n";
				}

				echo '--------------------------------------------------' . "\n\n";

			} else {

				if (!headers_sent()) {
					http_response_code(500);
					mime_set('text/html');
				}

				if (class_exists('view') && class_exists('template') && config::get('output.error') !== 'system') { // Avoid looping

					config::array_set('view.variables', 'message', $message);
					config::array_set('view.variables', 'hidden_info', $hidden_info);
					config::array_set('view.variables', 'contact_email', $contact_email);

					render_error('system');

				} else {

					echo '<!DOCTYPE html>
						<html lang="' . html(config::get('output.lang')) . '" xml:lang="' . html(config::get('output.lang')) . '" xmlns="http://www.w3.org/1999/xhtml">
						<head>
							<meta charset="' . html(config::get('output.charset')) . '" />
							<title>System Error</title>
						</head>
						<body id="p_error">
							<h1>System Error</h1>
							<p>' . html($message) . '</p>';

					if ($hidden_info !== NULL) {
						echo '
							<hr />
							<p>' . nl2br(html($hidden_info)) . '</p>';
					}

					echo '
						</body>
						</html>';

				}

			}

		//--------------------------------------------------
		// Exit script execution

			exit();

	}

//--------------------------------------------------
// Error handler

	function error_handler($err_no, $err_str, $err_file, $err_line, $err_context) {

		if (error_reporting() == 0) {
			return; // If disabled, as much granularity as I want to check for
		}

		$hidden_info = '';

		foreach (debug_backtrace() as $called_from) {
			if (isset($called_from['file']) && !prefix_match(FRAMEWORK_ROOT, $called_from['file'])) {

				$hidden_info .= "\n " . $err_file . ':' . $err_line;

				$err_line = $called_from['line'];
				$err_file = $called_from['file'];

				if (isset($called_from['function']) && $called_from['function'] == 'html') {

					// Show value for multibyte error in the html() function.
					//   ini_set('display_errors', false);
					//   html('Testing: ' . chr(254));

					$err_str .= ' (' . $called_from['args'][0] . ')';

				}

				break;

			}
		}

		switch ($err_no) { // From "Johan 'Josso' Jensen" on http://www.php.net/set_error_handler
			case E_NOTICE:
			case E_USER_NOTICE:
				$error_type = 'Notice';
			break;
			case E_WARNING:
			case E_USER_WARNING:
				$error_type = 'Warning';
			break;
			case E_ERROR:
			case E_USER_ERROR:
				$error_type = 'Fatal Error';
			break;
			default:
				$error_type = 'Unknown';
			break;
		}

		if (ini_get('display_errors')) {
			echo "\n<br />\n<b>" . error_handler_html($error_type) . '</b>: ' . error_handler_html($err_str) . ' in <b>' . error_handler_html($err_file) . '</b> on line <b>' . error_handler_html($err_line) . '</b>';
			if ($hidden_info != '') {
				echo "\n<!--" . error_handler_html($hidden_info) . "\n-->\n";
			}
			echo "<br /><br />\n";
		}

		if (ini_get('log_errors')) {
			error_log(sprintf('PHP %s: %s in %s on line %d', $error_type, $err_str, $err_file, $err_line));
		}

		return true;

	}

	function error_handler_html($text) { // Ignores bad characters
		return htmlspecialchars($text, ENT_QUOTES + ENT_IGNORE, config::get('output.charset'));
	}

	set_error_handler('error_handler');

//--------------------------------------------------
// Quick debug print of a variable

	function debug($variable = NULL) {

		$called_from = debug_backtrace();
		$called_from_path = substr(str_replace(ROOT, '', $called_from[0]['file']), 1);
		$called_from_line = $called_from[0]['line'];

		//$output = print_r($variable, true);
		$output = debug_dump($variable); // Shows false (not an empty string), quotes strings (var_export does - but has problems with recursion), and shows a simple string representation for the url object.

		if (php_sapi_name() == 'cli' || config::get('output.mime') == 'text/plain') {
			echo "\n" . $called_from_path . ' (line ' . html($called_from_line) . ')' . "\n";
			echo $output . "\n";
		} else {
			echo "\n" . '<strong>' . html($called_from_path) . '</strong> (line <strong>' . html($called_from_line) . '</strong>)' . "\n";
			echo '<pre>' . html($output) . '</pre>';
		}

	}

//--------------------------------------------------
// Dump a variable

	function debug_dump($variable, $level = 0) {
		switch ($type = gettype($variable)) {
			case 'NULL':
				return 'NULL';
			case 'boolean':
				return ($variable ? 'true' : 'false');
			case 'integer':
			case 'double':
			case 'float':
				return $variable;
			case 'string':
				return '"' . $variable . '"';
			case 'array':
				$indent = str_repeat('        ', $level);
				$output = '(' . "\n";
				foreach ($variable as $key => $value) {
					$output .= $indent . '    [' . debug_dump($key) . '] => ' . debug_dump($value, ($level + 1)) . "\n";
				}
				return $output . $indent . ')';
			case 'object':
				if (method_exists($variable, '_debug_dump')) {
					return $variable->_debug_dump();
				}
			default:
				return print_r($variable, true);
		}
	}

//--------------------------------------------------
// Debug run time

	function debug_time_elapsed() {
		return round((microtime(true) - FRAMEWORK_START), 4);
	}

	function debug_time_format($time) {
		return ($time == 0 ? '0.0000' : str_pad($time, 6, '0'));
	}

//--------------------------------------------------
// Config

	function debug_config_html($prefix = '') { // Used in CLI, so don't check on debug.level

		$config = config::get_all($prefix);

		ksort($config);

		$config_html  = html($prefix == '' ? 'Configuration:' : ucfirst($prefix) . ' configuration:');
		$config_html .= '<div class="debug_keys">';

		foreach ($config as $key => $value) {
			if (!in_array($key, array('db.link', 'debug.time_init', 'debug.time_check', 'debug.time_query'))) {
				if (in_array($key, array('db.pass', 'debug.notes', 'view.variables'))) {
					$value_html = '???';
				} else {
					$value_html = html(debug_dump($value, 1));
				}
				$config_html .= "\n" . '  <p><strong>' . html(($prefix == '' ? '' : $prefix . '.') . $key) . '</strong>: ' . $value_html . '</p>';
			}
		}

		return $config_html . '</div>';

	}

	function debug_constants_html() {

		$constants = get_defined_constants(true);

		if (!isset($constants['user'])) {
			return;
		}

		ksort($constants['user']);

		$constants_html  = 'Constants:';
		$constants_html .= '<div class="debug_keys">';

		foreach ($constants['user'] as $key => $value) {
			if ($key == 'ENCRYPTION_KEY') {
				$value_html = '???';
			} else {
				$value_html = html(debug_dump($value));
			}
			$constants_html .= "\n" . '  <p><strong>' . html($key) . '</strong>: ' . $value_html . '</p>';
		}

		return $constants_html . '</div>';

	}

//--------------------------------------------------
// Debug mode

	if (config::get('debug.level') > 0) {

		//--------------------------------------------------
		// Debug progress

			function debug_progress($label) {

				$time = debug_time_format(debug_time_elapsed() - config::get('debug.time_check'));

				config::array_push('debug.notes', array(
						'type' => 'L',
						'colour' => '#CCC',
						'time' => NULL,
						'html' => html($time . ' - ' . $label),
					));

			}

		//--------------------------------------------------
		// Debug notes

			function debug_note($note, $type = NULL, $colour = NULL) {
				debug_note_html(html(is_string($note) ? $note : debug_dump($note)), $type, $colour);
			}

			function debug_note_html($note_html, $type = NULL, $colour = NULL) {

				//--------------------------------------------------
				// Called from

					foreach (debug_backtrace() as $called_from) {
						if (isset($called_from['file']) && $called_from['file'] != __FILE__) {
							break;
						}
					}

					$call_from_file = $called_from['file'];
					$call_from_line = $called_from['line'];

					$system_call = prefix_match(FRAMEWORK_ROOT, $call_from_file);

				//--------------------------------------------------
				// Time position

					if (!$system_call) {

						$note_html = '&#xA0; ' . str_replace("\n", "\n&#xA0; ", $note_html);
						$note_html = '<strong>' . str_replace(ROOT, '', $call_from_file) . '</strong> (line ' . $call_from_line . '):<br />' . $note_html;

						$time = debug_time_format(debug_time_elapsed() - config::get('debug.time_check'));

					} else {

						if (SERVER == 'live') {
							$note_html = str_replace(ROOT, '[HIDDEN]', $note_html);
						}

						$time = NULL;

					}

				//--------------------------------------------------
				// Note

					if ($type == NULL) {
						$type = 'L';
					}

					if ($colour !== NULL) {
						if (substr($colour, 0, 1) != '#') {
							$colour = '#' . $colour;
						}
					} else {
						$colour = ($system_call ? '#CCC' : '#FFC');
					}

					config::array_push('debug.notes', array(
							'type' => $type,
							'colour' => $colour,
							'time' => $time,
							'html' => $note_html,
						));

			}

		//--------------------------------------------------
		// Show configuration

			if (config::get('debug.level') >= 3) {
				debug_note_html(debug_config_html(), 'C');
				debug_note_html(debug_constants_html(), 'C');
			}

		//--------------------------------------------------
		// Database debug

			function debug_require_db_table($table, $sql) {

				$db = new db();

				$db->query('SHOW TABLES LIKE "' . $db->escape($table) . '"', false); // No debug
				if ($db->num_rows() == 0) {
					http_response_code(500);
					mime_set('text/html');
					exit('Missing table <strong>' . html($table) . '</strong>:<br /><br />' . nl2br(html(trim(str_replace('[TABLE]', $table, $sql)))));
				}

			}

			function debug_database($db, $query) {

				//--------------------------------------------------
				// Skip if disabled debugging

					if (config::get('debug.db') !== true) {
						return mysql_query($query, $db->link_get());
					}

				//--------------------------------------------------
				// Full time

					$time_init = microtime(true);

				//--------------------------------------------------
				// HTML Format for the query

					$query_html = html($query);
					if (strpos($query_html, "\n") !== false) {

						$query_prefix_string = '';
						$query_prefix_length = 0;
						foreach (explode("\n", $query_html) as $line) {
							if (preg_match('/^\t+/', $line, $matches)) {
								$prefix_length = strlen($matches[0]);
								if ($query_prefix_length == 0 || $prefix_length < $query_prefix_length) {
									$query_prefix_length = $prefix_length;
									$query_prefix_string = $matches[0];
								}
							}
						}

						if ($query_prefix_length > 0) {
							$query_html = preg_replace('/^'. preg_quote($query_prefix_string, '/') . '/m', '', $query_html);
						}

					}

					$query_html = trim(preg_replace('/^[ \t]*(?! |\t|SELECT|UPDATE|DELETE|INSERT|SHOW|FROM|LEFT|SET|WHERE|GROUP|ORDER|LIMIT)/m', '    ', $query_html));

				//--------------------------------------------------
				// Called from

					foreach (debug_backtrace() as $called_from) {
						if (isset($called_from['file']) && !prefix_match(FRAMEWORK_ROOT, $called_from['file'])) {
							break;
						}
					}

				//--------------------------------------------------
				// Explain how the query is executed

					$explain_html = '';

					if (preg_match('/^\W*\(?\W*SELECT/i', $query)) {

						$explain_html .= '
							<table>';

						$headers_printed = false;

						$rst = @mysql_query('EXPLAIN ' . $query, $db->link_get());
						if ($rst) {
							while ($row = mysql_fetch_assoc($rst)) {

								if ($headers_printed == false) {
									$headers_printed = true;
									$explain_html .= '
										<tr>';
									foreach ($row as $key => $value) {
										$explain_html .= '
											<th>' . html($key) . '</th>';
									}
									$explain_html .= '
										</tr>';
								}

								$explain_html .= '
									<tr>';
								foreach ($row as $key => $value) {
									$explain_html .= '
										<td>' . ($key == 'type' ? '<a href="http://dev.mysql.com/doc/refman/5.0/en/explain.html#id2772158">' : '') . ($value == '' ? '&#xA0;' : html($value)) . ($key == 'type' ? '</a>' : '') . '</td>';
								}
								$explain_html .= '
									</tr>';

							}
						}

						$explain_html .= '
							</table>';

					}

				//--------------------------------------------------
				// Get all the table references, and if any of them
				// have a "deleted" column, make sure that it's
				// being used

					$text_html = '';

					if (preg_match('/^(SELECT|UPDATE|DELETE)/i', ltrim($query))) {

						$tables = array();

						if (preg_match('/WHERE(.*)/ims', $query, $matches)) {
							$where_sql = $matches[1];
							$where_sql = preg_replace('/ORDER BY.*/ms', '', $where_sql);
							$where_sql = preg_replace('/LIMIT\W+[0-9].*/ms', '', $where_sql);
						} else {
							$where_sql = '';
						}

						if (DB_PREFIX != '') {

							preg_match_all('/\b(' . preg_quote(DB_PREFIX, '/') .'[a-z0-9_]+)( AS ([a-z0-9]+))?/', $query, $matches, PREG_SET_ORDER);

						} else {

							$matches = array();

							preg_match_all('/(UPDATE|FROM)([^\(]*?)(WHERE|GROUP BY|HAVING|ORDER BY|LIMIT|$)/isD', $query, $from_matches, PREG_SET_ORDER);

							foreach ($from_matches as $match) {
								foreach (preg_split('/(,|(NATURAL\s+)?(LEFT|RIGHT|INNER|CROSS)\s+(OUTER\s+)?JOIN)/', $match[2]) as $table) {

									if (preg_match('/([a-z0-9_]+)( AS ([a-z0-9]+))?/', $table, $ref)) {
										$matches[] = $ref;
									}

								}
							}

						}

						foreach ($matches as $table) {

							$found = array();

							foreach (config::get('debug.db_required_fields') as $required_field) {

								$rst = @mysql_query('SHOW COLUMNS FROM ' . $table[1] . ' LIKE "' . $required_field . '"', $db->link_get());
								if ($rst && $row = mysql_fetch_assoc($rst)) {

									//--------------------------------------------------
									// Found

										$found[] = $required_field;

									//--------------------------------------------------
									// Table name

										$required_clause = (isset($table[3]) ? '`' . $table[3] . '`.' : '') . '`' . $required_field . '`';

									//--------------------------------------------------
									// Test

										$sql_conditions = array($where_sql);

										if (preg_match('/' . preg_quote($table[1], '/') . (isset($table[3]) ? ' +AS +' . preg_quote($table[3], '/') . '' : '') . ' +ON +(.*?)(LEFT|RIGHT|INNER|CROSS|WHERE|GROUP BY|HAVING|ORDER BY|LIMIT)/ms', $query, $on_details)) {
											$sql_conditions[] = $on_details[1];
										}

										$valid = false;
										foreach ($sql_conditions as $sql_condition) {
											if (preg_match('/' . str_replace('`', '(`|\b)', preg_quote($required_clause, '/')) . ' (IS NULL|=|>|>=|<|<=|!=)/', $sql_condition)) {
												$valid = true;
												break;
											}
										}

									//--------------------------------------------------
									// If missing

										if (!$valid) {

											echo "\n";
											echo '<div>' . "\n";
											echo '	<h1>Error</h1>' . "\n";
											echo '	<p><strong>' . str_replace(ROOT, '', $called_from['file']) . '</strong> (line ' . $called_from['line'] . ')</p>' . "\n";
											echo '	<p>Missing reference to "' . html(str_replace('`', '', $required_clause)) . '" column on the table "' . html($table[1]) . '".</p>' . "\n";
											echo '	<hr />' . "\n";
											echo '	<p><pre>' . "\n\n" . $query_html . "\n\n" . '</pre></p>' . "\n";
											echo '</div>' . "\n";

											exit();

										}

								}

							}

							$tables[] = $table[1] . ': ' . (count($found) > 0 ? implode(', ', $found) : 'N/A');

						}

						if (count($tables) > 0) {

							$text_html .= '
								<ul>';

							foreach ($tables as $table) {
								$text_html .= '
									<li>' . preg_replace('/: (.*)/', ': <strong>$1</strong>', html($table)) . '</li>';
							}

							$text_html .= '
								</ul>';

						}

					}

				//--------------------------------------------------
				// Run query

					$time_start = microtime(true);

					$result = mysql_query($query, $db->link_get()) or $db->_error($query);

					$time_check = round(($time_start - $time_init), 3);
					$time_query = round((microtime(true) - $time_start), 3);

					config::set('debug.time_query', (config::get('debug.time_query') + $time_query));
					config::set('debug.time_check', (config::get('debug.time_check') + $time_check));

				//--------------------------------------------------
				// Create debug output

					$single_line = (strpos($query_html, "\n") === false);

					$html  = '<strong>' . str_replace(ROOT, '', $called_from['file']) . '</strong> (line ' . $called_from['line'] . ')<br />' . "\n";
					$html .= '<pre class="debug_sql">' . ($single_line ? '' : "\n") . $query_html . ($single_line ? '' : "\n\n") . '</pre>';

					config::array_push('debug.notes', array(
							'type' => 'L',
							'colour' => '#CCF',
							'time' => debug_time_format($time_query),
							'html' => $html,
							'extra_html' => $explain_html . $text_html,
						));

				//--------------------------------------------------
				// Return the result

					return $result;

			}

		//--------------------------------------------------
		// End debug

			function debug_shutdown() {

				//--------------------------------------------------
				// Ignore

					if (!config::get('debug.show')) {
						return false;
					}

				//--------------------------------------------------
				// Time text

					$time_check = config::get('debug.time_check');
					$time_total = debug_time_elapsed();

					$time_text  = 'Setup time: ' . debug_time_format(config::get('debug.time_init')) . "\n";
					$time_text .= 'Query time: ' . debug_time_format(config::get('debug.time_query')) . "\n";
					$time_text .= 'Total time: ' . debug_time_format($time_total - $time_check) . ' (with checks ' . debug_time_format($time_total) . ')';

				//--------------------------------------------------
				// Send

					if (config::get('debug.mode') == 'js') {

						config::array_push('debug.notes', array(
								'type' => 'L',
								'colour' => '#FFF',
								'time' => NULL,
								'html' => nl2br(html($time_text)),
							));

						$js_code  = "\n";
						$js_code .= 'var debug_time = ' . json_encode(debug_time_format($time_total - $time_check)) . ';' . "\n";
						$js_code .= 'var debug_notes = ' . json_encode(config::get('debug.notes')) . ';';
						$js_code .= file_get_contents(FRAMEWORK_ROOT . '/library/view/debug.js');

						resources::js_code_add($js_code, 'async');

					} else if (config::get('output.mime') == 'text/plain') {

						$output_text  = "\n\n\n\n\n\n\n\n\n\n";

						foreach (config::get('debug.notes') as $note) {

							$output_text .= '--------------------------------------------------' . "\n\n";
							$output_text .= html_decode(strip_tags($note['html'])) . "\n\n";

							if ($note['time'] !== NULL) {
								$output_text .= 'Time Elapsed:  ' . $note['time'] . "\n\n";
							}

						}

						$output_text .= '--------------------------------------------------' . "\n\n";
						$output_text .= $time_text . "\n\n";
						$output_text .= '--------------------------------------------------' . "\n\n";

						echo $output_text;

					}

			}

			register_shutdown_function('debug_shutdown');

		//--------------------------------------------------
		// Report table exists

			if (config::get('db.host') !== NULL) {

				debug_require_db_table(DB_PREFIX . 'report', '
						CREATE TABLE [TABLE] (
							id int(11) NOT NULL auto_increment,
							type tinytext NOT NULL,
							created datetime NOT NULL,
							message text NOT NULL,
							request tinytext NOT NULL,
							referrer tinytext NOT NULL,
							ip tinytext NOT NULL,
							PRIMARY KEY  (id)
						);');

			}

	}

?>