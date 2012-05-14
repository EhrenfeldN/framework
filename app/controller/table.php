<?php

	class table_controller extends controller {

		public function action_index() {

			//--------------------------------------------------
			// Table

				$table = new table();
				$table->class_set('basic_table full_width');
				$table->no_records_set('No records found');

				$table->heading_add('Person', NULL, 'text');
				$table->heading_add('Event', NULL, 'text', 2);
				$table->end_heading_row();

				$table->heading_add('Name', NULL, 'text');
				$table->heading_add('Created', NULL, 'date');
				$table->heading_add('Message', NULL);
				$table->end_heading_row();

				$table_row = new table_row($table);
				$table_row->cell_add('Craig Francis');
				$table_row->cell_add('2012-04-01');
				$table_row->cell_add('Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.');

				$table_row = new table_row($table);
				$table_row->cell_add('Rebz Loggenberg');
				$table_row->cell_add('2012-04-03');
				$table_row->cell_add('Consectetur adipisicing elit, sed do eiusmod tempor' . "\n\n" . 'Incididunt ut labore et dolore magna aliqua.');

				$table_row = new table_row($table);

				$table_row = new table_row($table);
				$table_row->cell_add('John Smith');
				$table_row->cell_add('2012-04-04');
				$table_row->cell_add('Lorem ipsum dolor sit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.');

				$table_row = new table_row($table);
				$table_row->cell_add('My name', '', 2);
				$table_row->cell_add('Lorem ipsum dolor sit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.');

			//--------------------------------------------------
			// Download

				// $table->csv_download('File.csv', 'attachment');
				// exit();

			//--------------------------------------------------
			// Variables

				$this->set('table', $table);

		}

	}

?>