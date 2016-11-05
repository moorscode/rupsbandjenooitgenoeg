<?php

require_once( "functions.php" );
require_once( "class.Database.php" );
require_once( "shared.Servers.php" );

/**
 * @var Log file to save server output to
 */
$log_file = @fopen( "logs/gameserver/" . date( 'Y-m-d_H-i-s', time() ) . ".txt", "w" );

require_once( "class.GameServer.php" );

$game = new GameServer();
$game->connect();

