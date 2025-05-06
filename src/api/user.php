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

/**
 * 取得用户手机号列表
 */
$methods['get_mobiles'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $rows = $db->table('mobile')
        ->where('user_id = :user_id')
        ->bind(':user_id', $admin_id)
        ->select();

    foreach($rows as $i => $row) {
        $rows[$i]['status'] = 0;

        if (time() - strtotime($row['heartbeat_at']) < 600) {
            $rows[$i]['status'] = 1;
        }
    }

    // 按status倒序排序
    usort($rows, function($a, $b) {
        if ($a['status'] == $b['status']) {
            return 0;
        }
        return ($a['status'] > $b['status']) ? -1 : 1;
    });

    $result['status'] = 'ok';
    $result['data'] = $rows;
};

$methods['get_mobile'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $id = request('id', 0, 'integer');

    $row = $db->table('mobile')
        ->where('id = :id AND user_id = :user_id')
        ->bind(':id', $id)
        ->bind(':user_id', $admin_id)
        ->find();

    if (!empty($row)) {
        $result['status'] = 'ok';
        $result['data'] = $row;
    }
    else {
        $result['message'] = '无效的手机编号';
    }
};

$methods['bind_mobile'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $mobile = request('mobile');
    $remark = request('remark');
    $pattern = '/^\+?\d+$/';

    if (!preg_match($pattern, $mobile)) {
		$result['message'] = '请输入有效的手机号';
	}
    else {
        // 查询是否已经被绑定
        $exists = $db->table('mobile')
            ->where('mobile = :mobile')
            ->bind(':mobile', $mobile)
            ->find();

        if (empty($exists)) {
            $rtnCd = $db->table('mobile')
                ->fields('mobile', 'user_id', 'remark')
                ->bind(':mobile', $mobile)
                ->bind(':user_id', $admin_id)
                ->bind(':remark', $remark)
                ->insert();

            if ($rtnCd > 0) {
                $result['status'] = 'ok';
                $result['message'] = '绑定成功';
            }
            else {
                $result['message'] = '绑定失败';
            }
        }
        else {
            $result['message'] = '该手机号已经被绑定';
        }
    }
};

$methods['unbind_mobile'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $id = request('id', 0, 'integer');

    $rtnCd = $db->table('mobile')
        ->where('id = :id AND user_id = :user_id')
        ->bind(':id', $id)
        ->bind(':user_id', $admin_id)
        ->delete();

    if ($rtnCd > 0) {
        $result['status'] = 'ok';
        $result['message'] = '解绑成功';
    }
    else {
        $result['message'] = '解绑失败';
    }
};

$methods['edit_bind_mobile'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $id = request('id', 0, 'integer');
    $remark = request('remark');

    $rtnCd = $db->table('mobile')
        ->fields('remark')
        ->where('id = :id AND user_id = :user_id')
        ->bind(':id', $id)
        ->bind(':user_id', $admin_id)
        ->bind(':remark', $remark)
        ->update();

    if ($rtnCd > 0) {
        $result['status'] = 'ok';
        $result['message'] = '更新成功';
    }
    else {
        $result['message'] = '更新失败';
    }
};

$methods['get_contacts'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();
    $keyword = request('keyword');
	$page = max(1, request('page', 1, 'integer'));
	$limit = max(1, request('limit', 30, 'integer'));
	$offset = ($page - 1) * $limit;

    $db->table('contacts')
        ->where('user_id = :user_id')
        ->bind(':user_id', $admin_id);

    if (strlen($keyword) > 0) {
        $db->add_where('(name LIKE :keyword OR mobile LIKE :keyword OR remark LIKE :keyword)')
            ->bind(':keyword', '%' . $keyword . '%');
    }

    $total = $db->count();

    $rows = $db->order('name ASC')
        ->limit($limit, $offset)
        ->select();

    $result['status'] = 'ok';
    $result['data'] = $rows;
    $result['count'] = count($rows);
    $result['total'] = $total;
    $result['page'] = $page;
    $result['pages'] = ceil($total / $limit);
};

