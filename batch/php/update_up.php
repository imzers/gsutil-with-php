<?php
define('gm', true);
include('includes/include.googlestorage.php');
$gm->session_start();
$error = false;
$error_msg = array();

set_time_limit(72000);
ini_set("memory_limit", "2048M");


if (PHP_SAPI === 'cli') {
    $mysql_table = (isset($argv[1]) ? $argv[1] : '');
	$mssql_method = (isset($argv[2]) ? $argv[2] : '');
} else {
    $mysql_table = (isset($_POST['log']) ? $_POST['log'] : '');
	$mssql_method = (isset($_POST['tmp']) ? $_POST['tmp'] : '');
}
$mysql_table = $gm->safe_text_post($mysql_table, 512);
$mssql_method = $gm->safe_text_post($mssql_method, 512);



#Connect to log database
if (!$gm->db_connect($gm->config['database']['mysql']['googlestorage'], 'mysql')) {
	$gm->error('Cannot connect to googlestorage database with MySQL Server.');
}
$data_to_kpidb = array();
$sql = "SELECT * FROM {$gm->sql_addslashes($mysql_table, 'mysql')}";
if (!$gm->db_query($sql, 'mysql')) {
	$gm->custom_write_log("C:/gsutil/log/up_log_".date('Y-m-d').".txt", "Cannot query for get data of inserted earnings at this datetime today.");
	$gm->error("Cannot query for data of inserting today datetime.");
}
while ($pokpokporow = $gm->db_fetch('mysql')) {
	$data_to_kpidb[] = $pokpokporow;
}

switch ($mysql_table) {
	case 'earnings_PlayApps':
		$mssqlTable = "gsutil_earnings_playapps";
	break;
	default:
		$mssqlTable = "gsutil_{$mysql_table}";
	break;
}
$hasil_update = 0;
echo "Berhasil Update data: \r\n";
#Connect to KPIDB database
if (!$gm->db_connect($gm->config['database']['mssql']['kpidb'], 'mssql')) {
	$gm->error('Cannot connect to shared database with Microsoft SQL Server.');
}
if (count($data_to_kpidb) > 0) {
	switch ($mssql_method) {
		case 'installs':
			foreach ($data_to_kpidb as $val) {
				$sql = "SELECT seq AS dupe_check FROM {$gm->sql_addslashes($mssqlTable, 'mssql')} WHERE seq = '{$gm->sql_addslashes($val['seq'], 'mssql')}'";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/up_log_".date('Y-m-d').".txt", "Cannot check duplicate key seq on {$mssqlTable} KPIDB with SQL Statement: {$sql}\n---------\r\n");
					$gm->error("Cannot query for check is it data duplicated or not?");
				}
				$dupe = $gm->db_fetch('mssql');
				$dupe_check = (int)$dupe['dupe_check'];
				if (!$dupe_check) {
					$sqlPattern = "INSERT INTO {$gm->sql_addslashes($mssqlTable, 'mssql')}";
					switch (strtolower($mssqlTable)) {
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
						$gm->custom_write_log("C:/gsutil/log/up_log_".date('Y-m-d').".txt", "Cannot update to KPIDB with SQL Statement: {$sqlPattern}\n---------\r\n");
						$gm->error("Cannot update to KPIDB with SQL Statement.");
					}
					$objVal = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
					echo "----\r\n";
					echo $objVal;
					echo "\r\n----\r\n";
					echo "\r\n\n\n";
				$hasil_update += 1;
				}
			}
		break;
		case 'earnings':
			foreach ($data_to_kpidb as $val) {
				$sql = "SELECT seq AS dupe_check FROM {$gm->sql_addslashes($mssqlTable, 'mssql')} WHERE seq = '{$gm->sql_addslashes($val['seq'], 'mssql')}'";
				if (!$gm->db_query($sql, 'mssql')) {
					$gm->custom_write_log("C:/gsutil/log/up_log_".date('Y-m-d').".txt", "Cannot check duplicate key seq on {$mssqlTable} KPIDB with SQL Statement: {$sql}\n---------\r\n");
					$gm->error("Cannot query for check is it data duplicated or not?");
				}
				$dupe = $gm->db_fetch('mssql');
				$dupe_check = (int)$dupe['dupe_check'];
				if (!$dupe_check) {
					$sql = "INSERT INTO {$gm->sql_addslashes($mssqlTable, 'mssql')}(seq, earnings_Description, earnings_TransactionDate, earnings_TransactionTime, earnings_TaxType, earnings_TransactionType, earnings_RefundType, earnings_ProductTitle, earnings_Productid, earnings_ProductType, earnings_SkuId, earnings_Hardware, earnings_BuyerCountry, earnings_BuyerState, earnings_BuyerPostalCode, earnings_BuyerCurrency, earnings_Amount_BuyerCurrency, earnings_Currency_ConversionRate, earnings_MerchantCurrency, earnings_Amount_MerchantCurrency, earnings_DateInsert) 
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
						$gm->custom_write_log("C:/gsutil/log/up_log_".date('Y-m-d').".txt", "Cannot update to KPIDB with SQL Statement: {$sql}\n--\r\n");
						$gm->error("Cannot update to KPIDB with SQL Statement.");
					}
					$objVal = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
					echo "----\r\n";
					echo $objVal;
					echo "\r\n----\r\n";
					echo "\r\n\n\n";
				$hasil_update += 1;
				}
			}
		break;
	}	
}
	
