<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @since 2.2.2
 * @version 2.2.2
 * 
 * This class is a holder for the holding of a particular analysis result from the log processing
 */
/******************************************************************************
Name:    dranalysisresult.class.php
Version: 2.2.2
Since: 2.2.2
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

if (class_exists("DRAnalysisResult")) {
	DetectRight::registerClass("DRAnalysisResult");
}

class DRAnalysisResult {
	public $hash="N/A";
	public $ua;
	public $eHash;
	public $isMobile;
	public $isTablet;
	public $deviceType;
	public $category;
	public $description;
	public $browserCategory;
	public $browserDescription;
	public $browserVersion;
	public $OSCategory;
	public $OSDescription;
	public $OSVersion;
	public $DPCategory;
	public $DPDescription;
	public $DPVersion;
	public $class;	
	static $resultHeader = "ua\tclass\tisMobile\tisTablet\tdeviceType\tcat\tdesc\tbrowserCat\tbrowserDesc\tbrowserVer\tOSCat\tOSDesc\tOSVer\tDPCat\tDPDesc\tDPVer\teHash\ttime";
	public $time;

	function _toString() {
		$array = array(
			$this->ua,
			$this->class,
			($this->isMobile ? "Yes" : "No"),
			($this->isTablet ? "Yes" : "No"),
			$this->deviceType,
			$this->category,
			$this->description,
			$this->browserCategory,
			$this->browserDescription,
			$this->browserVersion,
			$this->OSCategory,
			$this->OSDescription,
			$this->OSVersion,
			$this->DPCategory,
			$this->DPDescription,
			$this->DPVersion,
			$this->eHash,
			$this->time);
		$output = implode("\t",$array);
		return $output;
	}
}