/*
<?php
session_start();
$forceSingle = (intval($_SESSION['user_id']) == 0)?1:0;

?>
*/
var PHPSESSION = '<?= session_id() ?>';
var forceSingle = parseInt('<?= $forceSingle ?>');