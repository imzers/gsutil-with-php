<?php
define('gm', true);
include('php/includes/include.global.php');
$gm->session_start();
$error = false;
$error_msg = array();


$sql = "SELECT MAX(seq) AS max_seq FROM report_sales_perday";
if (!$gm->db_query($sql, 'mysql', $mysql['resource'])) {
	$gm->error('Cannot query for max seq report sales perday.');
}
while ($row = $gm->db_fetch('mssql')) {
	echo $row['max_seq'];
}


$sql = "SELECT * FROM xls_game_mobile";
if (!$gm->db_query($sql, 'mssql', $mssql['resource'])) {
	$gm->error('Cannot query for game mobile.');
}
while ($row = $gm->db_fetch('mssql')) {
	echo "--\n";
	print_r($row);
}
?>