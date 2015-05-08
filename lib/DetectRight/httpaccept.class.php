<?php
// intelligent header version of accept header
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    httpaccept.class.php
Version: 2.7.0
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
	DetectRight::registerClass("HTTPAccept");
}

Class HTTPAccept {
	
	private $accept=array();
	private $doNotAccept = array();	
	private $esc;
	private $qdt;

	private $ignore = array("*","*/*","text/html","text/plain","application/xml");
	
	public function getESC() {
		return $this->esc;
	}
	
	public function getQDT() {
		return $this->qdt;
	}
	
	public function __construct() {
		$this->esc = new EntitySigCollection();
	}
	/**
	 * Derive a PropertyCollection object from an accept string
	 *
	 * @param string $acceptString
	 * @return PropertyCollection
	 * @static 
	 * @internal 
	 * @access public
	 */
	function add($string) {	
		if (DRFunctionsCore::isEmptyStr($string)) return;
		$tmp=explode(",",$string);
		$tmp=array_unique($tmp);
		foreach ($tmp as $accept) {
			if (in_array($accept,$this->ignore)) continue; // not even worth considering
			$importance=50; // equivalent to q=0.5
			$accept=trim(strtolower(str_replace("; ",";",$accept)));
			$accept=str_replace("_","-",$accept);
			$accept=str_replace("\n","",$accept);
			$acceptArray=explode(";",$accept);
			if (in_array($acceptArray[0],$this->ignore)) continue;
			$max="";
			
			foreach ($acceptArray as $acceptBit) {
				$acceptBit = trim($acceptBit);
				if (strpos($acceptBit,"=") !== false) {
					$tmp2=explode("=",$acceptBit);
					switch ($tmp2[0]) {
						case 'q':
							try {
								$importance=(int)floor($tmp2[1]*50);
							} catch (Exception $e) {
								$importance = 50;
							}
							break;
						case 'max':
							$max=$tmp2[1];
							break;
					}
				}
			}

			if (!isset($this->accept[$importance]))  {
				$this->accept[$importance] = array();
			}
			$this->accept[$importance][] = array("value"=>$acceptArray[0],"max"=>$max,"string"=>$accept);
		}
	}
	
	function process() {

		// we can add not only file formats, but mimetypes for file formats from here
		// i.e. File Format:Image:GIF//MIME//mimetype
		// this would produce an ESC
		
		if (!is_object($this->qdt)) $this->qdt = new QuantumDataTree("",null);
		
		$accept = $this->accept;
		krsort($accept);
		
		foreach ($accept as $importance=>$mimeTypeArray) {
			$package = array();
			// tomorrow: add mime type to QDT anyway in another tree branch, with split by "/"?
			// also, remember PHP compatibility object.
			foreach ($mimeTypeArray as $array) {
				$acceptString = $array['string'];
				$mime = trim($array['value']);

				$path = "Browser//MIME";
				
				$max = DRFunctionsCore::gv($array,'max');
				$mp = array();
				$mimePackage = str_replace(array("/","."),":",$mime);
				$path .= ":".$mimePackage;
				
				$status = "1";
				if ($importance === 0) {
					$status = "0";
					$mp[] = "status->0";
					$mp[] = "importance->100"; // when this happens, the device is very sure it doesn't want something.
				} else {
					$mp[] = "status->1";
					$mp[] = "importance->$importance";
				}

				if (!DRFunctionsCore::isEmptyStr($max)) $mp[] = "capacity=size{max:$max;units:bytes;importance:$importance;flag:$status}";

				if (!isset($mp[0])) {
					$package[] = $path."//".implode('&/&',$mp);
				}
				
				// add validation
				Validator::validate("format",$acceptString,false);
				
				$valPkg = Validator::$profileChanges;
				if ($valPkg) {
					foreach ($valPkg as $str) {
						if ($str !== null) {
							if (substr($str,0,1) === "+") $str = "Browser//" + substr($str,1);
							$package[] = $str;
						}
					}
				}
				
				$esc = DetectorCore::detect($acceptString,"MIMEDATA");
				if ($esc->es) {
					foreach ($esc->es as $es) {
						if ($es == null) continue;
						// we're looking for a datapoint
						$dp = $es->descriptor;
						$dp = $es->path . $dp;
						$package[] = $dp;
					}
					continue; // don't need any more from this
				}

				
				$esc = DetectorCore::detect($acceptString,"MIMETYPE");
				if ($esc === null) continue;
				$this->qdt->addQDT($esc->qdt);
				foreach ($esc->es as $es) {
					// for each entry we need to insert an importance
					$mp = array();
					$descriptor = $es->descriptor;
					$fullPath = $es->path."//".$descriptor;
					$mimePackage = str_replace(array("/","."),":",$mime);
					$fullPath  .= "//MIME:$mimePackage";

					if ($importance === 0) {
						//$package[] = $fullPath."//importance=0$importance";
						$mp[] = "status->0";
						$mp[] = "importance->$importance";
					} else {
						$mp[] = "status->1";
						$mp[] = "importance->$importance";
						//$package[] = $mimePath."//descriptor=$mime";
						if (!DRFunctionsCore::isEmptyStr($max)) $package[] = "capacity=size{max:$max;units:bytes;importance:$importance}";
						// only record this entity if there's a chance of a meaningful set of entity profiles for it later.
						// otherwise, we're just clogging things up.
						if (!DRFunctionsCore::isEmptyStr($es->entity->ID) && $es->entity->ID > 0) $this->esc->addES($es);
					}
					if (count($mp) > 0) {
						$package[] = $fullPath."//".implode("&/&",$mp);
					}
				}
			}
			$this->qdt->addPackage($package,0,"ACCEPT");
		}
		
	}
}