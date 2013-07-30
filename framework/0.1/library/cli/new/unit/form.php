<?php

	class [CLASS_NAME]_unit extends unit {

		public function setup($config) {

			//--------------------------------------------------
			// Config

				$config = array_merge(array(
						'next_url' => url('./thank-you/'),
					), $config);

				// $db = db_get();

			//--------------------------------------------------
			// Form setup

				$form = new form();
				$form->form_class_set('basic_form');
				$form->form_button_set('Send');
				//$form->form_action_set(https_url('#my-id'));
				//$form->db_table_set_sql($table_sql);
				//$form->db_where_set_sql($where_sql);

			//--------------------------------------------------
			// Form processing

				if ($form->submitted()) {

					//--------------------------------------------------
					// Validation



					//--------------------------------------------------
					// Form valid

						if ($form->valid()) {

							//--------------------------------------------------
							// Email

								$values = $form->data_array_get();

								$email = new email();
								$email->subject_set('Contact us');
								$email->request_table_add($values);
								$email->send(config::get('email.contact_us'));

							//--------------------------------------------------
							// Save

								// $form->db_value_set('ip', config::get('request.ip'));

								$form->db_save();

							//--------------------------------------------------
							// Next page

								// $form->dest_redirect($config['next_url']);

								redirect($config['next_url']);

						}

				} else {

					//--------------------------------------------------
					// Defaults



				}

			//--------------------------------------------------
			// Variables

				$this->set('form', $form);

		}

	}

/*--------------------------------------------------*/
/* Example

	$unit = unit_add('[CLASS_NAME]', array(
			'next_url' => url('/path/to/thankyou/'),
		));

/*--------------------------------------------------*/

?>