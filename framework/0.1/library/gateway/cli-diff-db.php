<?php

	$now = new timestamp('now', 'UTC');

	$key_valid = false;
	$key_request = request('key', 'POST');
	$key_time = $now->clone();

	for ($k = 0; $k <= 3; $k++) {
		if (hash('sha256', (ENCRYPTION_KEY . $key_time->format('Y-m-d H:i:s'))) == $key_request) {
			$key_valid = true;
			break;
		}
		$key_time = $key_time->clone('-1 second');
	}

	if (!$key_valid) {

		if ($key_request == '') {
			echo 'Missing key' . "\n";
		} else {
			echo 'Invalid key (' . config::get('request.domain') . ' / ' . $now->format('Y-m-d H:i:s') . ' / ' . request('timestamp') . ')' . "\n";
		}

	} else {

		require_once(FRAMEWORK_ROOT . '/library/cli/diff.php');
		require_once(FRAMEWORK_ROOT . '/library/cli/dump.php');

		diff_run('db', (request('upload') == 'true'));

	}

?>