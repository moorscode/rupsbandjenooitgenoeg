<?php

require("class.Database.php");
require("functions.php");

$db = &Database::getInstance();

/** Send verification email **/

$list = $db->query("SELECT * FROM `global__Players` WHERE `verify` <> ''");
while($player = $db->assoc($list)) {
	$email = $player['email'];
	$verify = $player['verify'];
	
	$verify_email = file_get_contents("../html/templates/verify_email.html");
	$verify_email = str_replace("#EMAIL#", $email, $verify_email);
	$verify_email = str_replace("#VERIFY#", $verify, $verify_email);
	
	echo "sending email to: $email ($verify)<br />\n";
	email($email, "Bevestig je emailadres", $verify_email);
}

?>