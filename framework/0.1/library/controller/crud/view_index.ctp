
	<?= $form ?>

	<?= $paginator ?>

	<?php if (isset($add_url)) { ?>

		<p><a href="<?= html($add_url) ?>">Add a new <?= html($item_single) ?></a>.</p>

	<?php } ?>

	<?= $table ?>

	<?= $paginator ?>
