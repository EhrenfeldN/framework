<?php

/***************************************************

	//--------------------------------------------------
	// Site config



	//--------------------------------------------------
	// Example setup

		$order = new order();
		$order->select_open();

	//--------------------------------------------------
	// Item count - quick summary for a basket count

		echo $order->items_count_get();

	//--------------------------------------------------
	// Add an item

		$order = new order();
		$order->select_open();

		$order->item_add(array(
				'item_id' => $id,
				'item_code' => $code,
				'item_name' => $name,
				'price' => $price,
			));

	//--------------------------------------------------
	// Edit basket with 'delete' links (CSRF issue)

		$order->items_update();

		$table_html = $order->table_get_html(array(
				'quantity_edit' => 'link',
			));

	//--------------------------------------------------
	// Edit basket with 'quantity' select fields

		//--------------------------------------------------
		// Controller

			$form = new form();

			if ($form->submitted() && $form->valid()) {

				$order->items_update();

				if (strtolower(trim(request('button'))) == 'update totals') {
					redirect(http_url('/basket/'));
				} else {
					redirect(http_url('/basket/checkout/'));
				}

			}

			$table_html = $order->table_get_html(array(
					'quantity_edit' => array('type' => 'select'),
				));

			$this->set('form', $form);
			$this->set('table_html', $table_html);
			$this->set('empty_basket', ($order->items_count_get() == 0));

		//--------------------------------------------------
		// View

			<?= $form->html_start(); ?>
				<fieldset>

					<?= $form->html_error_list(); ?>

					<?= $order_table_html; ?>

					<?php if (!$empty_basket) { ?>

						<div class="submit">
							<input type="submit" name="button" value="Update totals" />
							<input type="submit" name="button" value="Checkout" />
						</div>

					<?php } ?>

				</fieldset>
			<?= $form->html_end(); ?>

	//--------------------------------------------------
	// Checkout page

		$order = new order();

		if (!$order->select_open()) {
			redirect(http_url('/basket/'));
		}

		$form = $order->form_get();
		$form->form_class_set('basic_form');
		$form->form_button_set('Continue');

		$form->print_group_start('Payment details');
		$form->field_get('payment_name');
		$form->field_get('payment_address_1');
		$form->field_get('payment_address_2');
		$form->field_get('payment_address_3');
		$form->field_get('payment_town_city');
		$form->field_get('payment_postcode');
		$form->field_get('payment_country');
		$form->field_get('payment_telephone');

		$form->print_group_start('Delivery details');
		$form->field_get('delivery_different');
		$form->field_get('delivery_name');
		$form->field_get('delivery_address_1');
		$form->field_get('delivery_address_2');
		$form->field_get('delivery_address_3');
		$form->field_get('delivery_town_city');
		$form->field_get('delivery_postcode');
		$form->field_get('delivery_country');
		$form->field_get('delivery_telephone');

		if ($form->submitted()) {

			$result = $order->save();

			if ($result) {
				redirect(http_url('/basket/payment/'));
			}

		} else {

			// Defaults

		}

		$this->set('form', $form);

	//--------------------------------------------------
	// Admin access

		//--------------------------------------------------
		// To grant permission

			config::set('order.user_privileged', ADMIN_LOGGED_IN);

***************************************************/

	class order_base extends check {

		//--------------------------------------------------
		// Variables

			protected $order_id = NULL;
			protected $order_data = NULL;
			protected $order_fields = array();
			protected $order_currency = 'GBP';

			protected $db_table_main = NULL;
			protected $db_table_item = NULL;

			protected $object_table = 'order_table';
			protected $object_payment = 'payment';

			private $db_link = NULL;
			private $table = NULL;
			private $form = NULL;

			private $order_items = NULL; // Cache

		//--------------------------------------------------
		// Setup

			public function __construct() {
				$this->_setup();
			}

			protected function _setup() {

				//--------------------------------------------------
				// Tables

					if ($this->db_table_main === NULL) $this->db_table_main = DB_PREFIX . 'order';
					if ($this->db_table_item === NULL) $this->db_table_item = DB_PREFIX . 'order_item';

					if (config::get('debug.level') > 0) {

						debug_require_db_table($this->db_table_main, '
								CREATE TABLE [TABLE] (
									id int(11) NOT NULL AUTO_INCREMENT,
									pass tinytext NOT NULL,
									ip tinytext NOT NULL,
									email varchar(100) NOT NULL,
									created datetime NOT NULL,
									edited datetime NOT NULL,
									payment_received datetime NOT NULL,
									payment_settled datetime NOT NULL,
									payment_tax float NOT NULL,
									payment_name tinytext NOT NULL,
									payment_address_1 tinytext NOT NULL,
									payment_address_2 tinytext NOT NULL,
									payment_address_3 tinytext NOT NULL,
									payment_town_city tinytext NOT NULL,
									payment_postcode tinytext NOT NULL,
									payment_country tinytext NOT NULL,
									payment_telephone tinytext NOT NULL,
									delivery_different enum(\'false\',\'true\') NOT NULL,
									delivery_name tinytext NOT NULL,
									delivery_address_1 tinytext NOT NULL,
									delivery_address_2 tinytext NOT NULL,
									delivery_address_3 tinytext NOT NULL,
									delivery_town_city tinytext NOT NULL,
									delivery_postcode tinytext NOT NULL,
									delivery_country tinytext NOT NULL,
									delivery_telephone tinytext NOT NULL,
									dispatched datetime NOT NULL,
									deleted datetime NOT NULL,
									PRIMARY KEY (id)
								);');

						debug_require_db_table($this->db_table_item, '
								CREATE TABLE [TABLE] (
									id int(11) NOT NULL AUTO_INCREMENT,
									order_id int(11) NOT NULL,
									type enum(\'item\',\'voucher\',\'discount\',\'delivery\') NOT NULL,
									item_id int(11) NOT NULL,
									item_code varchar(30) NOT NULL,
									item_name tinytext NOT NULL,
									price decimal(10,2) NOT NULL,
									quantity int(11) NOT NULL,
									created datetime NOT NULL,
									deleted datetime NOT NULL,
									PRIMARY KEY (id),
									KEY order_id (order_id)
								);');

					}

			}

		//--------------------------------------------------
		// Configuration

			public function id_get() {
				return $this->order_id;
			}

			public function ref_get() {
				if ($this->order_id === NULL) {
					return NULL;
				} else {
					return $this->order_id . '-' . $this->order_data['pass'];
				}
			}

			public function db_get() { // Public so order table can access
				if ($this->db_link === NULL) {
					$this->db_link = new db();
				}
				return $this->db_link;
			}

			protected function table_get() {
				if ($this->table === NULL) {
					$this->table = new $this->object_table();
					$this->table->order_ref_set($this);
					$this->table->init();
				}
				return $this->table;
			}

			public function form_get() {
				if ($this->form === NULL) {

					$db = $this->db_get();

					$this->form = new order_form();
					$this->form->order_ref_set($this);
					$this->form->db_set($this->db_get());
					$this->form->db_save_disable();
					$this->form->db_table_set_sql($db->escape_field($this->db_table_main));

					if ($this->order_id > 0) {

						$where_sql = '
							id = "' . $db->escape($this->order_id) . '" AND
							deleted = "0000-00-00 00:00:00"';

						$this->form->db_where_set_sql($where_sql);

					}

					$this->form->init();

				}
				return $this->form;
			}

		//--------------------------------------------------
		// Reset

			public function reset() {

				$this->order_id = NULL;
				$this->order_data = NULL;

			}

		//--------------------------------------------------
		// Select

			public function selected() {
				return ($this->order_id !== NULL);
			}

			public function select_open() {

				$selected = $this->select_by_ref(session::get('order_ref'));

				if ($selected && $this->order_data['payment_received'] != '0000-00-00 00:00:00') {
					$this->reset();
				}

				return ($this->order_id !== NULL);

			}

			public function select_paid() {

				$selected = $this->select_by_ref(session::get('order_ref'));

				if ($selected && $this->order_data['payment_received'] == '0000-00-00 00:00:00') {
					$this->reset();
				}

				return ($this->order_id !== NULL);

			}

			public function select_by_ref($ref) {

				if (preg_match('/^([0-9]+)-([a-z]{5})$/', $ref, $matches)) {
					$this->select_by_id($matches[1], $matches[2]);
				} else {
					$this->reset();
				}

				return ($this->order_id !== NULL);

			}

			public function select_by_id($id, $pass = NULL) {

				$db = $this->db_get();

				$where_sql = '
					id = "' . $db->escape($id) . '" AND
					deleted = "0000-00-00 00:00:00"';

				if ($pass !== NULL || config::get('order.user_privileged', false) !== true) {
					$where_sql .= ' AND pass = "' . $db->escape($pass) . '"';
				}

				$fields_sql = array();
				foreach (array_merge(array('pass', 'created', 'payment_received'), $this->order_fields) as $field) {
					$fields_sql[] = $db->escape_field($field);
				}

				$db->query('SELECT
								' . implode(', ', $fields_sql) . '
							FROM
								' . $db->escape_field($this->db_table_main) . '
							WHERE
								' . $where_sql);

				if ($row = $db->fetch_row()) {
					$this->order_id = $id;
					$this->order_data = $row;
				} else {
					$this->reset();
				}

				return ($this->order_id !== NULL);

			}

		//--------------------------------------------------
		// Values

			public function values_set($values) {

				//--------------------------------------------------
				// Create order

					if ($this->order_id === NULL) {
						$this->create();
					}

				//--------------------------------------------------
				// Update

					$db = $this->db_get();

					$values['edited'] = date('Y-m-d H:i:s');

					$where_sql = '
						id = "' . $db->escape($this->order_id) . '" AND
						deleted = "0000-00-00 00:00:00"';

					$db->update($this->db_table_main, $values, $where_sql);

				//--------------------------------------------------
				// Local cache

					foreach ($values as $name => $value) {
						if (isset($this->order_data[$name])) {
							$this->order_data[$name] = $value;
						}
					}

				//--------------------------------------------------
				// Order update

					$this->order_update();

			}

			public function values_get($fields = NULL) {

				//--------------------------------------------------
				// Create order

					if ($this->order_id === NULL) {
						$this->create();
					}

					if (!is_array($fields) && $fields !== NULL) {
						exit_with_error('Fields list should be an array');
					}

				//--------------------------------------------------
				// Return

					$db = $this->db_get();

					$where_sql = '
						id = "' . $db->escape($this->order_id) . '" AND
						deleted = "0000-00-00 00:00:00"';

					$db->select($this->db_table_main, $fields, $where_sql, 1);

					if ($row = $db->fetch_row()) {
						return $row;
					} else {
						return false;
					}

			}

		//--------------------------------------------------
		// Save functionality (e.g. checkout form)

			public function save() {

				//--------------------------------------------------
				// Create order

					if ($this->order_id === NULL) {
						$this->create();
					}

				//--------------------------------------------------
				// Validation

					$this->validate_save();

					$form = $this->form_get();

					if (!$form->valid()) {
						return false;
					}

				//--------------------------------------------------
				// Update

					$values = $form->data_db_get();

					if (count($values) > 0) {
						$this->values_set($values);
					}

				//--------------------------------------------------
				// Success

					return true;

			}

			public function validate_save() {

				//--------------------------------------------------
				// Form reference

					$form = $this->form_get();

				//--------------------------------------------------
				// Optionally required fields

					if ($form->field_exists('delivery_different') && $form->field_get('delivery_different')->value_get() == 'true') {

						if ($form->field_exists('delivery_name') && $form->field_get('delivery_name')->value_get() == '') {
							$form->field_get('delivery_name')->error_add('Your delivery name is required.');
						}

						if ($form->field_exists('delivery_address_1') && $form->field_get('delivery_address_1')->value_get() == '') {
							$form->field_get('delivery_address_1')->error_add('Your delivery address line 1 is required.');
						}

						if ($form->field_exists('delivery_town_city') && $form->field_get('delivery_town_city')->value_get() == '') {
							$form->field_get('delivery_town_city')->error_add('Your delivery town or city is required.');
						}

						if ($form->field_exists('delivery_postcode') && $form->field_get('delivery_postcode')->value_get() == '') {
							$form->field_get('delivery_postcode')->error_add('Your delivery postcode is required.');
						}

						if ($form->field_exists('delivery_country') && $form->field_get('delivery_country')->value_get() == '') {
							$form->field_get('delivery_country')->error_add('Your delivery country is required.');
						}

						if ($form->field_exists('delivery_telephone') && $form->field_get('delivery_telephone')->value_get() == '') {
							$form->field_get('delivery_telephone')->error_add('Your delivery telephone number is required.');
						}

					}

			}



		//--------------------------------------------------
		// Items

			public function item_add($details = NULL) {

				//--------------------------------------------------
				// Validation

					if ($this->order_id === NULL) {
						$this->create();
					}

					if (!is_array($details)) {
						exit_with_error('When using item_add on an order, you must pass in an array.');
					}

					if (!isset($details['price'])) {
						exit_with_error('When using item_add on an order, you must supply the price.');
					}

				//--------------------------------------------------
				// Insert

					$db = $this->db_get();

					$values = array_merge(array(
							'quantity' => 1,
						), $details, array(
							'id' => '',
							'order_id' => $this->order_id,
							'type' => 'item',
							'created' => date('Y-m-d H:i:s'),
							'deleted' => '0000-00-00 00:00:00',
						));

					if ($values['quantity'] > 0) {

						$db->insert($this->db_table_item, $values);

						$id = $db->insert_id();

					} else {

						$id = NULL;

					}

				//--------------------------------------------------
				// Order update

					if ($id !== NULL) {

						$this->order_items = NULL;

						$this->order_update();

					}

				//--------------------------------------------------
				// Return

					return $id;

			}

			public function items_update() {

				//--------------------------------------------------
				// Order not selected

					if ($this->order_id === NULL) {
						return;
					}

				//--------------------------------------------------
				// Changes

					$changed = false;

				//--------------------------------------------------
				// Delete link

					$delete_id = request('item_delete');
					if ($delete_id !== NULL) {
						if ($this->_item_quantity_set($delete_id, 0)) {
							$changed = true;
						}
					}

				//--------------------------------------------------
				// Select fields

					foreach ($this->items_get() as $item) {
						$quantity = request('item_quantity_' . $item['id']);
						if ($quantity !== NULL) {
							if ($this->_item_quantity_set($item['id'], $quantity)) {
								$changed = true;
							}
						}
					}

				//--------------------------------------------------
				// Order update

					if ($changed) {

						$this->order_items = NULL;

						$this->order_update();

					}

			}

			public function item_quantity_set($item_id, $quantity) {

				//--------------------------------------------------
				// Check

					if ($this->order_id === NULL) {
						exit_with_error('An order needs to be selected', 'item_quantity_set');
					}

				//--------------------------------------------------
				// Update

					$changed = $this->_item_quantity_set($item['id'], $quantity);

				//--------------------------------------------------
				// Order update

					if ($changed) {

						$this->order_items = NULL;

						$this->order_update();

					}

			}

			protected function _item_quantity_set($item_id, $quantity) {

				//--------------------------------------------------
				// Update

					$db = $this->db_get();

					if ($quantity <= 0) {

						//--------------------------------------------------
						// Simple delete

							$db->query('UPDATE
											' . $db->escape_field($this->db_table_item) . ' AS oi
										SET
											oi.deleted = "' . $db->escape(date('Y-m-d H:i:s')) . '"
										WHERE
											oi.id = "' . $db->escape($item_id) . '" AND
											oi.order_id = "' . $db->escape($this->order_id) . '" AND
											oi.type = "item" AND
											oi.deleted = "0000-00-00 00:00:00"');

					} else {

						//--------------------------------------------------
						// Deleted backup (with new ID)

							$sql = 'SELECT
										*
									FROM
										' . $db->escape_field($this->db_table_item) . ' AS oi
									WHERE
										oi.id = "' . $db->escape($item_id) . '" AND
										oi.order_id = "' . $db->escape($this->order_id) . '" AND
										oi.type = "item" AND
										oi.deleted = "0000-00-00 00:00:00"';

							if ($row = $db->fetch($sql)) {

								if ($row['quantity'] == $quantity) {
									return false; // No change
								}

								$row['id'] = '';
								$row['deleted'] = date('Y-m-d H:i:s');

								$db->insert($this->db_table_item, $row);

							} else {

								exit_with_error('Cannot find item "' . $item_id . '" in the order "' . $this->order_id . '"');

							}

						//--------------------------------------------------
						// Update the quantity

							$db->query('UPDATE
											' . $db->escape_field($this->db_table_item) . ' AS oi
										SET
											oi.quantity = "' . $db->escape($quantity) . '"
										WHERE
											oi.id = "' . $db->escape($item_id) . '" AND
											oi.type = "item" AND
											oi.order_id = "' . $db->escape($this->order_id) . '" AND
											oi.deleted = "0000-00-00 00:00:00"');

					}

				//--------------------------------------------------
				// Success

					return true;

			}

			public function items_count_get() {
				$items = 0;
				foreach ($this->items_get() as $item) { // In most cases items_get() is used elsewhere (cached data)... so usually quicker than doing an extra 'SELECT SUM(quantity)'
					$items += $item['quantity'];
				}
				return $items;
			}

			public function items_get() {

				//--------------------------------------------------
				// Order not open yet

					if ($this->order_id === NULL) {
						return array();
					}

				//--------------------------------------------------
				// Cached values

					if ($this->order_items) {
						return $this->order_items;
					}

				//--------------------------------------------------
				// Tax details

					$tax_applied = in_array('item', $this->tax_types_get());
					$tax_included = in_array('item', $this->tax_included_get());
					$tax_percent = $this->tax_percent_get();

					$tax_ratio = (1 + ($tax_percent / 100));

				//--------------------------------------------------
				// Query

					$items = array();

					$db = $this->db_get();

					$sql = 'SELECT
								*
							FROM
								' . $db->escape_field($this->db_table_item) . ' AS oi
							WHERE
								oi.order_id = "' . $db->escape($this->order_id) . '" AND
								oi.type = "item" AND
								oi.deleted = "0000-00-00 00:00:00"';

					foreach ($db->fetch_all($sql) as $row) {

						//--------------------------------------------------
						// Details

							$details = $row;
							unset($details['deleted']);
							unset($details['order_id']);

						//--------------------------------------------------
						// Price details

							if (!$tax_applied) { // Very unlikely

								$details['price_net']   = round(($row['price']), 2);
								$details['price_tax']   = 0;
								$details['price_gross'] = $details['price_net'];

							} else if ($tax_included) {

								$details['price_tax']   = round(($row['price'] - ($row['price'] / $tax_ratio)), 2);
								$details['price_gross'] = round(($row['price']), 2);
								$details['price_net']   = round(($details['price_gross'] - $details['price_tax']), 2);

							} else {

								$details['price_net']   = round(($row['price']), 2);
								$details['price_tax']   = round((($details['price_net'] / 100) * $tax_percent), 2);
								$details['price_gross'] = round(($details['price_net'] + $details['price_tax']), 2);

							}

						//--------------------------------------------------
						// Store

							$items[$row['id']] = $details;

					}

				//--------------------------------------------------
				// Return

					$this->order_items = $items;

					return $items;

			}

		//--------------------------------------------------
		// Current basket

			protected function delivery_price_get() {
				return 0;
			}

			public function currency_get() {
				return $this->order_currency;
			}

			public function currency_char_get() {
				$currency = $this->currency_get();
				if ($currency == 'GBP') return '£';
			}

			public function tax_percent_get() {
				return config::get('order.tax_percent', 20);
			}

			public function tax_types_get() {
				return config::get('order.tax_types', array(
						'item',
					));
			}

			public function tax_included_get() {
				return config::get('order.tax_included', array(
						'item',
					));
			}

			public function totals_get() {

				//--------------------------------------------------
				// Tax details

					$tax_types = $this->tax_types_get();
					$tax_included = $this->tax_included_get();
					$tax_percent = $this->tax_percent_get();

					$tax_ratio = (1 + ($tax_percent / 100));

				//--------------------------------------------------
				// Defaults

					$db = $this->db_get();

					$return = array(
							'items' => array(),
							'amount' => array(
									'net' => 0,
									'tax' => 0,
									'gross' => 0,
								),
							'tax' => array(
									'percent' => $tax_percent,
									'types' => $tax_types,
									'included' => $tax_included,
								),
						);

					foreach ($db->enum_values($this->db_table_item, 'type') as $type) {

						$return['items'][$type] = array(
								'net' => 0,
								'tax' => 0,
								'gross' => 0,
							);

					}

				//--------------------------------------------------
				// Items

					$order_items = $this->items_get();

					foreach ($order_items as $item) {

						$return['items']['item']['net']   += ($item['price_net']   * $item['quantity']);
						$return['items']['item']['tax']   += ($item['price_tax']   * $item['quantity']);
						$return['items']['item']['gross'] += ($item['price_gross'] * $item['quantity']);

					}

					$totals = $return['items']['item'];

				//--------------------------------------------------
				// Other items (e.g. delivery)

					$sql = 'SELECT
								oi.type,
								SUM(oi.price * oi.quantity) AS total
							FROM
								' . $db->escape_field($this->db_table_item) . ' AS oi
							WHERE
								oi.order_id = "' . $db->escape($this->order_id) . '" AND
								oi.type != "item" AND
								oi.deleted = "0000-00-00 00:00:00"
							GROUP BY
								oi.type';

					foreach ($db->fetch_all($sql) as $row) {

						$taxed = in_array($type, $tax_types);

						if (!$taxed) {

							$total_net   = round($row['total'], 2);
							$total_tax   = 0;
							$total_gross = $total_net;

						} else if (in_array($type, $tax_included)) {

							$total_tax   = round(($row['total'] - ($row['total'] / $tax_ratio)), 2);
							$total_gross = round(($row['total']), 2);
							$total_net   = round(($total_gross - $total_tax), 2);

						} else {

							$total_net   = round(($row['total']), 2);
							$total_tax   = round((($total_net / 100) * $tax_percent), 2);
							$total_gross = round(($total_net + $total_tax), 2);

						}

						$return['items'][$row['type']]['net'] += $total_net;
						$return['items'][$row['type']]['tax'] += $total_tax;
						$return['items'][$row['type']]['gross'] += $total_gross;

						if ($taxed) {
							$totals['net'] += $total_net;
							$totals['tax'] += $total_tax;
						}

						$totals['gross'] += $total_gross;

					}

				//--------------------------------------------------
				// Round amounts

					$return['amount']['net'] = round($totals['net'], 2);
					$return['amount']['tax'] = round($totals['tax'], 2);
					$return['amount']['gross'] = round($totals['gross'], 2);

				//--------------------------------------------------
				// Return

					return $return;

			}

		//--------------------------------------------------
		// Events

			public function payment_received($values) {

				//--------------------------------------------------
				// Details

					if ($this->order_id === NULL) {
						exit_with_error('An order needs to be selected', 'payment_received');
					}

				//--------------------------------------------------
				// Store

					if (!is_array($values)) {
						$values = array();
					}

					$this->values_set(array_merge($values, array(
							'payment_received' => date('Y-m-d H:i:s'),
						)));

				//--------------------------------------------------
				// Customer email

					$this->_email_customer('order-payment-received');

			}

			public function payment_settled($values = NULL) {

				//--------------------------------------------------
				// Details

					if ($this->order_id === NULL) {
						exit_with_error('An order needs to be selected', 'payment_settled');
					}

				//--------------------------------------------------
				// Store

					if (!is_array($values)) {
						$values = array();
					}

					$this->values_set(array_merge($values, array(
							'payment_settled' => date('Y-m-d H:i:s'),
						)));

				//--------------------------------------------------
				// Customer email

					$this->_email_customer('order-payment-settled');

			}

			public function dispatched($values = NULL) {

				//--------------------------------------------------
				// Details

					if ($this->order_id === NULL) {
						exit_with_error('An order needs to be selected', 'dispatched');
					}

				//--------------------------------------------------
				// Store

					if (!is_array($values)) {
						$values = array();
					}

					$this->values_set(array_merge($values, array(
							'dispatched' => date('Y-m-d H:i:s'),
						)));

				//--------------------------------------------------
				// Customer email

					$this->_email_customer('order-dispatched');

			}

		//--------------------------------------------------
		// Tables

			public function table_get_html($config = NULL) {

				$table = $this->table_get();

				return $table->table_get_html($config);

			}

			public function table_get_text() {

				$table = $this->table_get();

				return $table->table_get_text();

			}

		//--------------------------------------------------
		// Create

			protected function create($defaults = NULL) {

				//--------------------------------------------------
				// Details

					if ($this->order_id !== NULL) {
						exit_with_error('Cannot create a new order when one is already selected (' . $this->order_id . ')');
					}

				//--------------------------------------------------
				// Order values

					$order_pass = '';
					for ($k=0; $k<5; $k++) {
						$order_pass .= chr(mt_rand(97,122));
					}

					if (!is_array($defaults)) {
						$defaults = array();
					}

					$date = date('Y-m-d H:i:s');

					$values = array_merge(array(
							'id' => '',
							'pass' => $order_pass,
							'ip' => config::get('request.ip'),
							'created' => $date,
						), $defaults);

				//--------------------------------------------------
				// Insert

					$db = $this->db_get();
					$db->insert($this->db_table_main, $values);

					$this->order_id = $db->insert_id();

				//--------------------------------------------------
				// Store

					$this->order_data = array();

					foreach ($this->order_fields as $field) {
						$this->order_data[$field] = NULL;
					}

					$this->order_data['pass'] = $order_pass;
					$this->order_data['created'] = $date;
					$this->order_data['payment_received'] = '0000-00-00 00:00:00';

					session::set('order_ref', $this->ref_get());

			}

		//--------------------------------------------------
		// Order update

			protected function order_update() {

				//--------------------------------------------------
				// Delivery

					//--------------------------------------------------
					// Current delivery price

						$delivery_price = $this->delivery_price_get();

					//--------------------------------------------------
					// No change with the 1 record (if more, still replace)

						$db = $this->db_get();

						$sql = 'SELECT
									oi.price
								FROM
									' . $db->escape_field($this->db_table_item) . ' AS oi
								WHERE
									oi.order_id = "' . $db->escape($this->order_id) . '" AND
									oi.type = "delivery" AND
									oi.deleted = "0000-00-00 00:00:00"';

						$delivery = $db->fetch_all($sql);

						if (count($delivery) == 1 && round($delivery[0]['price'], 2) == round($delivery_price, 2)) {
							return;
						}

					//--------------------------------------------------
					// Replace delivery record

						$db->query('UPDATE
										' . $db->escape_field($this->db_table_item) . ' AS oi
									SET
										oi.deleted = "' . $db->escape(date('Y-m-d H:i:s')) . '"
									WHERE
										oi.order_id = "' . $db->escape($this->order_id) . '" AND
										oi.type = "delivery" AND
										oi.deleted = "0000-00-00 00:00:00"');

						$db->insert($this->db_table_item, array(
								'id' => '',
								'order_id' => $this->order_id,
								'type' => 'delivery',
								'price' => $delivery_price,
								'quantity' => 1,
								'created' => date('Y-m-d H:i:s'),
								'deleted' => '0000-00-00 00:00:00',
							));

			}

		//--------------------------------------------------
		// Emails

			private function _email_customer($template) {

				//--------------------------------------------------
				// Does the template exist

					if (!is_dir(PUBLIC_ROOT . '/a/email/' . safe_file_name($template))) {
						return false;
					}

				//--------------------------------------------------
				// Build email

					$email = new email();
					$email->subject_default_set(link_to_human($template)); // Include a <title> in the html version of the email to override.
					$email->template_set($template);

				//--------------------------------------------------
				// Order details

					$order_details = $this->values_get();

					foreach ($order_details as $field => $value) {
						$email->template_value_set(strtoupper($field), $value);
					}

				//--------------------------------------------------
				// Order table

					$url_prefix = https_url('/');
					if (substr($url_prefix, -1) == '/') {
						$url_prefix = substr($url_prefix, 0, -1);
					}

					$config = array(
							'email_mode' => true,
							'url_prefix' => $url_prefix, // Images and links get full url
						);

					$table = $this->table_get();

					$email->template_value_set_text('TABLE', $table->table_get_text($config));
					$email->template_value_set_html('TABLE', $table->table_get_html($config));

				//--------------------------------------------------
				// Send to customer

					$email->send($order_details['email']);

			}

	}

?>