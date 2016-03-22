<?php

session_start();

require_once( "../assets/php/class.Database.php" );
require_once( "../assets/php/functions.php" );

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {
	$db = &Database::getInstance();
	if ( $_POST['email'] == "dikkeponk@hotmail.com" && $_POST['password'] == "peace" ) {
		$_SESSION['user_id']   = 1;
		$_SESSION['user_name'] = "Nooitgenoeg";

		$db->query( "UPDATE `global__Players` SET `PHPSESSION`='" . session_id() . "' WHERE `id`=1", true );

		header( "Location: ./" );

		return;
	}

}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>Admin Login</title>
</head>

<body>

<form action="<?php __FILE__ ?>" method="POST">
	<table>
		<tr>
			<td>u:</td>
			<td><input type="text" name="email" size="40"/></td>
		</tr>
		<tr>
			<td>p:</td>
			<td><input type="password" name="password" size="40"/></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" name="submit" value=" go "/></td>
		</tr>
	</table>
</form>


</body>
</html>