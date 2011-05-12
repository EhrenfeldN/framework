<?php

//--------------------------------------------------
// View

	ob_start();

	echo config::get('output.html');

	$view = new view();
	$view->render();

	config::set('output.html', ob_get_clean());

	unset($view);

//--------------------------------------------------
// Output variables

	//--------------------------------------------------
	// Title

		if (config::get('output.error')) {

			$title_default = config::get('output.title_error');

		} else {

			$title_prefix = config::get('output.title_prefix');
			$title_suffix = config::get('output.title_suffix');
			$title_divide = config::get('output.title_divide');

			$title_default = '';

			$k = 0;
			foreach (config::get('output.title_folders') as $folder) {
				if ($folder != '') {
					if ($k++ > 0) {
						$title_default .= $title_divide;
					}
					$title_default .= $folder;
				}
			}

			$title_default = $title_prefix . ($title_prefix != '' && $k > 0 ? $title_divide : '') . $title_default;
			$title_default = $title_default . ($title_suffix != '' && $k > 0 ? $title_divide : '') . $title_suffix;

		}

		config::set_default('output.title', $title_default);

		config::set('output.title_default', $title_default);

		unset($title_default, $title_prefix, $title_divide, $title_suffix, $k, $folder);

	//--------------------------------------------------
	// Page ref

		$page_ref_mode = config::get('output.page_ref_mode');

		if ($page_ref_mode == 'route') {

			config::set_default('output.page_ref', human_to_ref(config::get('route.path')));

		} else if ($page_ref_mode == 'request') {

			config::set_default('output.page_ref', human_to_ref(config::get('request.path')));

		} else if ($page_ref_mode == 'view') {

			config::set_default('output.page_ref', human_to_ref(config::get('view.path')));

		} else {

			exit_with_error('Unrecognised page ref mode "' . $page_ref_mode . '"');

		}

		unset($page_ref_mode);

	//--------------------------------------------------
	// Message

		$message = ''; // TODO: From cookie

		if ($message == '') {
			$message_html = '';
		} else {
			$message_html = '
				<div id="page_message">
					<p>' . html($message) . '</p>
				</div>';
		}

		config::set_default('output.message', $message);
		config::set_default('output.message_html', $message_html);

		unset($message, $message_html);

//--------------------------------------------------
// Layout

	$layout = new layout();
	$layout->render();

	unset($layout);

?>