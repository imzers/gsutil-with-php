<?php
define('gm', true);
include('includes/include.php');
$gm->session_start();
$error = false;
$error_msg = array();

set_time_limit(72000);
ini_set('memory_limit', -1);

if (PHP_SAPI === 'cli') {
    $log = (isset($argv[1]) ? $argv[1] : '');
	$tmp = (isset($argv[2]) ? $argv[2] : '');
	$report = (isset($argv[3]) ? $argv[3] : '');
	$store = (isset($argv[3]) ? $argv[3] : '');
} else {
    $log = (isset($_POST['log']) ? $_POST['log'] : '');
	$tmp = (isset($_POST['tmp']) ? $_POST['tmp'] : '');
	$report = (isset($_POST['report']) ? $_POST['report'] : '');
	$store = (isset($_POST['store']) ? $_POST['store'] : '');
}
$log = $gm->safe_text_post($log, 512);
$tmp = $gm->safe_text_post($tmp, 512);
$report = $gm->safe_text_post($report, 32);
$store = $gm->safe_text_post($store, 3);
if (!file_exists($log)) {
	exit("File does not exists in server: ".$log);
}
if (!file_exists($tmp)) {
	exit("Dirrectory does not exists in server: ".$tmp);
}
$log_size = filesize($log) or die("Cannot check size of file");
if (!$log_size) {
	$error = true;
	$error_msg[] = "File containing zero size.";
}
$log_checksum = md5_file($log);
if (!$log_checksum) {
	$error = true;
	$error_msg[] = "Cannot check md5 checksum of log file.";
}
$new_list_seq = 0;
if (!$error) {
	$sql = "INSERT INTO gs_list(store, list_report, list_log_datetime, list_log_filepath, list_log_size, list_log_checksum) VALUES('{$gm->sql_addslashes($store)}', '{$gm->sql_addslashes($report)}', NOW(), '{$gm->sql_addslashes($log)}', {$gm->sql_addslashes($log_size)}, '{$gm->sql_addslashes($log_checksum)}')";
	if (!$gm->db_query($sql, 'mysql')) {
		$gm->error("Cannot query for insert new log of gsutil retrieving.");
	}
	$new_list_seq = $gm->db_insert_id('mysql');
}
$sql = "SELECT * FROM gs_list WHERE seq = {$gm->sql_addslashes($new_list_seq)}";
if (!$gm->db_query($sql)) {
	$gm->error('Cannot query for get data of gsutil new log.');
}
if (!$gs_list = $gm->db_fetch()) {
	$gm->error('The gsutil list log not exist anymore on database.');
}
$read_file = $gm->read_stream($log) or die("Cannot read stream file.");
if (!is_array($read_file)) {
	$error = true;
	$error_msg = "Read stream file does not return an array value.";
}
$count_inserted_gsuri = 0;
if (!$error) {
	$count_read_file = count($read_file);
	foreach ($read_file as $k => $val) {
		$sql = "INSERT INTO gs_list_gsuri(gsuri_list_seq, gsuri_type, gsuri_report, gsuri_address, gsuri_filepath, gsuri_fileext, gsuri_filesize, gsuri_filemime, gsuri_datetime) VALUES({$gm->sql_addslashes($new_list_seq)}, 'file', '{$gm->sql_addslashes($gs_list['list_report'])}', '{$gm->sql_addslashes($val)}', NULL, NULL, NULL, NULL, NOW())";
		if (!$gm->db_query($sql, 'mysql')) {
			$gm->error("Cannot query for insert new gsutil retrieving data.");
		}
		$count_inserted_gsuri++;
	}
}
echo "There is {$count_inserted_gsuri} gsuri insert to database.\n";
echo "Now downloading file form gsutil\n";
echo "--------------------------------------------\n";
$gsuri_filepaths = array();
$path_download = "{$tmp}";
$path_batch = $gm->config['batch']['download'];
$path_urladdress = array();
$sql = "SELECT seq, gsuri_list_seq, gsuri_report, gsuri_address FROM gs_list_gsuri WHERE gsuri_list_seq = {$gm->sql_addslashes($gs_list['seq'])}";
if (!$gm->db_query($sql)) {
	$gm->error('Cannot query for get list gsuri of log.');
}
while ($row = $gm->db_fetch()) {
	$path_urladdress[] = trim($row['gsuri_address']);
	$gsuri_address_filename = basename($row['gsuri_address']);
	$gsuri_filepaths[] = array(
		'seq' => "{$row['seq']}",
		'gsuri_filepath' => "{$path_download}/{$gsuri_address_filename}",
		);
}
$count_path_urladdress = count($path_urladdress);
$last_i = ($count_path_urladdress - 1);
for ($i = 0; $i < $count_path_urladdress; $i++) {
	if ($i !== $last_i) {
		$bat = escapeshellcmd("cmd /c \"{$path_batch}\" {$path_urladdress[$i]} {$path_download}");
		system($bat);
	} else {
		exec('C:\WINDOWS\system32\cmd.exe /c START '.$path_batch.' '.$path_urladdress[$i].' '.$path_download);
	}
}
if (!$error) {
	echo "--------------------------------------------\n";
	echo "Now updating filepath location of each downloading data\n";
	echo "--------------------------------------------\n";
	echo "\n\n\n";
	foreach ($gsuri_filepaths as $key => $fileval) {
		$file_path = $gm->custom_file_path($fileval['gsuri_filepath']);
		if (!$gm->custom_file_exists($file_path)) {
			$gsutiluri_file = array(
				'path' => $fileval['gsuri_filepath'],
				'dir' => '',
				'ext' => '',
				'size' => 0,
				'mime' => '',
				);
		} else {
			$gsutiluri_file = array(
				'path' => $file_path,
				'dir' => pathinfo($file_path, PATHINFO_DIRNAME),
				'ext' => pathinfo($file_path, PATHINFO_EXTENSION),
				'size' => filesize($file_path),
				'mime' => $gm->file_mime_type($file_path),
				);
		}
		if ($gsutiluri_file['size'] > 0) {
			$sql = "UPDATE gs_list_gsuri SET
				gsuri_filepath = '{$gm->sql_addslashes($gsutiluri_file['path'])}',
				gsuri_fileext = '{$gm->sql_addslashes($gsutiluri_file['ext'])}',
				gsuri_filesize = {$gm->sql_addslashes($gsutiluri_file['size'])},
				gsuri_filemime = '{$gm->sql_addslashes($gsutiluri_file['mime'])}'
				WHERE 
				seq = {$gm->sql_addslashes($fileval['seq'])}";
			if (!$gm->db_query($sql)) {
				$gm->error("Cannot query for update gsuri filepath location.\n" . $file_path . "\n\n");
			}
		}
	}
	echo "--------------------------------------------\n";
	echo "Read all file and extract if was an archived file\n";
	echo "--------------------------------------------\n";
	$file_to_read = array(
		'report' 	=> $report,
		'data'		=> array(
			'table' => '',
			'seq' => array(),
			'csv' => array(),
			),
		);
	$sql = "SELECT * FROM gs_list_gsuri WHERE (gsuri_list_seq = {$gm->sql_addslashes($gs_list['seq'])} AND (gsuri_filepath IS NOT NULL))";
	if (!$gm->db_query($sql)) { $gm->error('Cannot query for get all updated gsuri data.'); }
	while ($row = $gm->db_fetch('mysql')) {
		$file_to_read['data']['table'] = "gsutil_{$row['gsuri_report']}";
		$file_to_read['data']['seq'][] = $row['seq'];
		switch ($row['gsuri_report']) {
			case 'sales':
				$file_path_and_name = $row['gsuri_filepath'];
				$file_dir = trim(dirname($file_path_and_name));
				$file_dir = str_replace('/', DIRECTORY_SEPARATOR, $file_dir).DIRECTORY_SEPARATOR;
				$file_name = trim(basename($file_path_and_name));
				$file_path = ($file_dir . $file_name);
				$zip = new ZipArchive;
				if ($zip->open($file_path) === TRUE) {
					for($i = 0; $i < $zip->numFiles; $i++) {
						$zip_filename = $zip->getNameIndex($i);
						$zip_fileinfo = pathinfo($zip_filename);
						$zip_dir_name = ($file_dir . $zip_filename);
						copy("zip://".$file_path."#".$zip_filename, $zip_dir_name);
						echo "OK, {$file_path} extracted! As: {$zip_dir_name}\r\n";
						$file_to_read['data']['csv'][] = $gm->custom_file_path($zip_dir_name);
					}                  
					//$zip->extractTo($file_dir);
					$zip->close();
				} else {
					echo "Failed to extract!: {$file_path}\r\n";
				}
			break;
			case 'earnings':
				$file_path_and_name = $row['gsuri_filepath'];
				$file_dir = trim(dirname($file_path_and_name));
				$file_dir = str_replace('/', DIRECTORY_SEPARATOR, $file_dir).DIRECTORY_SEPARATOR;
				$file_name = trim(basename($file_path_and_name));
				$file_path = ($file_dir . $file_name);
				$zip = new ZipArchive;
				if ($zip->open($file_path) === TRUE) {
					for($i = 0; $i < $zip->numFiles; $i++) {
						$zip_filename = $zip->getNameIndex($i);
						$zip_fileinfo = pathinfo($zip_filename);
						$zip_dir_name = ($file_dir . $zip_filename);
						copy("zip://".$file_path."#".$zip_filename, $zip_dir_name);
						echo "OK, {$file_path} extracted! As: {$zip_dir_name}\r\n";
						$file_to_read['data']['csv'][] = $gm->custom_file_path($zip_dir_name);
					}                  
					//$zip->extractTo($file_dir);
					$zip->close();
				} else {
					echo "Failed to extract!: {$file_path}\r\n";
				}
			break;
			case 'installs':
				$file_to_read['data']['csv'][] = $gm->custom_file_path($row['gsuri_filepath']);
			break;
			case 'ratings':
				$file_to_read['data']['csv'][] = $gm->custom_file_path($row['gsuri_filepath']);
				
			break;
			case 'crashes':
				$file_to_read['data']['csv'][] = $gm->custom_file_path($row['gsuri_filepath']);
			break;
		}
	}
	echo "\n-----------------\n\n\n";
	print_r($file_to_read);
	echo "\n-----------------\n\n\n";
	
	
	if (isset($file_to_read['report']) && isset($file_to_read['data'])) {
		switch ($file_to_read['report']) {
			case 'earnings':
				$sql = "SELECT";
				echo "Data {$file_to_read['report']} is in progress to checking and insert....\n\n";
				foreach ($file_to_read['data']['csv'] as $csvKey => $csvVal) {
					$insert_new_csv = FALSE;
					$csvVal = $gm->custom_file_path($csvVal);
					$file_to_read_for_check = array(
						'path' => $csvVal,
						'dir' => pathinfo($csvVal, PATHINFO_DIRNAME),
						'name' => trim(basename($csvVal)),
						'ext' => pathinfo($csvVal, PATHINFO_EXTENSION),
						'size' => filesize($csvVal),
						'mime' => $gm->file_mime_type($csvVal),
						'checksum' => md5_file($csvVal),
						);
					$new_csv_insert_id = 0;
					$sql = "SELECT seq, file_tmp_checksum FROM gs_list_gsuri_file WHERE (file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}') ORDER BY seq DESC LIMIT 1";
					if (!$gm->db_query($sql)) { $gm->error('Cannot perform to check file properties and check-sum: duplicate check.'); }
					if (!$gm->db_num_rows()) {
						$insert_new_csv = TRUE;
					} else {
						$crow = $gm->db_fetch('mysql');
						if (strtolower($crow['file_tmp_checksum']) !== strtolower($file_to_read_for_check['checksum'])) {
							$insert_new_csv = TRUE;
						}
						$new_csv_insert_id = $crow['seq'];
					}
					if ($insert_new_csv) {
						$sql = "INSERT INTO gs_list_gsuri_file(file_gsuri_seq, file_list_seq, file_list_report, 
						file_tmp_name, file_tmp_path, file_tmp_dir, file_tmp_ext, file_tmp_mime, file_tmp_size, file_tmp_checksum, 
						file_datetime_inserted, file_datetime_updated, file_datetime_reading_starting, file_datetime_reading_stopping, file_rows) VALUES({$gm->sql_addslashes($file_to_read['data']['seq'][$csvKey])}, {$gm->sql_addslashes($gs_list['seq'])}, '{$gm->sql_addslashes($file_to_read['report'])}', 
						'{$gm->sql_addslashes($file_to_read_for_check['name'])}', '{$gm->sql_addslashes($file_to_read_for_check['path'])}', '{$gm->sql_addslashes($file_to_read_for_check['dir'])}', '{$gm->sql_addslashes($file_to_read_for_check['ext'])}', '{$gm->sql_addslashes($file_to_read_for_check['mime'])}', {$gm->sql_addslashes($file_to_read_for_check['size'])}, '{$gm->sql_addslashes($file_to_read_for_check['checksum'])}', 
						NOW(), NOW(), NULL, NULL, 0)";
						if (!$gm->db_query($sql)) {
							$gm->error('Cannot query for inserting new csv file to be ready in reading.');
						}
						$new_csv_insert_id = $gm->db_insert_id();
					}
					$sql = "SELECT f.*, g.seq AS gsuri_seq, l.seq AS list_seq
						FROM (( gs_list_gsuri_file AS f
						LEFT JOIN gs_list_gsuri AS g ON g.seq = f.file_gsuri_seq )
						LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq )
						WHERE f.seq = {$gm->sql_addslashes($new_csv_insert_id)}";
					if (!$gm->db_query($sql)) { $gm->error('Cannot query for perform get csv file path from database.'); }
					$grow = $gm->db_fetch('mysql');
					$csv_filepath = $gm->custom_file_path($grow['file_tmp_path']);
					$csv_fileread = $gm->custom_read_csv($csv_filepath);
					$csv_rows = 0;
					$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
					if (!$gm->db_query($sql)) {
						$gm->error("Cannot query for perform update csv file read starting.");
					}
					foreach ($csv_fileread as $cKey => $cVal) {
						$cVal = $gm->custom_array_key($cVal);
						$cVal['Transaction_Date'] = date('Y-m-d', strtotime($cVal['Transaction_Date']));
						$sql = "SELECT Count(seq) AS total_duplicate FROM {$file_to_read['data']['table']} 
							WHERE (LOWER(Description) = '".$gm->sql_addslashes(strtolower(trim($cVal['Description'])))."' AND LOWER(Sku_Id) = '".$gm->sql_addslashes(strtolower(trim($cVal['Sku_Id'])))."')";
						if (!$gm->db_query($sql)) {
							$gm->error("Cannot query for permorm check duplicate data of {$file_to_read['report']}.");
						}
						$duprow = $gm->db_fetch('mysql');
						$total_duplicate = $duprow['total_duplicate'];
						if (!$total_duplicate) {
							$sql = "INSERT INTO {$file_to_read['data']['table']}(earnings_file_seq, earnings_gsuri_seq, earnings_list_seq, 
							Description, Transaction_Date, Transaction_Time, Tax_Type, Transaction_Type, Refund_Type, Product_Title, Product_id, Product_Type, Sku_Id, Hardware, Buyer_Country, Buyer_State, Buyer_Postal_Code, Buyer_Currency, Amount_buyer_currency, Currency_Conversion_Rate, Merchant_Currency, Amount_mercant_currency) VALUES({$gm->sql_addslashes($grow['seq'])}, {$gm->sql_addslashes($grow['gsuri_seq'])}, {$gm->sql_addslashes($grow['list_seq'])}, 
							'{$gm->sql_addslashes($cVal['Description'])}', '{$gm->sql_addslashes($cVal['Transaction_Date'])}', '{$gm->sql_addslashes($cVal['Transaction_Time'])}', '{$gm->sql_addslashes($cVal['Tax_Type'])}', '{$gm->sql_addslashes($cVal['Transaction_Type'])}', '{$gm->sql_addslashes($cVal['Refund_Type'])}', '{$gm->sql_addslashes($cVal['Product_Title'])}', '{$gm->sql_addslashes($cVal['Product_id'])}', '{$gm->sql_addslashes($cVal['Product_Type'])}', '{$gm->sql_addslashes($cVal['Sku_Id'])}', '{$gm->sql_addslashes($cVal['Hardware'])}', '{$gm->sql_addslashes($cVal['Buyer_Country'])}', '{$gm->sql_addslashes($cVal['Buyer_State'])}', '{$gm->sql_addslashes($cVal['Buyer_Postal_Code'])}', '{$gm->sql_addslashes($cVal['Buyer_Currency'])}', '{$gm->sql_addslashes($cVal['Amount_(Buyer_Currency)'])}', '{$gm->sql_addslashes($cVal['Currency_Conversion_Rate'])}', '{$gm->sql_addslashes($cVal['Merchant_Currency'])}', '{$gm->sql_addslashes($cVal['Amount_(Merchant_Currency)'])}')";
							if (!$gm->db_query($sql)) {
								$gm->error("Cannot query for perform inserting csv file content one by one.");
							}
						}
						$csv_rows++;
					}
					$sql = "UPDATE gs_list_gsuri_file SET 
						file_rows = {$gm->sql_addslashes($csv_rows)},
						file_datetime_reading_stopping = NOW()
						WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
					if (!$gm->db_query($sql)) {
						$gm->error("Cannot query for perform update csv total rows reading.");
					}
				$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Job for earning is done at " . date('Y-m-d H:i:s');
						
					
				}
			
			break;
			case 'sales':
				$sql = "SELECT";
				echo "Data {$file_to_read['report']} is in progress to checking and insert....\n\n";
				foreach ($file_to_read['data']['csv'] as $csvKey => $csvVal) {
					$insert_new_csv = FALSE;
					$csvVal = $gm->custom_file_path($csvVal);
					$file_to_read_for_check = array(
						'path' => $csvVal,
						'dir' => pathinfo($csvVal, PATHINFO_DIRNAME),
						'name' => trim(basename($csvVal)),
						'ext' => pathinfo($csvVal, PATHINFO_EXTENSION),
						'size' => filesize($csvVal),
						'mime' => $gm->file_mime_type($csvVal),
						'checksum' => md5_file($csvVal),
						);
					$sql = "SELECT seq, file_tmp_checksum FROM gs_list_gsuri_file WHERE (file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}') ORDER BY seq DESC LIMIT 1";
					if (!$gm->db_query($sql)) { $gm->error('Cannot perform to check file properties and check-sum: duplicate check.'); }
					if (!$gm->db_num_rows()) {
						$insert_new_csv = TRUE;
					} else {
						$crow = $gm->db_fetch('mysql');
						if (strtolower($crow['file_tmp_checksum']) !== strtolower($file_to_read_for_check['checksum'])) {
							$insert_new_csv = TRUE;
						}
						$new_csv_insert_id = $crow['seq'];
					}
					if ($insert_new_csv) {
						$sql = "INSERT INTO gs_list_gsuri_file(file_gsuri_seq, file_list_seq, file_list_report, 
						file_tmp_name, file_tmp_path, file_tmp_dir, file_tmp_ext, file_tmp_mime, file_tmp_size, file_tmp_checksum, 
						file_datetime_inserted, file_datetime_updated, file_datetime_reading_starting, file_datetime_reading_stopping, file_rows) VALUES({$gm->sql_addslashes($file_to_read['data']['seq'][$csvKey])}, {$gm->sql_addslashes($gs_list['seq'])}, '{$gm->sql_addslashes($file_to_read['report'])}', 
						'{$gm->sql_addslashes($file_to_read_for_check['name'])}', '{$gm->sql_addslashes($file_to_read_for_check['path'])}', '{$gm->sql_addslashes($file_to_read_for_check['dir'])}', '{$gm->sql_addslashes($file_to_read_for_check['ext'])}', '{$gm->sql_addslashes($file_to_read_for_check['mime'])}', {$gm->sql_addslashes($file_to_read_for_check['size'])}, '{$gm->sql_addslashes($file_to_read_for_check['checksum'])}', 
						NOW(), NOW(), NULL, NULL, 0)";
						if (!$gm->db_query($sql)) {
							$gm->error('Cannot query for inserting new csv file to be ready in reading.');
						}
						$new_csv_insert_id = $gm->db_insert_id();
						$sql = "SELECT f.*, g.seq AS gsuri_seq, l.seq AS list_seq
							FROM (( gs_list_gsuri_file AS f
							LEFT JOIN gs_list_gsuri AS g ON g.seq = f.file_gsuri_seq )
							LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq )
							WHERE f.seq = {$gm->sql_addslashes($new_csv_insert_id)}";
						if (!$gm->db_query($sql)) { $gm->error('Cannot query for perform get csv file path from database.'); }
						$grow = $gm->db_fetch('mysql');
						$csv_filepath = $gm->custom_file_path($grow['file_tmp_path']);
						$csv_fileread = $gm->custom_read_csv($csv_filepath);
						$csv_rows = 0;
						$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
						if (!$gm->db_query($sql)) {
							$gm->error("Cannot query for perform update csv file read starting.");
						}
						foreach ($csv_fileread as $cKey => $cVal) {
							$cVal = $gm->custom_array_key($cVal);
							$cVal['Order_Charged_Date'] = date('Y-m-d', strtotime($cVal['Order_Charged_Date']));
							$cVal['Item_Price'] = $gm->custom_float_val($cVal['Item_Price'], '.');
							$cVal['Taxes_Collected'] = $gm->custom_float_val($cVal['Taxes_Collected'], '.');
							$cVal['Charged_Amount'] = $gm->custom_float_val($cVal['Charged_Amount'], '.');
							$sql = "SELECT Count(seq) AS total_duplicate FROM {$file_to_read['data']['table']} 
								WHERE (TRIM(LOWER(SKU_ID)) = '".$gm->sql_addslashes(strtolower(trim($cVal['SKU_ID'])))."' AND TRIM(CONCAT('', LOWER(Order_Number), '')) = '".$gm->sql_addslashes(strtolower(trim($cVal['Order_Number'])))."')";
							if (!$gm->db_query($sql)) {
								$gm->error("Cannot query for permorm check duplicate data of {$file_to_read['report']}.");
							}
							$duprow = $gm->db_fetch('mysql');
							$total_duplicate = $duprow['total_duplicate'];
							if (!$total_duplicate) {
								$sql = "INSERT INTO {$file_to_read['data']['table']}(sales_file_seq, sales_gsuri_seq, sales_list_seq, Order_Number, Order_Charged_Date, Order_Charged_Timestamp, Financial_Status, Device_Model, Product_Title, Product_ID, Product_Type, SKU_ID, Currency_of_Sale, Item_Price, Taxes_Collected, Charged_Amount, City_of_Buyer, State_of_Buyer, Postal_Code_of_Buyer, Country_of_Buyer) VALUES({$gm->sql_addslashes($grow['seq'])}, {$gm->sql_addslashes($grow['gsuri_seq'])}, {$gm->sql_addslashes($grow['list_seq'])}, 
								'{$gm->sql_addslashes($cVal['Order_Number'])}', '{$gm->sql_addslashes($cVal['Order_Charged_Date'])}', '{$gm->sql_addslashes($cVal['Order_Charged_Timestamp'])}', '{$gm->sql_addslashes($cVal['Financial_Status'])}', '{$gm->sql_addslashes($cVal['Device_Model'])}', '{$gm->sql_addslashes($cVal['Product_Title'])}', '{$gm->sql_addslashes($cVal['Product_ID'])}', '{$gm->sql_addslashes($cVal['Product_Type'])}', '{$gm->sql_addslashes($cVal['SKU_ID'])}', '{$gm->sql_addslashes($cVal['Currency_of_Sale'])}', CAST('{$gm->sql_addslashes($cVal['Item_Price'])}' AS Decimal(24,2)), CAST('{$gm->sql_addslashes($cVal['Taxes_Collected'])}' AS Decimal(24,2)), CAST('{$gm->sql_addslashes($cVal['Charged_Amount'])}' AS Decimal(24,2)), '{$gm->sql_addslashes($cVal['City_of_Buyer'])}', '{$gm->sql_addslashes($cVal['State_of_Buyer'])}', '{$gm->sql_addslashes($cVal['Postal_Code_of_Buyer'])}', '{$gm->sql_addslashes($cVal['Country_of_Buyer'])}')";
								if (!$gm->db_query($sql)) {
									$gm->error("Cannot query for perform inserting csv file content one by one.". $sql );
								}
							}
							$csv_rows++;
						}
						$sql = "UPDATE gs_list_gsuri_file SET 
							file_rows = {$gm->sql_addslashes($csv_rows)},
							file_datetime_reading_stopping = NOW()
							WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
						if (!$gm->db_query($sql)) {
							$gm->error("Cannot query for perform update csv total rows reading.");
						}
					}
						
					
				}
				
			break;
			case 'crashes':
				$sql = "SELECT";
				echo "Data {$file_to_read['report']} is in progress to checking and insert....\n\n";
				foreach ($file_to_read['data']['csv'] as $csvKey => $csvVal) {
					$insert_new_csv = FALSE;
					$csvVal = $gm->custom_file_path($csvVal);
					$file_to_read_for_check = array(
						'path' => $csvVal,
						'dir' => pathinfo($csvVal, PATHINFO_DIRNAME),
						'name' => trim(basename($csvVal)),
						'ext' => pathinfo($csvVal, PATHINFO_EXTENSION),
						'size' => filesize($csvVal),
						'mime' => $gm->file_mime_type($csvVal),
						'checksum' => md5_file($csvVal),
						);
					$sql = "SELECT seq, file_tmp_checksum FROM gs_list_gsuri_file WHERE (file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}') ORDER BY seq DESC LIMIT 1";
					if (!$gm->db_query($sql)) { $gm->error('Cannot perform to check file properties and check-sum: duplicate check.'); }
					if (!$gm->db_num_rows()) {
						$insert_new_csv = TRUE;
					} else {
						$crow = $gm->db_fetch('mysql');
						if (strtolower($crow['file_tmp_checksum']) !== strtolower($file_to_read_for_check['checksum'])) {
							$insert_new_csv = TRUE;
						}
						$new_csv_insert_id = $crow['seq'];
					}
					if ($insert_new_csv) {
						$sql = "INSERT INTO gs_list_gsuri_file(file_gsuri_seq, file_list_seq, file_list_report, 
						file_tmp_name, file_tmp_path, file_tmp_dir, file_tmp_ext, file_tmp_mime, file_tmp_size, file_tmp_checksum, 
						file_datetime_inserted, file_datetime_updated, file_datetime_reading_starting, file_datetime_reading_stopping, file_rows) VALUES({$gm->sql_addslashes($file_to_read['data']['seq'][$csvKey])}, {$gm->sql_addslashes($gs_list['seq'])}, '{$gm->sql_addslashes($file_to_read['report'])}', 
						'{$gm->sql_addslashes($file_to_read_for_check['name'])}', '{$gm->sql_addslashes($file_to_read_for_check['path'])}', '{$gm->sql_addslashes($file_to_read_for_check['dir'])}', '{$gm->sql_addslashes($file_to_read_for_check['ext'])}', '{$gm->sql_addslashes($file_to_read_for_check['mime'])}', {$gm->sql_addslashes($file_to_read_for_check['size'])}, '{$gm->sql_addslashes($file_to_read_for_check['checksum'])}', 
						NOW(), NOW(), NULL, NULL, 0)";
						if (!$gm->db_query($sql)) {
							$gm->error('Cannot query for inserting new csv file to be ready in reading.');
						}
						$new_csv_insert_id = $gm->db_insert_id();
						$sql = "SELECT f.*, g.seq AS gsuri_seq, l.seq AS list_seq
							FROM (( gs_list_gsuri_file AS f
							LEFT JOIN gs_list_gsuri AS g ON g.seq = f.file_gsuri_seq )
							LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq )
							WHERE f.seq = {$gm->sql_addslashes($new_csv_insert_id)}";
						if (!$gm->db_query($sql)) { $gm->error('Cannot query for perform get csv file path from database.'); }
						$grow = $gm->db_fetch('mysql');
						$csv_filepath = $gm->custom_file_path($grow['file_tmp_path']);
						$csv_fileread = $gm->custom_read_csv($csv_filepath);
						$csv_rows = 0;
						$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
						if (!$gm->db_query($sql)) {
							$gm->error("Cannot query for perform update csv file read starting.");
						}
						foreach ($csv_fileread as $cKey => $cVal) {
							/*$cVal = $gm->custom_array_key($cVal);*/
							print_r($cVal);
							echo "\n--\n";
							/*
							$cVal['Order_Charged_Date'] = date('Y-m-d', strtotime($cVal['Order_Charged_Date']));
							$cVal['Item_Price'] = $gm->custom_float_val($cVal['Item_Price'], '.');
							$cVal['Taxes_Collected'] = $gm->custom_float_val($cVal['Taxes_Collected'], '.');
							$cVal['Charged_Amount'] = $gm->custom_float_val($cVal['Charged_Amount'], '.');
							$sql = "SELECT Count(seq) AS total_duplicate FROM {$file_to_read['data']['table']} 
								WHERE (TRIM(LOWER(SKU_ID)) = '".$gm->sql_addslashes(strtolower(trim($cVal['SKU_ID'])))."' AND TRIM(CONCAT('', LOWER(Order_Number), '')) = '".$gm->sql_addslashes(strtolower(trim($cVal['Order_Number'])))."')";
							if (!$gm->db_query($sql)) {
								$gm->error("Cannot query for permorm check duplicate data of {$file_to_read['report']}.");
							}
							$duprow = $gm->db_fetch('mysql');
							$total_duplicate = $duprow['total_duplicate'];
							if (!$total_duplicate) {
								$sql = "INSERT INTO {$file_to_read['data']['table']}(sales_file_seq, sales_gsuri_seq, sales_list_seq, Order_Number, Order_Charged_Date, Order_Charged_Timestamp, Financial_Status, Device_Model, Product_Title, Product_ID, Product_Type, SKU_ID, Currency_of_Sale, Item_Price, Taxes_Collected, Charged_Amount, City_of_Buyer, State_of_Buyer, Postal_Code_of_Buyer, Country_of_Buyer) VALUES({$gm->sql_addslashes($grow['seq'])}, {$gm->sql_addslashes($grow['gsuri_seq'])}, {$gm->sql_addslashes($grow['list_seq'])}, 
								'{$gm->sql_addslashes($cVal['Order_Number'])}', '{$gm->sql_addslashes($cVal['Order_Charged_Date'])}', '{$gm->sql_addslashes($cVal['Order_Charged_Timestamp'])}', '{$gm->sql_addslashes($cVal['Financial_Status'])}', '{$gm->sql_addslashes($cVal['Device_Model'])}', '{$gm->sql_addslashes($cVal['Product_Title'])}', '{$gm->sql_addslashes($cVal['Product_ID'])}', '{$gm->sql_addslashes($cVal['Product_Type'])}', '{$gm->sql_addslashes($cVal['SKU_ID'])}', '{$gm->sql_addslashes($cVal['Currency_of_Sale'])}', CAST('{$gm->sql_addslashes($cVal['Item_Price'])}' AS Decimal(24,2)), CAST('{$gm->sql_addslashes($cVal['Taxes_Collected'])}' AS Decimal(24,2)), CAST('{$gm->sql_addslashes($cVal['Charged_Amount'])}' AS Decimal(24,2)), '{$gm->sql_addslashes($cVal['City_of_Buyer'])}', '{$gm->sql_addslashes($cVal['State_of_Buyer'])}', '{$gm->sql_addslashes($cVal['Postal_Code_of_Buyer'])}', '{$gm->sql_addslashes($cVal['Country_of_Buyer'])}')";
								if (!$gm->db_query($sql)) {
									$gm->error("Cannot query for perform inserting csv file content one by one.". $sql );
								}
							}
							$csv_rows++;
							*/
						}
						$sql = "UPDATE gs_list_gsuri_file SET 
							file_rows = {$gm->sql_addslashes($csv_rows)},
							file_datetime_reading_stopping = NOW()
							WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
						if (!$gm->db_query($sql)) {
							$gm->error("Cannot query for perform update csv total rows reading.");
						}
					}
				}
			break;
		}
		
		
		
		
	}




}




exit;
?>