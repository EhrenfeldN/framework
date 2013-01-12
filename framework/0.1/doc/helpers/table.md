
# Table helper

To see some how the table helper can be used, look at the [examples](/examples/table/).

You can view the source on [GitHub](https://github.com/craigfrancis/framework/blob/master/framework/0.1/library/class/table.php).

	//--------------------------------------------------
	// Site config

		table.active_asc_suffix_html
		table.active_desc_suffix_html
		table.inactive_suffix_html

	//--------------------------------------------------
	// Example setup

		$table = new table();
		$table->class_set('basic_table');
		$table->no_records_set('No records found');

		$table->heading_add('Heading 1');
		$table->heading_add('Heading 2');

		while (false) {
			$table_row = new table_row($table);
			$table_row->cell_add_html('<html>');
			$table_row->cell_add('Plain text');
		}

		// $table->charset_output_set('ISO-8859-1');
		// $table->csv_download('File.csv');
		// exit();

		<?= $table->html(); ?>