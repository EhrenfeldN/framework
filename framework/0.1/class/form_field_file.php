<?php

	class form_field_file extends form_field_base {

		protected $max_size;

		protected $empty_file_error_set;
		protected $partial_file_error_set;
		protected $blank_name_error_set;

		protected $has_uploaded;

		protected $value_ext;
		protected $value_name;
		protected $value_size;
		protected $value_mime;

		function form_field_file(&$form, $label, $name = NULL) {
			$this->_setup_file($form, $label, $name);
		}

		function _setup_file(&$form, $label, $name = NULL) {

			//--------------------------------------------------
			// Perform the standard field setup

				$this->_setup($form, $label, $name);

			//--------------------------------------------------
			// Additional field configuration

				$this->quick_print_type = 'file';

			//--------------------------------------------------
			// Default validation configuration

				$this->max_size = 0;

				$this->empty_file_error_set = false;
				$this->partial_file_error_set = false;
				$this->blank_name_error_set = false;

			//--------------------------------------------------
			// If uploaded

				$this->has_uploaded = (isset($_FILES[$this->name]) && $_FILES[$this->name]['error'] != 4); // 4 = No file was uploaded (UPLOAD_ERR_NO_FILE)

			//--------------------------------------------------
			// File values

				$this->value_ext = NULL;
				$this->value_name = NULL;
				$this->value_size = NULL;
				$this->value_mime = NULL;

				if ($this->has_uploaded) {

					if (preg_match('/\.([a-z0-9]+)$/i', $_FILES[$this->name]['name'], $matches)) {
						$this->value_ext = strtolower($matches[1]);
					}

					$this->value_name = $_FILES[$this->name]['name'];
					$this->value_size = $_FILES[$this->name]['size'];
					$this->value_mime = $_FILES[$this->name]['type'];

				}

		}

		function set_required_error($error) {

			if (!$this->has_uploaded) {
				$this->form->_field_error_set_html($this->form_field_uid, $error);
			}

			$this->required = ($error !== NULL);

		}

		function set_max_size($error, $size) {

			$this->max_size = intval($size);

			if ($this->has_uploaded) {

				$error = str_replace('XXX', file_size2human($this->max_size), $error);

				if ($_FILES[$this->name]['error'] == 1) $this->form->_field_error_set_html($this->form_field_uid, $error, 'ERROR: Exceeds "upload_max_filesize" ' . ini_get('upload_max_filesize'));
				if ($_FILES[$this->name]['error'] == 2) $this->form->_field_error_set_html($this->form_field_uid, $error, 'ERROR: Exceeds "MAX_FILE_SIZE" specified in the html form');

				if ($_FILES[$this->name]['size'] >= $this->max_size) {
					$this->form->_field_error_set_html($this->form_field_uid, $error);
				}

			}

		}

		function get_max_size() {
			return $this->max_size;
		}

		function set_partial_file_error($error) {

			if ($this->has_uploaded) {
				if ($_FILES[$this->name]['error'] == 3) $this->form->_field_error_set_html($this->form_field_uid, $error);
			}

			$this->partial_file_error_set = true;

		}

		function set_allowed_file_types_mime($error, $types) {

			if ($this->has_uploaded && !in_array($this->value_mime, $types)) {
				$this->form->_field_error_set_html($this->form_field_uid, str_replace('XXX', $this->value_mime, $error), 'MIME: ' . $this->value_mime);
			}

		}

		function set_allowed_file_types_ext($error, $types) {

			if ($this->has_uploaded && !in_array($this->value_ext, $types)) {
				$this->form->_field_error_set_html($this->form_field_uid, str_replace('XXX', $this->value_ext, $error), 'EXT: ' . $this->value_ext);
			}

		}

		function set_empty_file_error($error) {

			if ($this->has_uploaded && $_FILES[$this->name]['size'] == 0) {
				$this->form->_field_error_set_html($this->form_field_uid, $error);
			}

			$this->empty_file_error_set = true;

		}

		function set_blank_name_error($error) {

			if ($this->has_uploaded && $_FILES[$this->name]['name'] == '') {
				$this->form->_field_error_set_html($this->form_field_uid, $error);
			}

			$this->blank_name_error_set = true;

		}

		function get_has_uploaded() {
			return $this->has_uploaded;
		}

		function get_value() {
			exit('<p>Do you mean get_file_path?</p>');
		}

		function get_file_path() {
			return (!$this->has_uploaded ? NULL: $_FILES[$this->name]['tmp_name']);
		}

		function get_file_ext() {
			return (!$this->has_uploaded ? NULL: $this->value_ext);
		}

		function get_file_name() {
			return (!$this->has_uploaded ? NULL: $this->value_name);
		}

		function get_file_size() {
			return (!$this->has_uploaded ? NULL: $this->value_size);
		}

		function get_file_mime() {
			return (!$this->has_uploaded ? NULL: $this->value_mime);
		}

		function html_field() {
			return '<input type="file" name="' . html($this->name) . '" id="' . html($this->id) . '"' . ($this->css_class_field === NULL ? '' : ' class="' . html($this->css_class_field) . '"') . ' />';
		}

		function save_file_to($path) {
			if ($this->has_uploaded && is_writable(dirname($path))) {
				return move_uploaded_file($_FILES[$this->name]['tmp_name'], $path);
			}
			return false;
		}

		function _error_check() {

			if ($this->max_size == 0) {
				exit('<p>You need to call "set_max_size", on the field "' . $this->label_html . '"</p>');
			}

			if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/x-www-form-urlencoded') { // If not set, assume its correct
				$GLOBALS['debug_show_output'] = false;
				exit('<p>The form needs the attribute: <strong>enctype="multipart/form-data"</strong></p>');
			}

			if ($this->empty_file_error_set == false) { // Provide default
				$this->set_empty_file_error('The uploaded file for "' . strtolower($this->label_html) . '" is empty');
			}

			if ($this->partial_file_error_set == false) { // Provide default
				$this->set_partial_file_error('The uploaded file for "' . strtolower($this->label_html) . '" was only partially uploaded');
			}

			if ($this->blank_name_error_set == false) { // Provide default
				$this->set_blank_name_error('The uploaded file for "' . strtolower($this->label_html) . '" does not have a filename');
			}

		}

	}

?>