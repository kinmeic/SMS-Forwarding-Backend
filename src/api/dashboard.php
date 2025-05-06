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

$methods['get_info'] = function() {
    global $result;
    global $admin_id;
	
	$db = new SQLiteDriver();

    $mobiles = $db->table('mobile')
        ->where('user_id=:user_id')
        ->bind(':user_id', $admin_id)
        ->select();

    $mobile_cnt = count($mobiles);
    $mobile_active_cnt = 0;

    foreach($mobiles as $m) {
        if (time() - strtotime($m['heartbeat_at']) < 600) {
            $mobile_active_cnt++;
        }
    }

    $db->reset();

    $inbox_cnt = $db->table('inbox')
        ->where('user_id=:user_id AND delete_at IS NULL')
        ->bind(':user_id', $admin_id)
        ->count();

    $db->reset();

    $sendboxs = $db->table('sendbox')
        ->where('user_id=:user_id AND delete_at IS NULL')
        ->bind(':user_id', $admin_id)
        ->select();

    $sendbox_cnt = count($sendboxs);
    $sendbox_sending_cnt = 0;

    foreach($sendboxs as $s) {
        if ($s['status'] == 0) {
            $sendbox_sending_cnt++;
        }
    }

    $contacts_cnt = $db->table('contacts')
        ->where('user_id=:user_id AND delete_at IS NULL')
        ->bind(':user_id', $admin_id)
        ->count();

    $result['status'] = 'ok';
    $result['data'] = array(
        'mobile_cnt' => $mobile_cnt,
        'mobile_active_cnt' => $mobile_active_cnt,
        'inbox_cnt' => $inbox_cnt,
        'sendbox_cnt' => $sendbox_cnt,
        'sendbox_sending_cnt' => $sendbox_sending_cnt,
        'contacts_cnt' => $contacts_cnt
    );
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