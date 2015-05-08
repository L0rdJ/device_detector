<?php
/**
 * @author Chris Abbott <chris@detectright.com>
 * @package DetectRight
 */
/******************************************************************************
Name:    entity.core.class.php
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

The summary here is intended to highlight some parts of the agreement, but is only 
an illustration and does not itself form part of the agreement itself.

The agreement does not contain any redistribution rights for the software (whether modified or unmodified), 
but it does allow modification of the source code by the end-user, with the understanding that
modified builds of the software are not supported by DetectRight without a premium support license.

For license fees for commercial use, please check the current rates and offers at 
http://www.detectright.com/device-detection-products.html

The database file accessed by this API should be downloaded solely from 
http://www.detectright.com through the user control panel (free registration). 

Your fair use and other rights are in no way affected by the above.
Changes: 
2.2.0 - changes to isMobile and uaImportance. Added extra condition for revision comparison to work out profile nearness.
2.2.1 - tweaked cache_sets to align with function definitions
2.2.1 - handled some non-initialization cases a bit better
2.3.0 - tweaked orderby in getEntityTypes and getCategories
2.3.1 - additional null checking in getEntityFromCatDesc that should never be triggered
2.7.0 - generateSearchStrings change
2.8.0 - added descriptor cache on a DRRollingCache. Removed bug for arrays passed to getEntityFromDescriptor (luckily this codepath was never called)
******************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("EntityCore");
}

/**
 * Entity Class. Holds Devices, Hardware Platforms, Browsers, Java Platforms, and whatever else we put in.
 * Pretty much the core entity of the application.
 * 
 */
