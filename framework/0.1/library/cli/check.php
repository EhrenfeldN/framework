<?php

//--------------------------------------------------
// Check

	function check_run($mode = NULL) {

		//--------------------------------------------------
		// Files

			if (!$mode || $mode == 'dir') {

			}

		//--------------------------------------------------
		// Database

			if (!$mode || $mode == 'db') {
				check_db();
			}

	}

//--------------------------------------------------
// Database engine and collation

	function check_db() {

		//--------------------------------------------------
		// Config

			// $config['db.setup'] = array(
			// 		'table' => array(
			// 				'engine' => 'MyISAM',
			// 				'collation' => 'utf8_unicode_ci',
			// 				'fields' => array(
			// 						'ref' => array('collation' => 'utf8_bin'),
			// 					),
			// 			),
			// 	);

			$database_setup = config::get('db.setup', array());

			$default_setup = array(
					'engine' => config::get('db.engine', 'MyISAM'), // InnoDB is a newer engine, but I don't use enforced relationships or transactions (yet).
					'collation' => config::get('db.collation', 'utf8_unicode_ci'), // Avoid general, is faster, but more error prone.
					'fields' => array(),
				);

			$notes_engine = array();
			$notes_collation = array();
			$update_sql = array();

		//--------------------------------------------------
		// For each table

			$db = db_get();

			foreach ($db->fetch_all('SHOW TABLE STATUS') as $row) {
				if (prefix_match(DB_PREFIX, $row['Name'])) {

					//--------------------------------------------------
					// Table

						$table = substr($row['Name'], strlen(DB_PREFIX));

						$table_sql = $db->escape_table($row['Name']);

						if (isset($database_setup[$table])) {
							$table_setup = array_merge($default_setup, $database_setup[$table]);
						} else {
							$table_setup = $default_setup;
						}

					//--------------------------------------------------
					// Type

						if ($row['Engine'] != $table_setup['engine']) {

							$notes_engine[] = $table;

							$update_sql[] = 'ALTER TABLE ' . $table_sql . ' ENGINE = "' . $table_setup['engine'] . '";';

						}

					//--------------------------------------------------
					// Default collation

						if ($row['Collation'] != $table_setup['collation']) {

							$notes_collation[] = $table;

							$update_sql[] = 'ALTER TABLE ' . $table_sql . ' DEFAULT CHARACTER SET "' . check_character_set($table_setup['collation']) . '" COLLATE "' . $table_setup['collation'] . '";';

						}

					//--------------------------------------------------
					// Check fields

						foreach ($db->fetch_fields($table_sql) as $field_name => $field_info) {

							$field_collation = (isset($table_setup['fields'][$field_name]['collation']) ? $table_setup['fields'][$field_name]['collation'] : $table_setup['collation']);

							if ($field_info['collation'] !== NULL && $field_info['collation'] != $field_collation) {

								$definition_sql = $field_info['definition'];
								$collate_sql = 'CHARACTER SET "' . check_character_set($field_collation) . '" COLLATE "' . $field_collation . '"';

								if (($pos = strpos($definition_sql, ' ')) !== false) {
									$definition_sql = substr($definition_sql, 0, $pos) . ' ' . $collate_sql . substr($definition_sql, $pos);
								} else {
									$definition_sql = $definition_sql . ' ' . $collate_sql;
								}

								$notes_collation[] = $table . '.' . $field_name;

								$update_sql[] = 'ALTER TABLE ' . $table_sql . ' MODIFY ' . $db->escape_field($field_name) . ' ' . $definition_sql . ';';

							}

						}

				}
			}

		//--------------------------------------------------
		// Output

			$output = '';

			if (count($notes_engine) > 0) {
				$output .= 'Engine changes:' . "\n";
				foreach ($notes_engine as $note) {
					$output .= '  ' . $note . "\n";
				}
				$output .= "\n";
			}

			if (count($notes_collation) > 0) {
				$output .= 'Collation changes:' . "\n";
				foreach ($notes_collation as $note) {
					$output .= '  ' . $note . "\n";
				}
				$output .= "\n";
			}

			if (count($update_sql) > 0) {
				$output .= 'SQL:' . "\n";
				foreach ($update_sql as $note) {
					$output .= '  ' . $note . "\n";
				}
				$output .= "\n";
			}

			if ($output != '') {
				echo "\n" . $output;
			}

	}

	function check_character_set($collation) {
		if (($pos = strpos($collation, '_')) !== false) {
			return substr($collation, 0, $pos);
		} else {
			exit_with_error('Could not return character set for collation "' . $collation . '"');
		}
	}

?>