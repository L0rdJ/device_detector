<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    sig.class.php
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
2.7.0 - change to SigPartResult use
2.7.0 - made Checkpoints diag-dependent to avoid extra count() 
2.8.0 - fixed problem in last function, wasn't returning anything.
**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("Sig");
}

/**
 * This class is designed to be a kind of souped up platform independent regular expression
 * handler for user agents.
 *
 * For instance, to handle this string:
 * Browser:Browser:Android WebKit:::(version{d}{3}):(version{d}{*})::(Mobile Safari{d}{*})
 * or this:
 * where the resulting output is a descriptor (if it matches) or nothing (if it doesn't).
 */
Class Sig {	
	static $cacheLink;
	//protected $cache;
		
	public $sigString;
	public $header;
	private $descriptorParts = array();
	private $entity = array(); // this will become a descriptor
	public $path = "";
	public $sigArray = array();
	public $propSigArray = array();
	private $propertySig;
	private $contains = array();
	private $properties = array();
	private $importance=0;
	
	public function __construct($string) {
		// create a new sig and parse it. First SIG generates the read process.
		//$this->cacheDB();
		if (!is_array($string)) {
			$this->sigString = $string;
			$this->parse();
		} else {
			$sp = DRFunctionsCore::gv($string,"sig");
			if ($sp === null) return;
			$this->sigString = $sp;
			$this->parse();
			$this->header = DRFunctionsCore::gv($string,"header");
			$this->path = DRFunctionsCore::gv($string,"path");
			$importance = DRFunctionsCore::gv($string,"importance",50);
			try {
				$importance = intval($importance);
			} catch (Exception $e) {

			}
			$this->importance = $importance;
		}
		
		if ($this->propertySig !== null) {
			if (strpos($this->propertySig,"^") !== false) {
				$this->propSigArray = explode("^",$this->propertySig);
			} else {
				$this->propSigArray = array($this->propertySig);
			}
		}
	}
	
	public function __destruct() {
		foreach (array_keys($this->contains) as $key) {
			$this->contains[$key] = null;
		}
		unset($this->contains);
		
		foreach (array_keys($this->properties) as $key) {
			$this->contains[$key] = null;
		}
		unset($this->contains);

	}
	
	public function parse() {
		$tmp = explode("//",$this->sigString);
		// properties
		if (count($tmp) > 1) {
			$this->propertySig = $tmp[1];
		}
		// entity descriptor bits
		$array = explode(":",$tmp[0]);
		foreach ($array as $string) {
			$string = DetectRight::unescapeDescriptor($string);
			$sig = new SigPart($string);
			$this->sigArray[] = $sig;
		}
	}
	
	public function getES($string) {
		$diag = DetectRight::$DIAG;
		$output = array();
		if ($diag) DetectRight::checkPoint("Trying $this->sigString");
		
		$properties = array();
		foreach ($this->sigArray as $sig) {
			//if ($diag) DetectRight::checkPoint("Doing sig part $key");
			$spResult = $sig->process($string);
			$result = $spResult->result;
			if ($result === null) return null;
			$found = $spResult->found;
			$spProperties = $spResult->properties;
			//if ($diag) DetectRight::checkPoint("Done sig part $key");
			if (!$found) {
				if ($diag) {
					DetectRight::checkPoint("Rejected $this->sigString");
				}
				return null;
			}
			
			$output[] = $result;
			
			
			if ($spProperties) {
				if ($diag) DetectRight::checkPoint("Merging... ".count($this->properties));
				$properties = array_merge($properties,$spProperties);
				if ($diag) DetectRight::checkPoint("Merged");
			}
			//if ($diag) DetectRight::checkPoint("Checked for properties");
		}
		
		// now look at the propertySig and fill that		
		if ($diag) DetectRight::checkPoint("Doing propSigArray count ".$this->propSigArray);
		foreach ($this->propSigArray as $propSig) {
			$sigP = new SigPart($propSig);
			$propertyR = $sigP->process($string);
			$property = $propertyR->result;
			if ($property !== null && $property !== "") {
				$properties[] = $this->path."//".$property;
			}
		}
		
		if ($diag) DetectRight::checkPoint("Doing new Entity Sig");
		$es = new EntitySig($output,$this->sigString,$this->path);
		$es->importance = $this->importance;
		if ($this->properties) {
			if ($diag) DetectRight::checkPoint("Adding properties");
			$es->addProperties($properties);
		}
		return $es;
	}
		
	// Stub facility
	public static function detect($string,$header="",$path="") {	
		return DetectorCore::detect($string,$header,$path);
	}
}