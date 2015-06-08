<?php

	class reset_db_helper_base extends check {

		//--------------------------------------------------
		// Variables

			private $tables = array();
			private $timestamps = array();
			private $now = NULL;
			private $list_paths = array();
			private $list_data = array();
			private $list_length = array();
			private $list_postcode = array('AL','CB','CM','BT','BR','E','EC','DE','LE','LN','DH','DL','NE','BB','BL','CA','AB','DD','DG','HP','MK','NN','BN','CR','CT','BA','BH','BS','CF','LD','LL','B','CV','DY','BD','DN','HD');

		//--------------------------------------------------
		// Setup

			public function __construct($config = array()) {
				$this->setup($config);
			}

			protected function setup($config) {

				$this->now = time();

				$this->list_paths = $config['list_paths'];

			}

			public function tables_set($tables) {
				$this->tables = $tables;
			}

		//--------------------------------------------------
		// Create record - typically for child records.

			final public function record_create($table, $values, $config = array()) {
				$this->tables[$table]['class']->record_add($values, $config);
			}

		//--------------------------------------------------
		// Create a value

			public function value_get($type, $config = array()) {

				if ($type == 'timestamp') {

					if (!isset($this->timestamps[$config['from']])) $this->timestamps[$config['from']] = strtotime($config['from']);
					if (!isset($this->timestamps[$config['to']]))   $this->timestamps[$config['to']]   = strtotime($config['to']);

					return rand($this->timestamps[$config['from']], $this->timestamps[$config['to']]);

				} else if ($type == 'now') {

					return $this->now;

				} else if (isset($this->list_paths[$type])) { // name_first, name_last, etc

					if (!isset($this->list_data[$type])) {
						$this->list_data[$type] = file($this->list_paths[$type], FILE_IGNORE_NEW_LINES);
						array_shift($this->list_data[$type]); // Source
						$this->list_length[$type] = (count($this->list_data[$type]) - 1);
					}

					$value = $this->list_data[$type][mt_rand(0, $this->list_length[$type])]; // array_rand returns a key, and isn't particualry random anyway.

					if ($type == 'address_1') {
						$value = rand(1, 120) . ' ' . $value;
					}

					$rand = rand(0, 100);
					if ($rand > 98) {
						$value = strtoupper($value);
					} else if ($rand > 96) {
						$value = strtolower($value);
					}

					return $value;

				} else if ($type == 'postcode') {

					if (isset($config['country'])) {
						$country = $config['country'];
					} else {
						$country = 'UK';
					}

					if ($country == 'UK') {
						return $this->list_postcode[array_rand($this->list_postcode)] . rand(1, 20) . ' ' . rand(1, 9) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);
					} else {
						return '';
					}

				}

			}

		//--------------------------------------------------
		// Cleanup values

			public function values_parse($table, $record, $config) {

				foreach ($record as $field => $value) {
					if (is_array($value)) {
						$record[$field] = $this->value_get($value, $record, $config);
					}
				}

				foreach ($this->tables[$table]['field_datetimes'] as $field) { // Timestamp helpers are too slow (0.8 vs 0.3 seconds for 10000 records)... and timestamp intgers allow rand(start, end)
					if (is_int($record[$field])) {
						$record[$field] = gmdate('Y-m-d H:i:s', $record[$field]);
					}
				}

				foreach ($this->tables[$table]['field_dates'] as $field) {
					if (is_int($record[$field])) {
						$record[$field] = gmdate('Y-m-d', $record[$field]);
					}
				}

				return $record;

			}

	}

?>