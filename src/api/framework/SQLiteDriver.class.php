<?php

class SQLiteDriver {
	protected $options;
	
	public $db;	// 数据库实例
	public $sql;
	public $lastsql;
	
	public function __construct() {
		global $Config;
		
		if (isset($Config) && isset($Config['sqlite'])) {
			$this->options = array();
			$this->reset();
			
			$this->connect($Config);
		} else {
			exit("Fatal error: Global config not exists.");
		}
	}
	
	/*
	* 连接数据库
	*/
	public function connect($Config) {
		if (!isset($this->db)) {
			try {
				$filename = $Config['sqlite']['host'];

				$this->db = new PDO("sqlite:$filename");
				$this->db->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
				$this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				//$this->db->query("SET NAMES 'utf8'");
			} catch (PDOException $e) {
				exit("Fatal error: " . $e->getMessage());
			}
		}
	}
	
	/*
	* 运行SQL语句
	*/
	public function exec($sql, array $params = array()) {
		$sth = $this->db->prepare($sql);
		
		if (!$sth) {
			exit(implode('<br>', $sth->errorInfo()));
		} else {
			foreach($params as $key => $obj) {
				$sth->bindValue($key, $obj[0], $obj[1]);
			}
			
			if ($sth->execute()) {
				foreach($params as $key => $obj) {
					$sql = str_replace($key, "'" . $obj[0] . "'", $sql);
				}
				
				$this->lastsql = $sql;
				return $sth;
			} else {
				exit(implode('<br>', $sth->errorInfo()));
			}
		}
	}	
	
	/*
	 * 开启事务
	 */
	public function begin() {
		$this->db->beginTransaction();
	}
	
	/*
	 * 回滚
	 */
	public function rollBack() {
		$this->db->rollBack();
	}
	
	/*
	 * 提交事务
	 */
	public function commit() {
		$this->db->commit();
	}
	
	/*
	* 回调方法，连贯操作的实现
	*/
	public function __call($method, $args) {
		$method = strtolower($method);
        if (in_array($method, array('group','having','order'))) {
            $this->options[$method] = $args[0];
            $this->options['field'] = $this->options['field'] == '' ? '*' : $this->options['field'];
            
			return $this;
        } else{
			throw new Exception($method . '方法在数据库类中没有定义。');
		}
    }
	
	/*
	* 设置主表
	*/
	public function table($table, $alias=null) {
		$this->options['table'] = $table.' '.$alias;
		return $this;
	}
	
	/*
	* 添加关联表
	*/
	public function add_table($table, $alias) {
		$this->options['add_table'][$alias]['table'] = ',' . $table . ' ' . $alias;
		return $this;
	}
	
	/**
	 * 左联接
	 * @param type $table
	 * @param type $alias
	 * @param type $on
	 * @return \MySQLDriver
	 */
	public function left_join($table, $alias, $on) {
		$this->options['joins'][$alias]['table'] = $table . ' ' . $alias;
		$this->options['joins'][$alias]['on'] = $on;
		$this->options['joins'][$alias]['type'] = 'LEFT JOIN';
		return $this;
	}

	/*
	* 需要使用的字段
	*/
	public function fields() {
		$this->options['field'] = implode(',', func_get_args());
		return $this;
	}
	
	/*
	* 搜索条件
	*/
	public function where($where) {
		$this->options['where'] = trim($where);
		$this->options['field'] = $this->options['field'] == '' ? '*' : $this->options['field'];
		return $this;
	}
	
	/*
	* 追加搜索条件
	*/
	public function add_where($where, $relation='AND') {
		if ($this->options['where'] == '') {
			$this->options['where'] = trim($where);
		} else {
			$relation = strtoupper(trim($relation));
			$this->options['where'] .= ' ' . $relation . ' ' . trim($where);
		}
		return $this;
	}

	/*
	* 绑定的参数值
	*/
	public function bind($field, $value, $valueType=PDO::PARAM_STR) {
		$field = trim($field);
		$field = ':' . ltrim($field, ':');
		$this->options['binds'][$field] = array($value, $valueType);
		return $this;
	}
	
	/*
	 * 查询指定数量的记录
	 */
	public function limit($lentgh, $offset=0) {
		if (intval($offset) > 0) {
			$this->options['limit'] = intval($lentgh) . ' OFFSET ' . intval($offset);
		} else {
			$this->options['limit'] = intval($lentgh);
		}
		return $this;
	}
	
	/*
	* 统计行数
	*/
	public function count() {
		$table = $this->options['table'];
		$field = 'count(*)';
		$where = $this->parseCondition();
		$binds = $this->options['binds'];
		
		$table_add = '';
		
		if (isset($this->options['add_table']) && is_array($this->options['add_table'])) {
			foreach ($this->options['add_table'] as $value) {
				$table_add .= $value['table'];
			}
		}
		
		if (isset($this->options['joins']) && is_array($this->options['joins'])) {
			foreach ($this->options['joins'] as $value) {
				$table_add .= ' ' . $value['type'] . ' ' . $value['table'] . ' ON ' . $value['on'];
			}
		}
		
		$this->sql = "SELECT $field FROM $table $table_add $where";
		$sth = $this->exec($this->sql, $binds);
		return intval($sth->fetchColumn());
	}
	
