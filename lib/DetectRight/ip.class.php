<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    ip.class.php
Version: 2.0.0
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
	DetectRight::registerClass("IP");
}
/**
 * Top level IP object to handle address ranges, geos and stuff.
 *
 */
Class IP {

	public $ip="";
	public $longIP=0;
	public $org; // isp,countrycode,country
	public $geo;
	public $commtype; // GPRS/WIFI/LAN/EVDO/CSD
	
	public $ipBlock;
	public $ipRange;
	
	static $cacheLink;
	protected $cache;
	
	static $dbLink;
	protected $db;
	
	public function __construct($ip) {
		$this->cacheDB();
		if (DRFunctionsCore::isEmptyStr($ip)) return;
		$this->ip = $ip;
		$this->longIP = self::tolong($ip);
	}
	
	public function __tostring() {
		return $this->ip;
	}
	
	public function cacheDB() {
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		$this->cache = self::$cacheLink;
		
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
		$this->db = self::$dbLink;		
	}
	
	public function __wakeup() {
		$this->cacheDB();
	}
	
	public function __sleep() {
		/*$this->cache = null;
		$this->db = null;*/
		$ov = get_object_vars($this);
		unset($ov['cache']);
		unset($ov['db']);
		return array_keys($ov);
	}

	public function process($serverVars) {
		if (DRFunctionsCore::isEmptyStr($this->longIP) && DRFunctionsCore::isEmptyStr($this->ip)) return;
		if (DRFunctionsCore::isEmptyStr($this->longIP)) $this->longIP = self::Dot2LongIP($this->ip);
	}
		
	/**
 	* Convert a dotted IP to a long integer
 	*
 	* @param string $IPaddr
 	* @return integer
	* @static 
	* @access public
	* @internal
 	*/
	static function Dot2LongIP ($IPaddr)
	{
		if (stripos($IPaddr,".") === false && stripos($IPaddr,":") === false) {
			return 0;
		} else {
			return inet_pton($IPaddr);
		}
	}	
	
	static function checkLocalIP($ip) {
		// check for use of local IP addresses.
		if (!strpos($ip,'.')) {
			return true;
		}

		$ip=explode(".",$ip);
		if ($ip[0]=="192" && $ip[1]="168") return true;
		if ($ip[0]=="10") return true;
		if ($ip[0]=="172" && ($ip[1]>15 && $ip[1]<32)) return true;
		return false;
	}
	
	static function tolong($ip) {
		$integer_ip = (substr($ip, 0, 3) > 127) ? ((ip2long($ip) & 0x7FFFFFFF) + 0x80000000) : ip2long($ip);
		return $integer_ip;
	}
	
	static function toip($long) {
		return long2ip($long);
	}	
}