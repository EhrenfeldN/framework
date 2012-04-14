<?php

//--------------------------------------------------
// Restricted nav creation, makes a navigation
// bar like:
//
//   [<]  1  2  3  4  [>]
//
//--------------------------------------------------

/***************************************************
// Example setup
//--------------------------------------------------

	// Site config:
	//   paginator.items_per_page
	//   paginator.items_count
	//   paginator... see below for the rest

	$result_count = 123;

	$paginator = new paginator($result_count);

	$paginator = new paginator(array(
			'items_per_page' => 3,
			'items_count' => $result_count,
		));

	$page_size = $paginator->page_size_get();
	$page_number = $paginator->page_number_get();

	$limit_sql = $paginator->limit_get_sql();

	<?= $paginator->html(); ?>

//--------------------------------------------------
// End of example setup
***************************************************/

class paginator_base extends check {

	protected $config = array(
		'items_per_page' => 24, // Divisible by 1, 2, 3, 4, 6, 12
		'items_count' => 0,
		'base_url' => NULL,
		'variable' => 'page',
		'elements' => NULL,
		'indent_html' => "\n\t\t\t\t",
		'first_html' => 'First',
		'back_html' => 'Back',
		'next_html' => 'Next',
		'last_html' => 'Last',
		'number_pad' => 0,
		'link_wrapper_element' => 'span',
		'extra_html' => '<span class="pagination_extra">Page [PAGE] of [COUNT]</span>',
	);

	protected $url = NULL;
	protected $page_count = NULL;
	protected $page_number = NULL;

	public function __construct($config = NULL) {
		this::setup($config);
	}

	protected function setup($config) {

		//--------------------------------------------------
		// Default elements

			if ($this->config['elements'] === NULL) {
				$this->config['elements'] = array('<p class="pagination">', 'first', 'back', 'links', 'next', 'last', 'extra', '</p>' . "\n");
			}

		//--------------------------------------------------
		// Set config

			if (is_numeric($config)) { // May be a string
				$config = array(
						'items_count' => $config
					);
			}

			$site_config = config::get_all('paginator');
			if (count($site_config) > 0) {
				if (is_array($config)) {
					$config = array_merge($site_config, $config);
				} else {
					$this->config($site_config);
				}
			}

			if (is_array($config)) {
				$this->config($config);
			}

	}

	public function config($config, $value = NULL) {

		//--------------------------------------------------
		// Set

			if (is_array($config)) {
				foreach ($config as $key => $value) {
					$this->config[$key] = $value;
				}
			} else {
				$this->config[$config] = $value;
			}

		//--------------------------------------------------
		// Get page variables to be re-calculated

			$this->url = NULL;

			if ($this->config['items_per_page'] > 0) {
				$this->page_count = ceil($this->config['items_count'] / $this->config['items_per_page']);
			} else {
				$this->page_count = 1;
			}

			if ($this->page_number === NULL || $config == 'variable' || isset($config['variable'])) {
				$this->page_number_set(isset($_REQUEST[$this->config['variable']]) ? $_REQUEST[$this->config['variable']] : 0);
			}

	}

	public function items_count_get() {
		return $this->config['items_count'];
	}

	public function limit_get_sql() {
		$page_number = $this->page_number_get();
		$page_size = $this->page_size_get();
		return intval(($page_number - 1) * $page_size) . ', ' . intval($page_size);
	}

	public function limit_array($array) {
		$page_number = $this->page_number_get();
		$page_size = $this->page_size_get();
		return array_slice($array, intval(($page_number - 1) * $page_size), $page_size, true);
	}

	public function page_size_get() {
		return $this->config['items_per_page'];
	}

	public function page_number_get() {
		return $this->page_number;
	}

	public function page_number_set($page_number) {

		$this->page_number = intval($page_number);

		if ($this->page_number > $this->page_count) {
			$this->page_number = $this->page_count;
		}
		if ($this->page_number < 1) {
			$this->page_number = 1;
		}

	}

	public function page_count_get() {
		return $this->page_count;
	}

	public function page_url_get($page_number) {

		if ($this->url === NULL) {
			$this->url = url($this->config['base_url']);
		}

		if ($page_number >= 1 && $page_number <= $this->page_count) {
			return $this->url->get(array($this->config['variable'] => $page_number));
		} else {
			return NULL;
		}

	}

	public function page_link_get_html($link_html, $page_number = NULL) {

		if ($page_number === NULL) {
			$page_number = $this->page_number;
		}

		$url = $this->page_url_get($page_number);

		if ($link_html !== NULL) {
			return ($url !== NULL ? '<a href="' . html($url) . '">' : '<span>') . $link_html . ($url !== NULL ? '</a>' : '</span>');
		} else {
			return $url;
		}

	}

