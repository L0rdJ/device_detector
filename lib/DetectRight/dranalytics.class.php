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
Name:    dranalytics.class.php
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

if (class_exists("DRAnalytics")) {
	DetectRight::registerClass("DRAnalytics");
}

Class DRAnalytics {
		
	static function processLogFile($fn,$out,$max=0,$quiet=false) {
		if (!$fn || !$out) throw new DetectRightException("Empty in or out filename",null);
		$cnt = 0;
		$start = DRFunctionsCore::mt();
		$analysis = new DRAnalysis();
		try {
			$uas = fopen($fn,"r");

			while (!feof($uas)) {
				$cnt++;
				if ($max && $cnt > $max) break;
				$ua = fgets($uas);
				$ua = str_replace(array("\n","\r"),"",$ua);
				if (substr($ua,0,1) === "\"") $ua = substr($ua,1);
				if (substr($ua,-1,1) === "\"") $ua = substr($ua,0,-1);
				$ua = trim($ua);
				if (DRFunctionsCore::isEmptyStr($ua)) continue;
				if (!isset($ua[1])) continue; // one char UA
				$result = $analysis->doUA($ua);
			}

			$out = fopen($out,"w");
			fwrite($out,DRAnalysisResult::$resultHeader."\tcount\n");
			foreach ($analysis->uaCacheCount as $hash=>$count) {
				$result = $analysis->uaCache[$hash];
				fwrite($out,$result->_toString()."\t$count\n");
			}
			
			// write out entity table
			
			fwrite($out,implode("\n",$analysis->topUAs()));
			fwrite($out,implode("\n",$analysis->topEntities()));
			fwrite($out,implode("\n",$analysis->summary()));
			fwrite($out,implode("\n",$analysis->topETs()));
			fwrite($out,implode("\n",$analysis->topETVersions()));

		} catch (Exception $e) {
			throw new DetectRightException("Processing Error",$e);
		}

		$end = DRFunctionsCore::mt();

		$jobTime = $end - $start;
		$totalUAs = $analysis->uaCount;
		$totalUniqueUAs = count($analysis->uaCache);
		fwrite($out,"\n\n*************** Job Summary **************\n");
		if (!$quiet) echo "\n\n*************** Job Summary **************\n";
		fwrite($out,"Total Unique UAs: $totalUniqueUAs\n");
		if (!$quiet) echo "Total Unique UAs: $totalUniqueUAs\n";
		fwrite($out,"Total UAs: $totalUAs\n");
		if (!$quiet) echo "Total UAs: $totalUAs\n";
		fwrite($out,"Job time:".round($jobTime,2)." seconds\n");
		if (!$quiet) echo "Job time:".round($jobTime,2)." seconds\n";
		fwrite($out,"Average: ".$jobTime/$totalUAs." seconds\n");
		if (!$quiet) echo "Average: ".$jobTime/$totalUAs." seconds\n";

		fclose($out);
		fclose($uas);
	}
}