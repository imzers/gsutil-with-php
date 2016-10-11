<?php
define('gm', true);
include('includes/include.googlestorage.php');
$gm->session_start();
$error = false;
$error_msg = array();

set_time_limit(72000);
ini_set("memory_limit", "2048M");


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
		$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot save new list of gs address txt file: {$sql}");
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
	$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "\n--------------------------------------------\nNow updating filepath location of each downloading data\n--------------------------------------------\n");	
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
	$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "\n--------------------------------------------\nRead all file and extract if was an archived file\n--------------------------------------------\n");
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
	$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", print_r($file_to_read, True));
	
	
	
	
	
	
	
	
	
	
	$is_insert_new = FALSE;
	if (isset($file_to_read['report']) && isset($file_to_read['data'])) {
		switch (strtolower($file_to_read['report'])) {
			case 'installs':
				if (count($file_to_read['data']['csv']) > 0) {
					foreach ($file_to_read['data']['csv'] as $csvKey => $csvVal) {
						#Connect to log database
						if (!$gm->db_connect($gm->config['database']['mysql']['googlestorage'], 'mysql')) {
							$gm->error('Cannot connect to googlestorage database with MySQL Server.');
						}
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
						$sql = "SELECT f.seq, f.file_tmp_checksum FROM gs_list_gsuri_file AS f LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq WHERE ((f.file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (f.file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND f.file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND f.file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}')) AND (l.store = '{$gm->sql_addslashes($gs_list['store'])}') ORDER BY f.seq DESC LIMIT 1";
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
							$csv_filestream = $gm->custom_str_getcsv($csv_filepath);
							$csv_fileread = array(); // Define as an array datatype
							$csv_rows = 0;
							/*
							*******************************************************
							You already get csv data on this stage
							*******************************************************
							*/
							if (count($csv_filestream) > 0) {
								foreach ($csv_filestream as $parentStream) {
									$csv_fileread[] = $parentStream;
								}
							}
							$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
							if (!$gm->db_query($sql)) {
								$gm->error("Cannot query for perform update csv file read starting.");
							}
							if ((int)$grow['file_datetime_reading_stopping'] == 0) {
								$is_insert_new = TRUE;
							}
							$product_report_tables = array(
								'app_version.csv',
								'carrier.csv',
								'device.csv',
								'language.csv',
								'os_version.csv',
								'overview.csv',
								'tablets.csv',
								'country.csv',
							);
							$doing_insert_table = "";
							if ($is_insert_new) {
								$doing_insert_new = FALSE;
								$grow_file_tmp_name = explode('_', $grow['file_tmp_name']);
								if (count($grow_file_tmp_name) > 0) {
									$product_report_code = (isset($grow_file_tmp_name[1]) ? $grow_file_tmp_name[1] : 'com.mobicle.sf2.th');
									$product_report_month = (isset($grow_file_tmp_name[2]) ? $grow_file_tmp_name[2] : '201601');
									$product_report_table = (isset($grow_file_tmp_name[4]) ? "{$grow_file_tmp_name[3]}_{$grow_file_tmp_name[4]}" : (isset($grow_file_tmp_name[3]) ? $grow_file_tmp_name[3] : 'app_version.csv'));
									if (in_array($product_report_table, $product_report_tables)) {
										$doing_insert_new = TRUE;
										$product_report_table_name = explode(".", $product_report_table);
										$doing_insert_table = "{$file_to_read['report']}_{$product_report_table_name[0]}";
									}
									# Do insert new
									if ($doing_insert_new) {
										$countData = array(
											'all'		=> 0,
										);
										$sqlPattern = "";
										$sqlPattern .= "INSERT IGNORE INTO {$doing_insert_table}";
										switch ($doing_insert_table) {
											case "{$file_to_read['report']}_app_version":
												$sqlPattern .= "(app_version_Date, app_version_PackageName, app_version_AppVersionCode, app_version_CurrentDeviceInstalls, app_version_DailyDeviceInstalls, app_version_DailyDeviceUninstalls, app_version_DailyDeviceUpgrades, app_version_CurrentUserInstalls, app_version_TotalUserInstalls, app_version_DailyUserInstalls, app_version_DailyUserUninstalls, app_version_ActiveDeviceInstalls, app_version_DateInsert)";
											break;
											case "{$file_to_read['report']}_carrier":
												$sqlPattern .= "(carrier_Date, carrier_PackageName, carrier_Carrier, carrier_CurrentDeviceInstalls, carrier_DailyDeviceInstalls, carrier_DailyDeviceUninstalls, carrier_DailyDeviceUpgrades, carrier_CurrentUserInstalls, carrier_TotalUserInstalls, carrier_DailyUserInstalls, carrier_DailyUserUninstalls, carrier_ActiveDeviceInstalls, carrier_DateInsert)";
											break;
											case "{$file_to_read['report']}_country":
												$sqlPattern .= "(country_Date, country_PackageName, country_Country, country_CurrentDeviceInstalls, country_DailyDeviceInstalls, country_DailyDeviceUninstalls, country_DailyDeviceUpgrades, country_CurrentUserInstalls, country_TotalUserInstalls, country_DailyUserInstalls, country_DailyUserUninstalls, country_ActiveDeviceInstalls, country_DateInsert)";
											break;
											case "{$file_to_read['report']}_device":
												$sqlPattern .= "(device_Date, device_PackageName, device_Device, device_CurrentDeviceInstalls, device_DailyDeviceInstalls, device_DailyDeviceUninstalls, device_DailyDeviceUpgrades, device_CurrentUserInstalls, device_TotalUserInstalls, device_DailyUserInstalls, device_DailyUserUninstalls, device_ActiveDeviceInstalls, device_DateInsert)";
											break;
											case "{$file_to_read['report']}_language":
												$sqlPattern .= "(language_Date, language_PackageName, language_Language, language_CurrentDeviceInstalls, language_DailyDeviceInstalls, language_DailyDeviceUninstalls, language_DailyDeviceUpgrades, language_CurrentUserInstalls, language_TotalUserInstalls, language_DailyUserInstalls, language_DailyUserUninstalls, language_ActiveDeviceInstalls, language_DateInsert)";
											break;
											case "{$file_to_read['report']}_os_version":
												$sqlPattern .= "(os_version_Date, os_version_PackageName, os_version_AndroidOSVersion, os_version_CurrentDeviceInstalls, os_version_DailyDeviceInstalls, os_version_DailyDeviceUninstalls, os_version_DailyDeviceUpgrades, os_version_CurrentUserInstalls, os_version_TotalUserInstalls, os_version_DailyUserInstalls, os_version_DailyUserUninstalls, os_version_ActiveDeviceInstalls, os_version_DateInsert)";
											break;
											case "{$file_to_read['report']}_overview":
												$sqlPattern .= "(overview_Date, overview_PackageName, overview_CurrentDeviceInstalls, overview_DailyDeviceInstalls, overview_DailyDeviceUninstalls, overview_DailyDeviceUpgrades, overview_CurrentUserInstalls, overview_TotalUserInstalls, overview_DailyUserInstalls, overview_DailyUserUninstalls, overview_ActiveDeviceInstalls, overview_DateInsert)";
											break;
											case "{$file_to_read['report']}_tablets":
												$sqlPattern .= "(tablets_Date, tablets_PackageName, tablets_Tablets, tablets_CurrentDeviceInstalls, tablets_DailyDeviceInstalls, tablets_DailyDeviceUninstalls, tablets_DailyDeviceUpgrades, tablets_CurrentUserInstalls, tablets_TotalUserInstalls, tablets_DailyUserInstalls, tablets_DailyUserUninstalls, tablets_ActiveDeviceInstalls, tablets_DateInsert)";
											break;
										}
										# Make a whitespace
										$sqlPattern .= " VALUES ";
										$count_csv_fileread = count($csv_fileread);
										if ($count_csv_fileread > 0) {
											foreach ($csv_fileread as $parentKey => $parentVal) {
												if (($parentKey > 0) && is_array($parentVal)) {
													$countData['all'] = count($parentVal);
													if ($countData['all'] > 0) {
														$sqlPattern .= "(";
														foreach ($parentVal as $key => $cVal) {
															$cVal = $gm->safe_text_post($cVal, 256);
															$sqlPattern .= "'{$gm->sql_addslashes($cVal, 'mysql')}',";
															$csv_rows += 1;
														}
														if ("{$product_report_month}" < '201609') {
															$sqlPattern .= " 0, NOW()";
														} else {
															$sqlPattern .= " NOW()";
														}
														if ($parentKey < ($count_csv_fileread - 1)) {
															$sqlPattern .= "), ";
														} else {
															$sqlPattern .= ")";
														}
													}
												}
											}
										}
										if (!$gm->db_query($sqlPattern, 'mysql')) {
											$gm->custom_write_log("C:/gsutil/tmp/{$file_to_read['report']}/{$doing_insert_table}_{$file_to_read['report']}.log", "{$gm->db_error()}\r\n-------\r\n\n{$sqlPattern}");
											$gm->error("Cannot query for insert ignore new data of report: {$file_to_read['report']} with table: {$doing_insert_table}");
										}
									}
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
					}
					/* One time process to update KPIDB 
					*******************************************************/
					$kpidb_product_report_tables = array(
						'app_version',
						'carrier',
						'device',
						'language',
						'os_version',
						'overview',
						'tablets',
						'country',
					);
					foreach ($kpidb_product_report_tables as $kpidb_product_report_table) {
						#Connect to log database
						if (!$gm->db_connect($gm->config['database']['mysql']['googlestorage'], 'mysql')) {
							$gm->error('Cannot connect to googlestorage database with MySQL Server.');
						}
						$kpidb_doing_insert_table = "installs_{$kpidb_product_report_table}";
						# START MAKE A INSERT
						$kpidb_data_to_kpidb = Array();
						$sql = "SELECT * FROM {$gm->sql_addslashes($kpidb_doing_insert_table, 'mysql')} WHERE (DATE({$kpidb_product_report_table}_DateInsert) = DATE(NOW()))";
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "\r\n---\r\n{$sql}\r\n");
						if (!$gm->db_query($sql, 'mysql')) {
							$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for get data of inserted {$kpidb_product_report_table_name[0]} at this datetime today|{$sql}\r\n---\r\n{$gm->db_error()}\r\n");
							$gm->error("Cannot query for data of inserting today datetime.");
						}
						while ($pokpokporow = $gm->db_fetch('mysql')) {
							$kpidb_data_to_kpidb[] = $pokpokporow;
						}
						//$kpidb_data_to_kpidb_json = json_encode($kpidb_data_to_kpidb, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
						//$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d')."_json.txt", "\r\n---\r\n{$kpidb_data_to_kpidb_json}\r\n");
						#Connect to KPIDB database
						if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
							$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
						}
						$kpidb_update_to_kpidb = update_to_KPIDB($file_to_read['report'], $kpidb_data_to_kpidb, "gsutil_{$kpidb_doing_insert_table}");
						if ($update_to_kpidb > 0) {
							$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Already done update to KPIDB from gsutil about {$kpidb_update_to_kpidb} rows.");
						} else {
							$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Data return of update on KPIDP function is 0 rows");
						}
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Job already done success and good!");
						#Connect to log database
						if (!$gm->db_connect($gm->config['database']['mysql']['googlestorage'], 'mysql')) {
							$gm->error('Cannot connect to googlestorage database with MySQL Server.');
						}
					}
					/* One time process to update KPIDB 
					*******************************************************/
				}
			break;
			case 'earnings':
				#Connect to log database
				if (!$gm->db_connect($gm->config['database']['mysql']['googlestorage'], 'mysql')) {
					$gm->error('Cannot connect to googlestorage database with MySQL Server.');
				}
				$sql = "SELECT";
				echo "Data {$file_to_read['report']} is in progress to checking and insert....\n\n";
				if (count($file_to_read['data']['csv']) > 0) {
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
						$sql = "SELECT f.seq, f.file_tmp_checksum FROM gs_list_gsuri_file AS f LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq WHERE ((f.file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (f.file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND f.file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND f.file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}')) AND (l.store = '{$gm->sql_addslashes($gs_list['store'])}') ORDER BY f.seq DESC LIMIT 1";
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
							$csv_rows = 0;
							$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
							if (!$gm->db_query($sql)) {
								$gm->error("Cannot query for perform update csv file read starting.");
							}
							$csv_filestream = $gm->custom_str_getcsv($csv_filepath);
							$csv_fileread = array(); // Define as an array datatype
							$csv_rows = 0;
							/*
							*******************************************************
							You already get csv data on this stage
							*******************************************************
							*/
							if (count($csv_filestream) > 0) {
								foreach ($csv_filestream as $parentStream) {
									$csv_fileread[] = $parentStream;
								}
							}
							
							
							foreach ($csv_fileread as $keval) {
								$sql = "INSERT IGNORE INTO earnings_PlayApps
									SET 
									earnings_Description = '{$gm->sql_addslashes($keval[0], 'mysql')}',
									earnings_TransactionDate = '{$gm->sql_addslashes($keval[1], 'mysql')}',
									earnings_TransactionTime = '{$gm->sql_addslashes($keval[2], 'mysql')}',
									earnings_TaxType = '{$gm->sql_addslashes($keval[3], 'mysql')}',
									earnings_TransactionType = '{$gm->sql_addslashes($keval[4], 'mysql')}',
									earnings_RefundType = '{$gm->sql_addslashes($keval[5], 'mysql')}',
									earnings_ProductTitle = '{$gm->sql_addslashes($keval[6], 'mysql')}',
									earnings_Productid = '{$gm->sql_addslashes($keval[7], 'mysql')}',
									earnings_ProductType = '{$gm->sql_addslashes($keval[8], 'mysql')}',
									earnings_SkuId = '{$gm->sql_addslashes($keval[9], 'mysql')}',
									earnings_Hardware = '{$gm->sql_addslashes($keval[10], 'mysql')}',
									earnings_BuyerCountry = '{$gm->sql_addslashes($keval[11], 'mysql')}',
									earnings_BuyerState = '{$gm->sql_addslashes($keval[12], 'mysql')}',
									earnings_BuyerPostalCode = '{$gm->sql_addslashes($keval[13], 'mysql')}',
									earnings_BuyerCurrency = '{$gm->sql_addslashes($keval[14], 'mysql')}',
									earnings_Amount_BuyerCurrency = '{$gm->sql_addslashes($keval[15], 'mysql')}',
									earnings_Currency_ConversionRate = '{$gm->sql_addslashes($keval[16], 'mysql')}',
									earnings_MerchantCurrency = '{$gm->sql_addslashes($keval[17], 'mysql')}',
									earnings_Amount_MerchantCurrency = '{$gm->sql_addslashes($keval[18], 'mysql')}',
									earnings_DateInsert = NOW()
									";
								if (!$gm->db_query($sql)) {
									$gm->error("Cannot query for perform insert ignore all data captured in stream csv.");
								}	
									
								
								
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
					/* One time process to update KPIDB: earnings
					*******************************************************/
					#Connect to log database
					if (!$gm->db_connect($gm->config['database']['mysql']['googlestorage'], 'mysql')) {
						$gm->error('Cannot connect to googlestorage database with MySQL Server.');
					}
					$sql = "SELECT * FROM earnings_PlayApps WHERE (DATE(earnings_DateInsert) = DATE(NOW()))";
					if (!$gm->db_query($sql, 'mysql')) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for get data of inserted earnings at this datetime today.");
						$gm->error("Cannot query for data of inserting today datetime.");
					}
					while ($pokpokporow = $gm->db_fetch('mysql')) {
						$data_to_kpidb[] = $pokpokporow;
					}
					#Connect to KPIDB database
					if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
						$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
					}
					$update_to_kpidb = update_to_KPIDB($file_to_read['report'], $data_to_kpidb, 'gsutil_earnings_playapps');
					if ($update_to_kpidb > 0) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Already done update to KPIDB from gsutil about {$update_to_kpidb} rows.");
					} else {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Data return of update on KPIDP function is 0 rows");
					}
					
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Job already done success and good!");
					/* One time process to update KPIDB: earnings
					*******************************************************/
				}
			break;
			case 'sales':
				$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Sudah sampai disini sales nya");
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
					$sql = "SELECT f.seq, f.file_tmp_checksum FROM gs_list_gsuri_file AS f LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq WHERE ((f.file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (f.file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND f.file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND f.file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}')) AND (l.store = '{$gm->sql_addslashes($gs_list['store'])}') ORDER BY f.seq DESC LIMIT 1";
					if (!$gm->db_query($sql)) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot perform to check file properties and check-sum: duplicate check.");
						$gm->error('Cannot perform to check file properties and check-sum: duplicate check.');
						}
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
							$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for inserting new csv file to be ready in reading.");
							$gm->error('Cannot query for inserting new csv file to be ready in reading.');
						}
						$new_csv_insert_id = $gm->db_insert_id();
					}
					$sql = "SELECT f.*, g.seq AS gsuri_seq, l.seq AS list_seq
						FROM (( gs_list_gsuri_file AS f
						LEFT JOIN gs_list_gsuri AS g ON g.seq = f.file_gsuri_seq )
						LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq )
						WHERE f.seq = {$gm->sql_addslashes($new_csv_insert_id)}";
					if (!$gm->db_query($sql)) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for perform get csv file path from database.");
						$gm->error('Cannot query for perform get csv file path from database.'); 
					}
					$grow = $gm->db_fetch('mysql');
					$csv_filepath = $gm->custom_file_path($grow['file_tmp_path']);
					$csv_fileread = $gm->custom_read_csv($csv_filepath);
					$csv_rows = 0;
					$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
					if (!$gm->db_query($sql)) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for perform update csv file read starting.");
						$gm->error("Cannot query for perform update csv file read starting.");
					}
					if ((int)$grow['file_datetime_reading_stopping'] == 0) {
						$is_insert_new = TRUE;
					}
					if ($is_insert_new) {
						foreach ($csv_fileread as $cKey => $cVal) {
							$cVal = $gm->custom_array_key($cVal);
							$cVal['Order_Charged_Date'] = date('Y-m-d', strtotime($cVal['Order_Charged_Date']));
							$cVal['Item_Price'] = $gm->custom_float_val($cVal['Item_Price'], '.');
							$cVal['Taxes_Collected'] = $gm->custom_float_val($cVal['Taxes_Collected'], '.');
							$cVal['Charged_Amount'] = $gm->custom_float_val($cVal['Charged_Amount'], '.');
							$sql = "SELECT Count(seq) AS total_duplicate FROM {$file_to_read['data']['table']} 
								WHERE (TRIM(LOWER(SKU_ID)) = '".$gm->sql_addslashes(strtolower(trim($cVal['SKU_ID'])))."' AND TRIM(CONCAT('', LOWER(Order_Number), '')) = '".$gm->sql_addslashes(strtolower(trim($cVal['Order_Number'])))."')";
							if (!$gm->db_query($sql)) {
								$gm->error("Cannot query for perform check duplicate data of {$file_to_read['report']}.");
							}
							$duprow = $gm->db_fetch('mysql');
							$total_duplicate = $duprow['total_duplicate'];
							if (!$total_duplicate) {
								$sql = "INSERT INTO {$file_to_read['data']['table']}(sales_file_seq, sales_gsuri_seq, sales_list_seq, Order_Number, Order_Charged_Date, Order_Charged_Timestamp, Financial_Status, Device_Model, Product_Title, Product_ID, Product_Type, SKU_ID, Currency_of_Sale, Item_Price, Taxes_Collected, Charged_Amount, City_of_Buyer, State_of_Buyer, Postal_Code_of_Buyer, Country_of_Buyer, sales_insert_datetime) VALUES({$gm->sql_addslashes($grow['seq'])}, {$gm->sql_addslashes($grow['gsuri_seq'])}, {$gm->sql_addslashes($grow['list_seq'])}, 
								'{$gm->sql_addslashes($cVal['Order_Number'])}', '{$gm->sql_addslashes($cVal['Order_Charged_Date'])}', '{$gm->sql_addslashes($cVal['Order_Charged_Timestamp'])}', '{$gm->sql_addslashes($cVal['Financial_Status'])}', '{$gm->sql_addslashes($cVal['Device_Model'])}', '{$gm->sql_addslashes($cVal['Product_Title'])}', '{$gm->sql_addslashes($cVal['Product_ID'])}', '{$gm->sql_addslashes($cVal['Product_Type'])}', '{$gm->sql_addslashes($cVal['SKU_ID'])}', '{$gm->sql_addslashes($cVal['Currency_of_Sale'])}', CAST('{$gm->sql_addslashes($cVal['Item_Price'])}' AS Decimal(24,2)), CAST('{$gm->sql_addslashes($cVal['Taxes_Collected'])}' AS Decimal(24,2)), CAST('{$gm->sql_addslashes($cVal['Charged_Amount'])}' AS Decimal(24,2)), '{$gm->sql_addslashes($cVal['City_of_Buyer'])}', '{$gm->sql_addslashes($cVal['State_of_Buyer'])}', '{$gm->sql_addslashes($cVal['Postal_Code_of_Buyer'])}', '{$gm->sql_addslashes($cVal['Country_of_Buyer'])}', NOW())";
								if (!$gm->db_query($sql)) {
									$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for perform inserting csv file content one by one: ".$sql);
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
							$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", $gm->db_error());
							$gm->error("Cannot query for perform update csv total rows reading.");
						}
					}
						
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Job already done of MySQL Log of {$file_to_read['report']} on {$file_to_read_for_check['name']} " . date('Y-m-d H:i:s', time()) . "!");
				}
				/* One time process to update KPIDB 
				*******************************************************/
				$data_to_kpidb = Array();
				$sql = "SELECT * FROM gsutil_sales WHERE (DATE(sales_insert_datetime) = DATE(NOW()))";
				if (!$gm->db_query($sql, 'mysql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot query for get data of inserted sales at this datetime today.");
					$gm->error("Cannot query for data of inserting today datetime.");
				}
				while ($pokpokporow = $gm->db_fetch('mysql')) {
					$data_to_kpidb[] = $pokpokporow;
				}
				#Connect to KPIDB database
				if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
					$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
				}
				$update_to_kpidb = update_to_KPIDB($file_to_read['report'], $data_to_kpidb);
				if ($update_to_kpidb > 0) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Already done update to KPIDB from gs_single about {$update_to_kpidb} rows.");
				} else {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Data return of update on KPIDP function is 0 rows");
				}
				
				$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Job already done success and good!");
				/* One time process to update KPIDB 
				*******************************************************/
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
					$sql = "SELECT f.seq, f.file_tmp_checksum FROM gs_list_gsuri_file AS f LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq WHERE ((f.file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (f.file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND f.file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND f.file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}')) AND (l.store = '{$gm->sql_addslashes($gs_list['store'])}') ORDER BY f.seq DESC LIMIT 1";
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
						$csv_rows = 0;
						$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
						if (!$gm->db_query($sql)) {
							$gm->error("Cannot query for perform update csv file read starting.");
						}
						$csv_filestream = $gm->custom_str_getcsv($csv_filepath);
						$csv_fileread = array(); // Define as an array datatype
						$csv_rows = 0;
						/*
						*******************************************************
						You already get csv data on this stage
						*******************************************************
						*/
						if (count($csv_filestream) > 0) {
							foreach ($csv_filestream as $parentStream) {
								$csv_fileread[] = $parentStream;
							}
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
			case 'gcm':
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
					$sql = "SELECT f.seq, f.file_tmp_checksum FROM gs_list_gsuri_file AS f LEFT JOIN gs_list AS l ON l.seq = f.file_list_seq WHERE ((f.file_list_report = '{$gm->sql_addslashes($file_to_read['report'])}') AND (f.file_tmp_name = '{$gm->sql_addslashes($file_to_read_for_check['name'])}' AND f.file_tmp_ext = '{$gm->sql_addslashes($file_to_read_for_check['ext'])}' AND f.file_tmp_mime = '{$gm->sql_addslashes($file_to_read_for_check['mime'])}')) AND (l.store = '{$gm->sql_addslashes($gs_list['store'])}') ORDER BY f.seq DESC LIMIT 1";
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
						$csv_rows = 0;
						$sql = "UPDATE gs_list_gsuri_file SET file_datetime_reading_starting = NOW() WHERE seq = {$gm->sql_addslashes($grow['seq'])}";
						if (!$gm->db_query($sql)) {
							$gm->error("Cannot query for perform update csv file read starting.");
						}
						$csv_filestream = $gm->custom_str_getcsv($csv_filepath);
						$csv_fileread = array(); // Define as an array datatype
						$csv_rows = 0;
						/*
						*******************************************************
						You already get csv data on this stage
						*******************************************************
						*/
						if (count($csv_filestream) > 0) {
							foreach ($csv_filestream as $parentStream) {
								$csv_fileread[] = $parentStream;
							}
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

	$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "This job already done at " . date('Y-m-d H:i:s'));	

}

