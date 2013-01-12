
# Site config

Your initial config values should be setup in:

	/app/setup/config.php

Which is done via the `$config` array, for example:

	$config['name'] = 'Value';

This allows you to include this file via other systems, without providing the `config` object.

After this file has been processed, you should use the [config helper](../../doc/system/config.md) to set/get values.

---

## Encryption key

This should be considered private, and involves you typing in some random keys:

	define('ENCRYPTION_KEY', 'type-your-own-random-characters');

The intention of this constant is simply for encrypting information.

Its also used to ensure sessions are valid for this websites... so avoiding session fixation, and sessions being created on other websites hosted on the same box ([notes](http://www.sitepoint.com/notes-on-php-session-security/)).

---

## Servers

In your config.php file, you will probably want to set the 'SERVER' constant. This will allow your scripts to determine if they are running on a development server (stage), demo or live.

There are many ways to detect which server your running on, but my preferred method is to use the path:

	if (preg_match('/^\/(Library|Volumes)\//i', ROOT)) {

		define('SERVER', 'stage');

	} else if (prefix_match('/www/demo/', ROOT)) {

		define('SERVER', 'demo');

	} else {

		define('SERVER', 'live');

	}

You will notice the first one uses a simple regexp... as on OSX most developers end up running it in the /Library/ folder, or on a second (case-sensitive) volume.

The second one uses the [prefix_match](../../doc/system/functions.md)() function for **demo**.

And the default is to assume we are running on **live**.

---

## Server differences

With the configuration setup like this, you can do different things in the different environments.

For example, its a good place to setup the [database connection](../../doc/system/database.md) details.

If your using the [email helper](../../doc/helpers/email.md), it might be worth setting the following on **stage**:

	$config['email.testing'] = 'admin@example.com';

And when you are on **stage**, [development mode](../../doc/setup/debug.md) is enabled by default.