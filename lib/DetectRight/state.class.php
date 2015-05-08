<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    state.class.php
Version: 2.2.3
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

2.2.3 - made default importance of datapoint "0" instead of "50".
2.3.2 - changed members to public so static function can fill them directly for speed reasons.
**********************************************************************************/

Class State {
	public $directHit = 0;
	public $importance = 0;
	public $status = 1;

	/**
	 * @param args
	 */
	public static function main($args) {
		// 

	}

	
	function __construct($status = 1,$importance = 0, $directHit = 1) {
		$this->directHit = $directHit;
		$this->status = $status;
		$this->importance = $importance;
	}
	
	public function getDirectHit() {
		return $this->directHit;
	}

	public function invert() {
		if ($this->status === 1) {
			$this->status = 0;
		} elseif ($this->status === 0) {
			$this->status = 1;
		}
	}
	
	public function setDirectHit($directHit) {
		$this->directHit = $directHit;
	}

	public function getImportance() {
		return $this->importance;
	}

	public function setImportance($importance) {
		$this->importance = $importance;
	}

	public function getStatus() {
		return $this->status;
	}

	public function setStatus($status) {
		$this->status = $status;
	}
	
	static public function stateFromString($stateStr) {
		//static $stateCache;
		if ($stateStr === null || $stateStr === "") return null;
		/*if (!isset($stateCache)) $stateCache = array();
		if (isset($stateCache[$stateStr])) return clone $stateCache[$stateStr];*/
		$state = new State();

		$status = $stateStr{0};
		if (isset($stateStr{9})) {
			$directHit = $stateStr{9};
			$importance = $stateStr{2}.$stateStr{3}.$stateStr{4}.$stateStr{5}.$stateStr{6}.$stateStr{7};
		} else if (isset($stateStr{8})) {
			$directHit = $stateStr{8};
			$importance = $stateStr{2}.$stateStr{3}.$stateStr{4}.$stateStr{5}.$stateStr{6};
		} else if (isset($stateStr{7})) {
			$directHit = $stateStr{7};
			$importance = $stateStr{2}.$stateStr{3}.$stateStr{4}.$stateStr{5};
		} else if (isset($stateStr{6})) {
			$directHit = $stateStr{6};
			$importance = $stateStr{2}.$stateStr{3}.$stateStr{4};
		} else if (isset($stateStr{5})) {
			$directHit = $stateStr{5};
			$importance = $stateStr{2}.$stateStr{3};
		} else if (isset($stateStr{4})) {
			$directHit = $stateStr{4};
			$importance = $stateStr{2};
		} else {
			return $state; // invalid state. Might even return error, but hey...
		}

		if ($status === '0') {
			$state->status = 0;
		} else {
			$state->status = 1;
		}
		
		$state->importance = $importance;
		if ($directHit === '1') {
			$state->directHit = 1;
		} else {
			$state->directHit = 0;
		}
		//$stateCache[$stateStr] = $state;
		return $state;
	}
	
	public function directHit($hitStatus) {
		$this->directHit = $hitStatus;
	}
	
	public function addImportance($importance) {
		$this->importance = $this->importance + $importance;
	}
	
	public function toString() {
		return $this->status.":".$this->importance.":".$this->directHit;
	}
	
	// PHP SPECIFIC, like the fill in Java
	static public function __set_state($array) {
		$obj = new State();
		foreach ($array as $key=>$value) {
			$obj->$key = $value;
		}
		return $obj;
	}

}