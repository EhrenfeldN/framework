<?php

//--------------------------------------------------
// http://www.phpprime.com/doc/helpers/table/
//--------------------------------------------------

	class table_base extends check {

		//--------------------------------------------------
		// Variables

			protected $table_id = NULL;
			protected $caption_text = NULL;
			protected $caption_html = NULL;
			protected $headings = array();
			protected $heading_id = 0;
			protected $footers = array();
			protected $footer_id = 0;
			protected $rows = array();

			protected $id_value = '';
			protected $class_name = '';
			protected $current_url = NULL;
			protected $no_records_html = 'No records found';
			protected $data_inherit_heading_class = true;
			protected $footer_inherit_heading_class = true;
			protected $charset_input = NULL;
			protected $charset_output = NULL;

			protected $sort_enabled = false;
			protected $sort_name = NULL;
			protected $sort_request_id = NULL;
			protected $sort_request_order = NULL;
			protected $sort_preserved_key = NULL;
			protected $sort_preserved_id = NULL;
			protected $sort_preserved_order = NULL;
			protected $sort_default_field = NULL;
			protected $sort_default_order = NULL;
			protected $sort_id = 0;
			protected $sort_fields = array();
			protected $sort_active_asc_prefix_html = '';
			protected $sort_active_asc_suffix_html = '&#xA0;<span class="sort asc" title="Ascending">&#9650;</span>';
			protected $sort_active_desc_prefix_html = '';
			protected $sort_active_desc_suffix_html = '&#xA0;<span class="sort desc" title="Descending">&#9660;</span>';
			protected $sort_inactive_prefix_html = '';
			protected $sort_inactive_suffix_html = '&#xA0;<span class="sort inactive" title="Sort">&#9650;</span>';

		//--------------------------------------------------
		// Setup

			public function __construct() {
				$this->setup();
			}

			protected function setup() {

				//--------------------------------------------------
				// Defaults

					$this->charset_input = config::get('output.charset');

				//--------------------------------------------------
				// Table ID

					$this->table_id = config::get('table.count', 1);

					config::set('table.count', ($this->table_id + 1));

				//--------------------------------------------------
				// Site config

					$site_config = config::get_all('table');

					foreach ($site_config as $name => $value) {
						if ($name == 'active_asc_suffix_html') $this->active_asc_suffix_set_html($value);
						else if ($name == 'active_desc_suffix_html') $this->active_desc_suffix_set_html($value);
						else if ($name == 'inactive_suffix_html') $this->inactive_suffix_set_html($value);
						else if ($name != 'count') exit_with_error('Unrecognised table configuration "' . $name . '"');
					}

			}

			public function current_url_set($url) {
				$this->current_url = $url;
			}

			public function id_set($id) {
				$this->id_value = $id;
			}

			public function caption_set($caption) {
				$this->caption_text = $caption;
				$this->caption_html = NULL;
			}

			public function caption_set_html($caption_html) {
				$this->caption_text = NULL;
				$this->caption_html = $caption_html;
			}

			public function caption_get() {
				if ($this->caption_text !== NULL) {
					return $this->caption_text;
				} else if ($this->caption_html !== NULL) {
					return html_decode(strip_tags($this->caption_html));
				} else {
					return NULL;
				}
			}

			public function caption_get_html() {
				if ($this->caption_text !== NULL) {
					return html($this->caption_text);
				} else if ($this->caption_html !== NULL) {
					return $this->caption_html;
				} else {
					return NULL;
				}
			}

			public function anchor_set($id) {
				$this->id_set($id);
				$this->current_url_set('#' . $id);
			}

			public function class_set($class_name) {
				$this->class_name = $class_name;
			}

			public function charset_output_set($charset) {
				$this->charset_output = $charset;
			}

			public function sort_name_set($name = NULL) {

				$this->sort_enabled = true;

				if ($name == NULL) {
					$name = 't' . $this->table_id;
				}

				$this->sort_name = $name;

				$sort = request($this->sort_name);
				if (preg_match('/^([0-9]+)(A|D)$/', $sort, $matches)) {
					$this->sort_request_id = $matches[1];
					$this->sort_request_order = $matches[2];
				}

			}

			public function sort_default_set($field, $order = 'ASC') {
				$this->sort_enabled = true;
				$this->sort_default_field = $field;
				$this->sort_default_order = strtoupper($order);
			}

			public function sort_preserve_set($preserve) {
				if ($preserve) {

					$this->sort_preserved_key = 'table.sort_preserved.' . base64_encode(config::get('request.path')) . '.' . $this->table_id;

					$session = session::get($this->sort_preserved_key);
					if ($session) {
						list($this->sort_preserved_id, $this->sort_preserved_order) = $session;
					}

				} else {

					$this->sort_preserved_key = NULL;
					$this->sort_preserved_id = NULL;
					$this->sort_preserved_order = NULL;

				}
			}

			public function sort_field_get() {

				$this->sort_enabled = true;

				if ($this->sort_name === NULL) {
					$this->sort_name_set();
				}

				if (isset($this->sort_fields[$this->sort_request_id])) {
					return $this->sort_fields[$this->sort_request_id];
				}

				if (isset($this->sort_fields[$this->sort_preserved_id])) {
					return $this->sort_fields[$this->sort_preserved_id];
				}

				if ($this->sort_default_field) { // May not be in sort_fields
					return $this->sort_default_field;
				}

				$default = reset($this->sort_fields);
				if ($default === false) {
					$default = NULL;
				}
				return $default;

			}

			public function sort_order_get() {

				$this->sort_enabled = true;

				if ($this->sort_name === NULL) {
					$this->sort_name_set();
				}

				if ($this->sort_request_order) {
					return ($this->sort_request_order == 'A' ? 'ASC' : 'DESC');
				}

				if ($this->sort_preserved_order) {
					return ($this->sort_preserved_order == 'A' ? 'ASC' : 'DESC');
				}

				if ($this->sort_default_order == 'ASC' || $this->sort_default_order == 'DESC') {
					return $this->sort_default_order;
				}

				return 'ASC';

			}

			public function sort_url_get($field, $order) {

				$this->sort_enabled = true;

				if ($this->sort_name === NULL) {
					$this->sort_name_set();
				}

				$params = array($this->sort_name => $field . $order);

				if ($this->current_url === NULL) {
					return url($params);
				} else {
					return url($this->current_url, $params);
				}

			}

			public function sort_get_sql() {

				$this->sort_enabled = true;

				$order_by_sql = $this->sort_field_get();

				if (preg_match('/^([^,]+)(,.*)$/', $order_by_sql, $matches)) {
					return $matches[1] . ' ' . $this->sort_order_get() . $matches[2];
				} else {
					return $order_by_sql . ' ' . $this->sort_order_get();
				}

			}

			public function active_asc_prefix_set($content) {
				$this->sort_active_asc_prefix_html = html($content);
			}

			public function active_asc_prefix_set_html($content_html) {
				$this->sort_active_asc_prefix_html = $content_html;
			}

			public function active_asc_suffix_set($content) {
				$this->sort_active_asc_suffix_html = html($content);
			}

			public function active_asc_suffix_set_html($content_html) {
				$this->sort_active_asc_suffix_html = $content_html;
			}

			public function active_desc_prefix_set($content) {
				$this->sort_active_desc_prefix_html = html($content);
			}

			public function active_desc_prefix_set_html($content_html) {
				$this->sort_active_desc_prefix_html = $content_html;
			}

			public function active_desc_suffix_set($content) {
				$this->sort_active_desc_suffix_html = html($content);
			}

			public function active_desc_suffix_set_html($content_html) {
				$this->sort_active_desc_suffix_html = $content_html;
			}

			public function inactive_prefix_set($content) {
				$this->sort_inactive_prefix_html = html($content);
			}

			public function inactive_prefix_set_html($content_html) {
				$this->sort_inactive_prefix_html = $content_html;
			}

			public function inactive_suffix_set($content) {
				$this->sort_inactive_suffix_html = html($content);
			}

			public function inactive_suffix_set_html($content_html) {
				$this->sort_inactive_suffix_html = $content_html;
			}

			public function heading_add($heading, $sort_name = NULL, $class_name = '', $config = array()) {
				$this->heading_add_html(nl2br(html($heading)), $sort_name, $class_name, $config);
			}

			public function heading_add_html($heading_html, $sort_name = NULL, $class_name = '', $config = array()) {

				if (!isset($this->headings[$this->heading_id])) {
					$this->headings[$this->heading_id] = array();
				}

				if ($sort_name !== NULL && $sort_name !== '') {

					$this->sort_enabled = true;
					$this->sort_id++;
					$this->sort_fields[$this->sort_id] = $sort_name;

					$sort_id = $this->sort_id;

				} else {

					$sort_id = NULL;

				}

				if (is_numeric($config)) {
					$config = array('colspan' => $config);
				}

				$this->headings[$this->heading_id][] = array_merge(array(
						'html' => $heading_html,
						'sort_id' => $sort_id,
						'class_name' => $class_name,
						'colspan' => 1,
						'title' => NULL,
					), $config);

			}

			public function heading_row_end() {
				$this->heading_id++;
			}

			public function footer_add($footer, $class_name = '', $config = array()) {
				$this->footer_add_html(html($footer), $class_name, $config);
			}

			public function footer_add_html($footer_html, $class_name = '', $config = array()) {

				if (!isset($this->footers[$this->footer_id])) {
					$this->footers[$this->footer_id] = array();
				}

				if (is_numeric($config)) {
					$config = array('colspan' => $config);
				}

				$this->footers[$this->footer_id][] = array_merge(array(
						'html' => $footer_html,
						'class_name' => $class_name,
						'colspan' => 1,
						'title' => NULL,
					), $config);

			}

			public function footer_row_end() {

				if (!isset($this->footers[$this->footer_id])) {
					$this->footers[$this->footer_id] = array();
				}

				$this->footer_id++;

			}

			public function _row_add($row, $class_name = '', $id_value = '') { // Public for table_row to call
				$this->rows[] = array(
						'row' => $row,
						'class_name' => $class_name,
						'id_value' => $id_value,
					);
			}

			public function row_count() {
				return count($this->rows);
			}

			public function no_records_set($no_records) {
				$this->no_records_html = html($no_records);
			}

			public function no_records_set_html($no_records_html) {
				$this->no_records_html = $no_records_html;
			}

		//--------------------------------------------------
		// Output

			public function html() {

				//--------------------------------------------------
				// Current sort - inc support for defaults

					if ($this->sort_enabled) {

						$sort_field = $this->sort_field_get();
						$sort_asc = ($this->sort_order_get() == 'ASC');

						if ($this->sort_preserved_key && $this->sort_request_id && $this->sort_request_order) {
							session::set($this->sort_preserved_key, array($this->sort_request_id, $this->sort_request_order));
						}

					}

				//--------------------------------------------------
				// Headings

					$col_class = array();
					$col_count = 0;

						// Yes, the @roles are not really necessary, but still required
						// to validate, and begrudgingly show screen readers we are
						// not using the table for layout purposes.
						// https://stackoverflow.com/q/24863531

					$output_html = '
						<table' . ($this->id_value != '' ? ' id="' . html($this->id_value) . '"' : '') . ($this->class_name != '' ? ' class="' . html($this->class_name) . '"' : '') . '>';

					if ($this->caption_text) {
						$output_html .= '
							<caption>' . html($this->caption_text) . '</caption>';
					} else if ($this->caption_html) {
						$output_html .= '
							<caption>' . $this->caption_html . '</caption>';
					}

					$output_html .= '
							<thead>';

					foreach ($this->headings as $row_id => $heading_row) {

						$col_id = 0;

						$output_html .= '
								<tr>';

						foreach ($heading_row as $heading_info) {

							//--------------------------------------------------
							// HTML content, url, and class

								$attributes_html = '';

								if ($this->sort_name === NULL || $heading_info['sort_id'] === NULL) {

									$heading_html = $heading_info['html'];

								} else if ($sort_field == $this->sort_fields[$heading_info['sort_id']]) {

									$url = $this->sort_url_get($heading_info['sort_id'], ($sort_asc ? 'D' : 'A'));

									$heading_html = '<a href="' . html($url) . '">' . ($sort_asc ? $this->sort_active_asc_prefix_html : $this->sort_active_desc_prefix_html) . $heading_info['html'] . ($sort_asc ? $this->sort_active_asc_suffix_html : $this->sort_active_desc_suffix_html) . '</a>';

									$heading_info['class_name'] .= ' sorted ' . ($sort_asc ? 'sorted_asc' : 'sorted_desc');

									$attributes_html .= ' aria-sort="' . ($sort_asc ? 'ascending' : 'descending') . '"'; // https://www.w3.org/TR/wai-aria/states_and_properties#aria-sort

								} else {

									$url = $this->sort_url_get($heading_info['sort_id'], 'A');

									$heading_html = '<a href="' . html($url) . '">' . $this->sort_inactive_prefix_html . $heading_info['html'] . $this->sort_inactive_suffix_html . '</a>';

								}

							//--------------------------------------------------
							// Attributes - col span

								if ($heading_info['colspan'] > 1) {
									$attributes_html .= ' colspan="' . html($heading_info['colspan']) . '"';
								}

							//--------------------------------------------------
							// Attributes - title

								if ($heading_info['title'] !== NULL) {
									$attributes_html .= ' title="' . html($heading_info['title']) . '"';
								}

							//--------------------------------------------------
							// Attributes - class

								$m = ($col_id + $heading_info['colspan']);

								for ($k = $col_id; $k < $m; $k++) {

									if (!isset($col_class[$k])) {
										$col_class[$k] = '';
									}

									if ($this->data_inherit_heading_class && $heading_info['class_name'] != '') {
										$col_class[$k] .= ' ' . $heading_info['class_name'];
									}

								}

								if ($heading_info['class_name'] != '') {
									$attributes_html .= ' class="' . html($heading_info['class_name']) . '"';
								}

							//--------------------------------------------------
							// HTML

								if ($heading_info['html'] === '' || $heading_info['html'] === NULL) {
									$heading_info['html'] = '&#xA0;';
								}

								$output_html .= '
										<th scope="col"' . $attributes_html . '>' . $heading_html . '</th>';

							//--------------------------------------------------
							// Column ID

								$col_id += $heading_info['colspan'];

						}

						if ($col_id > $col_count) {
							$col_count = $col_id;
						}

						$output_html .= '
								</tr>';

					}

					$output_html .= '
							</thead>';

				//--------------------------------------------------
				// Footer

					if (count($this->footers)) {

						$output_html .= '
							<tfoot>';

						foreach ($this->footers as $footer_row) {

							$col_id = 0;

							$output_html .= '
								<tr>';

							foreach ($footer_row as $footer_info) {

								//--------------------------------------------------
								// Attributes - col span

									if ($footer_info['colspan'] > 1) {
										$attributes_html = ' colspan="' . html($footer_info['colspan']) . '"';
									} else {
										$attributes_html = '';
									}

								//--------------------------------------------------
								// Attributes - title

									if ($footer_info['title'] !== NULL) {
										$attributes_html .= ' title="' . html($footer_info['title']) . '"';
									}

								//--------------------------------------------------
								// Attributes - class

									$class = $footer_info['class_name'];

									if ($this->footer_inherit_heading_class && isset($col_class[$col_id]) && $col_class[$col_id] != '') {
										$class .= ' ' . $col_class[$col_id];
									}

									$class = trim($class);
									if ($class != '') {
										$attributes_html .= ' class="' . html(trim($class)) . '"';
									}

								//--------------------------------------------------
								// HTML

									if ($footer_info['html'] === '' || $footer_info['html'] === NULL) {
										$footer_info['html'] = '&#xA0;';
									}

									$output_html .= '
										<td' . $attributes_html . '>' . $footer_info['html'] . '</td>';

								//--------------------------------------------------
								// Column ID

									$col_id += $footer_info['colspan'];

							}

							$output_html .= '
								</tr>';

						}

						$output_html .= '
							</tfoot>';

					}

				//--------------------------------------------------
				// Data

					$output_html .= '
							<tbody>';

					$row_count = 0;

					foreach (array_keys($this->rows) as $row_key) {

						$row_class = trim($this->rows[$row_key]['class_name'] . ($row_count++ % 2 ? ' even' : ' odd'));
						$row_id = $this->rows[$row_key]['id_value'];

						$output_html .= '
								<tr';

						if ($row_id != '') {
							$output_html .= ' id="' . html($row_id) . '"';
						}

						if ($row_class != '') {
							$output_html .= ' class="' . html($row_class) . '"';
						}

						$output_html .= '>';

						$col_id = 0;

						foreach ($this->rows[$row_key]['row']->data as $cell_info) {

							//--------------------------------------------------
							// Attributes - col span

								if ($cell_info['colspan'] <= 0) { // Try to use -1, as that looks different enough (but still a number) to be understood.
									$cell_info['colspan'] = $col_count;
								}

								if ($cell_info['colspan'] > 1) {
									$attributes_html = ' colspan="' . html($cell_info['colspan']) . '"';
								} else {
									$attributes_html = '';
								}

							//--------------------------------------------------
							// Attributes - title

								if ($cell_info['title'] !== NULL) {
									$attributes_html .= ' title="' . html($cell_info['title']) . '"';
								}

							//--------------------------------------------------
							// Attributes - class

								$class = $cell_info['class_name'];

								if (isset($col_class[$col_id]) && $col_class[$col_id] != '') {
									$class .= ' ' . $col_class[$col_id];
								}

								$class = trim($class);
								if ($class != '') {
									$attributes_html .= ' class="' . html($class) . '"';
								}

							//--------------------------------------------------
							// HTML

								if ($cell_info['html'] === '' || $cell_info['html'] === NULL) {
									$cell_info['html'] = '&#xA0;';
								}

								$output_html .= '
									<td' . $attributes_html . '>' . $cell_info['html'] . '</td>';

							//--------------------------------------------------
							// Column ID

								$col_id += $cell_info['colspan'];

						}

						while ($col_id < $col_count) {

							//--------------------------------------------------
							// Attributes - class

								if (isset($col_class[$col_id]) && $col_class[$col_id] != '') {
									$class = $col_class[$col_id];
								} else {
									$class = '';
								}

								$class = trim($class);
								if ($class != '') {
									$attributes_html = ' class="' . html($class) . '"';
								} else {
									$attributes_html = '';
								}

							//--------------------------------------------------
							// HTML

								$output_html .= '
									<td' . $attributes_html . '>&#xA0;</td>';

							//--------------------------------------------------
							// Column ID

								$col_id++;

						}

						$output_html .= '
								</tr>';

					}

				//--------------------------------------------------
				// Error message

					if (count($this->rows) == 0) {

						$output_html .= '
								<tr>
									<td colspan="' . html($col_count) . '" class="no_results">' . $this->no_records_html . '</td>
								</tr>';

					}

				//--------------------------------------------------
				// End

					$output_html .= '
							</tbody>
						</table>';

				//--------------------------------------------------
				// Return

					return $output_html;

			}

			public function text() {

				//--------------------------------------------------
				// Col widths

					$col_widths = array();
					$row_lines = array();
					$max_width = 70;

					foreach ($this->headings as $row_id => $heading_row) {

						$col_id = 0;

						foreach ($heading_row as $heading_id => $heading_info) {

							$text = $this->_html_to_text($heading_info['html']);

							$length = mb_strlen($text);
							if (!isset($col_widths[$col_id]) || $col_widths[$col_id] < $length) {
								$col_widths[$col_id] = $length;
							}

							$this->headings[$row_id][$heading_id]['text'] = $text;

							$col_id += $heading_info['colspan'];

						}

					}

					foreach (array_keys($this->rows) as $row_key) {

						$col_id = 0;

						foreach ($this->rows[$row_key]['row']->data as $cell_id => $cell_info) {

							$text = $this->_html_to_text($cell_info['html'], $max_width);

							$lines = count($text);

							if (!isset($row_lines[$row_key]) || $row_lines[$row_key] < $lines) {
								$row_lines[$row_key] = $lines;
							}

							foreach ($text as $line) {
								$length = mb_strlen($line);
								if (!isset($col_widths[$col_id]) || $col_widths[$col_id] < $length) {
									$col_widths[$col_id] = $length;
								}
							}

							$this->rows[$row_key]['row']->data[$cell_id]['text'] = $text;

							$col_id += $cell_info['colspan'];

						}

						if ($col_id == 0) {
							$row_lines[$row_key] = 1;
						}

					}

					$row_divide = '';
					foreach ($col_widths as $col_id => $col_width) {
						$row_divide .= ($col_id > 0 ? '-' : '') . '+-' . str_repeat('-', ($col_width > $max_width ? $max_width : $col_width));
					}

					$row_divide .= "-+\n";

				//--------------------------------------------------
				// Headings

					$col_count = 0;

					$output = '';

					foreach ($this->headings as $row_id => $heading_row) {

						$col_id = 0;

						$output .= str_replace('-', '=', $row_divide);

						foreach ($heading_row as $col_id => $heading_info) {

							$output .= ($col_id > 0 ? ' ' : '') . '| ' . mb_str_pad($heading_info['text'], $col_widths[$col_id]);

							for ($k = 1; $k < $heading_info['colspan']; $k++) {
								$output .= '   ' . mb_str_pad('', $col_widths[$col_id + $k]);
							}

							$col_id += $heading_info['colspan'];

						}

						if ($col_id > $col_count) {
							$col_count = $col_id;
						}

						$output .= " |\n";

					}

					$output .= str_replace('-', '=', $row_divide);

				//--------------------------------------------------
				// Data

					foreach (array_keys($this->rows) as $row_key) {

						$lines = (isset($row_lines[$row_key]) ? $row_lines[$row_key] : 0);

						for ($line = 0; $line < $lines; $line++) {

							$col_id = 0;

							foreach ($this->rows[$row_key]['row']->data as $cell_info) {

								$text = (isset($cell_info['text'][$line]) ? $cell_info['text'][$line] : '');

								$output .= ($col_id > 0 ? ' ' : '') . '| ' . mb_str_pad($text, $col_widths[$col_id]);

								for ($k = 1; $k < $cell_info['colspan']; $k++) {
									$output .= '   ' . mb_str_pad('', $col_widths[$col_id + $k]);
								}

								$col_id += $cell_info['colspan'];

							}

							while ($col_id < $col_count) {
								$output .= ($col_id > 0 ? ' ' : '') . '| ' . mb_str_pad('', $col_widths[$col_id]);
								$col_id++;
							}

							$output .= " |\n";

						}

						$output .= $row_divide;

					}

				//--------------------------------------------------
				// Return

					return $output;

			}

			public function csv() {

				//--------------------------------------------------
				// Headings

					$col_count = 0;

					$csv_output = '';

					foreach ($this->headings as $row_id => $heading_row) {

						$col_id = 0;

						foreach ($heading_row as $col_id => $heading_info) {

							$csv_output .= $this->_html_to_csv($heading_info['html']) . ',';

							for ($k = 1; $k < $heading_info['colspan']; $k++) {
								$csv_output .= '"",';
							}

							$col_id += $heading_info['colspan'];

						}

						if ($col_id > $col_count) {
							$col_count = $col_id;
						}

						$csv_output .= "\n";

					}

				//--------------------------------------------------
				// Data

					foreach (array_keys($this->rows) as $row_key) {

						$col_id = 0;

						foreach ($this->rows[$row_key]['row']->data as $cell_info) {

							$csv_output .= $this->_html_to_csv($cell_info['html']) . ',';

							for ($k = 1; $k < $cell_info['colspan']; $k++) {
								$csv_output .= '"",';
							}

							$col_id += $cell_info['colspan'];

						}

						while ($col_id < $col_count) {
							$csv_output .= '"",';
							$col_id++;
						}

						$csv_output .= "\n";

					}

				//--------------------------------------------------
				// Error message

					if (count($this->rows) == 0) {

						$csv_output .= $this->_html_to_csv($this->no_records_html) . ',';

						for ($k = 0; $k < ($col_count - 1); $k++) {
							$csv_output .= '"",';
						}

						$csv_output .= "\n";

					}

				//--------------------------------------------------
				// Footer

					if (count($this->footers)) {

						foreach ($this->footers as $footer_row) {

							foreach ($footer_row as $footer_info) {

								$csv_output .= $this->_html_to_csv($footer_info['html']) . ',';

								for ($k = 1; $k < $footer_info['colspan']; $k++) {
									$csv_output .= '"",';
								}

							}

							$csv_output .= "\n";

						}

					}

				//--------------------------------------------------
				// Clean end of lines

					$csv_output = preg_replace('/,$/m', '', $csv_output);

				//--------------------------------------------------
				// Return

					return $csv_output;

			}

			public function csv_download($file_name, $mode = NULL) {

				//--------------------------------------------------
				// Debug mode

					if ($mode === NULL && config::get('debug.level') > 0 && request('debug') !== 'false') {

						if (!$this->id_value) {
							$this->id_set('table_' . $this->table_id);
						}

						$output_html  = $this->html();
						$output_html .= '<p>Debug view / <a href="' . html(url(array('debug' => 'false'))) . '">Download CSV</a></p>';
						$output_html .= '<style>';
						$output_html .= '#' . html($this->id_value) . ' { border-collapse: collapse; border-spacing: 0; }';
						$output_html .= '#' . html($this->id_value) . ' caption { margin: 0 0 0.5em 0; }';
						$output_html .= '#' . html($this->id_value) . ' thead span.sort { font-size: 0.75em; }';
						$output_html .= '#' . html($this->id_value) . ' thead th { background-color: #DDD; }';
						$output_html .= '#' . html($this->id_value) . ' tr:nth-child(even) { background-color: #F7F7F7; }';
						$output_html .= '#' . html($this->id_value) . ' tr:hover { background-color: #FFD; }';
						$output_html .= '#' . html($this->id_value) . ' th { border: 1px solid #000; padding: 2px 4px; white-space: nowrap; }';
						$output_html .= '#' . html($this->id_value) . ' td { border: 1px solid #000; padding: 2px 4px; white-space: nowrap; }';
						$output_html .= '</style>';

						$response = response_get('html');
						$response->csp_source_add('style-src',  array('"unsafe-inline"'));
						$response->template_path_set(FRAMEWORK_ROOT . '/library/template/blank.ctp');
						$response->view_set_html($output_html);
						$response->send(); // So we can see the debug output.

						exit();

					}

				//--------------------------------------------------
				// Set output charset

					if ($this->charset_output !== NULL) {
						config::set('output.charset', $this->charset_output);
					}

				//--------------------------------------------------
				// Mime type

					if ($mode === 'inline') {

						$mode = 'inline';
						$mime = 'text/plain';

					} else {

						$mode = 'attachment';
						$mime = 'application/csv';

					}

				//--------------------------------------------------
				// Download

					http_download_content($this->csv(), $mime, $file_name, $mode);

			}

		//--------------------------------------------------
		// Support functions

			function _html_to_text($html, $max_width = NULL) {
				$text = html_decode(strip_tags($html));
				if ($max_width !== NULL) {
					$text = explode("\n", wordwrap($text, $max_width, "\n", true));
				}
				return $text;
			}

			function _html_to_csv($html) {
				$text = html_decode(strip_tags($html));
				if ($this->charset_output !== NULL && $this->charset_output != $this->charset_input) {
					$text = @iconv($this->charset_input, $this->charset_output . '//TRANSLIT', $text);
				}
				return csv($text);
			}

	}

	class table_row_base extends check {

		public $data;

		public function __construct($table, $class_name = '', $id_value = '') {

			//--------------------------------------------------
			// Defaults

				$this->data = array();

			//--------------------------------------------------
			// Add

				$table->_row_add($this, $class_name, $id_value);

		}

		public function cell_add($content = '', $class_name = '', $config = array()) {
			$this->cell_add_html(nl2br(html($content)), $class_name, $config);
		}

		public function cell_add_html($content_html = '', $class_name = '', $config = array()) {

			if (is_numeric($config)) {
				$config = array('colspan' => $config);
			}

			$this->data[] = array_merge(array(
					'html' => $content_html,
					'class_name' => $class_name,
					'colspan' => 1,
					'title' => NULL,
				), $config);

		}

		public function cell_add_link($url, $text, $class_name = '', $config = array()) {
			if ($url) {
				$this->cell_add_html('<a href="' . html($url) . '">' . nl2br(html($text)) . '</a>', $class_name, $config);
			} else {
				$this->cell_add($text, $class_name, $config);
			}
		}

	}

?>