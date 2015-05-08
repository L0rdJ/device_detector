<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    entitysigcollection.class.php
Version: 2.7.1
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

// the EntitySig collection is an intelligent collection of EntitySigs. As each one is put in, it accepts or rejects
// them based on the underlying entity.
// at some point we need to mark each component as "default" or "changed".
/*
Changelog:
2.1.2 - Altered getExportProfile to add in generic overrides, and fix the bug where it 
wasn't retrieving overrides because the query had an "owners" field in it, and 
the table doesn't have one in the compiled database: only back at HQ 
Added browsersAsUserAgents code
2.2 - tweaked override code and added device types
2.2.1 - handles some non-arrays a bit better
2.3.0 - tweak to make sure that you can turn off Nominative entity adding to an ESC if necessary.
2.3.0 - hardcoded "emptyhash" to 4501c091b0366d76ea3218b6cfdd8097
2.3.1 - in addEntityContains, clone the ESC entities before doing anything, since the loop changes the array. Java was already doing this.
2.3.5 - additional check on destruct for QDT.
2.5.0 - Entity type strings, and additional functions has() and hasNominativeEntity()
2.5.1 - new function to change importance of ESs
2.7.1 - added getEntityForEntityType($entitytype) 
*/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("EntitySigCollection");
}

Class EntitySigCollection {
	
	static $containedComponentOffset = -15;
	static $containedNominativeComponentOffset = -5;
	
	public $entityTypesString = "";
	public $allowNominativeEntities = true;
	public $es = array();
	public $entityTypes = array();
	public $entities = array();
	public $entityHashes = array();
	public $rejected = array();
	public $descriptors = array();
	public $qdt;
	public $uid;
	public $nom;
		
	static $cacheLink;
	static $useCache = false;
	static $cache_timeout = 6000;
	protected $cache;

	static $dbLink;
	protected $db;
	
	public function __construct() {
		$this->cacheDB();
		$this->qdt = new QuantumDataTree("",null);
	}
	
	public function __destruct() {

		$this->cache = null;
		$this->db = null;

		if (isset($this->entities) && is_array($this->entities)) {
			foreach (array_keys($this->entities) as $key) {
				$this->entities[$key] = null;
				unset($this->entities[$key]);
			}
			$this->entities = null;
		}

		if (isset($this->es) && is_array($this->es)) {
			foreach (array_keys($this->es) as $key) {
				$this->es[$key] = null;
				unset($this->es[$key]);
			}
			$this->es = null;
		}

		if (isset($this->rejected) && is_array($this->rejected)) {
			foreach (array_keys($this->rejected) as $key) {
				$this->rejected[$key] = null;
				unset($this->rejected[$key]);
			}
		} else {
			unset($this->rejected);
		}
		
		if (isset($this->qdt) && is_object($this->qdt)) {
			$this->qdt->close();
			unset($this->qdt);
		}
	}

	public function resetRootEntity() {
		$nom = $this->getNominativeEntity();
		$this->qdt->resetRootEntity($nom);
	}
	
	public function close() {
		unset($this->nom);
		unset($this->cache);
		unset($this->db);
		unset($this->entityTypes);
		unset($this->entityHashes);
		unset($this->entities);
		unset($this->es);
		unset($this->rejected);

		if (isset($this->qdt)) return;

		if (isset($this->qdt) && is_object($this->qdt)) {
			$this->qdt->close();
		}
		unset($this->qdt);
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

	public function getNominativeEntity() {
		if ($this->nom !== null) return $this->nom;
		if (!$this->entities) return null;
		$bestPos = count(EntityCore::$nominativeEntityTypes);
		$browserPos = -1;

		$nom = null;
		$eCount = count($this->entities);
		$maxImportance = 0;
		$bestESig = null;
		for ($i = 0; $i < $eCount; $i++) {
			$eSig  = $this->es[$i];
			if ($eSig->importance === 0) continue;
			if ($eSig->importance > $maxImportance) {
				$maxImportance = $eSig->importance;
				$bestESig = $eSig;
			}
		}
		if ($bestESig !== null) {
			$this->nom = $bestESig->entity;
			return $bestESig->entity;
		}

		for ($i = 0; $i < count($this->entities); $i++) {
			$entity  = $this->entities[$i];
			$entitytype = $entity->entitytype;
			if ($entitytype === "Mobile Emulator") {
				$dummy=true;
			}
			if ($entitytype === "Browser" || $entitytype === "Mobile Browser") {
				$browserPos = $i;
				continue;
			}

			$pos = array_search($entitytype,EntityCore::$nominativeEntityTypes);
			
			if ($pos !== false) {
				if ($pos < $bestPos) {
					$bestPos = $pos;
					$nom = $entity;
				}
			}
		}
		
		if ($browserPos > -1) {
			$userAgentsAsBrowsers = DetectRight::$userAgentsAsBrowsers;
			if ($userAgentsAsBrowsers === null) $userAgentsAsBrowsers = false;
			if ($userAgentsAsBrowsers && $nom !== null && $nom->entitytype === "UserAgent") {
				$nom = $this->entities[$browserPos];
			} else if ($userAgentsAsBrowsers && $nom === null) {
				$nom = $this->entities[$browserPos];
			}
		}

		$this->nom = $nom;
		return $nom;
	}
	
	// the next bit is an adjustment where we delete any of the added EntitySigs if they're already
	// in the appropriate entity or its contain chain. This ought to filter out much of the crap.
	// if the entities are already in the chain, leave them for the version number.
	// it's been neuterered for the time being since it's a bit of a corner case catcher.
	public function filter() {
	/*	foreach ($this->es as $key=>$es) {
			$entity = $es->getEntity();
			$containsArray = $entity->getContains();
			foreach ($containsArray as $contains) {				
				if ($contains->id() === $entity->id()) {
					unset($this->es[$key]);
					continue;
				}
			}
		}*/
	}

	public function addEntityContains() {
		// for each entity, get the contains and apply to this esc.
		$entities = $this->entities;
		$keys = array_keys($entities);
		foreach ($keys as $key) {
			$contains = $entities[$key]->getContains();
			foreach ($contains as $entity) {
				// check to see if we've already detected this. If that's the case, the detected component
				// is also a shipped component, and so its capabilities are already factored into the device's capabilities.
				// that means their importance is much less.
				$offset = 0;
				$descriptor = $entity->descriptor();
				if ($entity->isNominative()) {
					$offset = self::$containedNominativeComponentOffset;
				} else {
					$offset = self::$containedComponentOffset;
				}
				$remove = false;
				$tmpDescriptor = "";
				if (in_array($descriptor,$this->descriptors)) {
					$remove = true;
					$tmpDescriptor = $descriptor;
				}

				if (!$remove) {
					foreach ($this->descriptors as $tmpString) {
						if (strpos($tmpString,$descriptor.":") === 0) {
							$remove = true;
							$tmpDescriptor = $tmpString;
						}
					}
				}

				// what if this is the same entity as already here with a slightly different (but almost the same) version?
				if (!$remove) {
					foreach ($this->entities as $dkey => $e) {
						if ($e->ID === $entity->ID) {
							$remove=true;
							$tmpDescriptor = $this->descriptors[$dkey];
							if (stripos($e->majorrevision,$entity->majorrevision) === 0) {
								$offset = -5; // same entity, same revision.
							}
						}
					}
				}
				
				if ($remove) {
					$esID = array_search($tmpDescriptor,$this->descriptors);
					$this->es[$esID]->importance = $offset;
				} else {
					// special case for entities which fallback through others of their kind.
					// we change the entitytype so that they're "included", but "hidden" from the nominative
					// entity process.
					// we need to remove any ES where the descriptor is identical to this
					if ($entity->entitytype == $this->entities[$key]->entitytype) {
						$this->addEntity($entity,true,$entity->entitytype."/".$entity->ID,$offset + $this->es[$key]->importance);
					} else {
						$this->addEntity($entity,false,"",$offset); // importance offset for components.
					}
				}
			}
		}
	}
	
	public function writePointers($pointerType,$pointerString) {
		// for each entity, if worth committing to a pointer, commit, man, commit.
		if (!class_exists("PointerFull")) return;
		$nomEntity = $this->getNominativeEntity();
		
		foreach ($this->es as $es) {
			$entity = $es->getEntity();
			if (!$nomEntity->contains($entity)) {
				PointerFull::createPointer($pointerType,$pointerString,$entity->entityhash,$entity->majorrevision,$entity->minorrevision,"",1,$entity->subclass);
			}
		}
	}
	
	public function addESC($esc) {
		// hmm. We need to add all the entities in here
		if (!is_object($esc)) return;
		$ess = $esc->es;
		if (!is_array($ess)) return;
		foreach ($ess as $es) {
			DetectRight::checkPoint("Adding extra ES");
			$this->addES($es);
			DetectRight::checkPoint("Added extra ES");
		}
		DetectRight::checkPoint("Adding ESC QDT");
		$this->qdt->addQDT($esc->qdt);
		DetectRight::checkPoint("Added ESC QDT");
	}
	
	public function getQDT() {
		// this gets a propertycollection tree from all the entities;
		// do nomentity first
		DetectRight::checkPoint("Getting QDT");
		if (self::$useCache && !DetectRight::$redetect) {
			DetectRight::checkPoint("Getting QDT from cache");
			$qdtKey = $this->qdtKey();
			$qdt = $this->cache->cache_get($qdtKey);
			if ($qdt) {
				DetectRight::checkPoint("Got QDT from cache");
				$this->qdt = $qdt;
				return $qdt;
			}
		}
		
		DetectRight::checkPoint("Getting nom");
		$qdt = new QuantumDataTree("",null);
		$nomEntityHash = "";
		$entity = $this->getNominativeEntity();
		
		if (DetectRight::$DIAG) {
			$this->qdt->printTree("Current QDT for ESC");
		}
		DetectRight::checkPoint("Getting main tree");
		if ($entity !== null) {
			$nomEntityHash = $entity->hash();
			if ($nomEntityHash !== "") {
				$entity->getQDT();
				$qdt = $entity->qdt;
				$qdt->addState(1,999,1);
			}
		} 
		
		$qdt->subsume($this->qdt);
		if (DetectRight::$DIAG) {
			$qdt->printTree("QDT After Initial Entity");
		}
		DetectRight::checkPoint("Getting contains");
		/* @var $es EntitySig */
		foreach ($this->es as $es) {
			$entity = $es->getEntity();
			DetectRight::checkPoint("Getting QDT for $entity->hash");
			if ($entity->hash() === $nomEntityHash) continue;
			// this is a QDT with only a set of packages: i.e. a raw QDT
			$entity->getQDT();
			if (DetectRight::$DIAG) {
				$entity->qdt->printTree("Entity " . $entity->ID . " QDT");
			}
			$es->qdt->addImportance($es->importance);
			$entity->qdt->addImportance($es->importance);
			if (DetectRight::$DIAG) {
				$entity->qdt->printTree("Entity " . $entity->ID . " QDT");
			}
			$qdt->subsume($es->qdt);
			$qdt->subsume($entity->qdt);
			// here we'd be adding in a path to this entity, with the entity at the heart of it.
			$qdt->addEntity($es->path,$entity,$es->importance);
			if (DetectRight::$DIAG) {
				$qdt->printTree("QDT After Entity " . $entity->ID . " QDT");			
			}
		}
		$this->qdt = $qdt;
		if (DetectRight::$DIAG) $qdt->printTree("Final QDT");
		if (self::$useCache && !DetectRight::$redetect) {
			$this->cache->cache_set($qdtKey,$qdt,self::$cache_timeout);
		}
		return $qdt;
	}
	//Proxy:Generic:Proxy::{proxy}
	//Organisation:Telco:(VendorID/(*3}{|RimVendors})

	public function addDescriptor($descriptor,$sig="") {
		$descriptor = explode(":",$descriptor);
		$es = new EntitySig($descriptor,$sig);
		$this->addES($es);
	}
	
	public function addEntity(EntityCore $entity,$force = false,$entitytype = "",$importanceOffset = 0) {
		if ($entity === null) return;
		$es = new EntitySig(
			array(
				DRFunctionsCore::nn($entity->entitytype),
				DRFunctionsCore::nn($entity->category),
				DRFunctionsCore::nn($entity->description),
				DRFunctionsCore::nn($entity->subclass),
				DRFunctionsCore::nn($entity->majorrevision),
				DRFunctionsCore::nn($entity->minorrevision),
				DRFunctionsCore::nn($entity->build),
				DRFunctionsCore::nn($entity->entitytype)
			)
		);
		$es->entity = $entity;
		if (!$entitytype) $entitytype = $entity->entitytype;
		$es->importance =  $importanceOffset;
		$this->addES($es,$force,$entitytype);
	}
	public function qdtKey() {
		DetectRight::checkPoint("Generating QDT Key");
		$strArray = array();
		foreach ($this->es as $es) {
			$strArray[] = $es->entity->descriptor()."_".$es->importance;
		}
		sort($strArray);
		$key = md5(implode("_",$strArray));
		return DetectRight::cacheKey($key);
	}
	
	public function addES(EntitySig $es, $force = false, $entitytype = "") {
		// order is extremely important in an EntitySigCollection. The most important components are at the top.
		DetectRight::checkPoint("Adding ES");
		$emptyHash = "4501c091b0366d76ea3218b6cfdd8097"; // MD5(emptyhash)
		if (DRFunctionsCore::isEmptyStr($entitytype)) {
			$entitytype = $es->getEntityType();
		}
		$this->qdt->addQDT($es->qdt);
		if ($entitytype === "") {
			return;
		}
		$doBool = false;
		if (!in_array($entitytype,$this->entityTypes)) {
			$doBool = true;
		}

		$es->fillEntity();
		$entity = $es->entity;
		if (!is_object($entity)) {
			DetectRight::checkPoint("Entity isn't an object");
			return;
		}

		if (!$this->allowNominativeEntities && $entity->isNominative()) {
			return; // discard
		}

		// Following code corrects for entity types which override other entity types: in this case, Browsers and Mobile Browsers
		// are mutually exclusive, for instance.
		if ($doBool) {
			for ($r = 0; $r < count($this->entities); $r++) {
				if ($entity->entitytype === $this->entities[$r]->entitytype) {
					$doBool = false;
					break;
				}
			}
		}
		$descriptor = $entity->descriptor(); // this might be different from the descriptor we started with.
		if (in_array($descriptor,$this->descriptors)) {
			DetectRight::checkPoint("Already got object");
			return;
		}
		if (!$doBool && !$force) {
			if ($entity !== null) {
				switch ($entity->exclusivity) {
					case 1:
						// same entitytype allowed, but only one of each entity
						if (!in_array($entity->hash,$this->entityHashes)) {
							$doBool = true;
						} else {
							// we do nothing for now. This entity is already here, and it will be rejected. Aha!
						}
						break;
					case 0:
						// unlimited numbers of this entitytype allowed, just not same version
						if (!in_array($entity->hash,$this->entityHashes)) {
							$doBool = true;
						} else {
							// we do nothing for now. This entity is already here, and it will be rejected. Aha!
							// true unless we have exactly the same major version, subclass or build.
							// what if we have an entity with no majorrevision, subclass or build but more specific information
							// here? Do we integrate it?
							$doBool = true;
							foreach ($this->entities as $key=>$testEntity) {
								if ($entity->hash !== $testEntity->hash) continue;
								$evHash = md5($entity->subclass.":".$entity->majorrevision.":".$entity->build);
								$testHash = md5($testEntity->subclass.":".$testEntity->majorrevision.":".$testEntity->build);
								if ($testHash === $emptyHash && $evHash !== $emptyHash) {
									// special case: copy version numbering into a "superior" entity
									$this->entities[$key] = $entity;
									$doBool = false;
									break;
								}
								if ($evHash === $testHash) {
									$doBool = false;
									break;
								}
							}
						}
						break;
					case 2:
						// new code needed here for the case where a contains is giving us more version information than 
						// we're being given by the useragent. PHP FIX
						// this should only happen for contains ES, not detection ES.
						// this problem will probably disappear when we short circuit the SIG detections.
						$doBool = false; // not a new add. But we need to check something...
						if (DRFunctionsCore::isEmptyStr($es->sig) && in_array($entity->hash,$this->entityHashes)) {
							foreach ($this->entities as $eKey=>$cEntity)  {
								if ($cEntity === null) continue;
								if ($cEntity->hash === $entity->hash) {
									// we're copying over a revision numbers from the contained entity and then altering the detection to suit.
									// detected build and connection
									$oldDescriptor = $cEntity->descriptor();
									$changed=false;
									if (!DRFunctionsCore::isEmptyStr($entity->build) && DRFunctionsCore::isEmptyStr($cEntity->build)) {
										$this->entities[$eKey]->build = $entity->build;
										$changed=true;
									}
									
									if (!DRFunctionsCore::isEmptyStr($entity->subclass) && DRFunctionsCore::isEmptyStr($cEntity->subclass)) {
										$this->entities[$eKey]->subclass = $entity->subclass;
										$changed=true;
									}
									
									if (!DRFunctionsCore::isEmptyStr($entity->connection) && DRFunctionsCore::isEmptyStr($cEntity->connection)) {
										$this->entities[$eKey]->connection = $entity->connection;
										$changed=true;
									}

									if (DRFunctionsCore::isEmptyStr($cEntity->majorrevision) && !DRFunctionsCore::isEmptyStr($entity->majorrevision)) {
										$this->entities[$eKey]->majorrevision = $entity->majorrevision;
										$changed=true;
									}
									
									if ($changed) {
										$sKey = array_search($oldDescriptor,$this->descriptors);
										if ($sKey !== false && $sKey !== null && $sKey !== "null") {
											$this->descriptors[$sKey] = $entity->descriptor();
										}
										$this->es[] = $es;
									}
								}
							}
						} elseif (DRFunctionsCore::isEmptyStr($es->sig)) {
							foreach ($this->es as $key=>&$ces) {
								if ($ces->entity->entitytype === $es->entity->entitytype && $es->importance > $ces->importance) {
									// must... replace...
									$this->descriptors[$key] = $es->entity->descriptor();
									$this->entities[$key] = null;
									$this->entities[$key] = $es->entity;
									$this->entityHashes[$key] = $es->entity->hash;
									$this->es[$key] = null;
									$this->es[$key] = $es;
									$doBool = false; // already done.
								}
							}
						}
						break;
					default:
						$doBool = false;
				}
			}
		}
		
		if ($doBool || $force) {
			DetectRight::checkPoint("Adding");
			$this->es[] = $es;
			$this->entities[] = $entity;
			$this->entityHashes[] = $entity->hash;
			$this->descriptors[count($this->es) - 1] = $entity->descriptor();
			if (!in_array($entitytype,$this->entityTypes)) {
				$this->entityTypes[] = $entitytype;
				$this->entityTypesString .= "^";
				$this->entityTypesString .= $entitytype;
				$this->entityTypesString .= "^";
			}
		} else {
			DetectRight::checkPoint("Rejecting");
			$this->rejected[] = $es;
		}
		DetectRight::checkPoint("Leaving addES");
	}
	
	// filters down the Sigs to a final manifest
	public function getEntities() {
		// build tree
		return $this->entities; // array of entities
	}
	
	public function getDescriptors() {
		$output = array();
		foreach ($this->es as $es) {
			$output[] = $es->descriptor;
		}
		return $output;
	}
	
	public function getDescriptorTree() {
		$output = array();
		foreach ($this->es as $es) {
			$et = $es->getEntityType();
			if (!isset($output[$et])) $output[$et] = array();
			$output[$et][] = $es->descriptor;
		}		
	}
	
	public function getEntityTree() {
		$output = array();
		foreach ($this->es as $es) {
			$et = $es->getEntityType();
			if (!isset($output[$et])) $output[$et] = array();
			$output[$et][] = $es->entity;
		}		
		return $output;
	}
	
	public function getManufacturer() {
		$nomEntity = $this->getNominativeEntity();
		if (!is_object($nomEntity)) return "";
		return $nomEntity->category;
	}
	
	public function getModel() {
		$nomEntity = $this->getNominativeEntity();
		if (!is_object($nomEntity))	 return "";
		return $nomEntity->description;
	}
	
	function process($priorityIdentifiers=array()) {
		/* @var $qdt QuantumDataTree */
		$qdt = $this->getQDT($priorityIdentifiers);
		$qdt->processPackages();
		return $qdt;
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
	public function getExportProfile($schema)  {
		//if ($dbLink->params) $profile['dbconn'] = $dbLink->params;

		DetectRight::checkPoint("Generating profile");
		$profile = SchemaPropertyCore::export($this->qdt,$schema);
		DetectRight::checkPoint("Generated Profile");
		$profile['customised'] = 0;

		unset($profile['contains']);
		$nomEntity = $this->getNominativeEntity();
		// the below is a hack and probably isn't necessary any more.
		if (is_object($nomEntity)) {
			if (DRFunctionsCore::in("W3C",$schema)) {
				$profile['vendor'] =  $nomEntity->category;
				$profile['model'] = $nomEntity->description;
			}
			if (DRFunctionsCore::in("WURFL",$schema)) {
				$profile['brand_name'] = $nomEntity->category;
				$profile['model_name'] = $nomEntity->description;
			}

			if (DRFunctionsCore::in("UAProfile",$schema)) {
				$profile['vendor'] = $nomEntity->category;
				$profile['model'] = $nomEntity->description;
			}

			if (DRFunctionsCore::in("DR",$schema)) {
				$profile['manufacturer'] = $nomEntity->category;
				$profile['model'] = $nomEntity->description;
			}

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
			$profile['deviceid'] = $nomEntity->hash;
			$profile["internalid"] = $nomEntity->ID;
			$profile['id'] = $nomEntity->hash;
			$profile['devicedescriptor'] = $nomEntity->entitytype . ":" . $nomEntity->category . ":" . $nomEntity->description;
			$profile['components'] = implode(",",$this->descriptors);
			$profile['uid'] = $this->uid;
			$profile['altdescriptors'] = implode(",",$descriptors);
			$profile['altids'] = implode(",",$hashes);
			$eHashes = array();
			$eHashes = $hashes;
			//$eHashes[] = $nomEntity->hash;
			// add component overrides
			if ($eHashes) {
				$where = array("entityhash"=>array("op"=>"in","value"=>$eHashes));
				self::addOverrides($profile,$where,true); // override
			}
			
			// add entity overrides
			self::addOverrides($profile,array("entityhash"=>$nomEntity->hash),true);
			
			$where = array();
			$deviceType = $nomEntity->entitytype;
			// something about useragents as browsers here!
			$readersAsTablets = DetectRight::$readersAsTablets;
			if ($readersAsTablets === null) $readersAsTablets = false;
			if ($readersAsTablets && $nomEntity !== null && $nomEntity->entitytype === "e-Reader") {
				$deviceType="Tablet";
			}

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
			self::addOverrides($profile,$where,false);
			$where = array();
			$where['entityhash'] = "generic";
			self::addOverrides($profile,$where,false);
			
			$profile['type'] = $deviceType;
			// Java fix? Zeroes with strings?
			if (isset($et['UserAgent']) && DetectRight::$enableUserAgentEntityType === false) {
				foreach ($profile as $key=>$value) {
					if ($value) {
						if ($value == $nomEntity->category) {
							$profile[$key] = "Generic";
						} elseif ($value == $nomEntity->description) {
							$profile[$key] = $deviceType;
						}
					} 
				}
			}
		}
		return $profile;
	}
	
	static function addOverrides(&$profile,$where,$override) {
		if (DetectRight::$disableOverrides) return;
		$dbLink = &EntityCore::$dbLink;
		if (!is_object($dbLink)) $dbLink = DetectRight::$dbLink;

		if (!$where) return;
		$doHighlights = DetectRight::$overrideHighlight;

		$custom = false;
		if (is_object($dbLink) && $dbLink->dbOK) {
			$overrides = $dbLink->simpleFetch("entity_overrides",array("*"),$where);
			if (is_array($overrides)) {
				foreach ($overrides as $row) {
					if (is_array($row)) {
						$key = DRFunctionsCore::gv($row,'key');
						if (!$override && isset($profile[$key])) continue;
						$oldValue = DRFunctionsCore::gv($profile,$key);
						$value = DRFunctionsCore::gv($row,"value");
						if ($value === "true") {
							$value = '1';
						} elseif ($value === "false") {
							$value = '0';
						}
						$custom = true;
						if ($doHighlights) {
							if (!DRFunctionsCore::isEmptyStr($key) && !DRFunctionsCore::isEmptyStr($value) && $value !== $oldValue) {
								if (DRFunctionsCore::isEmptyStr($oldValue)) {
									if ($override) {
										$profile[$key] = $value."+";
									} else {
										$profile[$key] = $value."^";
									}
								} else {
									$profile[$key] = $value."!";
								}
							}
						} else {
							$profile[$key] = $value;
						}
					}
				}
			}
		}
		if ($custom) $profile['customised'] = 1;
	}	
	
	function refuseNominativeEntities() {
		$this->allowNominativeEntities = false;
	}
	
	function permitNominativeEntities() {
		$this->allowNominativeEntities = true;
	}
	
	public function has($thing) {
		// checks the beginning of each thing.
		foreach ($this->descriptors as $descriptor) {
			if (stripos($descriptor,$thing) !== false) return true;
		}
		return false;
	}
	
	public function hasNominativeEntity() {
		$nets = EntityCore::$nominativeEntityTypes;
		$diff = array_intersect($nets,$this->entityTypes);
		if ($diff) return true;
		return false;
	}

	public function addImportance($i) {
		// bumps up the relative EntitySigs in importance.
		$cnt = count($this->es);
		for ($r = 0; $r < $cnt; $r++) {
			$this->es[$r]->importance = $this->es[$r]->importance + $i;
		}
	}

	public function getEntityForEntityType($entitytype) {
		if (DRFunctionsCore::isEmpty($entitytype)) return null;
		$cnt = count($this->entities);
		for ($i = 0; $i < $cnt; $i++) {
			$entity  = $this->entities[$i];
			if ($entity->entitytype === $entitytype) {
				return $entity;
			}
		}
		return null;
	}

}