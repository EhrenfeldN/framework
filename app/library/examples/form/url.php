<?php

	$field = new form_field_url($form, 'URL');
	if ($database) $field->db_field_set('url');
	$field->scheme_default_set('http');
	$field->scheme_allowed_set('Your url has an invalid scheme.', array('http', 'https'));
	$field->format_error_set('Your url does not appear to be correct.');
	$field->min_length_set('Your url is required.');
	if ($database) $field->max_length_set('Your url cannot be longer than XXX characters.');
	if (!$database) $field->max_length_set('Your url cannot be longer than XXX characters.', 200);

?>