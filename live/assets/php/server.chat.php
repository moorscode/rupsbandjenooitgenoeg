<?php

require_once( "functions.php" );
require_once( "class.Database.php" );
require_once( "shared.Servers.php" );

/**
 * @var Log file to save server output to
 */
$log_file = @fopen( "logs/chatserver/" . date( 'Y-m-d_H-i-s', time() ) . ".txt", "w" );

require_once( "class.ChatServer.php" );

$chat = new ChatServer();
$chat->connect();

