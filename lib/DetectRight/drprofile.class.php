<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    drprofile.class.php
Version: 2.3.1
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
2.3.1 - minor bug fix to matches function
2.3.1 - other minor bug fixes
2.3.1 - this is still BETA, numerous fixes ongoing.
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRProfile");
}

Class DRProfile {
	// holder for DRProfileResults.
	public $results = array(); // Profile Result Array
	public $overrides = array(); // Profile Result Array
	public $metadata = array(); // Profile Result Array
	public $boolFormat = "truefalse";
	public $strictExportNames = true;
	public $headers; // HTTPHeadersCore
	public $esc; // EntitySigCollection
	public $schema = array();
	public $filled = false;
	public $source = "";
	static $profileCache = false;

	public function clear() {
		if (is_object($this->esc)) {
			$this->esc->close();
		}
		
		foreach ($this->results as $key=>$value) {
			unset($results[$key]);
		}
		
		foreach ($this->overrides as $key=>$value) {
			unset($overrides[$key]);
		}

		foreach ($this->metdata as $key=>$value) {
			unset($metadata[$key]);
		}
		
		$this->headers = null;
	}
	
	public function __construct($strictExportNames = true, $boolFormat = "truefalse",$source = "") {
		$this->strictExportNames = $strictExportNames;
		if ($boolFormat) {
			$this->boolFormat = $boolFormat;
		}
		if ($source) $this->source = $source;
	}
	
	public function matches($key,$value,$checkOverrides = true) {
		// does this profile match the incoming value?
		if ($checkOverrides) {
			$result = $this->getOverrideObject($key);
			if (is_object($result)) {
				if ($result->matches($value)) return true;
			}
		}
		$result = $this->getProfileResult($key);
		if ($result === null) return false;
		return $result->matches($value);
	}
	
	public function getProfileResult($field) {
		if (!isset($this->results[$field])) return null;
		$pr = $this->results[$field];
		return $pr;
	}
	
	public function overrideExists($field) {
		if (isset($this->overrides[$field])) return true;
		return false;
	}
	
	public function getOverrideObject($field) {
		if (!$this->overrideExists($field)) return null;
		return $this->overrides[$field];
	}
	
	public function getOverride($field) {
		$override = $this->getOverrideObject($field);
		if ($override === null) return null;
		return $override->getValue();
	}
	
	public function getValue($field,$checkOverrides = true) {
		if ($checkOverrides) {
			$result = $this->getOverride($field);
			if ($result !== null) return $result;
		}
		$result = $this->getProfileResult($field);
		if (!is_object($result)) return $result; // allow abuse!
		return $result->getValue();
	}

	public function addProfileResult($pr) {
		if (!is_object($pr)) return false;
		$this->results[$pr->getKey()] = $pr;
		return true;
	}
	
	public function addResult($sp,$value,$importances) {
		if ($this->strictExportNames) {
			$key = $sp->display_name;
		} else {
			$key = $sp->property;
		}
		// value could be array, importances is always.
		$result = DRProfileResult::newResult($key,$value,$importances,$sp);
		$result->_boolFormat = $this->boolFormat;
		$this->results[$key] = $result;
	}
	
	public function getFieldList() {
		return array_keys($this->results);
	}
	
	public function getImportance($field,$value) {
		$pr = $this->getProfileResult($field);
		if (!$pr) return 0;
		return $pr->getImportance($value);
	}

	public function addMetadata($key,$value,$type="Literal") {
		$sp = new SchemaPropertyCore();
		$sp->property = $key;
		$sp->display_name = $key;
		$sp->type = $type;
		$value = array($value);
		$this->metadata[$key] = DRProfileResult::newResult($key,$value,array($key=>"999"),$sp);
	}
	
	public function getAllMetadata($includeQDT = true) {
		$output = array();
		if ($includeQDT && $this->esc->qdt) {
			$output = $this->esc->qdt->metadata;
		}
		foreach ($this->metadata as $key=>$result) {
			if ($result === null) continue;
			$output[$key] = $result->getValue();
		}
		return $output;
	}
	
	public function clearOverrides() {
		$this->overrides = array();
	}
	
	public function addOverride($key,$value,$force = true,$type = "Literal",$source = "") {
		// check if key is currently in the schema
		if (!$force) {
			if ($this->matches($key,$value)) return;
			if ($this->matchesOverride($key,$value)) return;
			$importances = array($key=>"0");
		} else {
			$importances = array($key=>"200");
		}
		if (isset($this->schema[$key])) {
			$sp = $this->schema[$key];
		} else {
			$sp = new SchemaPropertyCore();
			$sp->property = $key;
			$sp->display_name = $key;
			$sp->type = $type;
			$sp->error = "";
		}
		$result = DRProfileResult::newResult($key,$value,$importances,$sp);
		$result->setSource($source);
		if ($type) {
			$result->setType($type);
		}
		$this->overrides[$key] = $result;
	}
	
	public function getAllOverrides() {
		$output = array();
		foreach ($this->overrides as $key=>$result) {
			$output[$key] = (string) $result->getValue();
		}
		return $output;
	}

	public function getAllData() {
		$output = array();
		foreach ($this->results as $key=>$result) {
			$value = $result->getValue();
			if (is_array($value)) $value = implode(",",$value);
			$output[$key] = $value;
		}
		return $output;
	}

	public function addSP($sp) {
		if (!is_object($sp)) return;
		if ($this->strictExportNames) {
			$key = $sp->display_name;
		} else {
			$key = $sp->property;
		}
		$this->schema[$key] = $sp;
	}
	
	public function getAllValues() {
		// return the whole shebang;
		$this->fillMe();
		
		$data = $this->getAllData();
		$overrides = $this->getAllOverrides();
		
		foreach ($overrides as $key=>$value) {
			$data[$key] = $value;
		}
		return $data;
	}
	
	public function fillMe() {
		// gets a DRProfileObject
		//DetectRight::$flush = true;
		if ($this->filled) return;
		$this->esc->qdt->processPackages();
		
		foreach ($this->schema as $nsp) {
			// if errorTrap = 1, then this is a field that isn't "official" in the schema: i.e., it's misspelled or something.
			// we need the data in it, since that's likely to be valid, but we don't want the field appearing in output.
			if ($nsp->error_trap > 0) continue;

			// if there's no output mapping, then nothing can happen.
			if (DRFunctionsCore::isEmptyStr($nsp->output_map)) continue;

			$array = array();
			$value = SchemaPropertyCore::getObjectValue($this->esc->qdt,$nsp,$array);

			$this->addResult($nsp,$value,$array);
		}
		$this->filled = true;
	}
	

	/**
	 * Gets a profile from the entity in the schema of WURFL, DR, W3C, or whatever.
	 * Contains is unset because it usually has no place in the profile.
	 * This ought
	 *
	 * @param string $schema
	 * @return 
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public function getProfile($schemas = "")  {
		//if ($dbLink->params) $profile['dbconn'] = $dbLink->params;

		$schemas = explode(",",$schemas);
		DetectRight::checkPoint("Getting profile");
		// get the SPs
		foreach ($schemas as $schema) {
			$schemaCollection = SchemaPropertyCore::getSchema($schema);
			foreach ($schemaCollection as $sp) {
				$this->addSP($sp);			
			}
		}
		
		$this->fillMe();
		DetectRight::checkPoint("Generated Profile");

		$nomEntity = $this->esc->getNominativeEntity();
		if ($nomEntity === null || $nomEntity === false) {
			$dummy=true;
		}
		// the below is a hack and probably isn't necessary any more.
		$status = array();
		$status[] = "1";
		$status[] = "2";
		$akas = EntityAliasCore::getAliasCollection($nomEntity->id(),$status);
		$descriptors = array();
		$hashes = array();
		foreach ($akas as $ea) {
			if (!is_object($ea)) continue;
			$desc = $ea->entitytype . ":" . $ea->category . ":" . $ea->description;
			$hashes[] = $ea->hash;
			$descriptors[] = $desc;
		}
		
		$this->addMetadata("deviceid",$nomEntity->hash);
		$this->addMetadata('id',$nomEntity->hash);
		$this->addMetadata('devicedescriptor',$nomEntity->entitytype . ":" . $nomEntity->category . ":" . $nomEntity->description);
		$this->addMetadata('components',implode(",",$this->esc->descriptors));
		if (is_object($this->headers)) {
			$this->addMetadata('uid',$this->headers->uid);
		}
		$this->addMetadata('altdescriptors',implode(",",$descriptors));
		$this->addMetadata('altids',implode(",",$hashes));

		$eHashes = array();
		$eHashes = $hashes;
		//$eHashes[] = $nomEntity->hash;
		// add component overrides
		if ($eHashes) {
			$where = array("entityhash"=>array("op"=>"in","value"=>$eHashes));
			$this->addOverrides($where,true); // override
		}
			
		// add entity overrides
		$this->addOverrides(array("entityhash"=>$nomEntity->hash),true);
			
		$where = array();
		$deviceType = $nomEntity->entitytype;
		// something about useragents as browsers here!
		$readersAsTablets = DetectRight::$readersAsTablets;
		if ($readersAsTablets === null) $readersAsTablets = false;
		if ($readersAsTablets && $nomEntity !== null && $nomEntity->entitytype === "e-Reader") {
			$deviceType="Tablet";
		}

		$et = null;
		if ($nomEntity !== null && $nomEntity->entitytype === "UserAgent") {
			// find out whether this is a mobile browser or a browser
			$et = $this->getEntityTree();
			if ($et !== null && isset($et["Mobile Browser"])) {
				$deviceType = "Mobile Device";
			} else if (isset($et["Browser"])) {
				$eArr = $et["Browser"];
				$e = array_shift($eArr);
				if ($e && $e->entitytype == "Mobile Browser") {
					$deviceType = "Mobile Device";
				} else {
					$deviceType = "Desktop";
				}
			}
		}

		if ($deviceType === "UserAgent") $deviceType="Desktop";
		$where['entityhash'] = "generic_" + $deviceType;
		$this->addOverrides($where,false);
		$where = array();
		$where['entityhash'] = "generic";
		$this->addOverrides($where,false);

		$this->addMetadata('type',$deviceType);
		if ($et !== null && isset($et['UserAgent']) && DetectRight::$enableUserAgentEntityType === false) {
			$uaKeys = $this->fieldsForValue($nomEntity->category,true);
			foreach ($uaKeys as $uaKey) {
				$this->addOverride($uaKey,"Generic");
			}
			
			$uaKeys = $this->fieldsForValue($nomEntity->description,true);
			foreach ($uaKeys as $uaKey) {
				$this->addOverride($uaKey, $deviceType);
			}
		}
	}

	public function fieldsForValue($value,$strict = false) {
		// returns a list of fields which have a particular value.
		$keys = array();
		foreach ($this->results as $key=>$result) {
			if ($strict) {
				if ($result->getValue() === $value) $keys[] = $key;
			} else {
				if ($result->getValue() == $value) $keys[] = $key;
			}
		}
		return $keys;
	}
	
	public function addOverrides($where,$forceOverride) {
		// force override 
		$dbLink = &EntityCore::$dbLink;
		if (!is_object($dbLink)) $dbLink = DetectRight::$dbLink;

		if (!$where) return;

		$custom = false;
		if (is_object($dbLink) && $dbLink->dbOK) {
			$overrides = $dbLink->simpleFetch("entity_overrides",array("*"),$where);
			if (is_array($overrides)) {
				foreach ($overrides as $row) {
					if ($row !== null) {
						$key = DRFunctionsCore::gv($row,'key');
						$value = DRFunctionsCore::gv($row,"value");
						$this->addOverride($key,$value,$forceOverride);
						$custom = true;						
					}
				}
			}
		}
		if ($custom) $this->addMetadata('customised',"1");
	}

	/**
	 * Get a profile for a schema from a full header set
	 *
	 * @param associative_array $lhm
	 * @param string $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
    static public function getProfileFromHeaders($lhm=array(),$schema="", $source = "", $boolFormat = "") {
 		$profile = new DRProfile(SchemaPropertyCore::$strictExportNames,$boolFormat, $source);
 		
    	if (DRFunctionsCore::isEmpty($lhm)) return array();
    	if (!is_array($lhm) && !is_object($lhm)) return array();
    	if (!is_object($lhm)) {
    		$profile->headers = new HTTPHeadersCore($lhm);
    	} else {
    		$profile->headers = $lhm;
    	}
    	$customer = $profile->headers->uid;
    	$key = "DRPHPDRP/$customer/$schema";
    	try {
    		if (self::$profileCache && !DetectRight::$redetect && DetectRight::$cacheLink !== null) {
    			$profile = DetectRight::$cacheLink->cache_get($key);
    			if ($profile) {
    				return $profile;
    			}
    		}
			if (!$profile->detect()) return $profile;
			$profile->getProfile($schema,$source);
			if (self::$profileCache && DetectRight::$cacheLink !== null) {
				DetectRight::$cacheLink->cache_set($key,$profile,3600);
			}
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DetectRightException $dre) {
			throw $dre;
		} catch (Exception $e) {
			throw new DetectRightException("Failed to detect ",$e);
		}
		return $profile;
	}

	/**
	 * Get a profile for a schema from a useragent. Also works with TAC codes, oddly.
	 *
	 * @param String $useragent
	 * @param String $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromUA($useragent,$schema="",  $source = "", $boolFormat = "") {
		if (DRFunctionsCore::isEmpty($useragent)) return array();
		if (!is_string($useragent) || !is_string($schema)) return array();

		$lhm = array("HTTP_USER_AGENT"=>$useragent);
		return self::getProfileFromHeaders($lhm, $schema, $source, $boolFormat);
	}

	/**
	 * Retrieve a profile from a Device ID: that's a 32 char hash based on the
	 * entity type, manufacturer and model. This needn't be cached.
	 *
	 * @param String $hash
	 * @param String $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
	static public function getProfileFromDeviceID($hash,$schema="",  $source = "", $boolFormat = "") {
		$profile = new DRProfile(SchemaPropertyCore::$strictExportNames,$boolFormat, $source);
		try {
			if (!$profile->detectDevice($hash)) return array();
			$profile->getProfile($schema,$source);
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DetectRightException $dre) {
			throw $dre;
		} catch (Exception $e) {
			throw new DetectRightException("Failed to get Device Id $hash",$e);
		}
		return $profile;
	}

	/**
	 * Retrieve a profile from the
	 * entity type, manufacturer and model of a device.
	 * Use "Device" as a catch-all.
	 *
	 * @param String $hash
	 * @param String $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromDevice($entitytype,$category,$description,$schema="",  $source = "", $boolFormat = "") {
		try {
			$entity = EntityCore::getEntityFromCatDesc($category,$description,$entitytype,false);
			if (!is_object($entity)) throw new DeviceNotFoundException("Didn't find $entitytype $category $description");
			$hash = $entity->hash;
			$profile = self::getProfileFromDeviceID($hash,$schema,$boolFormat,$source);
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DetectRightException $dre) {
			throw $dre;
		} catch (Exception $e) {
			throw new DetectRightException("Failed to detect $entitytype $category $description",$e);
		}
		return $profile;
	}

	/**
	 * Gets a profile from an IMEI/TAC number
	 *
	 * @param mixed $tac
	 * @param String $schema
	 * @return associative_array
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromTAC($tac,$schema = "",  $source = "", $boolFormat = "") {
		$profile = new DRProfile(SchemaPropertyCore::$strictExportNames,$boolFormat, $source);
		if (DRFunctionsCore::isEmpty($tac)) throw new DeviceNotFoundException("Empty TAC!");
		if (!is_numeric($tac)) return $tac;
		if (strlen($tac) > 8) $tac = substr($tac,0,8);
		$tacESC = PointerCore::getESC("TAC",md5($tac));
		if (count($tacESC->entities) == 0) {
			throw new DeviceNotFoundException("TAC $tac Not Found");
		}
		$profile->processDetection($tacESC);
		return $profile->getProfile($schema,$source);
	}

	/**
	 * Gets a profile from a Phone ID: that is, an arbitrary text string that might be
	 * used in other systems to identify a device.
	 *
	 * @param mixed $tac
	 * @param String $schema
	 * @return associative_array
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromPhoneID($id,$schema = "",  $source = "", $boolFormat = "") {
		if (DRFunctionsCore::isEmpty($id)) throw new DeviceNotFoundException("Empty ID!");
		$phoneIDESC = PointerCore::getESC("PhoneID",md5($id));
		if (count($phoneIDESC->entities) == 0) {
			throw new DeviceNotFoundException("Phone ID $id Not Found");
		}
		$profile = new DRProfile(SchemaPropertyCore::$strictExportNames,$boolFormat, $source);
		$profile->processDetection($phoneIDESC);
		return $profile->getProfile($schema, $source);
	}

	/**
	 * Gets a profile from a UAProfile URL. This kind of gets done implicitly during a
	 * detection, but this is a quicker way if all you've got is the UAProfile URL.
	 *
	 * @param mixed $tac
	 * @param String $schema
	 * @return associative_array
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromUAProfile($uap,$schema = "",  $source = "", $boolFormat = "") {
		if (DRFunctionsCore::isEmpty($uap)) throw new DeviceNotFoundException("Empty URL!");
		$url = DRFunctionsCore::cleanURL($uap);
		$uapESC = PointerCore::getESC("UAP",md5($url));
		if (count($uapESC->entities) == 0) {
			throw new DeviceNotFoundException("UAProfile link $uap Not Found");
		}
		$profile = new DRProfile(SchemaPropertyCore::$strictExportNames,$boolFormat, $source);
		$profile->processDetection($uapESC);
		return $profile->getProfile($schema, $source);
	}
	
	/**
	 * Runs a detection on either a pre-prepared HTTPHeaders object, or a raw $_SERVER
	 * type array. Produces an EntitySigCollection, which is a special object containing
	 * the list of all detected components and their versions.
	 *
	 * @param mixed $headers Associative array or HTTPHeadersCore/HTTPHeaders object
	 * @return EntitySigCollection
	 */
	public function detect()  {
		if (DRFunctionsCore::isEmpty($this->headers)) return false;
		if (!is_object($this->headers)) return false;

		try {
			$this->headers->process();
			$result = $this->processDetection($this->headers->getESC());
		} catch (DetectRightException $de) {
			throw $de;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (Exception $e) {
			throw new DetectRightException("Error detecting",$e);
		}
		return $result;
	}

	/**
	 * The second bit of the detection: we've got something, and now we process it to add
	 * contained entities, and other stuff.
	 *
	 * @param EntitySigCollection $esc
	 * @return boolean
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public function processDetection(EntitySigCollection $esc) {
		try {
			$esc->addEntityContains();
			DetectRight::checkPoint("Getting QDT");
			$esc->getQDT();
			DetectRight::checkPoint("Got QDT");
			//System.out.println(esc.qdt.getPackages());
			$esc->qdt->processPackages();
			$esc->resetRootEntity();
			$this->esc = $esc;
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (DetectRightException $de) {
			throw $de;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (Exception $e) {
			throw new DetectRightException("Error detecting",$e);
		}
		return true;
	}
	
	/**
	 * Gets a detection for a device in the database based on the deviceId/entity hash.
	 *
	 * @param mixed $id String or integer corresopnding to entity.hash/id column in DetectRight database
	 * @return boolean success or failure
	 * @see EntitySigCollection
	 * @throws DeviceNotFoundException
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public function detectDevice($id) {
		if (is_numeric($id) && strlen($id < 32)) {
			$entity = EntityCore::get($id);
		} else {
			$entity = EntityCore::get(0,$id);
		}
			
		if ($entity === null || !is_object($entity) || $entity->id() == 0)	{
			// absolutely no point in doing adaptation for this, there's no device!
			throw new DeviceNotFoundException("Device $id not found");
		}

		if ($entity->id() < 0) throw new DeviceNotFoundException("Invalid entity object passed in");
		
		$esc = new EntitySigCollection();
		$esc->addEntity($entity);
		//esc.filter();
		// resolve the manifest from the contain chains of each of its entities by going through them
		// and adding them to the ETS. Components which have been superceded in some way will be rejected.
		$esc->addEntityContains();
		DetectRight::checkPoint("Getting QDT");
		$esc->getQDT();
		//System.out.println(esc.qdt.getPackages());
		$esc->qdt->processPackages();
		$this->esc = $esc;
		return true;
	}
}