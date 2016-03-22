/*
<?php
session_start();
$loggedin = ( intval( $_SESSION['user_id'] ) == 0 ) ? 0 : 1;

?>
*/
var PHPSESSION = '<?= session_id() ?>';
var loggedin = parseInt('<?= $loggedin ?>');