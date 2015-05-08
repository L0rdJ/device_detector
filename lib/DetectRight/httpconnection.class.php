<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    httpconnection.class.php
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
	DetectRight::registerClass("HTTPConnection");
}

Class HTTPConnection {
	static $dbLink;
	protected $db;
	
	public $carrier;
	public $mobile = false;
	public $connectiontype;
	public $currentbearer;
	public $connection;
	public $stack;
	public $ssl;
	public $sdu;
	public $pdu;
	public $secureURI;
	public $confidence;
	
	public static function setDBLink(DBLink $dbLink) {
		self::$dbLink = $dbLink;
	}
	
	public function __construct() {
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
		$this->db = self::$dbLink;
	}
	
	public function setCarrier($carrier) {
		// load carrier from table, then pick right one and populate.
		// @also todo: populate this instead of realtime array from code above.
		$this->carrier = $carrier;
		$this->fillFromCarrier($carrier);
	}
	
	public function fillFromCarrier($carrier) {
		$carriers = $this->db->simpleFetch("carriers",array("*"),array("carrier"=>$carrier));
		if ($carriers === null || $carriers === false) {
			$carriers = array();
		}
		$carrierArray = array_shift($carriers);
		$this->setVars($carrierArray);
	}
	
	public function getCarrier() {
		return $this->carrier;
	}
	
	public function getCurrentBearer() {
		return $this->currentbearer;
	}
	
	public function setCurrentBearer($bearer) {
		$this->currentbearer = $bearer;
	}
	
	public function getConnectionType() {
		return $this->connectiontype;
	}
	
	public function setConnectionType($type) {
		$this->connectiontype = $type;
	}
	

	public function getConnection() {
		return $this->connectiontype;
	}
	
	public function setConnection($type) {
		$this->connectiontype = $type;
	}
	
	public function getIsMobile() {
		return $this->mobile;
	}
	
	public function setIsMobile($bool) {
		$this->mobile = $bool;
	}
		
	public function getVars() {
		return get_object_vars($this);
	}
	
	public function setVars($array) {
		if (!is_array($array)) {
			$array = array();
		}
		foreach ($array as $key=>$value) {
			if (property_exists(__CLASS__,$key)) {
				if (DRFunctionsCore::isEmptyStr($this->$key)) {
					$this->$key = $value;
				}
			}
		}
	}

	public function setStack($stack) {
		$this->stack = $stack;
	}
	
	public function getStack() {
		return $this->stack;
	}
	
	public function setSecure($bool) {
		$this->ssl = $bool;
	}
	public function getSecure() {
		return $this->ssl;
	}
	
	public function getSecureURI() {
		return $this->secureURI;
	}
	
	public function setSecureURI($uri) {
		$this->secureURI = $uri;
	}
	
	public function getSdu() {
		return $this->sdu;
	}
	
	public function setSdu($sdu) {
		$this->sdu = $sdu;
	}
}