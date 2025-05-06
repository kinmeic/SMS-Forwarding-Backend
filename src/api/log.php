<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/framework/SQLiteDriver.class.php';

// 接口消息
$result = array(
	'status' => 'failed',
	'message' => ''
);

$admin_id = 0;
$admin_username = '';
$admin_role = 0;

// 检查用户权限
if (!checkToken()) {
	$result['message'] = 'Invalid token';
	echo json_encode($result);
	exit;
}

$methods = array();

$methods['get_logs'] = function() {
    global $result;
    global $admin_id;
	
	$db = new SQLiteDriver();
	$page = max(1, request('page', 1, 'integer'));
	$limit = max(1, request('limit', 30, 'integer'));
	$offset = ($page - 1) * $limit;

	$db->table('log', 'l');

	$total = $db->count();

	$rows = $db->order('l.create_at DESC')
		->limit($limit, $offset)
		->select();

	$result['status'] = 'ok';
	$result['data'] = $rows;
	$result['count'] = count($rows);
	$result['total'] = $total;
	$result['page'] = $page;
	$result['pages'] = ceil($total / $limit);
};

// 接口处理
$do = request('do');

if (isset($methods[$do])) {
	$methods[$do]();
}
else {
	$result['message'] = 'Invalid action identifier';
}

// 输出接口消息
$method = $_SERVER['REQUEST_METHOD'];
$callback = request('callback');

if ($method == 'POST') {
	echo json_encode($result);
} else if ($callback == '') {
	echo json_encode($result);
} else {
	echo $callback . '(' . json_encode($result) . ');';
}

/**
 * @param string $name
 * @param string $default
 * @return mixed
 */
function request($name, $def='', $type='string') {
	$value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : $def;

	switch($type) {
		case 'string':
			return trim(strval($value));
		case 'integer':
			return isset($_REQUEST[$name]) ? intval($_REQUEST[$name]) : intval($def);
		case 'float':
			return isset($_REQUEST[$name]) ? floatval($_REQUEST[$name]) : floatval($def);
		default:
			return  $value;
	}
}

/**
 * 检查token逻辑
 */
function checkToken() {
	$db = new SQLiteDriver();

	$token = request('token');
	
	if ($token != '') {
		$row = $db->table('user')
			->where('token=:token')
			->bind(':token', $token)
			->find();
		
		if (!empty($row) && $row['role'] >= 3) {
            global $admin_id, $admin_username, $admin_role;
            $admin_id = $row['id'];
            $admin_username = $row['username'];
            $admin_role = $row['role'];
			return true;
		}
	}
}