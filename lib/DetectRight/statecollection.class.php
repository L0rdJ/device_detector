<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    statecollection.class.php
Version: 2.0.0
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

Class StateCollection {

	/**
	 * The array containing the states inherent in the system.
	 * Array of State Objects
	 */
	private $states = array();	
	private $lastState; 
	private $collapsedState;
	private $dirty = false;
	
	/**
	 * @param args
	 */
	public static function main($args) {
		// 

	}

	public function getCollapsedState() {
		if ($this->dirty) $this->collapse();
		return $this->collapsedState;
	}
	
	public function getLastState() {
		return $this->lastState;
	}
	
	public function getState($stateStr) {
		if (!isset($this->states[$stateStr])) return null;
		return $this->states[$stateStr];
	}
	
	public function toArray() {
		$retArray = array_keys($this->states);
		return $retArray;
	}
	
	public function addSC($sc,$indirect = false) {
		$scKeys = $sc->toArray();
		if ($scKeys === null) return;
		foreach ($scKeys as $scKey) {
			if ($indirect) {
				if (substr($scKey, -2,2) === ":1") {
					$scKey = substr($scKey,0,-2) . ":0";
				}
			}
			$this->addStateFromString($scKey);
		}
		$this->dirty  = true;
	}
	
	public function collapse() {
		// collapses the current state into the collapsedState
		if (empty($this->states)) {
			$this->collapsedState = new State(0,0,0);
			return;
		}

		$cnt = count($this->states);
		if ($cnt === 1) {
			$this->collapsedState = $this->lastState;
			return;
		}
		$statusZeroMaxImportance = 0;
		$statusOneMaxImportance = 0;
		$statusZeroDirectHit = 0;
		$statusOneDirectHit = 0;
		$collapsedDirectHit = 0;
		$collapsedStatus = 0;
		$collapsedImportance = 0;
		$statusOneCount = 0;
		$statusZeroCount = 0;
		
		foreach ($this->states as $s) {
			if ($s === null) continue; 
			$status = $s->getStatus();
			$importance = $s->getImportance();
			if ($status === 0) {
				$statusZeroCount++;
				if ($importance > $statusZeroMaxImportance) $statusZeroMaxImportance = $importance;
				if ($statusZeroDirectHit === 1 || $s->getDirectHit() === 1) $statusZeroDirectHit = 1;
			} else if ($status === 1) {
				$statusOneCount++;
				if ($importance > $statusOneMaxImportance) $statusOneMaxImportance = $importance;
				if ($statusOneDirectHit === 1 || $s->getDirectHit() === 1) $statusOneDirectHit = 1;
			}
		}
		
		// now we work out the final stuff.
		// the all important thing here is importance vs direct hit
		// let's turn this off, and then try and turn it on.
		$collapsedDirectHit = $statusZeroDirectHit;
		$collapsedStatus = 0;
		$collapsedImportance = $statusZeroMaxImportance;
		
		$statusOneAdvantage = $statusOneCount - $statusZeroCount;
		
		if ($statusOneAdvantage > 2) {
			$collapsedImportance = $collapsedImportance - 10;
		}
		
		if ($statusOneDirectHit === $collapsedDirectHit && $statusOneDirectHit === 1) {
			// two direct exact hits on this.
			if ($statusOneMaxImportance >= $collapsedImportance) {
				$collapsedImportance = $statusOneMaxImportance;
				$collapsedStatus = 1;
				$collapsedDirectHit = 1;
			}
		} else if ($statusOneDirectHit === $collapsedDirectHit) {
			// two negative non-exact hits
			if ($statusOneMaxImportance >= $collapsedImportance) {
				$collapsedImportance = $statusOneMaxImportance;
				$collapsedStatus = 1;
			}
		} else if ($statusOneDirectHit === 1) {
			// direct hit for on, implicit "false" property
			// may have to use probability to settle this argument
			$collapsedImportance = $statusOneMaxImportance;
			$collapsedStatus = 1;
			$collapsedDirectHit = 1;
		} else if ($statusOneMaxImportance === 0) {
			// status 0 direct hit, no importance for status 1.
			$collapsedStatus = 0;
		} else {
			// direct hit for false, implicit one.
			//double statusZeroPercent = (double) statusZeroCount / (statusOneCount + statusZeroCount);
			// todo
			if ($statusOneMaxImportance > ($statusZeroMaxImportance - 20)) {
				$collapsedImportance = $statusOneMaxImportance;
				$collapsedStatus = 1;
			}
		}
		
		$this->collapsedState = new State($collapsedStatus,$collapsedImportance,$collapsedDirectHit);
		$this->dirty = false;
	}
	
	public function getStatus() {
		// the million dollar question!
		if ($this->dirty) $this->collapse();
		if (is_null($this->collapsedState)) return null;
		// note: in rare occasions, a QuantumDataCollection object can turn up here.
		if (!method_exists($this->collapsedState,"getStatus")) {
			var_dump($this->collapsedState);
			die("QDC got into this");
		}
		return $this->collapsedState->getStatus();
	}
	
	public function getImportance() {
		// the million dollar question!
		if ($this->dirty) $this->collapse();
		if ($this->collapsedState === null) return 0;
		return $this->collapsedState->getImportance();
	}

	public function __toString() {
		return $this->toString();
	}
	
	public function toString() {
		$keys = array_keys($this->states);
		return implode("|",$keys);
	}
	
	public function fromString($statesStr) {
		$states = explode("|",$statesStr);
		foreach ($states as $state) {
			$this->addStateFromString($state);
		}
	}
		
	public function addImportance($importance) {
		foreach ($this->states as $key=>$state)  {
			if ($state !== null) {
				$state->addImportance($importance);
				unset($this->states[$key]);
				$this->addState($state);
				$this->dirty = true;
			}
		}
	}
	
	
	public function addStateFromString($stateStr) {
		if (DRFunctionsCore::isEmptyStr($stateStr)) return;
		$state = State::stateFromString($stateStr);
		if ($state === null) return;
		$this->addState($state);
	}
	
	public function resolve() {
		// collapses all datapoints
		$this->collapse();
		$this->states = array();
		$this->addState($this->collapsedState);
	}
	
	public function addStateFromValues($status, $importance, $directHit) {
		$state = new State($status,$importance,$directHit);
		$this->addState($state);
	}
	 
	public function addState($state) {
		if ($state === null) return;
		$stateKey = $state->toString();
		if (isset($this->states[$stateKey])) return;
		if ($this->states === null) $this->states = new StateCollection();
		$this->states[$stateKey] = $state;
		$this->lastState = &$this->states[$stateKey];
		$this->dirty = true;
	}
	
	public function directHit($hitStatus) {
		if ($this->lastState === null) return;
		$this->lastState->directHit($hitStatus);
	}
	
	public function invert() {
		foreach ($this->states as $key=>$state)  {
			if ($state !== null) {
				$state->invert();
				unset($this->states[$key]);
				$this->addState($state);
			}
		}
		$this->dirty = true;
	}
	
	public function extractStates($sidArr) {
		$output = array();
		$state = new State();
		$stateSeen = false;
		for ($i=0; $i < count($sidArr); $i++) {
			$item = $sidArr[$i];
			$item = str_replace("=","->",$item);
			$tmp = explode("->",$item);
			if (!isset($tmp[1])) {
				$output[] = $sidArr[$i];
				continue;
			}

			$itemB = $tmp[0];
			$payload = $tmp[1];
			if ($itemB === 'sc') {
				$this->fromString($payload);
			} else if ($itemB === 'state') {
				$this->addStateFromString($payload);
			} else if ($itemB === 'importance') {
				try {
					$importance = trim($payload);
					$state->setImportance((int)$importance);
					$stateSeen = true;
				} catch (Exception $e) {
					// don't fill 
				}
			} else if ($itemB === 'status') {
				try {
					$status = trim($payload);
					$state->setStatus((int) $status);
					$stateSeen = true;
				} catch (Exception $e) {
					// don't fill 
				}				
			} else if ($itemB === 'directHit') {
				try {
					$directHit = trim($payload);
					$state->setDirectHit((int)$directHit);
					$stateSeen = true;
				} catch (Exception $e) {
					// don't fill 
					
				}				
			} else {
				$output[] = $sidArr[$i];
			}
		}
		if ($stateSeen) $this->addState($state);
		return $output;
	}
	
	public function count() {
		return count($this->states);
	}
	
	// PHP SPECIFIC, like the fill in Java
	static public function __set_state($array) {
		$obj = new StateCollection();
		foreach ($array as $key=>$value) {
			$obj->$key = $value;
		}
		return $obj;
	}

}