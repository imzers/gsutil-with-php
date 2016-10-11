<?php
define('gm', true);
include('includes/include.googlestorage.php');
$gm->session_start();
$error = false;
$error_msg = array();

set_time_limit(72000);
ini_set("memory_limit", "512M");

/*
if (PHP_SAPI === 'cli') {
    $log = (isset($argv[1]) ? $argv[1] : '');
	$tmp = (isset($argv[2]) ? $argv[2] : '');
	$report = (isset($argv[3]) ? $argv[3] : '');
	$store = (isset($argv[4]) ? $argv[4] : '');
} else {
    $log = (isset($_POST['log']) ? $_POST['log'] : '');
	$tmp = (isset($_POST['tmp']) ? $_POST['tmp'] : '');
	$report = (isset($_POST['report']) ? $_POST['report'] : '');
	$store = (isset($_POST['store']) ? $_POST['store'] : '');
}
$log = $gm->safe_text_post($log, 512);
$tmp = $gm->safe_text_post($tmp, 512);
$report = $gm->safe_text_post($report, 32);
$store = $gm->safe_text_post($store, 3); //th, id, vn
*/


$report = array(
	//'gcm',
	//'crashes',
	'earnings',
	'installs', 
);
$store = 'th'; //th, id, vn
$bash_file = "C:/gsutil/batch/store/thailand/gsutil_th.bat";

$running_schedule = array();
foreach ($report as $valReport) {
	$last_datelog_for_report = '2012-12-31';
	$next_datelog_for_report = '2013-01-01';
	$sql = "SELECT MAX(schedule_datelog) AS last_datelog_for_report FROM gs_schedule WHERE";
	$sql .= " (LOWER(schedule_report) = LOWER('{$gm->sql_addslashes($valReport, 'mysql')}') AND (LOWER(schedule_country) = LOWER('{$gm->sql_addslashes($store, 'mysql')}')))";
	$sql .= " AND (schedule_status = 'success')";
	$sql .= " ORDER BY schedule_datelog DESC LIMIT 1";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for get last success report|{$gm->db_error()}");
	}
	while ($row = $gm->db_fetch('mysql')) {
		if ((!empty($row['last_datelog_for_report'])) && ($row['last_datelog_for_report'] !== '')) {
			$last_datelog_for_report = $row['last_datelog_for_report'];
		}
	}
	$sql = "SELECT DATE_ADD('{$gm->sql_addslashes($last_datelog_for_report, 'mysql')}', INTERVAL 1 DAY) AS next_datelog_for_report";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for get next datelog report|{$gm->db_error()}");
	}
	while ($row = $gm->db_fetch('mysql')) {
		$next_datelog_for_report = $row['next_datelog_for_report'];
	}
	
	# Check exists schedule
	$new_insert_seq = 0;
	$sql = "SELECT seq AS new_insert_seq FROM gs_schedule WHERE (schedule_running = 'log' AND (LOWER(schedule_country) = LOWER('{$gm->sql_addslashes($store, 'mysql')}')) AND (schedule_report = '{$gm->sql_addslashes($valReport, 'mysql')}')) AND (DATE(schedule_datelog) = DATE('{$gm->sql_addslashes($next_datelog_for_report, 'mysql')}'))";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for get if next datelog is exists or not|{$gm->db_error()}");
	}
	if (!$gm->db_num_rows()) {
		$sql = "INSERT INTO gs_schedule(schedule_running, schedule_datelog, schedule_starting, schedule_stopping, schedule_status, schedule_report, schedule_country) VALUES('log', '{$gm->sql_addslashes($next_datelog_for_report, 'mysql')}', NOW(), NULL, 'init', '{$gm->sql_addslashes($valReport, 'mysql')}', '{$gm->sql_addslashes($store, 'mysql')}')";
		if (!$gm->db_query($sql, 'mysql')) {
			$gm->error("Cannot query for insert new insert_seq|{$gm->db_error()}\r\n--\r\n{$sql}");
		}
		$new_insert_seq = $gm->db_insert_id('mysql');
	} else {
		$rowseq = $gm->db_fetch('mysql');
		$new_insert_seq = $rowseq['new_insert_seq'];
	}
	
	# get schedule_data
	$sql = "SELECT * FROM gs_schedule WHERE seq = {$gm->sql_addslashes($new_insert_seq, 'mysql')}";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for get the data of schedule_data|{$gm->db_error()}");
	}
	if (!$schedule_data = $gm->db_fetch('mysql')) {
		$gm->error("Scheduled data is not exists.");
	}
	# RUNNING Baby!
	$yearmonth_log = "";
	$sql = "SELECT DATE_FORMAT('{$gm->sql_addslashes($schedule_data['schedule_datelog'], 'mysql')}', '%Y%m') AS yearmonth_log";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for make yearmonth_log|{$gm->db_error()}");
	}
	if (!$yearmonth_data = $gm->db_fetch('mysql')) {
		$gm->error("There is no year month data from query database.");
	}
	$yearmonth_log = $yearmonth_data['yearmonth_log'];
	# Get all bundled id from store tables
	$sql = "SELECT * FROM gs_store_products WHERE (product_active = 'Y') AND (LOWER(store_country) = LOWER('{$gm->sql_addslashes($store, 'mysql')}')) ORDER BY product_order ASC";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for get product bundle id lists from store products|{$gm->db_error()}");
	}
	# Collect @running_schedule
	while ($row = $gm->db_fetch('mysql')) {
		$running_schedule[] = array(
			'report'			=> $schedule_data['schedule_report'],
			'product'			=> $row['product_bundle_id'],
			'datelog'			=> $yearmonth_log,
			'schedule_data'		=> $schedule_data,
			'bash'				=> $gm->custom_file_path($bash_file),
		);
	}
}


