<?php

//--------------------------------------------------
// http://www.phpprime.com/doc/helpers/nav/
//--------------------------------------------------

	class nav_base extends check {

		private $current_group;
		private $current_index;

		private $navigation;

		private $indent;
		private $main_class;

		private $expand_all_children;
		private $automatically_expand_children;
		private $automatically_select_link;
		private $include_white_space;

		private $selected_id;
		private $selected_len;
		private $selected_link_found;

		private $path;

		public function __construct() {

			//--------------------------------------------------
			// Holder

				$this->current_group = 0;
				$this->current_index = 0;

				$this->navigation = array();

				$this->indent = '';
				$this->main_class = '';

				$this->expand_all_children = false;
				$this->automatically_expand_children = true;
				$this->automatically_select_link = true;
				$this->include_white_space = true;

				$this->selected_id = NULL;
				$this->selected_len = 0;
				$this->selected_link_found = false; // Includes child navigation bars

			//--------------------------------------------------
			// Default indent

				$this->indent_set(3);

			//--------------------------------------------------
			// Current path

				$this->path = config::get('request.uri');

		}

		public function indent_set($indent) {
			if ($this->include_white_space) {
				$this->indent = "\n" . str_repeat("\t", intval($indent));
			}
		}

		public function main_class_set($class) {
			$this->main_class = $class;
		}

		public function expand_all_children($do) {
			$this->expand_all_children = $do;
		}

		public function automatically_expand_children($do) {
			$this->automatically_expand_children = $do;
		}

		public function automatically_select_link($do) {
			$this->automatically_select_link = $do;
		}

		public function include_white_space($do) {
			$this->include_white_space = $do;
			if ($do == false) {
				$this->indent = '';
			}
		}

		public function link_add($url, $name, $config = NULL) {

			//--------------------------------------------------
			// Next!

				$this->current_index++;

			//--------------------------------------------------
			// Config

				$url = strval($url); // Handle url object

				if (!is_array($config)) {
					if (is_bool($config)) { // Backwards config
						$config = array(
								'selected' => $config
							);
					} else {
						$config = array();
					}
				}

				if (!isset($config['selected'])) {
					$config['selected'] = NULL;
				}

			//--------------------------------------------------
			// Add

				$this->navigation[$this->current_group]['links'][$this->current_index]['url'] = $url;
				$this->navigation[$this->current_group]['links'][$this->current_index]['name'] = $name;
				$this->navigation[$this->current_group]['links'][$this->current_index]['config'] = $config;

			//--------------------------------------------------
			// See if we have a match

				if ($this->selected_len >= 0) { // -1 disables

					$url_len = strlen($url);

					if ($config['selected'] === true) {

						$this->selected_id = $this->current_index;
						$this->selected_len = -1;

					} else if ($config['selected'] !== false && $url_len > $this->selected_len) {

						if ($this->automatically_select_link && substr($this->path, 0, $url_len) == $url) {

							$this->selected_id = $this->current_index;
							$this->selected_len = $url_len;

						}

					}

				}

		}

		public function group_add($name = '', $config = NULL) {

			if (count($this->navigation) > 0) {
				$this->current_group++;
			}

			$this->navigation[$this->current_group]['name_html'] = (isset($config['html']) && $config['html'] === true ? $name : html($name));
			$this->navigation[$this->current_group]['links'] = array();

		}

		public function link_count() {
			return $this->current_index;
		}

		public function html($level = 1) {

			//--------------------------------------------------
			// Start

				$html = ($this->include_white_space ? "\n" : '');

			//--------------------------------------------------
			// Pre-process the child navigation bars - need
			// to know if one of them have a selected child link

				foreach (array_keys($this->navigation) as $group_id) {
					if (count($this->navigation[$group_id]['links']) > 0) {
						foreach (array_keys($this->navigation[$group_id]['links']) as $link_id) {

							//--------------------------------------------------
							// Configuration

								$link = $this->navigation[$group_id]['links'][$link_id];

								$selected = ($link_id == $this->selected_id);

								$child_nav = (isset($link['config']['child']) ? $link['config']['child'] : NULL);
								$child_open = (isset($link['config']['open']) ? $link['config']['open'] : NULL);

								if ($child_nav === NULL) {
									$child_open = false;
								}

								if ($child_open === NULL) {
									$child_open = (($this->expand_all_children) || ($selected == true && $this->automatically_expand_children));
								}

							//--------------------------------------------------
							// Create HTML

								$child_html = '';

								if ($child_open) {

									//--------------------------------------------------
									// Get HTML

										if ($this->include_white_space == false) {
											$child_nav->include_white_space($this->include_white_space); // Only inherit when parent disables it (one case could be parent enabled, child disabled).
										}

										$child_nav->indent_set(strlen($this->indent) + 1);

										$child_html = $child_nav->html($level + 1);

										if ($child_nav->include_white_space == true) {
											$child_html .= $this->indent . ($this->include_white_space ? "\t" : '');
										}

									//--------------------------------------------------
									// If a child has a selected link

										if ($child_nav->selected_link_found == true) {
											$this->selected_link_found = true; // Supports 2+ levels deep selection
										}

								}

							//--------------------------------------------------
							// Save the HTML

								$this->navigation[$group_id]['links'][$link_id]['child_html'] = $child_html;

						}
					}
				}

			//--------------------------------------------------
			// Groups

				foreach (array_keys($this->navigation) as $group_id) {
					if (count($this->navigation[$group_id]['links']) > 0) {

						//--------------------------------------------------
						// Group heading

							if (isset($this->navigation[$group_id]['name_html']) && $this->navigation[$group_id]['name_html'] != '') {

								$html .= $this->indent . '<h3>' . $this->navigation[$group_id]['name_html'] . '</h3>';

							}

						//--------------------------------------------------
						// Group links

							$html .= $this->indent . '<ul' . ($this->main_class == '' ? '' : ' class="' . html($this->main_class) . '"') . '>';

							$k = 0;
							$links_count = count($this->navigation[$group_id]['links']);

							foreach (array_keys($this->navigation[$group_id]['links']) as $link_id) {

								//--------------------------------------------------
								// Quick variables

									$k++;

									$link_url    = $this->navigation[$group_id]['links'][$link_id]['url'];
									$link_name   = $this->navigation[$group_id]['links'][$link_id]['name'];
									$link_config = $this->navigation[$group_id]['links'][$link_id]['config'];
									$child_html  = $this->navigation[$group_id]['links'][$link_id]['child_html'];

									$link_html = (isset($link_config['html']) && $link_config['html'] === true);

								//--------------------------------------------------
								// Configuration

									$selected = ($link_id == $this->selected_id);

									if ($this->selected_link_found == true) {
										$selected = false; // A child nav item?
									}

									if ($selected) {
										$this->selected_link_found = true; // For any parents
									}

									$wrapper_html = ($selected ? 'strong' : 'span');

								//--------------------------------------------------
								// Class

									if ($link_html) {
										$class = ''; // Don't allow HTML version in class name
									} else {
										$class = human_to_camel($link_name);
									}

									if ($k % 2) $class .= ' odd';
									if ($k == 1) $class .= ' first_child';
									if ($k == $links_count) $class .= ' last_child';
									if ($selected) $class .= ' selected';
									if ($child_html != '') $class .= ' open';

									if (isset($link_config['item_class']) && $link_config['item_class'] != '') {
										$class .= ' ' . html($link_config['item_class']);
									}

								//--------------------------------------------------
								// Link attributes

									$link_attributes_html = '';

									if (isset($link_config['link_class']) && $link_config['link_class'] != '') {
										$link_attributes_html .= ' class="' . html($link_config['link_class']) . '"';
									}

									if (isset($link_config['link_title']) && $link_config['link_title'] != '') {
										$link_attributes_html .= ' title="' . html($link_config['link_title']) . '"';
									}

								//--------------------------------------------------
								// Build

									$html .= $this->indent . ($this->include_white_space ? "\t" : '') . '<li' . ($class != '' ? ' class="' . trim($class) . '"' : '') . '><' . $wrapper_html . ' class="link_level' . html($level) . '"><a href="' . html($link_url) . '"' . $link_attributes_html . '>' . ($link_html ? $link_name : html($link_name)) . '</a></' . $wrapper_html . '>' . $child_html . '</li>';

							}

							$html .= $this->indent . '</ul>' . ($this->include_white_space ? "\n" : '');

					}
				}

			//--------------------------------------------------
			// Return

				return $html;

		}

	}

?>