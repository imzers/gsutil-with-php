<?php
if (!defined('gm')) { 
	die('Page is not defined yet.');
}
$json_config = array();
$db_config = Array (
	'mysql' => Array(
		'gsutil' => Array(
			'db_host' => '192.168.13.70',
			'db_user' => 'gsutil',
			'db_pass' => 'gsutil',
			'db_name' => 'gsutil',
			),
		),
	'mssql' => Array(
		'kpidb' => Array(
			'db_host' => 'KPIDB_Server',
			'db_user' => 'user',
			'db_pass' => 'password',
			'db_name' => 'KPIDB',
			),
		'exchangerate' => Array(
			'db_host' => 'ManagerDB_Server',
			'db_user' => 'user',
			'db_pass' => 'password',
			'db_name' => 'ManagerDB'
			),
		),
	);
$batch_config = array(
	'download' => 'C:\\gsutil\\batch\\download\\gsutil_download.bat',
	);
$config = array
	(
	'database' => $db_config,
	'batch' => $batch_config,
	'table_prefix' => 'gm_',
	'table' => 'gm_',
	'timezone' => 7,
	'list_per_page' => array('report' => 15, 'normal' => 15),
	'curl' => array(
		'path'		=> 'http://happyhappybread.com/report',
		'tdp'		=> array(
			'url'		=> 'http://10.10.41.58',
			'query'		=> array(
				'character' => '/platform/paycheck/goodgame/thailandtdp_user_info.php',
				'refill'	=> '/platform/paycheck/goodgame/thailandtdp_charge.php',
				'mail'		=> '/platform/paycheck/goodgame/thailandtdp_send_mail.php'
				)
			),
		'address'	=> array(
					'player' => '/gm/player.asp',
					'clan' => '/gm/clan.asp',
					'report' => '/gm/report.asp',
					),
		'engine'	=> 'php-curl',
		
		),
		
	'path' => array('root' => '/report', 'home' => '/home'),
	'datenow' => date('Y-m-d'),
	'datetime' => date('Y-m-d H:i:s'),
	'included' => array('proses' => '/ID/process/', 'template' => '/ID/report/template/'),
	'maintenance' => array('status' => 0, 'message' => 'Sedang Maintenance'),
	'client' => array(
		'user_ip' => (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] :
							(isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] :
								(getenv('HTTP_X_FORWARDED_FOR') ? getenv('HTTP_X_FORWARDED_FOR') :
									(isset($_ENV['HTTP_X_FORWARDED_FOR']) ? $_ENV['HTTP_X_FORWARDED_FOR'] :
										(getenv('HTTP_CLIENT_IP') ? getenv('HTTP_CLIENT_IP') :
											(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] :
												(getenv('REMOTE_ADDR') ? getenv('REMOTE_ADDR') :
													(isset($_ENV['REMOTE_ADDR']) ? $_ENV['REMOTE_ADDR'] :
														'0.0.0.0')))))))),
		'user_proxy' => (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : (getenv('REMOTE_ADDR') ? getenv('REMOTE_ADDR') : (isset($_ENV['REMOTE_ADDR']) ? $_ENV['REMOTE_ADDR'] : '0.0.0.0'))),
		'user_browser' => ((isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown.Browser.UA'),
		'server_name' => ((isset($_SERVER['SERVER_NAME']) && (!empty($_SERVER['SERVER_NAME']))) ? $_SERVER['SERVER_NAME'] : 'rw.gg.in.th'),
		'request_uri' => ((isset($_SERVER['REQUEST_URI']) && (!empty($_SERVER['REQUEST_URI']))) ? strtolower(preg_replace('/\&/', '&amp;', $_SERVER['REQUEST_URI'])) : '/index.php'),
		),
	'allow_ip' => array(
		'::1', '127.0.0.1', // Local Server
		'203.144.233.53', // TDP Thailand Office (MIS)
		'110.170.192.111', // TDP Thailand Wi-fi
		'115.75.2.105', // TDP Vietnam
		'202.169.54.34', '202.169.54.35', // TDP Indonesia
		'localhost',
		),
	
	);



?>