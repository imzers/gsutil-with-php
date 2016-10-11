<?php
define('gm', true);
include('includes/include.php');
$gm->session_start();
$error = false;
$error_msg = array();

set_time_limit(72000);
ini_set("memory_limit", -1);

if (PHP_SAPI === 'cli') {
    $order_date = (isset($argv[1]) ? $argv[1] : date('Y-m-d'));
} else {
    $order_date = (isset($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d'));
}
$order_date = $gm->safe_text_post($order_date, 12);
if (!strtotime($order_date)) {
	$error = true;
	$error_msg[] = "Date of execute request is not valid, please using yyyy-mm-dd";
}

$order_date = date('Y-m-d', strtotime('-1 days'));

if (!$error) {
	#Connect to KPIDB database
	if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
		$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
	}
	$sales_data = Array();
	$sql = "SELECT * FROM gsutil_sales WHERE (CONVERT(Date, Order_Charged_Date) = '{$gm->sql_addslashes($order_date, 'mssql')}')";
	if (!$gm->db_query($sql, 'mssql')) {
		$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for get all data of gsutil sales on date: {$order_date} with query: {$sql}");
		$gm->error("Cannot query for get all data of gsutil sales on date: {$order_date}");
	}
	while ($row = $gm->db_fetch('mssql')) {
		$sales_data[] = $row;
	}
	$count_sales_data = count($sales_data);
	$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "[".date('Y-m-d H:i:s')."] There is {$count_sales_data} data to insert to sales tax");
	$data_to_insert = Array();
	#Connect to ManagerDB database
	if (!$gm->db_connect($gm->config['database']['mssql']['exchangerate'], 'mssql')) {
		$gm->error('Cannot connect to ManagerDb shared database with Microsoft SQL Server.');
	}
	for ($i = 0; $i < $count_sales_data; $i++) {
		$data_to_insert[$i] = Array(
			'order_date' => $sales_data[$i]['Order_Charged_Date'],
			'order_number' => $sales_data[$i]['Order_Number'],
			'product_code' => $sales_data[$i]['Product_ID'],
			'item_identification' => $sales_data[$i]['Order_Number'],
			'item_price' => $sales_data[$i]['Item_Price'],
			'item_amount' => 1,
			'item_price_total' => ($sales_data[$i]['Item_Price'] * 1),
			'item_currency' => $sales_data[$i]['Currency_of_Sale'],
			'exchange_sources' => array(),
		);
		$sql = "SELECT * FROM TB_ExchangeRate WHERE (CAST(YMD AS Datetime) = '" . $gm->sql_addslashes("{$order_date}T00:00:00.000", "mssql") ."' AND UPPER(CurrencyCode) = UPPER('{$gm->sql_addslashes($sales_data[$i]['Currency_of_Sale'], 'mssql')}'))";
		if (!$gm->db_query($sql, 'mssql')) {
			$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for get data of currency exchange: {$sql}");
			$gm->error('Cannot query for get data of currency exchange.');
		}
		if (!$gm->db_num_rows('mssql')) {
			$data_to_insert[$i]['exchange_sources'][0] = array(
				'src' => 'SCB', 
				'value' => 1,
				'data' => array(
					'exchange_source' => 'SCB',
					'exchange_currency' => 'THB',
					'exchange_amount' => 1,
					'exchange_price' => ($sales_data[$i]['Item_Price'] * 1),
					'tax_currency' => 'THB',
					'tax_percentage' => 0.07,
					'tax_price' => (($sales_data[$i]['Item_Price'] * 1) * 0.07),
					'tax_invoice_number' => '',
					'item_price_tax' => (($sales_data[$i]['Item_Price'] * 1) - (($sales_data[$i]['Item_Price'] * 1) * 0.07)),
				),
			);
		} else {
			while ($row = $gm->db_fetch('mssql')) {
				$exchange_amount = ((($row['Source'] == 'BOT' && $row['CurrencyCode'] == 'IDR') ? ($row['BuyNotes'] / 1000) : 
												(($row['Source'] == 'BOT' && $row['CurrencyCode'] == 'JPY') ? ($row['BuyNotes'] / 100) : 
													$row['BuyNotes'])));
				$data_to_insert[$i]['exchange_sources'][] = array(
					'src' => $row['Source'], 
					'value' => $row['BuyNotes'],
					'data' => array(
						'exchange_source' => $row['Source'],
						'exchange_currency' => 'THB',
						'exchange_amount' => $exchange_amount,
						'exchange_price' => ($sales_data[$i]['Item_Price'] * $exchange_amount),
						'tax_currency' => 'THB',
						'tax_percentage' => 0.07,
						'tax_price' => (($sales_data[$i]['Item_Price'] * $exchange_amount) * 0.07),
						'tax_invoice_number' => '',
						'item_price_tax' => (($sales_data[$i]['Item_Price'] * $exchange_amount) - (($sales_data[$i]['Item_Price'] * $exchange_amount) * 0.07)),
					),
				);
			}
		}
	}
	#Connect to KPIDB database
	if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
		$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
	}
	$insert_new = 0;
	Foreach ($data_to_insert as $val) {
		foreach ($val['exchange_sources'] as $exchangeVal) {
			$sql = "SELECT seq FROM gsutil_sales_tax WHERE (CONVERT(Date, order_date) = '{$gm->sql_addslashes($val['order_date'], 'mssql')}' AND order_number = '{$gm->sql_addslashes($val['order_number'], 'mssql')}') AND (UPPER(exchange_source) = UPPER('{$gm->sql_addslashes($exchangeVal['src'], 'mssql')}'))";
			if (!$gm->db_query($sql, 'mssql')) {
				$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for check seq of exists data: {$sql}");
				$gm->error('Cannot query for check seq of exists data.');
			}
			$row = $gm->db_fetch('mssql');
			$roseq = (int)$row['seq'];
			if ($roseq < 1) {
				$sql = "INSERT INTO gsutil_sales_tax(order_date, order_number, product_code, item_identification, item_price, item_amount, item_price_total, item_currency, 
				exchange_source, exchange_currency, exchange_amount, exchange_price, tax_currency, tax_percentage, tax_price, tax_invoice_number, item_price_tax, item_insert_datetime) VALUES('{$gm->sql_addslashes($val['order_date'], 'mssql')}', '{$gm->sql_addslashes($val['order_number'], 'mssql')}', '{$gm->sql_addslashes($val['product_code'], 'mssql')}', '{$gm->sql_addslashes($val['item_identification'], 'mssql')}', '{$gm->sql_addslashes($val['item_price'], 'mssql')}', '{$gm->sql_addslashes($val['item_amount'], 'mssql')}', '{$gm->sql_addslashes($val['item_price_total'], 'mssql')}', '{$gm->sql_addslashes($val['item_currency'], 'mssql')}', 
				'{$gm->sql_addslashes($exchangeVal['src'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['exchange_currency'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['exchange_amount'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['exchange_price'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['tax_currency'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['tax_percentage'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['tax_price'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['tax_invoice_number'], 'mssql')}', '{$gm->sql_addslashes($exchangeVal['data']['item_price_tax'], 'mssql')}', GETDATE())";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for insert new data tax item: {$sql}");
					$gm->error('Cannot query for insert new data tax item.');
				}
				$insert_new += 1;
			}
		}
	}
	echo "{$insert_new} added to database.";
}

	
	


?>