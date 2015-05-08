<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    recordset.class.php
Version: 2.3.2
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

2.2.0 - bug fix in recordset action with limit clauses
2.3.2 - new syncRSWithArray command
**********************************************************************************/

Class RecordSet {
	// holds either a query or an array
	public $IDadded=false;
	public $lastID=0;
	public $data;
	public $table;
	public $fieldList;
	public $where;
	public $orderBy;
	public $limitClause;
	public $keyField;
	public $idField;
	public $sqlOp;
	public $eor=false;
	public $dbLink;
	public $buffersize=50;
		
	function __construct(DBLink $dbLink,$table,$fieldList,$where="",$orderBy="",$limitClause="",$keyField="",$sqlOp="select",$idField="ID") {
		$this->dbLink = $dbLink;
		$this->table = $table;
		$this->fieldList = $fieldList;
		$this->where = $where;
		$this->orderBy = $orderBy;
		$this->limitClause = $limitClause;
		$this->keyField = $keyField;
		$this->sqlOp = $sqlOp;				
		$this->idField = $idField;
		if (!in_array($idField,$fieldList) && !in_array("*",$fieldList)) {
			$this->IDadded = true;
			$this->fieldList[] = $idField;
		}
		$this->buffer();
		return;
	}
	
	function fetch() {
		if ($this->data === null) return null;
		if (is_scalar($this->data)) {
			return array();
		}
		$cnt = count($this->data);
		if ($cnt === 0 && $this->eor) return false;
		if ($cnt === 0) $cnt = $this->buffer();
		if ($cnt === 0) return false;
		$row = array_shift($this->data);
		return $row;
	}
	
	function buffer() {
		if (!$this->limitClause) {
			$limitClause = array("limit"=>$this->buffersize);
		} else {
			if ($this->limitClause['limit'] >= $this->buffersize) {
				$limitClause = array("limit"=>$this->buffersize);
				$this->limitClause['limit'] = $this->limitClause['limit'] - $this->buffersize;
			} else {
				$limitClause = $this->limitClause;
			}
		}
		
		$where = $this->where;
		if ($where === "") $where = array();
		if ($this->lastID) {
			$firstWhere = $where;
			$secondWhere = array($this->idField => array("op"=>">","value"=>$this->lastID));
			$where = array("op"=>"and");
			if (count($firstWhere) > 0) {
				$where[] = array("op"=>"where","value"=>$firstWhere);
			}
			$where[] = array("op"=>"where","value"=>$secondWhere);
		}
		
		if (!$this->orderBy) {
			$orderBy = array($this->idField => "ASC");
		} else {
			$orderBy = $this->orderBy;
		}
		$data = $this->dbLink->simpleFetch($this->table,$this->fieldList,$where,$orderBy,$limitClause,$this->keyField,$this->sqlOp);
		if ($data === false) {
			$this->data = false;
			return 0;
		}
		$cnt = count($data);
		if ($cnt < $this->buffersize) {
			$this->eor = true;
		} else {
			$lastRow = $data[$cnt-1];
			$this->lastID = DRFunctionsCore::gv($lastRow,$this->idField);
		}
		if ($this->IDadded) {
			foreach ($data as $key=>$array) {
				unset($data[$key][$this->idField]);
			}
		}
		$this->data = $data;
		return $cnt;
	}

	// JAVA FIX

	static function syncRSWithArray($srcEA, RecordSet $destEA, $idField = "ID") {
		// synchronisation between two arrays
		// first, build field lists from each
		if (count($srcEA) == 0) return true;
		$success = true;
		while (true) {
			$row = array_shift($srcEA);
			if (!$row) break;
			$id = DRFunctionsCore::gv($row,$idField);
			if (!DRFunctionsCore::isEmptyStr($id)) {
				$destEA->processRow($row);
			}
		}
		return $success;
	}


	static function syncRS(RecordSet $srcEA, RecordSet $destEA) {
		// synchronisation between two arrays
		// first, build field lists from each
		if (count($srcEA->data) == 0) return true;
		$success = true;
		while (true) {
			$row = $srcEA->fetch();
			if (!$row) break;
			$id = DRFunctionsCore::gv($row,$srcEA->idField);
			if (!DRFunctionsCore::isEmptyStr($id)) {
				$destEA->processRow($row);
			}
		}
		return $success;
	}

	function processRow($row) {
		$success = true;
		$id = DRFunctionsCore::gv($row,$this->idField);
		if ($this->IDExists($id)) {
			$success = $success & $this->dbLink->updateData($this->table,$row,array($this->idField,$id));
		} else {
			$success = $success & ($this->dbLink->insertData($this->table,$row,($id > 0)));
		}
		return $success;
	}

	function IDExists($id) {
		$ids = $this->dbLink->getIDs($this->table,$this->idField,array($this->idField=>$id));
		if (count($ids) == 0) return false;
		return true;
	}

	function delete() {
		// delete row by row, so as not to lock the file too much
		$success=true;
		while (true) {
			$row = $this->fetch();
			if (!$row) break;
			$id = DRFunctionsCore::gv($row,$this->idField);
			if (!DRFunctionsCore::isEmptyStr($id)) {
				$success = $success & $this->dbLink->deleteData($this->table,array($this->idField=>$id));
			}
		}
		return $success;
	}

	// JAVA FIX

	function fetchAll() {
		$output = array();
		while ($row = $this->fetch()) {
			$output[] = $row;
		}
		return $output;
	}
}