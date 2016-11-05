<?php

session_start();

require_once( "functions.php" );
require_once( "class.Database.php" );

$db = &Database::getInstance();


$user_id = intval( $_SESSION['user_id'] );

if ( $user_id == 0 ) {
	return;
}

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {
	$lastCompletedLevel = intval( $_POST['lastCompletedLevel'] );
	$finished           = intval( $_POST['finished'] );

	$db->query( "UPDATE `global__Players` SET `lastCompletedLevel`='" . $lastCompletedLevel . "' WHERE `id`='" . $user_id . "'" );


	if ( $finished == 1 ) {
		// check for achievement:  
		$achievement_id = 0;

		$query = $db->query( "SELECT `id` FROM `achievements__Achievements` WHERE `identifier`='PREPARED'" );
		if ( $achievement = $db->assoc( $query ) ) {
			$achievement_id = intval( $achievement['id'] );
		}

		if ( $achievement_id > 0 ) {
			// check for existing entry:
			$query = $db->query( "SELECT `id` FROM `achievements__Completed` WHERE  `player_id`='$user_id' AND `achievement_id`=$achievement_id" );
			if ( $db->num_rows( $query ) == 0 ) {
				$db->query( "INSERT INTO `achievements__Completed` (`achievement_id`, `player_id`, `timestamp`) VALUES ('$achievement_id', '$user_id', UNIX_TIMESTAMP())" );
			}
		}
	}

	return;

}
else {
	$query = $db->query( "SELECT `lastCompletedLevel` FROM `global__Players` WHERE `id`='" . $user_id . "'" );
	if ( $data = $db->assoc( $query ) ) {
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		echo "<level>\n";
		echo "	<lastCompletedLevel>" . $data['lastCompletedLevel'] . "</lastCompletedLevel>\n";
		echo "</level>\n";
	}

	return;
}

