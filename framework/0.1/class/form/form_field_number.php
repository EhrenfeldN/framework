<?php

	class form_field_number_base extends form_field_text {

		//--------------------------------------------------
		// Variables

			protected $format_error_set;
			protected $zero_to_blank;
			protected $min_value;
			protected $max_value;

		//--------------------------------------------------
		// Setup

			public function __construct($form, $label, $name = NULL) {

				//--------------------------------------------------
				// Perform the standard field setup

					$this->_setup_text($form, $label, $name);

				//--------------------------------------------------
				// Additional field configuration

					$this->format_error_set = false;
					$this->zero_to_blank = false;
					$this->min_value = NULL;
					$this->max_value = NULL;
					$this->type = 'number';

			}

			public function zero_to_blank_set($blank) {
				$this->zero_to_blank = ($blank == true);
			}

		//--------------------------------------------------
		// Errors

			public function format_error_set($error) {

				if ($this->form_submitted && $this->value != '' && !is_numeric($this->value)) {
					$this->form->_field_error_set_html($this->form_field_uid, $error);
				}

				$this->format_error_set = true;

			}

			public function required_error_set($error) {
				$this->min_length_set($error);
			}

			public function min_value_set($error, $value) {

				if ($this->form_submitted && floatval($this->value) < $value) {
					$this->form->_field_error_set_html($this->form_field_uid, str_replace('XXX', $value, $error));
				}

				$this->min_value = $value;

			}

			public function max_value_set($error, $value) {

				if ($this->form_submitted && floatval($this->value) > $value) {
					$this->form->_field_error_set_html($this->form_field_uid, str_replace('XXX', $value, $error));
				}

				$this->max_value = $value;
				$this->max_length = (strlen($value) + 6); // Allow for a decimal place, plus an arbitrary 5 digits.

				if ($this->size === NULL && $this->max_length < 20) {
					$this->size = $this->max_length;
				}

			}

		//--------------------------------------------------
		// Value

			public function value_print_get($decimal_places = 2) {

				$value = parent::value_print_get();

				if ($value == 0 && $this->zero_to_blank) {
					return '';
				} else {
					return $value;
				}

			}

		//--------------------------------------------------
		// Validation

			public function _post_validation() {

				parent::_post_validation();

				if ($this->format_error_set == false) {
					exit('<p>You need to call "format_error_set", on the field "' . $this->label_html . '"</p>');
				}

			}

		//--------------------------------------------------
		// HTML

			public function html_input() {

				$attributes = $this->_input_attributes();
				$attributes['type'] = 'number';

				if ($this->min_value !== NULL) {
					$attributes['min'] = $this->min_value;
				}

				if ($this->max_value !== NULL) {
					$attributes['max'] = $this->max_value;
				}

				if ($attributes['value'] == '') {
					unset($attributes['value']); // HTML5 validation requires a valid floating point number.
				}

				unset($attributes['size']); // Invalid HTML5 attributes
				unset($attributes['maxlength']); // Invalid HTML5 attributes

				return $this->_html_input($attributes);

			}

	}

?>