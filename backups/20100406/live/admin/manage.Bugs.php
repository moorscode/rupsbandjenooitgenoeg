<?php

require_once("../assets/php/class.Database.php");
require_once("../assets/php/functions.php");

$db = &Database::getInstance();

//manage.Bugs.php

$time = time();

if($_SERVER['REQUEST_METHOD'] == "POST") {
	$action = $_POST['action'];
	$id = intval($_POST['id']);
	
	$saved = false;
	
	switch($action) {
		case "add":
			$type 		 = $db->prepare(htmlspecialchars($_POST['type']), 32);
			$description = $db->prepare(htmlspecialchars($_POST['description']));
			
			$status 					= $db->prepare(htmlspecialchars($_POST['status']), 16);
			$status_description 	= $db->prepare(htmlspecialchars($_POST['status_description']));

			
			$query = "INSERT INTO `dev__KnownBugs` (`type`, `description`, `timestamp`) VALUES ('$type', '$description', $time)";
			$db->query($query);
			
			$bug = mysql_insert_id(); 
			
			$query = "INSERT INTO `dev__BugStatus` (`bug_id`, `status`, `description`, `timestamp`) VALUES ('$bug', '$status', '$status_description', '$time')";
			$db->query($query);
			
			$saved = true;
			
			break;
			
		case "edit":
			
			if($id == 0) {
				$saved = false;
			} else {
			
				$type 		 = $db->prepare(htmlspecialchars($_POST['type']), 32);
				$description = $db->prepare(htmlspecialchars($_POST['description']));
				
				$query = "UPDATE `dev__KnownBugs` SET `type`='$type', `description`='$description' WHERE `id`='$id'";
				$db->query($query);
				
				$saved = true;
				
			}
			
			break;
			
		case "add_status":
		
			if($id == 0) {
				$saved = false;
			} else {

				$status 					= $db->prepare(htmlspecialchars($_POST['status']), 16);
				$status_description 	= $db->prepare(htmlspecialchars($_POST['status_description']));
				
				$query = "INSERT INTO `dev__BugStatus` (`bug_id`, `status`, `description`, `timestamp`) VALUES ('$id', '$status', '$status_description', '$time')";
				$db->query($query);
				
				$saved = true;
			
			}
		
		case 'del':
			
			if($id == 0) {
				$saved = false;
			} else {
				$db->query("DELETE FROM `dev__BugStatus` WHERE `bug_id`='$id'");
				$db->query("DELETE FROM `dev__KnownBugs` WHERE `id`='$id'");
				$saved = true;
			}
				
			break;
	} // SWITCH
	
	if($saved) {
		die("{saved:true}");
	}
	
	die("{saved:false}");
} // POST

$id = intval($_GET['id']);
$action = $_GET['action'];


$select_options = "";

$status_list = array_reverse($readable_bug_status, true);
foreach($status_list as $index=>$status) {
	$select_options .= "<option value='$index'>$status</option>";
} // FOREACH

switch($action) {
	case "add":
	
		$select_options = str_replace("'0'>", "'0' selected>", $select_options);
		
		echo <<<EOADD
	<input type="hidden" name="action" value="$action" />
	
	<table>
	<tr>
		<td></td>
		<td><h2>Add new bug</h2></td>
	</tr><tr>
		<td align="right">Type: </td>
		<td><input type="text" name="type" maxlength="32" /></td>
	</tr><tr>
		<td align="right">Description: </td>
		<td><textarea name="description" rows="3" cols="45" /></textarea></td>
	</tr><tr>
		<td colspan="2">&nbsp;</td>
	</tr><tr>
		<td align="right">Status description: </td>
		<td><textarea name="status_description" rows="2" cols="45" /></textarea></td>
	</tr><tr>
		<td align="right">Status: </td>
		<td><select name="status">$select_options</select></td>
	</tr>
	</table>
EOADD;
		
		break;
		
	case "edit":
		
		if($id == 0) {
			die();	
		}
		
		$query = $db->query("SELECT `type`, `description` FROM `dev__KnownBugs` WHERE `id`='$id'");
		if($row = $db->assoc($query)) {
//			extract($row);
		
			echo <<<EOEDIT
	<input type="hidden" name="action" value="$action" />
	<input type="hidden" name="id" value="$id" />
	
	<table>
	<tr>
		<td></td>
		<td><h2>Edit bug</h2></td>
	</tr><tr>
		<td align="right">Type: </td>
		<td><input type="text" name="type" value="{$row['type']}" maxlength="32" /></td>
	</tr><tr>
		<td align="right">Description: </td>
		<td><textarea name="description" rows="5" cols="40">{$row['description']}</textarea></td>
	</tr>
	</table>
EOEDIT;
		}
	
		break;
	
	case "add_status":
	
		if($id == 0) {
			die();	
		}
		
		// select the next highest status as default:
		$highest_status = 0;
		$status_query = $db->query("SELECT `status` FROM `dev__BugStatus` WHERE `bug_id`='$id' ORDER BY `status` DESC LIMIT 0,1");
		if($status = $db->assoc($status_query)) {
			$highest_status = intval($status['status']);
		}
		$db->free($status_query);
		
		// apply selection to the list
		$select_status = $highest_status + (($highest_status < count($status_list) - 1)?1:0);
		$select_options = str_replace("'$select_status'>", "'$select_status' selected>", $select_options);
		
		echo <<<EOADD
	<input type="hidden" name="action" value="$action" />
	<input type="hidden" name="id" value="$id" />
	
	<table>
	<tr>
		<td></td>
		<td><h2>Add status</h2></td>
	</tr><tr>
		<td align="right">Status: </td>
		<td><select name="status">$select_options</select></td>
	</tr><tr>
		<td align="right">Status description: </td>
		<td><textarea name="status_description" rows="5" cols="40" /></textarea></td>
	</tr>
	</table>
EOADD;

		break;
		
} // SWITCH

?>