$methods['get_contact'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $id = request('id', 0, 'integer');

    $row = $db->table('contacts')
        ->where('id = :id AND user_id = :user_id')
        ->bind(':id', $id)
        ->bind(':user_id', $admin_id)
        ->find();

    if (!empty($row)) {
        $result['status'] = 'ok';
        $result['data'] = $row;
    }
    else {
        $result['message'] = '无效的联系人编号';
    }
};

$methods['add_contact'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $name = request('name');
    $mobile = request('mobile');
    $remark = request('remark');
    $pattern = '/^\+?\d+$/';

    if (!preg_match($pattern, $mobile)) {
		$result['message'] = '请输入有效的手机号';
	}
    else {
        // 查询是否已经被绑定
        $exists = $db->table('contacts')
            ->where('mobile = :mobile AND user_id = :user_id')
            ->bind(':mobile', $mobile)
            ->bind(':user_id', $admin_id)
            ->find();

        if (empty($exists)) {
            $rtnCd = $db->table('contacts')
                ->fields('name', 'mobile', 'remark', 'user_id')
                ->bind(':name', $name)
                ->bind(':mobile', $mobile)
                ->bind(':remark', $remark)
                ->bind(':user_id', $admin_id)
                ->insert();

            if ($rtnCd > 0) {
                $result['status'] = 'ok';
                $result['message'] = '添加成功';
            }
            else {
                $result['message'] = '添加失败';
            }
        }
        else {
            $result['message'] = '该联系人已经添加';
        }
    }
};

$methods['edit_contact'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $id = request('id', 0, 'integer');
    $remark = request('remark');
    $pattern = '/^\+?\d+$/';
    $mobile = request('mobile');
    $name = request('name');

    if (!preg_match($pattern, $mobile)) {
		$result['message'] = '请输入有效的手机号';
	}
    else { 
        $rtnCd = $db->table('contacts')
            ->fields('name', 'mobile', 'remark')
            ->where('id = :id AND user_id = :user_id')
            ->bind(':id', $id)
            ->bind(':user_id', $admin_id)
            ->bind(':name', $name)
            ->bind(':mobile', $mobile)
            ->bind(':remark', $remark)
            ->update();

        if ($rtnCd > 0) {
            $result['status'] = 'ok';
            $result['message'] = '更新成功';
        }
        else {
            $result['message'] = '更新失败';
        }
    }
};

$methods['del_contact'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $id = request('id', 0, 'integer');

    $rtnCd = $db->table('contacts')
        ->where('id = :id AND user_id = :user_id')
        ->bind(':id', $id)
        ->bind(':user_id', $admin_id)
        ->delete();

    if ($rtnCd > 0) {
        $result['status'] = 'ok';
        $result['message'] = '删除成功';
    }
    else {
        $result['message'] = '删除失败';
    }
};

/**
 * 修改密码
 */
$methods['edit'] = function() {
    global $result;
    global $admin_id;

    $db = new SQLiteDriver();

    $old_password = request('old_password');
    $new_password = request('new_password');
    $new_password_confirm = request('new_password_confirm');

    if ($old_password == '') {
        $result['message'] = '请输入当前密码';
    }
    else if ($new_password == '') {
        $result['message'] = '请输入新密码';
    }
    else if ($new_password_confirm == '') {
        $result['message'] = '请输入确认密码';
    }
    else if ($new_password != $new_password_confirm) {
        $result['message'] = '新密码和确认密码不一致';
    }
    else {
        $rtnCd = $db->table('user')
            ->fields('password')
            ->where('id=:id AND password=:old_password')
            ->bind(':password', md5($new_password))
            ->bind(':id', $admin_id)
            ->bind(':old_password', md5($old_password))
            ->update();
        
        if ($rtnCd > 0) {
            $result['status'] = 'ok';
            $result['message'] = '修改密码成功';
        }
        else {
            $result['message'] = '修改密码失败';
        }
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