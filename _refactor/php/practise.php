<?php

session_start();

require( "class.Database.php" );
require( "class.Statistics.php" );

$stats = new Statistics();
$stats->add( 'player_id', $_SESSION['user_id'] );
$stats->add( 'IP', $_SERVER['REMOTE_ADDR'] );
$stats->save( 'Practise' );

