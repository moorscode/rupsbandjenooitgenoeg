<?php
/*
 * Parsing the highscores
 *
 * 	Starting index and length can be added, to enable paged-browsing
 *		Also to display the highscores on the main page
 *
 *		Highest rank is index 0
 *		Lowest rank is index (highest)
 *
 *	nov 7th, 2009 @ 23:00
*/

session_start();

require( "class.Database.php" );

$ranks = array( 'PVT', 'KRP', 'SGT', 'MAJ', 'ADJ', 'LUI', 'KPT', 'KOL', 'GNM', 'LGN', 'GEN' );


$start = intval( $_GET['start'] );
$count = intval( $_GET['count'] );

// get highscores from the database.

$limit = "LIMIT $start, $count";
$spot  = 1;

$db = &Database::getInstance();


echo <<<EOSCORES
<div class='header'>
	<div class='rank'>&nbsp;</div>
	<div class='user'>speler</div>
	<div class='points'>punten</div>
</div>

EOSCORES;

$first  = " first";
$scores = $db->query( "SELECT `points`, `username` FROM `global__PlayerInfo` AS info INNER JOIN `global__Players` AS players ON info.player_id=players.id WHERE players.id <> 1 ORDER BY `points` DESC " . $limit );
while ( $score = $db->assoc( $scores ) ) {
	$rank = floor( sqrt( $score['points'] / 20 ) );

	echo <<<EOSCORE
<div class='line'>
	<div class='rank{$first}'>{$ranks[$rank]}</div>
	<div class='user{$first}'>{$score['username']}</div>
	<div class='points{$first}'>{$score['points']}</div>
</div>

EOSCORE;

	$first = "";
}

