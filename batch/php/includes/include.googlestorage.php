<?php
error_reporting(E_ALL);
ini_set('display_startup_errors', true);
ini_set('display_errors', true);
#set_magic_quotes_runtime(0);
$local_path = __DIR__;
include($local_path.'/config.php');
include($local_path.'/class.php');
if (isset($_POST['GLOBALS']) || isset($_FILES['GLOBALS']) || isset($_GET['GLOBALS']) || isset($_COOKIE['GLOBALS'])) {
	die ('Forbidden: 403.');
	}
$gm = new gmtools($config);
if (get_magic_quotes_gpc()) {
	$_REQUEST = $gm->stripslashes_deep($_REQUEST);
	$_POST = $gm->stripslashes_deep($_POST);
	$_GET = $gm->stripslashes_deep($_GET);
	$_COOKIE = $gm->stripslashes_deep($_COOKIE);
	}
# Maintenance
if ($gm->config['maintenance']['status'] > 0) {
	$gm->error('System Maintenance.');
}
# Open for allowing IP Addresses
/*
if (!in_array($gm->config['client']['user_proxy'], $gm->config['allow_ip'])) {
	$gm->error('System not allow your IP Address to access page.');
}
*/
#Connect to log database
if (!$gm->db_connect($gm->config['database']['mysql']['googlestorage'], 'mysql')) {
	$gm->error('Cannot connect to googlestorage database with MySQL Server.');
}
/*
#Connect to KPIDB database
if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
	$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
}
*/
?>