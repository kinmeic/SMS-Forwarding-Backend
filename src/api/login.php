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
// if (!checkToken()) {
// 	$result['message'] = 'Invalid token';
// 	echo json_encode($result);
// 	exit;
// }

$methods = array();

/**
 * 登录
 */
$methods['login'] = function() {
    global $result;
    global $admin_id, $admin_role, $admin_username;

    $username = request('username');
    $password = request('password');

    $db = new SQLiteDriver();

    $row = $db->table('user')
        ->fields('id, role, token')
        ->where('username=:username AND password=:password')
        ->bind(':username', $username)
        ->bind(':password', md5($password))
        ->find();

    if (!empty($row)) {
        $token = $row['token'];
				
        if ($token == '') {
            // 更新Token
            $token = md5($username . time() . rand(0, 9999));
            
            $db->table('users')
                ->fields('token')
                ->where('id=:id')
                ->bind(':token', $token)
                ->bind(':id', $row['id'])
                ->update();
        }


        $result['status'] = 'ok';
        $result['token'] = $token;
    } else {
        $result['message'] = 'Invalid username or password';
    }
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
		$row = $db->table('users')
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