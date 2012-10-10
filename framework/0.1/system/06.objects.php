<?php

//--------------------------------------------------
// Base objects

	//--------------------------------------------------
	// Main

		class base extends check {

			private $db_link;

			public function db_get() {
				if ($this->db_link === NULL) {
					$this->db_link = new db();
				}
				return $this->db_link;
			}

			public function request_folder_get($id) {
				$folders = config::get('request.folders');
				if (isset($folders[$id])) {
					return $folders[$id];
				} else {
					return NULL;
				}
			}

			public function title_set($title_main) {

				$title_prefix = config::get('output.title_prefix');
				$title_suffix = config::get('output.title_suffix');
				$title_divide = config::get('output.title_divide');

				$title_full = $title_prefix . ($title_prefix != '' && $title_main != '' ? $title_divide : '') . $title_main;
				$title_full = $title_full   . ($title_suffix != '' && $title_main != '' ? $title_divide : '') . $title_suffix;

				$this->title_full_set($title_full);

			}

			public function title_full_set($title) {
				config::set('output.title', $title);
			}

			public function title_folder_set($id, $name) {
				config::array_set('output.title_folders', $id, $name);
			}

			public function title_get() {
				return config::get('output.title');
			}

			public function page_ref_set($page_ref) {
				config::set('output.page_ref', $page_ref);
			}

			public function page_ref_get() {

				$page_ref = config::get('output.page_ref', NULL);

				if ($page_ref === NULL) {

					$page_ref_mode = config::get('output.page_ref_mode', 'route');

					if ($page_ref_mode == 'route') {

						$page_ref = human_to_ref(config::get('route.path'));

					} else if ($page_ref_mode == 'view') {

						$page_ref = human_to_ref(config::get('view.path'));

					} else if ($page_ref_mode == 'request') {

						$page_ref = human_to_ref(urldecode(config::get('request.path')));

					} else {

						exit_with_error('Unrecognised page ref mode "' . $page_ref_mode . '"');

					}

					if ($page_ref == '') {
						$page_ref = 'home';
					}

					$page_ref = 'p_' . $page_ref;

					config::set('output.page_ref', $page_ref);

				}

				return $page_ref;

			}

			public function message_set($message) {
				session::set('message', $message);
			}

			public function view_path_set($view_path) {
				config::set('view.path', $view_path);
			}

			public function set($name, $value) {
				config::array_set('view.variables', $name, $value);
			}

		}

	//--------------------------------------------------
	// Setup

		class setup_base extends base {

			public function run() {

				$include_path = APP_ROOT . '/setup/setup.php';
				if (is_file($include_path)) {
					require_once($include_path);
				}

			}

		}

	//--------------------------------------------------
	// Controller

		class controller_base extends base {

			public $parent;

			public function route() {
			}

			public function before() {
			}

			public function after() {
			}

		}

	//--------------------------------------------------
	// Template

		class template_base extends base {

			private $view_html = '';
			private $view_processed = false;
			private $message = NULL;
			private $debug_output = NULL;

			public function message_get() {
				return $this->message;
			}

			public function message_get_html() {
				if ($this->message == '') {
					return '';
				} else {
					return '
						<div id="page_message">
							<p>' . html($this->message) . '</p>
						</div>';
				}
			}

			public function tracking_get_html() {

				//--------------------------------------------------
				// If allowed

					if (!$this->tracking_allowed_get()) {
						return '';
					}

				//--------------------------------------------------
				// Start

					$html = '';

				//--------------------------------------------------
				// Google analytics

					$google_analytics_code = config::get('tracking.google_analytics.code');
					if ($google_analytics_code !== NULL) {

						$html .= "\n";
						$html .= '	<script type="text/javascript">' . "\n";
						$html .= '	//<![CDATA[' . "\n";
						$html .= "\n";
						$html .= '		var _gaq = _gaq || [];' . "\n";
						$html .= '		_gaq.push(["_setAccount", "' . html($google_analytics_code) . '"]);' . "\n";
						$html .= '		_gaq.push(["_trackPageview"]);' . "\n";
						$html .= "\n";
						$html .= '		(function() {' . "\n";
						$html .= '			var ga = document.createElement("script"); ga.type = "text/javascript"; ga.async = true; ga.src = "https://ssl.google-analytics.com/ga.js"' . "\n";
						$html .= '			var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ga, s);' . "\n";
						$html .= '		})();' . "\n";
						$html .= "\n";
						$html .= '	//]]>' . "\n";
						$html .= '	</script>';

					}

				//--------------------------------------------------
				// Return

					return $html;

			}

			public function tracking_allowed_get() {

				//--------------------------------------------------
				// Do not track header support

					if (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == 1) {

						return false;

					} else if (function_exists('getallheaders')) {

						foreach (getallheaders() as $name => $value) {
							if (strtolower($name) == 'dnt' && $value == 1) {
								return false;
							}
						}

					}

				//--------------------------------------------------
				// If on live server

					return (SERVER == 'live');

			}

			public function css_get($mode) {

				//--------------------------------------------------
				// Main files

					$return = '';

					$css_prefix = config::get('output.css_path_prefix', '');

					foreach (resources::get('css') as $file) { // Cannot use array_unique, as some versions of php do not support multi-dimensional arrays

						if (substr($file['path'], 0, 1) == '/') {
							$file['path'] = $css_prefix . $file['path'];
						}

						if ($mode == 'html') {
							$return .= "\n\t" . '<link rel="stylesheet" type="text/css" href="' . html($file['path']) . '" media="' . html($file['media']) . '" />';
						} else if ($mode == 'xml') {
							$return .= "\n" . '<?xml-stylesheet href="' . xml($file['path']) . '" media="' . xml($file['media']) . '" type="text/css" charset="' . xml(config::get('output.charset')) . '"?>';
						}

					}

				//--------------------------------------------------
				// Alternative files

					$files_alternate = resources::get('css_alternate');
					if (count($files_alternate) > 0) {

						if ($mode == 'html') {
							$return .= "\n\t";
						}

						foreach ($files_alternate as $file) {

							if (substr($file['path'], 0, 1) == '/') {
								$file['path'] = $css_prefix . $file['path'];
							}

							if ($mode == 'html') {
								$return .= "\n\t" . '<link rel="alternate stylesheet" type="text/css" href="' . html($file['path']) . '" media="' . html($file['media']) . '" title="' . html($file['title']) . '" />';
							} else if ($mode == 'xml') {
								$return .= "\n" . '<?xml-stylesheet href="' . html($file['path']) . '" alternate="yes" title="' . html($file['title']) . '" media="' . html($file['media']) . '" type="text/css" charset="' . xml(config::get('output.charset')) . '"?>';
							}

						}

					}

				//--------------------------------------------------
				// Return

					return $return;

			}

			public function view_add_html($html) {
				$this->view_html .= $html;
			}

			public function view_get_html() {
				$this->view_processed = true;
				return $this->view_html;
			}

			public function head_get_html($config = NULL) {

				//--------------------------------------------------
				// Debug

					if (config::get('debug.level') >= 4) {
						debug_progress('Start head', 2);
					}

				//--------------------------------------------------
				// Content type

					$html = "\n\t" . '<meta charset="' . html(config::get('output.charset')) . '" />';

				//--------------------------------------------------
				// Page title

					$html .= "\n\n\t" . '<title>' . html($this->title_get()) . '</title>';

				//--------------------------------------------------
				// Favicon

					$favicon_url = config::get('output.favicon_url');

					if ($favicon_url !== NULL) {
						$html .= "\n\n\t" . '<link rel="shortcut icon" type="image/x-icon" href="' . html($favicon_url) . '" />';
					}

				//--------------------------------------------------
				// Debug

					if (config::get('debug.level') >= 4) {
						debug_progress('Meta, title, and favicon', 3);
					}

				//--------------------------------------------------
				// Browser on black list (no css/js)

					$browser = config::get('request.browser');
					if ($browser != '') {
						foreach (config::get('output.block_browsers') as $browser_reg_exp) {
							if (preg_match($browser_reg_exp, $browser)) {
								return $html;
							}
						}
					}

					if (config::get('debug.level') >= 4) {
						debug_progress('Browser blacklist', 3);
					}

				//--------------------------------------------------
				// CSS

					$css_html = $this->css_get('html');

					if ($css_html !== '') {
						$html .= "\n\t" . $css_html;
					}

					if (config::get('debug.level') >= 4) {
						debug_progress('CSS', 3);
					}

				//--------------------------------------------------
				// Javascript - after CSS

					if (session::get('js_disable') != 'true') {

						$js_prefix = config::get('output.js_path_prefix', '');
						$js_files = array();

						foreach (resources::get('js') as $file) {

							if (substr($file['path'], 0, 1) == '/') {
								$file['path'] = $js_prefix . $file['path'];
							}

							$js_files[$file['path']] = array_merge(array('type' => 'text/javascript', 'src' => $file['path']), $file['attributes']); // Unique path

						}

						if (count($js_files) > 0) {
							$html .= "\n";
							foreach ($js_files as $attributes) {
								$html .= "\n\t" . html_tag('script', $attributes) . '</script>';
							}
						}

					}

					if (config::get('debug.level') >= 4) {
						debug_progress('JavaScript', 3);
					}

				//--------------------------------------------------
				// Extra head HTML

					$html .= resources::head_get_html() . "\n\n";

					if (config::get('debug.level') >= 4) {
						debug_progress('Extra HTML', 3);
					}

				//--------------------------------------------------
				// Return

					return trim($html) . "\n";

			}

			public function render() {

				//--------------------------------------------------
				// Default title

					if (config::get('output.title') === NULL) {

						if (config::get('output.error')) {

							$title_default = config::get('output.title_error');

						} else {

							$k = 0;

							$title_default = '';
							$title_divide = config::get('output.title_divide');

							foreach (config::get('output.title_folders') as $folder) {
								if ($folder != '') {
									if ($k++ > 0) {
										$title_default .= $title_divide;
									}
									$title_default .= $folder;
								}
							}

						}

						$this->title_set($title_default);

					}

				//--------------------------------------------------
				// Message

					$this->message = session::get('message');
					if ($this->message !== NULL) {
						session::delete('message');
					}

				//--------------------------------------------------
				// Headers

					//--------------------------------------------------
					// No-cache headers

						if (config::get('output.no_cache', false)) {
							header('Pragma: no-cache');
							header('Cache-control: private, no-cache, must-revalidate');
							header('Expires: Sat, 01 Jan 2000 01:00:00 GMT');
						}

					//--------------------------------------------------
					// Mime

						$mime_xml = 'application/xhtml+xml';

						if (config::get('output.mime') == $mime_xml && stripos(config::get('request.accept'), $mime_xml) === false) {
							mime_set('text/html');
						} else {
							mime_set();
						}

						unset($mime_xml);

					//--------------------------------------------------
					// Debug

						if (config::get('debug.level') > 0 && config::get('debug.show') === true) {

							$output_mime = config::get('output.mime');

							if ($output_mime == 'text/html' || $output_mime == 'application/xhtml+xml') {

								//--------------------------------------------------
								// Store notes

									$debug_ref = time() . '-' . mt_rand(100000, 999999);

									$session_notes = session::get('debug.notes');
									$session_notes[$debug_ref] = config::get('debug.notes');

									session::set('debug.notes', $session_notes);

								//--------------------------------------------------
								// Resources

									resources::js_add(gateway_url('framework-debug', array('ref' => $debug_ref)), 'async');
									resources::css_add(gateway_url('framework-file', array('file' => 'debug.css')));

							} else if ($output_mime == 'text/plain') {

								//--------------------------------------------------
								// Notes

									$this->debug_output = '';

									foreach (config::get('debug.notes') as $note) {

										$this->debug_output .= '--------------------------------------------------' . "\n\n";
										$this->debug_output .= html_decode(strip_tags($note['html'])) . "\n\n";

										if ($note['time'] !== NULL) {
											$this->debug_output .= 'Time Elapsed: ' . $note['time'] . "\n\n";
										}

									}

									$this->debug_output = str_replace('&#xA0;', ' ', $this->debug_output); // From HTML notes mostly
									$this->debug_output = str_replace('<br />', '', $this->debug_output);
									$this->debug_output = str_replace('<strong>', '', $this->debug_output);
									$this->debug_output = str_replace('</strong>', '', $this->debug_output);

							}

							unset($output_mime, $debug_ref, $session_notes);

						}

					//--------------------------------------------------
					// Content security policy

						$csp = config::get('output.csp_directives');
						if (is_array($csp)) {

							if (!isset($csp['report-uri'])) {
								$csp['report-uri'] = '/a/api/csp-report/';
							}

							$output = array();
							foreach ($csp as $directive => $value) {
								if (is_array($value)) {
									$value = implode(' ', $value);
								}
								$output[] = $directive . ' ' . str_replace('"', "'", $value);
							}

							if (stripos(config::get('request.browser'), 'chrome') !== false) { // Safari 5.1.7 is still widely used, and very buggy (not loading styles)

								$header = 'X-WebKit-CSP';

								if (!config::get('output.csp_active', (SERVER == 'stage'))) {
									$header .= '-Report-Only';
								}

								header($header . ': ' . implode('; ', $output));

							} else {

								// $header = 'Content-Security-Policy';
								// $header = 'X-Content-Security-Policy'; // Firefox does not support 'unsafe-inline' - https://bugzilla.mozilla.org/show_bug.cgi?id=763879#c5

							}

							if (config::get('debug.level') > 0) {

								debug_require_db_table('report_csp', '
										CREATE TABLE [TABLE] (
											blocked_uri varchar(100) NOT NULL,
											violated_directive varchar(100) NOT NULL,
											referrer tinytext NOT NULL,
											document_uri tinytext NOT NULL,
											original_policy text NOT NULL,
											data_raw text NOT NULL,
											ip tinytext NOT NULL,
											browser tinytext NOT NULL,
											created datetime NOT NULL,
											updated datetime NOT NULL,
											PRIMARY KEY (blocked_uri,violated_directive)
										);');

							}

						}

					//--------------------------------------------------
					// Cleanup

						unset($mime_xml, $csp, $output, $header);

				//--------------------------------------------------
				// Local variables

					extract(config::get('view.variables', array()));

				//--------------------------------------------------
				// XML Prolog

					if (config::get('output.mime') == 'application/xml') {
						echo '<?xml version="1.0" encoding="' . html(config::get('output.charset')) . '" ?>';
						echo $this->css_get('xml') . "\n";
					}

				//--------------------------------------------------
				// Include

					require($this->template_path_get());

				//--------------------------------------------------
				// If view_get_html() was not called

					if (!$this->view_processed) {
						echo $this->view_get_html();
					}

				//--------------------------------------------------
				// Debug output

					if ($this->debug_output !== NULL) {

						//--------------------------------------------------
						// Time taken

							$output_text  = "\n\n\n\n\n\n\n\n\n\n";
							$output_text .= '--------------------------------------------------' . "\n\n";

							if (defined('FRAMEWORK_INIT_TIME')) {
								$output_text .= 'Setup time: ' . html(FRAMEWORK_INIT_TIME) . "\n";
							}

							$output_text .= 'Total time: ' . html(debug_run_time()) . "\n";
							$output_text .= 'Query time: ' . html(config::get('debug.db_time')) . "\n\n";

						//--------------------------------------------------
						// Send

							echo $output_text . $this->debug_output;

					}

			}

			private function template_path_get() {

				if (config::get('debug.level') >= 4) {
					debug_progress('Find template', 2);
				}

				$template_path = APP_ROOT . '/templates/' . safe_file_name(config::get('view.template')) . '.ctp';

				if (config::get('debug.level') >= 3) {
					debug_note_html('<strong>Template</strong>: ' . html(str_replace(ROOT, '', $template_path)), 'H');
				}

				if (!is_file($template_path)) {

					$template_path = FRAMEWORK_ROOT . '/library/view/template.ctp';

					resources::css_add(gateway_url('framework-file', array('file' => 'template.css')));

				}

				if (config::get('debug.level') >= 4) {
					debug_progress('Done', 3);
				}

				return $template_path;

			}

		}

	//--------------------------------------------------
	// View

		class view_base extends base {

			protected $template;

			public function __construct($template = NULL) {
				if ($template === NULL) {
					$this->template = new template();
				} else {
					$this->template = $template;
				}
			}

			public function render() {

				ob_start();

				extract(config::get('view.variables', array()));

				require_once($this->view_path_get());

				$this->template->view_add_html(ob_get_clean());
				$this->template->render();

			}

			public function add_html($html) {
				$this->template->view_add_html($html);
			}

			public function render_html($html) {
				$this->template->view_add_html($html);
				$this->template->render();
			}

			public function render_error($error) {

				config::set('route.path', '/error/' . safe_file_name($error) . '/');
				config::set('view.path', VIEW_ROOT . '/error/' . safe_file_name($error) . '.ctp'); // Will be replaced, but set so its shown on default error page.
				config::set('output.error', $error);
				config::set('output.page_ref', 'error-' . $error);

				$this->render();

			}

			private function view_path_get() {

				//--------------------------------------------------
				// Default

					$view_path_default = VIEW_ROOT . '/' . implode('/', config::get('view.folders', array('home'))) . '.ctp';

					config::set_default('view.path', $view_path_default);

					config::set('view.path_default', $view_path_default);

				//--------------------------------------------------
				// Get

					$view_path = config::get('view.path');

					if (config::get('debug.level') >= 3) {
						debug_note_html('<strong>View</strong>: ' . html(str_replace(ROOT, '', $view_path)), 'H');
					}

				//--------------------------------------------------
				// Page not found

					$error = config::get('output.error');

					if (is_string($error) || !is_file($view_path)) {

						if ($error === false || $error === NULL) {
							$error = 'page-not-found';
						}

						if (!headers_sent()) {
							if ($error == 'page-not-found') {
								http_response_code(404);
							} else if ($error == 'system') {
								http_response_code(500);
							}
						}

						if ($error == 'page-not-found') {
							error_log('File does not exist: ' . config::get('request.uri'), 4);
						}

						$view_path = VIEW_ROOT . '/error/' . safe_file_name($error) . '.ctp';
						if (!is_file($view_path)) {
							$view_path = FRAMEWORK_ROOT . '/library/view/error-' . safe_file_name($error) . '.ctp';
						}
						if (!is_file($view_path)) {
							$view_path = FRAMEWORK_ROOT . '/library/view/error-page-not-found.ctp';
						}

					}

				//--------------------------------------------------
				// Return

					return $view_path;

			}

		}

//--------------------------------------------------
// Get website customised objects

	//--------------------------------------------------
	// Include

		$include_path = APP_ROOT . '/setup/objects.php';
		if (is_file($include_path)) {
			require_once($include_path);
		}

	//--------------------------------------------------
	// Defaults if not provided

		if (!class_exists('setup')) {
			class setup extends setup_base {
			}
		}

		if (!class_exists('controller')) {
			class controller extends controller_base {
			}
		}

		if (!class_exists('template')) {
			class template extends template_base {
			}
		}

		if (!class_exists('view')) {
			class view extends view_base {
			}
		}

?>