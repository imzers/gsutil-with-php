<?php
define('gm', true);
include('includes/include.php');
$gm->session_start();
$error = false;
$error_msg = array();

set_time_limit(72000);
ini_set("memory_limit", -1);

if (PHP_SAPI === 'cli') {
    $date = (isset($argv[1]) ? $argv[1] : '');
} else {
    $date = (isset($_POST['log']) ? $_POST['log'] : '');
}


//$date = gmdate('Y-m-d', time());


/* Change date spesific */
//$date = '2016-05-30';
/* Change date spesific */


#Connect to log database
if (!$gm->db_connect($gm->config['database']['mysql']['gsutil'], 'mysql')) {
	$gm->error('Cannot connect to shared database with MySQL Server.');
}
// # Get last date log from gsutil_schedule
$sql = "SELECT MAX(schedule_datelog) AS last_schedule_datelog FROM gsutil_schedule WHERE schedule_running = 'srinok'";
if (!$gm->db_query($sql, 'mysql')) {
	$gm->error("Cannot query for get last date schedule.");
}
if (!$gm->db_num_rows('mysql')) {
	$last_schedule_datelog = '2016-06-01';
} else {
	$row = $gm->db_fetch('mysql');
	$last_schedule_datelog = $row['last_schedule_datelog'];
}
$sql = "SELECT DATE_ADD('{$gm->sql_addslashes($last_schedule_datelog, 'mysql')}', INTERVAL 1 DAY) AS datelog_schedule";
if (!$gm->db_query($sql, 'mysql')) {
	$gm->error("Cannot query for get add date of schedule.");
}
$row = $gm->db_fetch('mysql');
/* Get date for datelog running */
$date = $row['datelog_schedule'];
$sql = "INSERT INTO gsutil_schedule(schedule_running, schedule_datelog, schedule_starting, schedule_stopping) VALUES('srinok', '{$gm->sql_addslashes($date, 'mysql')}', NOW(), NULL)";
if (!$gm->db_query($sql, 'mysql')) {
	$gm->error("Cannot query for insert new date log of schedule.");
}
$new_date_log_add = $gm->db_insert_id('mysql');




$date_selected = $gm->safe_text_post($date, 12);
echo "Get all game code on Date: {$date_selected}\n";
$game_codes = Array();
$sql = "SELECT DISTINCT product_code AS game_code FROM report_sales_perday ORDER BY game_code ASC";
if (!$gm->db_query($sql, 'mysql')) {
	$gm->error("Cannot query for get distinct game code.");
}
while ($row = $gm->db_fetch('mysql')) {
	$game_codes[] = $row['game_code'];
}
$game_code_sales = Array();
foreach ($game_codes as $gkey => $gval) {
	echo "Get all data of {$gval} on Date: {$date_selected}\n";
	$game_code_sales[$gval] = array();
	$sql = "SELECT * FROM report_sales_perday WHERE ((product_code = '{$gm->sql_addslashes($gval)}') AND (YEAR(sales_date) = YEAR('{$gm->sql_addslashes($date_selected)}') AND MONTH(sales_date) = MONTH('{$gm->sql_addslashes($date_selected)}'))) ORDER BY sales_date ASC";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for get all sales of date selected on game code: {$gval}");
	}
	if ($gm->db_num_rows('mysql')) {
		while ($row = $gm->db_fetch('mysql')) {
			$game_code_sales[$gval][] = $row;
		}
	}
}


/* print_r($game_code_sales); */
//exit;
echo "ALL JOB DONE! Yihaaaaa!!!\n";

