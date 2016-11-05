<?php

$categories = array();

function add_category( $name ) {
	global $categories;

	return array_push( $categories, array( "name" => $name, "charts" => array() ) ) - 1;
}

function add_chart( $category, $title, $identifier ) {
	global $categories;
	array_push( $categories[ $category ]['charts'], array( "title" => $title, "id" => $identifier ) );
}

$servers = add_category( "Servers" );
add_chart( $servers, "Server Downtime", "serverdown" );

$registration = add_category( "Registraties" );
add_chart( $registration, "RegVerInv", "registration" );
add_chart( $registration, "Logins", "logins" );

$chat = add_category( "Chat server" );
add_chart( $chat, "Idle tijd", "chatidle" );
add_chart( $chat, "Gebruikers online", "chatusers" );
add_chart( $chat, "User input", "chatinput" );

$games = add_category( "Game server" );
add_chart( $games, "Games", "gamegames" );
add_chart( $games, "Wachttijd", "gamequeuetime" );
add_chart( $games, "Rang verandering", "rankchange" );
add_chart( $games, "Kills", "gamekills" );
add_chart( $games, "Accuracy", "accuracy" );
add_chart( $games, "Oefenspel", "practise" );
add_chart( $games, "Prestaties", "achievements" );

$settings = add_category( "Instellingen" );
add_chart( $settings, "Naam wijzigingen", "namechanges" );

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>Rupsbandjenooitgenoeg: Statistics</title>
	<link rel="stylesheet" href="../assets/html/style.css" type="text/css"/>
	<link rel="shortcut icon" href="../assets/html/images/favicon.ico" type="image/x-icon">
	<link rel="icon" href="../assets/html/images/favicon.ico" type="image/x-icon">
	<script language="javascript">AC_FL_RunContent = 0;</script>
	<script language="javascript"> DetectFlashVer = 0; </script>
	<script src="../assets/charts/AC_RunActiveContent.js" language="javascript"></script>
	<script language="JavaScript" type="text/javascript">
		<!--
		var requiredMajorVersion = 10;
		var requiredMinorVersion = 0;
		var requiredRevision = 0;
		-->
	</script>
</head>

<body>

<table width="100%">
	<tr>
		<td width="50%" align="center">

			<h1>5 April 2010 - 20:00 tot 03:00</h1>
			<?php
			foreach ( $categories as $category ) {
				?>
				<h1 id="<?= $category['name'] ?>"><?= $category['name'] ?></h1>

			<?php
			foreach ( $category['charts'] as $chart ) {
			?>
				<script language="JavaScript" type="text/javascript">
					<!--
					if (AC_FL_RunContent == 0 || DetectFlashVer == 0) {
						alert("This page requires AC_RunActiveContent.js.");
					} else {
						var hasRightVersion = DetectFlashVer(requiredMajorVersion, requiredMinorVersion, requiredRevision);
						if (hasRightVersion) {
							AC_FL_RunContent(
								'codebase', 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,45,0',
								'width', '650',
								'height', '250',
								'scale', 'noscale',
								'salign', 'LT',
								'bgcolor', '#CCD6E0',
								'wmode', 'opaque',
								'movie', '../assets/charts/charts',
								'src', '../assets/charts/charts',
								'FlashVars', 'library_path=../assets/charts/charts_library&xml_source=../assets/charts/data/stats.range.xml.php?type=<?= $chart['id'] ?>%26start=5 April 2010 2000%26end=6 April 2010 0300',
								'id', '<?= $chart['id'] ?>',
								'name', '<?= $chart['id'] ?>',
								'menu', 'true',
								'allowFullScreen', 'true',
								'allowScriptAccess', 'sameDomain',
								'quality', 'high',
								'align', 'middle',
								'pluginspage', 'http://www.macromedia.com/go/getflashplayer',
								'play', 'true',
								'devicefont', 'false'
							);
						} else {
							var alternateContent = 'This content requires the Adobe Flash Player. <u><a href=http://www.macromedia.com/go/getflash/>Get Flash</a></u>.';
							document.write(alternateContent);
						}
					}
					// -->
				</script>
				<?php
			}
			}
			?>
		</td>
		<td width="50%" align="center">
			<h1>14 April 2010 - 20:00 tot 03:00</h1>
			<?php
			foreach ( $categories as $category ) {
				?>
				<h1 id="<?= $category['name'] ?>"><?= $category['name'] ?></h1>

			<?php
			foreach ( $category['charts'] as $chart ) {
			?>
				<script language="JavaScript" type="text/javascript">
					<!--
					if (AC_FL_RunContent == 0 || DetectFlashVer == 0) {
						alert("This page requires AC_RunActiveContent.js.");
					} else {
						var hasRightVersion = DetectFlashVer(requiredMajorVersion, requiredMinorVersion, requiredRevision);
						if (hasRightVersion) {
							AC_FL_RunContent(
								'codebase', 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,45,0',
								'width', '650',
								'height', '250',
								'scale', 'noscale',
								'salign', 'LT',
								'bgcolor', '#CCD6E0',
								'wmode', 'opaque',
								'movie', '../assets/charts/charts',
								'src', '../assets/charts/charts',
								'FlashVars', 'library_path=../assets/charts/charts_library&xml_source=../assets/charts/data/stats.range.xml.php?type=<?= $chart['id'] ?>%26start=14 April 2010 2000%26end=15 April 2010 0300',
								'id', '<?= $chart['id'] ?>',
								'name', '<?= $chart['id'] ?>',
								'menu', 'true',
								'allowFullScreen', 'true',
								'allowScriptAccess', 'sameDomain',
								'quality', 'high',
								'align', 'middle',
								'pluginspage', 'http://www.macromedia.com/go/getflashplayer',
								'play', 'true',
								'devicefont', 'false'
							);
						} else {
							var alternateContent = 'This content requires the Adobe Flash Player. <u><a href=http://www.macromedia.com/go/getflash/>Get Flash</a></u>.';
							document.write(alternateContent);
						}
					}
					// -->
				</script>
				<?php
			}
			}
			?>
		</td>
	</tr>
</table>

</body>
</html>
