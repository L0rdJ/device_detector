<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    entityprofile.core.class.php
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
	DetectRight::registerClass("EntityProfileCore");
}

/**
 * Entity profile class. Holds essentially compiled data from the external_data table.
 * 
 * @internal
 */

Class EntityProfileCore {	
	
	static $useCache = false;
	static $qdtCache = false;
	static $dbLink;
	protected $db;	
	static $cacheLink;
	protected $cache;
	
	static $hashedIdentifiers = false;
	
	/**
	 * Auto ID
	 *
	 * @var integer
	 * @access public
	 */
	public $ID;
	
	/**
	 * Entity ID this belongs to
	 *
	 * @var integer
	 * @access public
	 * @acl 9
	 */
	public $entityid;

	/**
	 * Entity ID this belongs to
	 *
	 * @var string
	 * @access public
	 * @acl 1
	 */
	public $entityhash;

	/**
	 * Entity profile hash, generated from a series of fields.
	 *
	 * @var string
	 * @access public
	 */
	public $hash;
	
	/**
	 * Data source.
	 *
	 * @var string
	 * @access public
	 */
	public $source; // trust level is inherited from this.
	
	/**
	 * The QuantumDataTree object this contains
	 *
	 * @var QuantumDataTree
	 * @access public
	 */
	public $data;
	
	/**
	 * The major revision this is attached to
	 *
	 * @var string
	 * @access public
	 */
	public $majorrevision;
	
	/**
	 * The Minor revision that this is attached to
	 *
	 * @var string
	 * @access public
	 */
	public $minorrevision;
	
	/**
	 * The subclass that this is attached to. The subclass is important for differentiating between different
	 * variants of device, such as a Nokia N95-3 or a Nokia N95-1, for instance.
	 *
	 * @var string
	 * @access public
	 */
	public $subclass;
	
	/**
	 * Does this profile refer to a 2G or 3G variant of the device? Some UAProfiles are meant for 2G or 3G (or GPRS)
	 * use.
	 *
	 * @var string
	 * @access public
	 */
	public $connection;

	public $build;
	
	/**
	 * MD5 of the contents of the profile field. Meant to help working out if something's changed.
	 *
	 * @var string
	 * @access public
	 */
	public $crchash;
		
	/**
	 * Just a regular timestamp
	 *
	 * @var timestamp
	 * @access public
	 */
	public $ts;
	
	/**
	 * Link to the DR_EXTERNAL table. Human readable for interest, though incriminating ;-)
	 *
	 * @var string
	 * @access public
	 * @acl 9
	 */
	public $identifier;
	
	/**
	 * Owner of the data. Would mostly be "SYSTEM".
	 *
	 * @var string
	 * @access public
	 */
	public $owner;

	/**
	 * Primary key name for the object.
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $pk="ID";
	
	/**
	 * Eventually filled with an array of valid fields in the database table.
	 * note that this can be changed if we decided to temporarily point this at a separate table.
	 *
	 * @var array
	 * @access public
	 * @internal
	 */
	public $fieldList;
	
	/**
	 * Current table being used
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $tablename;
	
	/**
	 * Oooh, Betty. Holds errors for an object (not the class).
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $error="";
	
	/**
	 * Default table to use
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static $table = "entity_profiles";
	
	/**
	 * Error for static functions
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static $ERROR="";
	
	/**
	 * Cache timeout/lifetime
	 *
	 * @staticvar integer
	 * @access public
	 * @internal
	 */
	static $cache_timeout=480; // not huge: entities are cached for longer.
	
	/**
	 * Fields in default table. Stored as string because.. well, just because.
	 *
	 * @staticvar array
	 * @access public
	 * @internal
	 */
	static $fields=array("ID","entityid","entityhash","source","data","majorrevision","minorrevision","identifier","hash","ts","owner","crchash","subclass","connection","build");

	protected $qdt;
	public $pkg = array();
	
	/**
	 * Constructor
	 *
	 * @param integer $ID
	 * @param string $hash
	 * @param array $row
	 * @return EntityProfileCore
	 * @internal
	 * @access public
	 */
	function __construct($ID=0,$hash="",$row="") {
		$this->cacheDB();

		$this->fieldList = self::$fields;
		$this->tablename = self::$table;
		if ($ID) {
			//$ID=escape_string($uaHash);
			$where = array($this->pk=>$ID);
		} elseif ($hash) {
			$where = array("hash"=>$hash);
		} elseif ($row) {
			$newResult=$row;
		} else {
			return;
		}

		if (isset($where)) {
			$result=$this->db->simpleFetch($this->tablename,$this->fieldList,$where);
			if ($result===false) {
				$this->error=$this->db->error;
				return;
			}
			if (count($result)==0) {
				$this->error="Non-existent profile...";
				return;
			}
			$newResult=array_shift($result);
		}
		
		foreach($newResult as $key=>$value) {
			if (property_exists($this,$key)) {
				$this->$key=$value;
			}
		}
	}
	
	public function __destruct() {
		$this->cache = null;
		$this->db = null;
	}
	
	public function close($destroyQDT = true) {
		$this->cache = null;
		$this->db = null;
		unset($this->entity);
		if ($destroyQDT && is_object($this->qdt)) {
			$this->qdt->close();
		}
		unset($this->qdt);
	}
	
	public function cacheDB() {
		if (self::$cacheLink === null) self::$cacheLink = DetectRight::$cacheLink;
		$this->cache = self::$cacheLink;
		
		if (self::$dbLink === null) self::$dbLink = DetectRight::$dbLink;
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
		unset($ov['qdt']);
		return array_keys($ov);
	}

	/**
	 * returns the unique hash for this
	 *
	 * @return string
	 * @internal
	 * @access public
	 */
	function hash() {
		return $this->hash;
	}

	/**
	 * Get ID
	 *
	 * @return integer
	 * @internal
	 * @access public
	 */
	function id() {
		$pk = $this->pk;
		return $this->$pk;
	}

	/**
	 * To string
	 * @internal
	 * @access public
	 *
	 */
	function toString() {
		return "Entity Profile from source $this->source for entity $this->entityid for majorrevision $this->majorrevision, minorrevision $this->minorrevision, subclass $this->subclass connection $this->connection derived from $this->identifier";
	}
	
	
	/**
	 * Get the appropriate timestamp for this object
	 *
	 * @return timestamp
	 * @internal
	 * @access public
	 */
	function ts() {
		return strtotime($this->ts);
	}

		
	function fill() {
		// is it already filled?
		if ($this->qdt !== null || ($this->pkg !== null && isset($this->pkg[0])) || DRFunctionsCore::isEmpty($this->data)) {
			return;
		}

		$key = DetectRight::cacheKey("epQDT".$this->id());
		$qdt = null;
		if (self::$qdtCache) {
			$qdt = self::$cacheLink->cache_get($key);
		}
		if (is_object($qdt) && get_class($qdt) == "QuantumDataTree") {
			$this->qdt = $qdt->_clone();
		} else if ($this->data !== null && $this->data !== "")  {
			$tmp = DRFunctionsCore::ungz($this->data);
			if ($tmp !== null) {
				if (is_string($tmp))  {
					$tmpString = $tmp;
					if (!strpos($tmpString,"source") !== false  && !DRFunctionsCore::isEmpty($this->source)) {
						$tmpString = str_replace("}",";source:$this->source}",$tmpString);
					}
					$this->pkg = explode("\n",$tmpString);
					$creator=$this->source + "/" + $this->identifier;
					$this->qdt = new QuantumDataTree("",null);
					$this->qdt->addPackage($this->pkg);
					$this->qdt->processPackages();
					if (DetectRight::$DIAG) {
						$this->qdt->printTree("Entity profile " . $this->ID);
					}

				} else {
					if (is_object($tmp)) {
						if (get_class($tmp) === "QuantumDataTree") {
							$this->qdt = $tmp;
							if (DetectRight::$DIAG) {
								$this->qdt->printTree("Entity profile " . $this->ID);
							}
						} 
					} else {
						// not an object or a string? We'd better ignore.
						return;
					}
				}
			} 
			
			if (self::$qdtCache) {
				$success = self::$cacheLink->cache_set($key,$this->qdt->_clone(),self::$cache_timeout);
			}
		} 

		if ($this->id()) {
			$this->qdt->entityid = $this->entityid;
			$this->qdt->majorrevision = $this->majorrevision;
			$this->qdt->minorrevision = $this->minorrevision;
			$this->qdt->subclass = $this->subclass;
			$this->qdt->connection = $this->connection;			
			$this->qdt->metadata['entityprofileid']=$this->id();
		}		
	}

	/**
	 * Get the essential Property Collection
	 *
	 * @return QuantumDataTree
	 * @internal
	 * @access public
	 */
	function getQDT() {
		//echo "Filling...";
		$this->fill();
		//echo "new QDT";
		if ($this->qdt === null) return new QuantumDataTree("",null);;
		return $this->qdt;
	}
	
	function getData() {
		return $this->data;
	}
	
	function getPackage() {
		return $this->pkg;
	}
	/**
	 * Creates an array representation of this.
	 *
	 * @param boolean $profile
	 * @param boolean $pointers
	 * @return associative_array
	 * @access public
	 * @internal
	 */
	function toArray($data=false,$pointers=false) {
		$return=array();
		$include="ID,source,majorrevision,minorrevision,subclass,connection,identifier,hash,ts,owner,status";
		$include=explode(",",$include);

		foreach ($include as $field) {
			$return[$field]=$this->$field;
		}

		/* @public $this->data QuantumDataTree */
		if ($data) {
			$return['data']=$this->pkg;
		}

		// get pointers
		if ($pointers) {
			/*$pointerIDs = PointerCore::getIdentifiedPointers($this->identifier);
			foreach ($pointerIDs as $pointerID) {
				$pointer = new PointerCore($pointerID);
				$return['pointers'][]=$pointer;
			}*/
		}
		return $return;
	}
	
	/**
	 * Get an export package for this entity
	 * @internal
	 * @access public
	 * @todo
	 */
	function toExport() {
		
	}
	
	/**
	 * Returns a human readable brief description of this.
	 * @internal
	 * @access public
	 */
	function description() {
		return "Entity profile $this->id() from $this->source ($this->subclass:$this->majorrevision:$this->minorrevision:$this->connection) owned by $this->owner";
	}
	
	function commit() {
		// does nothing. It's up to the non-core Entity Profile to do that.
	}

	function update() {
		// does nothing. It's up to the non-core Entity Profile to do that.
	}
	
	function getEntity() {
		if (DRFunctionsCore::isEmpty($this->ID)) return null;
		if ($this->ID === 0) return null;
		if (DRFunctionsCore::isEmpty($this->entityid)) return null;
		$entity = EntityCore::get($this->entityid);
		$entity->majorrevision = $this->majorrevision;
		$entity->minorrevision = $this->minorrevision;
		$entity->subclass = $this->subclass;
		$entity->build = $this->build;
		$entity->connection = $this->connection;
		return $entity;
	}
		
	/**
	 * Cacheing the entity profile
	 *
	 * @param integer $timeout
	 * @internal
	 * @access public
	 * @static
	 */
	function cache($timeout=0) {
		if (!$timeout) $timeout = self::$cache_timeout;
		if (self::$useCache && $this->id() && $this->data) {
			$this->cache->cache_set($this->cacheKey(),$this,$timeout);
		}
	}
	
	function cacheKey() {
		return self::getCacheKey($this->id);
	}
	
	static function getCacheKey($id) {
		return DetectRight::cacheKey("ep_".$id);
	}
	/**
	 * Getting an Entity Profile more indirectly. Useful because it invokes cacheing when necessary.
	 *
	 * @param integer $id
	 * @param string $hash
	 * @return EntityProfile
	 * @acl 9
	 * @access public
	 * @static
	 */
	static function get($id=0,$hash="") {
		$ep = false;
		if ($id && self::$useCache) $ep = self::$cacheLink->cache_get(self::getCacheKey($id));
		if ($ep === null || $ep === false) {
			$ep = new EntityProfileCore($id,$hash);
			if ($ep->id()) {
				if (self::$useCache) $ep->cache();
			} else {
				$ep->error="";
			}
		}
		return $ep;
	}
	
	/**
	 * Get an entity profile from its identifier. 
	 * There is now only a 1:1 relationship between an entity profile and an external data source
	 *
	 * @param string $identifier
	 * @return EntityProfile
	 * @acl 9
	 * @access public
	 * @static
	 */
	static function getEPFromIdentifier($identifier) {
		if (DRFunctionsCore::isEmpty($identifier)) return null;
		if (self::$hashedIdentifiers) $identifier = md5($identifier);
		$key = DetectRight::cacheKey("DREPC_".$identifier);
		$epid = self::$cacheLink->cache_get($key);
		if ($epid) {
			$ep = self::get($epid);
			return $ep;
		}
		
		$result = self::$dbLink->simpleFetch(self::$table,array("*"),array("identifier"=>$identifier),"",array("limit"=>1));
		if ($result === false) return false;
		
		$row=array_shift($result);
		if ($row !== null) {
			$ep = new EntityProfileCore(0,"",$row);
			if ($ep->id()) {
				$ep->cache();
				self::$cacheLink->cache_set($key,$ep->id(),self::$cache_timeout);
			}
			return $ep;
		} 
		
		return null;
	}
	
	static function getEntityFromIdentifier($identifier="") {
		if (DRFunctionsCore::isEmpty($identifier)) return null;
		$ep = EntityProfileCore::getEPFromIdentifier($identifier);
		if (!is_object($ep)) {
			return null;
		}
		$entity = $ep->getEntity();
		return $entity;
	}
	/**
	 * Get the entity profiles for an entity
	 *
	 * @param integer $id
	 * @return array
	 * @acl 9
	 * @access public
	 * @static
	 */
	static function getEPsForEntity($id,$majorrevision="",$minorrevision="",$source="") {
		$profiles=array();
		if (!$id) return array();
		$profiles = self::$dbLink->simpleFetch(self::$table,array("*"),array("entityid"=>$id),"","",array("ID"));
		$output=array();
		foreach ($profiles as $profile) {
			$ep=new EntityProfileCore(0,"",$profile); // this should create a profile without reading from the db again.
			$do=true;
			if ($source && $source !== $ep->source) {
				$do=false;
			}
			if ($majorrevision && $majorrevision !== $ep->majorrevision) {
				$do=false;
			}
			
			if ($minorrevision && $minorrevision !== $ep->minorrevision) {
				$do=false;
			}
			
			if ($do) {
				$output[$ep->id()]=$ep;
				$ep->cache();
			}
		}
		return $output;
	}
	
	/**
	 * Get the entity profiles for an entity
	 *
	 * @param integer $id
	 * @return array
	 * @acl 9
	 * @access public
	 * @static
	 */
	static function getEPsForEntityHash($hash,$majorrevision="",$minorrevision="",$source="") {
		if (!$hash) return array();
		$entity = EntityCore::getEntityFromHash($hash);
		if (is_object($entity)) {
			return self::getEPsForEntity($entity->id(),$majorrevision,$minorrevision,$source);
		}
		return array();
	}
	
	/**
	 * Get an Entity Profile from a row in its table.
	 *
	 * @param array $row
	 * @return EntityProfile
	 * @internal
	 * @access public
	 * @static
	 */
	static function getEPFromRow($row) {
		$ep = new EntityProfileCore(0,"",$row);
		/*if ($ep->id()) {
			$ep->cache();
		}*/
		return $ep;
	}	
}