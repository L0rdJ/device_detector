<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @since 2.2.2
 * @version 2.2.2
 * 
 * This class is a holder for a global analysis of a log file or other bulk data.
 */
/******************************************************************************
Name:    dranalysis.class.php
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

if (class_exists("DRAnalysis")) {
	DetectRight::registerClass("DRAnalysis");
}

class DRAnalysis {
	public $mobileUACount = 0;
	public $tabletUACount = 0;
	public $desktopUACount = 0;
	public $deviceTypeUACounts = array();
	public $uaCount = 0;
	public $avgTime = 0;
	public $uaCache = array();	
	public $uaCacheCount = array();
	public $entityCount = array();
	public $etCounts = array("OS"=>array(),"Browser"=>array(),"Developer Platform"=>array());
	
	public function countResult($result) {
		if ($result->deviceType === "Tablet") {
			$dummy=true;
		}
		if (!isset($this->uaCache[$result->hash])) {
			$this->uaCache[$result->hash] = $result;
			$this->uaCacheCount[$result->hash] = 1;
		} else {
			$this->uaCacheCount[$result->hash]++;
		}
		
		if (!isset($this->entityCount[$result->eHash])) {
			$this->entityCount[$result->eHash] = 1;
		} else {
			$this->entityCount[$result->eHash]++;
		}
		
		if (!isset($this->deviceTypeUACounts[$result->deviceType])) {
			$this->deviceTypeUACounts[$result->deviceType] = 1;
		} else {
			$this->deviceTypeUACounts[$result->deviceType]++;
		}
		$this->uaCount = $this->uaCount + 1;
		switch ($result->class) {
			case 'Desktop':
				$this->desktopUACount = $this->desktopUACount + 1;
				break;
			case 'Tablet':
				$this->tabletUACount = $this->tabletUACount + 1;
				break;
			case 'Mobile':
				$this->mobileUACount = $this->mobileUACount + 1;
				break;
		}
		
		$this->countETVersion("Browser",$result->browserDescription,$result->browserVersion);
		$this->countETVersion("OS",$result->OSDescription,$result->OSVersion);
		$this->countETVersion("Developer Platform",$result->DPDescription,$result->DPVersion);
	}

	public function countETVersion($key,$desc,$version) {
		if (!isset($this->etCounts[$key])) {
			$this->etCounts[$key] = array();
		}
		if (!isset($this->etCounts[$key][$desc])) {
			$this->etCounts[$key][$desc] = array("count"=>0);
		}
		$this->etCounts[$key][$desc]['count']++;
		if (!DRFunctionsCore::isEmptyStr($version)) {
			if (!isset($this->etCounts[$key][$desc][$version])) {
				$this->etCounts[$key][$desc][$version] = 0;
			}
			$this->etCounts[$key][$desc][$version]++;
		}
	}

	public function doUA($ua) {
		$uaMD5 = md5($ua);
		$result = DRFunctionsCore::gv($this->uaCache,$uaMD5);
		if (!$result) {
			$start = DRFunctionsCore::mt();
			$result = new DRAnalysisResult();
			$result->hash = $uaMD5;
			$result->ua = $ua;
			$headers = new HTTPHeadersCore(array("HTTP_USER_AGENT"=>$ua));
			DetectRight::detect($headers);
			$esc = DetectRight::getLastDetection();
			$nom = $esc->getNominativeEntity();
			if ($nom && $nom->entitytype !== "UserAgent") {
				// something to count
				$result->eHash = $nom->hash;
				$result->isMobile = $nom->isMobile();
				$result->isTablet = ($nom->entitytype === "Tablet" || $nom->entitytype === "e-Reader" || $nom->entitytype === "Slate");
				if ($result->isTablet) {
					$result->class = "Tablet";
				} elseif ($result->isMobile) {
					$result->class = "Mobile";
				} else {
					$result->class = "Desktop";
				}
				$result->deviceType = $nom->entitytype;
				$result->category = $nom->category;
				$result->description = $nom->description;
			} else {
				$result->class = "Desktop";
				$result->eHash = "N/A";
				$importance = EntityCore::uaImportance($ua,$esc->entities);
				if ($importance < 0) {
					$result->isMobile = false;
				} else {
					$result->isMobile = true;
				}
				$result->isTablet = false;
				$result->deviceType ="Desktop";
				$result->category="Generic";
				if (stripos($ua,"macintosh") !== false) {
					$result->description = "Mac";
				} else {
					$result->description = "PC";
				}
				
			}
			
			$contains = $esc->getEntities();
			$browser = null;
			$os = null;
			$dp = null;
			
			foreach ($contains as $contain) {
				switch ($contain->entitytype) {
					case 'Browser':
						if (!$browser) $browser = $contain;
						break;	
					case 'Mobile Browser':
						$browser = $contain;
						break;
					case 'OS':
						if (!$os) $os = $contain;
						break;
					case 'Developer Platform':
						if (!$dp) $dp = $contain;			
						break;
				}
			}
			
			if ($os) {
				$result->OSCategory = $os->category;
				$result->OSDescription = $os->description;
				$osVersion = $os->majorrevision;
				$tmp = explode(".",$osVersion);
				$result->OSVersion = $tmp[0];
				if (isset($tmp[1]) && !DRFunctionsCore::isEmptyStr($tmp[1])) {
					$result->OSVersion .= ".".substr($tmp[1],0,1);
				} 
			}
			
			if ($dp) {
				$result->DPCategory = $dp->category;
				$result->DPDescription = $dp->description;
				$DPVersion = $dp->majorrevision;
				$tmp = explode(".",$DPVersion);
				$result->DPVersion = $tmp[0];
				if (isset($tmp[1]) && !DRFunctionsCore::isEmptyStr($tmp[1])) {
					$result->DPVersion .= ".".substr($tmp[1],0,1);
				} 
			}
			
			if ($browser) {
				$result->browserCategory = $browser->category;
				$result->browserDescription = $browser->description;
				$browserVersion = $browser->majorrevision;
				$tmp = explode(".",$browserVersion);
				$result->browserVersion = $tmp[0];	
			}

			$esc->close();
			DetectRight::clear();
			$end = DRFunctionsCore::mt();
			$result->time = ($end - $start);
			$this->uaCache[$uaMD5] = $result;
			$this->uaCacheCount[$uaMD5] = 0; // this will be counted later
		}
		$this->countResult($result);
		return $result;
	}
	
	function topETs() {
		$output = array();
		foreach ($this->etCounts as $et=>$array) {
			$tmp = array();
			$total = 0;
			$output[] = "\n\n*************** Top $et"."s"." **************\n";
			foreach ($array as $etDesc=>$data) {
				$cnt = $data['count'];
				$tmp[$etDesc] = $cnt;
				$total = $total + $cnt;
			}
			arsort($tmp);
			foreach ($tmp as $desc=>$count) {
				if (!$desc) $desc = "N/A";
				$pct = round(($count / $total) * 100.00,2);
				$output[] = "$pct"."% ($count) - $desc";
			}
		}
		return $output;
	}
	
	function topETVersions() {
		$output = array();
		foreach ($this->etCounts as $et=>$array) {
			$tmp = array();
			$total = 0;
			$output[] = "\n\n*************** Top $et versions"." **************\n";
			foreach ($array as $etDesc=>$data) {
				$cnt = $data['count'];
				unset($data['count']);
				if (count($data) == 0) {
					$tmp[$etDesc] = $cnt;
					$total = $total + $cnt;
				} else {
					foreach ($data as $etDescVersion=>$vCnt) {
						$tmp[$etDesc." ".$etDescVersion] = $vCnt;
						$total = $total + $vCnt;
					}
				}
			}
			arsort($tmp);
			foreach ($tmp as $desc=>$count) {
				if (!$desc) $desc = "N/A";
				$pct = round(($count / $total) * 100.00,2);
				$output[] = "$pct"."% ($count) - $desc";
			}
		}
		return $output;		
	}
	
	function topEntities($topNum=20) {
		// echoes a summary of what's been doing on here
		$e = $this->entityCount;
		//$sum = array_sum($e);
		arsort($e);
		$e = array_slice($e,0,$topNum,true);
		$e = array_keys($e);
		$output = array("\n\n*************** Top $topNum Entities **************\n");
		foreach ($e as $ehash) {
			$cnt = $this->entityCount[$ehash];
			$pct = round((float)$cnt/(float)$this->uaCount*(float)100,2);
			if ($ehash === "N/A") {
				$output[] = "$pct"."% ($cnt) - Generic Desktop";
			} else {
				$entity = EntityCore::getEntityFromHash($ehash);
				$output[] = $pct."% ($cnt) - ".$entity->category." ".$entity->description." (".$entity->entitytype.")";
			}
		}
		return $output;
	}
	
	function topUAs($topNum = 100) {
		$e = $this->uaCacheCount;
		//$sum = array_sum($e);
		arsort($e);
		$e = array_slice($e,0,$topNum,true);
		$e = array_keys($e);
		$output = array("\n\n*************** Top $topNum UserAgents **************\n");
		foreach ($e as $uaHash) {
			$cnt = $this->uaCacheCount[$uaHash];
			//$pct = round((float)$cnt/(float)$this->uaCount*100.00,2);
			$pct = (float)$cnt/(float)$this->uaCount*100.00;
			$result = $this->uaCache[$uaHash];
			$ua = $result->ua;
			$output[] = $pct."% - ".$ua;
		}
		return $output;		
	}
	
	function summary() {
		$output = array("\n\n*************** Device Type Summary **************\n");
		$output[] = "deviceType\tcount\tpercentage";
		$cnt = $this->mobileUACount;
		$pct = round((float)$cnt/(float)$this->uaCount*100.00,2);
		$output[] = "Mobile\t$cnt\t$pct"."%";
		$cnt = $this->tabletUACount;
		$pct = round((float)$cnt/(float)$this->uaCount*100.00,2);
		$output[] = "Tablet\t$cnt\t$pct"."%";
		$cnt = $this->uaCount - $this->mobileUACount - $this->tabletUACount;
		$pct = round((float)$cnt/(float)$this->uaCount*100.00,2);
		$output[] = "Desktop\t$cnt\t$pct"."%";
		$output[] = "\n\n*************** Top Entity Types **************\n";
		foreach ($this->deviceTypeUACounts as $type=>$cnt) {
			$pct = round((float)$cnt/(float)$this->uaCount*100.00,2);
			$output[] = "$type\t$cnt\t$pct"."%";
		}
		
		// average time
		return $output;
	}
}