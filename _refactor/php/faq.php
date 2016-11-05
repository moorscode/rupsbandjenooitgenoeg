<h2>Algemene informatie</h2>

<ul class="faq">

	<?php

	require_once( "class.Database.php" );
	$db = &Database::getInstance();

	$query = $db->query( "SELECT `title`, `description` FROM `global__Faqs` ORDER BY `title`" );
	while ( $row = $db->assoc( $query ) ) {
		extract( $row );
		$description = nl2br( $description );
		?>

		<li onclick="showMyDiv(this);">
			<?= $title ?><br/>
			<div class="faqDescription"><?= $description ?></div>
		</li>

		<?php
	}
	?>
</ul>