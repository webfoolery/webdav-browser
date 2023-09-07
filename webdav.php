<?php
/********************************************************/
/* Work in progress!                                    */
/* This was intended as a utility to unlock files that  */
/* are stuck in a lock on a remote (or local) webdav    */
/********************************************************/

/*
NB: YOU CAN ALSO UNLOCK A FILE FROM THE COMMAND LINE WITH 2 cURL COMMANDS:
	curl -X PROPFIND '{path-to-resource}' -H 'Authorization:Basic {base64 encoded username:password}' -H 'Depth:1'
	curl -X UNLOCK '{path-to-resource}' -H 'Authorization: Basic {base64 encoded username:password}' -H 'Lock-Token: <{lock-token-from-first-request}>'
*/

// session_start();
date_default_timezone_set('Europe/London');

class webdav {
	public $username;
	public $password;
	public $auth;
	public $location;
	public $host;
	public $port;
	public $endpoint;
	public $fileList;
	public $log;
	public $lockedFiles;
	public $recurseLog = array();
	
	function __construct() {
		$env = parse_ini_file('.env');
		ini_set ('max_execution_time', 1200);
		if (isset($_POST['location'])) {
			$this->location = $_POST['location'];
			$_SESSION['location'] = $_POST['location'];
		}
		else if (isset($_SESSION['location'])) $this->location = $_SESSION['location'];
		else if (isset($env["LOCATION"])) $this->location = $env["LOCATION"];
		else $this->location = null;
		if ($this->location) {
			$this->host = parse_url($this->location, PHP_URL_HOST);
			$this->port = parse_url($this->location, PHP_URL_PORT);
		}
		if (isset($_POST['username'])) {
			$this->username = $_POST['username'];
			$_SESSION['username'] = $_POST['username'];
		}
		else if (isset($_SESSION['username'])) $this->username = $_SESSION['username'];
		else if (isset($env["USERNAME"])) $this->username = $env["USERNAME"];
		else $this->username = null;
		if (isset($_POST['password'])) {
			$this->password = $_POST['password'];
			$_SESSION['password'] = $_POST['password'];
		}
		else if (isset($_SESSION['password'])) $this->password = $_SESSION['password'];
		else if (isset($env["PASSWORD"])) $this->password = $env["PASSWORD"];
		else $this->password = null;
		if (isset($this->username) && isset($this->password)) $this->auth = base64_encode($this->username.':'.$this->password);
		if (isset($_POST['endpoint'])) {
			$this->endpoint = $_POST['endpoint'];
			$_SESSION['endpoint'] = $_POST['endpoint'];
		}
		else if (isset($env["ENDPOINT"])) $this->endpoint = $env["ENDPOINT"];
		else $this->endpoint = null;
		
		if ($this->auth & $this->location & $this->endpoint) {
			$task = (isset($_POST['task']) ? $_POST['task'] : false);
			if ($task && method_exists($this, $task)) $this->{$task}();
			// TRIGGER THE FILE LIST
			$fileList = $this->getlistFilesArray();
		}
	}
	
	function __destruct() {
		// echo 'ended';
	}
	
	function getPropertiesArray() {
		if (isset($this->itemProperties)) return $this->itemProperties;
		$properties = $this->propfind();
		$xmlDoc = new DOMDocument();
		$xmlDoc->loadXML($properties);
		$propArray = $this->xml_to_array($xmlDoc);
		$info = array();
		if (isset($propArray['D:multistatus']['D:response'])) {
			$info['Filename'] = explode('/', $propArray['D:multistatus']['D:response']['D:href']);
			$info['Filename'] = urldecode(array_pop($info['Filename']));
		}
		if (isset($propArray['D:multistatus']['D:response']['D:propstat'])) {
			$info['Type'] = $propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['D:getcontenttype'];
			$info['Created'] = $propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['lp1:creationdate'];
			$info['Size'] = $this->formatBytes($propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['lp1:getcontentlength']);
			$info['Modified'] = $propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['lp1:getlastmodified'];
			$info['eTag'] = $propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['lp1:getetag'];
		}
		if (isset($propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['D:lockdiscovery']['D:activelock'])) {
			$info['Locked by'] = $propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['D:lockdiscovery']['D:activelock']['ns0:owner']['ns0:href'];
			$info['lockToken'] = $propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['D:lockdiscovery']['D:activelock']['D:locktoken']['D:href'];
			$info['Lock expiry'] = $propArray['D:multistatus']['D:response']['D:propstat']['D:prop']['D:lockdiscovery']['D:activelock']['D:timeout'];
		}
		if (count($info)) return $info;
		$this->itemProperties = $propArray;
		return $this->itemProperties;
	}
	
