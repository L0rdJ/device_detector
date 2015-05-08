<?php
/**
 * @author Chris Abbott <chris@detectright.com>
 * @package DetectRight
 */
/******************************************************************************
Name:    quantumdatatree.class.php
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
2.2.0 - minor change to binary values acquired in getDirectValueArray
2.3.0 - addImportance deals with packages yet to be processed
2.7.0 - shortcut for "isMobile"
2.7.1 - added isOK diagnostic
******************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("QuantumDataTree");
}

Class QuantumDataTree {
	// a holder for numerous PropertyCollections in a tree structure.
	static $cacheLink;
	static $dbLink;
	
	public $top; // top tree
	public $parent; // tree which created this.
	public $value; // string representation of "value" of tree.
	public $descriptor;
	public $tree;
	public $brand = array();
	
	public $entitytype;
	public $category;
	public $description;
	public $subclass;
	public $majorrevision;
	public $minorrevision;
	//public $importance=50;
	/**
	 * Status Collection to hold quantum stuff ;-)
	 *
	 * @var StateCollection
	 */
	public $sc; // holding the quantum flux :)
	public $connection;
	public $build;
	public $metadata=array();
	public $useSubtree=false;
	public $access_count = 0;	
	public $locked = false;
	/**
	 * The data go here, maaan.
	 *
	 * @var QuantumDataCollection
	 */
	public $qdc; // actual properties
	public $entityid; // entity_descriptor stuff from this gets pulled out and spread across the entire tree
	private $entity;
	private $object; // arbitrary object. Could be anything, seriously.	
	public $pkg; // storage for data which gets compiled.
	
	//private $status=1; // is this actually present? This flag means we can tell the difference between not there and missing
	//private $directHit=0; // is this node actually set by implication or direct setting?	
	public $index = array();
	
	public function status() {
		return $this->sc->getStatus();
	}
	
	public function __construct($value="",$parent=null) {
		if ($parent !== null) {
			$this->parent = &$parent;
			if ($parent->top !== null) {
				$this->top = &$parent->top;	
			} else {
				$this->top = null;
			}
		}
		$this->value = $value;
		$this->qdc = new QuantumDataCollection("AUTO");
		$this->tree = array();
		$this->pkg = array();
		$this->sc = new StateCollection();
	}
	
	public function __wakeup() {
		$this->access_count = 0;
		$this->useSubtree = 0;
		$this->relinkTree();
	}
	
	public function __sleep() {
		$ov = get_object_vars($this);
		unset($ov['entity']);
		unset($ov['object']);
		unset($ov['useSubtree']);
		unset($ov['parent']);
		unset($ov['top']);
		unset($ov['locked']);
		unset($ov['index']);
		return array_keys($ov);
	}

	public function setStatusFromString($string) {
		$this->sc->addStateFromString($string);
	}
	
	public function setStatus($status,$importance,$directHit) {
		$this->sc->addStateFromValues($status,$importance,$directHit);
	}

	public function reindex() {
		// if index is on, rebuild index tree
		// @todo
	}
	
	function setSource($source) {
		// sets the source attribute for datapoints in QDCs
		$this->qdc->setSource($source);
		$treeKeys = array_keys($this->tree);
		foreach ($treeKeys as $treeKey) {
			if (is_object($this->tree[$treeKey])) {
				$this->tree[$treeKey]->setSource($source);
			}
		}
	}

	public function insertPackage($pkg) {		
		if (!is_array($pkg)) return;
		foreach ($pkg as $pkgPart) {
			$qdtMessage = new QDTMessage($pkgPart);
			$this->deliverQDTMessage($qdtMessage);
		}
	}
	
	static public function unpackage($pkg) {
		// creates a new QDT from a package
		$qdt = new QuantumDataTree("",null);
		foreach ($pkg as $pkgPart) {
			$qdtMessage = new QDTMessage($pkgPart);
			$qdt->deliverQDTMessage($qdtMessage);
		}
		return $qdt;
	}
			
	public function addEntity($path,$entity,$importanceOffset=0) {
		$descriptor = $entity->descriptor();
		$path = $path."//".$descriptor;
		$pkg = array();
		$pkg[] = "$path//status=1";
		if ($entity->id()) {
			$pkg[] = "$path//entityid=".$entity->id();
		}
		$this->addPackage($pkg,$importanceOffset,$entity->descriptor());
	}

	public function resetRootEntity($entity) {
			if ($entity === null) return;
			if ($entity->entitytype === null) return;
			if ($entity->ID > 0) {
				$this->entityid = $entity->ID;
			}
			$this->entity = $entity;
			$descriptor = $entity->descriptor();
			$this->addNode($descriptor,$descriptor,1000,1,1);
	}

	// this adds a live object.
	// not sure about how this deals with paths.
	public function addObject($path,$object) {
		$pct = $this->addNode($path,$path);
		//$this->tree[$path]->object = $object;
		$pct->object = $object;
	}

	public function setObject($object) {
		$this->object = $object;
	}
	
	public function getObject() {
		return $this->object;
	}
	/**
	 * Clone this QDT.
	 */
	public function _clone() {
		$qdtClone = new QuantumDataTree($this->value,$this->parent);
		$qdtClone->descriptor = $this->descriptor;
		if ($this->brand !== null) {
			$qdtClone->brand = $this->brand;
		}
		$qdtClone->entitytype = $this->entitytype;
		$qdtClone->category = $this->category;
		$qdtClone->description = $this->description;
		$qdtClone->subclass = $this->subclass;
		$qdtClone->majorrevision = $this->majorrevision;
		$qdtClone->minorrevision = $this->minorrevision;
		$qdtClone->entityid = $this->entityid;
		$qdtClone->connection = $this->connection;
		$qdtClone->build = $this->build;
		$qdtClone->access_count = $this->access_count;
		if (!DRFunctionsCore::isEmpty($this->metadata)) {
			$qdtClone->metadata = $this->metadata;
		}
		$qdtClone->useSubtree = $this->useSubtree;
		$qdtClone->entity = $this->entity; // this is not deep copied, since it's not ever altered
		$qdtClone->object = $this->object;
		$qdtClone->locked = false;
		if (!DRFunctionsCore::isEmpty($this->pkg)) {
			$qdtClone->pkg = $this->pkg;
		}
		if (!DRFunctionsCore::isEmpty($this->qdc)) {
			$qdtClone->qdc = $this->qdc->_clone();
		}
		$qdtClone->sc->addSC($this->sc);
		
		if (!DRFunctionsCore::isEmpty($this->tree)) {
			foreach ($this->tree as $treeKey=>$qdt) {
				if ($qdt !== null) {
					$qdtClone->tree[$treeKey] = $qdt->_clone();
				}
			}
		}
		return $qdtClone;
	}
	
	public function lock() {
		self::applyLock(true);
	}
	
	public function unlock() {
		self::applyLock(false);
	}
	
	public function applyLock($lockType) {
		$this->locked = $lockType;
		if (is_object($this->qdc)) $this->qdc->applyLock($lockType);
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			$this->tree[$key]->applyLock($lockType);
		}
		// and children??
	}

	public function getZeroCountQDTs(&$list) {
		if ($this->access_count === 0) {
			$list[] = &$this; // take the whole damn branch!
			return true;
		}
		
		if (count($list) < 10) {
			$keys = array_keys($this->tree);
			foreach ($keys as $key) {
				$this->tree[$key]->getZeroCountQDTs($list);
			}
			return true;
		} else {
			return false;
		}		
	}
	
	public function preprune() {
		$list = array();
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			$success = $this->tree[$key]->getZeroCountQDTs($list);
			if (!$success) break;
		}
		
		for ($r = 0; $r < count($list); $r++) {
			if (is_object($list[$r])) $list[$r]->close();
			$list[$r] = null;
		}
		return true;
	}
	
	public function prune($level = 0) {
		// remove all nodes where access_count = 0
		//if ($this->pruned) echo $this->getPath()." has been pruned more than once";
		//$this->pruned = true;
		if ($this->locked) return;
		if (property_exists($this,"tree")) {
			$keys = array_keys($this->tree);
			foreach ($keys as $key) {
				$this->tree[$key]->prune($level + 1);
				if ($this->tree[$key]->isEmpty()) {
					unset($this->tree[$key]);
				}
			}
		}
		if ($this->access_count === 0) {
			$this->close();
		} else {
			$this->qdc->prune();
		}
	}
	
	
	public function packageMe($optimize=false) { 
		// just pkg this node up.
		$path = $this->getPath();
		$path = str_replace(array("\n","\t","\r"),"",$path);
		// put datapoints in
		$pkg = array();
		if (property_exists($this,"qdc") && is_object($this->qdc)) {
			$nodepkg = $this->qdc->toStringArray("",$optimize);
		} else {
			$nodepkg = array();
		}

		$values = array();
		//$values['descriptor'] = $this->descriptor;
		$values['brand'] = $this->brand;
		$values['sc'] = $this->sc;
		$values['metadata'] = $this->metadata;
		$values['useSubtree'] = $this->useSubtree;
		$values['entityid'] = $this->entityid;
		
		$do = true;
		if ($optimize) {
			$do=false;
			if ($this->access_count > 0) $do=true;	
		}

		if ($do) {
			if ($this->sc === null) {
				$do = false;
			} else {
				$state = $this->sc->getCollapsedState();
				if ($state === null) {
					$do = false;
				} else {
					$dh = $state->getDirectHit();
					if ($dh === 0) {
						// this isn't a direct hit. We shouldn't include it unless
						// this has an access count of non-zero, and
						// it has children with an access count of zero.
						// this means this node will show up later and its importance
						// set is necessarily made of its children.
						$do = true;
						foreach ($this->tree as $tree) {
							if ($tree->access_count > 0) {
								$do = false;
								break;
							}
						}
					}
				}
			}
		}

		if (count($nodepkg) > 0) $do=true; // this is an absolute.
		
		if ($do) {
			if ($optimize) {
				//$this->sc->resolve();
			}
			foreach ($values as $key=>$value) {
				if (is_object($value) && method_exists($value,"toString")) {
					$value = $value->toString();
				}
				if ($value !== null && is_scalar($value)) {
					if (is_string($value)) $value = str_replace(array("\n","\t","\r"),"",$value);
					if ($value === true) $value = "1";
					if ($value === false) $value = "0";
					if ($key === "entityid" && $value === "-1") continue;
					if ($key === "useSubtree" && $value === "0") continue;
					$nodepkg[] = $key."->".$value;
				} elseif ((array) $value === $value) {
					$assoc = null;
					foreach ($value as $tmpKey=>$tmpValue) {
						// if we're here, we have at least one row. If it's not "0"...
						if ($assoc === null) $assoc = !isset($value[0]);
						$tmpValue= str_replace(array("\n","\t","\r"),"",$tmpValue);
						if ($assoc) {
							$nodepkg[] = $key."-->".$tmpKey."^".$tmpValue;
						} else {
							$nodepkg[] = $key."-->".$tmpValue;
						}
					}
				}
			}
			// if we've never grabbed the node, we're not interested in the goods
		}

		if (isset($nodepkg[0])) {
			$node = implode("&/&",$nodepkg);
			$pkg[] = $path."//".$node;
		}

		// next line covers "property exists" and "is not null"
		if (isset($this->tree)) {
			foreach ($this->tree as $qdt) {
				$qdtpkg = $qdt->packageMe($optimize);
				foreach ($qdtpkg as $pkgString) {
					array_push($pkg,$pkgString);
				}
			}
		}
		return $pkg;
	}
	
	public function getPackages() {
		$return = $this->pkg;
		if ($this->pkg === null) $return = array();
		return $return;
	}
	
	public function addQDT(QuantumDataTree $qdt) {
		$packages = $qdt->getPackages();
		foreach ($packages as $pkg) {
			$this->pkg[] = $pkg;
		}
	}
	
	public function relinkTree() {	
		$keys = array_keys($this->tree);

		foreach ($keys as $key) {
			$qdt = &$this->tree[$key];
			$qdt->top = $this;
			$qdt->relink($this);
		}
	}

	public function relink(QuantumDataTree $parent) {
		// assumes top and parent are correct in this.
		if ($parent->top === null) {
			$this->top = $parent;
		} else {
			$this->top = $parent->top;
		}
		
		$this->parent = $parent;

		$keys = array_keys($this->tree);

		foreach ($keys as $key) {
			$qdt = &$this->tree[$key];
			$qdt->relink($this);
		}
	}
	
	public function isEmpty() {
		if ($this->locked) return false;
		if ($this->access_count > 0) return false;
		if (isset($this->tree) && $this->tree !== null && $this->treeCount() > 0) return false;
		if (isset($this->qdc) && $this->qdc !== null && $this->qdcCount() > 0) return false;
		//if (isset($this->entityid) && !is_null($this->entityid) && $this->entityid > 0) return false; // check later: might regret this!
		if (isset($this->entityid) && $this->entityid !== null && $this->entityid > 0) return false;
		return true;
	}
	
	public function subsume(QuantumDataTree &$qdt) {
		// this is the merge of two live trees: the second will be effectively destroyed!
		// any merge will leave descriptors alone at the top level, in favour of the existing ones
		// merge the node
		if ($qdt === null) return;

		if (isset($this->pkg[0])) {
			$this->processPackages();
		}

		if (isset($qdt->pkg[0])) {
			$qdt->processPackages();
		}

		if (is_array($qdt->tree)) {
			$keys = array_keys($qdt->tree);
			foreach ($keys as $key) {
				$thatQDT = &$qdt->tree[$key];
				if (isset($this->tree[$key]))  {
					$this->tree[$key]->subsume($thatQDT); // this makes a copy, essentially, by stealing the objects
					if (!$thatQDT->locked) $thatQDT->close(); // should be empty,actually.
					if (!$thatQDT->locked) $thatQDT = null;
				} else {
					$this->tree[$key] = $thatQDT; // keep this luvverly full object
				}
				if (!$qdt->locked) unset($qdt->tree[$key]);//pwned!
			}
		}

		$this->merge($qdt);
	}

	public function merge(QuantumDataTree &$qdt) {
		// merge nodes
		// if these two are not equal on value, then there's a real problem, except if they're both top level trees:
		// then we need to add the incoming descriptor as a path in "System". If we want to go around
		// merging subtrees, then fine, but you've got to clear the descriptors first
		if (DRFunctionsCore::isEmptyStr($qdt->descriptor)) {
			// do nothing.
		} else if (DRFunctionsCore::isEmptyStr($this->descriptor)) {
			// the source tree has no descriptor
			$this->descriptor = $qdt->descriptor;
			$this->addNode("System",DRFunctionsCore::nn($qdt->descriptor),999,1,1);
		} else {
			// both are full
			if ($qdt->descriptor !== $this->descriptor) {
				if ($qdt->parent === null && $this->parent === null) {
					// add qdt's descriptor as a System path. Then let the merge happen.
					// this might be duplicated during the merge, but I'd like to make sure it's there.
					$this->addNode("System",DRFunctionsCore::nn($qdt->descriptor),999,1,1);
				} else {
					// two incompatible descriptors. Later on we could improve this by searching the tree for compatible descriptors
					// but that seems unnecessary for now.
					return;
				}
			}
		}
		
		if (!is_object($this->qdc)) {
			$this->qdc = $qdt->qdc;
		} elseif (is_object($qdt->qdc)) {
			$this->qdc->addPropertyCollection($qdt->qdc);
		}

		// now we consider the various fields
		if (!$this->entity || $this->entity->ID === null) {
			if ($qdt->entity) {
				if ($qdt->entityid) {
					$this->entityid = $qdt->entityid;
				}
				$this->entity = $qdt->entity;
			}
		}
		
		if ((array) $qdt->brand === $qdt->brand && (array) $this->brand === $this->brand) {
			$this->brand = array_merge($this->brand,$qdt->brand);
		} elseif (!$this->brand) {
			$this->brand = $qdt->brand;
		}

		if (!is_object($this->object) && is_object($qdt->object)) {
			$this->object = $qdt->object;
		}
		
		/*if (!is_null($this->index) && is_array($this->index) && !is_null($qdt->index) && is_array($this->index)) {
			$hashes = array_keys($qdt->index);
			foreach ($hashes as $hash) {
				$this->index[$hash] = &$qdt->index[$hash];
			}
		}*/

		if ($this->metadata === null || (array) $this->metadata !== $this->metadata) $this->metadata = array();
		if ($qdt->metadata) {
			$this->metadata = array_merge($this->metadata,$qdt->metadata);
		}
		
		if ($this->sc !== null && $qdt->sc !== null) {
			$this->sc->addSC($qdt->sc);
		} elseif ($this->sc === null) {
			$this->sc = $qdt->sc;
		}
	}
		
	public function processPackages() {
		$totalTime = 0;
		$newTime = 0;
		$addTime = 0;
		$r = 0;
		if (!is_array($this->pkg)) {
			return;
		}
		foreach ($this->pkg as $key=>$array) {
			$importanceOffset = $array['importanceOffset'];
			$brand = $array['brand'];
			$pkg = $array['pkg'];
			foreach ($pkg as $i=>$string) {
				$qdtMessage = new QDTMessage($string,array(),$importanceOffset,$brand);
				$this->deliverQDTMessage($qdtMessage);
			}
		}
		$this->pkg = array();
	}
	
	public function addPackage($pkg,$importanceOffset=0,$brand = "") {
		$this->pkg[] = array("pkg"=>$pkg,"importanceOffset"=>$importanceOffset,"brand"=>$brand);
	}
		
	public function brand($brand) {
		// ees a merge!
		if (!in_array($brand,$this->brand)) {
			$this->brand[] = $brand;
		}
	}

	public function getQDTsWithDescriptor($descriptor) {
		// gets lists of paths?
		$qdts = array();
		if ($descriptor === null) return $qdts;
		if ($this->descriptor === $descriptor) {
			$path = $this->getPath();
			$this->top->getQDT($path); // should record access.
			$qdts[] = $path;
		}
		foreach (array_keys($this->tree) as $key) {
			$array = $this->tree[$key]->getQDTsWithDescriptor($descriptor);
			if ($array !== null && isset($array[0])) {
				foreach (array_keys($array) as $tmpKey) {
					//$array[$tmpKey]->recordAccess(); // ? is this overrecording? And does it matter? Would ++ work?
					$qdts[] = $array[$tmpKey];
				}
			}
		}
		return $qdts;
	}

	public function recordAccess() {
		// increases access_counts up the tree
		$this->access_count = $this->access_count + 1;
		if ($this->parent !== null) {
			$this->parent->recordAccess();
		}
	}
	
	public function getDescriptors($requiredStatus=1,$includeImportances=false) {
		$descriptors = array();
		if (!DRFunctionsCore::isEmptyStr($this->descriptor) && $this->sc->getStatus() === $requiredStatus) {
			if ($includeImportances) {
				$importance = $this->getImportance();
				$descriptors = array($this->descriptor."%/%".$importance);
			} else {
				$descriptors = array($this->descriptor);
			}
		}
		
		$treeKeys = array_keys($this->tree);
		foreach ($treeKeys as $treeKey) {
			$qdt = &$this->tree[$treeKey];
			$array = $qdt->getDescriptors($requiredStatus,$includeImportances); // ?does required status go here?
			if ($array !== null) {
				$qdt->access_count++;
				foreach ($array as $descriptor) {
					if (substr($descriptor,-1,1) === ":") $descriptor = substr($descriptor,0,-1);
					if (!in_array($descriptor,$descriptors)) {
						//if (strpos($this->descriptor,$descriptor) === 0) {
						if (strpos($descriptor,$this->descriptor) === 0) {
							array_push($descriptors,$descriptor);
						}
					}
				}
			}
		}
		return $descriptors;
	}
	
	// this function is for adding without input points.
	public function addValueString($string,$value,$validation_type,$source,$importance,$trust,$array) {
		$datapoint = Datapoint::newDatapoint($string);
		$datapoint->wildcard = $value;
		// any of these can be overridden by the input string
		$datapoint->type = $validation_type;
		$datapoint->source = $source;
		$datapoint->importance = $importance;
		$datapoint->trust = $trust;
		$datapoint->array = $array;
		$this->addDatapoint($datapoint);
	}
	
	function addDatapointToQDC(Datapoint $datapoint) {
		if (!is_object($this->qdc)) {
			$this->qdc = new QuantumDataCollection($datapoint->brand);
		}
		// @TODO: work out if this is necessary
		//$this->addState($datapoint->getDataStatus(), $datapoint->importance, 1);
		/*if (isEmpty($datapoint->))
		$datapoint->parse();
		$datapoint->parseValue();
		$datapoint->processValue();*/
		$this->qdc->addDatapoint($datapoint);
	}
	

	public function getData($datapoint) {		
		
		if (is_object($this->qdc)) {
			// should return all datapoints at this level, sorted.
			$datapoints = $this->qdc->getValueArrayFromExampleDP($datapoint);
		}
		if ($this->useSubtree) {
			$treeKeys = array_keys($this->tree);
			foreach ($treeKeys as $treeKey) {
				if (isset($this->tree[$treeKey])) {
					if (is_object($this->tree[$treeKey])) {
						if (strpos($this->tree[$treeKey]->descriptor,$this->descriptor) === 0) {
							$dps = $this->tree[$treeKey]->getData($datapoint);
							if ($dps) {
								$datapoints = array_merge($datapoints,$dps);
							}
						}
					}
				}
			}
		}
		return $datapoints;
	}

	public function getChildren() {
		return $this->tree;
	}
	public function getPath() {
		if (is_object($this->parent)) {
			$path = $this->parent->getPath();
		} else {
			$path = "";
		}
		if (!is_null($this->value) && is_object($this->top)) {
			if (DRFunctionsCore::in(":$this->value",$this->descriptor)) {
				$path = $path . ":". $this->value;
			} else {
				$path = $path . "//". $this->value;
			}
		}
		if (substr($path,0,2) === "//") $path = substr($path,2);
		return $path;
	}
	
	// more sophisticated function which pulls out descriptors such as "Codec" or "Codec:Audio Format" wherever they should be.
	public function fillQDTWithDescriptor(QuantumDataTree &$descriptorQDT,$descriptor) {
		// return QDT containing other QDTs hanging off the said descriptor
		// ? is this right?
		if ($this->descriptor === $descriptor) {
			$descriptorQDT->tree[$this->getPath()] = $this;
			return;
		}
		foreach ($this->tree as $qdt) {
			$qdt->fillQDTWithDescriptor($descriptorQDT,$descriptor);
		}
		return;
	}
	
	public function getDirectValue($command) {
		$result = "";
		if (DRFunctionsCore::isEmptyStr($command)) return "";
		if ($command === "status") {
			if (!is_object($this->sc)) return "";
			$result = $this->sc->getStatus();
			return $result;
		}

		$doIt = ($this->sc !== null && $this->sc->getStatus() === 1); // don't return text fields from a zero status thing.
		if ($doIt) {
			if ($command ==="entitytype") return $this->entitytype;
			if ($command ==="category") return $this->category;
			if ($command ==="description") return $this->description;
			if ($command ==="descriptor") return $this->descriptor;
			if ($command ==="subclass") return $this->subclass;
			if ($command ==="majorrevision") return $this->majorrevision;
			if ($command ==="minorrevision") return $this->minorrevision;
			if ($command ==="build") return $this->build;
			if ($command ==="connection") return $this->connection;
			if ($command ==="value") return $this->value;
		}

		if (DRFunctionsCore::isEmpty($result)) {
			if ($this->entity === null && $this->entityid !== null && $this->entityid > 0) {
				$this->entity = EntityCore::get($this->entityid);
			}
			
			$iseo = is_object($this->entity);
			$isoo = is_object($this->object);

			if ($iseo && $command === "isMobile") {
				$result =  $this->entity->isMobile();
			} else if ($iseo && property_exists($this->entity,$command)) {
				$result = $this->entity->$command;
			} elseif ($iseo && method_exists($this->entity,$command)) {
				$result = $this->entity->$command();
			} elseif ($isoo && property_exists($this->object,$command)) {
				$result = $this->object->$command;
			} elseif ($isoo && method_exists($this->object,$command)) {
				$result = $this->object->$command();
			} else {
				
			}
		}
	
		if ($doIt && DRFunctionsCore::isEmptyStr($result) && property_exists($this,$command)) {
			$result = $this->$command;
		}

		return $result;
	}
	
	public function getDirectValueArray($command,$includeRoot=false,$includeImportances = true) {
		// first, check descriptor fields
		$output = array();

		$result = "";
		if ($includeRoot === true) {
			$result = $this->getDirectValue($command);
			$emptyResult = (DRFunctionsCore::isEmptyStr($result));
			if ($result === true) $result = "1";
			if ($result === false) $result = "0";
			if (!$emptyResult && $includeImportances) {
				$output[] = $result."%/%".$this->getImportance();
			} elseif (!$emptyResult) {
				$output[] = $result;
			}
		}
		
		if (!$this->useSubtree) return $output;
		//if (count($output) > 0 && $array === false) return $output;

		$treeKeys = array_keys($this->tree);
		foreach ($treeKeys as $treeKey) {
			$qdt = &$this->tree[$treeKey];
			$qdt->useSubtree = 1;
			if (strpos($qdt->descriptor,$this->descriptor) !== false) {
				$qdt->access_count = $qdt->access_count + 1;
				$tmp = $qdt->getDirectValueArray($command,true,$includeImportances);
				foreach($tmp as $tmpString) {
					if (!in_array($tmpString,$output)) {
						array_push($output,$tmpString);
					}
				}
			}
		}
		return $output;
	}
	
	public function evaluateBool($command) {
		$comparitors = array(" contains "," in ","<>","!=",">=","<=","=","!","<",">");

		$comparitor = "";

		foreach ($comparitors as $testComparitor) {
			if (strpos($command,$testComparitor) === false) continue;
			$tmp = explode($testComparitor,$command);
			if (!isset($tmp[1])) return null;
			$queryCommand = trim($tmp[0]);
			$compareValue = trim($tmp[1]);
			$comparitor = $testComparitor;
			break;
		}

		if (DRFunctionsCore::isEmptyStr($comparitor)) return false;
		if (DRFunctionsCore::isEmptyStr($queryCommand)) return false;
		$queryValue = $this->getDirectValue($queryCommand);
		if (substr($compareValue,-1,1) === "*" && trim($comparitor) !== "in") {
			$compareValue = substr($compareValue,0,-1);
			$compareValueLen = strlen($compareValue);
			if (strlen($queryValue) > $compareValueLen) $queryValue = substr($queryValue,0,$compareValueLen);
		}

		if (DRFunctionsCore::isEmptyStr($queryValue)) return false;
		switch (trim($comparitor)) {
			case 'contains':
				$return = (stripos($queryValue,$compareValue) !== false);
				break;
			case 'in':
				$return = false;
				$compare = explode(",",$compareValue);
				foreach ($compare as $compareStr) {
					$do = false;
					if ($queryValue === $compareStr) $do=true;
					if (substr($compareStr,-1,1) === "*" && substr($compareStr,0,-1) === substr($queryValue,0,strlen($compareStr)-1)) $do=true;
					if ($do) {
						$return=true;
						break;
					}
				}
				break;
			case '<>':
			case '!=':
			case '!':
				$return = ($queryValue !== $compareValue);
				break;
			case '>':
				$return = ($queryValue > $compareValue);
				break;
			case '<':
				$return = ($queryValue < $compareValue);
				break;
			case '>=':
				$return = ($queryValue >= $compareValue);
				break;
			case '<=':
				$return = ($queryValue <= $compareValue);
				break;
			default:
				$return = ($compareValue == $queryValue);
		}
		return $return;
	}
	
	public function getBoolean($command,$mode) {
		$this->access_count++;
		$results = array();
		$boolResult = $this->evaluateBool($command);
		if (!is_null($boolResult)) {
			if ($boolResult === false && $mode == 'and') return false;
			if ($boolResult === true && $mode == 'none') return false;
			$results[] = $boolResult;
		}
		
		if ($this->useSubtree) {
			foreach ($this->tree as $qdt) {
				if (!is_null($this->descriptor) && stripos($qdt->descriptor,$this->descriptor) !== false) {
					$qdt->useSubtree=true;
					$results[] = $qdt->getBoolean($command,$mode);
				}
			}
		}
		
		switch ($mode) {
			case 'and':
				foreach ($results as $result) {
					if (!$result) return false;
				}
				return true;
				break;
			case 'or':
				foreach ($results as $result) {
					if ($result) return true;
				}
				return false;
				break;
			case 'none':
				foreach ($results as $result) {
					if ($result) return false;
				}
				return true;
				break;
		}
		return true;
	}
	
	public function getQDT($pathArray) {
		$this->access_count = $this->access_count+1;
		if ((array) $pathArray !== $pathArray) {
			if ($pathArray == "") return $this;
			if (substr($pathArray,-2,2) == "//") $pathArray = substr($pathArray,0,-2);
			$pathArray = explode("//",$pathArray);
		}
		if (!isset($pathArray[0])) return $this;
		$path = array_shift($pathArray);
		if (strpos($path,":") !== false) {
			$pathTmp = explode(":",$path);
			$path = array_shift($pathTmp);
			krsort($pathTmp);
			foreach ($pathTmp as $tmp) {
				array_unshift($pathArray,$tmp);
			}
		}
		// change in logic here: no longer looking to see if tree is an array. Assumes it is if set.
		if (isset($this->tree) && isset($this->tree[$path])) {
			return $this->tree[$path]->getQDT($pathArray);
		} 
		return null;
	}
	
	public function applyToEntity($key,$value) {
		// checks the attached entity to see
		if (DRFunctionsCore::isEmptyStr($this->entityid)) return false;
		if (!is_object($this->entity)) {
			$this->entity = EntityCore::get($this->entityid);
		}
		if (!is_object($this->entity)) return false;
		$entityClass = get_class($this->entity);
		if (property_exists($entityClass,$key)) {
			try {
				$this->entity->$key = $value;
			} catch (Exception $e) {
				// do nothing. Quietly fail.
			}
			return true;
		}
		if (method_exists($entityClass,$key)) {
			try {
				if (DRFunctionsCore::isEmpty($value)) {
					$this->entity->$key();
				} else {
					$this->entity->$key($value);
				}
			} catch (Exception $e) {
				// do nothing. Quietly fail.
			}
			return true;
		}
		return true;
	}

	public function applyToObject($key,$value) {
		// checks the attached entity to see
		if (!is_object($this->object)) return false;
		$objectClass = get_class($this->object);
		if (!property_exists($objectClass,$key)) {
			try {
				$this->object->$key = $value;
			} catch (Exception $e) {
				// do nothing
			}
			return true;
		}
		
		if (!method_exists($objectClass,$key)) {
			try {
				if (DRFunctionsCore::isEmpty($value)) {
					$this->object->$key();
				} else {
					$this->object->$key($value);
				}
			} catch (Exception $e) {
				// do nothing
			}
			return true;
		}
		return false;
	}

	public function lastState() {
		return $this->sc->getLastState();
	}
	
	public function directHit($hitStatus) {
		$this->sc->directHit($hitStatus);
	}

	public function &addNode($value,$descriptor,$importance=0,$initialStatus = 0, $directHit = 0) {
		if ($this->tree === null || !is_array($this->tree)) {
			$this->tree = array();
		}
		
		/*if ((array) $this->tree !== $this->tree) {
			$this->tree = array();
		}*/
		if ($value === null) $value = "";
		
		/*if (!DRFunctionsCore::isEmptyStr($value)) {
			$value = DetectRight::unescapeDescriptor($value);
		}*/

		if (!isset($this->tree[$value])) {
			$newQDT = new QuantumDataTree($value,$this);
			if ($this->top === null) {
				$newQDT->top = $this;
			}
			
			$newQDT->setDescriptor($descriptor);
			$this->tree[$value] = $newQDT;
			$this->tree[$value]->indexMe();
		}
		// make sure that we don't add "fake" zeros, and that zero stuff hits the mark directly.
		if (($initialStatus > 0 || $directHit > 0) && $importance > 0) {
			$this->tree[$value]->addState($initialStatus,$importance,$directHit);
		}
		return $this->tree[$value];
	}

	public function addState($status, $importance, $directHit) {
		$this->sc->addStateFromValues($status,$importance,$directHit);
	}

	public function indexMe()  {
		// note: why is this never triggered? Well, it was written but resulted in no
		// performance increase. And since it complicated stuff, it was left out.
		return;
		if (is_null($this->top) || is_object($this->top) !== null || true) return;
		$path = $this->getPath();
		$key = self::makePathHash($path);
		$this->top->index[$key] = &$this;
	}

	public function refreshDescriptor() {
		if (DRFunctionsCore::isEmptyStr($this->descriptor)) return;
		$this->setDescriptor($this->descriptor);
	}
	
	
	public function setDescriptor($descriptor,$doParents=false) {
		$this->descriptor = $descriptor;
		$descriptorParts = explode(":",$descriptor);
		$cnt = count($descriptorParts);
		if ($cnt == 0) return;
		if ($cnt > 0) $this->entitytype = DetectRight::unescapeDescriptor($descriptorParts[0]);
		if ($cnt > 1) $this->category = DetectRight::unescapeDescriptor($descriptorParts[1]);
		if ($cnt > 2) $this->description = DetectRight::unescapeDescriptor($descriptorParts[2]);
		if ($cnt > 3) $this->subclass = DetectRight::unescapeDescriptor($descriptorParts[3]);
		if ($cnt > 4) $this->majorrevision = DetectRight::unescapeDescriptor($descriptorParts[4]);
		if ($cnt > 5) $this->minorrevision = DetectRight::unescapeDescriptor($descriptorParts[5]);
		if ($cnt > 6) $this->connection = DetectRight::unescapeDescriptor($descriptorParts[6]);
		if ($cnt > 7) $this->build = DetectRight::unescapeDescriptor($descriptorParts[7]);
		
		if ($cnt == 1) return; // top level already
		
		if ($doParents && $this->parent !== null && strpos($this->parent->descriptor,":") !== false) {
			array_pop($descriptorParts);
			$this->parent->setDescriptor(implode(":",$descriptorParts));
		}
	}
	
	
	/**
	 * Makes a unique hash from the path to a nide
	 * @param String $path
	 * @return String
	 * @throws DetectRightException
	 */
	public function makePathHash($path) {
		if (is_array($path)) $path = implode("//",$path);
		$path = str_replace("://","{cdbs}",$path);
		$path = str_replace(":","//",$path);
		return md5($path);
	}


	function getImportance() {
		if (!is_null($this->sc)) {
			return $this->sc->getImportance();
		} else {
			return 0;
		}
	}

	function setImportance($importance) {
		$this->setStatus(1,$importance,1);
	}
	
	function addImportance($importance) {
		$r = 0;
		if (isset($this->pkg)) {
			while (isset($this->pkg[$r])) {
				$this->pkg[$r]['importanceOffset'] = $this->pkg[$r]['importanceOffset'] + $importance;
				$r++;
			}
		}
		if ($importance == 0) return;
		$this->sc->addImportance($importance);
		if (is_object($this->qdc)) $this->qdc->addImportance($importance);
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			$this->tree[$key]->addImportance($importance);
		}
	}
	
	public function nuke() {
		$this->qdc = null;
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			if (is_object($this->tree[$key])) {
				$this->tree[$key]->__destruct();
				$this->tree[$key] = null;
				unset($this->tree[$key]);
			}
			$this->tree = null;
		}
	}
	
	public function __destruct() {
		$this->qdc = null;
		if (empty($this->tree)) return;
		if ((array) $this->tree !== $this->tree) return;
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			$this->tree[$key] = null;
			unset($this->tree[$key]);
		}
		$this->tree = null;
	}
	
	function treeCount() {
		if (!isset($this->tree)) return 0;
		return count($this->tree);
	}
	
	function qdcCount() {
		if (!isset($this->qdc)) return 0;
		return $this->qdc->propSize();
	}

	public function resetCount($cnt = 0) {
		$this->access_count = $cnt;
		if (is_object($this->qdc)) {
			$this->qdc->resetCount($cnt);
		}
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			$this->tree[$key]->resetCount($cnt);
		}
	}
	
	public function close() {
		// kind of like a destruct, but mutual
		if ($this->locked) return;
		$this->index = array();
		unset($this->entity);
		if (isset($this->qdc) && is_object($this->qdc) && !$this->qdc->locked) $this->qdc->close();
		unset($this->qdc);
		if (!isset($this->tree)) return;
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			if (!is_null($this->tree[$key])) $this->tree[$key]->close();
			unset($this->tree[$key]);
		}
		unset($this->tree);
	}
	
	public function printTree($comment,$optimize=false) {
		echo $comment."\n";
		echo implode("\n",$this->packageMe($optimize))."\n\n\n";
	}
	
	// JAVA ADD
	public function sleep() {
		// recursive sleep
		$this->parent = null;
		$this->top = null;
		$this->entity = null;
		$this->object = null;
		$treeKeys = array_keys($this->tree);
		foreach ($treeKeys as $key) {
			$this->tree[$key]->sleep();
		}
	}
	// JAVA ADD
	public function wakeup() {
		$treeKeys = array_keys($this->tree);
		foreach ($treeKeys as $key) {
			$this->tree[$key]->wakeup();
		}
	}
	
	// PHP SPECIFIC, like the fill in Java
	static public function __set_state($array) {
		$qdt = new QuantumDataTree("",null);
		foreach ($array as $key=>$value) {
			$qdt->$key = $value;
		}
		return $qdt;
	}
	
	public function deliverQDTMessage($msg) {
		// this is equivalent to processing a bit of a package.
		// should this function infect the QDT it starts from?
		$destQDT = $this->getQDTMessageTarget($msg);
		DetectRight::checkPoint("Got QDT " . $msg->path);
		if ($destQDT === null || !is_object($destQDT)) return;
		
		$destQDT->infect($msg,false);
		
		// now we've got the delivery QDT.
		// First, create the nodes in "edit"
		$edits = $msg->getEdits();
		$meEdits = $edits[""];
		unset($edits[""]);
		
		foreach ($meEdits as $command) {
			$destQDT->applyCommand($command);
		}
		
		$nodes = array_keys($edits);
		
		// populate me first
		foreach ($nodes as $node) {
			if ($node === "") continue;
			$descriptor = $destQDT->descriptor;
			if (!$destQDT->descriptor) {
				// this really shouldn't happen.
				$descriptor = $this->value;
			}
			$destQDT->addNode($node,$descriptor.":".$node);
			$destQDT->tree[$node]->infect($msg,false);
		}

		
		foreach ($edits as $node=>$payloadArray) {
			$target = &$destQDT->tree[$node];
			
			if (!is_object($target)) {
				throw new DetectRightException("Horrible QDT fault with node $node at path ". $this->getPath());
			}
			
			foreach ($payloadArray as $command) {
				$target->applyCommand($command);
			}
		}
		
		$datapoints = $msg->getDatapoints();
		foreach ($datapoints as $datapoint) {
			if (!is_object($datapoint)) {
				throw new DetectRightException("Horrible QDT fault with adding ".$datapoint->toString()." path ". $this->getPath());
			}
			$destQDT->qdc->addDatapoint($datapoint);
		}
		
		// now for extra PS messages
		$extraMessages = $msg->getExtraMessages();
		foreach ($extraMessages as $newMSG) {
			$this->deliverQDTMessage($newMSG);
		}
		// done. Neat!
	}
	
	public function getQDTMessageTarget($msg) {
		$path = $msg->path;
		$path = str_replace("{cdbs}","://",$path);
		$pathArr = explode("//",$path);

		$rootQDT = &$this;
		// we don't retrieve from cache if dataStatus is zero since negative data makes new zero
		// nodes on the way down. Besides which, there's so few negative datapoints that this won't affect speed.
		//if (count($this->index) > 0 /*&& $this->dataStatus > 0*/) {
		if (isset($this->index[0])) {
			$testHash = self::makePathHash($path);
			if (array_key_exists($testHash,$this->index)) {
				$qdt = &$this->index[$testHash];
			}

			$notMatched = array();
			while (isset($pathArr[0])) {
				array_unshift($notMatched,array_pop($pathArr)); // remove last entry
				$testHash = self::makePathHash($pathArr);
				if (array_key_exists($testHash,$this->index))  {
					$rootQDT = &$this->index[$testHash];
					$pathArr = &$notMatched;
					break;
				}
			}

			if (!isset($pathArr[0])) {
				$pathArr = explode("//",$path);
				$rootQDT = &$this;
			}
		}

		// failed to get anything from cache
		//value = datapoint.command;
		
		$nodes = array();
		$descriptor = array();
		$descCumulative = array();
		$tmpHMS = array();
		$descString = "";
		//boolean fragmentWildcard = false;
		foreach ($pathArr as $pathFragment)  {
			if (strpos($pathFragment,":") !== false) {
				$descriptor = explode(":",$pathFragment);
				$descCumulative = array();
				foreach ($descriptor as $descFragment) {
					if ($descFragment === '*') {
						//fragmentWildcard = true;
						break;
					}
					if (strpos($descFragment,'{cdbs}') !== false) $descFragment = str_replace("{cdbs}","://",$descFragment);
					$descCumulative[] = $descFragment;
					$tmpHMS = array();
					$tmpHMS['path'] = $descFragment;
					$tmpHMS['descriptor'] = implode(":",$descCumulative);
					$nodes[] = $tmpHMS;
				}
			} else {
				if (strpos($pathFragment,'{cdbs}') !== false) $pathFragment = str_replace('{cdbs}','://',$pathFragment);
				if ($pathFragment !== null && $pathFragment !== "" && $pathFragment !== "*") {
					$tmpHMS = array();
					$tmpHMS['path'] = $pathFragment;
					$tmpHMS['descriptor'] = $pathFragment;
					$nodes[] = $tmpHMS;
				} else if ($pathFragment === "*") {
					//fragmentWildcard=true;
				}
			}
		}

		/* @var qdt QuantumDataTree */
		$qdt = &$rootQDT;
		$i = 1;
		$nodeCnt = count($nodes);		
		foreach ($nodes as $node) { 
			$descString = $node['descriptor'];
			$nodePath = $node['path'];
			$qdt->addNode($nodePath,$descString);
			if ($i <  $nodeCnt) {
				$qdt->tree[$nodePath]->infect($msg,true);
			}
			$qdt = &$qdt->tree[$nodePath];
			$i++;
		}
		return $qdt;
	}

	public function infect($msg,$treadLightly=false) {
		// infects the SC collection (or not)
		// this needs to be more intelligent
		if (!$treadLightly) {
			$this->sc->addSC($msg->sc);
		} else {
			// zero datapoints on their way somewhere should leave no trace.
			$status = $msg->sc->getStatus();
			if ($status === 1) {
				$this->sc->addSC($msg->sc,true);
			}
		}
	}
	
	public function applyCommand($key) {
		$value = "";
		
		$array = false;
		if (strpos($key,"-->") !== false) {
			$tmp = explode("-->",$key,2);
			$key = trim($tmp[0]);
			$value = trim($tmp[1]);
			$array = true;
		} elseif (strpos($key,"->") !== false) {
			$tmp = explode("->",$key,2);
			$key = trim($tmp[0]);
			$value = trim($tmp[1]);
		} elseif (strpos($key,"=") !== false) {
			$tmp = explode("=",$key,2);
			$key = trim($tmp[0]);
			$value = trim($tmp[1]);			
		}

		if ($key === "descriptor") {
			$this->setDescriptor($value,true);
		}
		
		// check if this is a valid thing in the entity
		if (property_exists($this,$key)) {
			if ((array)$this->$key !== $this->$key) $this->$key = array();
			if ($array) {
				if (strpos($value,"^") !== false) {
					$tmp = explode("^",$value);
					$arrayToUse = &$this->$key;
					$arrayToUse[$tmp[0]] = $tmp[1];
					// associative array
				} else {
					if (!in_array($value,$this->$key)) {
						array_push($this->$key,$value);
					}
				}
			} else {
				if (!DRFunctionsCore::isEmptyStr($value)) $this->$key = $value;
			}
		} elseif (!$this->applyToEntity($key,$value)) {
			$this->applyToObject($key,$value);
		} 
	}
	
	function addMetadata($key,$value,$filterByParent=false) {
		if (!$filterByParent) {
			$this->metadata[$key] = $value;
		} else {
			if ($this->parent !== null) {
				if (!$this->parent->hasMetadata($key,$value)) {
					$this->metadata[$key] = $value;
				}
			}
		}
	}
	
	function hasMetadata($key,$value) {
		if (isset($this->metadata[$key])) {
			return ($this->metadata[$key] === $value);
		}
		
		if ($this->parent !== null) {
			return $this->parent->hasMetadata($key,$value);
		}
		return false;
	}
		
	function isOK(&$errors = 0) {
		if (get_class($this->qdc) === "State") $errors = $errors + 1;
		$keys = array_keys($this->tree);
		foreach ($keys as $key) {
			if ($this->tree[$key] == null || get_class($this->tree[$key]) !== "QuantumDataTree") {
				$errors = $errors + 1;
				continue;
			}
			$this->tree[$key]->isOK($errors);
		}
	}
}