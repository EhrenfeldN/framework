<?php

// TODO: Test it works, and has "hidden" support

	class form_field_date extends form_field_base {

		//--------------------------------------------------
		// Variables

			protected $value;
			protected $value_provided;
			protected $invalid_error_set;
			protected $invalid_error_found;

		//--------------------------------------------------
		// Setup

			public function __construct(&$form, $label, $name = NULL) {

				//--------------------------------------------------
				// General setup

					$this->_setup($form, $label, $name);

				//--------------------------------------------------
				// Value

					// TODO
					if ($this->value === NULL) {
						if ($this->form_submitted) {

							$this->value = array(
									'D' => intval(data($this->name . '_D', $form->form_method_get())),
									'M' => intval(data($this->name . '_M', $form->form_method_get())),
									'Y' => intval(data($this->name . '_Y', $form->form_method_get())),
								);

						} else if ($this->db_field_name !== NULL) {

							$value = $this->form->db_value_get($this->db_field_name); // TODO

						}

						// TODO: What happens when this field is hidden

						// $value = $this->form->hidden_value_get($this->name);
						// if ($value != '') {
						// 	$this->value_key_set($value);
						// }

					}
					// TODO: Fix
					$this->value_provided = ($this->value['D'] != 0 || $this->value['M'] != 0 || $this->value['Y'] != 0);

				//--------------------------------------------------
				// Default configuration

					$this->invalid_error_set = false;
					$this->invalid_error_found = false;
					$this->type = 'date';

			}

		//--------------------------------------------------
		// Value

			public function value_provided() {
				return $this->value_provided;
			}

			public function value_set($value, $month = NULL, $year = NULL) {
				if ($month === NULL && $year === NULL) {

					if (!is_numeric($value)) {
						if ($value == '0000-00-00' || $value == '0000-00-00 00:00:00') {
							$value = NULL;
						} else {
							$value = strtotime($value);
							if ($value == 943920000) { // "1999-11-30 00:00:00", same as the database "0000-00-00 00:00:00"
								$value = NULL;
							}
						}
					}

					if (is_numeric($value)) {
						$this->value['D'] = date('j', $value);
						$this->value['M'] = date('n', $value);
						$this->value['Y'] = date('Y', $value);
					}

				} else {
					$this->value['D'] = intval($value);
					$this->value['M'] = intval($month);
					$this->value['Y'] = intval($year);
				}
			}

			public function value_get($part = NULL) {
				if ($part == 'D' || $part == 'M' || $part == 'Y') {
					return $this->value[$part];
				} else {
					return 'The date part must be set to "D", "M" or "Y"... or you could use value_date_get() or value_time_stamp_get()';
				}
			}

			public function value_date_get() {
				return str_pad(intval($this->value['Y']), 4, '0', STR_PAD_LEFT) . '-' . str_pad(intval($this->value['M']), 2, '0', STR_PAD_LEFT) . '-' . str_pad(intval($this->value['D']), 2, '0', STR_PAD_LEFT);
			}

			public function value_time_stamp_get() {
				if ($this->value['M'] == 0 && $this->value['D'] == 0 && $this->value['Y'] == 0) {
					$timestamp = false;
				} else {
					$timestamp = mktime(0, 0, 0, $this->value['M'], $this->value['D'], $this->value['Y']);
					if ($timestamp === -1) {
						$timestamp = false; // If the arguments are invalid, the function returns FALSE (before PHP 5.1 it returned -1).
					}
				}
				return $timestamp;
			}

		//--------------------------------------------------
		// Errors

			public function required_error_set($error) {

				if ($this->form_submitted && !$this->value_provided) {
					$this->form->_field_error_set_html($this->form_field_uid, $error);
				}

				$this->required = ($error !== NULL);

			}

			public function invalid_error_set($error) {

				$value = $this->value_time_stamp_get(); // Check upper bound to time-stamp, 2037 on 32bit systems

				if ($this->form_submitted && $this->value_provided && (!checkdate($this->value['M'], $this->value['D'], $this->value['Y']) || $value === false)) {

					$this->form->_field_error_set_html($this->form_field_uid, $error);

					$this->invalid_error_found = true;

				}

				$this->invalid_error_set = true;

			}

			public function min_date_set($error, $timestamp) {

				if ($this->form_submitted && $this->value_provided && $this->invalid_error_found == false) {

					$value = $this->value_time_stamp_get();

					if ($value !== false && $value < intval($timestamp)) {
						$this->form->_field_error_set_html($this->form_field_uid, $error);
					}

				}

			}

			public function max_date_set($error, $timestamp) {

				if ($this->form_submitted && $this->value_provided && $this->invalid_error_found == false) {

					$value = $this->value_time_stamp_get();

					if ($value !== false && $value > intval($timestamp)) {
						$this->form->_field_error_set_html($this->form_field_uid, $error);
					}

				}

			}

		//--------------------------------------------------
		// Validation

			private function _post_validation() {

				parent::_post_validation();

				if ($this->invalid_error_set == false) {
					exit('<p>You need to call "set_invalid_error", on the field "' . $this->label_html . '"</p>');
				}

			}

		//--------------------------------------------------
		// Status

			public function hidden_value_get() {
				return $this->value_date_get(); // TODO: Database support, $this->value_print_get() ?
			}

		//--------------------------------------------------
		// HTML output

			public function html_label($part = 'D', $label_html = NULL) {

				//--------------------------------------------------
				// Check the part

					if ($part != 'D' && $part != 'M' && $part != 'Y') {
						return 'The date part must be set to "D", "M" or "Y"';
					}

				//--------------------------------------------------
				// Required mark position

					$required_mark_position = $this->required_mark_position;
					if ($required_mark_position === NULL) {
						$required_mark_position = $this->required_mark_position_get();
					}

				//--------------------------------------------------
				// If this field is required, try to get a required
				// mark of some form

					if ($this->required) {

						$required_mark_html = $this->required_mark_html;

						if ($required_mark_html === NULL) {
							$required_mark_html = $this->form->required_mark_get_html($required_mark_position);
						}

					} else {

						$required_mark_html = NULL;

					}

				//--------------------------------------------------
				// Return the HTML for the label

					return '<label for="' . html($this->id) . '_' . html($part) . '"' . ($this->class_label === NULL ? '' : ' class="' . html($this->class_label) . '"') . '>' . ($required_mark_position == FORM_REQ_MARK_POS_LEFT && $required_mark_html !== NULL ? $required_mark_html : '') . ($label_html !== NULL ? $label_html : $this->label_html) . ($required_mark_position == FORM_REQ_MARK_POS_RIGHT && $required_mark_html !== NULL ? $required_mark_html : '') . '</label>';

			}

			public function html_label_for_date($separator_html = '/', $day_html = 'DD', $month_html = 'MM', $year_html = 'YYYY') {
				return '<label for="' . html($this->id) . '_D">' . $day_html . '</label>' . $separator_html . '<label for="' . html($this->id) . '_M">' . $month_html . '</label>' . $separator_html . '<label for="' . html($this->id) . '_Y">' . $year_html . '</label>';
			}

			public function html_field($part = NULL) {
				if ($part == 'D' || $part == 'M' || $part == 'Y') {
					return '<input type="text" name="' . html($this->name) . '_' . html($part) . '" id="' . html($this->id) . '_' . html($part) . '" maxlength="' . ($part == 'Y' ? 4 : 2) . '" size="' . ($part == 'Y' ? 4 : 2) . '" value="' . html($this->value[$part] == 0 ? '' : $this->value[$part]) . '"' . ($this->class_field === NULL ? '' : ' class="' . html($this->class_field) . '"') . ' />';
				} else {
					return 'The date part must be set to "D", "M" or "Y"';
				}
			}

			public function html() {
				return '
					<div class="' . html($this->class_row_get()) . '">
						<span class="label">' . $this->html_label() . $this->label_suffix_html . '</span>
						<span class="input">
							' . $this->html_field('D') . '
							' . $this->html_field('M') . '
							' . $this->html_field('Y') . '
						</span>
						<span class="help">' . $this->html_label_for_date() . '</span>' . $this->info_get_html(6) . '
					</div>' . "\n";
			}

	}

?>