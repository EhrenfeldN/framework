<?php

//--------------------------------------------------
// Database

	$config['db.prefix'] = 'tpl_';

//--------------------------------------------------
// Server specific

	if (preg_match('/^\/(Library|Volumes)\//i', realpath(__FILE__))) {

		//--------------------------------------------------
		// Server

			$config['server'] = 'stage'; // Special / constant?

		//--------------------------------------------------
		// Database

			$config['db.host'] = 'stage';
			$config['db.user'] = 'stage';
			$config['db.pass'] = 'st8ge';
			$config['db.name'] = 's-company-project';

			$config['db.debug_mode'] = true;

		//--------------------------------------------------
		// Email

			$config['email.from_name'] = 'Company Name';
			$config['email.from_address'] = 'noreply@domain.com';
			$config['email.error'] = array('admin@domain.com');
			$config['email.contact_us'] = array('admin@domain.com');

		//--------------------------------------------------
		// General

			$config['output.mime'] = 'application/xhtml+xml';

			$config['debug.run'] = true;

	} else if (preg_match('/^\/www\/demo/i', realpath(__FILE__))) {

		//--------------------------------------------------
		// Server

			$config['server'] = 'demo';

	} else {

		//--------------------------------------------------
		// Server

			$config['server'] = 'live';

		//--------------------------------------------------
		// General

			$config['ve_google_analytics.code'] = 'GA-';

	}

?>