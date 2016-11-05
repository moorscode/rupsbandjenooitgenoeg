<?php

require_once( "../assets/php/class.Database.php" );
require_once( "../assets/php/functions.php" );

$db = &Database::getInstance();

//manage.Faqs.php

$time = time();

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {
	$action = $_POST['action'];
	$id     = intval( $_POST['id'] );

	$saved = false;

	switch ( $action ) {
		case "add":
			$title       = $db->prepare( htmlspecialchars( $_POST['title'] ), 64 );
			$description = $db->prepare( htmlspecialchars( $_POST['description'] ) );

			$query = "INSERT INTO `global__Faqs` (`title`, `description`) VALUES ('$title', '$description')";
			$db->query( $query );

			$saved = true;

			break;

		case "edit":

			if ( $id == 0 ) {
				$saved = false;
			}
			else {

				$title       = $db->prepare( htmlspecialchars( $_POST['title'] ), 64 );
				$description = $db->prepare( htmlspecialchars( $_POST['description'] ) );

				$query = "UPDATE `global__Faqs` SET `title`='$title', `description`='$description' WHERE `id`='$id'";
				$db->query( $query );

				$saved = true;

			}

			break;

	} // SWITCH

	if ( $saved ) {
		die( "{saved:true}" );
	}

	die( "{saved:false}" );
} // POST

$id     = intval( $_GET['id'] );
$action = $_GET['action'];

switch ( $action ) {
	case "add":

		echo <<<EOADD
	<input type="hidden" name="action" value="$action" />
	
	<table>
	<tr>
		<td></td>
		<td><h2>Add new F.A.Q.</h2></td>
	</tr><tr>
		<td align="right">Title: </td>
		<td><input type="text" name="title" maxlength="64" size="41" /></td>
	</tr><tr>
		<td align="right">Description: </td>
		<td><textarea name="description" rows="7" cols="50" /></textarea></td>
	</tr>
	</table>
EOADD;

		break;

	case "edit":

		if ( $id == 0 ) {
			die();
		}

		$query = $db->query( "SELECT `title`, `description` FROM `global__Faqs` WHERE `id`='$id'" );
		if ( $row = $db->assoc( $query ) ) {
//			extract($row);

			echo <<<EOEDIT
	<input type="hidden" name="action" value="$action" />
	<input type="hidden" name="id" value="$id" />
	
	<table>
	<tr>
		<td></td>
		<td><h2>Edit F.A.Q.</h2></td>
	</tr><tr>
		<td align="right">Title: </td>
		<td><input type="text" name="title" value="{$row['title']}" maxlength="64" size="41" /></td>
	</tr><tr>
		<td align="right">Description: </td>
		<td><textarea name="description" rows="7" cols="50">{$row['description']}</textarea></td>
	</tr>
	</table>
EOEDIT;
		}

		break;

	case "del":

		if ( $id == 0 ) {
			die();
		}

		$db->query( "DELETE FROM `global__Faqs` WHERE `id`='$id'" );

		break;

} // SWITCH

