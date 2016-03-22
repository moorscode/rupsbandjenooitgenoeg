<?php
/*
 * Achievement parsing file
 *
 *	mar 13th, 2010 @ 15:00 pm
*/

function set_achievement( $identifier ) {
	global $db;

	$achievement_id = get_achievement_id( $identifier );

	if ( $achievement_id > 0 ) {
		$user_id = intval( $_SESSION['user_id'] );

		// check for existing entry:
		$query = $db->query( "SELECT `id` FROM `achievements__Completed` WHERE  `player_id`='$user_id' AND `achievement_id`=$achievement_id" );
		if ( $db->num_rows( $query ) == 0 ) {
			$db->query( "INSERT INTO `achievements__Completed` (`achievement_id`, `player_id`, `timestamp`) VALUES ($achievement_id, $user_id, UNIX_TIMESTAMP())" );

			return $achievement_id;
		}
	}

	return 0;
}

function get_achievement_id( $identifier ) {
	global $db;

	$query = $db->query( "SELECT `id` FROM `achievements__Achievements` WHERE `identifier`='$identifier'" );
	if ( $achievement = $db->assoc( $query ) ) {
		return intval( $achievement['id'] );
	}

	return 0;
}

session_start();

// check for database queued messages
if ( intval( $_SESSION['user_id'] ) > 0 ) {
	require( "class.Database.php" );
	require_once( "functions.php" );
	$db = &Database::getInstance();

	$user_id = $_SESSION['user_id'];

	/**
	 * Check for newly gained achievements:
	 */

	// global__PlayerInfo - distance
	$query = $db->query( "SELECT `distance`, `points`, `shots_fired`, `shots_hit`, `deaths`, `kills` FROM `global__PlayerInfo` WHERE `player_id`='$user_id'" );
	//echo $db->num_rows($query);
	if ( $db->num_rows( $query ) > 0 ) {
		$info = $db->assoc( $query );

		set_achievement( "RANK_PVT" );

		/** Distance Achievements **/
		if ( $info['distance'] > 10 ) {
			set_achievement( "BABY_STEPS" );
		}

		if ( $info['distance'] > 10000 ) {
			set_achievement( "MARATHON" );
		}

		if ( $info['distance'] > 100000 ) {
			set_achievement( "AROUNDTHEWORLD" );
		}

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

		$points = $info['points'];

		if ( $points > 19 ) {
			set_achievement( "RANK_KRP" );
		}

		if ( $points > 79 ) {
			set_achievement( "RANK_SGT" );
		}

		if ( $points > 179 ) {
			set_achievement( "RANK_MAJ" );
		}

		if ( $points > 319 ) {
			set_achievement( "RANK_ADJ" );
		}

		if ( $points > 499 ) {
			set_achievement( "RANK_LUI" );
		}

		if ( $points > 719 ) {
			set_achievement( "RANK_KPT" );
		}

		if ( $points > 979 ) {
			set_achievement( "RANK_KOL" );
		}

		if ( $points > 1279 ) {
			set_achievement( "RANK_GNM" );
		}

		if ( $points > 1619 ) {
			set_achievement( "RANK_LGN" );
		}

		if ( $points == 2000 ) {
			set_achievement( "HIGHEST_RANK" );
		}
	}

	/**
	 * accuracy & efficiency achievements
	 */
	if ( $info['shots_hit'] > 0 ) {
		$accuracy = ( 1 / $info['shots_fired'] ) * $info['shots_hit'];

		if ( $accuracy > 0.5 ) {
			set_achievement( "SHARP_SHOOTER" );
		}
	}


	if ( $info['kills'] > 0 ) {
		if ( $info['deaths'] * 5 < $info['kills'] ) {
			set_achievement( "HARD_TO_KILL" );
		}
	}

	/**
	 * Friends achievements
	 */

	$query = $db->query( "SELECT COUNT(*) AS `invites` FROM `global__Players` WHERE `invited_by`='$user_id'" );
	$info  = $db->assoc( $query );

	if ( intval( $info['invites'] ) >= 5 ) {
		set_achievement( "SOCIAL" );
	}

	/**
	 * Announce queued achievements:
	 */

	setlocale( LC_ALL, "nl_NL" );

	$query = $db->query( "SELECT c.`id`, c.`timestamp`, a.`title`, a.`description`, a.`difficulty`, a.`id` AS `achievement_id` FROM `achievements__Achievements` AS a INNER JOIN `achievements__Completed` as c ON a.`id`=c.`achievement_id` WHERE c.`player_id`='$user_id' AND c.`shown`=0 ORDER BY `timestamp` ASC" );
	if ( $achievement = $db->assoc( $query ) ) {
		// parse data to JSON
		echo "{'html':\"<h3>Prestatie: " . $achievement['title'] . "</h3><br />'" . $achievement['description'] . "'<br /><small>gehaald op " . strftime( "%e %B %Y om %l:%M %p", $achievement['timestamp'] ) . "</small>\", 'tell_chat':\"ach" . $achievement['achievement_id'] . "\"}";

		// update database
		$db->query( "UPDATE `achievements__Completed` SET `shown`=1 WHERE `id`=" . $achievement['id'] );

		// update points for achievement
		//$extra_points = 10 + ($achievement['difficulty'] * 10);

	}
}

?>
