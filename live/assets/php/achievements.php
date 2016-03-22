<?php
/*
 * Achievement listing file
 *
 *	mar 13th, 2010 @ 15:00 pm
*/

function get_done_data($array, $achievement_id) {
	foreach($array as $achievement) {
		if($achievement['achievement_id'] == $achievement_id) {
			return $achievement;
		}
	}
}

function create_achievement($title, $description, $difficulty, $timestamp = -1) {
	$class = ($timestamp == -1)?'incomplete':'complete';
	
	$points = 10 + ($difficulty * 10);
	
	echo "<div class='achievement $class'><h1>$title ($points punten)</h1><br>$description";
	
	if($timestamp > -1) {
		setlocale(LC_TIME, "nl_NL");
		echo "<br>" . strftime("%e %B %Y om %l:%M %p", $timestamp);
	}
	
	echo "</div>\n";
}

session_start();

$done = array();
$done_ids = array();

$show_first = array();
$show_last = array();

// check for database queued messages
if(intval($_SESSION['user_id']) > 0) {
	require("class.Database.php");
	require_once("functions.php");
	$db = &Database::getInstance();
	
	$user_id = $_SESSION['user_id'];
	
	// list achievements, completed first.. rest later, ordered by difficulty easy -> hard
	$query = $db->query("SELECT `achievement_id`, `timestamp` FROM `achievements__Completed` WHERE `player_id`=$user_id");
	while($achieved = $db->assoc($query)) {
		array_push($done, $achieved);
		array_push($done_ids, $achieved['achievement_id']);
	}
	
	$query = $db->query("SELECT `id`, `title`, `description`, `difficulty` FROM `achievements__Achievements` ORDER BY `difficulty` ASC");
	while($achievement = $db->assoc($query)) {
		
		if(in_array($achievement['id'], $done_ids)) {
			// done
			array_push($show_first, $achievement);
		} else {
			// not done
			array_push($show_last, $achievement);
		}
	}
}

foreach($show_first as $achievement) {
	$done_data = get_done_data($done, $achievement['id']);
	create_achievement($achievement['title'], $achievement['description'], $achievement['difficulty'], $done_data['timestamp']);
}

foreach($show_last as $achievement) {
	create_achievement($achievement['title'], $achievement['description'], $achievement['difficulty']);
}
?>