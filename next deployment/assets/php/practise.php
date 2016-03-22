<?php

require("class.Database.php");
require("class.Statistics.php");


$stats = new Statistics();

$stats->add('IP', $_SERVER['REMOTE_ADDR']);
$stats->save('Practise');


?>