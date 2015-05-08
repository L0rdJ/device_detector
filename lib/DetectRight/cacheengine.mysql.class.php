<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage Cache
 */
/******************************************************************************
Name:    cacheengine.mysql.class.php
Version: 2.7.0
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

© 2012 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License 
Agreement, the latest version of which can be found at 

http://www.detectright.com/legal-and-privacy.html

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance, 
for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com

**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRMySQLCache");
}

/**
 * Memcache cache engine for DetectRight.
 * @internal
 */
Class DRMySQLCache extends DRCache {

	static $tableSQL = 'CREATE TABLE `cache` (
  `idcache` int(10) unsigned NOT NULL auto_increment,
  `key` varchar(512) collate latin1_general_cs NOT NULL,
  `object` mediumblob,
  `expiry` timestamp NULL default NULL,
  `vartype` varchar(40) character set latin1 collate latin1_general_ci default NULL,
  `serialized` tinyint(1) default \'0\',
  PRIMARY KEY  (`idcache`),
  UNIQUE KEY `key_UNIQUE` (`key`,`serialized`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs';
	
	static $table = "cache";
	public $params;
	
	function __construct($hash) {
		$this->engine = "MySQL";
		$this->hash = $hash;
	}
	
	/**
	 * Check if the cache object is OK
	 *
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_ok() {
		if (!is_object($this->cache)) return false;
		if (!$this->cacheOK) return false;
		if (!$this->enabled) return false;
		return true;
	}

	/**
	 * Close the cache. Discard errors.
	 *
	 * @access public
	 * @internal
	 */
	function cache_close() {
		if (is_object($this->cache)) {
			mysqli_close($this->cache);
		}
		parent::cache_close();
	}

	/**
	 * Increment a cache key
	 *
	 * @param string $key
	 * @internal
	 * @access public
	 * @return integer
	 */
	function cache_increment($key) {
		if (!$this->cache_ok()) return false;
		if (!self::$useCache) return false;
		$key = $this->_normalize($key);
		// note that this will only work for non-serialized variables
		$sql = "update ".self::$table." set value = value + 1 where `key`='$key' and serialized=0";
		$success = mysqli_query($this->cache,$sql);
		if ($success === false) {
			$this->throwError("MySQL increment failure $key");
		}
		return $success;
	}

	private function _normalize($key) {
		if (strlen($key) > 511) {
			$key = md5($key);
		} else {
			$key = mysqli_real_escape_string($this->cache,$key);
		}
		return $key;
	}
	
	/* Delete a cache key
	 *
	 * @param string $key
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_delete($key) {
		if (!$this->cache_ok()) return;
		$key = $this->_normalize($key);
		$success = mysqli_query($this->cache,"delete from ".self::$table." where key = '$key'");
		if ($success === false) {
			$this->throwError("Memcache Delete error");
		}
	}

	/**
	 * Set cache
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param boolean $compression
	 * @param integer $timeout
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_set($key,$value,$timeout=0) {
		if (!$this->cache_ok()) return false;
		if (!self::$useCache) return false;
		$key = $this->_normalize($key);
		return $this->set($key,$value,$timeout);
	}

	protected function set($key,$value,$timeout) {
		$diag = DetectRight::$DIAG;
		if (DRFunctionsCore::isEmptyStr($key)) return false;
		if ($diag) $start=DRFunctionsCore::mt();
		if ($timeout < 0) $timeout = 1;
		$expiry = time() + $timeout;
		if ($timeout === 0) {
			$expiry = "NULL"; // never expires
		}
		
		$serialized = 0;
		if (!is_scalar($value)) {
			$value = serialize($value);
			$value = mysqli_real_escape_string($this->cache,$value);
			$serialized = 1;
		} elseif (is_string($value)) {
			$value = mysqli_real_escape_string($this->cache,$value);
		}

		$query = "insert delayed into `".self::$table."` (`key`, `object`,`expiry`,`serialized`) VALUES ('$key', '$value',$expiry,$serialized)";
		$result = mysqli_query($this->cache,$query);

		if ($diag) $end = DRFunctionsCore::mt();
		if ($diag) $timeTaken = $end - $start;
		if ($diag) $this->set_time = $this->set_time + $timeTaken;

		if ($result===false) {
			DetectRight::checkPoint("MySQL Set Fail $key");
			$this->throwError("MySQL Set Fail $key");
		}
		return $result;
	}
	/**
	 * Get a key from the cache
	 *
	 * @param string $key
	 * @return mixed
	 * @access public
	 * @internal
	 */
	function cache_get($key) {
		if (!$this->cache_ok()) return null;
		if (!self::$useCache) return null;
		$key = $this->_normalize($key);
		if (DetectRight::$flush) {
			$this->cache->delete($key);
			return null;
		}
		$ret = $this->get($key);
		return $ret;
	}

	protected function get($key) {
		$diag = DetectRight::$DIAG;
		if ($diag) DetectRight::checkPoint("MySQL get start");
		if ($diag) $start = DRFunctionsCore::mt();
		$query = "select `object`, UNIX_TIMESTAMP(`expiry`) as `expiry`, `serialized` from `".self::$table."` where `key`='$key'";
		$result = mysqli_query($this->cache,$query);
		if (!is_object($result)) return null;
		$row = mysqli_fetch_assoc($result);
		mysqli_free_result($result);
		if (!$row) return null;
		$expiry = $row['expiry'];
		if ($expiry > 0 && $expiry !== null && $expiry < time()) {			
			mysqli_query($this->cache,"delete from `".self::$table."` where `key`='$key'");
			return null;
		}
		$serialized = $row['serialized'];
		$value = $row['object'];
		if ($serialized) {
			$value = unserialize($value);
		}
		return $value;
	}
	/**
	 * Initiate the cache with some HTTP variables which allow us to tweak the cache a bit.
	 *
	 * @param array $params
	 * @access public
	 * @internal
	 */
	function cache_init($params) {
		if (is_object($this->cache)) return;
		if (!extension_loaded("mysqli")) {
			throw new DetectRightException("MySQLi not installed");
		} 
		
		$this->params = $params;
		$host = DRFunctionsCore::gv($params,'address');
		$port = DRFunctionsCore::gv($params,'port',"3306");
		$database = DRFunctionsCore::gv($params,'bucket');
		$user = DRFunctionsCore::gv($params,'username');
		$password = DRFunctionsCore::gv($params,'password');
		
		$retval = @mysqli_connect( $host,$user, $password,$database,$port);
		if (!is_object($retval)) {
			$this->throwError(mysqli_error());
			return;	
		}
		
		$this->cache = $retval;		
		parent::cache_init($params);
	}
	
	function cache_flush() {
		if (!$this->cache_ok()) return false;
		try {
			mysqli_query($this->cache,"delete from ".self::$table);
		} catch (Exception $e) {
			
		}
		//$success = $this->cache->query("truncate table {idd}$this->table{idd}");
		return true;
	}

}

if (!class_exists("MySQLCache")) {
	DetectRight::registerClass("MySQLCache");
	
	Class MySQLCache extends DRMySQLCache {
		
	}
}
