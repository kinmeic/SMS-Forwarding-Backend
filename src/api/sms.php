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

$methods['get_inbox'] = function() {
    global $result;
    global $admin_id;
	
	$db = new SQLiteDriver();
	$keyword = request('keyword');
	$page = max(1, request('page', 1, 'integer'));
	$limit = max(1, request('limit', 30, 'integer'));
	$offset = ($page - 1) * $limit;

	$db->table('inbox', 'i')
		->where('i.user_id = :user_id')
		->add_where('i.delete_at IS NULL')
		->bind(':user_id', $admin_id);

	$total = $db->count();

	$rows = $db->order('i.create_at DESC')
		->limit($limit, $offset)
		->select();

	$result['status'] = 'ok';
	$result['data'] = $rows;
	$result['count'] = count($rows);
	$result['total'] = $total;
	$result['page'] = $page;
	$result['pages'] = ceil($total / $limit);
};

$methods['remove_inbox_message'] = function() {
	global $result;
    global $admin_id;

	$db = new SQLiteDriver();

	$id = request('id', 0, 'integer');

	if ($id > 0) {
		$rtnCd = $db->table('inbox')
			->fields('delete_at')
			->where('id = :id')
			->bind(':id', $id)
			->bind(':delete_at', date('Y-m-d H:i:s'))
			->update();

		if ($rtnCd > 0) {
			$result['status'] = 'ok';
			$result['message'] = '删除成功';
		}
		else {
			$result['message'] = '删除失败';
		}
	}
	else {
		$result['message'] = '无效的收件消息编号';
	}
};

$methods['get_sendbox'] = function() {
    global $result;
    global $admin_id;
	
	$db = new SQLiteDriver();
	$keyword = request('keyword');
	$page = max(1, request('page', 1, 'integer'));
	$limit = max(1, request('limit', 30, 'integer'));
	$offset = ($page - 1) * $limit;

	$db->table('sendbox', 's')
		->where('s.user_id = :user_id')
		->add_where('s.delete_at IS NULL')
		->bind(':user_id', $admin_id);

	$total = $db->count();

	$rows = $db->order('s.create_at DESC')
		->limit($limit, $offset)
		->select();

	$result['status'] = 'ok';
	$result['data'] = $rows;
	$result['count'] = count($rows);
	$result['total'] = $total;
	$result['page'] = $page;
	$result['pages'] = ceil($total / $limit);
};

$methods['get_push_data'] = function() {
	global $result;
    global $admin_id;

    $db = new SQLiteDriver();

	$row = $db->table('user')
		->fields('push_tg_enable', 'push_tg_token', 'push_tg_chatid', 'push_email_enable', 'push_email', 'push_gotify_enable', 'push_gotify_url', 'push_gotify_token')
		->where('id = :id')
		->bind(':id', $admin_id)
		->find();

	if (!empty($row)) {
		$result['status'] = 'ok';
		$result['data'] = $row;
	}
	else {
		$result['message'] = '无效的用户';
	}
};

$methods['set_push_data'] = function() {
	global $result;
    global $admin_id;

    $db = new SQLiteDriver();

	$push_tg_enable = request('push_tg_enable', 0, 'integer');
	$push_tg_token = request('push_tg_token');
	$push_tg_chatid = request('push_tg_chatid');
	$push_email_enable = request('push_email_enable', 0, 'integer');
	$push_email = request('push_email');
	$push_gotify_enable = request('push_gotify_enable', 0, 'integer');
	$push_gotify_url = request('push_gotify_url');
	$push_gotify_token = request('push_gotify_token');

	$rtnCd = $db->table('user')
		->fields('push_tg_enable', 'push_tg_token', 'push_tg_chatid', 'push_email_enable', 'push_email', 'push_gotify_enable', 'push_gotify_url', 'push_gotify_token')
		->where('id = :id')
		->bind('push_tg_enable', $push_tg_enable)
		->bind('push_tg_token', $push_tg_token)
		->bind('push_tg_chatid', $push_tg_chatid)
		->bind('push_email_enable', $push_email_enable)
		->bind('push_email', $push_email)
		->bind('push_gotify_enable', $push_gotify_enable)
		->bind('push_gotify_url', $push_gotify_url)
		->bind('push_gotify_token', $push_gotify_token)
		->bind('id', $admin_id)
		->update();

	if ($rtnCd > 0) {
		$result['status'] = 'ok';
		$result['message'] = '更新成功';
	}
	else {
		$result['message'] = '更新失败';
	}
};

$methods['send'] = function() {
	global $result;
    global $admin_id;

	$db = new SQLiteDriver();

	$sender_mobile = request('sender_mobile');
	$receiver_mobile = request('receiver_mobile');
	$message = request('message');
	$message = preg_replace('/\s+|　/', '', $message);

	// 检查发送人手机号是否属于该用户
	$rows = $db->table('mobile')
        ->where('user_id = :user_id')
        ->bind(':user_id', $admin_id)
        ->select();

	$mobiles = array_column($rows, 'mobile');
	$pattern = '/^\+?\d+$/';

	if (!preg_match($pattern, $receiver_mobile)) {
		$result['message'] = '请输入有效的收件人手机号';
	}
	else if (in_array($sender_mobile, $mobiles)) {
		$rtnCd = $db->table('sendbox')
			->fields('user_id', 'receiver_mobile', 'sender_mobile', 'message')
			->bind(':user_id', $admin_id)
			->bind(':receiver_mobile', $receiver_mobile)
			->bind(':sender_mobile', $sender_mobile)
			->bind(':message', $message)
			->insert();

		if ($rtnCd > 0) {
			$result['status'] = 'ok';
			$result['message'] = '发送任务添加成功';
		}
		else {
			$result['message'] = '发送任务添加失败';
		}
	}
	else {
		$result['message'] = '无效的发件人手机号';
	}
};

$methods['remove_send_task'] = function() {
	global $result;
    global $admin_id;

	$db = new SQLiteDriver();

	$id = request('id', 0, 'integer');

	if ($id > 0) {
		$row = $db->table('sendbox')
			->fields('status')
			->where('id = :id AND user_id = :user_id')
			->bind(':id', $id)
			->bind(':user_id', $admin_id)
			->find();

		if (!empty($row)) {
			$status = $row['status'];

			if ($status == 0 || $status == 2) {
				$rtnCd = $db->table('sendbox')
					->where('id = :id')
					->bind(':id', $id)
					->delete();

				if ($rtnCd > 0) {
					$result['status'] = 'ok';
					$result['message'] = '删除成功';
				}
				else {
					$result['message'] = '删除失败';
				}
			}
			else if ($status == 1) {
				$rtnCd = $db->table('sendbox')
					->fields('delete_at')
					->where('id = :id')
					->bind(':delete_at', date('Y-m-d H:i:s'))
					->bind(':id', $id)
					->update();

				if ($rtnCd > 0) {
					$result['status'] = 'ok';
					$result['message'] = '删除成功';
				}
				else {
					$result['message'] = '删除失败';
				}
			}
		}
		else {
			$result['message'] = '无效的发件编号';
		}
	}
	else {
		$result['message'] = '无效的发件编号';
	}
};

/**
 * 查询用户角色信息
 */
$methods['getrole'] = function() {
    global $result;
    global $admin_id, $admin_role, $admin_username;

    $result['status'] = 'ok';
    $result['data'] = array(
        'id' => $admin_id,
        'role' => $admin_role,
        'username' => $admin_username
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