	public function html() {

		//--------------------------------------------------
		// Page

			if ($this->page_number === NULL) {
				$this->page_number_set(1);
			}

		//--------------------------------------------------
		// Ignore if the navigation only has 1 page

			if ($this->page_count <= 1) {
				return '';
			}

		//--------------------------------------------------
		// Elements

			$nav_links_html = $this->_nav_links_html();

			if ($this->config['extra_html'] !== '') {
				$extra_html = $this->config['indent_html'] . "\t" . $this->config['extra_html'];
				$extra_html = str_replace('[PAGE]', $this->page_number, $extra_html);
				$extra_html = str_replace('[COUNT]', ($this->page_count == 0 ? 1 : $this->page_count), $extra_html);
			} else {
				$extra_html = '';
			}

		//--------------------------------------------------
		// Links

			$links_array = $this->_page_links_html();

			$links_html = '';
			foreach ($links_array as $link_html) {
				$links_html .= $this->config['indent_html'] . "\t" . $link_html;
			}

		//--------------------------------------------------
		// Return the html

			return $this->_html_format(array(
					'first' => $nav_links_html['first'],
					'back' => $nav_links_html['back'],
					'links' => $links_html,
					'links_array' => $links_array,
					'next' => $nav_links_html['next'],
					'last' => $nav_links_html['last'],
					'extra' => $extra_html,
				));

	}

	protected function _html_format($elements_html) {

			// $elements_html['first']
			// $elements_html['back']
			// $elements_html['links']
			// $elements_html['links_array']
			// $elements_html['next']
			// $elements_html['last']
			// $elements_html['extra']

		$html = '';

		foreach ($this->config['elements'] as $element) {
			if (isset($elements_html[$element])) {
				$html .= $elements_html[$element];
			} else {
				$html .= $this->config['indent_html'] . $element;
			}
		}

		return $html;

	}

	private function _nav_links_html() {

		//--------------------------------------------------
		// Defaults

			$nav_links_html = array(
					'first' => '',
					'back' => '',
					'next' => '',
					'last' => '',
				);

		//--------------------------------------------------
		// Build

			if ($this->config['first_html'] !== '') {

				$link_html = ($this->page_number <= 1 ? '<span>' . $this->config['first_html'] . '</span>' : $this->page_link_get_html($this->config['first_html'], 1));

				$nav_links_html['first'] = $this->config['indent_html'] . "\t" . '<' . html($this->config['link_wrapper_element']) . ' class="pagination_first">' . $link_html . '</' . html($this->config['link_wrapper_element']) . '>';

			}

			if ($this->config['back_html'] !== '') {

				$link_html = $this->page_link_get_html($this->config['back_html'], ($this->page_number - 1));

				$nav_links_html['back'] = $this->config['indent_html'] . "\t" . '<' . html($this->config['link_wrapper_element']) . ' class="pagination_back">' . $link_html . '</' . html($this->config['link_wrapper_element']) . '>';

			}

			if ($this->config['next_html'] !== '') {

				$link_html = $this->page_link_get_html($this->config['next_html'], $this->page_number + 1);

				$nav_links_html['next'] = $this->config['indent_html'] . "\t" . '<' . html($this->config['link_wrapper_element']) . ' class="pagination_next">' . $link_html . '</' . html($this->config['link_wrapper_element']) . '>';

			}

			if ($this->config['last_html'] !== '') {

				$link_html = ($this->page_number >= $this->page_count ? '<span>' . $this->config['last_html'] . '</span>' : $this->page_link_get_html($this->config['last_html'], $this->page_count));

				$nav_links_html['last'] = $this->config['indent_html'] . "\t" . '<' . html($this->config['link_wrapper_element']) . ' class="pagination_last">' . $link_html . '</' . html($this->config['link_wrapper_element']) . '>';

			}

		//--------------------------------------------------
		// Return

			return $nav_links_html;

	}

	private function _page_links_html() {

		//--------------------------------------------------
		// Range of page numbers

			$start = ($this->page_number - 4); // floor(9 / 2)
			if ($start > ($this->page_count - 8)) $start = ($this->page_count - 8);
			if ($start < 1) $start = 1;

			$page_links_html = array();

			for ($i = 1; $start <= $this->page_count && $i <= 9; $i++, $start++) {
				$c = ($start == $this->page_number);
				$page_links_html[] = '<' . html($this->config['link_wrapper_element']) . ' class="pagination_page pagination_page_' . $i . ($c ? ' pagination_current' : '') . '">' . ($c ? '<strong>' : '') . '<a href="' . html($this->page_url_get($start)) . '">' . str_pad($start, $this->config['number_pad'], '0', STR_PAD_LEFT) . '</a>' . ($c ? '</strong>' : '') . '</' . html($this->config['link_wrapper_element']) . '>';
			}

		//--------------------------------------------------
		// Return

			return $page_links_html;

	}

	public function __toString() { // (PHP 5.2)
		if (SERVER == 'stage') {
			return 'depreciated - use $paginator->html()';
		}
		return $this->html();
	}

}

?>