/* Start this Line is only for 1 time work for updating KPIDB */
function update_to_KPIDB($report, $new_data_to_kpidb = Array(), $table = 'gsutil_sales') {
	$hasil_update = 0;
	global $gm;
	#Connect to KPIDB database
	if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
		$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
	}
	switch ($report) {
		case 'sales':
		default:
			foreach ($new_data_to_kpidb as $val) {
				$sql = "SELECT seq AS sales_seq FROM gsutil_sales WHERE 
				(Order_Number = '{$gm->sql_addslashes($val['Order_Number'], 'mssql')}' AND Order_Charged_Date = '{$gm->sql_addslashes($val['Order_Charged_Date'], 'mssql')}' AND Product_ID = '{$gm->sql_addslashes($val['Product_ID'], 'mssql')}' AND SKU_ID = '{$gm->sql_addslashes($val['SKU_ID'], 'mssql')}')";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->error("Cannot query for check is it data duplicated or not?");
				}
				$dupe = $gm->db_fetch('mssql');
				$dupe_check = (int)$dupe['sales_seq'];
				if (!$dupe_check) {
					$val['Order_Charged_Datetime'] = gmdate('Y-m-d H:i:s', $val['Order_Charged_Timestamp']);
					$sql = "INSERT INTO {$gm->sql_addslashes($table, 'mssql')}(Order_Number, Order_Charged_Date, Order_Charged_Datetime, Order_Charged_Timestamp, Financial_Status, Device_Model, Product_Title, Product_ID, Product_Type, SKU_ID, Currency_of_Sale, Item_Price, Taxes_Collected, Charged_Amount, City_of_Buyer, State_of_Buyer, Postal_Code_of_Buyer, Country_of_Buyer, sales_insert_datetime) VALUES('{$gm->sql_addslashes($val['Order_Number'], 'mssql')}', '{$gm->sql_addslashes($val['Order_Charged_Date'], 'mssql')}', '{$gm->sql_addslashes($val['Order_Charged_Datetime'], 'mssql')}', '{$gm->sql_addslashes($val['Order_Charged_Timestamp'], 'mssql')}', '{$gm->sql_addslashes($val['Financial_Status'], 'mssql')}', '{$gm->sql_addslashes($val['Device_Model'], 'mssql')}', '{$gm->sql_addslashes($val['Product_Title'], 'mssql')}', '{$gm->sql_addslashes($val['Product_ID'], 'mssql')}', '{$gm->sql_addslashes($val['Product_Type'], 'mssql')}', '{$gm->sql_addslashes($val['SKU_ID'], 'mssql')}', '{$gm->sql_addslashes($val['Currency_of_Sale'], 'mssql')}', CAST('{$gm->sql_addslashes($val['Item_Price'], 'mssql')}' AS Float), CAST('{$gm->sql_addslashes($val['Taxes_Collected'], 'mssql')}' AS Float), CAST('{$gm->sql_addslashes($val['Charged_Amount'], 'mssql')}' AS Float), '{$gm->sql_addslashes($val['City_of_Buyer'], 'mssql')}', '{$gm->sql_addslashes($val['State_of_Buyer'], 'mssql')}', '{$gm->sql_addslashes($val['Postal_Code_of_Buyer'], 'mssql')}', '{$gm->sql_addslashes($val['Country_of_Buyer'], 'mssql')}', GETDATE())";
					if (!$gm->db_query($sql, 'mssql')) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot update to KPIDB with SQL Statement: {$sql}\n");
						$gm->error("Cannot update to KPIDB with SQL Statement.");
					}
				$hasil_update += 1;
				}
			}
		break;
		case 'earnings':
			foreach ($new_data_to_kpidb as $val) {
				$sql = "SELECT seq AS dupe_check FROM {$gm->sql_addslashes($table, 'mssql')} WHERE seq = '{$gm->sql_addslashes($val['seq'], 'mssql')}'";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot check duplicate key seq on {$table} KPIDB with SQL Statement: {$sql}\n---------\r\n");
					$gm->error("Cannot query for check is it data duplicated or not?");
				}
				$dupe = $gm->db_fetch('mssql');
				$dupe_check = (int)$dupe['dupe_check'];
				if (!$dupe_check) {
					$sql = "INSERT INTO {$gm->sql_addslashes($table, 'mssql')}(seq, earnings_Description, earnings_TransactionDate, earnings_TransactionTime, earnings_TaxType, earnings_TransactionType, earnings_RefundType, earnings_ProductTitle, earnings_Productid, earnings_ProductType, earnings_SkuId, earnings_Hardware, earnings_BuyerCountry, earnings_BuyerState, earnings_BuyerPostalCode, earnings_BuyerCurrency, earnings_Amount_BuyerCurrency, earnings_Currency_ConversionRate, earnings_MerchantCurrency, earnings_Amount_MerchantCurrency, earnings_DateInsert) 
					VALUES(
						CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
						CAST('{$gm->sql_addslashes($val['earnings_Description'], 'mssql')}' AS VARCHAR(128)), 
						CAST('{$gm->sql_addslashes($val['earnings_TransactionDate'], 'mssql')}' AS VARCHAR(128)), 
						CAST('{$gm->sql_addslashes($val['earnings_TransactionTime'], 'mssql')}' AS VARCHAR(64)), 
						CAST('{$gm->sql_addslashes($val['earnings_TaxType'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_TransactionType'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_RefundType'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_ProductTitle'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_Productid'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_ProductType'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_SkuId'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_Hardware'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_BuyerCountry'], 'mssql')}' AS CHAR(3)),
						CAST('{$gm->sql_addslashes($val['earnings_BuyerState'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_BuyerPostalCode'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_BuyerCurrency'], 'mssql')}' AS CHAR(3)),
						CAST('{$gm->sql_addslashes($val['earnings_Amount_BuyerCurrency'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_Currency_ConversionRate'], 'mssql')}' AS VARCHAR(128)),
						CAST('{$gm->sql_addslashes($val['earnings_MerchantCurrency'], 'mssql')}' AS CHAR(3)),
						CAST('{$gm->sql_addslashes($val['earnings_Amount_MerchantCurrency'], 'mssql')}' AS VARCHAR(128)),
						GETDATE()
					)";
					if (!$gm->db_query($sql, 'mssql')) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot update to KPIDB with SQL Statement: {$sql}\n--\r\n");
						$gm->error("Cannot update to KPIDB with SQL Statement.");
					}
				$hasil_update += 1;
				}
			}
		break;
		case 'crashes':
			
		break;
		case 'installs':
			foreach ($new_data_to_kpidb as $val) {
				$sql = "SELECT seq AS dupe_check FROM {$gm->sql_addslashes($table, 'mssql')} WHERE seq = '{$gm->sql_addslashes($val['seq'], 'mssql')}'";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot check duplicate key seq on {$table} KPIDB with SQL Statement: {$sql}\n---------\r\n");
					$gm->error("Cannot query for check is it data duplicated or not?");
				}
				$dupe = $gm->db_fetch('mssql');
				$dupe_check = (int)$dupe['dupe_check'];
				if (!$dupe_check) {
					$sqlPattern = "INSERT INTO {$gm->sql_addslashes($table, 'mssql')}";
					switch (strtolower($table)) {
						case "gsutil_installs_app_version":
							$sqlPattern .= "(seq, app_version_Date, app_version_PackageName, app_version_AppVersionCode, app_version_CurrentDeviceInstalls, app_version_DailyDeviceInstalls, app_version_DailyDeviceUninstalls, app_version_DailyDeviceUpgrades, app_version_CurrentUserInstalls, app_version_TotalUserInstalls, app_version_DailyUserInstalls, app_version_DailyUserUninstalls, app_version_ActiveDeviceInstalls, app_version_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['app_version_PackageName'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['app_version_AppVersionCode'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['app_version_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['app_version_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['app_version_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()
							)";
						break;
						case "gsutil_installs_carrier":
							$sqlPattern .= "(seq, carrier_Date, carrier_PackageName, carrier_Carrier, carrier_CurrentDeviceInstalls, carrier_DailyDeviceInstalls, carrier_DailyDeviceUninstalls, carrier_DailyDeviceUpgrades, carrier_CurrentUserInstalls, carrier_TotalUserInstalls, carrier_DailyUserInstalls, carrier_DailyUserUninstalls, carrier_ActiveDeviceInstalls, carrier_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['carrier_PackageName'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['carrier_Carrier'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['carrier_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['carrier_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['carrier_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()
							)";
						break;
						case "gsutil_installs_country":
							$sqlPattern .= "(seq, country_Date, country_PackageName, country_Country, country_CurrentDeviceInstalls, country_DailyDeviceInstalls, country_DailyDeviceUninstalls, country_DailyDeviceUpgrades, country_CurrentUserInstalls, country_TotalUserInstalls, country_DailyUserInstalls, country_DailyUserUninstalls, country_ActiveDeviceInstalls, country_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['country_PackageName'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['country_Country'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['country_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['country_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['country_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()
							)";
						break;
						case "gsutil_installs_device":
							$sqlPattern .= "(seq, device_Date, device_PackageName, device_Device, device_CurrentDeviceInstalls, device_DailyDeviceInstalls, device_DailyDeviceUninstalls, device_DailyDeviceUpgrades, device_CurrentUserInstalls, device_TotalUserInstalls, device_DailyUserInstalls, device_DailyUserUninstalls, device_ActiveDeviceInstalls, device_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['device_PackageName'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['device_Device'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['device_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['device_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['device_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()
							)";
						break;
						case "gsutil_installs_language":
							$sqlPattern .= "(seq, language_Date, language_PackageName, language_Language, language_CurrentDeviceInstalls, language_DailyDeviceInstalls, language_DailyDeviceUninstalls, language_DailyDeviceUpgrades, language_CurrentUserInstalls, language_TotalUserInstalls, language_DailyUserInstalls, language_DailyUserUninstalls, language_ActiveDeviceInstalls, language_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['language_PackageName'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['language_Language'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['language_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['language_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['language_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()			
							)";
						break;
						case "gsutil_installs_os_version":
							$sqlPattern .= "(seq, os_version_Date, os_version_PackageName, os_version_AndroidOSVersion, os_version_CurrentDeviceInstalls, os_version_DailyDeviceInstalls, os_version_DailyDeviceUninstalls, os_version_DailyDeviceUpgrades, os_version_CurrentUserInstalls, os_version_TotalUserInstalls, os_version_DailyUserInstalls, os_version_DailyUserUninstalls, os_version_ActiveDeviceInstalls, os_version_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['os_version_PackageName'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['os_version_AndroidOSVersion'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['os_version_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['os_version_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['os_version_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()
							)";
						break;
						case "gsutil_installs_overview":
							$sqlPattern .= "(seq, overview_Date, overview_PackageName, overview_CurrentDeviceInstalls, overview_DailyDeviceInstalls, overview_DailyDeviceUninstalls, overview_DailyDeviceUpgrades, overview_CurrentUserInstalls, overview_TotalUserInstalls, overview_DailyUserInstalls, overview_DailyUserUninstalls, overview_ActiveDeviceInstalls, overview_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['overview_PackageName'], 'mssql')}' AS VARCHAR(128)),
								
								CAST('{$gm->sql_addslashes($val['overview_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['overview_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['overview_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()
							)";
						break;
						case "gsutil_installs_tablets":
							$sqlPattern .= "(seq, tablets_Date, tablets_PackageName, tablets_Tablets, tablets_CurrentDeviceInstalls, tablets_DailyDeviceInstalls, tablets_DailyDeviceUninstalls, tablets_DailyDeviceUpgrades, tablets_CurrentUserInstalls, tablets_TotalUserInstalls, tablets_DailyUserInstalls, tablets_DailyUserUninstalls, tablets_ActiveDeviceInstalls, tablets_DateInsert)
							VALUES (
								CAST('{$gm->sql_addslashes($val['seq'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_Date'], 'mssql')}' AS DATE),
								CAST('{$gm->sql_addslashes($val['tablets_PackageName'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['tablets_Tablets'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['tablets_CurrentDeviceInstalls'], 'mssql')}' AS VARCHAR(128)),
								CAST('{$gm->sql_addslashes($val['tablets_DailyDeviceInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_DailyDeviceUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_DailyDeviceUpgrades'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_CurrentUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_TotalUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_DailyUserInstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_DailyUserUninstalls'], 'mssql')}' AS INT),
								CAST('{$gm->sql_addslashes($val['tablets_ActiveDeviceInstalls'], 'mssql')}' AS INT),
								GETDATE()
							)";
						break;
					}
					if (!$gm->db_query($sqlPattern, 'mssql')) {
						$gm->custom_write_log("C:/gsutil/log/log_".date('Y-m-d').".txt", "Cannot update to KPIDB with SQL Statement: {$sqlPattern}\n---------\r\n");
						$gm->error("Cannot update to KPIDB with SQL Statement.");
					}
				$hasil_update += 1;
				}
			}
		break;
		case 'gcm':
		
		break;
	}
	return $hasil_update;
}


exit;
?>