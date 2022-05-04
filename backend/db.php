<?php

include_once 'config.php';
$db = new Database;
$db->connect('sqlite:db.sqlite');
$ret = $db->select('sqlite_master', '*', ['type'=> 'table', 'name'=> 'ospf']);
if(empty($ret))
	$db->query('CREATE TABLE `ospf` (
		name TEXT NOT NULL,
		name_alias TEXT DEFAULT NULL,
		real_ip TEXT NOT NULL,
		router_id TEXT NOT NULL,
		ospfconf TEXT DEFAULT NULL,
		netintf TEXT DEFAULT NULL,
		myospfconf TEXT DEFAULT NULL,
		mynetintf TEXT DEFAULT NULL,
		push_myospfconf INTEGER DEFAULT 0,
		push_mynetintf INTEGER DEFAULT 0,
		ipas TEXT DEFAULT NULL,
		linkstate TEXT DEFAULT NULL,
		last_updated INTEGER DEFAULT 0,
		PRIMARY KEY (router_id)
	);');

function GetStrBetween($str, $start, $end)
{
    $arr = explode($start, $str);
    array_shift($arr);
    $res = array();
    foreach ($arr as $val)
        if (($pos=strpos($val, $end)) !== false)
            $res[] = substr($val, 0, $pos);
    return $res;
}

function diemsg($msg, $code=0)
{
	header('Access-Control-Allow-Origin: *');
	header('Content-type: application/json');
	echo json_encode(['status'=> $code, 'msg'=> $msg]);
	die;
}

if(!extension_loaded('pdo_sqlite'))
	diemsg('pdo_sqlite not loaded');

class Database
{
	private $dbh = null;
	private $sth = null;
	
	public $lastSQL = null;
	
	private function watchException($execute_state)
	{
		if(!$execute_state || $execute_state===false)
			diemsg("SQL Halt: ".var_export($this->dbh->errorInfo(),true)."\n");
		return $execute_state;
	}
	
	private function format_table_name($table)
	{
		$parts = explode('.', $table, 2);
		if(count($parts) > 1)
			return $parts[0]."`{$parts[1]}`";
		else
			return "`{$table}`";
	}
	
	public function connect($dsn, $user='', $pass='', $charset='utf-8')
	{
		if($this->dbh)
			return;
		$this->dbh = new PDO($dsn, $user, $pass);
		$this->dbh->setAttribute(PDO::ATTR_TIMEOUT, 60);
		return $this;
	}
	
	public function close()
	{
		$this->dbh = null;
		return $this;
	}
	
	public function fetch($sql, $params=array(), $type=PDO::FETCH_ASSOC)
	{
		$this->lastSQL = $sql;
		$this->sth = $this->watchException($this->dbh->prepare($sql));
		$this->watchException($this->sth->execute($params));
		return $this->sth->fetch($type);
	}
	
	public function fetchColumn($sql, $params=array(), $position=0)
	{
		$this->lastSQL = $sql;
		$this->sth = $this->watchException($this->dbh->prepare($sql));
		$this->watchException($this->sth->execute($params));
		return $this->sth->fetch(PDO::FETCH_COLUMN, $position);
	}
	
	public function fetchAll($sql, $params=array())
	{
		$result = array();
		$this->lastSQL = $sql;
		$this->sth = $this->watchException($this->dbh->prepare($sql));
		$this->watchException($this->sth->execute($params));
		while($result[] = $this->sth->fetch(PDO::FETCH_ASSOC));
		array_pop($result);
		return $result;
	}
	
	public function fetchAllColumn($sql, $params=array(), $position=0)
	{
		$result = array();
		$this->lastSQL = $sql;
		$this->sth = $this->watchException($this->dbh->prepare($sql));
		$this->watchException($this->sth->execute($params));
		while($result[] = $this->sth->fetch(PDO::FETCH_COLUMN, $position));
		array_pop($result);
		return $result;
	}
	
