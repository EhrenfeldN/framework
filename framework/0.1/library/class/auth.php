<?php

		// Notes:
		// - Add a registration table (http://www.troyhunt.com/2015/01/introducing-secure-account-management.html)
		// - Verify email address on register, but also on email address change?
		// - Add a remember me table (https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence)
		// - Use encryption to the stored db hash (like a pepper, https://github.com/paragonie/password_lock).

	class auth_base extends check {

		//--------------------------------------------------
		// Variables

			protected $lockout_attempts = 10;
			protected $lockout_timeout = 1800; // 30 minutes
			protected $lockout_mode = NULL;

			protected $session_name = 'user'; // Allow different user log-in mechanics, e.g. "admin"
			protected $session_info = NULL;
			protected $session_pass = NULL;
			protected $session_hash = 'sha256'; // Using CRYPT_BLOWFISH would make page loading too slow (good for login though)
			protected $session_length = 1800; // 30 minutes, or set to 0 for indefinite length
			protected $session_ip_lock = false; // By default this is disabled (AOL users)
			protected $session_concurrent = false; // Close previous sessions on new session start
			protected $session_cookies = true; // Use sessions by default
			protected $session_history = 2592000; // Keep session data for 30 days, 0 to delete once expired, -1 to keep data indefinitely

			protected $identification_type = 'email'; // Or 'username'
			protected $identification_max_length = NULL;

			protected $username_max_length = 30;
			protected $email_max_length = 100;
			protected $password_min_length = 6; // A balance between security and usability.
			protected $password_max_length = 250; // Bcrypt truncates to 72 characters anyway.

			protected $text = array();

			protected $db_link = NULL;
			protected $db_table = array();
			protected $db_fields = array();
			protected $db_where_sql = array();

			protected $logout_details = NULL;

			protected $login_field_identification = NULL;
			protected $login_field_password = NULL;
			protected $login_last_cookie = 'u'; // Or set to NULL to not remember.
			protected $login_details = NULL;

			protected $register_field_identification = NULL;
			protected $register_field_password_1 = NULL;
			protected $register_field_password_2 = NULL;
			protected $register_details = NULL;

			protected $update_field_identification = NULL;
			protected $update_field_password_old = NULL;
			protected $update_field_password_new_required = false;
			protected $update_field_password_new_1 = NULL;
			protected $update_field_password_new_2 = NULL;
			protected $update_details = NULL;

			protected $reset_field_email = NULL;
			protected $reset_field_password_1 = NULL;
			protected $reset_field_password_2 = NULL;
			protected $reset_details = NULL;

		//--------------------------------------------------
		// Setup

			public function __construct() {
				$this->setup();
			}

			protected function setup() {

				//--------------------------------------------------
				// Session length

					if (!$this->session_cookies && (ini_get('session.gc_maxlifetime') / $this->session_length) < 0.5) { // Default server gc lifetime is 24 minutes (over 50% of 30 minutes)
						exit_with_error('Session max lifetime is too short for user session length, perhaps use cookies instead?');
					}

				//--------------------------------------------------
				// Text

					$default_text = array(

							'identification_label'           => 'Email',
							'identification_min_len'         => 'Your email address is required.',
							'identification_max_len'         => 'Your email address cannot be longer than XXX characters.',
							'identification_format'          => 'Your email address does not appear to be correct.',

							'password_label'                 => 'Password',
							'password_old_label'             => 'Current password',
							'password_new_label'             => 'New password',
							'password_min_len'               => 'Your password must be at least XXX characters.',
							'password_max_len'               => 'Your password cannot be longer than XXX characters.',

							'password_repeat_label'          => 'Repeat password',
							'password_repeat_min_len'        => 'Your password confirmation is required.',
							'password_repeat_max_len'        => 'Your password confirmation cannot be longer than XXX characters.',

							'failure_login_details'          => 'Incorrect log-in details.',
							'failure_login_identification'   => NULL, // Do not use, except for very special situations (e.g. low security and overly user friendly).
							'failure_login_password'         => NULL,
							'failure_login_repetition'       => 'Too many login attempts.',
							'failure_identification_current' => 'The email address supplied is already in use.',
							'failure_password_current'       => 'Your current password is incorrect.', // Used on profile page
							'failure_password_repeat'        => 'Your new passwords do not match.', // Register and profile pages
							'failure_reset_identification'   => 'Your email address has not been recognised.',
							'failure_reset_changed'          => 'Your account has already had its password changed recently.',
							'failure_reset_requested'        => 'You have recently requested a password reset.',
							'failure_reset_token'            => 'The link to reset your password is incorrect or has expired.',

						);

					$default_text['email_label'] = $default_text['identification_label']; // For the password reset page
					$default_text['email_min_len'] = $default_text['identification_min_len'];
					$default_text['email_max_len'] = $default_text['identification_max_len'];
					$default_text['email_format'] = $default_text['identification_format'];

					if ($this->identification_type == 'username') {

						$default_text['identification_label'] = 'Username';
						$default_text['identification_min_len'] = 'Your username is required.';
						$default_text['identification_max_len'] = 'Your username cannot be longer than XXX characters.';

						$default_text['failure_identification_current'] = 'The username supplied is already in use.';
						$default_text['failure_reset_identification'] = 'Your username has not been recognised.';

					}

					$this->text = array_merge($default_text, $this->text); // TODO: Maybe $this->messages_html ?

					if (!$this->text['failure_login_identification']) $this->text['failure_login_identification'] = $this->text['failure_login_details'];
					if (!$this->text['failure_login_password'])       $this->text['failure_login_password']       = $this->text['failure_login_details'];

				//--------------------------------------------------
				// Tables

					$this->db_table = array(
							'main'     => DB_PREFIX . 'user',
							'session'  => DB_PREFIX . 'user_session',
							'password' => DB_PREFIX . 'user_password',
						);

					$this->db_where_sql = array(
							'main'       => 'm.deleted = "0000-00-00 00:00:00"',
							'main_login' => 'true', // e.g. to block inactive users during login
							'session'    => 's.deleted = "0000-00-00 00:00:00"',
							'password'   => 'true',
						);

					$this->db_fields = array(
							'main' => array(
									'id'             => 'id',
									'identification' => 'email',
									'password'       => 'pass',
									'created'        => 'created',
									'edited'         => 'edited',
									'deleted'        => 'deleted',
								),
						);

					if ($this->identification_type == 'username') {

						$this->db_fields['main']['identification'] = 'username';

						$this->identification_max_length = $this->username_max_length;

					} else {

						$this->identification_max_length = $this->email_max_length;

					}

					if (config::get('debug.level') > 0) {

						debug_require_db_table($this->db_table['main'], '
								CREATE TABLE [TABLE] (
									id int(11) NOT NULL AUTO_INCREMENT,
									' . $this->db_fields['main']['identification'] . ' varchar(' . $this->identification_max_length . ') NOT NULL,
									pass tinytext NOT NULL,
									created datetime NOT NULL,
									edited datetime NOT NULL,
									deleted datetime NOT NULL,
									PRIMARY KEY (id),
									UNIQUE KEY ' . $this->db_fields['main']['identification'] . ' (' . $this->db_fields['main']['identification'] . ')
								);');

						debug_require_db_table($this->db_table['session'], '
								CREATE TABLE [TABLE] (
									id int(11) NOT NULL AUTO_INCREMENT,
									pass tinytext NOT NULL,
									user_id int(11) NOT NULL,
									ip tinytext NOT NULL,
									browser tinytext NOT NULL,
									logout_csrf tinytext NOT NULL,
									created datetime NOT NULL,
									last_used datetime NOT NULL,
									request_count int(11) NOT NULL,
									deleted datetime NOT NULL,
									PRIMARY KEY (id),
									KEY user_id (user_id)
								);');

						// Password reset feature not always used

					}

			}

			public function db_get() {
				if ($this->db_link === NULL) {
					$this->db_link = db_get();
				}
				return $this->db_link;
			}

			public function db_table_get($name = 'main') {
				if (isset($this->db_table[$name])) {
					return $this->db_table[$name];
				} else {
					exit_with_error('Unrecognised table "' . $name . '"');
				}
			}

		//--------------------------------------------------
		// Session

			public function session_get($config = array()) {
				if ($this->session_info === NULL) {

					//--------------------------------------------------
					// Config

						$config = array_merge(array(
								'fields' => array(),
								'auth_token' => NULL,
							), $config);

					//--------------------------------------------------
					// Get session details

						if ($config['auth_token'] === NULL) {

							if ($this->session_cookies) {
								$session_id = cookie::get($this->session_name . '_id');
								$session_pass = cookie::get($this->session_name . '_pass');
							} else {
								$session_id = session::get($this->session_name . '_id');
								$session_pass = session::get($this->session_name . '_pass');
							}

						} else {

							if (preg_match('/^([0-9]+)-(.+)$/', $config['auth_token'], $matches)) {
								$session_id = $matches[1];
								$session_pass = $matches[2];
							} else {
								$session_id = 0;
								$session_pass = '';
							}

						}

						$session_id = intval($session_id);

					//--------------------------------------------------
					// If set, test

						if ($session_id > 0) {

							$db = $this->db_get();

							$fields_sql = array('s.pass', 's.user_id', 's.ip', 's.logout_csrf');
							foreach ($config['fields'] as $field) {
								$fields_sql[] = 'm.' . $db->escape_field($field);
							}
							$fields_sql = implode(', ', $fields_sql);

							$where_sql = '
								s.id = "' . $db->escape($session_id) . '" AND
								s.pass != "" AND
								' . $this->db_where_sql['session'] . ' AND
								' . $this->db_where_sql['main'];

							if ($this->session_length > 0) {
								$last_used = new timestamp((0 - $this->session_length) . ' seconds');
								$where_sql .= ' AND' . "\n\t\t\t\t\t\t\t\t\t" . 's.last_used > "' . $db->escape($last_used) . '"';
							}

							$sql = 'SELECT
										' . $fields_sql . '
									FROM
										' . $db->escape_table($this->db_table['session']) . ' AS s
									LEFT JOIN
										' . $db->escape_table($this->db_table['main']) . ' AS m ON m.id = s.user_id
									WHERE
										' . $where_sql;

							if ($row = $db->fetch_row($sql)) {

								$ip_test = ($this->session_ip_lock == false || config::get('request.ip') == $row['ip']);

								if ($ip_test && $row['pass'] != '' && hash($this->session_hash, $session_pass) == $row['pass']) {

									//--------------------------------------------------
									// Update the session - keep active

										$now = new timestamp();

										$request_mode = config::get('output.mode');
										if (($request_mode === 'asset') || ($request_mode === 'gateway' && in_array(config::get('output.gateway'), array('framework-file', 'js-code', 'js-newrelic')))) {
											$request_increment = 0;
										} else {
											$request_increment = 1;
										}

										$db->query('UPDATE
														' . $db->escape_table($this->db_table['session']) . ' AS s
													SET
														s.last_used = "' . $db->escape($now) . '",
														s.request_count = (s.request_count + ' . intval($request_increment) . ')
													WHERE
														s.id = "' . $db->escape($session_id) . '" AND
														' . $this->db_where_sql['session']);

									//--------------------------------------------------
									// Update the cookies - if used

										if ($config['auth_token'] === NULL && $this->session_cookies && config::get('output.mode') === NULL) { // Not a gateway/maintenance/asset script

											$cookie_age = new timestamp($this->session_length . ' seconds');

											cookie::set($this->session_name . '_id', $session_id, $cookie_age);
											cookie::set($this->session_name . '_pass', $session_pass, $cookie_age);

										}

									//--------------------------------------------------
									// Session info

										$row['id'] = $session_id;

										unset($row['ip']);
										unset($row['pass']); // The hashed version

										$this->session_info = $row;
										$this->session_pass = $session_pass;

								}

							}

						}

				}
				return $this->session_info;
			}

			public function session_required($login_url) {
				if ($this->session_info === NULL) {
					save_request_redirect($login_url, $this->login_last_get());
				}
			}

			public function session_user_id_get() {
				if ($this->session_info !== NULL) {
					return $this->session_info['user_id'];
				} else {
					return NULL;
				}
			}

			public function session_id_get() {
				if ($this->session_info !== NULL) {
					return $this->session_info['id'];
				} else {
					return NULL;
				}
			}

			public function session_token_get() {
				if ($this->session_info !== NULL) {
					return $this->session_info['id'] . '-' . $this->session_pass;
				} else {
					return NULL;
				}
			}

			protected function session_start($user_id) { // See the login_* or register_* functions (don't call directly)

				//--------------------------------------------------
				// Config

					$db = $this->db_get();

					$now = new timestamp();

				//--------------------------------------------------
				// Process previous sessions

					if ($this->session_concurrent !== true) {
						if ($this->session_history == 0) {

							$db->query('DELETE FROM
											s
										USING
											' . $db->escape_table($this->db_table['session']) . ' AS s
										WHERE
											s.user_id = "' . $db->escape($user_id) . '" AND
											' . $this->db_where_sql['session']);

						} else {

							$db->query('UPDATE
											' . $db->escape_table($this->db_table['session']) . ' AS s
										SET
											s.deleted = "' . $db->escape($now) . '"
										WHERE
											s.user_id = "' . $db->escape($user_id) . '" AND
											' . $this->db_where_sql['session']);

						}
					}

				//--------------------------------------------------
				// Delete old sessions (house cleaning)

					if ($this->session_history >= 0) { // TODO: Check usage (inc session start and logout)

						$deleted_before = new timestamp((0 - $this->session_history) . ' seconds');

						$db->query('DELETE FROM
										s
									USING
										' . $db->escape_table($this->db_table['session']) . ' AS s
									WHERE
										s.deleted != "0000-00-00 00:00:00" AND
										s.deleted < "' . $db->escape($deleted_before) . '"');

						if ($this->session_length > 0) {

							$last_used = new timestamp((0 - $this->session_length - $this->session_history) . ' seconds');

							$db->query('DELETE FROM
											s
										USING
											' . $db->escape_table($this->db_table['session']) . ' AS s
										WHERE
											s.last_used < "' . $db->escape($last_used) . '" AND
											' . $this->db_where_sql['session']);

						}

					}

				//--------------------------------------------------
				// Session pass

					$session_pass = random_key(40);

				//--------------------------------------------------
				// Create a new session

					$db->insert($this->db_table['session'], array(
							'pass'          => hash($this->session_hash, $session_pass), // Must be a quick hash for fast page loading time.
							'user_id'       => $user_id,
							'ip'            => config::get('request.ip'),
							'browser'       => config::get('request.browser'),
							'logout_csrf'   => random_key(15), // Different to csrf_token_get() as this is typically printed on every page in a simple logout link (and its value may be exposed in a referrer header after logout).
							'created'       => $now,
							'last_used'     => $now,
							'request_count' => 0,
							'deleted'       => '0000-00-00 00:00:00',
						));

					$session_id = $db->insert_id();

				//--------------------------------------------------
				// Store

					if ($this->session_cookies) {

						$cookie_age = new timestamp($this->session_length . ' seconds');

						cookie::set($this->session_name . '_id', $session_id, $cookie_age);
						cookie::set($this->session_name . '_pass', $session_pass, $cookie_age);

					} else {

						session::regenerate(); // State change, new session id (additional check against session fixation)
						session::set($this->session_name . '_id', $session_id);
						session::set($this->session_name . '_pass', $session_pass); // Password support still used so an "auth_token" can be passed to the user.

					}

				//--------------------------------------------------
				// Change the CSRF token, invalidating forms open in
				// different browser tabs (or browser history).

					csrf_token_change();

			}

		//--------------------------------------------------
		// Logout

			public function logout_url_get($logout_url = NULL) {
				if ($this->session_info) {
					$logout_url = url($logout_url, array('csrf' => $this->session_info['logout_csrf']));
				}
				return $logout_url; // Never return NULL, the logout page should always be linked to (even it it only shows an error).
			}

			public function logout_validate() {

				//--------------------------------------------------
				// Config

					$this->logout_details = false;

				//--------------------------------------------------
				// Validate the logout CSRF token.

					$csrf_get = request('csrf', 'GET');

					if ($this->session_info && $this->session_info['logout_csrf'] === $csrf_get) {

						$this->logout_details = array(
								'csrf' => $csrf_get,
							);

						return true;

					}

				//--------------------------------------------------
				// Failure

					return false;

			}

			public function logout_complete() {

				//--------------------------------------------------
				// Config

					if ($this->logout_details === NULL) {
						exit_with_error('You must call auth::logout_validate() before auth::logout_complete().');
					}

					if (!$this->logout_details) {
						exit_with_error('The logout details are not valid, so why has auth::logout_complete() been called?');
					}

				//--------------------------------------------------
				// Delete the current session

					$db = $this->db_get();

// TODO: Test

					if ($this->session_history == 0) {

						$db->query('DELETE FROM
										s
									USING
										' . $db->escape_table($this->db_table['session']) . ' AS s
									WHERE
										s.id = "' . $db->escape($this->session_info['id']) . '" AND
										' . $this->db_where_sql['session']);

					} else {

						$now = new timestamp();

						$db->query('UPDATE
										' . $db->escape_table($this->db_table['session']) . ' AS s
									SET
										s.deleted = "' . $db->escape($now) . '"
									WHERE
										s.id = "' . $db->escape($this->session_info['id']) . '" AND
										' . $this->db_where_sql['session']);

					}

				//--------------------------------------------------
				// Be nice, and cleanup (not necessary)

					if ($this->session_cookies) {
						cookie::delete($this->session_name . '_id');
						cookie::delete($this->session_name . '_pass');
					} else {
						session::regenerate(); // State change, new session id
						session::delete($this->session_name . '_id');
						session::delete($this->session_name . '_pass');
					}

				//--------------------------------------------------
				// Change the CSRF token, invalidating forms open in
				// different browser tabs (or browser history).

					csrf_token_change();

			}

		//--------------------------------------------------
		// Login

			//--------------------------------------------------
			// Fields

				public function login_field_identification_get($form, $config = array()) {

					$field = $this->field_identification_get($form, array_merge(array(
							'label' => $this->text['identification_label'],
							'name' => 'identification',
							'max_length' => $this->identification_max_length,
							'check_domain' => false, // DNS lookups can take time.
						), $config));

					if ($form->initial()) {
						$field->value_set($this->login_last_get());
					}

					return $this->login_field_identification = $field;

				}

				public function login_field_password_get($form, $config = array()) {

					$field = $this->field_password_1_get($form, array_merge(array(
							'label' => $this->text['password_label'],
							'name' => 'password',
							'min_length' => $this->password_min_length,
							'max_length' => $this->password_max_length,
						), $config, array(
							'required' => true,
							'autocomplete' => 'current-password',
						)));

					return $this->login_field_password = $field;

				}

			//--------------------------------------------------
			// Request

				public function login_validate() {

					//--------------------------------------------------
					// Config

						$form = $this->login_field_identification->form_get();

						$identification = $this->login_field_identification->value_get();
						$password = $this->login_field_password->value_get();

						$this->login_details = NULL; // Make sure (if called more than once)

					//--------------------------------------------------
					// Validate

						if ($form->valid()) { // Basic checks such as required fields, and CSRF

							$result = $this->validate_login($identification, $password);

							if ($result === 'failure_identification') {

								$form->error_add($this->text['failure_login_identification']); // Do NOT attach to specific input field (identifies which value is wrong).

							} else if ($result === 'failure_password') {

								$form->error_add($this->text['failure_login_password']);

							} else if ($result === 'failure_repetition') {

								$form->error_add($this->text['failure_login_repetition']);

							} else if (is_string($result)) {

								$form->error_add($result); // Custom (project specific) error message.

							} else if (!is_int($result)) {

								exit_with_error('Unknown response from auth::validate_login()', $result);

							} else {

								$this->login_details = array(
										'id' => $result,
										'identification' => $identification,
										'form' => $form,
									);

								return $result; // Success (return user_id, which is true-ish)

							}

						}

					//--------------------------------------------------
					// Failure

						return false;

				}

				public function login_complete() {

					//--------------------------------------------------
					// Config

						if (!$this->login_details) {
							exit_with_error('You must call auth::login_validate() before auth::login_complete().');
						}

						if (!$this->login_details['form']->valid()) {
							exit_with_error('The form is not valid, so why has auth::login_complete() been called?');
						}

					//--------------------------------------------------
					// Remember identification

						$this->login_last_set($this->login_details['identification']);

					//--------------------------------------------------
					// Start session

						$this->session_start($this->login_details['id']);

					//--------------------------------------------------
					// Try to restore session

						save_request_restore($this->login_details['identification']);

					//--------------------------------------------------
					// Return

						return $this->login_details['id'];

				}

				public function login_last_get() {
					if ($this->login_last_cookie !== NULL) {
						return cookie::get($this->login_last_cookie);
					} else {
						return NULL;
					}
				}

				protected function login_last_set($identification) {
					if ($this->login_last_cookie !== NULL) {
						cookie::set($this->login_last_cookie, $identification, '+30 days');
					}
				}

		//--------------------------------------------------
		// Register

			//--------------------------------------------------
			// Fields

				public function register_field_identification_get($form, $config = array()) {

					$field = $this->field_identification_get($form, array_merge(array(
							'label' => $this->text['identification_label'],
							'name' => 'identification',
							'max_length' => $this->identification_max_length,
							'check_domain' => true,
						), $config));

					return $this->register_field_identification = $field;

				}

				public function register_field_password_1_get($form, $config = array()) {

					$field = $this->field_password_1_get($form, array_merge(array(
							'label' => $this->text['password_label'],
							'name' => 'password',
							'min_length' => $this->password_min_length,
							'max_length' => $this->password_max_length,
						), $config, array(
							'required' => true,
							'autocomplete' => 'new-password',
						)));

					return $this->register_field_password_1 = $field;

				}

				public function register_field_password_2_get($form, $config = array()) {

					$field = $this->field_password_2_get($form, array_merge(array(
							'label' => $this->text['password_repeat_label'],
							'name' => 'password_repeat',
							'min_length' => $this->password_min_length,
							'max_length' => $this->password_max_length,
						), $config, array(
							'required' => true,
						)));

					return $this->register_field_password_2 = $field;

				}

			//--------------------------------------------------
			// Request

				public function register_validate() {

					//--------------------------------------------------
					// Config

						$form = $this->register_field_identification->form_get();

						$identification = $this->register_field_identification->value_get();
						$password_1 = $this->register_field_password_1->value_get();
						$password_2 = $this->register_field_password_2->value_get();

						$this->register_details = NULL; // Make sure (if called more than once)

					//--------------------------------------------------
					// Validate identification

						$result = $this->validate_identification($identification, NULL);

						if ($result === 'failure_current') {

							$this->register_field_identification->error_add($this->text['failure_identification_current']);

						} else if (is_string($result)) {

							$this->register_field_identification->error_add($result); // Custom (project specific) error message

						} else if ($result !== true) {

							exit_with_error('Unknown response from auth::validate_identification()', $result);

						}

					//--------------------------------------------------
					// Validate password

						$result = $this->validate_password_new($password_1);

						if (is_string($result)) {

							$this->register_field_password_1->error_add($result); // Custom (project specific) error message

						} else if ($result !== true) {

							exit_with_error('Unknown response from auth::validate_password_new()', $result);

						} else if ($password_1 != $password_2) {

							$this->register_field_password_2->error_add($this->text['failure_password_repeat']);

						}

					//--------------------------------------------------
					// Return

						if ($form->valid()) {

// TODO: Add a separate register table, send an email, on confirmation (assuming identification is unique) the record can be copied over.
// But what about profile changes once registered?

							$this->register_details = array(
									'identification' => $identification,
									'password' => $password_1,
									'form' => $form,
								);

							return true;

						} else {

							return false;

						}

				}

				public function register_complete($config = array()) {

					//--------------------------------------------------
					// Config

						$config = array_merge(array(
								'login' => true,
							), $config);

						if (!$this->register_details) {
							exit_with_error('You must call auth::register_validate() before auth::register_complete().');
						}

						if (!$this->register_details['form']->valid()) {
							exit_with_error('The form is not valid, so why has auth::register_complete() been called?');
						}

						$form = $this->register_details['form'];

						$db = $this->db_get();

					//--------------------------------------------------
					// Register

							// TODO: Should probably use value_set on the record helper.

						$form->db_value_set($this->db_fields['main']['identification'], $this->register_details['identification']);

						$user_id = $form->db_insert();

					//--------------------------------------------------
					// Password

						$password_hash = password::hash($this->register_details['password'], $user_id);

						$db->query('UPDATE
										' . $db->escape_table($this->db_table['main']) . ' AS m
									SET
										m.' . $db->escape_field($this->db_fields['main']['password']) . ' = "' . $db->escape($password_hash) . '"
									WHERE
										m.' . $db->escape_field($this->db_fields['main']['id']) . ' = "' . $db->escape($user_id) . '" AND
										m.' . $db->escape_field($this->db_fields['main']['deleted']) . ' = "0000-00-00 00:00:00" AND
										' . $this->db_where_sql['main'] . '
									LIMIT
										1');

					//--------------------------------------------------
					// Remember identification

						$this->login_last_set($this->register_details['identification']);

					//--------------------------------------------------
					// Start session

						if ($config['login']) {
							$this->session_start($user_id);
						}

					//--------------------------------------------------
					// Return

						return $user_id;

				}

		//--------------------------------------------------
		// Update

			//--------------------------------------------------
			// Fields

				public function update_field_identification_get($form, $config = array()) {

					$field = $this->field_identification_get($form, array_merge(array(
							'label' => $this->text['identification_label'],
							'name' => 'identification',
							'max_length' => $this->identification_max_length,
							'check_domain' => true,
						), $config));

					$field->db_field_set($this->db_fields['main']['identification']);

					return $this->update_field_identification = $field;

				}

				public function update_field_password_old_get($form, $config = array()) {

					$field = $this->field_password_1_get($form, array_merge(array(
							'label' => $this->text['password_old_label'],
							'name' => 'password',
							'min_length' => $this->password_min_length,
							'max_length' => $this->password_max_length,
						), $config, array(
							'required' => true,
							'autocomplete' => 'current-password',
						)));

					return $this->update_field_password_old = $field;

				}

				public function update_field_password_new_1_get($form, $config = array()) {

					$this->update_field_password_new_required = (isset($config['required']) ? $config['required'] : false);

					$field = $this->field_password_1_get($form, array_merge(array(
							'label' => $this->text['password_new_label'],
							'name' => 'password_new',
							'min_length' => $this->password_min_length,
							'max_length' => $this->password_max_length,
						), $config, array(
							'required' => $this->update_field_password_new_required,
							'autocomplete' => 'new-password',
						)));

					return $this->update_field_password_new_1 = $field;

				}

				public function update_field_password_new_2_get($form, $config = array()) {

					$field = $this->field_password_2_get($form, array_merge(array(
							'label' => $this->text['password_repeat_label'],
							'name' => 'password_repeat',
							'min_length' => $this->password_min_length,
							'max_length' => $this->password_max_length,
						), $config, array(
							'required' => $this->update_field_password_new_required,
						)));

					return $this->update_field_password_new_2 = $field;

				}

			//--------------------------------------------------
			// Request

				public function update_validate() {

					//--------------------------------------------------
					// Config

						if ($this->session_info === NULL) {
							exit_with_error('Cannot call auth::update_validate() when the user is not logged in.');
						}

						$form = NULL;
						$new_identification = NULL;
						$new_password = NULL;

						$this->update_details = NULL; // Make sure (if called more than once)

					//--------------------------------------------------
					// Validate identification

						if ($this->update_field_identification !== NULL) {

							$form = $this->update_field_identification->form_get();
							$new_identification = $this->update_field_identification->value_get();

							$result = $this->validate_identification($new_identification, $this->session_user_id_get());

							if ($result === 'failure_current') {

								$this->update_field_identification->error_add($this->text['failure_identification_current']);

							} else if (is_string($result)) {

								$this->update_field_identification->error_add($result); // Custom (project specific) error message

							} else if ($result !== true) {

								exit_with_error('Unknown response from auth::validate_identification()', $result);

							}

						}

					//--------------------------------------------------
					// Validate old password

						if ($this->update_field_password_old !== NULL) {

							$form = $this->update_field_password_old->form_get();
							$old_password = $this->update_field_password_old->value_get();

							$result = $this->validate_login(NULL, $old_password);

							if ($result === 'failure_identification') {

								exit_with_error('Could not return details about user id "' . $this->session_user_id_get() . '"');

							} else if ($result === 'failure_password') {

								$this->update_field_password_old->error_add($this->text['failure_login_password']);

							} else if ($result === 'failure_repetition') {

								$this->update_field_password_old->error_add($this->text['failure_login_repetition']);

							} else if (is_string($result)) {

								$this->update_field_password_old->error_add($result); // Custom (project specific) error message.

							} else if (!is_int($result)) {

								exit_with_error('Unknown response from auth::validate_login()', $result);

							}

						}

					//--------------------------------------------------
					// Validate new password

						if ($this->update_field_password_new_1 !== NULL) {

							if ($this->update_field_password_new_2 === NULL) {
								exit_with_error('Cannot call auth::update_validate() with new password 1, but not 2.');
							}

							$form = $this->update_field_password_new_1->form_get();
							$password_1 = $this->update_field_password_new_1->value_get();
							$password_2 = $this->update_field_password_new_2->value_get();

							$result = $this->validate_password_new($password_1);

							if (is_string($result)) {

								$this->update_field_password_new_1->error_add($result); // Custom (project specific) error message

							} else if ($result !== true) {

								exit_with_error('Unknown response from auth::validate_password_new()', $result);

							} else if ($password_1 != $password_2) {

								$this->update_field_password_new_2->error_add($this->text['failure_password_repeat']);

							} else {

								$new_password = $password_1;

							}

						}

					//--------------------------------------------------
					// Return

						if ($form === NULL) {
							exit_with_error('Cannot call auth::update_validate() without using one of the update fields.');
						}

						if ($form->valid()) {

							$this->update_details = array(
									'identification' => $new_identification,
									'password' => $new_password,
									'form' => $form,
								);

							return true;

						} else {

							return false;

						}

				}

				public function update_complete() {

					//--------------------------------------------------
					// Config

						if (!$this->update_details) {
							exit_with_error('You must call auth::update_validate() before auth::update_complete().');
						}

						if (!$this->update_details['form']->valid()) {
							exit_with_error('The form is not valid, so why has auth::update_complete() been called?');
						}

						$form = $this->update_details['form'];

						$db = $this->db_get();

					//--------------------------------------------------
					// Update

						if ($this->update_details['identification']	) {

							$form->db_value_set($this->db_fields['main']['identification'], $this->update_details['identification']);

							$this->login_last_set($this->update_details['identification']);

						}

						if ($this->update_details['password']) { // could be NULL or blank (if not required)

							$password_hash = password::hash($this->update_details['password'], $this->session_user_id_get());

							$form->db_value_set($this->db_fields['main']['password'], $password_hash);

						}

						$form->db_save();

					//--------------------------------------------------
					// Return

						return true;

				}

		//--------------------------------------------------
		// Reset (forgotten password)

			//--------------------------------------------------
			// Fields

				public function reset_field_email_get($form, $config = array()) { // Must be email, username will be known and can be used to spam.

					$config = array_merge(array(
							'label' => $this->text['email_label'],
							'name' => 'email',
							'max_length' => $this->email_max_length,
						), $config);

					$field = new form_field_email($form, $config['label'], $config['name']);
					$field->format_error_set($this->text['email_format']);
					$field->min_length_set($this->text['email_min_len']);
					$field->max_length_set($this->text['email_max_len'], $config['max_length']);
					$field->autocomplete_set('email');

					// $field->info_set($field->type_get() . ' / ' . $field->input_name_get() . ' / ' . $field->autocomplete_get());

					return $this->reset_field_email = $field;

				}

				public function reset_field_password_new_1_get($form, $config = array()) {

					// $config = array_merge(array(
					// 		'label' => $this->text['password_label'], - New Password
					// 		'name' => 'password',
					// 		'max_length' => 250,
					// 	), $config);

					// Required

				}

				public function reset_field_password_new_2_get($form, $config = array()) {

					// $config = array_merge(array(
					// 		'label' => $this->text['password_label'], - Repeat Password
					// 		'name' => 'password',
					// 		'max_length' => 250,
					// 	), $config);

				}

			//--------------------------------------------------
			// Request

				public function reset_request_validate() {

					// Too many attempts?
					// What happens if there is more than one account?

				}

				public function reset_request_complete($change_url = NULL) {
					// Return
					//   false = invalid_user
					//   $change_url = url($request_url, array('t' => $request_id . '-' . $request_pass));
					//   $change_url->format_set('full');
					//
					// Store users email address in user_password
				}

			//--------------------------------------------------
			// Process

				public function reset_process_active() {
					return false; // Still a valid token?
				}

				public function reset_process_validate() {
					$this->validate_password_new();
					// Repeat password is the same
				}

				public function reset_process_complete() {
				}

		//--------------------------------------------------
		// Support functions

			protected function validate_identification($identification, $user_id) {

				//--------------------------------------------------
				// Config

					$db = $this->db_get();

				//--------------------------------------------------
				// Account details

					$sql = 'SELECT
								1
							FROM
								' . $db->escape_table($this->db_table['main']) . ' AS m
							WHERE
								m.' . $db->escape_field($this->db_fields['main']['identification']) . ' = "' . $db->escape($identification) . '" AND
								m.' . $db->escape_field($this->db_fields['main']['id']) . ' != "' . $db->escape($user_id) . '" AND
								m.' . $db->escape_field($this->db_fields['main']['deleted'])  . ' = "0000-00-00 00:00:00" AND
								' . $this->db_where_sql['main'] . '
							LIMIT
								1';

					if ($row = $db->fetch_row($sql)) {
						return 'failure_current';
					}

				//--------------------------------------------------
				// Valid

					return true;

			}

			protected function validate_password_new($password) {
				return true; // Could set additional complexity requirements (e.g. must contain numbers/letters/etc, to make the password harder to remember)
			}

			protected function validate_login($identification, $password) {

				//--------------------------------------------------
				// Config

					$db = $this->db_get();

					$now = new timestamp();

				//--------------------------------------------------
				// Account details

					if ($identification === NULL) {
						$where_sql = 'm.' . $db->escape_field($this->db_fields['main']['id']) . ' = "' . $db->escape($this->session_user_id_get()) . '"';
					} else {
						$where_sql = 'm.' . $db->escape_field($this->db_fields['main']['identification']) . ' = "' . $db->escape($identification) . '"';
					}

					$sql = 'SELECT
								m.' . $db->escape_field($this->db_fields['main']['id']) . ' AS id,
								m.' . $db->escape_field($this->db_fields['main']['password']) . ' AS password
							FROM
								' . $db->escape_table($this->db_table['main']) . ' AS m
							WHERE
								' . $where_sql . ' AND
								' . $this->db_where_sql['main'] . ' AND
								' . $this->db_where_sql['main_login'] . ' AND
								m.' . $db->escape_field($this->db_fields['main']['password']) . ' != "" AND
								m.' . $db->escape_field($this->db_fields['main']['deleted'])  . ' = "0000-00-00 00:00:00"
							LIMIT
								1';

					if ($row = $db->fetch_row($sql)) {
						$db_id = $row['id'];
						$db_hash = $row['password']; // A blank password (disabled account) is excluded in the query.
					} else {
						$db_id = 0;
						$db_hash = '';
					}

					$error = '';

				//--------------------------------------------------
				// Too many failed logins?

					if ($this->lockout_attempts > 0) {

						$where_sql = array();

						if ($this->lockout_mode === NULL || $this->lockout_mode == 'user') $where_sql[] = 's.user_id = "' . $db->escape($db_id) . '"';
						if ($this->lockout_mode === NULL || $this->lockout_mode == 'ip')   $where_sql[] = 's.ip = "' . $db->escape(config::get('request.ip')) . '"';

						if (count($where_sql) == 0) {
							exit_with_error('Unknown lockout mode (' . $this->lockout_mode . ')');
						}

						$created_after = new timestamp((0 - $this->lockout_timeout) . ' seconds');

						$db->query('SELECT
										1
									FROM
										' . $db->escape_table($this->db_table['session']) . ' AS s
									WHERE
										(
											' . implode(' OR ', $where_sql) . '
										) AND
										s.pass = "" AND
										s.created > "' . $db->escape($created_after) . '" AND
										' . $this->db_where_sql['session']);

						if ($db->num_rows() >= $this->lockout_attempts) { // Once every 30 seconds, for the 30 minutes
							$error = 'failure_repetition';
						}

					}

				//--------------------------------------------------
				// Hash the users password - always run, so timing
				// will always be about the same... taking that the
				// hashing process is computationally expensive we
				// don't want to return early, as that would show
				// the account exists... but don't run for frequent
				// failures, as this could help towards a DOS attack

					if ($error == '') {

						if (extension_loaded('newrelic')) {
							newrelic_ignore_transaction(); // This will be slow!
						}

						$valid = password::verify($password, $db_hash, $db_id);

					}

				//--------------------------------------------------
				// Result

					if ($error == '') {
						if ($db_id > 0) {

							if ($valid) {

								if (password::needs_rehash($db_hash)) {

									$new_hash = password::hash($password, $db_id);

									$db->query('UPDATE
													' . $db->escape_table($this->db_table['main']) . ' AS m
												SET
													m.' . $db->escape_field($this->db_fields['main']['password']) . ' = "' . $db->escape($new_hash) . '"
												WHERE
													m.' . $db->escape_field($this->db_fields['main']['id']) . ' = "' . $db->escape($db_id) . '" AND
													m.' . $db->escape_field($this->db_fields['main']['deleted']) . ' = "0000-00-00 00:00:00" AND
													' . $this->db_where_sql['main'] . '
												LIMIT
													1');

								}

								return intval($db_id); // Success

							} else {

								$error = 'failure_password';

							}

						} else {

							$error = 'failure_identification';

						}
					}

				//--------------------------------------------------
				// Record failure

					$request_ip = config::get('request.ip');

					if (!in_array($request_ip, config::get('auth.ip_whitelist', array()))) {

						$db->insert($this->db_table['session'], array(
								'pass' => '', // Will remain blank to record failure
								'user_id' => $db_id,
								'ip' => $request_ip,
								'browser' => config::get('request.browser'),
								'created' => $now,
								'last_used' => $now,
								'deleted' => '0000-00-00 00:00:00',
							));

					}

				//--------------------------------------------------
				// Return error

					return $error;

			}

		//--------------------------------------------------
		// Fields

			protected function field_identification_get($form, $config) {

				if ($this->identification_type == 'username') {
					$field = new form_field_text($form, $config['label'], $config['name']);
				} else {
					$field = new form_field_email($form, $config['label'], $config['name']);
					$field->check_domain_set($config['check_domain']);
					$field->format_error_set($this->text['identification_format']);
				}

				$field->min_length_set($this->text['identification_min_len']);
				$field->max_length_set($this->text['identification_max_len'], $config['max_length']);
				$field->autocapitalize_set(false);
				$field->autocomplete_set('username');

				// $field->info_set($field->type_get() . ' / ' . $field->input_name_get() . ' / ' . $field->autocomplete_get());

				return $field;

			}

			public function field_password_1_get($form, $config) { // Used in login, register, update (x2), reset.

				$field = new form_field_password($form, $config['label'], $config['name']);
				$field->max_length_set($this->text['password_max_len'], $config['max_length']);
				$field->autocomplete_set($config['autocomplete']);

				if ($config['required']) {
					$field->min_length_set($this->text['password_min_len'], $config['min_length']);
				}

				// $field->info_set($field->type_get() . ' / ' . $field->input_name_get() . ' / ' . $field->autocomplete_get());

				return $field;

			}

			public function field_password_2_get($form, $config) { // Used in register, update, reset.

				$field = new form_field_password($form, $config['label'], $config['name']);
				$field->max_length_set($this->text['password_repeat_max_len'], $config['max_length']);
				$field->autocomplete_set('new-password');

				if ($config['required']) {
					$field->min_length_set($this->text['password_repeat_min_len'], $config['min_length']);
				}

				// $field->info_set($field->type_get() . ' / ' . $field->input_name_get() . ' / ' . $field->autocomplete_get());

				return $field;

			}

	}

?>