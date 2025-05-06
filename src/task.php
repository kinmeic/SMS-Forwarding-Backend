<?php
require_once __DIR__ . '/api/config.inc.php';
require_once __DIR__ . '/api/framework/SQLiteDriver.class.php';

// 接口消息
$result = array(
	'status' => 'failed',
	'message' => ''
);

$methods = array();

/**
 * 取得用户手机号列表
 */
$methods['get_task'] = function() {
    global $result;

    $db = new SQLiteDriver();

    $mobile = urldecode(request('mobile'));
    $mobile = str_replace('+', '', $mobile);
    $mobile = trim($mobile);

    // 区号处理
    $mobile_without_86 = (strpos($mobile, "86") === 0) ? substr($mobile, 2) : $mobile;

    // 查询手机号是否存在
    $exists = $db->table('mobile')
        ->where('mobile = :mobile OR mobile = :mobile_without_86')
        ->bind(':mobile', $mobile)
        ->bind(':mobile_without_86', $mobile_without_86)
        ->find();

    if (!empty($exists)) {
        $db->reset();
        $rows = $db->table('sendbox')
            ->fields('id', 'receiver_mobile', 'message')
            ->where('(sender_mobile = :sender_mobile OR sender_mobile = :sender_mobile_without_86) AND status=0')
            ->bind(':sender_mobile', $mobile)
            ->bind(':sender_mobile_without_86', $mobile_without_86)
            ->order('create_at ASC')
            ->select();

        $result['status'] = 'ok';
        $result['data'] = $rows;
    }
    else {
        // 插入异常日志
        $log_content = sprintf('手机号 %s 不存在', $mobile);

        $db->reset();
        $db->table('log')
            ->fields('content', 'channel')
            ->bind(':content', $log_content)
            ->bind(':channel', 'TASK')
            ->insert();
    }
};

$methods['finish'] = function() {
    global $result;

    $db = new SQLiteDriver();

    $id = request('id', 0, 'integer');
    $mobile = urldecode(request('mobile'));
    $mobile = str_replace('+', '', $mobile);
    $mobile = trim($mobile);
    
    // 区号处理
    $mobile_without_86 = (strpos($mobile, "86") === 0) ? substr($mobile, 2) : $mobile;

    $rtnCd = $db->table('sendbox')
        ->fields('status')
        ->where('id = :id AND (sender_mobile = :sender_mobile OR sender_mobile = :sender_mobile_without_86)')
        ->bind(':status', 1)
        ->bind(':id', $id)
        ->bind(':sender_mobile', $mobile)
        ->bind(':sender_mobile_without_86', $mobile_without_86)
        ->update();

    if ($rtnCd > 0) {
        $result['status'] = 'ok';
        $result['message'] = '更新成功';
    }
    else {
        $result['message'] = '更新失败';
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
