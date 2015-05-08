<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    httpheaders.core.class.php
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

2.2.0 - added destructor
2.2.1 - added array init in case of language not found
2.3.0 - added code to make sure Proxy Nominative devices aren't detected in preference to proper ones
2.3.1 - forgot to change function name from disallowNominativeEntities to refuseNominativeEntities
2.5.0 - put in the new locale thing, added rawHeader getting. Added stock device UA thing.
2.5.0 - implemented JIT gateway initialization
2.7.0 - run getAccept even in Express mode, if we've detected /ss (a screen size).
2.7.0 - fix for useragents which happen to coincide to an entity type
2.8.0 - tidied up a raw header access.
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("HTTPHeadersCore");
}

/**
 * Finally a class for handling the HTTP headers more intelligently: all header stuff goes through here.
 *
 * $headers = new HTTPHeaders($serverVars);
 * $headers->clean();
 * $headers->getRealTime();
 * $headers->getAccept();
 * // then we have lists of contains 'n real-time properties 'n everything
 */
// still to do: make sure that connection, proxy and gateway objects are connected and dealt with: still jury's out on
// whether to get QDTs from them, or attach the objects to the QDT "object".
// 2.1.2 - throw out devices with category of "generic" if deviceNotFoundBehavior = "Exception"

Class HTTPHeadersCore {
	static $localeRegEx = "/;[\\[ ]{0,3}((?:a[abefkmnrsvyz]|b[aeghimnors]|c[aehorsuvy]|d[aevz]|e[elnostu]|f[afijory]|g[adlnuv]|h[aeiortuyz]|i[adegiknostuw]|j[aiv]|k[agijklmnorsuvwy]|l[abinotuv]|m[ghiklnorsty]|n[abdeglnorvy]|o[cjmrs]|p[ailst]|qu|r[mnouw]|s[acdegiklmnoqrstuvw]|t[aeghiklnorstwy]|u[gkrz]|v[eio]|w[ao]|xh|y[io]|z[ahu])(?: ?[\\];)]|[-_](?:A[DEFGILMNOQRSTUWXZ]|B[ABDEFGHIJLMNOQRSTVWYZ]|C[ACDFGHIKLMNORSUVWXYZ]|D[EJKMOZ]|E[CEGHRST]|F[IJKMOR]|G[ABDEFGHILMNPQRSTUWY]|H[KMNRTU]|I[DELMNOQRST]|J[EMOP]|K[EGHIMNPRWYZ]|L[ABCIKRSTUVY]|M[ACDEFGHKLMNOPQRSTUVWXYZ]|N[ACEFGILOPRUZ]|OM|P[AEFGHKLMNRSTWY]|QA|R[EOSUW]|S[ABCDEGHIJKLMNORTVXYZ]|T[CDFGHJKLMNORTVWZ]|U[AGMSYZ]|V[ACEGINU]|W[FS]|Y[ET]|Z[AMW])?))/i";
	static $dbLink;
	
	static $cacheLink;
	public $cache;
	
	public $rawHeaders;
	public $connection; // HTTPConnection Object
	public $serverVars=array();
	public $proxy; // HTTPHeaders object
	public $gateway; // HTTP Headers object	
	public $esc; // an actual entity sig
	public $qdt; // collection of property collections
	public $cleaned=false;
	public $special = "";
	public $uaprof = "";
	public $ip;
	public $localIP; // for the lurking client...
	public $ua=""; // real useragent after all the cleaning is done
	public $diags; // diagnostic timings
	
	public $uid="";
	public $charset; // HTTPLocale Object
	public $language; // can use an HTTPLocale object
	public $accept; // HTTPAccept Object
	public $nominativeEntity;
	
	static $table="headers";
	
	function __construct($headers) {
		$this->ip = new IP("");
		$this->rawHeaders = $headers;
		if (count($headers) > 0) {
			$this->initializeProxy();
		}
		$this->esc = new EntitySigCollection();
		$this->qdt = new QuantumDataTree("",null);
		$this->proxyEsc = new EntitySigCollection();
		DetectRight::checkPoint("Cleaning Headers");
		$this->cleanHeaders(false);
		DetectRight::checkPoint("Getting uid");
		$this->fillUid();
	}

	function initializeProxy() {
		if ($this->proxy === null) $this->proxy = new HTTPHeadersCore(array());
	}
		
	public function __destruct() {
		if (isset($this->esc) && is_object($this->esc)) {
			$this->esc->close();
		}
		if (isset($this->qdt) && is_object($this->qdt)) {
			$this->qdt->close();
		}
	}
		
	public function __sleep() {
		$ov = get_object_vars($this);
		unset($ov['cache']);
		unset($ov['db']);
		return array_keys($ov);
	}

	public function getRaw($header) {
		return DRFunctionsCore::gv($this->rawHeaders,$header,"");
	}

	// for proxies or gateways
	function processMini($includeNominativeEntities = false) {
		if (count($this->serverVars) === 0) return;
		if (!DetectRight::$expressMode) {
			$this->getAccept();
			$this->geoSniff();
		}
		$this->deduce($includeNominativeEntities);
	}
	
	/**
	 * Deduce all the realtime variables
	 *
	 * @internal 
	 * @access public
	 */
	function process() {
		// IPs
		if (count($this->serverVars) === 0) return;
		if (!DetectRight::$expressMode) {
			$this->deduceGateway(); // note: do we ever use this information?
		}
		// first, add in the overriding browser properties. They're guaranteed to take priority.
		// Proxy processing always needs to happen.
		$this->processProxy();
		$realtimeStart=DRFunctionsCore::mt();
		/* @var $realtime PropertyCollection */
		if (!DetectRight::$expressMode) {
			$this->processCookies();
			$this->getIPs();
			$this->getCharset();
			$this->getLanguage();
			$this->geoSniff();
			$this->getConnectionProperties();
		}
		$this->getAccept();
		$this->deduceProfileStuffFrom();
		// add information about proxy
		if (!DRFunctionsCore::isEmptyStr($this->special)) {
			$es = new EntitySig(explode(":",$this->special),$this->getRaw('HTTP_USER_AGENT'));
			$this->esc->addES($es);
		}

		$this->deduce();
		
		$nom = $this->esc->getNominativeEntity();
		
		if (is_null($nom) ) {
			// add in the useragent type
			if (DetectRight::$deviceNotFoundBehavior === "Exception") {
				$this->esc->close();
				throw new DeviceNotFoundException("Device not found");
				return;
			}
			$uaDescriptor = "UserAgent:UserAgent:".DetectRight::escapeDescriptor($this->ua);
			$es = new EntitySig(EntityCore::parseEntityDescriptor($uaDescriptor),$this->getRaw('HTTP_USER_AGENT'));
			$this->esc->addES($es);
		} elseif ($nom->id() < 1 || $nom->category === "Generic") {
			if (DetectRight::$deviceNotFoundBehavior === "Exception") {
				$this->esc->close();
				throw new DeviceNotFoundException("Device not found");
				return;
			} else {
				$this->nominativeEntity = $nom;
			}
		} else {
			$this->nominativeEntity = $nom;
		}
		// see if we need to add some IPCache detail
		$this->recordHeader(); // create new instance and do analytics
		$realtimeTime=DRFunctionsCore::mt()-$realtimeStart;
		$this->diags['realtimeTime'] = $realtimeTime;
		return;
	}

	function setIP($ip) {
		if (DRFunctionsCore::isEmptyStr($ip)) return;
		$this->ip = new IP($ip);
	}
	
	function addRawHeader($key,$value) {
		$this->rawHeaders[$key] = $value;
	}
	
	function addCleanedHeader($key,$value) {
		$this->serverVars[$key]	= $value;
	}

	function getHeader($header) {
		if (isset($this->serverVars[$header]) && !DRFunctionsCore::isEmptyStr($this->serverVars[$header])) {
			return $this->serverVars[$header];
		}
		return "";
	}

	function setHeaders($serverVars) {
		$this->serverVars = $serverVars;
	}
	
	function getHeaders() {
		return $this->serverVars;	
	}

	function getHeadersRaw() {
		return $this->rawHeaders;	
	}

	function removeHeader($header) {
		unset($this->serverVars[$header]);
	}

	function addData($path,$value="",$importanceOffset=0) {
		//$package,$importanceOffset=0,$brand = ""
		if ($value) {
			$path = $path."//".$value;
		} 
		$package = array($path);
		$this->qdt->addPackage($package, $importanceOffset,"DR_Realtime");
	}
	
	function clear() {
		$this->serverVars = array();
		$this->cleaned=false;
		$this->special = "";
		$this->uaprof = "";
	}

	function normalise() {
		//@test
		// change into HTTP_USER_AGENT format from User-agent
		if (!is_array($this->serverVars) || count($this->serverVars) == 0) {
			$this->serverVars = array();
			return;
		}

		// special case: we're guessing that this doesn't need changing
		if (isset($this->serverVars['HTTP_USER_AGENT'])) return;
		if (isset($this->serverVars['HTTP_ACCEPT'])) return;

		$serverVars = array_change_key_case($this->serverVars,CASE_UPPER);

		$exclude = array("REMOTE_ADDR");

		$cleanedServerVars = array();
		foreach ($serverVars as $key=>$value) {
			$key=str_replace("-","_",$key);
			if (!in_array($key,$exclude) && substr($key,0,4) !== "HTTP") {
				$key="HTTP_".$key;
			}
			$cleanedServerVars[$key]=$value;
		}
		$this->serverVars = $cleanedServerVars;
	}

	function cleanUA() {
		$uAgent = $this->getHeader("HTTP_USER_AGENT");
		if (strpos($uAgent,"%") !== false || (strpos($uAgent,"+") !== false && strpos($uAgent," ") === false)) { 
			$uAgent = urldecode($uAgent);
		}

		if (!$uAgent) $uAgent = $this->getHeader("HTTP_USER_AGENT");
		
		if (!DetectRight::$expressMode){
			$uAgent = preg_replace("/[\x01-\x19\x7e-\xff]*/","",$uAgent);

			// this should be in the clean up
			/*if(DRFunctionsCore::in(",",$uAgent)) {
			// commas usually denote duplicate agent strings. Here we remove the shortest one.
			$tmp=explode(",",$uAgent);
			if (substr($tmp[0],0,30) == substr($tmp[1],0,30)) {
			if (strlen($tmp[0]) > strlen($tmp[1])) {
			$uAgent=$tmp[0];
			} else {
			$uAgent=$tmp[1];
			}
			} else {
			$uAgent = str_replace(","," ",$uAgent);
			}
			}*/

			if (substr($uAgent,0,3) === "*/*") $uAgent = trim(substr($uAgent,3));
			if (substr($uAgent,-3,3) === "*/*") $uAgent = trim(substr($uAgent,0,-3));
			if (!is_null(self::$dbLink)) {
				$uAgent = self::$dbLink->arrayFilter("UAClean",$uAgent);
				$uAgent = self::$dbLink->arrayFilter("Crap",$uAgent);
			}
		}
		
		
		$uAgent = $this->localeCleanQuick($uAgent);

		// the below code is a 0.1 second overhead even when we're not using it.
		/* @var Sig $sig */
		// It doesn't matter in the Java because Sigs are an app object
		/*
		$sigs = Sig::getSigsForHeader("Clean");
		foreach ($sigs as $sig) {
		$test = $sig->getString($uAgent);
		if ($sig->found === false) continue;
		$uAgent = $test;
		}*/
		//SIMBAR={492C6CE6-FADC-4927-AA23-241093A04519}
		$pos = strpos($uAgent,"SIMBAR={");
		if ($pos !== false) {
			$destString = "00000000-0000-0000-0000-000000000000";
			$startPos = $pos+8;
			$sourceString = substr($uAgent,$startPos,36/*strlen($destString)*/);
			$uAgent = str_replace($sourceString,$destString,$uAgent);
		}


		if (!DetectRight::$expressMode) {
			$r=0;
			while ( is_numeric(substr($uAgent, $r, 1) ) )  {
				$r=$r+1;
			}

			if ($r > 6) {
				// you hardly ever get 7 or more digit numbers at the end of strings.
				$uAgent = substr($uAgent,$r+1); // take the space or semicolon too.
			}

			// we should be able to do this in the sigs table
			$pos = stripos($uAgent, "UP.Link");
			if ( $pos !== false ) {
				$sigPart = new SigPart("(UP.Link{d}{*})");
				$upLinkVersion = $sigPart->process(substr($uAgent,$pos));
				if ($upLinkVersion) {
					// add a gateway object?
					//$this->deduceGateway("UP.Link/$upLinkVersion");
				}
				if ($sigPart->found) {
					$uAgent = substr($uAgent,0,$pos).$sigPart->workingString;
				}
			}

			$checkFor = array("*","/SN","SoftBank;SN","[TF");
			foreach ($checkFor as $checkString) {
				$pos = stripos($uAgent, $checkString);
				if ( $pos !== false ) {
					$sigPart = new SigPart("(".$checkString."{*})");
					$imei = $sigPart->process(substr($uAgent,$pos));
					if ($sigPart->found && strlen($imei)>7) {
						$zeroes = "";
						if (substr($imei,0,4) !== "0000" && substr($imei,0,4) !== "XXXX") {
							$this->uid = $imei;
						}
						$zeroes = str_pad($zeroes,strlen($imei),"0");
						$uAgent = substr($uAgent,0,$pos+strlen($checkString)).$zeroes.substr($uAgent,$pos + strlen($checkString) + strlen($zeroes));
					}
				}
			}

			$is930 = strpos($uAgent,"i930");
			if ($is930 !== false) {
				$tmp = explode(";",$uAgent);
				foreach ($tmp as $part) {
					$part = trim($part);
					if (substr($part,-1,1) === ")") $part = substr($part,0,-1);
					if (substr($part,-1,1) === ";") $part = substr($part,0,-1);
					if (strlen($part)==15) {
						$this->uid = $part;
						$replace = "000000000000000";
						$uAgent = str_replace($part,$replace,$uAgent);
						break;
					}
				}
			}
		}
		// get rid of foreign characters
		$this->addCleanedHeader("HTTP_USER_AGENT",trim($uAgent));
	}

	function getUAProfURL() {
		// get the UAProf URL
		$this->uaprof = self::findUAProfURL($this->serverVars);
	}

	function removeIPFromUA() {
		//LG-KP570 Teleca/WAP2.0 MIDP-2.0/CLDC-1.1(REAL IP: 201.144.221.245)
		$ua = $this->getHeader("HTTP_USER_AGENT");
		if (stripos($ua,"(REAL IP:") !== false) {
			$tmp=explode("(REAL IP:",$ua);
			$this->addCleanedHeader('HTTP_USER_AGENT',trim($tmp[0]));
			$this->setIP(trim(str_replace(")","",$tmp[1])));
		}
	}

	function recordHeader() {
		
	}
	
	function checkHideMe() {
		$ua = $this->getHeader("HTTP_USER_AGENT");
		if (stripos($ua,"HideMe.mobi Browser") !== false) {
			$this->special = "Proxy:Proxy:HideMe.mobi";
			$this->addCleanedHeader("HTTP_USER_AGENT",trim(str_replace("HideMe.mobi Browser","",$ua)));
		}
	}
	
	function commitToAnalytics() {
		
	}
	
	function checkSkyfire() {
		/**
	 * X-Skyfire-Version: 1.1.0.13052
X-Skyfire-Screen: 240,320,240,296
X-Skyfire-Phone: Mozilla/4.0 (compatible; MSIE 6.0; JavaScript; ) # touchscreen
	 */
		$version = $this->getHeader('HTTP_X_SKYFIRE_VERSION');
		if (!DRFunctionsCore::isEmptyStr($version)) {
			// this is a Skyfire browser
			$majorrevision = substr($version,0,3);
			$this->addCleanedHeader('HTTP_USER_AGENT',"Skyfire/$majorrevision ".$this->getHeader('HTTP_USER_AGENT'));
			$originalUA = $this->getHeader('HTTP_X_SKYFIRE_PHONE');
			$tmp = explode("#",$originalUA);
			$feature = trim($tmp[1]);
			if ($feature === "touchscreen") {
				$this->addData("","Input//Display//status=1",50);
			}
			//$this->addCleanedHeader('HTTP_USER_AGENT',$tmp[0]);
			//$this->cleanUA();
			//$this->proxy->addRawHeader('HTTP_ACCEPT',$this->getHeader('HTTP_ACCEPT'));

			$skyFireScreen = $this->getHeader('HTTP_X_SKYFIRE_SCREEN');
			$skyFireScreen = explode(",",$skyFireScreen);
			if (count($skyFireScreen)>1) {
				$this->addCleanedHeader('HTTP_UA_PIXELS',array_shift($skyFireScreen)."x".array_shift($skyFireScreen));
			}
			if (count($skyFireScreen)>1) {
				// browser screen size
				$this->addCleanedHeader('HTTP_X_UP_DEVCAP_SCREENPIXELS',implode(",",$skyFireScreen));
			}
			// take back the IP address so it's Skyfire's any more
			$this->rewindIP();
		}
	}

	function checkGoogleTranslator() {
		$ua = $this->getHeader("HTTP_USER_AGENT");
		if (stripos($ua,",gzip(gfe) (via translate.google.com)") !== false) {
			$this->special = "Proxy:Google:Translator";
			$this->addCleanedHeader('HTTP_USER_AGENT',str_replace(",gzip(gfe) (via translate.google.com)","",$ua));
		}
	}

	function checkGoogleAppEngine() {
		$ua = $this->getHeader("HTTP_USER_AGENT");
		$pos = stripos($ua,",gzip(gfe) AppEngine-Google;");
		if ($pos !== false) {
			$this->special = "Proxy:Google:AppEngine";
			$this->addCleanedHeader('HTTP_USER_AGENT',substr($ua,0,$pos));
		}
	}

	function checkNovarraVision() {
		$originalUA = $this->getHeader("HTTP_USER_AGENT");

		if (stripos($originalUA,"Novarra-Vision/") !== false) {
			$this->special = "Proxy:Proxy:Novarra-Vision";
			$pos = stripos($originalUA,"Novarra-Vision/");
			$majorrevision = substr($originalUA,$pos + 15);
			if (is_numeric($majorrevision)) {
				$this->special .= "::$majorrevision";
			}
			//$this->addCleanedHeader('HTTP_USER_AGENT',trim(substr($originalUA,0,$pos-1)));
			//$this->proxy->addRawHeader('HTTP_USER_AGENT',substr($originalUA,$pos));
		}
	}

	function checkszn() {
		$originalUA = $this->getHeader("HTTP_USER_AGENT");
		$pos = stripos($originalUA,"/szn-mobile-transcoder");
		if ($pos > 0) {
			$this->special = "Proxy:SZN:Mobile Transcoder";
			$this->addCleanedHeader('HTTP_USER_AGENT',trim(substr($originalUA,0,$pos)));
			$this->proxy->addRawHeader('HTTP_USER_AGENT',substr($originalUA,$pos));
		}
	}

	// perform this after Opera Mini
	function checkStockUA() {
		//#HTTP_DEVICE_STOCK_UA
		$originalUA = $this->getHeader("HTTP_DEVICE_STOCK_UA");
		if (!DRFunctionsCore::isEmptyStr($originalUA)) {
			if ($originalUA !== $this->serverVars['HTTP_USER_AGENT']) {
				// adjust for stock browser
				$this->proxy->addRawHeader('HTTP_USER_AGENT',$this->getHeader('HTTP_USER_AGENT'));
				$this->addCleanedHeader('HTTP_USER_AGENT',$originalUA);
			}
		}
	}
	
	function checkOperaMini() {
		// check Opera Mini
		$originalUA = $this->getHeader("HTTP_X_OPERAMINI_PHONE_UA");
		if (!DRFunctionsCore::isEmptyStr($originalUA)) {
			// adjust for Opera Mini
			$this->proxy->addRawHeader('HTTP_USER_AGENT',$this->getHeader('HTTP_USER_AGENT'));
			$this->addCleanedHeader('HTTP_USER_AGENT',$originalUA);
			$this->proxy->addRawHeader('HTTP_ACCEPT',$this->getHeader('HTTP_ACCEPT'));
			$this->addCleanedHeader('HTTP_ACCEPT',""); // note: this is because there's no original accept string here. The accept string there is comes from Opera Mini, not the device.
			$this->rewindIP();
			$this->special="Proxy:Opera:Opera Mini";
		}
		
		// reminder: doing this->servervars -> addcleanedheader/getheader
		$operaMiniPhone = $this->getHeader('HTTP_X_OPERAMINI_PHONE');
		if (!DRFunctionsCore::isEmptyStr($operaMiniPhone)) {
			$tmp=explode('#',$operaMiniPhone);
			$manufacturer=trim($tmp[0]);
			//if ($manufacturer === "SonyEricsson") $manufacturer = "Sony Ericsson";
			$model=trim($tmp[1]);
			if ($manufacturer && $manufacturer != "?" && $model && $model !== "?") {
				$es = new EntitySig(array("entitytype"=>"Device","category"=>DetectRight::escapeDescriptor($manufacturer),"description"=>DetectRight::escapeDescriptor($model)),"OperaMini");
				$this->esc->addES($es);
			}
		}

		$features = $this->getHeader('HTTP_X_OPERAMINI_FEATURES');
		if (!DRFunctionsCore::isEmptyStr($features)) {
			$features = explode(",",$features);
			foreach ($features as $feature) {
				$this->addData("Browser//Browser:Feature:$feature","status=1");
			}
		}
	}

	function checkGoogleWirelessTranscoder() {
		$originalUA = $this->getHeader('HTTP_X_ORIGINAL_USER_AGENT');
		if (!DRFunctionsCore::isEmptyStr($originalUA)) {
			$this->special="Proxy:Google:Wireless Transcoder";
			$this->proxy->addRawHeader('HTTP_USER_AGENT',$this->getHeader('HTTP_USER_AGENT'));
			$this->addCleanedHeader('HTTP_USER_AGENT',$originalUA);
			$this->proxy->addRawHeader('HTTP_ACCEPT',$this->getHeader('HTTP_ACCEPT'));
			$this->addCleanedHeader('HTTP_ACCEPT',"");
			$this->rewindIP();
		}
	}

	function checkGoogleProxy() {
		$originalUA = $this->getHeader("HTTP_X_MOBILE_UA");
		if (!DRFunctionsCore::isEmptyStr($originalUA)) {
			$this->proxy->addRawHeader('HTTP_USER_AGENT',$this->getHeader('HTTP_USER_AGENT'));
			$this->addCleanedHeader('HTTP_USER_AGENT',$originalUA);
			$this->proxy->addRawHeader('HTTP_ACCEPT',$this->getHeader('HTTP_ACCEPT'));
			$this->addCleanedHeader('HTTP_ACCEPT',"");
			$this->special="Proxy:Google:Google Transcoder";
			$this->rewindIP();
		}
	}

	function checkOpenWeb() {
		if (strpos($this->getHeader('HTTP_USER_AGENT'),"OpenWeb") !== false) {
			$this->special="Proxy:Openwave:Openweb";
		}
	}

	function checkNovarraProxy() {
		$originalUA = $this->getHeader('HTTP_X_DEVICE_USER_AGENT');
		if (!DRFunctionsCore::isEmptyStr($originalUA)) {
			$ua = $this->getHeader('HTTP_USER_AGENT');
			$this->proxy->addRawHeader('HTTP_USER_AGENT',trim(str_replace($originalUA,"",$ua)));
			$this->addCleanedHeader('HTTP_USER_AGENT',$originalUA);
			$this->proxy->addRawHeader('HTTP_ACCEPT',$this->getHeader('HTTP_ACCEPT'));
			$this->addCleanedHeader('HTTP_ACCEPT',$this->getHeader('HTTP_X_DEVICE_ACCEPT'));
			$this->removeHeader('HTTP_X_DEVICE_USER_AGENT');
			$this->removeHeader('HTTP_X_DEVICE_ACCEPT');
		}

		if (!DRFunctionsCore::isEmptyStr($this->getHeader('HTTP_X_NOVARRA_DEVICE_TYPE'))) {
			$this->special = "Proxy:Novarra:Web Proxy";
			$this->rewindIP();
		}

		$serverVars = $this->getHeaders();
		foreach ($serverVars as $key=>$value) {
			if (substr($key,0,14)=="HTTP_X_DEVICE_") {
				// e.g. HTTP_X_DEVICE_USER_AGENT = HTTP_USER_AGENT
				$realKey=str_replace("X_DEVICE_","",$key);
				$this->proxy->addRawHeader($realKey,$this->getHeader($realKey));
				$this->addCleanedHeader($realKey,$value);
				$this->special="Proxy:Novarra:Web Proxy";
			}
		}
	}

	function checkIfUAIsUAP() {
		$ua = $this->getHeader("HTTP_USER_AGENT");
		if (substr($ua,0,7)=="http://") {
			$url = $ua;
			if (strpos($url,".xml") !== false || strpos($url,".rdf") !== false) {
				if (DRFunctionsCore::isEmptyStr($this->uaprof)) {
					$this->addCleanedHeader('HTTP_X_WAP_PROFILE',$url);
					$this->uaprof = $url;
				}
			}
			$this->addCleanedHeader('HTTP_USER_AGENT',"Missing");
		}
	}
	
	function addDownloadAccept() {
		$da = $this->getHeader('HTTP_X_UP_DOWNLOAD_ACCEPT');
		$a = $this->getHeader('HTTP_ACCEPT');
		if (!DRFunctionsCore::isEmptyStr($da)) $this->addCleanedHeader("HTTP_ACCEPT",$a.",".$da);
	}
	
	/**
	 * Clean the server headers: lots of work done here to normalise things.
	 * This will clean server vars, but also return an additional set of headers
	 * if necessary.
	 *
	 * @param boolean $reclean
	 * @internal
	 * @access public
	 */
	function cleanHeaders($reclean=false) {

		if ($this->cleaned && !$reclean) return;
		if (!is_array($this->rawHeaders)) return;
		
		//$this->init();
		$this->serverVars = $this->rawHeaders;
		if (count($this->rawHeaders) === 0) return;
		$this->normalise();
		$this->checkOperaMini();
		$this->checkStockUA();
		
		$this->getUAProfURL();
		$this->checkIfUAIsUAP();
		
		if (!DRFunctionsCore::isEmptyStr($this->special)) {
			$this->checkSkyfire();
		}

		if (!DetectRight::$expressMode) {
			// thorough cleaning of useragent.
			$this->addDownloadAccept();
			$this->removeIPFromUA();

			$this->checkGoogleTranslator();
			$this->checkGoogleAppEngine();
			$this->checkHideMe();

			$this->checkNovarraVision();
			$this->checkszn();

			if (DRFunctionsCore::isEmptyStr($this->special)) {
				$this->checkGoogleWirelessTranscoder();
			}

			if (DRFunctionsCore::isEmptyStr($this->special)) {
				$this->checkGoogleProxy();
			}

			$this->checkOpenWeb();

			if (DRFunctionsCore::isEmptyStr($this->special)) {
				$this->checkNovarraProxy();
			}
		}
		
		$this->cleanUA();
		$this->grabUA();
		$this->cleaned = true;
		return; // if we've created another set
	}

	function grabUA() {
		$ua = $this->getHeader('HTTP_USER_AGENT');
		if (DRFunctionsCore::isEmptyStr($ua)) {
			$this->ua = "Missing";
		}

		$this->ua = $ua;
	}

	function rewindIP() {
		$this->proxy->setIP($this->getHeader('REMOTE_ADDR'));
		$ips=$this->getHeader('HTTP_X_FORWARDED_FOR');
		if ($ips) {
			$ips=explode(",",$ips);
			$ip=array_shift($ips);
			if (!IP::checkLocalIP($ip)) {
				$this->addCleanedHeader('REMOTE_ADDR',$ip);
				$this->setIP($ip);
				$ips=implode(",",$ips);
				if ($ips) {
					$this->addCleanedHeader('HTTP_X_FORWARDED_FOR',$ips);
				} else {
					$this->removeHeader('HTTP_X_FORWARDED_FOR');
				}
			}
		}
	}

	function geoSniff() {
		if (is_object($this->ip)) {
			$this->ip->process($this);
		}
	}

	function getConnectionProperties() {
		if (!is_object($this->connection)) $this->connection = new HTTPConnection;
		$this->connection->secureURI = $this->getHeader('HTTP_X_UP_WAPPUSH_SECURE');
		
		$bytes = $this->getHeader('HTTP_X_UP_DEVCAP_MAX_PDU');
		if (!DRFunctionsCore::isEmptyStr($bytes)) {
			//$this->addData("Connection","capacity=pdu{max:$bytes;units:bytes}");
			$this->connection->pdu = Validator::validate("bytesize",$bytes);
		}
		
	// ClientID
		// x-pcs-mdn: aknc89ndakkk2r87
		//x-pcs-sub: oisd61louhrw0n53
		//x-pcs-location: 39.006948, -122.53094,2M,2004-05-10T22:14:45:694Z
		// HTTP_X_NOKIA_GATEWAY_ID
		// HTTP_X_UP_BEAR_TYPE
		// _umta cookie 216343109.3899183901784318464.1253295116.1253295116.1253295116.1

		$connection = $this->connection->getCarrier();
		$stack = "";
		if (DRFunctionsCore::isEmptyStr($connection)) {
			// CSD,GPRS,UMTS,258,3G,
			$connection = $this->getHeader("HTTP_X_NOKIA_BEARER");
		}
		

		if (DRFunctionsCore::isEmptyStr($connection)) {
			$connection = $this->getHeader('HTTP_X_NETWORK_TYPE');
		}

		
		$ni = $this->getHeader("HTTP_X_NETWORK_INFO");
		if (!DRFunctionsCore::isEmptyStr($ni)) {
			$tmp = explode(",",$ni);
			foreach ($tmp as $tmp2) {
				$ni = trim(strtoupper($tmp2));
				if (!DRFunctionsCore::isEmptyStr($ni) && ($ni === "TCP" || $ni === "UDP")) {
					if (DRFunctionsCore::isEmptyStr($stack)) {
						$stack = $ni;
					}
				} else {
					if (DRFunctionsCore::isEmptyStr($connection)) {
						$connection = strtolower($ni);
					}
				}
			}
		}

		if (DRFunctionsCore::isEmptyStr($connection)) {
			foreach ($this->getHeaders() as $key=>$value) {
				if (stripos($key,'bear') !== false) {
					$connection = strtoupper($value);
				}
			}
		}

		if (DRFunctionsCore::isEmpty($stack)) {
			$wc = $this->getHeader('HTTP_WAP_CONNECTION');
			if (!DRFunctionsCore::isEmptyStr($wc)) {
				// the type of WAP connection they're coming in on.
				$tmp=split("=",trim($wc));
				if (count($tmp)>1) {
					$stack = strtoupper(trim($tmp[1]));
				}
			}
		}

		//HTTP_COOKIE: $Version=0;Bearer-Type=w-TCP;wtls-security-level=none;network-access-type=GPRS

		$sdu = $this->getHeader('HTTP_X_WAP_CLIENT_SDU_SIZE');
		if (!DRFunctionsCore::isEmptyStr($sdu)) {
			$this->connection->setSdu($sdu);
		}

		//s:29:"HTTP_X_NOKIA_MUSICSHOP_BEARER";s:4:"WLAN"
		//GPRS/3G
		if (DRFunctionsCore::isEmptyStr($connection)) {
			$connection = $this->getHeader("HTTP_X_NOKIA_MUSICSHOP_BEARER");
		}

		$connArray = array("GPRS/3G"=>"GPRS/3G","3G"=>"UMTS","2G"=>"GPRS","UMTS"=>"UMTS","WCDMA"=>"UMTS","EDGE"=>"EDGE","GPRS"=>"GPRS","CSD"=>"CSD","IDEN"=>"IDEN","USB"=>"USB","WLAN"=>"WLAN","80211g"=>"WLAN","80211n"=>"WLAN","80211b"=>"WLAN","CDMA1X"=>"RTT1X","_1x"=>"RTT1X","EVDO"=>"EVDO");

		$uaprof = $this->getHeader('HTTP_UAPROF');

		if (DRFunctionsCore::isEmptyStr($connection) && !DRFunctionsCore::isEmptyStr($uaprof)) {
			foreach ($connArray as $conn=>$conntype) {
				if (stripos($uaprof,$conn)) {
					$connection = $conntype;
					break;
				}
			}
		}

		if (!DRFunctionsCore::isEmptyStr($connection)) {
			$this->connection->setCarrier($connection);
		}
		
		if (!DRFunctionsCore::isEmptyStr($stack)) {
			$this->connection->setStack($stack);
		}
		
		$this->qdt->addObject("Connection",$this->connection);
	}

	function deduceGateway($via = "") {
		if (!$via) $via = $this->getHeader('HTTP_VIA');
		if (!$via) return;
		$via = explode(",",$via);
		$gatewayAgent = array_shift($via);
		if (DRFunctionsCore::isEmptyStr($gatewayAgent)) return;
		$this->gateway = new HTTPHeadersCore(array("HTTP_USER_AGENT"=>$gatewayAgent));
		try {
			$this->gateway->process();
		} catch (DeviceNotFoundException $dnfe) {
			// Not caring. Not seeing. A bit like my bank.
		}
	}

	function fillUid() {
		if (DetectRight::$expressMode) {
			$this->uid = $this->getQuickUid();
		} else {
			$this->uid = $this->getUid();
		}
	}
	
	function getQuickUid() {
		$uid = $this->ua."/".$this->uaprof;
		if ($this->proxy) {
			$proxyUA = $this->proxy->getHeader("HTTP_USER_AGENT");
			if ($proxyUA) {
				$uid = $uid."/".$this->getRaw("HTTP_USER_AGENT");
			}
		}
		return $uid;
	}
	
	function getUid() {
		/*
		if (!$idString) $idString=$this->getHeader('HTTP_X_ACCESS_SUBNYM');
*/
				// HTTP_X_WAP_CLIENT_IP
		// HTTP_X_NOKIA_IPADDRESS - this tends to end up in forwarding anyway
		// HTTP_X_NOKIA_MSISDN
		// HTTP_X_NOKIA_IMSI
		$value = "";
		foreach ($this->getHeaders() as $key=>$valueArray) {
			if (substr($key,0,4) === "HTTP") {
				if (!is_array($valueArray)) $valueArray = array($valueArray);
				foreach ($valueArray as $value) {
					if (stripos($key,'msisdn') !== false) break;
					if (stripos($key,'line_id') !== false) break;
					if (stripos($key,'subno') !== false) break;
					if (stripos($key,'clid') !== false) break;
					if (stripos($key,'subscribe') !== false) break;
					if (stripos($key,'imsi') !== false) break;
					if (stripos($key,'imei') !== false) break;
					if (stripos($key,'gdi') !== false) break;
					if (stripos($key,'subnym') !== false) break;
					if (stripos($key,'orange_id') !== false) break;
				}
				$value = "";
			}
			
		}

		if (!DRFunctionsCore::isEmptyStr($value)) {
			return $value;
		}

		$hashHeaders=array(
			$this->getHeader('HTTP_ACCEPT_LANGUAGE'),
			$this->getHeader('HTTP_BEARER_INDICATION'),
			$this->getHeader('HTTP_ENCODING_VERSION'),
			$this->getHeader('HTTP_HOST'),
			$this->getHeader('HTTP_USER_AGENT'),
			$this->getHeader('HTTP_X_FORWARDED_FOR'),
			$this->getHeader('HTTP_X_UP_UPLINK'),
			$this->getHeader('REMOTE_ADDR'),
			$this->getHeader('SERVER_ADDR')
		);
		
		if (is_object($this->proxy)) {
			$hashHeaders[] = $this->proxy->getHeader('HTTP_USER_AGENT');
			$hashHeaders[] = $this->proxy->getHeader('HTTP_ACCEPT');
		}
		
		$idString = implode("/",$hashHeaders);
		return md5($idString);
	}

	function getCharset() {
		if (!is_object($this->charset)) {
			$this->charset = new HTTPLocale;
		}
		$this->charset->add($this->getHeader('HTTP_ACCEPT_CHARSET'));
		$this->charset->add($this->getHeader('HTTP_X_UP_DEVCAP_CHARSET'));		
	}
	
	function getLanguage() {
		$this->addLanguages($this->getHeader('HTTP_ACCEPT_LANGUAGE'));
	}
	
	function getAccept() {
		$accept = $this->getHeader("HTTP_ACCEPT");
		if (DRFunctionsCore::isEmptyStr($accept)) return;

		$doit = !DetectRight::$expressMode;
		if (strpos($accept, "ss/") !== false) $doit = true;
		if (!$doit) return;

		if (!is_object($this->accept)) {
			$this->accept = new HTTPAccept();
		}
		$this->accept->add($accept);
		$this->accept->process();
		$this->qdt->addQDT($this->accept->getQDT());
		$this->esc->addESC($this->accept->getESC());
	}
	
	function addLanguages($string) {
		if (!is_object($this->language)) {
			$this->language = new HTTPLocale;
		}
		
		$this->language->add($string);
	}
	
	/**
	 * Process the cookies. Why not? :)
	 *
	 * @internal 
	 * @access public
	 */
	function processCookies() {
		if (!DRFunctionsCore::isEmptyStr($cookie = $this->getHeader('HTTP_COOKIE'))) {
			$cookieSplit=explode(";",$cookie);
			foreach ($cookieSplit as $value) {
				$thing=explode("=",$value,2);
				$key=$thing[0];
				$key=strtolower(preg_replace("/[0-9]{1}/",'',$key));
				if ($key == "user-identity-forward-msisdn") {
					$this->uid = $thing[1];
				} elseif ($key == "network-access-type") {
					$this->setConnectionType(strtoupper($thing[1]));
				} elseif ($key == "bearer-type") {
					$this->setBearerType(strtolower($thing[1]));
				} 
			}
		}
		return;
	}

	function setConnectionType($type) {
		if (!is_object($this->connection)) {
			$this->connection = new HTTPConnection();
		}
		$this->connection->setCarrier($type);
	}
	
	function setBearerType($type) {
		if (!is_object($this->connection)) {
			$this->connection = new HTTPConnection();
		}
		$this->connection->setStack($type);
	}
	
	/**
	 * Get the Device IP and related fields.
	 *
	 * @internal 
	 * @access public
	 */
	function getIPs() {
		$remoteAddrIP = $this->getHeader("REMOTE_ADDR",$this->getHeader("HTTP_REMOTE_ADDR"));
		
		if (!DRFunctionsCore::isEmptyStr($xff = $this->getheader('HTTP_X_FORWARDED_FOR'))) {
			$xffArray = explode(",",$xff);
			foreach ($xffArray as $xff) {
				if (!IP::checkLocalIP($xff)) {
					$this->setIP($xff);
					$this->proxy->setIP($remoteAddrIP);
				} else {
					$this->setIP($remoteAddrIP);
					$this->setLocalIP($xff);
				}
			}
		} elseif (!DRFunctionsCore::isEmptyStr($xwcip = $this->getHeader('HTTP_X_WAP_CLIENT_IP'))) {
			if (!IP::checkLocalIP($xwcip)) {
				$this->setIP($xwcip);
				$this->proxy->setIP($remoteAddrIP);
			} else {
				$this->setIP($remoteAddrIP);
				$this->setLocalIP($xwcip);
			}
		} elseif (!DRFunctionsCore::isEmptyStr($cip = $this->getHeader('HTTP_CLIENT_IP'))) {
			if (!IP::checkLocalIP($cip)) {
				$this->setIP($cip);
				$this->proxy->setIP($remoteAddrIP);
			} else {
				$this->setIP($remoteAddrIP);
				$this->setLocalIP($cip);
			}
		} elseif (!DRFunctionsCore::isEmptyStr($si = $this->getHeader('HTTP_X_SUBSCRIBER_INFO'))) {
			if (!IP::checkLocalIP($si)) {
				$this->setIP($si);
				$this->proxy->setIP($remoteAddrIP);
			} else {
				$this->setIP($remoteAddrIP);
				$this->setLocalIP($si);
			}
		} else {
			$this->setIP($remoteAddrIP);
		}
	}

	function setLocalIP($ip) {
		$this->localIP = new IP($ip);	
	}
	
	function getLocalIP() {
		if (!is_object($this->localIP)) return "";
		return $this->localIP->ip;
	}
	
	function processProxy() {
		if (!$this->proxy || empty($this->proxy->rawHeaders)) return;
		try {
			$this->proxy->cleanHeaders(false);
			$this->proxy->processMini(false);
			$this->esc->addESC($this->proxy->getESC());
		} catch (DeviceNotFoundException $dnfe) {
			// not really, no thanks, not today, I've got some.
		}
	}
			
	/**
	 * Extract a URL from headers
	 *
	 * @param array $serverVars
	 * @return string
	 * @acl 1
	 * @access public
	 * @static
	 * @ClientTest 18/5/09
	 */
	static function findUAProfURL($serverVars) {
		$uaProfURL='';

		$diffs=array();
		$keys=array_keys($serverVars);
		foreach ($keys as $key) {
			if (DRFunctionsCore::in('profile',$key)) {
				if (DRFunctionsCore::in('diff',$key)) {
					array_push($diffs,$serverVars[$key]);
				} else {
					$uaProfURL=$serverVars[$key];
				}
			}
		}
		
		if (strpos($uaProfURL,"/") === false) $uaProfURL = "";
		
		if (DRFunctionsCore::isEmptyStr($uaProfURL)) return "";

		$httpPos=stripos($uaProfURL,"http");

		// strip the crap
		if ($httpPos===false) {
			// a UA Profile without a URL??
		} else {
			$uaProfURL=substr($uaProfURL,$httpPos);
		}

		$endUrlPos=strpos(strtolower($uaProfURL),".rdf");
		if ($endUrlPos===false) {
			// a UA Profile without an RDF extension perhaps
		} else {
			$uaProfURL=substr($uaProfURL,0,$endUrlPos+4);
		}

		$endUrlPos=strpos(strtolower($uaProfURL),".xml");
		if ($endUrlPos===false) {
			// a UA Profile without an XML extension perhaps?
		} else {
			$uaProfURL=substr($uaProfURL,0,$endUrlPos+4);
		}


		$uaProfURL=stripslashes($uaProfURL);
		$uaProfURL=stripslashes($uaProfURL);
		$uaProfURL=str_replace('"',"",$uaProfURL);
		// add diffs later. Usually in the headers with _DIFF1 with XML in them.
		// I guess it would just be superimposing the variables therein.
		if (!empty($uaProfURL) && substr($uaProfURL,0,4)!='http') {
			$uaProfURL=base64_decode($uaProfURL);
		}

		$uaProfURL=DRFunctionsCore::cleanURL($uaProfURL);
		return $uaProfURL;
	}
	
	/**
	 * Generate a unique customer key
	 * 
	 * @param associative_array $serverVars
	 * @param string $licence 32 character DR licence
	 * 
	 * @return string
	 * 
	 * @access public
	 * @static 
	 * @acl 1
	 * @ClientTest 18/5/09
	 */
	public function getCustomerHash() {
		if ($this->uid) return $this->uid;
		$this->fillUid();
		return $this->uid;
	}

	public function customerID() {
		return $this->getUid;
	}

	public function hash() {
		$uaPixels=$this->getHeader("HTTP_UA_PIXELS");
		$uaProfURL=$this->uaprof;
		$ua=$this->ua;
		$accept=$this->getHeader("HTTP_ACCEPT");
		$hash=md5(strtolower(str_replace(" ","",$ua."/".$accept."/".$uaProfURL."/".$uaPixels)));
		// note: if full hash is the below, then it's empty data.
		return $hash;
	}
	
	function getESC() {
		$esc = $this->esc;
		$esc->qdt->addQDT($this->qdt);
		return $esc;
	}
	
	function getQDT() {
		return $this->qdt;
	}

	function deduce($allowDeducedNominativeEntities = true) {
		
		if (!$allowDeducedNominativeEntities) {
			$this->esc->refuseNominativeEntities();
		}
		$uaLen = strlen($this->ua);

		if ($uaLen < 11) {
			if (in_array($this->ua, EntityCore::$nominativeEntityTypes)) {
				$e = EntityCore::addEntity($this->ua, "Generic", "Generic " . $this->ua);
				$this->esc->addEntity($e);
			}
		}

		// takeaways:
		// 1) Make sure that device in a contains relationship has an entitytype of "BaseDevice", or remove the "top" device into a top level position?
		// 2) make sure that trust in contains links is properly supervised when adding to ET.
		// this is meant to replace "getprofile".
		/* @var $pc PropertyCollection */
		DetectRight::checkPoint("Deduce Pointers");

		
		$ua = $this->ua;
		if (($uaLen === 7 || $uaLen === 8 || $uaLen === 9) && is_numeric($ua)) {
			$tacESC = PointerCore::getESC("TAC",$ua);
			$this->esc->addESC($tacESC);
		}

		DetectRight::checkPoint("Getting UAP ESC From pointers");
		if (!DRFunctionsCore::isEmptyStr($this->uaprof)) {
			$uaProfESC = PointerCore::getESC("UAP",$this->uaprof);
			$uaProfESC->addImportance(30);
			$this->esc->addESC($uaProfESC);
		}

		DetectRight::checkPoint("Getting UA Raw ESC From pointers");
		$rawUA = $this->getRaw("HTTP_USER_AGENT");
		if (!DRFunctionsCore::isEmptyStr($rawUA)) {
			$pointerRawESC = PointerCore::getESC("PhoneID",$rawUA);
			$pointerRawESC->addImportance(20);
			$this->esc->addESC($pointerRawESC);
		}

		if ($rawUA !== $ua) {
			DetectRight::checkPoint("Getting PhoneID ESC From pointers");
			// only now now check for phone IDs: exact match with phone IDs
			$phoneIDESC = PointerCore::getESC("PhoneID",$ua);
			$phoneIDESC->addImportance(20);
			$this->esc->addESC($phoneIDESC);
		}

		DetectRight::checkPoint("Getting ESC From pointers");
		$pointerESC = PointerCore::getESC("UserAgent",$ua);
		$pointerESC->addImportance(20);
		// next, add in our manual pointers
		$this->esc->addESC($pointerESC);

		if (!$rawUA !== $ua) {
			DetectRight::checkPoint("Getting UA ESC From pointers (raw)");
			if (!$pointerESC->descriptors) {
				$pointerRawESC = PointerCore::getESC("UserAgent",$rawUA);
				$pointerRawESC->addImportance(15);
				$this->esc->addESC($pointerRawESC);
			}
		}

		// right. At this point we have the following resources:
		//$this->serverVars->realtime ==> realtime profile
		//$this->serverVars->entitySigCollection ==> ESC (EntitySigCollection) of real-time derived components
		//$this->proxy->realtime ==> more realtime profile
		//$this->proxy->entitySigCollection
		//$this->uaProfEntity ==> entity derived from UAProfile
		//$this->pointerESC ==> entitySigCollection from pointers
		
		// now, we basically throw entities at the new ESC in the right order.
		// the main thing here is that generally detected entities take priority over contained ones
		// except when the detected entity is actually lower in the contains chain than the shipped one:
		// for instance, if we detect a more generic version of Windows Mobile than it shipped with the device.
		
		
		// that was the easy bit. Now there's a bit of a tense dance between the shipped components, the browser detected
		// (whether a proxy or not), and other detected components.
		// the general theory here is that components which are newly detected or changed from the original
		// spec of the entity change its properties from the shipped default: and so should be given more weight in the data process.
		
		$diag = DetectRight::$DIAG;
		
		if (DetectRight::$expressMode) {
			$esc = DetectorCore::detect($this->ua,"HTTP_USER_AGENT");
			if (!is_null($esc)) {
				if ($diag) DetectRight::checkPoint("Adding ESC");
				$this->esc->addESC($esc);
				if ($diag) DetectRight::checkPoint("Finished adding ESC");
			}
			return;
		}
		// deduces everything from all headers		
		$excludedHeaders = array("HTTP_ACCEPT","REMOTE_ADDR");
		foreach ($this->serverVars as $key=>$value) {
			if ($diag) DetectRight::checkPoint("Detecting Header $key");
			if (in_array($key,$excludedHeaders)) continue;
			$esc = DetectorCore::detect($value,$key);
			if (!is_null($esc)) {
				DetectRight::checkPoint("Adding ESC $key");
				$this->esc->addESC($esc);
				DetectRight::checkPoint("Finished adding ESC");
			}
		}
		if ($diag) DetectRight::checkPoint("Getting customer hash");
		$this->esc->uid = $this->getCustomerHash();
		if ($diag) DetectRight::checkPoint("Deduced");
	}
	
	/**
	 * Return a PropertyCollection based on miscellaneous stuff in the server headers
	 *
	 * @param associative_array $serverVars
	 * @return PropertyCollection
	 * @static 
	 * @internal 
	 * @access public
	 * 
	 */
	function deduceProfileStuffFrom() {
		// screen color
		// headers that were here
		//HTTP_X_OS_PREFS
		//HTTP_X_JPHONE_COLOR
		//HTTP_X_UP_DEVCAP_SCREENDEPTH
		//HTTP_X_UP_DEVCAP_ISCOLOR
		//HTTP_X_JPHONE_DISPLAY
		//HTTP_X_UP_DEVCAP_NUMSOFTKEYS
		//HTTP_X_UP_DEVCAP_SCREENPIXELS
		//HTTP_X_UP_DEVCAP_SCREENCHARS
		//HTTP_X_JPHONE_JAVA
		//HTTP_X_JPHONE_MSNAME --> device name
		//HTTP_X_JPHONE_SMAF
		//HTTP_X_JPHONE_SOUND
		//HTTP_X_JPHONE_UID

	}
	
					
	/**
	 * Parsing Windows Mobile stuff
	 *
	 * @param associative_array $serverVars
	 * @param PropertyCollection $result
	 * @access public
	 * @internal
	 * @static 
	 * @deprecated: most strings moved off to checking HTTP_UA_OS. Microsoft's weird method of combining headers is not worth modelling since we're getting pretty good version information from the database contains too.
	 * 
	 */
	function parseWindowsMobile() {
		// deal with OS
		//$result = new PropertyCollection("UNIVERSAL","WINMOB");
		/* @var $result PropertyCollection */
		$wmResult="";
		$profileChanges = array();
		
		$currentOS=$this->getHeader('HTTP_UA_OS');
		$ua = $this->ua;
		
		if (!$currentOS) {
			$wmResult = EntityCore::parseEntityDescriptor("Developer Platform:Microsoft:Windows Mobile");
			if (stripos($ua,"Smartphone;") !== false) {
				if (stripos($wmResult['description'],"Smartphone" === false)) $wmResult['description'] .= "Smartphone";
			} else {
				if (stripos($wmResult['description'],"Pocket PC" === false)) $wmResult['description'] .= "PPC";
			}

			// if mozilla version is 2.0, then assume this is a pocket PC 2000
			if (stripos($this->ua,"Mozilla/2.0") !== false) {
				$wmResult["description"]="Pocket PC 2000";
			} else {
				$wmResult['description'] = "Windows Mobile";
			}
		}
		
		if (stripos("6.",$wmResult['description']) !== false) {
			if (!DRFunctionsCore::isEmptyStr($this->getHeader('HTTP_UA_CPU'))) {
				$wmResult['description'] .= "Professional";
			} else {
				$wmResult['description'] .= "Standard";
			}
		}

		$color=$this->getHeader('HTTP_UA_COLOR');
		if (!DRFunctionsCore::isEmptyStr($color)) {
			$color=str_replace('color','',$color);
			$color=str_replace('mono','',$color);
			$colors=pow(2,$color);
			$profileChanges[]="Display//depth=screen{value:$color;units:bpp}";
			$profileChanges[]="Display//capacity=screen{max:$colors;units:colors}";
		}

		$es = new EntitySig($wmResult,$this->getHeader("HTTP_UA_OS"),$profileChanges);
		$this->esc->addES($es);
	}

	function localeClean($uAgent) {
		if (is_null($this->db)) return;
		$uAgent = $this->db->arrayFilter("UAClean",$uAgent);
		$uAgent = $this->db->arrayFilter("Crap",$uAgent);
		
		$languages = $this->db->getArray("Languages");
		if (!$languages) $languages = array();
		$languageDelimiters = array(";"," ","[","]","(",")");
		$languageDelimitersStrict = array(";","[","]","(",")");
		
		foreach ($languages as $cultureKey=>$culture) {
			$match = "";
			$ckUS = str_replace("-","_",$culture);
			$ckUSK = str_replace("-","_",$cultureKey);
			if (($pos = stripos($uAgent,$culture)) !== false) {
				$match = $culture;
			} elseif (($pos = stripos($uAgent,$cultureKey)) !== false) {
				$match = $cultureKey;
			} elseif (($pos = stripos($uAgent,$ckUS)) !== false) {
				$match = $ckUS;
			} elseif (($pos = stripos($uAgent,$ckUSK)) !== false) {
				$match = $ckUSK;
			} 

			if ($match === "") continue;
			if ($pos === 0) continue;
			if ($match === "(none)") continue;
						
			$prevChar = substr($uAgent,$pos-1,1);
			$nextChar = substr($uAgent,$pos+strlen($match),1);
			// matches of two characters can generate false positives.
			// For instance, we can't assume "mr" appearing anywhere without some kind of other delimiter
			// is a language string.
			if (strlen($match) > 2) {
				if (!in_array($nextChar,$languageDelimiters)) continue;
				if (!in_array($prevChar,$languageDelimiters)) continue;
			} else {
				if (!in_array($nextChar,$languageDelimitersStrict)) continue;
				if (!in_array($prevChar,$languageDelimitersStrict)) continue;				
			}
			

			// add language and culture to the appropriate object?
			$this->addLanguages($culture.";q=1");
			$uAgent = str_ireplace("$prevChar$match$nextChar",$prevChar."xx-xx".$nextChar,$uAgent);
			break;
		}
		return $uAgent;
	}
	
	static public function headerFromUA($ua) {
		$lhm = array("HTTP_USER_AGENT"=>$ua);
		$headers = new HTTPHeadersCore($lhm);
		return $headers;
	}
    	
	public function localeCleanQuick($uAgent) {
		if (strpos($uAgent,"xx-xx") !== false) return $uAgent;
		$matches = array();
		
		if (preg_match_all(self::$localeRegEx,$uAgent,$matches,PREG_OFFSET_CAPTURE)) {
			foreach ($matches[1] as $match) {
				$replacement = $match[0];
				// CE-HT is found in "CE-HTML"
				if ($replacement === "ce-ht" || $replacement === "CE-HT") continue;
				$lastChar = substr($replacement,-1,1);
				if ($lastChar === ";" || $lastChar === ")") $replacement = substr($replacement,0,-1);
				$this->addLanguages($replacement . ";q=1");
				$start = $match[1];
				$end = $start + strlen($replacement);
				$uAgent = substr($uAgent,0,$start). "xx-xx" .  substr($uAgent,$end);
				break;
			}
		}
		return $uAgent;
	}
	
	public function isMobileConnection() {
		return false;
	}
}