	/*
	* 查询表
	*/
	public function select() {
		$table = $this->options['table'];
		$field = $this->options['field'];
		$where = $this->parseCondition();
		$binds = $this->options['binds'];
		
		$table_add = '';
		
		if (isset($this->options['add_table']) && is_array($this->options['add_table'])) {
			foreach ($this->options['add_table'] as $value) {
				$table_add .= $value['table'];
			}
		}
		
		if (isset($this->options['joins']) && is_array($this->options['joins'])) {
			foreach ($this->options['joins'] as $value) {
				$table_add .= ' ' . $value['type'] . ' ' . $value['table'] . ' ON ' . $value['on'];
			}
		}
		
		$this->sql = "SELECT $field FROM $table $table_add $where";
		$sth = $this->exec($this->sql, $binds);
		$this->reset();
		return $sth->fetchAll();
	}
	
	/*
	* 查询并返回第一条记录
	*/
	public function find() {
		$this->options['limit'] = 1;	//限制只查询一条数据
		$data = $this->select();
		return isset($data[0]) ? $data[0] : false;
	}
	
	/*
	* 插入数据
	*/
	public function insert() {
		$table = $this->options['table'];
		$data = $this->parseData('add');
		$binds = $this->options['binds'];
		
		// 若更新的字段为空
		if ($data == false) return false;
		
        $this->sql = "INSERT INTO $table $data";
        $sth = $this->exec($this->sql, $binds);
		$this->reset();
        $affects = $sth->rowCount();
        
        if ($affects > 0) {
        	$id = $this->db->lastInsertId();
        	return empty($id) ? $affects : $id;
        } else {
        	return false;
        }
	}
	
	/*
	* 更新数据
	*/
	public function update() {
		$table = $this->options['table'];
		$data = $this->parseData('save');
		$where = $this->parseCondition();
		$binds = $this->options['binds'];
		
		// 若更新的字段为空
		if ($data == false) return false;
		// 修改条件为空时，则返回false，防止无修改整个表数据
		if (empty($where)) return false;
			
		$this->sql = "UPDATE $table SET $data $where";
		$sth = $this->exec($this->sql, $binds);
		$this->reset();
		return $sth->rowCount();
	}
	
	/*
	* 删除记录
	*/
	public function delete() {
		$table = $this->options['table'];
		$where = $this->parseCondition();
		$binds = $this->options['binds'];
		
		// 修改条件为空时，则返回false，防止删除所有表数据
		if (empty($where)) return false;
		
		$this->sql = "DELETE FROM $table $where";
	    $sth = $this->exec($this->sql, $binds);
		$this->reset();
		return $sth->rowCount();
	}
	
	/*
	 * 重置环境便于再次查询
	 */
	public function reset() {
		$this->options['table'] = '';
		$this->options['add_table'] = array();
		$this->options['field'] = '*';
		$this->options['where'] = '';
		$this->options['group'] = '';
		$this->options['having'] = '';
		$this->options['order'] = '';
		$this->options['limit'] = '';
		$this->options['binds'] = array();
		$this->options['joins'] = array();
		
		return $this;
	}
	
	/*
	* 获取SQL文
	*/
	public function getSQL() {
		return $this->lastsql;
	}
	
	/*
	* 解析条件
	*/
	private function parseCondition() {
		$condition = '';
		
		if(!empty($this->options['where']) && is_string($this->options['where'])) {
			$condition .= " WHERE " . $this->options['where'];
		}
		
		if( !empty($this->options['group']) && is_string($this->options['group']) ) {
			$condition .= " GROUP BY " . $this->options['group'];
		}
		
		if( !empty($this->options['having']) && is_string($this->options['having']) ) {
			$condition .= " HAVING " .  $this->options['having'];
		}
		
		if( !empty($this->options['order']) && is_string($this->options['order']) ) {
			$condition .= " ORDER BY " .  $this->options['order'];
		}
		
		if( !empty($this->options['limit']) && (is_string($this->options['limit']) || is_numeric($this->options['limit'])) ) {
			$condition .= " LIMIT " .  $this->options['limit'];
		}
		
		return $condition;		
	}
	
	/*
	* 解析数据
	*/
	private function parseData($type) {
		if (empty($this->options['field'])) {
			return false;
		}
		
		switch($type){
			case 'add':
				$data = array();
				$fields = explode(',', $this->options['field']);
				
				foreach($fields as $value) {
					$value = trim($value);
					$data[] = ":" . $value;
				}
				
				return " (`" . implode("`,`", $fields) . "`) VALUES (" . implode(",", $data) . ") ";
			case 'save':
				$data = array();
				$fields = explode(',', $this->options['field']);
				
				foreach($fields as $value) {
					$value = trim($value);
					$data[] = " `$value` = :" . $value;
				}
				
				return implode(',', $data);
			default:
				return false;
		}
	}
}