foreach ($running_schedule as $k => $val) {
	$val_obj = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	$sql = "UPDATE gs_schedule SET schedule_status = 'running' WHERE seq = {$gm->sql_addslashes($val['schedule_data']['seq'], 'mysql')}";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for update schedule status of seq|{$gm->db_error()}");
	}
	# Insert log
	$sql = "INSERT INTO gs_schedule_log(log_schedule_seq, log_schedule_status, log_schedule_data, log_insert) VALUES({$gm->sql_addslashes($val['schedule_data']['seq'], 'mysql')}, 'running', '{$gm->sql_addslashes($val_obj, 'mysql')}', NOW())";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for insert new schedule log|{$gm->db_error()}");
	}
	# Running python via @bash_file
	$bat = escapeshellcmd("cmd /c \"{$gm->custom_file_path($val['bash'])}\" {$val['report']} {$val['product']} {$val['datelog']}");
	system($bat);
	$sql = "UPDATE gs_schedule SET schedule_status = 'success', schedule_stopping = NOW() 
		WHERE seq = {$gm->sql_addslashes($val['schedule_data']['seq'], 'mysql')}";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for update schedule status to success of seq|{$gm->db_error()}");
	}
	# Insert log
	$sql = "INSERT INTO gs_schedule_log(log_schedule_seq, log_schedule_status, log_schedule_data, log_insert) VALUES({$gm->sql_addslashes($val['schedule_data']['seq'], 'mysql')}, 'success', '{$gm->sql_addslashes($val_obj, 'mysql')}', NOW())";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for insert new schedule log|{$gm->db_error()}");
	}
}


# Running python via bash
/*
for ($i = 0; $i < $count_path_urladdress; $i++) {
	if ($i !== $last_i) {
		$bat = escapeshellcmd("cmd /c \"{$path_batch}\" {$path_urladdress[$i]} {$path_download}");
		system($bat);
	} else {
		exec('C:\WINDOWS\system32\cmd.exe /c START '.$path_batch.' '.$path_urladdress[$i].' '.$path_download);
	}
}
*/