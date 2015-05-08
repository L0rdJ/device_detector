<?php
// v 2.8.0 - minor change so the signature filling uses self::$table instead of "sigs"
if (class_exists("DetectRight")) {
	DetectRight::registerClass("SigGroup");
}

class SigGroup {
	static public $table = "sigs";
	public $name = "";
	public $header = "";
	public $path = "";
	public $needsNominativeEntity = false;
	public $uaMatch = ""; // match string of UA to be included in this.
	public $uaMatchCS = false;
	public $uaMatchPtn = null;
	public $hasPrefix = "";
	public $shouldHavePrefix = true;
	public $entityTypesMatch = ""; // a regexp
	public $entityTypesMatchPtn = null; 
	static public $load_all = true;
	static public $dbLink;
	
	// ArrayList<Sig>
	public $sigList = array();
	
	public function __construct($header, $path = "") {
		// two things needed: (1) a definition, and (2) the sigs themselves
		if ($path === null || $header === null) return;
		$this->header = $header;
		$this->path = $path;
		$this->fill(); // fill from lookup table
		if (!DetectorCore::$streamSigGroups) {
			$this->sigList = $this->fillSigsFromTable($header,$path);
		}
	}
	
	public function name() {
		return "SigGroup_" . $this->header . "_" . $this->path;
	}
	
	public function addSig($sig) {
		$this->sigList[] = $sig;
	}
	
	public function fill() {
		$db = self::$dbLink;
		$name = $this->name();
		$sgMap = $db->getArray($name); 
		if (!$sgMap) return;
		if ($name === null) return;
		if (array_key_exists("needsNominativeEntity",$sgMap)) {
			$this->needsNominativeEntity = $sgMap["needsNominativeEntity"] ? true : false;
		}
		$this->uaMatch = DRFunctionsCore::gv($sgMap,"uaMatch");
		$this->uaMatchCS = $sgMap["uaMatchCS"] ? true : false;
		// compile patterns
		$this->entityTypesMatch = DRFunctionsCore::gv($sgMap,"entityTypesMatch"); // this is always case sensitive;
		
		$prefix = DRFunctionsCore::gv($sgMap,"hasPrefix");
		if (substr($prefix,0,1) == "!") {
			$this->shouldHavePrefix = false;
			$this->hasPrefix = substr($prefix,1);
		} else {
			$this->shouldHavePrefix = true;
			$this->hasPrefix = $prefix;
		}
	}
	
	static public function fillSigsFromTable($header,$path) {
		//ArrayList<Sig>
		$sigList = array();

		$orderClause = array();
		$orderClause["ordinal"]= "ASC";

		$db = self::$dbLink;
		if ($db === null) {
			throw new ConnectionLostException("Sigs lost DB");
		}
		$whereClause = array();
		if ($header !== "") {
			$whereClause['header'] = $header;
		}
		if ($path != "") {
			$whereClause["path"] = $path;
		}
		// Java fix?
		$sigs=$db->simpleFetch(self::$table,array("*"),$whereClause,$orderClause);
		if ($sigs === null) return null;
		foreach ($sigs as $row) {
			$sigString = DRFunctionsCore::gv($row,"sig","");
			if (strpos($sigString,"(") === false) continue; // validating a Sig
			$sig = new Sig($row);
			$sigList[] = $sig;
		}
		return $sigList;
	}
	
	public function canProcess($ua) {
		if ($this->uaMatchCS) {
			return preg_match("#".$this->uaMatch."#", $ua);
		} else {
			return preg_match("#".$this->uaMatch."#i", $ua);
		}
	}
	
	public function isValidEsc(EntitySigCollection $esc) {
		$hasNominativeEntity = $esc->hasNominativeEntity();
		if ($hasNominativeEntity != $this->needsNominativeEntity) return false;
		if ($this->hasPrefix !== "") {
			$result = $esc->has($this->hasPrefix);
			if ($result !== $this->shouldHavePrefix) return false;
		}
		$ets = $esc->entityTypesString;
		return preg_match("#".$this->entityTypesMatch."#i",$ets);
	}
	
	public function _detect($string) {
		$contains = new EntitySigCollection();
		if (empty($string)) return $contains;
		if (empty($this->sigList)) {
			$this->sigList = $this->fillSigsFromTable($this->header,$this->path);
		}
		foreach ($this->sigList as $sig) {
			//$sig = clone $masterSig;
			/* @var $sig Sig */
			// the following line invalidates any signature which doesn't have a "(" in it.
			// this is because any such sig would be applied to every single access, and there's a possibility
			// that someone entering new sigs would accidentally leave the "(" out at first, leading to widespread
			// data problems.
			// speed up process, hopefully
			//if (strpos($sig->sigString,"(") === false) continue;

			/*if ($sig->sigString === "(Windows NT 5.1)OS:Microsoft:Windows XP") {
				$dummy=true;
			}*/

			$et = $sig->sigArray[0]->startPart.$sig->sigArray[0]->endPart;
			if ($et === "Device" || $et === "Browser" || $et == "Mobile Browser" || $et === "Tablet") {
				//if (in_array($et,$contains->entityTypes)) continue;
				if (strpos($contains->entityTypesString,$et) !== false) continue;
			}
			
			//$start = DRFunctionsCore::mt();
			$es = $sig->getES($string);
			//$end = DRFunctionsCore::mt();
			/*if (($end - $start) > 0.1) {
			DetectRight::checkPoint("Sig $sig->sigString took long");
			}*/
			if ($es !== null) {
				//DetectRight::checkPoint("Detection with $sig->sigString, adding ES");
				$contains->addES($es);
				//DetectRight::checkPoint("Added ES for $sig->sigString");
			}
		}
		return $contains;
	}
}