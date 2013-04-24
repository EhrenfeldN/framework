
# White-site

A white-site is a good starting point for a website.

It typically has very little design, allowing you to start describing/building the websites functionality, and entering the content.

---

## Setup

To begin, create an empty folder, and initialise the framework

	mkdir company.project;
	cd company.project;
	../craig.framework/framework/0.1/cli/run.sh -i;

And while a good example for beginners, delete the file:

	/app/view/contact.ctp

---

## Automatic views

As we don't want to waste time creating lots of view files, we can get the framework to use a standard view that simply gives us two content managed text areas.

Todo this, first create the generic view file:

	/app/library/view/place-holder.ctp

	<?php
		echo_place_holder();
	?>

And add the following code:

	/app/library/setup/setup.php

	<?php

	//--------------------------------------------------
	// CMS Text

		config::set('cms.default.editable', true);

		function echo_place_holder() {

			echo cms_text_html(array(
					'section' => 'title',
					'wrapper_tag' => 'h1',
					'global' => true,
				));

			if (!config::get('output.new_page')) {

				echo cms_text_html(array(
						'section' => 'functionality',
						'default' => 'None: Static content',
					));

				echo cms_text_html(array(
						'section' => 'content',
						'default' => 'Lorem ipsum dolor sit...',
					));

			}

		}

		$route_path = config::get('route.path');
		$view_path = view_path(path_to_array($route_path));

		if (!is_file($view_path)) {

			$page_title = cms_text_html(array(
						'path' => $route_path,
						'section' => 'title',
						'wrapper_tag' => 'none',
						'editable' => false,
						'default' => '',
					));

			if ($page_title != '' || SERVER == 'stage') {

				config::set('output.new_page', ($page_title == ''));

				$response = response_get();
				$response->view_path_set(ROOT . '/app/library/view/place-holder.ctp');

			}

		}

	?>

---

## The database

Set the [database config](../../doc/system/database.md):

	/app/library/setup/config.php

		$config['db.host'] = 'localhost';
		$config['db.user'] = 'stage';
		$config['db.pass'] = 'XXX';
		$config['db.name'] = 's-company-project';

		$config['db.prefix'] = 'tbl_';

Then load the website.

It should complain about missing tables, and give you the SQL so they can be created, as I prefer frameworks not to do this automatically.

---

## Editing content

Then to allow you to edit the content, create the controller:

	/app/controller/admin/cms-text.php

	<?php

		class admin_cms_text_controller extends controller_cms_text {
		}

	?>

This will probably be part our admin control panel in the future, but for now its not password protected.

Also, you may find that you want to create a [before](../../doc/setup/controllers.md) method, and set a variable for the [template](../../doc/setup/templates.md) which holds a simple <h1> for the page.

---

## Navigation

To make the navigation bar use the pages title for the link text, create the file:

	/app/library/class/nav.php

	<?php

		class nav extends nav_base {

			public function link_name_get($url) {
				return html_decode(cms_text_html(array(
						'path' => $url,
						'section' => 'title',
						'wrapper_tag' => 'none',
						'editable' => false,
						'default' => $url,
					)));
			}

		}

	?>

So when your creating your navigation, do it like this:

	/app/template/default.ctp

		$nav = new nav();
		$nav->link_add('/');
		$nav->link_add('/about/');
		$nav->link_add('/contact/');
		$nav->link_add('/news/');

Then to create pages, simply add more "link_add()" calls, refresh the site in your browser, follow the links in the nav, and set the pages title by clicking on it.

If the page does not have a title set, the url will be shown in the navigation, and on Demo/Live the page will return a 404.

---

## Developing the site

As the site gets built, simply add your [ctp files](../../doc/setup/views.md) in /app/view/.

To continue seeing the content from the place-holder page, simply call:

	<?php echo_place_holder(); ?>

Or if you want to start calling the cms_text_html() helper function directly:

	<?php

		echo cms_text_html(array(
				'section' => 'title',
				'wrapper_tag' => 'h1',
				'global' => true,
			));

		echo cms_text_html(array(
				'section' => 'functionality',
			));

		echo cms_text_html(array(
				'section' => 'content',
			));

	?>