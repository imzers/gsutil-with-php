<?php
if (!defined('gm')) { die ('Page note yet defined: class page.'); }
class gmtools
	{
	var $session_id, $db_resource, $db_result;
	var $db_resources = array();
	var $config = array();
	var $page_content = array();
	var $errors = array();
	var $queries = array();
	var $session_data = array();
	var $ip_allowed = array("127.0.0.1", "202.176.89.136", "110.138.102.86", "202.93.131.68", "202.93.131.67");
	function gmtools($config = array()) {
		$this->config = $config;
		}
		
	/***********************************************************************
	* Error page
	************************************************************************/
	function add_error($msg) {
		array_push($this->errors, $msg);
		}
	function error($msg) {
		array_push($this->errors, $msg);
		$this->show_msg('Error', $msg);
		}
	/***********************************************************************
	* Databases
	************************************************************************/
	function db_connect_resources($database, $type = 'mysql') {
		if ($type != 'mysql') {
			try {
				if (!$this->db_resource = odbc_connect($database['db_host'], $database['db_user'], $database['db_pass'])) {
					$this->add_error('Could not connect to Microsoft SQL Server.');
					return false;
				}
			} catch (Exception $e) {
				$this->add_error($e);
				return false;
			}
			return array('result' => true, 'resource' => $this->db_resource);
		} else {
			try {
				$this->db_resource = new mysqli($database['db_host'], $database['db_user'], $database['db_pass'], $database['db_name']);
				if ($this->db_resource->connect_error) { // PHP >= 5.4
				#if (mysqli_connect_error()) { // PHP < 5.4
					$this->add_error('Could not connect to database server.');
					return false;
				}
				if (!$this->db_query("SET NAMES utf8")) { $this->add_error('Cannot set collation name as UTF-8.'); }
			} catch (Exception $e) {
				$this->add_error($e);
				return false;
			}
			return array('result' => true, 'resource' => $this->db_resource);
		}
	}
	function db_connect($database, $type = 'mysql') {
		//ini_set('display_errors', false);
		if ($type != 'mysql') {
			try {
				if (!$this->db_resource = odbc_connect($database['db_host'], $database['db_user'], $database['db_pass'])) {
					$this->add_error('Could not connect to Microsoft SQL Server.');
					return false;
				}
			} catch (Exception $e) {
				$this->add_error($e);
				return false;
			}
			return true;
		} else {
			try {
				$this->db_resource = new mysqli($database['db_host'], $database['db_user'], $database['db_pass'], $database['db_name']);
				if ($this->db_resource->connect_error) { // PHP >= 5.4
				#if (mysqli_connect_error()) { // PHP < 5.4
					$this->add_error('Could not connect to database server.');
					return false;
				}
				if (!$this->db_query("SET NAMES utf8")) { $this->add_error('Cannot set collation name as UTF-8.'); }
			} catch (Exception $e) {
				$this->add_error($e);
				return false;
			}
			return true;
		}
	}
	function sql_addslashes($sql, $type = 'mysql') {
		switch ($type) {
			case 'mysql':
			default:
				if (!isset($result)) {
					$result = $this->db_resource;
				}
				$return = $result->real_escape_string($sql);
			break;
			case 'mssql':
				$return = str_replace("'", "", $sql);
				/*$return = $this->checkApostrophes($return);*/
			break;
		}
		return $return;
	}
	function db_free($type = 'mysql', $result = null) {
		if (!isset($result)) {
			$result = $this->db_result;
		}
		if ($type != 'mysql') {
			return odbc_free_result($result);
		}
	}
	function db_close($type = 'mysql', $resource = null) {
		if (!isset($resource)) {
			$resource = $this->db_resource;
		}
		if ($type != 'mysql') {
			return odbc_close($resource);
		} else {
			return $resource->close();
		}
	}
	function db_insert_id($type = 'mysql', $resource = null) {
		if (!isset($resource)) {
			$resource = $this->db_resource;
		}
		switch ($type) {
			case 'mysql': 
			default:
				return $resource->insert_id; 
			break;
			case 'mssql':
				return $this->db_query("SELECT @"."@IDENTITY AS Ident", 'mssql');
			break;
		}
	}
	function db_prepare($sql, $type = 'mysql', $resources = null) {
		if (!isset($resources)) {
			$resources = $this->db_resource;
		}
		$stmt = null;
		switch ($type) {
			case 'mysql':
			default:
			$stmt = $resources->prepare($sql);
			break;
			case 'mssql':
			$stmt = odbc_prepare($resources, $sql);
			break;
		}
		return $stmt;
	}
	function db_execute($type, $stmt, $arrayVal = array(), $resources = null) {
		if (!isset($resources)) {
			$resources = $this->db_resource;
		}
		$return = false;
		switch ($type) {
			case 'mysql':
			default:
			$return = $resources->execute();
			break;
			case 'mssql':
			$return = odbc_execute($stmt, $arrayVal);
			break;
		}
		return $return;
	}
	function db_query($sql, $type = 'mysql', $resources = null) {
		if (!isset($resources)) {
			$resources = $this->db_resource;
		}
		array_push($this->queries, $sql);
		switch ($type) {
			case 'mssql':
			if ($this->db_result = odbc_exec($resources, $sql)) {
				return $this->db_result;
			}
			break;
			case 'mysql':
			default:
			if ($this->db_result = $resources->query($sql)) {
				return $this->db_result;
			}
			break;
		}
		return false;
	}
	function db_fetch($type = 'mysql', $result = null) {
		if (!isset($result)) {
			$result = $this->db_result;
		}
		switch ($type) {
			case 'mysql':
			default:
			return $result->fetch_assoc();
			break;
			case 'mssql':
			return odbc_fetch_array($result);
			break;
		}
	}
	function db_num_rows($type = 'mysql', $result = null) {
		if (!isset($result)) {
			$result = $this->db_result;
		}
		switch ($type) {
			case 'mysql':
			default:
			return $result->num_rows;
			break;
			case 'mssql':
			return odbc_num_rows($result);
			break;
		}
	}
	function db_error($type = 'mysql', $resources = null) {
		if (!isset($resources)) {
			$resources = $this->db_resource;
		}
		switch ($type) {
			case 'mysql':
			default:
				return $resources->error;
			break;
			case 'mssql':
				return "ERROR!";
			break;
		}
		return true;
	}
	# Additional for ODBC
	/*
	function odbc_fetch_assoc($result) {
        $resultArray = array();
 		$resultReturn = array();
		if (odbc_fetch_into($result, &$resultArray) {
			foreach($resultArray as $k => $v) {
				$key = odbc_field_name($result, ($k + 1));
				$resultReturn[$key] = trim($v);
			}
		}
		return $resultReturn;
	}
	*/
    /***********************************************************************
	* Session
	************************************************************************/
	function is_session_started() {
		if (php_sapi_name() !== 'cli') {
			if (version_compare(phpversion(), '5.4.0', '>=')) {
				return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
			} else {
				return session_id() === '' ? FALSE : TRUE;
			}
		}
		return FALSE;
	}
	function session_start() {
		global $SID;
		session_name('gmtools');
		session_start();
		$this->session_data = &$_SESSION;
		$this->session_id = session_id();
		$SID = session_name() . '=' . $this->session_id;
		$this->supportdata = isset($this->session_data['support_login_account']) ? $this->get_supportdata($this->session_data['support_login_account']) : false;
		$this->login_data = $this->supportdata;
		}
	function get_supportdata($uid) {
		$supportdata = array();
		$sql = "SELECT * FROM report_gg_support WHERE ";
		$sql .= " LOWER(username) = '{$this->sql_addslashes(strtolower($uid))}'";
		if (!$this->db_query($sql)) { $this->error('Cannot query for support data account session.'); }
		return $this->db_fetch();
		}
	function redirect($redirect) {
		$redirect = isset($_REQUEST['r']) ? $_REQUEST['r'] : '/index.php';
		if (is_array($redirect) == 'Array') {
			$this->error('Array for redirect value.');
			}
		$redirect = strtolower(trim($redirect));
		header( 'Location: '.$redirect );
		exit;
		}
	function random($maxchar) {
		$maxchar = (integer)$maxchar;
		$chars = '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';
		srand((double)microtime()*100000);
		$i = 0;
		$pass = '' ;
		while ($i <= $maxchar)  {
			$num = rand() % 32;
			$tmp = substr($chars, $num, 1);
			$pass = $pass . $tmp;
			$i++; 
			}
		return $pass; 
		}
	/***********************************************************************
	* Header, footer and show page
	************************************************************************/
	function show_head($session_id) {
		if (defined('PAGE_HEADER')) {
			return;
			}
		define('PAGE_HEADER', 1);
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
		header('Cache-Control: no-cache, must-revalidate');
		header('Pragma: no-cache');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		}
	function show_foot($result = null) {
		if (!isset($result)) {
			$result = $this->db_result;
		}
		$result ? $this->db_free($result) : false;
		$this->db_resource ? $this->db_close() : false;
		exit;
	}
	function redirect_page($url, $title, $msg, $jsid) {
		$this->show_head($session_id)
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<script type="text/javascript" language="javascript">
				var count = 6;
				var redirect = "<?=$url;?>";
				function countRedirect(){
					if(count <= 0){
						window.location = redirect;
						}else{
						count--;
						document.getElementById("<?=$jsid;?>").innerHTML = "This page will be redirect in "+count+" seconds or <a href=\""+redirect+"\">click here</a>.";
						setTimeout("countRedirect()", 1000);
						}
					}
			</script>
		</head>
		<body>
			<div class="row1"><?=$msg;?></div>
			<div id="<?=$jsid;?>" class="row2">
				<script>countRedirect();</script>
			</div>
		</body>
		</html>
		<?php
		$this->show_foot();
		}
	function show_msg($title, $msg) {
		exit($msg);
		//$this->show_head($title);
		?>
		<!--
		<script type="text/javascript" language="javascript">
			var count = 30;
			var redirect = "<?=$this->config['path']['root'];?>";
			function countRedirect(){
				if(count <= 0){
					window.location = redirect;
					}else{
					count--;
					document.getElementById("timeredirect").innerHTML = "This page will be redirect in "+count+" seconds or <a href=\""+redirect+"\">click here</a>.";
					setTimeout("countRedirect()", 1000);
					}
				}
		</script>
		<div class="row1"><?php echo $msg; ?></div>
		<div id="timeredirect" class="row2">
			<script>countRedirect();</script>
		</div>
		-->
		<?php
		$this->show_foot();
		}
	/***********************************************************************
	* Paging
	************************************************************************/
	function generate_pagination($self, &$page, $per_page, $rows_count, &$start) {
		$total_pages = ceil($rows_count / $per_page);
		if ( $total_pages < 2 )
			{
			$total_pages = 1;
			}
		$page = isset($page) ? intval($page) : 1;
		if ( $page < 1 || $page > $total_pages )
			{
			$page = 1;
			}
		$start = (($page * $per_page) - $per_page);
		$ended_page = (($page - 1) * 5);
		$navigation = $start;
		$pagenav = '<div style="padding-bottom:0.4em;margin-bottom:0.4em;border-bottom:1px solid #363636;max-width:32%;">'.$page.'/'.$total_pages.'</div>';
		if ($start > 0) {
			$pagenav .= ' <a href="'.sprintf($self, ($total_pages / $total_pages)).'">&#171;1</a>';
			$pagenav .= ' <a href="'.sprintf($self, ($page - 1)).'">Prev</a> ';
			}
		$pagenav .= ' <span class="pageselected">'.$page.'</span>';
		if ($total_pages > 1) {
			if ($page < $total_pages) {
				$pagenav .= ' <a href="'.sprintf($self, ($page + 1)).'">Next</a>';
				$pagenav .= ' <a href="'.sprintf($self, ($total_pages)).'">'.$total_pages.'&raquo;</a>';
				}
			}
		return $pagenav;
		}
	function generate_pagenave($self, &$page, $per_page, $rows_count, &$start) {
		$sum_pages = ceil($rows_count / $per_page);
		if ($sum_pages < 2) { $sum_pages = 1; }
		$page = isset($page) ? intval($page) : 1;
		if ($page < 1 || $page > $sum_pages) { $page = 1; }
		$start = ceil(($page * $per_page) - $per_page);
		$pagenav = '<a href="'.sprintf($self, ($sum_pages / $sum_pages)).'">&#171;First</a>';
		if ($sum_pages <= 3) {
			$i = 1;
			while ($i <= $sum_pages) {
				$pagenav .= '<a href="'.sprintf($self, $i).'">'.$i.'</a>';
				$i++;
				}
			} else {
			for ($i = ($page - 2); $i < $page; $i++) {
				if ($i > 0) {
					$pagenav .= '<a href="'.sprintf($self, $i).'">'.$i.'</a>';
					}
				}
			$pagenav .= '<span class="pageselected">'.$page.'</span>';
			for ($i = ($page + 1); $i <= ($page + 2); $i++) {
				if ($i <= $sum_pages) {
					$pagenav .= '<a href="'.sprintf($self, $i).'">'.$i.'</a>';
					}
				}
			}
		$pagenav .= '<a href="'.sprintf($self, $sum_pages).'">Last&raquo;</a>';
		return $pagenav;
		}
	function generate_pagenav_javascript($self, &$page, $per_page, $rows_count, &$start) {
		$sum_pages = ceil($rows_count / $per_page);
		if ($sum_pages < 2) { $sum_pages = 1; }
		$page = isset($page) ? intval($page) : 1;
		if ($page < 1 || $page > $sum_pages) { $page = 1; }
		$start = ceil(($page * $per_page) - $per_page);
		$pagenav = '<input type="button" class="pagenavesubmit" name="pagevalue" value="&#171;First" data-send="1" />';
		if ($sum_pages <= 3) {
			$i = 1;
			while ($i <= $sum_pages) {
				$pagenav .= '<input type="button" class="pagenavesubmit" name="pagevalue" value="'.sprintf($self, $i).'" data-send="'.$i.'" />';
				$i++;
				}
			$pagenav .= '<span class="pageselected">'.$page.'</span>';
			} else {
			for ($i = ($page - 2); $i < $page; $i++) {
				if ($i > 0) {
					$pagenav .= '<input type="button" class="pagenavesubmit" name="pagevalue" value="'.sprintf($self, $i).'" data-send="'.$i.'" />';
					}
				}
			$pagenav .= '<span class="pageselected">'.$page.'</span>';
			for ($i = ($page + 1); $i <= ($page + 2); $i++) {
				if ($i <= $sum_pages) {
					$pagenav .= '<input type="button" class="pagenavesubmit" name="pagevalue" value="'.sprintf($self, $i).'" data-send="'.$i.'" />';
					}
				}
			}
		$pagenav .= '<input type="button" class="pagenavesubmit" name="pagevalue" value="Last&raquo;" data-send="'.$sum_pages.'" />';
		return $pagenav;
		}
	/***********************************************************************
	* Static queries
	************************************************************************/
	
	/***********************************************************************
	* SQL and Other Security
	************************************************************************/
	function pregsplit_linebreak($text) {
		return preg_split('/$\R?^/m', $text);
		}
	function cleanspecialchar($txt) {
		$txt = strip_tags($txt);
		$txt = preg_replace('/&.+?;/', '', $txt);
		$txt = preg_replace('/\s+/', ' ', $txt);
		$txt = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', ' ', $txt);
		$txt = preg_replace('|-+|', ' ', $txt);
		$txt = preg_replace('/&#?[a-z0-9]+;/i', '', $txt);
		$txt = preg_replace('/[^%A-Za-z0-9 \_\-]/', ' ', $txt);
		$txt = trim($txt, ' ');
		return $txt;
		}
	function permalink($url) {
		$url = strtolower($url);
		$url = preg_replace('/&.+?;/', '', $url);
		$url = preg_replace('/\s+/', '_', $url);
		$url = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '_', $url);
		$url = preg_replace('|%|', '_', $url);
		$url = preg_replace('/&#?[a-z0-9]+;/i', '', $url);
		$url = preg_replace('/[^%A-Za-z0-9 \_\-]/', '_', $url);
		$url = preg_replace('|_+|', '-', $url);
		$url = preg_replace('|-+|', '-', $url);
		$url = trim($url, '-');
		$url = (strlen($url) > 128) ? substr($url, 0, 128) : $url;
		return $url;
		}
	function phpcode($code) {
		$code = strtr($code, array('<br />' => ''));
		$code = html_entity_decode(trim($code), ENT_QUOTES, 'UTF-8');
		$code = highlight_string(stripslashes($code), true);
		/*
		$code = preg_replace('#(&lt;\?.*?)(php)?(.*?&nbsp;)#s','\\1\\3', $code);
		$code = preg_replace (array (
			'/.*<code>\s*<span style="color: #000000">/', //
			'#</span>\s*</code>#', //  <code><span black>
			//$r1, $r2, // php tags
			'/<span[^>]*><\/span>/' // empty spans
			), '', $code);
		*/
		$code = strtr($code, array(':' => '&#58;', '[' => '&#91;', '&nbsp;' => ' '));
		return '<div class="phpcode">'.$code.'</div>';
		}
	function txtcode($code)	{
		$code = strtr($code, array('<br />' => ''));
		$code = html_entity_decode(trim($code), ENT_QUOTES, 'UTF-8');
		$code = highlight_string(stripslashes($code), true);
		$code = strtr($code, array(':' => '&#58;', '[' => '&#91;', '&nbsp;' => ' '));
		return '<div class="small"><span style="font-weight: bold; text-decoration: underline;">Code</span></div><div class="code">' . $code . '</div>';
		}
	function htmlcode($source, $classes = false) {
		$source = html_entity_decode(trim($source), ENT_QUOTES, 'UTF-8');
		$r1 = $r2 = '##';
		// adds required PHP tags (at least with vers. 5.0.5 this is required)
		if (strpos($source, ' ?>') === false ) // xml is not THAT important
			{
			$source = "<?php ".$source." ?>";
			$r1 = '#&lt;\?.*?(php)?.*?&nbsp;#s';
			$r2 = '#\?&gt;#s';
			}
		elseif (strpos($source, '<? ') !== false )
			{
			$r1 = '--';
			$source = str_replace('<? ', '<?php ', $source);
			}
		$source = highlight_string(stripslashes($source), true);
		if ($r1 == '--') { 
			$source = preg_replace('#(&lt;\?.*?)(php)?(.*?&nbsp;)#s','\\1\\3', $source); 
			}
		$source = preg_replace (array (
			'/.*<code>\s*<span style="color: #000000">/', //
			'#</span>\s*</code>#', //  <code><span black>
			$r1, $r2, // php tags
			'/<span[^>]*><\/span>/' // empty spans
			), '', $source);
		if ($classes) $source = str_replace(array(
			'style="color: #0000BB"', 'style="color: #007700"', 'style="color: #DD0000"', 'style="color: #FF8000"'),
			array('style="color: #000000"', 'style="color: #000000"', 'style="color: #000000"', 'style="color: #000000"',), $source);
		return '<div class="phpcode">' . $source . '</div>';
		}
	function url_link($text) {
		$ret = ' ' . $text;
		$ret = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t<]*)#ise", "'\\1<a href=\"\\2\" target=\"_blank\" class=\"url\">\\2</a>'", $ret);
		$ret = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r<]*)#ise", "'\\1<a href=\"http://\\2\" target=\"_blank\" class=\"url\">\\2</a>'", $ret);
		$ret = preg_replace("#(^|[\n ])([a-z0-9&\-_\.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1<a href=\"mailto:\\2@\\3\" class=\"email\">\\2@\\3</a>", $ret);
		$ret = substr($ret, 1);
		return($ret);
		}
	function safe_text_post($text, $length, $allow_nl = false) {
		$text = htmlspecialchars($text, ENT_QUOTES);
		$text = trim(chop($text));
		$text = $allow_nl ? $text : preg_replace("/[\r|\n]/", "", $text);
		$text = substr($text, 0, $length);
		return $text;
		}
	function safe_text_save($text, $length, $allow_nl = false) {
		$text = htmlspecialchars($text, ENT_QUOTES);
		$text = trim(chop($text));
		$tagphp = array ('#\[php\](.*?)\[\/php\]#se', '#\[PHP\](.*?)\[\/PHP\]#se');
		$phptag = array ("''.\$this->phpcode('$1').''", "''.\$this->phpcode('$1').''");
		$codetag = array ('#\[code\](.*?)\[\/code\]#se', '#\[CODE\](.*?)\[\/CODE\]#se');
		$codehtml = array ("''.\$this->txtcode('$1').''", "''.\$this->txtcode('$1').''");
		$text = preg_replace($tagphp, $phptag, $text);
		$text = preg_replace($codetag, $codehtml, $text);
		//include('./include/text/bbcode.php');
		//$text = preg_replace($bbcode, $htmlcode, $text);
		$text = $this->url_link($text);
		$text = $allow_nl ? $text : preg_replace('/[\n]/', '<br/>', $text);
		#include($this->config['include_dir'] . 'function/text/smile.php');
		#$text = str_replace((array_keys($add_smile)), array_values($add_smile), $text);
		$text = substr($text, 0, $length);
		return $text;
		}
	function stripslashes_deep($var) {
		return is_array($var) ? array_map(array($this, 'stripslashes_deep'), $var) : stripslashes($var);
		}
	/***********************************************************************
	* Additional
	************************************************************************/
	function zersheader() {
		$out = array();
		$ignore = array('HTTP_CONNECTION' => true, 'HTTP_PRAGMA' => true, 'HTTP_COOKIE' => true, 'HTTP_CACHE_CONTROL' => true, 'HTTP_KEEP_ALIVE' => true);
		foreach($_SERVER as $key => $value) {
			if ((substr($key,0,5) == "HTTP_") && (empty($ignore[$key])) && (isset($value))) {
				$key = str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5)))));
				$out[$key] = $value;
				}
			}
		return $out;
		}
	function curl_response($url) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->zersheader());
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip, deflate');
		$contents = curl_exec($curl);
		curl_close($curl);
		return $contents;
		}
	function get_date($format, $timestamp) {
		return gmdate($format, $timestamp + ($this->config['timezone'] * 3600));
		}
	function get_timestamp($datetime) {
		if (($timestamp = strtotime($datetime)) === false) {
			return false;
			}
		return $timestamp;
		}
	function curlrequest($url, $params = array(), $timeout = 30) {
		$cookie = isset($_COOKIE) ? $_COOKIE : array();
		$url = str_replace( "&amp;", "&", urldecode(trim($url)) );
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    # required for https urls
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_POST, count($params));
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		$response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);
		curl_close ($ch);
		if (!empty($response) || $response != '') {
			return array(
				'header' => array('size' => $header_size, 'content' => $header),
				'content' => $body,
				);
			}
		return false;
		}
	function curlrequest_header($url, $params = array(), $header = array(), $timeout = 30) {
		$cookie = isset($_COOKIE) ? $_COOKIE : array();
		$url = str_replace( "&amp;", "&", urldecode(trim($url)) );
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    # required for https urls
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_POST, count($params));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		$response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);
		curl_close ($ch);
		if (!empty($response) || $response != '') {
			return array(
				'header' => array('size' => $header_size, 'content' => $header),
				'content' => $body,
				);
			}
		return false;
		}
	function xmltoarray($contents, $get_attributes=1) {
		if(!$contents) return array();
		if(!function_exists('xml_parser_create')) {
			return array();
			}
		$parser = xml_parser_create();
		xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE, 1 );
		xml_parse_into_struct( $parser, $contents, $xml_values );
		xml_parser_free( $parser );
		if(!$xml_values) return;
		$xml_array = array();
		$parents = array();
		$opened_tags = array();
		$arr = array();
		$current = &$xml_array;
		foreach($xml_values as $data) {
			unset($attributes,$value);
			extract($data);
			$result = '';
			if($get_attributes) {
				$result = array();
				if(isset($value)) $result['value'] = $value;
				if(isset($attributes)) {
					foreach($attributes as $attr => $val) {
						if($get_attributes == 1) $result['attr'][$attr] = $val;
						}
					}
				} elseif(isset($value)) {
				$result = $value;
				}
			if($type == "open") {
				$parent[$level-1] = &$current;
				if(!is_array($current) or (!in_array($tag, array_keys($current)))) {
					$current[$tag] = $result;
					$current = &$current[$tag];
					} else {
					if(isset($current[$tag][0])) {
						array_push($current[$tag], $result);
						} else {
						$current[$tag] = array($current[$tag],$result);
						}
					$last = count($current[$tag]) - 1;
					$current = &$current[$tag][$last];
					}
				} elseif($type == "complete") {
				if(!isset($current[$tag])) {
					$current[$tag] = $result;
					} else {
					if((is_array($current[$tag]) and $get_attributes == 0)
					or (isset($current[$tag][0]) and is_array($current[$tag][0]) and $get_attributes == 1)) {
						array_push($current[$tag],$result);
						} else {
						$current[$tag] = array($current[$tag],$result);
						}
					}
				} elseif($type == 'close') {
				$current = &$parent[$level-1];
				}
			}
		return($xml_array);
		}
	function read_stream($filename, $handle = null) {
		if (!$handle = fopen($filename, 'r')) {
			$this->error('Cannot read ' . $filename);
			}
		$contents = fread($handle, filesize($filename));
		fclose($handle);
		return preg_split('/$\R?^/m', $contents);
		}
	function read_url($urladdress, $handle = null) {
		$contents = '';
		if (!$handle = fopen($urladdress, 'r')) {
			$this->error('Cannot open and read ' . $urladdress);
			}
		while (!feof($handle)) {
			$contents .= fread($handle, 8192);
			}
		fclose($handle);
		return $contents;
		}
	function choosedate($date) {
		$timedate_post = explode('/', $date);
		if (count($timedate_post) !== 3) { $this->error('Invalid date format.'); }
		$timedateformat = ($timedate_post[2] . '-' . $timedate_post[1] . '-' . $timedate_post[0]);
		if ((int)strtotime($timedateformat) < 1) {
			$this->error('Invalid date format again.');
			}
		return $timedateformat;
		}
	function htmlspecialcharset($text, $allow_nl = false) {
		$text = htmlspecialchars($text, ENT_QUOTES);
		$text = trim(chop($text));
		$text = $allow_nl ? $text : preg_replace("/[\r|\n]/", "", $text);
		return $text;
		}
	// Validate URL and Parse URL
	function validate_url($string) {
		$pattern = "/\b(?:(?:https?|ftp):\/\/|www\.|xshot\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i";
		preg_match($pattern, $string, $matches);
		if (count($matches) > 0) {
			return $matches[0];
			}
		return false;
		}
	function parseurl($url) {
		$validurl = $this->validate_url($url);
		if ($validurl) {
			$parseurl = parse_url($validurl);
			if (count($parseurl) == 1) {
				$validurl = 'http://'.$validurl;
				}
			return parse_url($validurl);
			}
		return false;
		}
	// CheckIncluded Page
	function pageincluded($sub = 'template', $pagename) {
		if (!file_exists($this->config['included'][$sub].$pagename.'.php')) {
			include($this->config['included']['template'].'404.php');
			} else {
			include($this->config['included'][$sub].$pagename.'.php');
			}
		return true;
		}
	/* For Ranking Image */
	function rankimage($level) {
		$rankimage = '/x/Req_Thumbs/Rank/s_';
		$stringlength = strlen($level);
		switch($stringlength) {
			case 1:
				$rankimage .= '00';
			break;
			case 2:
				$rankimage .= '0';
			break;
			default:
				$rankimage .= '';
			break;
			}
		$rankimage .= $level . '.jpg';
		return $rankimage;
		}
	function LockAccountPaysys($Username, $NewPassword, $ipaddress) {
		$params = array(
			'url'		=> 'http://localhost/topup/point.asp',
			'data'		=> array(
				'chkcode'		=> 'lockidfromgmtools',
				'UserId'		=> $Username,
				'NewPassword'	=> $NewPassword,
				'IP'			=> $ipaddress,
				'SendDate'		=> date('Y-m-d H:i:s'),
				),
			'return'	=> 'integer',
			);
		if (!$curlrequest = $this->curlrequest($params['url'], $params['data'], 30)) {
			return json_encode(array('error' => true, 'msg' => 'Tidak bisa melakukan curl web services.'));
		}
		return $curlrequest['content'];
	}
	function datetime_get_date($month, $year) {
		$month = (int)$month;
		$year = (int)$year;
		switch ($month) {
			case 2:
				if (($year % 4) == 0) {
					$returnMaxDate = 29;
				} else {
					$returnMaxDate = 28;
				}
			break;
			case 4:
			case 6:
			case 9:
			case 11:
				$returnMaxDate = 30;
			break;
			case 1:
			case 3:
			case 5:
			case 7:
			case 8:
			case 10:
			case 12:
			default:
				$returnMaxDate = 31;
			break;
		}
	return $returnMaxDate;
	}
	function datetime_get_month($month) {
		$month = (int)$month;
		if (($month < 1) && ($month > 12)) {
			return false;
		}
		if (strlen($month) < 2) {
			$month = "0{$month}";
		}
		return $month;
	}
	function datetime_get_day($day, $month, $year) {
		$day = (int)$day;
		$month = (int)$month;
		$year = (int)$year;
		if (($day < 0) && ($day > $this->datetime_get_date($month, $year))) {
			return false;
		}
		if (strlen($day) < 2) {
			$day = "0{$day}";
		}
		return $day;
	}
	function unicode_escape($string, $function = 'decode', $format = 'utf-8') {
		if ($format != 'utf-8') {
			// In case if UTF-16 based C/C++/Java/Json-style:
			$string = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
			return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UTF-16BE');
			}, $string);
		} else {
			$string = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
			return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
			}, $string);
		}
		return $string;
	}
	function create_xml_graph($data, $yLine, $caption, $maxrows = 1, $colors = array()) { // $data must be array with value array(2 index => [0] and [1])
		if (count($colors) < 10) {
			$colors = array('AFD8F8', 'F6BD0F', '8BBA00', 'FF8E46', '008E8E', 'D64646', '8E468E', '588526', 'B3AA00', '008ED6');
		}
		$xml_string = '<graph caption="' . $caption . '"
			xAxisName="Date"
			yAxisName="' . $yLine . '"
			showValues="1"
			rotatevalues="1"
			valueposition="auto"
			formatNumberScale="0"
			bgColor="E4E7D9"
			bgAlpha="40"
			showAlternateHGridColor="1"
			AlternateHGridAlpha="30"
			AlternateHGridColor="E4E7D9"
			divLineColor="E4E7D9"
			divLineAlpha="80"
			decimalPrecision="0">';
		$i = 0;
		foreach ($data as $row) {
			$color = $colors[($i % 10)];
			$xml_str .= '<set name="' . $row[0] . '" value="' . $row[1] . '" color="' . $color . '" />';
			$i++;
		}
		$xml_str .= '</graph>';
		$xml = new SimpleXMLElement($xml_str);
		return $xml->asXML();
	}
	function file_mime_type($file, $encoding = true)
    {
        $mime = false;
        if (!file_exists($file)) {
            return false;
            exit;
        }
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file);
            finfo_close($finfo);
        } else if (substr(PHP_OS, 0, 3) == 'WIN') {
            $mime = mime_content_type($file);
        } else {
            $file = escapeshellarg($file);
            $cmd  = "file -iL $file";
            exec($cmd, $output, $r);
            if ($r == 0) {
                $mime = substr($output[0], strpos($output[0], ': ') + 2);
            }
        }
        if (!$mime) {
            return false;
        }
        if ($encoding) {
            return $mime;
        }
        return $mime;
    }
	function custom_file_exists($file_path='') {
		$file_exists=false;
		//clear cached results
		//clearstatcache();
		//trim path
		$file_dir=trim(dirname($file_path));
		//normalize path separator
		$file_dir=str_replace('/',DIRECTORY_SEPARATOR,$file_dir).DIRECTORY_SEPARATOR;
		//trim file name
		$file_name=trim(basename($file_path));
		//rebuild path
		$file_path=$file_dir."{$file_name}";
		//If you simply want to check that some file (not directory) exists, 
		//and concerned about performance, try is_file() instead.
		//It seems like is_file() is almost 2x faster when a file exists 
		//and about the same when it doesn't.
		$file_exists=is_file($file_path);
		//$file_exists=file_exists($file_path);
		return $file_exists;
	}
	function custom_file_path($file_path_and_name) {
		$file_dir = trim(dirname($file_path_and_name));
		$file_dir = str_replace('/', DIRECTORY_SEPARATOR, $file_dir).DIRECTORY_SEPARATOR;
		$file_name = trim(basename($file_path_and_name));
		$file_path = ($file_dir . $file_name);
		return $file_path;
	}
	function custom_read_csv($path_file) {
		$csv = array_map('str_getcsv', file($path_file));
		array_walk($csv, function(&$a) use ($csv) {
			$a = array_combine($csv[0], $a);
			});
		array_shift($csv);
		return $csv;
	}
	function custom_read_multicsv($path_file) {
		$csv = array_map('str_getcsv', file($path_file));
		$csvLine = array();
		foreach ($csv as $line) {
			$decoded_line = iconv('UTF-16LE', 'UTF-8', $line);
		}
		return $csvLine;
	}
	function custom_str_getcsv($path_file, $encoding = 'UTF-8') {
		$ReturnCsv = array();
		//$csvData = file_get_contents($path_file);
		//$csvData = $this->custom_convert_to_utf8($csvData);
		$csvData = $this->file_get_contents_utf_ansi($path_file);
		$Data = str_getcsv($csvData, "\n");
		$i = 0;
		foreach($Data as $Row) {
			$ReturnCsv[$i] = str_getcsv($Row, ",");
			$i += 1;
		}
		return $ReturnCsv;
	}
	function custom_stringcsv($path_file) {
		$return = array();
		$csvData = file_get_contents($path_file);
		$lines = explode(PHP_EOL, $csvData);
		$i = 0;
		foreach ($lines as $line) {
			//$decoded_line = mb_convert_encoding($line, "UTF-8", "UTF-16LE");
			$decoded_line = iconv('UTF-16LE', "UTF-8", $line);
			if (false === $decoded_line) {
				throw new Exception('Input line cannot be converted');
			} else {
				$return[$i] = preg_split('/$\R?^/m', $decoded_line);
			}
			$i += 1;
		}
		return $return;
	}
	function parse_csv($path_file) {
		$row = 1;
		$data_csv = Array();
		if (($handle = fopen($path_file, "r")) !== FALSE) {
		  while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			$num = count($data);
			$row++;
			for ($c=0; $c < $num; $c++) {
				$data_csv[] = $data[$c];
			}
		  }
		  fclose($handle);
		}
		return Array('rows' => $row, 'data' => $data_csv);
	}
	function array_stripstuff(&$elem) {
		if (is_array($elem)) {
			foreach ($elem as $key=>$value)
				$elem[str_replace(array(" ",",",".","-","+"),"_",$key)]=$value;
		}
		return $elem;
	}
	function custom_array_key(&$arr) {
		$arr = array_combine(
			array_map(
				function ($str) {
					return trim(str_replace(" ", "_", $str));
				},
				array_keys($arr)
			),
			array_values($arr)
		);
		return $arr;
	}
	function custom_float_val($string_number = '0.00', $string_point = '.') {
		$string_number = strval($string_number);
		$decimal_point = array('.', ',');
		if ($string_point == '.') {
			$string_return = str_replace($decimal_point[1], '', $string_number);
		} else {
			$string_return = str_replace($decimal_point[1], $decimal_point[0], str_replace($decimal_point[0], '', $string_number));
		}
		return floatval($string_return);
	}
	function custom_write_log($path, $content, $type = 'put') {
		$create_new_write = FALSE;
		if (!file_exists($path)) {
			$create_new_write = TRUE;
		}
		if (!$file_handle = fopen($path, 'a+')) {
			return false;
		}
		if ($create_new_write) {
			fwrite($file_handle, "\r\n{$content}");
		} else {
			fwrite($file_handle, "\r\n{$content}");
			//file_put_contents($path, $content.PHP_EOL, FILE_APPEND);
		}
		fclose($file_handle);
	}
	function checkApostrophes(&$strQuery) {
		$strQuery = trim($strQuery);
		$params = split(",", $strQuery);
		$buffer = "";
		foreach($params as $param)    {
			$param = trim($param);
			if (substr_count($param, "'") > 0)    {
				//replace double all quotes in data
				$param = substr($param, 0, strpos($param, "'"))."'".str_replace("'", "''", substr( $param, strpos($param, "'") + 1, -1 ))."'";
			}
			$buffer .= $param.", ";
		}
		//remove final "," and "\"s
		$strQuery = str_replace("\\", "", substr($buffer, 0,  -2));
	}
	function custom_convert_to_utf8($text) {
		$encoding = mb_detect_encoding($text, mb_detect_order(), false);
		if($encoding == "UTF-8") {
			$text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
		}
		$out = iconv(mb_detect_encoding($text, mb_detect_order(), false), "UTF-8//IGNORE", $text);
		return $out;
	}
	
	function file_get_contents_utf_ansi($filename, $defAnsiEnc = 'Windows-1251') {
		$buf = file_get_contents($filename);
		if (substr($buf, 0, 3) == "\xEF\xBB\xBF") return substr($buf,3);
		else if (substr($buf, 0, 2) == "\xFE\xFF") return mb_convert_encoding(substr($buf, 2), 'UTF-8', 'UTF-16BE');
		else if (substr($buf, 0, 2) == "\xFF\xFE") return mb_convert_encoding(substr($buf, 2), 'UTF-8', 'UTF-16LE');
		else if (substr($buf, 0, 4) == "\x00\x00\xFE\xFF") return mb_convert_encoding(substr($buf, 4), 'UTF-8', 'UTF-32BE');
		else if (substr($buf, 0, 4) == "\xFF\xFE\x00\x00") return mb_convert_encoding(substr($buf, 4), 'UTF-8', 'UTF-32LE');
		else if (mb_detect_encoding(trim($buf), $defAnsiEnc) || utf8_encode(utf8_decode($buf)) != $buf) return mb_convert_encoding($buf, 'UTF-8', $defAnsiEnc);
		else return $buf;
	}
}
?>