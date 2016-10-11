<?php
define('gm', true);
include('php/includes/include.php');
$gm->session_start();
$error = false;
$error_msg = array();


if (PHP_SAPI === 'cli') {
    $year = (isset($argv[1]) ? $argv[1] : '');
} else {
    $year = (isset($_POST['log']) ? $_POST['log'] : '');
}
$year_selected = $gm->safe_text_post($year, 4);
$year_selected = (int)$year_selected;
$game_codes = Array();
$sql = "SELECT DISTINCT g.Product_ID AS game_code FROM gsutil_sales AS g ORDER BY game_code ASC";
if (!$gm->db_query($sql, 'mysql')) {
	$gm->error("Cannot query for get distinct game code.");
}
while ($row = $gm->db_fetch('mysql')) {
	$game_codes[] = $row['game_code'];
}
echo "Update sales each game per date before 2016-02-23\n";
foreach ($game_codes as $gkey => $gval) {
	$game_code_sales = Array();
	$sql = "SELECT gsutil_sales.Order_Charged_Date, gsutil_sales.Currency_of_Sale FROM gsutil_sales WHERE ((gsutil_sales.Product_ID = '{$gm->sql_addslashes($gval)}') AND (YEAR(gsutil_sales.Order_Charged_Date) = '{$gm->sql_addslashes($year_selected)}')) GROUP BY Order_Charged_Date, Currency_of_Sale ORDER BY Order_Charged_Date ASC";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for get all sales of year selected on game code.");
	}
	while ($row = $gm->db_fetch('mysql')) {
		$game_code_sales[] = $row;
	}
	foreach ($game_code_sales as $val) {
		$sql = "CALL get_report_sales_perday('{$gm->sql_addslashes($val['Order_Charged_Date'])}', '{$gm->sql_addslashes($gval)}', '{$gm->sql_addslashes($val['Currency_of_Sale'])}');";
		if (!$gm->db_query($sql, 'mysql')) {
			$gm->error("Cannot query for update sales report per day on selected date.");
		}
	}
}
?>