<?php

require_once( "../../php/class.DisplayStatistics.php" );

setlocale( LC_ALL, "nl_NL" );

switch ( strtolower( $_GET['type'] ) ) {
	case "registration":
		$stats = new DisplayStatistics( "Registration" );
		$stats->timeRange( time() - DisplayStatistics::WEEK * 2, time(), DisplayStatistics::DAY );
		$stats->dataset( 'Uitnodigingen', array( "column" => "type", "value" => "INVITE" ) );
		$stats->dataset( 'Verificaties', array( "column" => "type", "value" => "VERIFY" ) );
		$stats->dataset( 'Registraties', array( "column" => "type", "value" => "REGISTER" ) );
		break;

	case "namechanges":
		$stats = new DisplayStatistics( "Username" );
		$stats->timeRange( time() - DisplayStatistics::WEEK, time(), DisplayStatistics::DAY );
		$stats->dataset( "Naam wijzigingen" );
		break;

	case "gamegames":
		$stats = new DisplayStatistics( "Games" );
		$stats->timeRange( time() - DisplayStatistics::WEEK, time(), DisplayStatistics::DAY );
		$stats->dataset( "Aantal gespeelde spellen", null, "gamesPlayed" );
		break;

	case "gamequeuetime":
		$stats = new DisplayStatistics( "Games" );
		$stats->type( "line" );
		$stats->maxCategoryLabels( 24 );
		$stats->timeRange( time() - DisplayStatistics::WEEK, time(), DisplayStatistics::HOUR * 2 );
		$stats->compareType( DisplayStatistics::COMPARE_AVERAGE );
		$stats->axisType( DisplayStatistics::DISPLAY_SECONDS );
		$stats->dataset( "Tijd in de wachtrij", null, "gameQueue" );
		break;

	case "practise":
		$stats = new DisplayStatistics( "Practise" );
		$stats->type( "line" );
		$stats->maxCategoryLabels( 24 );
		$stats->timeRange( time() - DisplayStatistics::WEEK, time(), DisplayStatistics::HOUR * 6 );
		$stats->dataset( "Oefenspellen" );
		break;

	case "serverdown":
		$stats = new DisplayStatistics( "Servers" );
		$stats->maxCategoryLabels( 14 );
		$stats->timeRange( time() - DisplayStatistics::WEEK, time(), DisplayStatistics::HOUR );
		$stats->compareType( DisplayStatistics::COMPARE_AVERAGE );
		$stats->axisType( DisplayStatistics::DISPLAY_SECONDS );
		$stats->dataset( "Chat offline tijd", array( "column" => "type", "value" => "chatserver" ), "serverDowntime" );
		$stats->dataset( "Game offline tijd", array( "column" => "type", "value" => "gameserver" ), "serverDowntime" );
		break;

	case "chatidle":
		$stats = new DisplayStatistics( "Chat" );
		$stats->type( "line" );
		$stats->maxCategoryLabels( 24 );
		$stats->timeRange( time() - DisplayStatistics::DAY * 2, time(), DisplayStatistics::HOUR );
		$stats->compareType( DisplayStatistics::COMPARE_AVERAGE );
		$stats->axisType( DisplayStatistics::DISPLAY_SECONDS );
		$stats->labelReadibleTime = true;
		$stats->dataset( "Idle tijd", null, "chatIdle" );
		$stats->parameters( "type", "IDLE" );
		break;

	case "chatusers":
		$stats = new DisplayStatistics( "Chat" );
		$stats->type( "line" );
		$stats->maxCategoryLabels( 24 );
		$stats->timeRange( time() - DisplayStatistics::DAY * 2, time(), DisplayStatistics::HOUR );
		$stats->dataset( "Speler joins", array( "column" => "type", "value" => "JOIN" ) );
		break;

	case "chatinput":
		$stats = new DisplayStatistics( "UserInput", "dev__" );
		$stats->timeRange( time() - DisplayStatistics::WEEK * 2, time(), DisplayStatistics::DAY );
		$stats->dataset( "Vragen", array( "column" => "phrase", "value" => "VRAAG" ) );
		$stats->dataset( "Bugs", array( "column" => "phrase", "value" => "BUG" ) );
		$stats->dataset( "Ideeen", array( "column" => "phrase", "value" => "IDEE" ) );
		break;

	case "rankchange":
		$stats = new DisplayStatistics( "RankChange" );
		$stats->timeRange( time() - DisplayStatistics::WEEK * 2, time(), DisplayStatistics::DAY );
		$stats->dataset( "Rang verandering" );
		break;

	case "gamekills":
		$stats = new DisplayStatistics( "PlayerKills" );
		$stats->timeRange( time() - DisplayStatistics::WEEK * 2, time(), DisplayStatistics::DAY );
		$stats->dataset( "Kills" );
		break;

	case "accuracy":
		$stats = new DisplayStatistics( "PlayerGame" );
		$stats->axisSuffix( "%" );
		$stats->timeRange( time() - DisplayStatistics::WEEK * 2, time(), DisplayStatistics::DAY );
		$stats->compareType( DisplayStatistics::COMPARE_AVERAGE );
		$stats->dataset( "Accuracy", null, "calculateAccuracy" );
		break;

	case "logins":
		$stats = new DisplayStatistics( "Login" );
		$stats->maxCategoryLabels( 32 );
		$stats->timeRange( time() - DisplayStatistics::WEEK * 4, time(), DisplayStatistics::DAY );
		$stats->dataset( "Mislukte logins", array( "column" => "player_id", "value" => "-1" ) );
		$stats->dataset( "Gelukte logins", array( "column" => "player_id", "value" => "-1", "operand" => "NOT" ) );
		break;

	case "achievements":
		$stats = new DisplayStatistics( "Completed", "achievements__" );
		$stats->maxCategoryLabels( 21 );
		$stats->timeRange( time() - DisplayStatistics::WEEK, time(), DisplayStatistics::HOUR * 6 );
		$stats->dataset( "Prestaties" );
		break;
}

if ( $stats ) {
	$stats->createXML();
}

/* Custom data functions */

function calculateAccuracy( $row, $data ) {
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$accuracy = 0;
	if ( $row['shots_fired'] > 0 && $row['shots_hit'] > 0 ) {
		$accuracy = ( 1 / ( $row['shots_fired'] / $row['shots_hit'] ) ) * 100;
	}

	array_push( $data, $accuracy );
}

function chatIdle( $row, $data ) {
	if ( ! is_array( $data ) ) {
		$data = array();
	}
	array_push( $data, $row['value'] );
}

function gamesPlayed( $row, $data ) {
	if ( intval( $row['starttime'] ) > 0 ) {
		$data = intval( $data ) + 1;
	}
}

function gameQueue( $row, $data ) {
	if ( ! is_array( $data ) ) {
		$data = array();
	}
	if ( $row['queuetime'] > 0 && ( $row['starttime'] > 0 || $row['endtime'] > 0 ) ) {
		array_push( $data, ( ( $row['starttime'] > 0 ) ? $row['starttime'] : $row['endtime'] ) - $row['queuetime'] );
	}
}

function serverDowntime( $row, $data ) {
	global $lastOffline;

	if ( $row['status'] == "OFFLINE" ) {
		$lastOffline = $row['timestamp'];
	}
	else if ( isset( $lastOffline ) ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		array_push( $data, $lastOffline - $row['timestamp'] );
	}
}

function secondsToMinutes( $value ) {
	return round( $value / 60 );
}

function secondsToHours( $value ) {
	return round( $value / 3600 );
}

