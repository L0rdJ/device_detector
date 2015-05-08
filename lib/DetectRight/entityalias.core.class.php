<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    entityalias.core.class.php
Version: 2.8.0
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
2.8.0 - Made sure that entity aliases aren't always being recreated. I can't believe that was in there so long :(

**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("EntityAliasCore");
}

/**
 * Entity Class. Holds Devices, Hardware Platforms, Browsers, Java Platforms, and whatever else we put in.
 * Pretty much the core entity of the application.
 * 
 */
Class EntityAliasCore {
	
	static $dbLink;
	protected $db;
	static $cacheLink;
	protected $cache;
	/**
	 * Autoincrementing ID
	 * @var integer
	 * @access public
	 * @acl 9
	 */
	public $ID;
	
	/**
	 * Hash
	 *
	 * @var string
	 * @access public
	 */
	public $hash;
	
	/**
	 * Status of alias: 0 = ignore, 1 = public, 2 = internal
	 *
	 * @var integer
	 */
	public $status;
	
	/**
	 * Entity id linking to the entities table
	 *
	 * @var integer
	 * @access public
	 * @acl 9
	 */
	public $entityid;
	
	/**
	 * hash linking to entities table
	 * @var string
	 * @access public
	 */	
	public $entityhash; // 
	
	/**
	 * Type of entity. Arbitrary in the sense that it can contain new values unrelated to mobile devices.
	 * Device = used for something you might buy in a shop. Used in this particular application (mobile devices) are:
	 * Hardware Platform = Some reference design or base model which has been build on by other manufacturers. Also used for Sony Ericsson devices where the same base is used for different regional variants.
	 * Developer Platform = Some programming API that allows application development: such as .NET or Series 60.
	 * Java Platform = Some set of Java libraries
	 * Browser = self explanatory
	 * Bot = Some kind of automated agent
	 * Spider = An automated agent which is built to follow page links. The difference between this and a bot is so blurry as to probably be eliminated.
	 * Emulator = something pretending to be something else
	 * OS = Operating system
	 * User Agent = special case: this type of entity exists either so (a) we know about it and it doesn't recur,
	 * and (b) so we can convert it into other kinds of object.
	 * 
	 * @var string
	 * @access public
	 */			
	public $entitytype;

	/**
	 * Category: for phones this is "manufacturer". Sometimes it echoes entitytype if "manufacturer" is less critical 
	 * @var string
	 * @access public
	 */		
	public $category;
	
	/**
	 * Description: "model" for devices, but has other values for other types of entity.
	 * @var string
	 * @access public
	 */		
	public $description;

	public $owner;
	/**
	 * General purpose timestamp.
	 * @var timestamp
	 * @access public
	 */	
	public $ts;
	

	/**
	 * This is just a helper field for search functions containing a punctuation-stripped concatenation
	 * of the category and description.
	 *
	 * @var string
	 * @access public

	 */
	public $catDescSearch;
	
	/**
	 * This is just a helper field for search functions containing a punctuation-stripped version of the 
	 * description.
	 *
	 * @var string
	 * @access public
	 */
	public $descSearch;
		
	/**
	 * The bit of the description after the "-" or " ".
	 *
	 * @var string
	 * @access internal
	 * @access public
	 */
	public $postPart;

	/**
	 * The bit of the description after the "-" or " ".
	 *
	 * @var string
	 * @access internal
	 * @access public
	 */
	public $prePart;

	/**
	 * Which subclass of the underlying entity does this map to?
	 *
	 * @var string
	 */
	public $subclass;
	
	/**
	 * Which major revision of the underlying entity does this map to?
	 *
	 * @var string
	 */
	public $majorrevision;
	
	public $descriptor;
	/**
	 * Holds any errors that might have been generated 
	 *
	 * @var string
	 * @access public;

	 */
	public $error;
	
	/**
	 * Variable to hold errors at static level
	 *
	 * @staticvar string
	 * @access public

	 */
	static $ERROR;

	/**
	 * Table to be targeted in database
	 *
	 * @var string
	 * @access public

	 */
	public $tablename="";
		
	/**
	 * Current primary key
	 *
	 * @var string
	 * @access public

	 */
	public $pk;

	/**
	 * Default tablename for this class
	 *
	 * @static string
	 * @access public

	 */
	static public $table = "entity_alias";
		
	/**
	 * Default timeout for entity objects in the cache
	 *
	 * @staticvar integer
	 * @access public

	 */
	static public $cache_timeout=600;
	
	/**
	 * List of fields in the default table. 
	 *
	 * @staticvar string
	 * @access public

	 */
	static public $fields=array("ID","hash","entityid","entityhash","entitytype","category","description","ts","descSearch","catDescSearch","postPart","prePart","subclass","majorrevision","status","descriptor","owner");
	public $fieldList;
	public $cached=false;
	/**
	 * Default primary key
	 *
	 * @staticvar string

	 * @access public
	 */
	static $PK="ID";
	/**
	 * Constructor. The idea is that if a complete record is passed in with just a data record (say for instance
	 * that we'd get entities from a fetch all and were passing them in), we'd avoid hitting the 
	 * database to create the entity.
	 *
	 * @param integer $ID
	 * @param string $hash
	 * @param string $description
	 * @param string $category
	 * @param array $data

	 * @access public
	 * @return Entity
	 */
	function __construct($ID=0,$hash="") {
		// 11.03.2008 - OK
		$this->cacheDB();

		if ($hash && strlen($hash) !== 32) $hash = "";
		$this->tablename = self::$table;
		$this->fieldList = self::$fields;
		$this->pk = self::$PK;

		if ($hash) {
			$where = array("hash" => $hash);
			
			if (DetectRight::$data_owner !== "SYSTEM") {
				$where["owner"] = array("op"=>"in","value"=>array(DetectRight::$data_owner,"SYSTEM"));
			}
		} elseif ($ID) {
			$where = array($this->pk => $ID);
		} else  {
			$this->error="No alias available";
			return;
		}

		$entityAlias =$this->db->simpleFetch($this->tablename,$this->fieldList,$where);
		if ($entityAlias === false) {
			$this->error = $this->db->sql_error();
			return;
		}
		
		if ( count($entityAlias) === 0) {
			return;
		}
		$data=array_shift($entityAlias);
		
		foreach($data as $key=>$value) {
			if (property_exists($this,$key)) {
				$this->$key=$value;
			}
		}
		
		$this->generateSearchStrings();
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
	 * Generates the unique hash for this
	 *
	 * @return string

	 * @access public
	 */
	function hash() {
		return $this->hash;
	}

	/**
	 * Return ID
	 *
	 * @return integer

	 * @access public
	 */
	function id() {
		$pk = $this->pk;
		return $this->$pk;
	}
	
	/**
	 * Set the ID
	 *
	 * @param integer $value
	 * @return boolean

	 * @access public
	 */
	function setid($value) {
		if (!is_integer($value)) return false;
		$pk = $this->pk;
		$this->$pk = $value;
		return true;
	}
	
	function toString() {
		// @todo
	}
	/**
	 * Get the appropriate timestamp for this object
	 *
	 * @return timestamp

	 * @access public
	 */
	function ts() {
		return strtotime($this->ts);
	}
	

	
	/**
	 * Gets a collection of aliases according to the chosen parameters
	 *
	 * @param integer $entityid
	 * @param array || string $status  can be comma delimited string
	 * @param boolean $system
	 * @param boolean $user
	 * @return EntityAlias[]
	 */
	static function getAliasCollection($entityid,$status="") {
		if (!DRFunctionsCore::isEmptyStr($status) && !is_array($status)) $status =  explode(",",$status);
		$where = array();
		if ($status) $where['status'] = array("op"=>"in","value"=>$status);
		$where['entityid']=$entityid;
		
		$ids = self::$dbLink->getIDs(self::$table,"ID",$where);
		$eas = array();
		foreach ($ids as $id) {
			$ea = self::getEntityAlias($id);
			$eas[$id]=$ea;
		}
		return $eas;
	}
	
	static function getEntities($where,$limit=1) {
		$ids = self::$dbLink->getIDs(self::$table,"entityid",$where,"",array("limit"=>$limit));
		if ($ids) {
			$ids = array_unique($ids);
		}
		return $ids;
	}

	/**
	 * Get an entity from its ID, with cacheing.
	 *
	 * @param integer $id
	 * @return Entity

	 * @access public
	 * @static
	 */
	static function getEntityAlias($id) {
		DetectRight::checkPoint("Asked for EntityAlias $id");
		$ea = new EntityAliasCore($id);
		if (!$ea->id()) {
				DetectRight::checkPoint("Failed to get entityalias $id");
				return false;
		}
		return $ea;
	}
	
	/**
	 * Get an filled entity
	 *
	 * @param integer $id
	 * @param string $hash
	 * @return Entity
	 * @acl 9
	 * @access public
	 * @static
	 */
	static function get($id=0,$hash="") {
		if ($id) {
			return self::getEntityAlias($id);
		} else {
			return self::getEntityAliasFromHash($hash);
		}
	}
	
	/**
	 * Gets an entity from its hashcode.
	 *
	 * @param string || array $hash
	 * @return EntityAlias
	 * @acl 1
	 * @access public
	 * @static
	 * @ClientTest 13/5/09
	 */
	static function getEntityAliasFromHash($hash) {
		if ($hash === null) return new EntityAliasCore(0);
		if ((string) $hash === $hash) {
			$ea=new EntityAliasCore(0,$hash);
			if ($ea->error) {
				self::$ERROR=$ea->error;
				return $ea;
			}
			return $ea; // nice XML or serialized representation of entity without object.
		} /*elseif (is_array($hash)) {
			foreach ($hash as $ehash) {
				$ea = self::getEntityAliasFromHash($ehash);
				$output[$ehash] = $ea;
			}
		}*/
		return null;
	}
	
	/**
	 * Returns the cache key for this entity
	 *
	 * @return string

	 * @access public
	 */
	function cacheKey() {
		return DetectRight::cacheKey("EA_".$this->id());
	}

	/**
	 * Cache this entity. If overwrite is false, then it checks first before storing.
	 *
	 * @param integer $timeout
	 * @param boolean $overwrite

	 * @access public
	 */	
	function cache($timeout=0,$overwrite=true) {
		if (!$timeout) $timeout = self::$cache_timeout;
		if (!$overwrite && $this->cached) return;
		if ($this->id()) {
			$this->cache->cache_set($this->cacheKey(),$this,$timeout);
			$this->cached = true;
		}
	}
	
	/**
	 * Creating some helper search strings to avoid different variants of the same device
	 * (separated only by punctuation or capitalisation differences) making it into the DB.
	 *

	 * @access public
	 */
	function generateSearchStrings() {
		if ($this->catDescSearch && !DetectRight::$redetect) return;
		$this->catDescSearch=DRFunctionsCore::punctClean("$this->category$this->description");
		$this->descSearch=DRFunctionsCore::punctClean($this->description);
		$this->postPart = self::postPart($this->description);
		$this->prePart = self::prePart($this->description);
	}
	
	/**
	 * The bit after the dash or space.
	 *
	 * @return string

	 * @access public
	 * @static
	 */
	static function postPart($description) {
		//$description = $this->description;
		$tmp = explode("-",$description);
		if (count($tmp)>1) {
			return array_pop($tmp);
		}
		$tmp = explode(" ",$description);
		if (count($tmp)>1) {
			return array_pop($tmp);
		}
		return "";
	}

	
	/**
	 * The bit after the dash or space.
	 *
	 * @return string

	 * @access public
	 * @static
	 */
	static function prePart($description) {
		//$description = $this->description;
		$tmp = explode("-",$description);
		if (count($tmp)>1) {
			return array_shift($tmp);
		}
		$tmp = explode(" ",$description);
		if (count($tmp)>1) {
			return array_shift($tmp);
		}
		return "";
	}
	
	function descriptor() {
		$entitytype = DetectRight::escapeDescriptor($this->entitytype);
		$category = DetectRight::escapeDescriptor($this->category);
		$description = DetectRight::escapeDescriptor($this->description);
		$descriptor = $entitytype.":".$category.":".$description;
		$subclass = DetectRight::escapeDescriptor($this->subclass);
		$majorRevision = DetectRight::escapeDescriptor($this->majorrevision);
		$descriptor .= ":$subclass:$majorRevision";
		while (substr($descriptor,-1,1) === ":") {
			$descriptor = substr($descriptor,0,-1);
		}
		return $descriptor;
	}
}