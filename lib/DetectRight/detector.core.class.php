<?php

if (class_exists("DetectRight")) {
	DetectRight::registerClass("DetectorCore");
}

class DetectorCore {
	// top level heuristic detector that hides SigGroups, Sigs and SigParts
	static public $dbLink;
	public static $streamSigGroups = true; // load sig groups as they're required rather than at initialization.
	public static $getSigGroupsFromLookupTable = false; // if we're keeping whole cached object versions of the SigGroups in the lookup 
	
	/**
	 * Load all maps instead of one by one
	 */
	// ConcurrentMap<String,LinkedHashMap<String,SigGroup>>
	static $sigGroupsByHeaderPath = array();

	// ConcurrentMap<String,SigGroup> sigGroupsByHeader = new ConcurrentHashMap<String,SigGroup>();
	static $sigGroupsByHeader = array();
	
	// ArrayList<String> sigGroupPathList = new ArrayList<String>();
	static $sigGroupPathList = array();
	
	/** 
	 * Load all Sigs
	 * @param flush boolean
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	static public function loadSigs_all($flush = false, $sigGroupHPList = "SigGroupHPList") {
		// do we have good data
		if (count(self::$sigGroupsByHeader) > 0 && !$flush) return;
		self::$sigGroupsByHeader = array();
		self::$sigGroupsByHeaderPath = array();
		DetectRight::checkPoint("Loading sigs");
		
		// populate the SigGroups with existing lists first. e.g. HTTP_USER_AGENT/iOS,HTTP_USER_AGENT/Android,HTTP_USER_AGENT/MSIE
		// the sig group definitions are also in lookup. IF streaming is on, then create the entries but with null.
		// Java fix? .NET Fix?
		$sigGroupHPList = self::$dbLink->getArray($sigGroupHPList);
		if (count($sigGroupHPList) == 0) {
			$sigGroupHPList[] = "HTTP_USER_AGENT"; // this is an emergency measure.
		}
		
		foreach ($sigGroupHPList as $sigGroup) {
			$tmp = explode("/",$sigGroup);
			$header = $tmp[0];
			$path = "";
			if (count($tmp) > 1) $path = $tmp[1];
			
			/* @var $sg SigGroup */
			$sg = null;
			if (!self::$streamSigGroups) {
				if (self::$getSigGroupsFromLookupTable) {
					$sg = self::getSigGroupFromLookupTable($header,$path);
				}
				if ($sg === null) {
					$sg = new SigGroup($header,$path);
					/*if (self::$getSigGroupsFromLookupTable) {
						self::saveSigGroupToLookupTable($sg); // temporary line!
					}*/
				}
			}
			
