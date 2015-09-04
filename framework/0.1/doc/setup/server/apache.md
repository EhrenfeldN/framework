Below are some example configurations for Apache.

### Uploaded files

Typically the framework will map the /a/files/ URL to the /files/ folder.

This folder is not touched when uploading changes to the website, as it contains files that are uploaded to the website (e.g. CMS controlled images).

### Cache busting URLs

The framework will allow you to include the modifiation time in the URL of images, CSS, and JavaScript files, so the server can then set a long cache time on them with the ExpiresByType rules:

	ExpiresActive On
	ExpiresByType image/gif "access plus 12 months"
	ExpiresByType image/jpeg "access plus 12 months"
	ExpiresByType image/png "access plus 12 months"
	ExpiresByType image/x-icon "access plus 12 months"
	ExpiresByType text/css "access plus 12 months"
	ExpiresByType application/javascript "access plus 12 months"
	ExpiresByType application/x-font-woff "access plus 12 months"

For resources linked to automaticaly by the framework, this feature can be enabled with:

	$config['output.timestamp_url'] = true;

And anything you include yourself, can use the timestamp_url() function:

	<img src="<?= html(timestamp_url('/a/img/logo.gif')) ?>" alt="Logo" />

---

## Standard setup

	<VirtualHost *:80>

		ServerName www.example.com
		DocumentRoot /www/live/test.project/app/public/

		RewriteEngine on

		RewriteRule ^/a/files/(.*) /www/live/test.project/files/$1 [S=1]
		RewriteRule ^(.*)$ %{DOCUMENT_ROOT}$1

		RewriteCond $1/$2 -f
		RewriteRule ^(.*)/[0-9]+-([^/]+)$ $1/$2 [L]

		RewriteCond %{REQUEST_FILENAME} !^/a/files/
		RewriteCond %{REQUEST_FILENAME} !-d
		RewriteCond %{REQUEST_FILENAME} !-f
		RewriteRule ^(.*)$ %{DOCUMENT_ROOT}/index.php [L]

	</VirtualHost>

---

## Simple .htaccess

    RewriteEngine On

	RewriteRule ^/a/files/(.*) /www/live/test.project/files/$1

	RewriteRule ^(.*)/[0-9]+-([^/]+)$ $1/$2 [L]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ /index.php [L]

---

## Sub-folder .htaccess

If the website is going work in a sub-folder for something like a WordPress website:

	RewriteEngine On

	RewriteBase /
	RewriteRule ^index\.php$ - [L]

	RewriteRule ^(folder/.*)/[0-9]+-([^/]+)$ $1/$2 [L]

	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^folder/ folder/index.php [L]

	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule . /index.php [L]

---

## Development URLs

If you work on many websites, and use a URL structure such as:

	http://test.project.emma.devcf.com/

Which maps automatically to the appropriate websites folder, then use something like:

	NameVirtualHost *:80

	<VirtualHost *:80>

		DocumentRoot /Volumes/WebServer/Projects/craig.homepage/public
		UseCanonicalName off

		LogFormat "%h %l %u [%{SESSION}n] [%{LOG_INFO}n] [%{%Y-%m-%d %H:%M:%S}t] \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" inc_info
		CustomLog /private/var/log/apache2/access_log inc_info
		ErrorLog /private/var/log/apache2/error_log

		Alias /phpMyAdmin/ /Volumes/WebServer/Projects/craig.homepage/phpMyAdmin/

		RewriteEngine on
		#RewriteLog /var/log/apache2/rewrite_log
		#RewriteLogLevel 2
		#tail -f /var/log/apache2/rewrite_log | grep -o -E "(initial|subreq).*"

		# Map project from HOST into FILENAME
		RewriteCond %{HTTP_HOST} ^([a-z0-9]+(\.[a-z0-9]+))*\.[a-z]+\.devcf\.com$
		RewriteRule ^(/.*) - [NS,E=WEB_ROOT:/Volumes/WebServer/Projects/%1]

		# Drop into /files/ folder (if present)
		RewriteCond %{REQUEST_FILENAME} ^/a/files
		RewriteCond %{ENV:WEB_ROOT}/files -d
		RewriteRule ^/a/files(/.*) $1 [NS,E=WEB_ROOT:%{ENV:WEB_ROOT}/files]

		# Drop into /app/public/ folder (if present)
		RewriteCond %{ENV:WEB_ROOT}/app/public -d
		RewriteRule ^(/.*) - [NS,E=WEB_ROOT:%{ENV:WEB_ROOT}/app/public]

		# Drop into /public/ folder (if present)
		RewriteCond %{ENV:WEB_ROOT}/public -d
		RewriteRule ^(/.*) - [NS,E=WEB_ROOT:%{ENV:WEB_ROOT}/public]

		# Drop timestamp from url - e.g. "/a/12345-logo.jpg" to "/a/logo.jpg"
		RewriteCond %{ENV:WEB_ROOT}/$1/$2 -f
		RewriteRule ^(.*)/[0-9]+-([^/]+)$ $1/$2

		# If there is a rewrite.php script
		RewriteCond %{REQUEST_FILENAME} !^/a/
		RewriteCond %{ENV:WEB_ROOT}/a/php/rewrite.php -f
		RewriteRule ^(/.*) %{ENV:WEB_ROOT}/a/php/rewrite.php [L]

		# Apply the WEB_ROOT, both if initial request or as a sub-request... but only when set (not homepage)
		RewriteCond %{ENV:WEB_ROOT} !=""
		RewriteRule ^(/.*) %{ENV:WEB_ROOT}$1

	</VirtualHost>

	<Directory "/Volumes/WebServer/Projects">

		AllowOverride All
		Options +Indexes +Includes
		Order allow,deny
		Allow from all

	</Directory>

	<Directory "/Volumes/WebServer/Projects/craig.homepage/phpMyAdmin">

		Order deny,allow
		Deny from all
		Allow from 127.0.0.1

	</Directory>

	<VirtualHost *:80>

		ServerName localhost

		RewriteEngine on
		RewriteRule   ^/(.*)  http://emma.devcf.com/$1  [R=301]

	</VirtualHost>