/* Start this Line is only for 1 time work for updating KPIDB */
#Connect to KPIDB database
if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
	$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
}
$working = 0;
foreach ($game_code_sales as $vkey => $vcode) {
	if (!is_array($vcode)) {
		exit("Value is not an array.");
	}
	if (count($vcode) > 0) {
		foreach ($vcode as $k => $val) {
			$sql = "SELECT sales_seq FROM gsutil_sales_perday WHERE (sales_date = '{$val['sales_date']}' AND sales_product_code = '{$val['product_code']}' AND sales_currency = '{$val['sales_currency']}')";
			if (!$gm->db_query($sql, 'mssql', $gm->db_resource)) {
				$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Check Data Error: \n{$sql}");
				$gm->error("Cannot query for check duplicate from KPIDB.");
			}
			$checkrow = $gm->db_fetch('mssql');
			$checkrow_seq = $checkrow['sales_seq'];
			if (!$checkrow_seq) {
				$sql = "INSERT INTO gsutil_sales_perday(sales_date, sales_product_code, sales_currency, sales_price, sales_insert_datetime) VALUES('{$val['sales_date']}', '{$val['product_code']}', '{$val['sales_currency']}', '{$val['sales_price']}', GETDATE())";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Insert Error: \n{$sql}");
					$gm->error("Cannot query for perform insert new sales per day to KPIDB.");
				}
				/*
				if (!$gm->db_insert_id('mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Last Insert Error: \n");
					$gm->error('Cannot get last insert identity.');
				}
				*/
				//$new_insert_seq = $gm->db_fetch('mssql');
				//$new_insert_seq = $new_insert_seq['Ident'];
				$new_insert_seq = 0;
			} else {
				$new_insert_seq = $checkrow_seq;
			}
			/* Insert to allstore table */
			$sql = "SELECT sales_seq FROM allstore_sales_perday WHERE (sales_store_log = 'playstore' AND sales_date = '{$val['sales_date']}' AND sales_product_code = '{$val['product_code']}' AND sales_currency = '{$val['sales_currency']}')";
			if (!$gm->db_query($sql, 'mssql', $gm->db_resource)) {
				$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Check Data Error: \n{$sql}");
				$gm->error("Cannot query for check duplicate from KPIDB on allstore.");
			}
			$scheckrow = $gm->db_fetch('mssql');
			$scheckrow_seq = $scheckrow['sales_seq'];
			if (!$scheckrow_seq) {
				$sql = "INSERT INTO allstore_sales_perday(sales_store_log, sales_date, sales_product_code, sales_currency, sales_price, sales_insert_datetime) VALUES('playstore', '{$val['sales_date']}', '{$val['product_code']}', '{$val['sales_currency']}', '{$val['sales_price']}', GETDATE())";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Insert Error: \n{$sql}");
					$gm->error("Cannot query for perform insert new sales per day to KPIDB on allstore.");
				}
				/*
				if (!$gm->db_insert_id('mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Last Insert Error: \n");
					$gm->error('Cannot get last insert identity.');
				}
				*/
				//$new_insert_seq = $gm->db_fetch('mssql');
				//$new_insert_seq = $new_insert_seq['Ident'];
				$snew_insert_seq = 0;
			} else {
				$snew_insert_seq = $scheckrow_seq;
			}
			
			
			/*
			$sql = "SELECT * FROM gsutil_sales_perday WHERE sales_seq = {$new_insert_seq}";
			if (!$gm->db_query($sql, 'mssql')) {
				$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Select Error: \n{$sql}");
				$gm->error("Cannot query for check last insert id seq.");
			}
			$row = $gm->db_fetch('mssql');
			if ($row['sales_price'] !== $val['sales_price']) {
				$sql = "UPDATE gsutil_sales_perday SET sales_price = {$row['sales_price']} WHERE sales_seq = {$row['sales_seq']}";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Update Error: \n{$sql}");
					$gm->error("Cannot query for update latest sales seq id inserted.");
				}
			}
			*/
		}
	}
$working += 1;
}
#Connect to log database
if (!$gm->db_connect($gm->config['database']['mysql']['gsutil'], 'mysql')) {
	$gm->error('Cannot connect to shared database with MySQL Server.');
}
$sql = "UPDATE gsutil_schedule SET schedule_stopping = NOW() WHERE seq = {$gm->sql_addslashes($new_date_log_add, 'mysql')}";
if (!$gm->db_query($sql, 'mysql')) {
	$gm->error("Cannot query for Update new date log of schedule.");
}
echo "ALL JOB DONE! with {$working}!!!\n";
?>