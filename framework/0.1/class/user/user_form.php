<?php

	class user_form_base extends form {

		protected $user;

		public function user_ref_set($user) {
			$this->user = $user;
		}

		protected function field_identification_get($name = NULL) {

			if ($this->user->identification_type_get() == 'username') {

				$field_identification = new form_field_text($this, $this->user->text_get('identification_label'), ($name === NULL ? 'identification' : $name));
				$field_identification->min_length_set($this->user->text_get('identification_min_len'), 1);
				$field_identification->max_length_set($this->user->text_get('identification_max_len'), 50);
				return $field_identification;

			} else {

				$field_identification = new form_field_email($this, $this->user->text_get('identification_label'), ($name === NULL ? 'identification' : $name));
				$field_identification->format_error_set($this->user->text_get('identification_format'));
				$field_identification->min_length_set($this->user->text_get('identification_min_len'), 1);
				$field_identification->max_length_set($this->user->text_get('identification_max_len'), 250);
				return $field_identification;

			}

		}

		protected function field_identification_new_get($name = NULL) {

			if ($this->user->identification_type_get() == 'username') {

				$field_identification_new = new form_field_text($this, $this->user->text_get('identification_new_label'), ($name === NULL ? 'identification_new' : $name));
				$field_identification_new->min_length_set($this->user->text_get('identification_new_min_len'), 1);
				$field_identification_new->max_length_set($this->user->text_get('identification_new_max_len'), 50);
				return $field_identification_new;

			} else {

				$field_identification_new = new form_field_email($this, $this->user->text_get('identification_new_label'), ($name === NULL ? 'identification_new' : $name));
				$field_identification_new->format_error_set($this->user->text_get('identification_new_format'));
				$field_identification_new->min_length_set($this->user->text_get('identification_new_min_len'), 1);
				$field_identification_new->max_length_set($this->user->text_get('identification_new_max_len'), 250);
				return $field_identification_new;

			}

		}

		protected function field_password_get($required = NULL, $name = NULL) {

			$field_password = new form_field_password($this, $this->user->text_get('password_label'), ($name === NULL ? 'password' : $name));

			if ($required === NULL || $required === true) {  // Default required (register page, or re-confirm on profile page)
				$field_password->min_length_set($this->user->text_get('password_min_len'), 1);
			}

			$field_password->max_length_set($this->user->text_get('password_max_len'), 250);

			return $field_password;

		}

		protected function field_password_new_get($required = NULL) {

			$field_password = new form_field_password($this, $this->user->text_get('password_new_label'));

			if ($required === true) { // Default not required (profile page)
				$field_password->min_length_set($this->user->text_get('password_new_min_len'), 1);
			}

			$field_password->max_length_set($this->user->text_get('password_new_max_len'), 250);

			return $field_password;

		}

		protected function field_password_repeat_get($required = NULL) {

			$field_password_repeat = new form_field_password($this, $this->user->text_get('password_repeat_label'));

			if ($required === NULL) {
				if ($this->field_exists('password_new')) {
					$required = false; // Profile page, with new password field (will be used to check re-entry)
				} else if ($this->field_exists('password')) {
					$required = true; // Register page, asking to repeat password.
				}
			}

			if ($required === true) {
				$field_password_repeat->min_length_set($this->user->text_get('password_repeat_min_len'), 1);
			}

			$field_password_repeat->max_length_set($this->user->text_get('password_repeat_max_len'), 250);

			return $field_password_repeat;

		}

	}

?>