			// note: At this point, sg might be null if we intend to fill it later (like in PHP).
			if (!DRFunctionsCore::isEmpty($path)) {
				self::addSigGroup($header,$path,$sg);
			} else {
				self::$sigGroupsByHeader[$header] = $sg;
			}
		}
				
		DetectRight::checkPoint("Loaded Sigs");
		return;
	}

	static public function addSigGroup($header, $path, $sg) {
		if (!array_key_exists($header,self::$sigGroupsByHeaderPath)){
			self::$sigGroupsByHeaderPath[$header] = array();
		}
		/*if (!array_key_exists($path,self::$sigGroupsByHeaderPath[$header])) {
			self::$sigGroupsByHeaderPath[$header][$path] = array();
		}*/
		self::$sigGroupsByHeaderPath[$header][$path] = $sg;
	}

	static public function getSigGroupFromLookupTable($header, $path ) {
		$dbl = self::$dbLink;
		$tmpStr = $dbl->getOption("SigGroup_" . $header . "_" . $path);
		$sg = null;
		if (!DRFunctionsCore::isEmpty($tmpStr)) {
			$sg = DRFunctionsCore::ungz($tmpStr);
		}
		return $sg;
	}
	
	static public function addOrGetSigGroup($header,$path = "") {
		// order is important in the order of sigGroups in the header path.
		/* @var $sg SigGroup */
		
		if (!DRFunctionsCore::isEmpty($path)) {
			if (!array_key_exists("header",self::$sigGroupsByHeaderPath)) self::$sigGroupsByHeaderPath[$header] = array();
			$sg = DRFunctionsCore::gv(self::$sigGroupsByHeaderPath[$header],$path);
			if ($sg !== null) return $sg;
			
			if (self::$getSigGroupsFromLookupTable) {
				$test = self::$dbLink->getOption("SigGroup_" . $header . "_" . $path);
				$sg = DRFunctionsCore::ungz($test);
			}

			if ($sg === null) {
				$sg = new SigGroup($header,$path);
			}

			if (!array_key_exists($path,self::$sigGroupsByHeaderPath[$header])) self::$sigGroupsByHeaderPath[$header][$path] = $sg;
			
		} else {
			$sg = DRFunctionsCore::gv(self::$sigGroupsByHeader,$header);
			if ($sg === null) {
				if (self::$getSigGroupsFromLookupTable) {
					$test = self::$dbLink->getOption("SigGroup_" . $header . "_" . $path);
					$sg = DRFunctionsCore::ungz($test);
				}
	
				if ($sg === null) $sg = new SigGroup($header,"");
				if (!array_key_exists($header,self::$sigGroupsByHeader)) self::$sigGroupsByHeader[$header] = $sg;
			}
		}
		return $sg;
	}
	
	static public function addSig(Sig $sig) {
		$path = $sig->path;
		$header = $sig->header;

		$sg = self::addOrGetSigGroup($header,$path);
		$sg->addSig($sig);
		
		$sg = self::addOrGetSigGroup($header);
		
	}

	/**
	 * Gets Sigs for a particular path, for example "Display//0"
	 * @param path
	 * @return LinkedHashMap<String,Object>
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	static public function getSigGroup($header, $path) {
		$sg = null;
		if (!DRFunctionsCore::isEmpty($path)) {
			$map = DRFunctionsCore::gv(self::$sigGroupsByHeaderPath,$header);
			if ($map !== null) {
				$sg = DRFunctionsCore::gv($map,$path);
			}
		} else {
			$sg = DRFunctionsCore::gv(self::$sigGroupsByHeader,$header);
			
		}
		return $sg;
	}

	static public function detect($string, $header = "", $path = "") {
		if (DRFunctionsCore::isEmpty($string)) return new EntitySigCollection();
		$pathEmpty = DRFunctionsCore::isEmpty($path);
		
		if ($pathEmpty) {
			return self::detectSH($string, $header);
		} else {
			return self::detectSHP($string, $header, $path);
		}
	}
	/**
	 * Start the detection process with Sigs for a particular header, for example, HTTP_USER_AGENT
	 * @param string
	 * @param header
	 * @return EntitySigCollection
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	private static function detectSH($string,$header) {
		// this detects across all SigGroups for the header, starting with specific SigGroups with paths, then graduating to the
		//return sg._detect(string);
		$test = null;
		$sg = null;

		// the following needs stuff in the database for the Sigs.
		//if (header.equals("HTTP_USER_AGENT") && DetectRight.expressMode){
		// when we get to here, we know we have a list of SigGroups in sigGroupPathList
		$sgs = DRFunctionsCore::gv(self::$sigGroupsByHeaderPath,$header);
		if ($sgs !== null) {
			// process Sig groups, such as Android, iOS and MSIE.
			foreach ($sgs as $path=>$sg) {
				if ($sg === null) {
					$sg = new SigGroup($header,$path);
					self::addSigGroup($header,$path,$sg);
				}
				if ($sg->canProcess($string)) {
					$test = $sg->_detect($string);
					if ($sg->isValidEsc($test)) {
						return $test;
					}
				}
			}
		}
		//}

		if ($test !== null) {
			$test->close();
			$test = null;
		}

		//sg = sigGroupsByHeader.get(header);
		$sg = self::addOrGetSigGroup($header);
		return $sg->_detect($string);
	}

	/**
	 * Start the detection 
	 * @param string String
	 * @param header String
	 * @param path String
	 * @return EntitySigCollection
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	private static function detectSHP($string,$header,$path) {
		if ($string === null) return new EntitySigCollection();
		if ($path === null) $path = "";
		$sg = self::getSigGroup($header,$path);
		return $sg->_detect($string);
	}
	
	public static function status() {
		// TODO Auto-generated method stub
		return null;
	}
	
	static public function saveAllSigGroups() {
		$tmp1 = self::$streamSigGroups;
		$tmp2 = self::$getSigGroupsFromLookupTable;
		self::$streamSigGroups = false;
		self::$getSigGroupsFromLookupTable = false;
		self::loadSigs_all(true);
		foreach (self::$sigGroupsByHeaderPath as $array) {
			foreach ($array as $sg) {
				self::saveSigGroupToLookupTable($sg);
			}
		}
		
		foreach (self::$sigGroupsByHeader as $sg) {
			self::saveSigGroupToLookupTable($sg);
		}
		self::$streamSigGroups = $tmp1;
		self::$getSigGroupsFromLookupTable = $tmp2;
	}
	
	static public function saveSigGroupToLookupTable($sg) {
		$dbl = self::$dbLink;
		$dbl->setOption("SigGroup_".$sg->header."_".$sg->path, DRFunctionsCore::gz($sg));
	}
}