	public function exists($sql, $params=array())
	{
		$this->lastSQL = $sql;
		$data = $this->fetch($sql, $params);
		return !empty($data);
	}
	
	public function query($sql, $params=array())
	{
		$this->lastSQL = $sql;
		$this->sth = $this->watchException($this->dbh->prepare($sql));
		$this->watchException($this->sth->execute($params));
		return $this->sth->rowCount();
	}
	
	public function mquery($sql, $params=array())
	{
		$this->lastSQL = $sql;
		$this->sth = $this->watchException($this->dbh->prepare($sql));
		$this->watchException($this->sth->execute($params));
		return $this->sth->rowCount();
	}
	
	public function _select($table, $col='*', $cond=array())
	{
		$table = $this->format_table_name($table);
		if(is_array($col))
			$col = implode(',', $col);
		$sql = "SELECT {$col} FROM {$table}";
		$fields = array();
		$pdo_params = array();
		$where = '';
		if(is_string($cond))
			$where = $cond;
		else if(is_array($cond))
		{
			foreach($cond as $field => $value)
			{
				$fields[] = "`{$field}`=:cond_{$field}";
				$pdo_params["cond_{$field}"] = $value;
			}
			$where = implode(' AND ', $fields);
		}
		if(!empty($where))
			$sql.=' WHERE '.$where;
		return [$sql, $pdo_params];
	}
	
	public function select($table, $col='*', $cond=array())
	{
		$ret = $this->_select($table, $col, $cond);
		return $this->fetchAll($ret[0], $ret[1]);
	}
	
	public function selectColumn($table, $col='*', $cond=array())
	{
		$ret = $this->_select($table, $col, $cond);
		return $this->fetchAllColumn($ret[0], $ret[1]);
	}
	
	public function update($table, $params=array(), $cond=array())
	{
		$table = $this->format_table_name($table);
		$sql = "UPDATE {$table} SET ";
		$fields = array();
		$pdo_params = array();
		foreach($params as $field => $value)
		{
			$fields[] = "`{$field}`=:field_{$field}";
			$pdo_params["field_{$field}"] = $value;
		}
		$sql.=implode(',', $fields);
		$fields = array();
		$where = '';
		if(is_string($cond))
			$where = $cond;
		else if(is_array($cond))
		{
			foreach($cond as $field => $value)
			{
				$fields[] = "`{$field}`=:cond_{$field}";
				$pdo_params["cond_{$field}"] = $value;
			}
			$where = implode(' AND ', $fields);
		}
		if(!empty($where))
			$sql.=' WHERE '.$where;
		return $this->mquery($sql, $pdo_params);
	}
	
	public function insert($table, $params=array())
	{
		$table = $this->format_table_name($table);
		$sql = "INSERT INTO {$table} ";
		$fields = array();
		$placeholder = array();
		foreach($params as $field => $value)
		{
			$placeholder[] = ":{$field}";
			$fields[] = "`{$field}`";
		}
		$sql.='('.implode(',', $fields).') VALUES ('.implode(',', $placeholder).')';
		
		$this->mquery($sql, $params);
		$id = $this->dbh->lastInsertId();
		if(empty($id))
			return $this->sth->rowCount();
		else
			return $id;
	}
	
	public function delete($table, $cond=array())
	{
		$table = $this->format_table_name($table);
		$sql = "DELETE FROM {$table}";
		$fields = array();
		$pdo_params = array();
		$where = '';
		if(is_string($cond))
			$where = $cond;
		else if(is_array($cond))
		{
			foreach($cond as $field => $value)
			{
				$fields[] = "`{$field}`=:cond_{$field}";
				$pdo_params["cond_{$field}"] = $value;
			}
			$where = implode(' AND ', $fields);
		}
		if(!empty($where))
			$sql.=' WHERE '.$where;
		return $this->mquery($sql, $pdo_params);
	}
};

?>