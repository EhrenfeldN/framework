<?php

//--------------------------------------------------
// Encryption key

	define('ENCRYPTION_KEY', 'gNB2gaD7hpR*q[2[NCv');

//--------------------------------------------------
// Server specific

	if (preg_match('/^\/(Library|Volumes)\//i', ROOT)) {

		//--------------------------------------------------
		// Server

			define('SERVER', 'stage');

		//--------------------------------------------------
		// Database

			$config['db.host'] = 'localhost';
			$config['db.user'] = 'stage';
			$config['db.pass'] = 'st8ge';
			$config['db.name'] = 's-craig-framework';

			$config['db.prefix'] = 'tpl_';

		//--------------------------------------------------
		// Email

			$config['email.from_email'] = 'noreply@phpprime.com';
			$config['email.contact_us'] = array('craig@craigfrancis.co.uk');

		//--------------------------------------------------
		// Gateway

			$config['gateway.tester'] = true;

	} else if (prefix_match('/mnt/www/demo/', ROOT)) {

		//--------------------------------------------------
		// Server

			define('SERVER', 'demo');

	} else {

		//--------------------------------------------------
		// Server

			define('SERVER', 'live');

		//--------------------------------------------------
		// Database

			$config['db.host'] = 'devcf-rds.cfbcmnc53kwh.eu-west-1.rds.amazonaws.com';
			$config['db.user'] = 'craig-framework';
			$config['db.pass'] = NULL; // see /private/passwords/database.txt
			$config['db.name'] = 'l-craig-framework';

			$config['db.ca_file'] = '/etc/mysql/tls.pem';
			$config['db.prefix'] = 'tpl_';

		//--------------------------------------------------
		// Email

			$config['email.from_email'] = 'noreply@phpprime.com';
			$config['email.error'] = array('craig@craigfrancis.co.uk');
			$config['email.contact_us'] = array('craig@craigfrancis.co.uk');

		//--------------------------------------------------
		// General

			$config['output.domain'] = 'www.phpprime.com';

	}

//--------------------------------------------------
// Output

	$config['output.site_name'] = 'PHP Prime';
	$config['output.js_min'] = (SERVER != 'stage');
	$config['output.css_min'] = (SERVER != 'stage');
	$config['output.timestamp_url'] = true;

//--------------------------------------------------
// Security

	$config['cookie.prefix'] = '__Host-'; // A `Secure` cookie, with no `Domain` attribute

	$config['output.protocols'] = array('https');

	$config['output.framing'] = 'DENY'; // or SAMEORIGIN

	$config['output.fp_enabled'] = true;

	$config['output.referrer_policy'] = 'same-origin';

	$config['output.xss_reflected'] = 'block';

	$config['output.fp_enabled'] = true;

	$config['output.csp_enabled'] = true;
	$config['output.csp_enforced'] = true;
	$config['output.csp_directives'] = array(
			'default-src'  => array("'none'"),
			'base-uri'     => array("'none'"),
			'connect-src'  => array("'self'"),
			'form-action'  => array("'self'"),
			'style-src'    => array("'self'"),
			'img-src'      => array("'self'"),
			'script-src'   => array("'self'"),
		);

	if ($config['output.tracking'] !== false) {
		$config['output.csp_directives']['script-src'][] = 'https://www.google-analytics.com';
		$config['output.csp_directives']['connect-src'][] = 'https://www.google-analytics.com';
	}

	if (SERVER != 'stage') {
		$config['output.ct_enabled'] = true;
	}

//--------------------------------------------------
// Tracking

	// $config['tracking.ga_code'] = 'UA-000000-0';
	$config['tracking.js_path'] = '/a/js/analytics.js';

//--------------------------------------------------
// Pagination

	$config['file.test-png.image_type'] = 'png';
	$config['file.test-gif.image_type'] = 'gif';

	// $config['paginator.elements'] = array('<ul class="pagination">', 'first', 'back', 'links', 'next', 'last', '</ul>', 'extra', "\n");
	// $config['paginator.link_wrapper_element'] = 'li';

//--------------------------------------------------
// Upload

	$config['upload.demo.source'] = 'git';
	$config['upload.demo.location'] = 'fey:/www/demo/craig.framework';

	$config['upload.live.source'] = 'demo';
	$config['upload.live.location'] = 'fey:/www/live/craig.framework';

?>