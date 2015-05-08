<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    entitysig.class.php
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
	DetectRight::registerClass("EntitySig");
}

Class EntitySig {
	public $sig;
	public $aDescriptor;
	public $descriptor;
	public $entity;
	public $path;
	public $importance=0; // importance offset
	
	/**
	 * a tree for the weekend
	 *
	 * @var QuantumDataTree
	 */
	public $qdt;
	
	static $cacheLink;
	protected $cache;
	
	static $dbLink;
	protected $db;
	
	public function __construct($array,$sig="",$path="") {
		$this->cacheDB();

		$this->path = $path;
		$this->sig = $sig;
		$this->aDescriptor = $array;
		foreach ($array as $key => $string) {
			$array[$key] = DetectRight::escapeDescriptor($string);
		}
		$this->descriptor = implode(":",$array);
		while (substr($this->descriptor,-1,1) === ":") {
			$this->descriptor = substr($this->descriptor,0,-1);
		}

		$this->qdt = new QuantumDataTree($this->descriptor,null);
	}
	
	public function __destruct() {
		$this->cache = null;
		$this->db = null;
		$this->qdt = null;		
		$this->entity = null;
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

	
	public function addQDT($qdt) {
		$this->qdt->addQDT($qdt);
	}
	
	public function setQDT($qdt) {
		$this->qdt = $qdt;
	}
	
	public function addProperties($properties) {
		// array of properties which need putting into a PropertyCollection
			$this->qdt->addPackage($properties,0,"RealTime");
	}
	
	public function addProperty($property) {
		$this->addProperties(array($property));
	}
	
	public function getEntityType() {
		if (!is_array($this->aDescriptor) || count($this->aDescriptor) === 0) return "";
		$desc = $this->aDescriptor;
		$et = array_shift($desc);
		return $et;
	}
	
	public function getEntity() {
		$this->fillEntity();
		return $this->entity;
	}

	public function entityid() {
		$this->fillEntity();
		return $this->entity->id();
	}
	
	public function fillEntity() {
		if (DRFunctionsCore::isEmptyStr($this->descriptor)) return;
		if (is_object($this->entity)) return;
		$entity = EntityCore::getEntityFromDescriptor($this->descriptor,true,true);
		if (is_object($entity)) {
			$this->entity = $entity;
		}
		//$this->entity->profileChanges = $this->properties;
	}
}