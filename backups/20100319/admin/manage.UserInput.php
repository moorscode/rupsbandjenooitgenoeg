<?php

//manage.UserInput.php
require_once("../assets/php/class.Database.php");
require_once("../assets/php/functions.php");

$db = &Database::getInstance();

$id = intval($_GET['id']);
if($id == 0) die();

$db->query("UPDATE `dev__UserInput` SET `looked_at`='1' WHERE `id`='$id'");

?>