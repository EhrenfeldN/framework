# Form helper

To see some of the form fields, look at the [examples](/examples/form/).

Kind of like [Symfony Forms](http://symfony.com/doc/2.0/book/forms.html), however [validation](http://symfony.com/doc/2.0/book/validation.html) is an integral part of this helper.

Inc notes on `save_request_restore/save_request_redirect()` functions.

	//--------------------------------------------------
	// Site config

		form.disabled
		form.readonly
		form.label_override_function
		form.error_override_function

		form.date_input_order
		form.time_input_order

		form.date_format_html
		form.time_format_html

	//--------------------------------------------------
	// Example setup

		$form = new form();
		$form->form_class_set('basic_form');
		$form->form_button_set('Save');
		$form->db_table_set_sql(DB_PREFIX . 'table');

		$field_name = new form_field_text($form, 'Name');
		$field_name->db_field_set('name');
		$field_name->min_length_set('Your name is required.');
		$field_name->max_length_set('Your name cannot be longer than XXX characters.');

		if ($form->submitted()) {

			// $form->error_add('Custom error');

			if ($form->valid()) {
				$form->db_save();
				redirect('...');
			}

		}

		<?= $form->html(); ?>

	//--------------------------------------------------
	// Search form

		$form = new form();
		$form->form_passive_set(true, 'GET');
		$form->form_button_set('Search');

		$field_search = new form_field_text($form, 'Search');
		$field_search->max_length_set('The search cannot be longer than XXX characters.', 200);

		if ($form->valid()) {
			$search = $field_search->value_get();
		} else {
			$search = '';
		}