Class EntityCore {
	
	static public $useCache = false;
	static public $epCache = false;
	static public $eCache = false;
	static public $eCacheEntities = array(); // cache for entity results from getEntityFromCatDesc
	static public $descriptors = null; // this is going to be a DRRollingCache Object
	 
	private $cached=false;
	/**
	 * Which entity types describe things (as opposed to merely components of things?)
	 *
	 * @static string[]
	 */
	static $nominativeEntityTypes;

	/**
	 * List of export profiles that are put into the search engine
	 *
	 * @static string[]
	 * @internal 
	 * @access public
	 */
	static $exportProfiles=array();

	static $QDTs = array();
	static $dbLink;
	static $cacheLink;
	protected $db;
	protected $cache;
	
	/**
	 * Autoincrementing ID
	 * @var integer
	 * @access public
	 * @acl 9
	 */
	public $ID;
	
	/**
	 * md5 of concat($entitytype,"/",$category,"/","description") used as unique key.
	 * @var string
	 * @access public
	 */	
	public $hash; // 
	
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

	/**
	 * General purpose timestamp.
	 * @var timestamp
	 * @access public
	 */	
	public $ts;
	
	/**
	 * Status of entity for workflow:
	 * 0 = invalid
	 * 1 = valid (for useragents, this also means "yes, I know, this isn't obviously invalid"
	 * 2 = incoming (it's been added recently)
	 * 3 = research needed (tough User agent or device which needs some googling)
	 * 4 = really difficult (it's almost certainly not worth spending the time on this category)
	 * 
	 * @var integer
	 * @access public
	 */		
	public $status;
	
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
	 * This is just a helper field for search functions containing a punctuation-stripped concatenation
	 * of the category and description.
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $catDescSearch;
	
	/**
	 * This is just a helper field for search functions containing a punctuation-stripped version of the 
	 * description.
	 *
	 * @var string
	 * @access public
	 * @internal 9
	 */
	public $descSearch;
		
	/**
	 * Describes how fallback is handled for major revisions. For instance, for some entities, we might want to 
	 * fall back through all minor versions picking up profiles ("fallback"). For some of them, we might just
	 * want to take the base profiles (no major version, or major version 1) and any exact matching 
	 * major revision profiles ("base"). Alternatively, we can use "discrete", which requires exact matching of
	 * major revisions, or both to be empty. 
	 *
	 * @var string
	 * @access public
	 */
	public $revisionfallback;
	
	/**
	 * The bit of the description after the "-" or " ".
	 *
	 * @var string
	 * @access internal
	 * @access public
	 */
	public $postPart;

	/**
	 * The bit of the description before the "-" or " ".
	 *
	 * @var string
	 * @access internal
	 * @access public
	 */
	public $prePart;

	/**
	 * Less specific year column
	 *
	 * @var integer
	 * @acl 1
	 * @access public
	 */
	public $year;

	/**
	 * Details whether this entity can co-exist with entities of the same type, or with different versions of itself.
	 * 2 = demands total exclusivity
	 * 1 = can co-exist with multiple versions of itself
	 * 0 = can exist in unlimited quantities and versions (such as plugins)
	 * @var integer
	 * @acl 1
	 * @access public
	 *
	 */
	public $exclusivity = 2;
	
	/**
	 * Temporary holder of related Entity Profiles.
	 *
	 * @var array EntityProfiles
	 * @access public
	 * @populate
	 * @acl 9
	 */
	public $profiles; 
	

	/**
	 * Does this entity contain custom data? This is filled to true when custom data is detected.
	 *
	 * @var boolean
	 */
	public $isCustom=false;
	/**
	 * Property collections are stored here temporarily to avoid recalculation. This makes sense since
	 * Entities are now stored in cache for a short time.
	 *
	 * @var array of PropertyCollection objects.
	 * @access public
	 * @internal
	 */
	public $qdt; 

	public $qdc; // temporary holding area for QDCs.
	
	public $profileChanges; // temporary holding area for arrays of Universal schema commands
	/**
	 * The username of the first chap who puts an entity here. This was going to be used in the dim and
	 * distant past to reward people for that, but actually it's just good for auditing now.
	 *
	 * @var string
	 * @access public
	 * @acl 9
	 */	
	public $owner;
	
	/**
	 * Meant to convey which fields are editable in, say, a GUI (comma separated).
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $editable="";
	
	/**
	 * Holds any errors that might have been generated 
	 *
	 * @var string
	 * @access public;
	 * @internal
	 */
	public $error;
	
	/**
	 * Variable to hold errors at static level
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static $ERROR;

	/**
	 * Table to be targeted in database
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $tablename="";
	
	/**
	 * Array of entity tables linked symbiotically to this entity
	 *
	 * @var array
	 * @access public
	 * @internal
	 */
	public $linkedTables="";
	
	/**
	 * Details about the tables linked to this
	 *
	 * @var array
	 * @access public
	 * @internal
	 */
	public $tables="";
	
	/**
	 * Current Major Revision being applied, this is essentially holding the data from a related pointer.
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $majorrevision;
	
	/**
	 * Current Minor Revision being applied, this is essentially holding the data from a related pointer.
	 *
	 * @var string
	 * @access public
	 * @internal
	 */	
	public $minorrevision;
	
	/**
	 * Current Subclass being applied, this is essentially holding the data from a related pointer.
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $subclass;

	/**
	 * Current Subclass being applied, this is essentially holding the data from a related pointer.
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $connection;
	
	/**
	 * Which build being applied?
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $build;
	
	/**
	 * Used as a temporary holder when this entity has been linked with a "contains" link.
	 *
	 * @var integer
	 * @access public
	 * @internal
	 */
	public $currentTrust;
	
	/**
	 * Holds a contains array
	 *
	 * @var array
	 * @populate
	 * @access public
	 * @acl 1
	 */
	public $contains = null;

	/**
	 * Holds an EntitySig collection
	 *
	 * @var array
	 * @populate
	 * @access public
	 * @acl 1
	 */
	public $esc;
	
	/**
	 * Organisations this entity has been seen with.
	 *
	 * @var array
	 * @access public
	 * @populate
	 * @acl 1
	 */
	public $orgs;

	/**
	 * Aliases for this
	 *
	 * @var array
	 * @populate
	 * @acl 1
	 * @access public
	 */
	public $aliases;
	
	/**
	 * Overriding alias for this
	 *
	 * @var array
	 */
	public $alias;
	
	/**
	 * External Entity Objects
	 * @acl 9
	 * @populate
	 * @var array
	 * @access public
	 */
	public $ees;
		
	/**
	 * Current primary key
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $pk;

	/**
	 * Search engine data
	 *
	 * @var array
	 * @access public
	 * @populate
	 * @acl 1
	 */
	public $canned;
	
	/**
	 * Field list
	 *
	 * @var array
	 * @access public
	 * @internal
	 */
	public $fieldList;
	
	/**
	 * Default tablename for this class
	 *
	 * @static string
	 * @access public
	 * @internal
	 */
	static public $table = "entity";
	
	/**
	 * Search table.
	 *
	 * @static string
	 * @access public
	 * @internal
	 */
	static public $staticTable = "entity_data";
	
	/**
	 * Default timeout for entity objects in the cache
	 *
	 * @staticvar integer
	 * @access public
	 * @internal
	 */
	static public $cache_timeout=600;
	
	/**
	 * List of fields in the default table. 
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static public $fields=array("ID","hash","category","description","status","entitytype","owner","revisionfallback","descSearch","catDescSearch","postPart","prePart","year","exclusivity");

	/**
	 * Certain tables are linked to the entities table, since they contain sub-data.
	 * They're listed here.
	 *
	 * @staticvar array
	 * @access public
	 * @internal
	 */
	static public $LINKED_TABLES = array(
		"contains"=>"entity_contains",
	);
				
	/**
	 * Default primary key
	 *
	 * @staticvar string
	 * @internal
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
	 * @internal
	 * @access public
	 * @return Entity
	 */
	function __construct($ID=0,$hash="") {
		$this->cacheDB();
		// 11.03.2008 - OK
		if (is_string($ID) && strlen($ID)==32) {
			$hash=$ID;
			$ID=0;
		}
		if ($hash && strlen($hash) !== 32) $hash = "";
		$this->tablename = self::$table;
		$this->fieldList = self::$fields;
		$this->linkedTables = self::$LINKED_TABLES;
		$this->pk = self::$PK;
		$this->errors=array();
		
		if ($hash) {
			$where = array("hash" => $hash);
		} elseif ($ID) {
			$where = array($this->pk => $ID);
		} else {
			$this->error="No category, description or entitytype supplied";
			return;
		}

		$entity=$this->db->simpleFetch($this->tablename,$this->fieldList,$where);
		if ($entity===false) {
			$this->error=$this->db->sql_error();
			return;
		}
		if (count($entity) === 0) {
			$this->error="Non-existent handset and no data...";
			return;
		}
		$data=array_shift($entity);

		foreach($data as $key=>$value) {
			if (property_exists($this,$key)) {
				$this->$key=$value;
			}
		}		
		$this->generateSearchStrings();
	}

	function __destruct() {
		$this->cache = null;
		$this->db = null;
		$this->qdt = null;
		
		if (!isset($this->profiles)) return;
		if ((array) $this->profiles !== $this->profiles) return;
		foreach (array_keys($this->profiles) as $key) {
			$this->profile[$key] = null;
			unset($this->profiles[$key]);
		}
		$this->profiles = null;
	}
	
	/**
	 * Returns an array of contained all entities sorted at level order.
	 *
	 * @return Entity[]
	 */
	function getContains($maxLevel = 999) {
		//  build an entity contains tree with levels and then filter out more specific contains
		// that don't apply to this particular version number;
		if (is_array($this->contains)) return $this->contains;
		$output = array();
		$entityIndex = array();
		$array = self::getContainsArray($this->ID,1);
		foreach ($array as $containsArray) {
			$contains = null;

			$entityid = $containsArray['entityid'];
			$entityDescriptor = $containsArray['entitydescriptor'];
			if (DRFunctionsCore::isEmptyStr($entityDescriptor)) {
				$entity = self::get($entityid);
				$entityDescriptor = $entity->descriptor();
			}

			if ($entityid === ($this->id()."")) {
				if (!$this->isDescriptorCompatible($entityDescriptor)) continue;
			} elseif (isset($entityIndex[$entityid])) {
				$level = $entityIndex[$entityid];
				if (!$output[$level][$entityid]->isDescriptorCompatible($entityDescriptor)) continue;
			}
			$descriptor = $containsArray['containsdescriptor'];

			if (!DRFunctionsCore::isEmptyStr($descriptor)) {
				$contains = self::getEntityFromDescriptor($descriptor,false,false);
				if (!is_object($contains)) continue;
			} else {
				$containshash = $containsArray['containshash'];
				$subclass = DRFunctionsCore::gv($containsArray,'subclass');
				$majorrevision = DRFunctionsCore::gv($containsArray,"majorrevision");
				$contains = self::get($containshash);
				if (!is_object($contains)) continue;
				$contains->subclass = $subclass;
				$contains->majorrevision = $majorrevision;
			}

			//$contains = self::getEntityFromDescriptor($descriptor,false,false);
			$contains->isCustom = ($containsArray['owner'] !== "SYSTEM");
						
			// mark entity for custom wor
			if ($contains->isCustom && !$this->isCustom) $this->isCustom=true;

			$level = $containsArray['level'];
			if (!isset($output[$level])) $output[$level] = array();

			if (!isset($output[$level][$contains->id()])) {
				$output[$level][$contains->id()]  = $contains;
				$entityIndex[$contains->id()] = $level; // used for looking up
			}
		}
		ksort($output);
		$entities = array();
		foreach ($output as $level=>$entityArray) {
			if ($level > $maxLevel) continue;
			foreach ($entityArray as $entity) {
				$entities[] = $entity;
			}
		}
		$this->contains = $entities;
		return $entities;
	}
	
	static function getContainsArray($entityid,$level = 1) {
		if ($level > 10) return array();
		DetectRight::$data_mode = "SystemUser";
		$where = DetectRight::addOwner(array("entityid"=>$entityid,"valid"=>1,"contains"=>array("op"=>"<>","value"=>$entityid)));
		$ids = self::$dbLink->simpleFetch(self::$LINKED_TABLES["contains"],array("*"),$where,array("trust"=>"DESC","containsdescriptor"=>"DESC","majorrevision"=>"DESC"));
		if (!$ids) return array();
		$output = array();
		foreach ($ids as $array) {
			$array['level'] = $level;
			$entityid = $array['contains'];
			$output[] = $array;
			$containIDs = self::getContainsArray($entityid,$level+1);
			foreach ($containIDs as $array) {
				$output[] = $array;
			}
		}
		return $output;
	}
	
	function descriptor() {
		$entitytype = DetectRight::escapeDescriptor($this->entitytype);
		$category = DetectRight::escapeDescriptor($this->category);
		$description = DetectRight::escapeDescriptor($this->description);
		$descriptor = $entitytype.":".$category.":".$description;
		$subclass = DetectRight::escapeDescriptor($this->subclass);
		$majorRevision = DetectRight::escapeDescriptor($this->majorrevision);
		$minorRevision = DetectRight::escapeDescriptor($this->minorrevision);
		$connection = DetectRight::escapeDescriptor($this->connection);
		$build = DetectRight::escapeDescriptor($this->build);
		$descriptor .= ":$subclass:$majorRevision:$minorRevision:$connection:$build";
		while (substr($descriptor,-1,1) === ":") {
			$descriptor = substr($descriptor,0,-1);
		}
		return $descriptor;
	}
	
			
	/**
	 * Generates the unique hash for this
	 *
	 * @return string
	 * @internal
	 * @access public
	 */
	function hash() {
		return $this->hash;
	}

	function checkHash() {
		$testHash = self::makeHash($this->entitytype,$this->category,$this->description);
		if ($testHash !== $this->hash) {
			$this->fixHash();
		}
	}
	
	function fixHash() {
		// chase around all the tables fixing hashes
	}
	/**
	 * Return ID
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
	 * Set the ID
	 *
	 * @param integer $value
	 * @return boolean
	 * @internal
	 * @access public
	 */
	function setid($value) {
		if (!is_integer($value)) return false;
		$pk = $this->pk;
		$this->$pk = $value;
		return true;
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
	
	/**
	 * Create a new string representation of this
	 *
	 * @return string
	 * @internal
	 * @access public
	 */
	function toString() {
		$return=array();
		$majorRevision=$this->majorrevision;
		$minorRevision=$this->minorrevision;
		$subclass = $this->subclass;
		$connection = $this->connection;
		$build = $this->build;
		
		if ($this->entitytype=="Developer Platform" && $this->category == "Nokia" && DRFunctionsCore::like($this->description,"Series*")) {
			$dp = substr($majorRevision,0,1);
			$fp = "fp".substr($majorRevision,2,1);
			if (strtolower(substr($majorRevision,-2)) == "le") {
				$fp = "le";
			}
			//s60_2ed_fp1
			if ($dp) {
				$majorRevision="$dp"."ed_".$fp;
				$minorRevision="";
			} 
		}
		$return[]=$this->entitytype;
		$return[]=$this->category;
		$return[]=$this->description;
		$return[]=$subclass;
		$return[]=$majorRevision;
		$return[]=$minorRevision;
		$return[]=$connection;
		$return[]=$build;
		$return=implode(":",$return);
		return $return;
	}
	
	
	/**
	 * Gets an entity object with current_majorrevision, current subclass, etc, set
	 * e.g. Device:Nokia:3510i:ONE:2.0:4.5.6.7
	 * 
	 * @param string $description
	 * @return Entity || false
	 * @static 
	 * @access public
	 * @acl 1
	 */
	static function getEntityFromDescriptor($descriptor,$allowAdd = true,$fuzzy=true) {
		if (self::$dbLink === null) {
			self::reconnect();
		}
		
		$sHash = $descriptor."/".($allowAdd ? "a" : "na").($fuzzy ? "f" : "nf");
		$e = self::$descriptors->get($sHash);
		if ($e != null) return $e;
		
		$parse = self::parseEntityDescriptor($descriptor);

		$et = DRFunctionsCore::gv($parse,'entitytype');
		$cat = DRFunctionsCore::gv($parse,'category');
		$desc = DRFunctionsCore::gv($parse,'description');
		$majorrevision = DRFunctionsCore::gv($parse,'majorrevision',"");
		$minorrevision = DRFunctionsCore::gv($parse,"minorrevision","");
		
		$majorrevision = self::$dbLink->arrayFilter("invalidrev",$majorrevision);
		$majorrevision = trim($majorrevision);
		// This logic might not be as necessary or clever as it looked.
		//if (strlen($majorrevision) == 0) $minorrevision = "";
		// cleaning for dashes
		if (substr($desc,0,1) == "-" || substr($desc,0,1) == "_") $desc = substr($desc,1);

		if (strpos($desc,")") !== false && strpos($desc,"(") === false) {
			$test = explode(")",$desc);
			$test = $test[0];
			if (!$test)  return null;
			$desc = trim($test);
		}

		$desc = str_replace("_"," ",$desc);
		$desc = trim($desc);
		DetectRight::checkPoint("Getting entity $cat $desc");
		$entity = self::getEntityFromCatDesc($cat,$desc,$et,$fuzzy);
		if ($entity === null) {
			DetectRight::checkPoint("Failed to get entity");
		} else {
			DetectRight::checkPoint("Got entity $cat $desc...");
		}
		if ($allowAdd && !is_object($entity)) {
			$hash = self::makeHash($et,$cat,$desc);
			$entity = self::addEntity($et,$cat,$desc,$hash);
		} elseif (!is_object($entity)) {
			DetectRight::$error = "Entity doesn't exist";
			return null;
		}

		if (is_object($entity)) {
			$entity->majorrevision = $majorrevision;
			$entity->subclass = DRFunctionsCore::gv($parse,"subclass","");
			$entity->minorrevision = $minorrevision;
			$entity->build = DRFunctionsCore::gv($parse,"build");
			$entity->connection = DRFunctionsCore::gv($parse,"connection");
		}
		
		if ($entity != null)
		{
			self::$descriptors->set($sHash,$entity);
		}
		
		return $entity;
	}	

	static function uaImportance($ua,$entities = array()) {
		$importance = 0.00;
		// easy stuff first. If this is a status
		//if ($this->status == 0) return $importance;

		$totalPossibleScore = 0.00;

		$os = null;
		$dp = null;
		$browser = null;
		foreach ($entities as $entity) {
			switch ($entity->entitytype) {
				case 'OS':
					$os = $entity;
					break;
				case 'Browser':
				case 'Mobile Browser':
					$browser = $entity;
					break;
				case 'Developer Platform':
					$dp = $entity;

			}
		}

		/*if ($newua !== $ua) {
		// this usergent should be another one.
		$newEntity = self::getEntityFromCatDesc("UserAgent",$newua,"UserAgent","","",false,true,$this->status,false);
		// now switcheroo and delete this
		$serverVars = array("HTTP_USER_AGENT"=>$newua);
		$pointers = DDR::deducePointers($serverVars);
		Pointer::deletePointersForEntity($this->id());
		Header::moveEntity($this,$newEntity);
		Customer::moveEntity($this,$newEntity);
		$this->delete();
		echo "Deleted $ua";
		return false;
		}*/

		//$markers = array("fly-","kddi","softbank","foma","docomo","arm","maemo","maemo","htil","spice","mtk","mobile","ipad","iphone","ipod","iprod","armv5EJl","wap2.0","cldc","symbian","obigo","net front","netfront","blackberry","480x640","480x800","176x220","series60","up.browser","j2me","midp","android","vodafone","t-mobile","sprint","verizon","vzw","qtv","build","mobile safari","opera mini","240x320","nucleus","rtos");
		$markers = self::$dbLink->getArray("mobile_markers");
		$totalPossibleScore = $totalPossibleScore + count($markers)*10.00;
		foreach ($markers as $marker) {
			if (stripos($ua,$marker) !== false) {
				$importance = $importance +10;
			}
		}

		$markers = self::$dbLink->getArray("non_mobile_markers");//array("Macintosh; Intel Mac OS X","curl/","perl/","Microsoft Data Access ","Mediapartners-Google","links","FreeBSD i386","WebDAV","larbin","Klondike","WordPress","agent","crawler","nutch","bot/","bot ","spider","Googlebot","SunOS","Google Desktop","NaviWoo","Mac_PowerQDC","Debian","os=Windows","cpu=IA32","cpu=PQDC","OSSProxy","PHP/","Java/","purl/","CURL","Linux i686","Linux x86_64","Windows Vista","Windows XP","Intel Mac OS X","Maxthon","iCab/","Macintosh","Konqueror","beos","avant","net clr","ppc mac os x","sky broadband","simbar","funweb","zango","media center","megaupload","alexa","zencast","x11","ubuntu","msn","trident","SLCC1","autoupdate","officeliveconnector","officelivepatch","naviwoo","wow64","Minefield");
		foreach ($markers as $marker) {
			if (stripos($ua,$marker) !== false) {
				$importance = $importance - 10;
			}
		}

		
		$os_to_ignore = self::$dbLink->getArray("ignore_os");
		//$os_to_ignore = array("Windows","Windows NT","Mac","Linux");
		$totalPossibleScore = $totalPossibleScore + 15.00;
		if ($os) {
			if (!in_array($os->description,$os_to_ignore)) {
				$importance = $importance + 15.00;
			} else {
				$importance = $importance - 15.00;
			}
		}

		$totalPossibleScore = $totalPossibleScore + 30.00;
		if ($dp) {
			if ($dp->description !== ".NET") {
				$importance = $importance + 30.00;
			} else {
				$importance = $importance - 10.00;
			}
		}

		$browsers_to_ignore = self::$dbLink->getArray("ignore_browsers");
		//$browsers_to_ignore=array("Minefield","MS Internet Explorer","Chrome","Firefox","Opera","Safari","Mozilla","Konqueror","Camino","SeaMonkey","BonEcho","Gecko","Harmony");
		$totalPossibleScore = $totalPossibleScore + 20.00;
		if ($browser) {
			if ($browser->entitytype === "Mobile Browser") {
				$totalPossibleScore = $totalPossibleScore + 30;
				$importance = $importance + 50;
			} else if ($browser->entitytype === "Browser") {
				$importance = $importance - 20.00;
			} 
		}

		// it's all about the pointers
		return $importance/$totalPossibleScore*100;
	}


	/**
	 * Return an array
	 *
	 * @internal
	 * @access public
	 */
	function toArray() {
		return DRFunctionsCore::objectToArray($this);
	}
		
	/**
	 * Creating some helper search strings to avoid different variants of the same device
	 * (separated only by punctuation or capitalisation differences) making it into the DB.
	 *
	 * @internal
	 * @access public
	 */
	function generateSearchStrings() {
		if (!empty($this->catDescSearch) && !empty($this->descSearch)) return;
		$this->catDescSearch=DRFunctionsCore::punctClean("$this->category$this->description");
		$this->descSearch=DRFunctionsCore::punctClean($this->description);
		$this->postPart = self::postPart($this->description);
		$this->prePart = self::prePart($this->description);
	}
	
	/**
	 * Commit to the database.
	 *
	 * @return boolean
	 * @internal
	 */
	function commit() {
			// 11.03.2008 - OK
			if (!DetectRight::canLearn()) {
				// if this is a non-member, then stop it learning.
				$this->setid(0);
				return true;
			}
			$this->generateSearchStrings();
			$this->setid($this->db->commitObject($this));
			if (!$this->id()) {
				$this->error=$this->db->sql_error();
			}		

			if (!$this->error) $this->db->recordInsert($this->tablename,$this->id());
			return true;
	}
	
	/**
	 * The bit after the dash or space.
	 *
	 * @return string
	 * @internal
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
	 * @internal
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

	/**
	 * Human readable description of this object
	 *
	 * @internal
	 * @access public
	 * @return string
	 */
	function description() {
		return $this->entitytype.":".$this->category.":".$this->description." (status: $this->status)";
	}
	
	function isDescriptorCompatible($descriptor,$strict = false) {
		// checks a descriptor to see if it's compatible with this entity
		if (is_string($descriptor)) {
			$ed = self::parseEntityDescriptor($descriptor);
		} else {
			$ed = $descriptor;
		}
		if ($ed['entitytype'] !== $this->entitytype) return false;
		if ($ed['category'] !== $this->category) return false;
		if ($ed['description'] !== $this->description) return false;
		$subclass = $ed['subclass'];
		$majorrevision = $ed['majorrevision'];
		$build = $ed['build'];
		$minorrevision = $ed['minorrevision'];

		if ($strict) {
			if (!DRFunctionsCore::isEmptyStr($this->minorrevision) && $minorrevision > $this->minorrevision) return false;
		}

		// logic here: if subclass is empty when passed in, we don't want any subclasses in the end data.
		if (DRFunctionsCore::isEmptyStr($this->subclass)) {
			if (!DRFunctionsCore::isEmptyStr($subclass)) {
				return false;
			}
		} else {
			// this means that we only want profiles with the same subclass or no subclass.
			if ((!DRFunctionsCore::isEmptyStr($subclass) && $this->subclass !== $subclass) || (DRFunctionsCore::isEmpty($subclass) && $majorrevision !== "" && $majorrevision !== "1.0" && $majorrevision !== "100")) {
				return false;
			}
		}

		// logic here: if build is empty when passed in, we don't want any buildes in the end data.
		if (DRFunctionsCore::isEmptyStr($this->build)) {
			if (!DRFunctionsCore::isEmptyStr($build)) {
				return false;
			}
		} else {
			// this means that we only want profiles with the same build or no build.
			if (!DRFunctionsCore::isEmptyStr($build) && $this->build !== $build) {
				return false;
			}
		}			

		// here's where we need to do the whole majorrevision thing with fallback rules
		$include=false;
		$fallback = $this->revisionfallback;
		$tmr = $this->majorrevision;
		$pmr = $majorrevision;
		// if these two are equal then we go now.
		if (($isEntityEmpty=($tmr === null || $tmr === "")) & ($isProfileEmpty = ($pmr === null || $pmr === ""))) return true; // this will always be the case
		if ($tmr === $pmr) return true; // this is true in all paths
		// align first decimal point by padding out string.
		// we need to work out how to pad all decimal points by padding from the left of the decimal point
		// we may need an explode here.
		$tpos = strpos($tmr,".");
		$ppos = strpos($pmr,".");
		if ($tpos !== false && $ppos !== false) {
			DRFunctionsCore::dpAlign($tmr,$pmr);
		}
		// pad out both revisions
		switch ($fallback) {
			case 'discrete':
				// with "discrete", major revision version numbers must match, or both must be empty.
				break;
			case 'fallback':
				if ($isProfileEmpty) {
					// this fits whereever, since the profile is "general".
					$include=true;
					break;
				} 
				
				// partial match
				if (!$include && strpos($tmr,$pmr) === 0) {
					$include=true;
					break;
				}
				// following line kicks out numeric revisions if the one we're looking for is empty.
				$isProfileNumeric = DRFunctionsCore::isNumeric($pmr);
				$isEntityNumeric = DRFunctionsCore::isNumeric($tmr);
				$pmrDbl = null;
				$tmrDbl = null;
				if ($isProfileNumeric) {
					$tmp = explode(".",$pmr);
					$tmpPMR = array_shift($tmp);
					if ($tmp) {
						$tmpPMR = $tmpPMR.".".implode("",$tmp);
					}
					$pmrDbl = doubleval($tmpPMR);
				}
				if ($isEntityNumeric) {
					$tmp = explode(".",$tmr);
					$tmpTMR = array_shift($tmp);
					if ($tmp) {
						$tmpTMR = $tmpTMR.".".implode("",$tmp);
					}

					$tmrDbl = doubleval($tmpTMR);
				}
				if ($isEntityEmpty && $isProfileNumeric) {
					$include=false;
					break;
				}
				if ($pmrDbl !== null && $tmrDbl !== null && $pmrDbl <= $tmrDbl) {
					$include=true;
					break;
				}
				
				if (stripos($pmr,$tmr.".") === 0) {
					$include=true;
					break;
				}

				if ($tmr == $pmr) $include=true;
				break;
			case 'base':
			case '':
				// either a match or profile revision is empty
				if ($isProfileEmpty) {
					$include=true;
					break;
				} elseif ($isEntityEmpty) {
					$include=false;
				} elseif (strpos($tmr,$pmr) === 0) {
					$include=true;
					break;
				}

				// it was tempting to put this in.
				/*if (strpos($pmr,$tmr) === 0) {
					$include=true;
					break;
				}*/

				// sorting out decimal points again so we can do a < effectively.
				
				/*if (is_numeric($this->majorrevision)) {
					$mr = (string) round($this->majorrevision,1);
					if (!isset($mr[1])) $mr = $mr.".0";
				} else {
					$mr = $this->majorrevision;
				}
				if (is_numeric($majorrevision)) {
					$pr = (string) round($majorrevision,1);
					if (!isset($pr[1])) $pr = $pr.".0";
				} else {
					$pr = $majorrevision;
				}
				if ($mr === $pr) $include=true; */
				break;
		}

		return $include;
	}	
	
	function isMobile($contains=array()) {
		if ($contains === null) $contains = array();
		$ua = $this->description;
		
		// anything in this list below must be submitted for deeper UA analysis
		if ($this->entitytype !== "UserAgent" && $this->status && $this->category !== "Generic") {
			$headers = DetectRight::getLastHeaders();
			if ($headers) {
				$ua = $headers->ua;
			}

			$do = true;
			if ($ua && $headers) {
				$md = self::$dbLink->getArray("MobileDisqualifiers");
				if ($md) {
					foreach ($md as $token) {
						if (strpos($ua,$token) !== false) {
							$do = false;
							break;
						}
					}
				}
			}
			
			if ($do) {
				if (stripos($this->entitytype,"Mobile") !== false) return true;
				if (stripos($this->entitytype,"Handheld") !== false) return true;
				$mobile = self::$dbLink->getArray("MobileEntityTypes");
				if (!$mobile) {
					$mobile = array("Device","Tablet","e-Reader","Reader","PDA","Hardware Platform","Wristwatch");
				}
				if (in_array($this->entitytype,$mobile)) return true;
				$nonMobile = self::$dbLink->getArray("NonMobileEntityTypes");
				if (!$nonMobile) {
					$nonMobile = array("BDP","Smart TV","SmartTV","Desktop","Netbook","STB","Set-Top Box","Games Console","Laptop","Bot","Emulator","RSS","Application","ProgLang","Programming Language");
				}
				if (in_array($this->entitytype,$nonMobile)) return false;
			}
			if (!$ua) return false;
		}

		if ($contains === null) $contains = array();
		$esc = DetectRight::getLastDetection();
		if ($esc !== null) {
			if ($esc->entities !== null) {
				$contains = $esc->entities;
			}
		}
		if (!$contains) {
			$detection = DetectorCore::detect($ua,"HTTP_USER_AGENT");
			$contains = $detection->entities;
		}

		$uaImportance = self::uaImportance($ua,$contains);
		return ($uaImportance > 0);
	}
	
	/**
	 * Get contains objects from the contains table that are linked to this entity
	 *
	 * Return assoc array is keyed by entity type, then has an array of entities keyed by their IDs.
	 * @param string $subclass
	 * @return array
	 * @internal
	 * @access public
	 * is this deprecated already?
	 */
	function getESC() {
		// should this return an ESC?
		// this used to be getContains, and is now getESC.
		//@todo
		if (DRFunctionsCore::isEmptyStr($this->id())) return null;
		
		if (isset($this->esc)  && is_object($this->esc)) {
			return $this->esc;
		}
		
		$esc = new EntitySigCollection($this->descriptor());
		if ($this->entitytype == "UserAgent") {
			$this->esc = $esc;
			return $esc;
		}

		DetectRight::$data_mode = "SystemUser";
		$where = DetectRight::addOwner(array("entityid"=>$this->id(),"valid"=>1));
		$result = $this->db->simpleFetch(self::$LINKED_TABLES["contains"],array("ID","entitydescriptor","containsdescriptor","owner","trust"),$where,array("trust"=>"DESC","containsdescriptor"=>"DESC"));
		foreach ($result as $row) {
			$ed = $row['entitydescriptor'];
			$cd = $row['containsdescriptor'];
			$entityDescriptor = self::parseEntityDescriptor($ed);
			// check to see if this contains applies to this entity
			if (!$this->isDescriptorCompatible($entityDescriptor)) continue;
			
			$contains = self::getEntityFromDescriptor($cd,false,false);
			$contains->isCustom = ($row['owner'] !== "SYSTEM");
			$esc->addEntity($contains);
			
			// mark entity for custom work
			$isCustom = ($row['owner'] !== "SYSTEM");
			if ($isCustom && !$this->isCustom) $this->isCustom=true;			
		}
		
		$this->esc =$esc;
		$this->cache();
		return $esc;
	}
			
	/**
	 * Get AKAs which might be interesting to a human, rather than just be mis-spellings.
	 *
	 * @return array
	 * @internal
	 * @access public
	 */
	function getAKAs($system=true,$user=true) {
		$akas=array();
		$status=array(1,2);
		$eas = EntityAliasCore::getAliasCollection($this->id(),$status,$system,$user);

		foreach ($eas as $ea) {
			$description = "$ea->category $ea->description";
			$akas[$ea->ID]=$description;
		}
		return $akas;	
	}
	
	function setVersion($majorRevision,$minorRevision,$subclass,$connection,$build) {
		$this->majorrevision = $majorRevision;
		$this->minorrevision = $minorRevision;
		$this->subclass = $subclass;
		$this->connection = $connection;
		$this->build = $build;
	}
		
	/**
	 * Get the EntityProfile objects for this, and then cache the entity.
	 *
	 * @return associative_array 
	 * @internal
	 * @access public
	 */
	function fillProfiles() {
		DetectRight::checkPoint("Filling properties for $this->category $this->description");
		if (is_array($this->profiles)) return false;
		$this->profiles=array();

		$this->profiles = EntityProfileCore::getEPsForEntity($this->id());

		DetectRight::checkPoint("Filled properties for $this->category $this->description");
		$this->cache();
		return $this->profiles;
	}
	

	function isNominative() {
		$entitytype = $this->entitytype;
		return in_array($entitytype,self::$nominativeEntityTypes);
	}
	
	/**
	 * Adds a property collection straight into the entity and through to an EntityProfile
	 *
	 * @param string $source
	 * @param QuantumDataTree $propertyCollectionTree
	 * @param string $majorrevision
	 * @param string $minorrevision
	 * @param string $owner
	 * @param string $identifier
	 * @param string $subclass
	 * @param string $connection
	 * @return integer
	 * @internal
	 * @access public
	 */
	function addProfileCollection($source,$propertyCollection,$majorrevision,$minorrevision,$owner,$identifier,$subclass="",$connection="",$build="") {
		$ep = EntityProfileCore::getEP($source,$this->id(),$this->hash(),$majorrevision,$minorrevision,$owner,$identifier,$subclass,$connection,$build);
		//echo "Adding Property Collection";
		if (is_object($propertyCollection)) {
			$ep->addPropertyCollection($propertyCollection);
		}
		return $ep->id();
	}
	
	/**
	 * Possible the most important function in the class, this puts all the data together from the 
	 * various entity profiles.
	 *
	 * @param string $schema
	 * @param array $identifiers
	 * @param string $majorRevision
	 * @param string $minorRevision
	 * @param boolean $includeSystemData
	 * @param boolean $includeCustomData
	 * @param boolean $includeAllData
	 * @param boolean $addContainsData
	 * @param string $subclass
	 * @param string $connection
	 * @param integer $importanceOffset
	 * @acl 9
	 * @return QuantumDataTree
	 * @internal
	 * @access public
	 */
	function getQDT() {
		$majorrevision = trim($this->majorrevision);
		$minorrevision = trim($this->minorrevision);
		$subclass = trim($this->subclass);
		$connection = trim($this->connection);
				
		$creator=strtolower($this->entitytype."/".$this->category."/".$this->description);
		$qdt = new QuantumDataTree($this->descriptor(),null);
		$qdt->brand($creator);
		
		$wc=array();
		
		if (DetectRight::$data_owner !== "SYSTEM") {
			$wc["owner"]=array("op"=>"in","value"=>array(DetectRight::$data_owner,"SYSTEM"));
		} else {
			$wc["owner"] ="SYSTEM";
		}
		
		$cacheWorthy = false;
		if (self::$epCache && !DetectRight::$redetect) {
			switch ($this->entitytype) {
				case 'OS':
				case 'Developer Platform':
				case 'Browser':
				case 'Mobile Browser':
				case 'Device':
				case 'Tablet':
					$cacheWorthy = true;
					break;
			}
			if ($cacheWorthy) {
				$epCacheKey = $this->descriptor()."_".DetectRight::$data_owner."_".$this->db->currentDB;
				$cacheQDT = $this->cache->cache_get($epCacheKey);
				if ($cacheQDT) {
					$this->qdt = &$cacheQDT;
					return;
				} 
			}
		}
		
		$profiles=array();
		$table = EntityProfileCore::$table;
		
		$wc["entityid"]=$this->id();
		$result = $this->db->simpleFetch($table,array("*"),$wc);

		// if there's only one subclass in the external data for this device, then 
		// we need to consider the relative frequencies, use the most common one, and
		// leave the rest. However, we also need to consider if no subclasses are appropriate.
		// if there's at least one filled UAProfile for the base case, then we should probably keep that one.
		/*if (!DRFunctionsCore::isEmptyStr($majorrevision)) {
			foreach ($result as $row) {
				$rowrev = $row['majorrevision'];
				if (!DRFunctionsCore::isEmptyStr($rowrev)) {
					if ($rowrev < $majorrevision || DRFunctionsCore::isEmptyStr($majorrevision)) $majorrevision = $rowrev;
				}
			}
		}*//*commented out as a result of tests in the Java */
		if (!$result) $result = array();
		foreach ($result as $row) {
			$ed = array();
			$ed['majorrevision']=$row['majorrevision'];
			$ed['subclass'] = $row['subclass'];
			$ed['connection'] = $row['connection'];
			$ed['build'] = $row['build'];
			$ed['minorrevision'] = $row['minorrevision'];
			$ed['entitytype'] = $this->entitytype;
			$ed['category'] = $this->category;
			$ed['description'] = $this->description;
			if ($this->isDescriptorCompatible($ed))	{
				$profiles[]=$row;
			}
		}
				
		// OK, nearness is now a 10 point scale. 5 means "average".
		DetectRight::checkPoint("Doing Entity Profiles");
		foreach ($profiles as $row) {
			DetectRight::checkPoint("New Entity Profile ".$row['ID'],4);
			$profile = EntityProfileCore::getEPFromRow($row);
			if ($profile->ID === "257") {
				$dummy=true;
			}
			DetectRight::checkPoint("Done New Entity Profile ".$row['ID'],4);
			$nearness=0;
			// work out nearness of fit
			if ($majorrevision == $profile->majorrevision && $minorrevision == $profile->minorrevision) {
				// exact version match.
				if ($majorrevision !== "") {
					$nearness=8;
				} else {
					$nearness=5;
				}
			} elseif (($majorrevision && $majorrevision == $profile->majorrevision ) || ($minorrevision && ($minorrevision == $profile->minorrevision))) {
				// either version match.
				$nearness=7;
			} elseif (($majorrevision == 1 || $majorrevision == 100) && DRFunctionsCore::isEmptyStr($profile->majorrevision)) {
				$nearness=5;
			} elseif (!$majorrevision && !$minorrevision && !$profile->majorrevision && !$profile->majorrevision && !$profile->minorrevision) {
				// match for profiles without versions.
				$nearness=5; // leave unadjusted
			} elseif ($majorrevision && $profile->majorrevision && stripos($majorrevision,$profile->majorrevision) === 0) {
				$nearness = 7;
			} elseif ($majorrevision && $profile->majorrevision) {
				// compare versions
				try {
					$versionDifference = DRFunctionsCore::parseDoubleLoose($majorrevision) - DRFunctionsCore::parseDoubleLoose($profile->majorrevision);
					$nearness = (int)(7 - floor($versionDifference)); 
				} catch (Exception $e) {
					$nearness = 3;
				}
			} else {
				$nearness = 3;
			}
						
			if ($nearness > 0) {
				// adjust nearness and process row.
				if (!DRFunctionsCore::isEmptyStr($subclass) && $subclass == $profile->subclass) {
					// second if clause above shouldn't be necessary, but is put in just in case.
					$nearness = $nearness + 5;
				}

				// now for connection
				if ($connection && !DRFunctionsCore::isEmptyStr($profile->connection)) {
					if ($profile->connection == $connection) {
						$nearness++;
					} else {
						$nearness--;
					}
				}

				// NOTE: this seems to render entity profile source pretty much irrelevant.
				DetectRight::checkPoint("Adding QDT for EP $profile->ID",4);
				
				$isCustom = ($row['owner'] !== "SYSTEM");
				if ($isCustom && !$this->isCustom) $this->custom=true;
				$tmpQDT = $profile->getQDT();
				$tmpQDT->addImportance($nearness - 5);
				if ($tmpQDT->pkg) {
					DetectRight::checkPoint("Adding...",4);
					$qdt->addQDT($tmpQDT);
				} else {
					DetectRight::checkPoint("Subsuming...",4);
					$qdt->subsume($tmpQDT);
				}
				DetectRight::checkPoint("Added Property Collection",4);
			}
		}
		DetectRight::checkPoint("Done Entity Profiles");
		// add in the parameters which created this profile collection.
		$qdt->setDescriptor($this->descriptor());
		$qdt->entityid = $this->ID;
		//$qdt->metadata['identifiers']=$identifiers;
		DetectRight::checkPoint("Cleaning QDT",4);
		//$this->cleanQDT($pc,$addContainsData,$importanceOffset);
		//DetectRight::checkPoint("Cleaned QDC",4);
				
		// now we add one extra package
		$this->qdt = &$qdt;
		$this->postProcessQDT();
		DetectRight::checkPoint("PostProcessed QDC",4);
		if ($cacheWorthy && self::$epCache) {
			$success = $this->cache->cache_set($epCacheKey,$qdt,self::$cache_timeout);
		}
	}
	
	function postProcessQDT() {
		// here's where we add our descriptor as a path: an additional descriptive node, if you will.
		$descriptor = $this->descriptor();
		$data = "System//".$descriptor."//status=1";
		$this->qdt->addPackage(array($data),"99",$this->descriptor());
	}
	/**
	 * Returns the cache key for this entity
	 *
	 * @return string
	 * @internal
	 * @access public
	 */
	function cacheKey() {
		return self::getCacheKey($this->id());
	}

	/**
	 * Cache this entity. If overwrite is false, then it checks first before storing.
	 *
	 * @param integer $timeout
	 * @param boolean $overwrite
	 * @internal
	 * @access public
	 */	
	function cache($timeout=0,$overwrite=true) {
		if (!$timeout) $timeout = self::$cache_timeout;

		if (is_null($this->ID) || $this->ID < 1) return;
		if ($this->cached && !$overwrite) return;
		$currentOverrides = array();
		$restoreOverrides = false;
		if (isset($this->overrides) && !empty($this->overrides)) {
			$currentOverrides = $this->overrides;
			$this->overrides = null;
			$restoreOverrides = true;
			
		}
		$this->cache->cache_set($this->cacheKey(),$this,$timeout);

		if ($restoreOverrides) {
			$this->overrides = $currentOverrides;
		}
		$this->cached=true;
	}


	/****************************************************************************************************/
	/*                                      Static functions                                            */
	/****************************************************************************************************/

	/**
	 * Works out the hash (unique key) for this combination of entitytype, category and description.
	 *
	 * @param string $entitytype
	 * @param string $category
	 * @param string $description
	 * @return string
	 * @acl 1
	 * @access public
	 * @static
	 * @ClientTest 13/5/09
	 */
	static function makeHash($entitytype,$category,$description) {
		$hash=md5(strtolower($entitytype."/".$category."/".$description));
		return $hash;
	}
	
	/**
	 * Parses an entity representation of the kind: Device:Nokia:3200:Verizon::1.0:1.45:3G
	 *
	 * @param string $string
	 * @return associative_array
	 * @access public
	 * @static
	 * @acl 0
	 * @ClientTest 14/5/09
	 */
	static function parseEntityDescriptor($string) {
		$return=array();
		$tmp=explode(":",$string);
		$tmpPad=array_pad($tmp,8,"");
		$return['entitytype']=DetectRight::unescapeDescriptor($tmpPad[0]);
		$return['category']=DetectRight::unescapeDescriptor($tmpPad[1]);
		if ($return['entitytype'] !== "UserAgent") {
			$return['description']=DetectRight::unescapeDescriptor($tmpPad[2]);
			$return['subclass']=DetectRight::unescapeDescriptor($tmpPad[3]);
			$return['majorrevision']=DetectRight::unescapeDescriptor($tmpPad[4]);
			$return['minorrevision']=DetectRight::unescapeDescriptor($tmpPad[5]);
			$return['connection']=DetectRight::unescapeDescriptor($tmpPad[6]);
			$return['build'] = DetectRight::unescapeDescriptor($tmpPad[7]);
		} else {
			unset($tmp[0]);
			unset($tmp[1]);
			$return['description'] = DetectRight::unescapeDescriptor(implode(":",$tmp));
			$return['subclass']="";
			$return['majorrevision']="";
			$return['minorrevision']="";
			$return['connection']="";
		}
		
		$etMap = array("D"=>"Device","HP"=>"Hardware Platform","DP"=>"Developer Platform");
		if (isset($etMap[$return['entitytype']])) $return['entitytype']=$etMap[$return['entitytype']];
		return $return;
	}
	/**
	 * Parses an entity representation of the kind: Device:Nokia:3200:Verizon:1.0:1.45:3G
	 * It makes the assumption that subclass (here "Verizon") shows up when the string gets
	 * above a certain number of clauses. This proved to be a mistake :)
	 *
	 * @param string $string
	 * @return associative_array
	 * @access public
	 * @static
	 * @acl 0
	 * @ClientTest 14/5/09
	 */
	static function parseEntityDescriptor_heuristic($string) {
		$tmp=explode(":",$string);
		
		switch (count($tmp)) {
			case 1:
				// uh? This is no use.
				return false;
				break;
			case 2:
				$return['category']=$tmp[0];
				$return['description']=$tmp[1];
				break;
			case 3:
				$return['entitytype']=$tmp[0];
				$return['category']=$tmp[1];
				$return['description']=$tmp[2];
				break;
			case 4:
				$return['entitytype']=$tmp[0];
				$return['category']=$tmp[1];
				$return['description']=$tmp[2];
				$return['majorrevision']=$tmp[3];
				break;
			case 5:
				$return['entitytype']=$tmp[0];
				$return['category']=$tmp[1];
				$return['description']=$tmp[2];
				$return['majorrevision']=$tmp[3];
				$return['minorrevision']=$tmp[4];
				break;
			case 6:
				$return['entitytype']=$tmp[0];
				$return['category']=$tmp[1];
				$return['description']=$tmp[2];
				$return['subclass']=$tmp[3];
				$return['majorrevision']=$tmp[4];
				$return['minorrevision']=$tmp[5];
				break;
			case 7:
				$return['entitytype']=$tmp[0];
				$return['category']=$tmp[1];
				$return['description']=$tmp[2];
				$return['subclass']=$tmp[3];
				$return['majorrevision']=$tmp[4];
				$return['minorrevision']=$tmp[5];
				$return['connection']=$tmp[6];
				break;
			case 8:
				$return['entitytype']=$tmp[0];
				$return['category']=$tmp[1];
				$return['description']=$tmp[2];
				$return['subclass']=$tmp[3];
				$return['majorrevision']=$tmp[4];
				$return['minorrevision']=$tmp[5];
				$return['connection']=$tmp[6];
				$return['build']=$tmp[7];
				break;

			default:
				
		}
		return $return;
	}
		
	/**
	 * Get a cache key - static version
	 *
	 * @param integer $id
	 * @return string
	 * @internal
	 * @static
	 * @access public
	 */
	static function getCacheKey($id) {
		return DetectRight::cacheKey("drEntity_".DetectRight::$data_owner."_$id");
	}
	/**
	 * Gets a list of entity types from the entity table.
	 * @acl 1
	 * @return array
	 * @access public
	 * @static
	 * @ClientTest 14/5/09
	 */
	static function getEntityTypes() {
		return self::$dbLink->getIDs(self::$table,"entitytype","",array("entitytype"=>"ASC"),"","select distinct");
	}
	
	/**
	 * Get a list of categories for valid objects from the database. 
	 * For example, a list of manufacturers
	 * 
	 * @param string $entitytypes Comma-separated list of entity types
	 * @param boolean $unlimited Include categories for all status devices. Enterprise users only.
	 * @return array
	 * @access public
	 * @static
	 * @acl 1
	 * @ClientTest 14/5/09
	 */
	static function getCategories($entitytypes="",$unlimited=false) {		
		if ($unlimited && DetectRight::getAccessLevel() < 9) $unlimited=false;
		$key = "DRCategories";
		if ($unlimited) $key .= "_unlimited";
		$key = DetectRight::cacheKey($key);
		$table = self::$table;
		
		$redetect=DetectRight::$redetect;
		if (!$redetect) {
			$return=self::$cacheLink->cache_get($key);
			if ($return) return $return;
		}

		$entityWhere = array();
		if (is_string($entitytypes) && !DRFunctionsCore::isEmptyStr($entitytypes)) {
			$entitytypes = explode(",",$entitytypes);
			$entityWhere['entitytype'] = array("op"=>"in","value"=>$entitytypes);
		}
		
		if (!$unlimited) {
			$entityWhere["status"]=1;
		}
		$entityWhere['category']=array("op"=>"<>","value"=>"");
		$return = self::$dbLink->getIDs($table,"category",$entityWhere,array("category"=>"ASC"),"","select distinct");
		
		self::$cacheLink->cache_set($key,$return);
		return $return;	
	}
	
	/**
	 * Get descriptions/model names for a manufacturer.
	 *
	 * @param string $category
	 * @param boolean $unlimited
	 * @param string $entitytypes
	 * @return associative_array
	 * @acl 1
	 * @access public
	 * @static
	 */
	static function getDescriptions($category,$unlimited=false,$entitytypes="") {
		$entityWhere = array();
		if (is_string($entitytypes) && !DRFunctionsCore::isEmptyStr($entitytypes)) {
			$entitytypes = explode(",",$entitytypes);
			$entityWhere['entitytype'] = array("op"=>"in","value"=>$entitytypes);
		} elseif (DRFunctionsCore::isEmptyStr($entitytypes)) {
			$entitytypes = self::$nominativeEntityTypes;
			$entityWhere['entitytype'] = array("op"=>"in","value"=>$entitytypes);
		}
		
		if (!$unlimited) {
			$entityWhere["status"]=1;
		}

		$entityWhere['category']=$category;
		$output = self::$dbLink->simpleFetch(self::$table,array("hash","description"),$entityWhere,array("description"=>"ASC"),"","hash");
		foreach ($output as $key=>$value) {
			$output[$key] = $value['description'];
		}
		return $output;
	}
	
	/**
	 * Get array of IDs for an entitytype
	 *
	 * @param array $entitytypes
	 * @param array $status
	 * @param string $returnHash
	 * @acl 9
	 * @return array
	 * @access public
	 * @static
	 */
	static function getIDs($entitytypes="",$status=false,$returnHash=false) {
		$table = self::$table;
		
		if (!$returnHash) {
			$returnField=self::$PK;
		} else {
			$returnField="hash";
		}

		$entityWhere = array();
		if (!DRFunctionsCore::isEmptyStr($entitytypes)) {
			if (is_string($entitytypes)) {
				$entitytypes = explode(",",$entitytypes);
			}
			$entityWhere['entitytype'] = array("op"=>"in","value"=>$entitytypes);
		}
		
		if ($status !== false) {
			if (is_string($status)) {
				$status = explode(",",$status);
			}
			$entityWhere['status'] = array("op"=>"in","value"=>$status);
		}
		
		return self::$dbLink->getIDs($table,$returnField,$entityWhere);	
	}
	
	/**
	 * Get Entity from Category, Description and other stuff, using cacheing and heuristics.
	 * Will also add a new device if you let it.
	 *
	 * @param string $category
	 * @param string $description
	 * @param string $entityType
	 * @param string $identifier
	 * @param string $owner
	 * @param boolean $modelClean
	 * @param boolean $allowAdd
	 * @param integer $addStatus
	 * @param boolean $heuristics
	 * @return Entity
	 * @acl 0
	 * @access public
	 * @static
	 * @ClientTest 13/5/09
	 */
	static function getEntityFromCatDesc($category,$description,$entityType="",$heuristics=true) {
		// this is an extra layer of protection for the entity table when you've got a new manufacturer/model.
		// yes, the entity object adds a new entity itself, but this gives a bit more protection from
		// the outside world.
		$entity = null;
		if (self::$dbLink === null) {
			self::reconnect();
		}
		$getEntityFromCatDescCacheKey = "";
		if (self::$eCache) {
			$getEntityFromCatDescCacheKey = "getEntityFromCatDesc_"."$entityType/$category/$description/".($heuristics ? "1" : "0");
			$entity = DRFunctionsCore::gv(self::$eCacheEntities,$getEntityFromCatDescCacheKey,false);
			if ($entity === null) return null;
			if ($entity !== false) {
				$retEntity = clone $entity;
				return $retEntity;
			}
			//if ($entity !== false) return $entity;
			//DetectRight::checkPoint("Non-object result from cache was $entity");
		}
		
		$et = array();
		if (DRFunctionsCore::isEmpty($entityType) && $category === "Browser") $entityType = "Browser";
		if (!DRFunctionsCore::isEmptyStr($entityType)) {
			$et[] = $entityType;
		} else {
			$entityType="Device";
			$et[] = self::$nominativeEntityTypes;
		}

		$fuzzyUsed=false;
		if (strtolower($category) == "blackberry") {
			$description = "BlackBerry ".$description;
			$category="RIM";
		}
		
		if (strtolower($category) == "spv") {
			$description = "spv ".$description;
			$category="Orange";
		}
		
		$testHash=self::makeHash($entityType,$category,$description);
		$entity=self::get(0,$testHash);

		if ($entity !== null && !$entity->id() && strpos($description,"_") !== false) {
			$testHash=self::makeHash($entityType,$category,str_replace("_"," ",$description));
			$entity=self::get(0,$testHash);
		}

		if ($entity !== null && !$entity->id() && strpos($description," ") !== false) {
			$testHash2=self::makeHash($entityType,$category,str_replace(" ","",$description));
			$entity=self::get(0,$testHash2);
		}

		if ($entity !== null && !$entity->id()) {
			$alias = EntityAliasCore::getEntityAliasFromHash($testHash);
			if (!is_null($alias->entityid)) {
				$entity = self::get($alias->entityid);
				if ($entity === null || $entity->ID === null) {
					$entity = EntityCore::getEntityFromHash($alias->entityhash);
				}
				if ($entity !== null) {
					$entity->majorrevision = $alias->majorrevision;
					$entity->subclass = $alias->subclass;
				}
			}
		}
		
		if (is_object($entity) && !DRFunctionsCore::isEmptyStr($entity->id())) {
			if (self::$eCache) {
				self::$eCacheEntities[$getEntityFromCatDescCacheKey] = clone $entity;
			}
			return $entity;
		}

		$where=array();
		$where['entitytype']=array("op"=>"in","value"=>$et);
		$where['category']=$category;
		$where['description']=$description;
		
		if ($entityType == "UserAgent" || !$heuristics) {
			// special case. No fancy matchin'.
			// here, we should add a check in the pointers table to see if this
			// is a user agent pointer, and if there isn't a userAgent pointer,
			// then return false, since this user agent is no longer an object.
			$result = self::$dbLink->getIDs(self::$table,self::$PK,$where);
			if ($result===false) return null;
			if (count($result)==0) {
				$entity=self::addEntity($entityType,$category,$description,$testHash);
			} else {
				$entityid=array_shift($result);
				$entity = self::getEntity($entityid);
			}
			if (!is_object($entity) || $entity->ID === null) {
				if (self::$eCache) {
					self::$eCacheEntities[$getEntityFromCatDescCacheKey] = null;
				}
				return null;
			}
			if (self::$eCache) {
				self::$eCacheEntities[$getEntityFromCatDescCacheKey] = clone $entity;
			}
			return $entity;
		}
		$knownCategoryTypes=array("Browser","Chipset","OS");
		// avoid manufacturer translations for absolutely known manufacturers to save a little bit of time.
		$knownManufacturers=array("Android","Nokia","Sony Ericsson","LG","Motorola","HTC","Samsung","RIM","Sanyo","Fujitsu","Lenovo","Audiovox","Philips","Grundig","Mitsubishi","O2","Orange","Vodafone","Symbian","Opera","Openwave","Alcatel","NTT DoCoMo","Google");
		if (!$category || !$description) return null;
		$category=trim($category);
		if (!$entityType) $entityType="Device";
		$category=str_replace(array("\t","\n","\r"),'',$category);
		$description=trim($description);
		$description=str_replace(array("\t","\n","\r"),'',$description);
		if (array_search($category,$knownCategoryTypes) === false) {
			if (array_search($category,$knownManufacturers) === false) {
				$category = Validator::translation($category,"Manufacturer");
			}
		}

		/*$profile=array();
		if ($modelClean && $entityType == "Device") {
			self::modelClean($category,$description,$profile);
			$where['category'] = $category;
			$where['description'] = $description;
		}*/

		if (!$description) {
			return null;
		}
		
		// check AKAs
		$entities = self::$dbLink->getIDs(self::$table,self::$PK,$where,"",array("limit"=>1));
		if (count($entities)==0) {
			// changed to more effectively deal with punctuation differences
			// stop duplicates coming in with dash differences.
			$catDescSearch=DRFunctionsCore::punctClean("$category$description");
			$where = array("catDescSearch"=>$catDescSearch);
			if ($entityType) $where["entitytype"]=$entityType;
			$entities = self::$dbLink->getIDs(self::$table,self::$PK,$where,"",array("limit"=>1));
		}
			
		// finally check AKAs
		if (count($entities)==0) {
			$entities = EntityAliasCore::getEntities($where,1);
		}

		if (count($entities) == 0) {
			$entityTypeEnc = self::$dbLink->escape_string($entityType);
			$cleanDesc = DRFunctionsCore::punctClean($description);
			if (strlen($cleanDesc) < 3) $cleanDesc="";
			if ($cleanDesc) {
				// changed to more effectively deal with punctuation differences
				// stop duplicates coming in with dash differences.
				$entities = self::$dbLink->getIDs(self::$table,self::$PK,array("postPart"=>$cleanDesc,"category"=>$category),array("expression"=>"(entitytype='$entityTypeEnc') DESC"),array("limit"=>1));
				if ($entities) $fuzzyUsed=true;
			}
		}

		if (count($entities)==0 && $cleanDesc) {
			// changed to more effectively deal with punctuation differences
			// stop duplicates coming in with dash differences.
			$entities = self::$dbLink->getIDs(self::$table,self::$PK,array("prePart"=>$cleanDesc,"category"=>$category),array("expression"=>"(entitytype='$entityTypeEnc') DESC"),array("limit"=>1));
			if ($entities) $fuzzyUsed=true;
		}

		//$entities=array_keys($entities);
		foreach ($entities as $entityID) {
			$entity = self::getEntity($entityID);
			if ($fuzzyUsed && is_object($entity)) {
				// we've used a fuzzy postpart: so we put in a category check. Otherwise things get silly.
				if (strtolower(substr($entity->category,0,2)) != strtolower(substr($category,0,2))) {
					$entity = null;
				}
			}
			break;
		}

		if (!is_object($entity) || !$entity->id()) {
				if (DetectRight::$LOG) DRFunctionsCore::dr_echo("Adding entity\n");
				//if (!isset($_GET['debug_port'])) die("New entity");
				$entity=self::addEntity($entityType,$category,$description,$testHash);
				if (DetectRight::$LOG) DRFunctionsCore::dr_echo("New Entity: $category $description");
		}
		
		//if (is_object($entity)) {
			if (self::$eCache) {
				self::$eCacheEntities[$getEntityFromCatDescCacheKey] = clone $entity;
			}
			return $entity;
		//} 
		//return null;
	}

	public static function findEntity($entitytype="",$category="",$description="",$hash="",$ID=0) {
		if ($ID > 0) {
			return new EntityCore($ID);
		}
		if (!DRFunctionsCore::isEmptyStr($hash)) {
			return new EntityCore(0,$hash);
		}

		return self::getEntityFromCatDesc($category,$description,$entitytype,true);
	}

	public function getExportProfile($schema) {
		$esc = new EntitySigCollection();
		$esc->addEntity($this);
		return $esc->getExportProfile($schema);
	}
	
	static function addEntity($entityType,$category,$description,$hash) {
		if (DRFunctionsCore::isEmptyStr($entityType)) return null;
		if (DRFunctionsCore::isEmptyStr($category)) return null;
		if (DRFunctionsCore::isEmptyStr($description)) return null;
		if (DRFunctionsCore::isEmptyStr($hash)) return null;
		
		$entity = new EntityCore(0);
		$entity->error="";
		$entity->entitytype = $entityType;
		$entity->category = $category;
		$entity->description = $description;
		$entity->hash = $hash;
		$entity->owner = DetectRight::$data_owner;
		$entity->ID = 0;
		if (self::$nominativeEntityTypes && !in_array($entityType,self::$nominativeEntityTypes)) {
			$entity->exclusivity = 0;
		} else {
			$entity->exclusivity = 2;
		}
		$entity->status = 2;
		return $entity;
	}
	
	/**
	 * Exposed by the web service, this does a composite base-level profile for an entity
	 * with whatever schema is active on this access. Responds to comma delimited schemas
	 * but not to comma-delimited entitytypes, which actually would make no sense considering
	 * this function works on a single entity.
	 *
	 * @param string $entitytype
	 * @param string $category
	 * @param string $description
	 * @param string $hash
	 * @param integer $ID
	 * @return Entity
	 * @acl 1
	 * @access public
	 * @static
	 * @ClientTest 14/5/09
	 */
	static function getExportProfileForManModel($entitytype="",$category="",$description="",$hash="",$ID=0) {
		// assumes no specific version numbers or whatever
		/* @var $entity Entity */
		$output=array();
		$entity = self::findEntity($entitytype,$category,$description,$hash,$ID);
		if ($entity==false) {
			return false;
		}			

		$cache = $entity->getCache();
		$profiles=$cache['basicprofile'];
		
		// if this is from a customer, let's generate it from scratch.
		if (DetectRight::getAccessLevel() > 0) $profiles=array();
		$schemas = explode(",",DetectRight::$schema);
		foreach ($schemas as $schema) {
			if (isset($profiles[$schema])) {
				$return = $profiles[$schema];
			} else {
				$return = $entity->getExportProfile($schema);
				
			}
			unset($return['audit']);
			$output = array_merge($output,$return);
		}
		return $output;
	}
	
	/**
	 * Get an entity from its ID, with cacheing.
	 *
	 * @param integer $id
	 * @return Entity
	 * @internal
	 * @access public
	 * @static
	 */
	static function getEntity($id) {
		DetectRight::checkPoint("Asked for Entity $id");
		$entity = null;
		if (self::$useCache && !DetectRight::$redetect && DRCache::$useCache) {
			$entity = self::$cacheLink->cache_get(self::getCacheKey($id));
			if (is_object($entity)) $entity->cached=true;
		}
		if (is_null($entity) || $entity === false || !is_object($entity)) {
			$entity = new EntityCore($id);
			if (self::$useCache && $entity->id() && DRCache::$useCache) {
				$entity->cache();
			} elseif ($entity->id()) {
				// don't need to do anything.
			} else {
				DetectRight::checkPoint("Failed to get entity $id");
				return false;
			}
		}  else {
			DetectRight::checkPoint("Served Entity $id from cache");
		}
		return $entity;
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
			return self::getEntity($id);
		} else {
			return self::getEntityFromHash($hash);
		}
	}
	/**
	 * Gets an entity from its hashcode.
	 *
	 * @param string || array $hash
	 * @return Entity
	 * @acl 1
	 * @access public
	 * @static
	 * @ClientTest 13/5/09
	 */
	static function getEntityFromHash($hash) {
		$output = array();
		if (is_null($hash)) return new EntityCore(0);
		if (is_string($hash)) {
			$entity=new EntityCore(0,$hash);
			if ($entity->error) {
				$alias = EntityAliasCore::getEntityAliasFromHash($hash);
				if (is_object($alias) && $alias->ID) {
					$entity = new EntityCore($alias->entityid);
					$entity->majorrevision = $alias->majorrevision;
					$entity->subclass = $alias->subclass;
					$entity->alias = $alias;
					return $entity;
				}
				self::$ERROR="No alias";
				return $entity;
			}
			return $entity; // nice XML or serialized representation of entity without object.
		} elseif (is_array($hash)) {
			foreach ($hash as $ehash) {
				$entity = self::getEntityFromHash($ehash);
				$output[$ehash] = $entity;
			}
		}
		return $output;
	}
	
	public function cacheDB() {
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		$this->cache = self::$cacheLink;
		
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
		$this->db = self::$dbLink;		
		
		if (DRFunctionsCore::isEmptyStr(self::$nominativeEntityTypes)) {
			self::$nominativeEntityTypes = self::$dbLink->getArray("nominativeEntityTypes");
		}
	}
	
	static public function reconnect() {
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
		if (self::$dbLink && DRFunctionsCore::isEmptyStr(self::$nominativeEntityTypes)) {
			self::$nominativeEntityTypes = self::$dbLink->getArray("nominativeEntityTypes");
		}
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
	 * Total number of entities in database, keyed by entitytype
	 *
	 * @return array
	 * @acl 0
	 * @access public
	 * @static
	 * @ClientTest 18/5/09
	 */
	static function total() {
		// returns summary totals of devices in database
		$result = array();
		$output = array();
		$result = self::$dbLink->fillArrayFromSQL("select entitytype,count(*) as cnt from entity group by entitytype","entitytype");
		foreach ($result as $entitytype=>$array) {
			$output[$entitytype] = $array['cnt'];
		}
		return $output;
	}
}