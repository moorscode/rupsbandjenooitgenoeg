<?php

require_once("class.Database.php");
require("functions.php");

$db = &Database::getInstance();

/*
$readableBugStatus = array();
$readableBugStatus[0] = "Vastgesteld";
$readableBugStatus[1] = "Geanalyseerd";
$readableBugStatus[2] = "Test fase";
$readableBugStatus[3] = "Opgelost";
*/

$bugs = array();

$bug_query = $db->query("SELECT `id`, `type`, `description`, `timestamp` FROM `dev__KnownBugs` ORDER BY `type`, `timestamp` DESC");
while($bug = $db->assoc($bug_query)) {
	
	$bug_index = -1 + array_push($bugs, $bug);
	
	// get all status chances per bug
	// set latest status as current
	$status_history = array();
	$status_query = $db->query("SELECT `status`, `description`, `timestamp` FROM `dev__BugStatus` WHERE `bug_id`='{$bug['id']}' ORDER BY `timestamp` DESC");
	while($status = $db->assoc($status_query)) {
		array_push($status_history, $status);
	}
	
	$bugs[$bug_index]['status_history'] = $status_history;
	if(count($status_history) > 0) {
		$bugs[$bug_index]['status'] = $status_history[0]['status'];
	}
}

// show the list!
// kinda like the FAQ
setlocale(LC_ALL, 'nl_NL');

?>
<h2>Bekende Bugs</h2>

<ul class="bug">
<?php

foreach($bugs as $bug) {
//	$latest = strftime("%A %d %b %Y", $bug['last_changed']);
	$status = $readable_bug_status[$bug['status']];
	
	echo "<li onclick='showMyDiv(this);'><span class='status{$bug['status']}'>$status:</span> {$bug['description']}";
	echo "<div>";
	
	$status_history = "";
	foreach($bug['status_history'] as $status) {
		$time = strftime("%d %b %Y om %H:%M", $status['timestamp']);
		$status_text = $readable_bug_status[$status['status']];
		
		$status_history .= ($status_history != "")?"<br />":"";
		$status_history .= "Status: <span class='status{$status['status']}'>$status_text<br />{$status['description']}</span><br /><small>$time</small><br />";
	}
	
	$status_history .= "</div>";
	
	echo $status_history . "</li>";
}

?>
</ul>