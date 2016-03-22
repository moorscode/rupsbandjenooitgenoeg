<?php

session_start();

require( "class.Database.php" );
$db = &Database::getInstance();


/*
		PVT: 0-19 punten (soldaat)
		KRP: 20-79 punten (korporaal)
		SGT: 80-179 punten (sergant)
		MAJ: 180-319 punten (majoor)
		ADJ: 320-499 punten: (adjudant)
		LUI: 500-719 punten (luitenant)
		KPT: 720-979 punten (kapitein)
		KOL: 980-1279 punten (kolonel)
		GNM: 1280-1619 punten (generaal-majoor)
		LGN: 1620-1999 punten (luitenant-generaal)
		GEN: 2000 punten (generaal)
		*/

$ranks = array(
	'Soldaat',
	'Korporaal',
	'Sergant',
	'Majoor',
	'Adjudant',
	'Luitenant',
	'Kapitein',
	'Kolonel',
	'Generaal-Majoor',
	'Luitenant-Generaal',
	'Generaal'
);


$uid  = $_SESSION['user_id'];
$user = $db->query( "SELECT * FROM `global__PlayerInfo` WHERE `player_id`='$uid'" );
if ( $row = $db->assoc( $user ) ) {
	extract( $row );

	$rank     = floor( sqrt( intval( $row['points'] ) / 20 ) );
	$top_rank = floor( sqrt( intval( $row['top_points'] ) / 20 ) );

	if ( $shots_fired == 0 ) {
		$accuracy = "100";
	}
	else {
		$accuracy = floor( ( 100 / $shots_fired ) * $shots_hit );
	}

	if ( $shots_hit == 0 ) {
		$efficienty = "100";
	}
	else {
		$efficienty = min( 100, floor( ( 100 / ( $shots_hit / 10 ) ) * $kills ) );
	}

	$distance = number_format( $distance, 0, ",", "." );
}

?>

<h2><?= $ranks[ $rank ] ?> <?= html_entity_decode( $_SESSION['user_name'], ENT_NOQUOTES ); ?></h2>
<h3><?= $points ?> punten</h3>
<br/>

Hoogst behaalde rang:<br><?= $ranks[ $top_rank ] ?><br/>
<br/>
Aantal gewonnen spellen: <?= $wins ?><br/>
Aantal kills: <?= $kills ?><br/>
<br/>

<span class="help" onmousemove="showPopup('Hoeveel van je schoten raak waren');"
      onmouseout="hidePopup();">Accuracy:</span> <?= $accuracy ?>%<br/>
Efficiency: <?= $efficienty ?>%<br/>

<br/>
Afgelegde afstand:<br/>
<?= $distance ?> meter
