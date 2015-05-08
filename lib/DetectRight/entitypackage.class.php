<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    entitypackage.class.php
Version: 2.5.0
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

2.5.0 - finally entered beta and use in anger.
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("EntityPackage");
}

/**
 * Entity Class. Holds Devices, Hardware Platforms, Browsers, Java Platforms, and whatever else we put in.
 * Pretty much the core entity of the application.
 * 
 */
Class EntityPackage {
	
	static $dbLink;
	protected $db;
	static $cacheLink;
	protected $cache;

	/**
	 * Recordset onto entity table
	 *
	 * @var RecordSet
	 */
	private $entityRS;
	
	/**
	 * Main container for data about entity. Array of hashmaps, basically.
	 *
	 * @var associative_array
	 */
	private $entity;

	/**
	 * Entity Alias Recordset Connector
	 *
	 * @var RecordSet
	 */
	private $entity_alias;

	/**
	 * Pointers Recordset connection
	 *
	 * @var RecordSet
	 */
	private $pointers;
	
	/**
	 * Entity Profiles connection
	 *
	 * @var RecordSet
	 */	
	private $entity_profiles;
	
	/**
	 * Entity Contains Recordset connection
	 *
	 * @var RecordSet
	 */	
	private $entity_contains;
	
	/**
	 * Entity Overrides Recordset connection
	 *
	 * @var RecordSet
	 */	
	private $entity_overrides;
	
	function __construct() {
		$this->cacheDB();
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

	
	function getEntityID() {
		return DRFunctionsCore::gv(entity,"ID");
	}

	function getEntityHash() {
		return DRFunctionsCore::gv(entity,"hash");
	}

	/**
	 * Fill the entity with all its bits
	 *
	 * @param String $hash
	 */
	public function fill($hash) {
		$this->entityRS = $this->db->fetchRecordset(EntityCore::$table,array("*"),array("hash"=>$hash));
		$this->entity = $this->entityRS->fetch();
		$entityid = "-1";
		if ($this->entity) {
			$entityid = DRFunctionsCore::gv($this->entity,"ID");
		}
		$wc = array("entityid"=>$entityid);
		$this->entity_alias = $this->db->fetchRecordset(EntityAliasCore::$table,array("*"),$wc);
		$this->pointers = $this->db->fetchRecordset(PointerCore::$table,array("*"),$wc);
		$this->entity_profiles = $this->db->fetchRecordset(EntityProfileCore::$table,array("*"),$wc);
		$this->entity_contains = $this->db->fetchRecordset("entity_contains",array("*"),$wc);
		$this->entity_overrides = $this->db->fetchRecordset("entity_overrides",array("*"),$wc);
	}

	public function addFrom(EntityPackage $ep) {
		// fills the database with a new entity.
		$success = $this->entityRS->processRow($ep->entity);
		$success = $success & RecordSet::syncRS($ep->entity_alias,$this->entity_alias);
		$success = $success & RecordSet::syncRS($ep->pointers,$this->pointers);
		$success = $success & RecordSet::syncRS($ep->entity_profiles,$this->entity_profiles);
		$success = $success & RecordSet::syncRS($ep->entity_contains,$this->entity_contains);
		$success = $success & RecordSet::syncRS($ep->entity_overrides,$this->entity_overrides);
		return $success;
	}

	public static function transferEntityData($fromID,$toID) {
		$tables = array("entity_contains","entity_profiles","entity_overrides","entity_alias","pointers");
		$success = true;
		foreach ($tables as $table) {
			$success = $this->db->updateData($table,array("entityid"=>$toID),array("entityid"=>$fromID),"","","update ignore") && $success;
		}
	}

	public static function removeEntity($id) {
		$tables = array("entity_contains","entity_profiles","entity_overrides","entity_alias","pointers");		
		foreach ($tables as $table) {
			$cnt = $this->db->dCount($table,"*");
			if ($cnt > 0) return false;
		}
		return $this->db->deleteData("entity",array("ID"=>$id)); // only orphaned entities
	}
	
	public function addFromPortablePackage($package) {
		// this takes a portable package and massages it into the database.
		$entity = DRFunctionsCore::gv($package,"entity", null);
		if (!$entity) throw new DetectRightException("Entity data not in package",null);
		
		$entity_alias = DRFunctionsCore::gv($package,"entity_alias",null);
		if ($entity_alias === null) throw new DetectRightException("Entity Alias data not in package",null);

		$entity_contains = DRFunctionsCore::gv($package,"entity_contains", null);
		if ($entity_contains === null) throw new DetectRightException("Entity Contains data not in package",null);
		
		$entity_overrides = DRFunctionsCore::gv($package,"entity_overrides", null);
		if ($entity_overrides === null) throw new DetectRightException("Entity Overrides data not in package",null);
		
		$entity_profiles = DRFunctionsCore::gv($package,"entity_profiles", null);
		if ($entity_profiles === null) throw new DetectRightException("Entity profiles data not in package",null);

		$pointers = DRFunctionsCore::gv($package,"pointers", null);
		if ($pointers === null) throw new DetectRightException("Pointers data not in package",null);

		$entityid = $entity['ID'];
		$entityhash = $entity['hash'];
		$this->fill($entityhash);
		
		$success = $this->entityRS->processRow($entity);
		
		// run through checking hashes. Main problem is if what's now an alias is a main here.
		$toDelete = array();
		foreach ($entity_alias as $key=>$row) {
			$hash = $row['hash'];
			$entity = new EntityCore(0,$hash);
			if ($entity->ID) {
				// this entity no longer exists in the database, it's now an alias. We need to transfer all its stuff, then delete it at the end.
				$success = self::transferEntityData($entity->ID, $entityid);
				if ($success) {
					$toDelete[] = $entity->ID;
				} else {
					throw new DetectRightException("Failed to move data from $entity->ID to $entityid");
				}
			}
		}
		
		// right, we've got everything.
		// main worry is that the contains hash is pointing to the right place, since we're referencing other
		// entities that might have been moved or something.
		foreach ($entity_contains as $key=>$row) {
			$containsID = $row['contains'];
			$containsHash = $row['containshash'];
			$c = EntityCore::getEntityFromHash($containsHash);
			if ($c->ID !== $containsID || $c->hash !== $containsHash) {
				$entity_contains[$key]["contains"] = $c->ID;
				$entity_contains[$key]["containshash"] = $c->hash;
			}
		}
		
		$success = $success & RecordSet::syncRSWithArray($entity_alias,$this->entity_alias);
		$success = $success & RecordSet::syncRSWithArray($pointers,$this->pointers);
		$success = $success & RecordSet::syncRSWithArray($entity_profiles,$this->entity_profiles);
		$success = $success & RecordSet::syncRSWithArray($entity_contains,$this->entity_contains);
		$success = $success & RecordSet::syncRSWithArray($entity_overrides,$this->entity_overrides);
		
		foreach ($toDelete as $delID) {
			$success = self::removeEntity($delID) && !$success;
		}
		return $success;

	}
	
	public function isCompatibleWith(EntityPackage $ep) {
		$srcID = $this->getEntityID();
		$srcHash = $this->getEntityHash();
		$destID = $ep->getEntityID();
		$destHash = $ep->getEntityHash();

		if ($srcID === $destID && $srcHash === $destHash) return true;
		return false;
	}

	static function getPkg($hash, DBLink $source)  {
		$db = &$source;
		$ep = new EntityPackage();
		$ep->db = &$db;
		$ep->fill($hash);
		return $ep;
	}

	function delete()  {
		// in all honesty, by the time the rest of the script has run, there shouldn't be too much more to delete here.
		$this->entity_alias->delete();
		$this->entity_contains->delete();
		$this->entity_profiles->delete();
		$this->entity_overrides->delete();
		$this->pointers->delete();
		$this->db->deleteData(EntityCore::$table,array("hash"=>$this->getEntityHash()));
	}
	
	function getPortablePackage() {
		// wraps up the current package into one transportable array
		$output = array(); // 
		$output["entity"] = $this->entity;
		$output["entityrs"] = $this->entityRS->fetchAll();
		$output["entity_alias"] = $this->entity_alias->fetchAll();
		$output['entity_contains'] = $this->entity_contains->fetchAll();
		$output['entity_overrides'] = $this->entity_overrides->fetchAll();
		$output['entity_profiles'] = $this->entity_profiles->fetchAll();
		$output['pointers'] = $this->pointers->fetchAll();
		return $output;
	}
}