	function showLocked() {
		if (!$this->location || !$this->endpoint || !$this->auth) return false;
		$properties = $this->propfind();
		if (isset($properties['error'])) return $properties;
		$lockedFiles = $this->recurse();
		if (!is_array($this->lockedFiles) || !count($this->lockedFiles)) {
			$this->lockedFiles = array();
		}
		return $this->lockedFiles;
	}
	
	function recurse($endpoint = false) {
		if (!$endpoint) $endpoint = $this->location.$this->endpoint;
		$this->recurseLog[] = $endpoint;
		$items = $this->getlistFilesArray($endpoint);
		foreach ($items as $item) {
			if (!$item) continue;
			if ($item['path'] == '/') continue;
			if (strpos($item['path'], 'recycle')) continue;
			if ($item['path'] == str_replace($this->location, '', $endpoint)) continue;
			if (strpos($item['type'], 'httpd/unix-directory') !==false) {
				$this->recurse($this->location.$item['path']);
			}
			else if ($item['lock']) {
				$this->lockedFiles[] = $item;
			}
		}
		return;
	}
	
	function getlistFilesArray($url = false) {
		if (isset($this->fileList) && !$url) return $this->fileList;
		if (!$this->location || !$this->endpoint || !$this->auth) return false;
		$properties = $this->propfind($url);
		if (isset($properties['error'])) return $properties;
		$xmlDoc = new DOMDocument();
		$xmlDoc->loadXML($properties);
		$propArray = $this->xml_to_array($xmlDoc);
		$elements = $this->removeEmptyArrayElements($propArray);
		$directories = array();
		$directories['/'] = array(
				'path' => '/', 
				'name' => '<span uk-icon="push"></span>/ (root)', 
				'type' => 'httpd/unix-directory', 
				'created' => '', 
				'modified' => '', 
				'lock' => '', 
				'size' => '', 
				'sizeFormatted' => '', 
		);
		$upOneLevel = explode('/', rtrim($this->endpoint, '/'));
		array_pop($upOneLevel);
		$upOneLevel = implode('/', $upOneLevel).'/';
		if (strlen(rtrim($upOneLevel, '/')) && rtrim($upOneLevel, '/') != rtrim($this->endpoint, '/')) {
			$directories[$upOneLevel] = array(
				'path' => $upOneLevel, 
				'name' => '<span uk-icon="arrow-up"></span>'.$upOneLevel, 
				'type' => 'httpd/unix-directory', 
				'created' => '', 
				'modified' => '', 
				'lock' => '', 
				'size' => '', 
				'sizeFormatted' => '', 
			);
		}
		$files = array();
		$counter = 0;
		foreach ($elements['D:multistatus']['D:response'] as $k => $v) {
			$counter++;
			if (!isset($v['D:href'])) continue; // NOT A CLICKABLE RESOURCE
			if (rtrim($v['D:href'], '/') == rtrim($this->endpoint, '/')) continue; // NO NEED FOR REPEATS
			$nameTemp = explode('/', rtrim($v['D:href'], '/'));
			$name = urldecode(array_pop($nameTemp));
			$lock = (isset($v['D:propstat']['D:prop']['D:lockdiscovery']['D:activelock']['ns0:owner']['ns0:href']) ? $v['D:propstat']['D:prop']['D:lockdiscovery']['D:activelock']['ns0:owner']['ns0:href'] : false);
			$type = (isset($v['D:propstat']['D:prop']['D:getcontenttype']) ? $v['D:propstat']['D:prop']['D:getcontenttype'] : false);
			if (substr($v['D:href'], -1) == '/') {
				// DIRECTORY FOUND
				$directories[$v['D:href']] = array(
					'path' => $v['D:href'], 
					'name' => '/'.$name, 
					'type' => $type, 
					'created' => date('Y-m-d H:i:s', strtotime($v['D:propstat']['D:prop']['lp1:creationdate'])), 
					'modified' => date('Y-m-d H:i:s', strtotime($v['D:propstat']['D:prop']['lp1:getlastmodified'])),
					'lock' => $lock, 
					'size' => '', 
					'sizeFormatted' => '', 
				);
			}
			else {
				// FILE FOUND
				$files[$v['D:href']] = array(
					'path' => $v['D:href'], 
					'name' => $name, 
					'type' => $type, 
					'created' => date('Y-m-d H:i:s', strtotime($v['D:propstat']['D:prop']['lp1:creationdate'])), 
					'modified' => date('Y-m-d H:i:s', strtotime($v['D:propstat']['D:prop']['lp1:getlastmodified'])), 
					'lock' => $lock, 
					'size' => $v['D:propstat']['D:prop']['lp1:getcontentlength'], 
					'sizeFormatted' => $this->formatBytes($v['D:propstat']['D:prop']['lp1:getcontentlength']), 
				);
			}
		}
		if ($counter == 2 && !$url) {
			// IT MUST BE A FILE, GET THE PROPERTIES SETTING
			$this->itemProperties = $this->getPropertiesArray();
		}
		array_multisort (array_column($files, 'path'), SORT_NATURAL | SORT_FLAG_CASE, $files);
		array_multisort (array_column($directories, 'path'), SORT_NATURAL | SORT_FLAG_CASE, $directories);
		$fileTree = array_merge($directories, $files);
		if ($url) return $fileTree;
		$this->fileList = $fileTree;
		return $this->fileList;
	}

