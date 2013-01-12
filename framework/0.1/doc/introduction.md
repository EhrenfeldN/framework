
# Introduction

PHP Prime is a basic framework, which is loosely based on the MVC structure.

Where the typical page is built using the URL, for example:

	http://www.example.com/contact/

If you're adding a HTML page, then you just need to create a [view file](../doc/setup/views.md) such as:

	/app/view/contact.ctp

		<h1>Contact Us</h1>
		<p>Tel: 1234 567 8900</p>
		<p>Email: <a href="mailto:admin@example.com">admin@example.com</a></p>

The output from this is added to a [template](../doc/setup/templates.md), where the common HTML for the website is added (such as the site navigation).

	<!DOCTYPE html>
	<html lang="<?= html($this->lang_get()) ?>">
	<head>
		<?= $this->head_get_html(); ?>
	</head>
	<body id="<?= html($this->page_id_get()) ?>">

		<h1><?= html($this->title_get()) ?></h1>

		<div id="page_nav">

			<?= $nav->html(); ?>

		</div>

		<div id="page_content">

			<?= $this->message_get_html(); ?>

			<?= $this->view_get_html(); ?>

		</div>

	</body>
	</html>

NB: I use the [echo shortcut](http://www.php.net/echo) (<?=), instead of (<?php echo) as it's shorter, easier to read, and no longer considered a short tag as of PHP 5.4.

---

# Optional controller

You could extend the above by creating a [controller](../doc/setup/controllers.md).

If you are running the website in [development mode](../doc/setup/debug.md), PHP Prime will add some notes to explain how it searches for the controller.

In this example we use the [form](../doc/helpers/form.md) and [email](../doc/helpers/email.md) helpers to create a 'contact us' form, which sends an email, and keeps a copy in the database.

It should be noted that the database determines the maximum length of the fields.

	/app/controller/contact.php

	<?php [SEE EXAMPLE] ?>

To quickly create the HTML in the [view](../doc/setup/views.md), we can use:

	<?= $form->html(); ?>

---

# Next steps

From here I urge you to at least scan over the notes on [security](../doc/security.md), which applies to all websites/frameworks.

You will also notice that when you are in [development mode](../doc/setup/debug.md), not only do you get the helper notes, the page loads with the XML header to ensure your HTML remains strict, and the [CSP header](../doc/security/csp.md) is enabled and enforced.

Now you're free to [start using the framework](../doc/setup.md).