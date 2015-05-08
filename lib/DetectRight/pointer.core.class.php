<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    pointer.core.class.php
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
	DetectRight::registerClass("PointerCore");
}

/**
 * Pointer Class. Its job is to connect identifying strings with entities in the database at
 * particular major/minor/subclass versions.
 * 
 * Its job is no longer holding a complete list of user agent pointer strings: that's the job of the pointerstrings
 * object
 * 
 */
Class PointerCore {
	
	static $dbLink;
	protected $db;
	static $cacheLink;
	protected $cache;
		
	/**
	 * Self-incrementing ID
	 *
	 * @var integer
	 * @access public
	 */
	public $ID;
	
	/**
	 * Type of pointer. We only use it for "UserAgent" in detectRight, most of the time.
	 *
	 * @var string
	 * @access public
	 */
	public $pointertype;

	/**
	 * The processed MD5 of whatever useragent this pointer is attached to 
	 *
	 * @var string
	 */
	public $stringhash;
	
	/**
	 * Major Revision that this pointer is directed to
	 *
	 * @var string
	 * @access public
	 */
	public $majorrevision;
	
	/**
	 * Minor revision that this pointer is directed to
	 *
	 * @var string
	 * @access public
	 */
	public $minorrevision;
	
	/**
	 * The entity id that this pointer pointers to
	 *
	 * @var integer
	 * @access public
	 * @acl 9
	 */
	public $entityid;

	/**
	 * The entity id that this pointer pointers to
	 *
	 * @var string
	 * @access public
	 */
	public $entityhash;

	/**
	 * Subclass that this pointer is directed to
	 *
	 * @var string
	 * @access public
	 */
	public $subclass;
	
	/**
	 * Connection that this pointer is directed to
	 *
	 * @var string
	 * @access public
	 */
	public $connection;
	
	public $build;
	
	/**
	 * Timestamp
	 *
	 * @var timestamp
	 * @access public
	 */
	public $ts;
	
	/**
	 * Status of pointer.
	 *
	 * @var integer
	 * @access public
	 */
	public $status=2;
	
	/**
	 * Owner of pointer
	 * 
	 * @var String
	 */
	public $owner="SYSTEM";
	/**
	 * current table name
	 *
	 * @var string
	 * @access public
	 */
	public $tablename;
	
	/**
	 * Current field list
	 *
	 * @var array
	 * @access public
	 */
	public $fieldList;
	
	/**
	 * Error go here.
	 *
	 * @var string
	 * @access public
	 */
	public $error;
	
	/**
	 * Current primary key field
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $pk;
			
	/**
	 * Entity object associated with this pointer
	 *
	 * @var Entity
	 * @access public
	 * @populate
	 */
	public $entity;
	
	/**
	 * Default tablename
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	static $table="pointers";
	
	/**
	 * Default fieldnames
	 *
	 * @var string
	 * @access public
	 */
	static $fields=array("ID","pointertype","stringhash","majorrevision","minorrevision","status","entityid","entityhash","subclass","connection","ts","pointerhash","build","owner");
	
	/**
	 * Default primary key name
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static $PK="ID";

	/**
	 * Constructor
	 *
	 * @param integer $ID
	 * @param string $pointertype
	 * @param string $pointerstring
	 * @param string $entitytype
	 * @param array $data
	 * @return Pointer
	 * @access public
	 */
	function __construct($ID=0) {
		$this->cacheDB();

		$this->tablename = self::$table;
		$this->fieldList = self::$fields;
		$this->pk = self::$PK;
		
		if ($ID === 0) return;
		$where=array($this->pk=>$ID);

		$pointer=$this->db->simpleFetch($this->tablename,$this->fieldList,$where);
		if (!$pointer) {
			$this->error=$this->db->error;
			return;
		}
		
		if ( count($pointer) === 0)  {
			$this->error="Invalid pointer ID";
			return;
		}
		
		$data=array_shift($pointer);

		foreach($data as $key=>$value) {
			if (property_exists($this,$key)) {
				$this->$key=$value;
			}
		}
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

	/**
	 * Return the official ID for this entity.
	 *
	 * @return mixed
	 * @access public
	 * @internal
	 */
	function id() {
		$key = $this->pk;
		return $this->$key;
	}
	
	/**
	 * Set the primary key ID
	 *
	 * @param integer $value
	 * @access public
	 */
	function setid($value) {
		$key = $this->pk;
		$this->$key = $value;
		return;
	}
	
	/**
	 * Return timestamp
	 *
	 * @return timestamp
	 * @access public
	 */
	function ts() {
		return $this->ts;
	}
	
	/**
	 * Human readable description
	 *
	 * @return string
	 * @access public
	 */
	function description() {
		$entity = $this->getEntity();
		return "$this->pointertype $this->stringhash connected to ".$entity->descriptor()." (status:$this->status) ts: $this->ts";
	}
	
	/**
	 * Return a hashcode uniquely identifying this object
	 *
	 * @return string
	 * @access public
	 */
	function hash() {
		return md5(strtolower("$this->pointertype/$this->stringhash/$this->entityhash"));
	}
	
	/**
	 * Returns array representation of this object
	 * 
	 * @access public
	 */
	function toArray() {
		$return = array();
		$return['pointertype']=$this->pointertype;
		//$return['pointerstring']=$this->pointerstring;
		$entity = $this->getEntity();
		$return['entitytype']=$entity->entitytype;
		$return['entityid']=$entity->entityid;
		$return['entityhash']=$entity->entityhash;
		$return['category']=$entity->category;
		$return['description']=$entity->description;
		$return['majorrevision']=$this->majorrevision;
		$return['minorrevision']=$this->minorrevision;
		$return['subclass']=$this->subclass;
		$return['connection']=$this->connection;
		$return['build'] = $this->build;
		$return['status']=$this->status;
		$return['owner'] = $this->owner;
		return $return;
	}
	
	/**
	 * Returns a string representation of this object
	 * @access public
	 */
	function toString() {
		$return = $this->toArray();
		return implode("\n",$return);
	}
	
	/**
	 * Commit me. Seriously.
	 * @access public
	 */
	function commit() {
		
	}
	
	
	/**
	 * Get the entity related to this nice pointer.
	 *
	 * @return EntityCore
	 * @access public
	 */
	function getEntity() {
		DetectRight::checkPoint("Getting Entity for pointer $this->stringhash");
		// in general, a pointer should already be pointing at something valid.
		// however, it might not, especially at the beginning of a system.
		if (is_object($this->entity)) return $this->entity;
		$entity = null;
		$update = false;
		if ($this->entityid) {
			$entity = EntityCore::getEntity($this->entityid);
			if (is_object($entity) && $entity->id()) {
				$this->entity = $entity;
			} else {
				$entity = EntityCore::getEntityFromHash($this->entityhash);
				if (is_object($entity) && $entity->id()) {
					$this->entityid = $entity->id();
					$update = true;
				}
			}
		}

		if (!is_object($entity)) {
			$entity = new EntityCore(0);
			$entity->status=2;
		}
		
		if ($entity->status == 0) {
			$this->status=0;
		}
		$entity->majorrevision=$this->majorrevision;
		$entity->minorrevision=$this->minorrevision;
		$entity->subclass = $this->subclass;
		$entity->connection = $this->connection;
		$entity->build = $this->build;		
		$this->entity=$entity;
		DetectRight::checkPoint("Got Entity for pointer $this->stringhash");
		return $entity;
	}

	
	/**
	 * This should be in the entity table.
	 * 
	 *
	 * @return string
	 * @access public
	 */
	function entityToString() {
		// returns an entity
		$entity = $this->getEntity();
		return $entity->toString();
	}
	
	/**
	 * Contractual get function
	 *
	 * @param integer $id
	 * @param string $hash
	 * @return Pointer
	 * @static 
	 * @access public
	 */
	static function get($id=0) {
		return new PointerCore($id);
	}
	
	static function pointerFromRow($row) {
		$pointer = new PointerCore(0);
		foreach ($row as $key=>$value) {
			if (property_exists($pointer,$key)) {
				$pointer->$key = $value;
			}
		}
		return $pointer;
	}
	
	static function getPointersForPointerString($pointertype,$pointerstring) {
		$output = array();
		$hash = self::makeHash($pointerstring);
		$rows = self::$dbLink->simpleFetch(self::$table,array("*"),array("pointertype"=>$pointertype,"stringhash"=>$hash));
		foreach ($rows as $row) {
			$output[] = self::pointerFromRow($row);
		}
		return $output;
	}
	
	static function makeHash($ps) {
		if (!$ps) return "";
		$ps = self::punctClean($ps);
		$md5 = md5($ps);
		return $md5;
	}
	
	static function punctClean($ps) {
		if (!$ps) return "";
		$ps = strtolower($ps);
		$string=str_replace(array(" ","-","_","/","*"),"",$ps);
		$string=str_replace(array("\n","\r"),"",$string);
		$string=preg_replace('#[^\+\;\-\)\(\d\w\s\/:.]#','',$string);
		return $string;
	}
	
	static function getESC($pointerType,$pointerString) {
		$esc = new EntitySigCollection;
		$stringhash = self::makeHash($pointerString);
		
		$pointers = self::$dbLink->simpleFetch(self::$table,array("*"),array("pointertype"=>$pointerType,"stringhash"=>$stringhash));
		if ($pointers === false) return $esc;

		$useOwner = false;
		if (count($pointers) > 1) {
			$useOwner = true;
		}
		
		foreach ($pointers as $pointerRow) {
			$pointer = self::pointerFromRow($pointerRow);
			if ($useOwner && $pointer->owner == "SYSTEM") continue; // take the first non-system pointer.
			$entity = $pointer->getEntity();
			$esc->addEntity($entity);
		}
		return $esc;
	}
}