	function getLockToken() {
		$properties = $this->propfind();
		$d = 'DAV';
		$xmlDoc = new DOMDocument();
		$xmlDoc->loadXML($properties);
		$xpath = new DOMXpath($xmlDoc);
		$xpath->registerNamespace('d', 'DAV:');
		$lockToken = $xpath->evaluate('string(/d:multistatus/d:response/d:propstat/d:prop/d:lockdiscovery/d:activelock/d:locktoken/d:href)');
		return $lockToken;
	}

	function unlock () {
		$lockToken = $this->getLockToken();
		$altUsername = (isset($_POST['altUsername']) ? $_POST['altUsername'] : false);
		$altPassword = (isset($_POST['altPassword']) ? $_POST['altPassword'] : false);
		if ($altUsername && $altPassword) $auth = base64_encode($altUsername.':'.$altPassword);
		else $auth = $this->auth;
		$ch = curl_init();
		// FIX LOCALHOST SSL CERTIFICATE ISSUES
		if ($_SERVER['SERVER_NAME'] == 'localhost') curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_URL, $this->location.$this->endpoint);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'UNLOCK');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Host: '.$this->host, 
			'Authorization: Basic '.$auth, 
			'Lock-Token: <'.$this->getLockToken().'>', 
		));
		$response = curl_exec($ch);
		$curlInfo = curl_getinfo($ch);
		if(curl_error($ch)) {
			$this->unlockStatus = 'ERROR: '.curl_error($ch).print_r($curlInfo,1);
			return $this->unlockStatus;
		}
		curl_close($ch);
		$this->unlockStatus = array(
			'status' => ($curlInfo['http_code'] == '204' ? 'ok' : 'Fail'), 
			'response' => htmlentities($response), 
			'curlInfo' => $curlInfo, 
		);
		return $this->unlockStatus;
	}

	function propfind($url = false) {
		if (!$url) {
		if (isset($this->propfind) && !isset($this->unlockStatus)) return $this->propfind;
		$url = $this->location.$this->endpoint;
		}
		$xml = '<?xml version="1.0" encoding="utf-8" ?><D:propfind xmlns:D="DAV:"><D:prop><D:creationdate/><D:getlastmodified/><D:getcontentlength/></D:prop></D:propfind>';
		$ch = curl_init();
		// FIX LOCALHOST SSL CERTIFICATE ISSUES
		if ($_SERVER['SERVER_NAME'] == 'localhost') curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$verbose = fopen('php://temp', 'w+');
		curl_setopt($ch, CURLOPT_STDERR, $verbose);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// curl_setopt($ch, CURLOPT_POST, 1);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: text/xml; charset="utf-8"',
			'Host: '.$this->host, 
			'Authorization: Basic '.$this->auth, 
			'Depth: 1', 
		));
		$response = curl_exec($ch);
		$curlInfo = curl_getinfo($ch);
		rewind($verbose);
		$this->verboseLog = stream_get_contents($verbose);
		if(curl_error($ch)) {
			return array('error'=>curl_errno($ch).': '.curl_error($ch), 'response'=>print_r($curlInfo,1));
		}
		curl_close($ch);
		if (!$url) $this->propfind = $response;
		return $response;
	}
	
	function removeEmptyArrayElements($haystack) {
		// SOURCE: https://stackoverflow.com/a/7696597
		foreach ($haystack as $key => $value) {
			if (is_array($value)) $haystack[$key] = $this->removeEmptyArrayElements($haystack[$key]); 
			if (empty($haystack[$key])) unset($haystack[$key]);
		}
		return $haystack;
	}
	
	function xml_to_array($xmlDoc) {
		// SOURCE: https://stackoverflow.com/a/14554381
		$result = array();
		if ($xmlDoc->hasAttributes()) {
			$attrs = $xmlDoc->attributes;
			foreach ($attrs as $attr) {
				$result['@attributes'][$attr->name] = $attr->value;
			}
		}
		if ($xmlDoc->hasChildNodes()) {
			$children = $xmlDoc->childNodes;
			if ($children->length == 1) {
				$child = $children->item(0);
				if ($child->nodeType == XML_TEXT_NODE) {
					$result['_value'] = $child->nodeValue;
					return count($result) == 1 ? $result['_value'] : $result;
				}
			}
			$groups = array();
			foreach ($children as $child) {
				if (!isset($result[$child->nodeName])) {
					$result[$child->nodeName] = $this->xml_to_array($child);
				} else {
					if (!isset($groups[$child->nodeName])) {
						$result[$child->nodeName] = array($result[$child->nodeName]);
						$groups[$child->nodeName] = 1;
					}
					$result[$child->nodeName][] = $this->xml_to_array($child);
				}
			}
		}
		return $result;
	}
	
	function formatBytes($bytes, $precision = 2) {
		// SOURCE: https://stackoverflow.com/a/2510459
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		// $bytes /= (1 << (10 * $pow));
		return round($bytes, $precision) . ' ' . $units[$pow];
	} 
 
	function makeHtpasswd($user, $pass) {
		$encrypted_password = $this->crypt_apr1_md5($pass);
		echo $user.':'.$pass.' = '.$user . ':' . $encrypted_password;
	}
	
	function crypt_apr1_md5($plainpasswd) {
		// SOURCE: https://stackoverflow.com/a/41079166
		// APR1-MD5 encryption method (windows compatible)
		$salt = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8);
		$len = strlen($plainpasswd);
		$text = $plainpasswd.'$apr1$'.$salt;
		$bin = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
		for($i = $len; $i > 0; $i -= 16) { $text .= substr($bin, 0, min(16, $i)); }
		for($i = $len; $i > 0; $i >>= 1) { $text .= ($i & 1) ? chr(0) : $plainpasswd{0}; }
		$bin = pack("H32", md5($text));
		for($i = 0; $i < 1000; $i++) {
			$new = ($i & 1) ? $plainpasswd : $bin;
			if ($i % 3) $new .= $salt;
			if ($i % 7) $new .= $plainpasswd;
			$new .= ($i & 1) ? $bin : $plainpasswd;
			$bin = pack("H32", md5($new));
		}
		$tmp = null;
		for ($i = 0; $i < 5; $i++) {
			$k = $i + 6;
			$j = $i + 12;
			if ($j == 16) $j = 5;
			$tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
		}
		$tmp = chr(0).chr(0).$bin[11].$tmp;
		$tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
		"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
		"./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
		return "$"."apr1"."$".$salt."$".$tmp;
	}
}


