<?php

require_once("functions.php");
require_once("class.Database.php");

$db = &Database::getInstance();

$chat_server_online = (@exec("ps aux | grep rupsChatServer | grep -v grep") != "")?1:0;
$game_server_online = (@exec("ps aux | grep rupsGameServer | grep -v grep") != "")?1:0;

$query = "UPDATE `global__ServerStatus` SET `game`=$game_server_online, `chat`=$chat_server_online";
$db->query($query);

?>