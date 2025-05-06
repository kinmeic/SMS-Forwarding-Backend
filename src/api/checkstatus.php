<?php
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/framework/SQLiteDriver.class.php';

require_once __DIR__ . '/TelegramBot/TypeInterface.php';
require_once __DIR__ . '/TelegramBot/BaseType.php';
require_once __DIR__ . '/TelegramBot/Types/User.php';
require_once __DIR__ . '/TelegramBot/Types/Chat.php';
require_once __DIR__ . '/TelegramBot/Types/Message.php';
require_once __DIR__ . '/TelegramBot/Types/MessageEntity.php';
require_once __DIR__ . '/TelegramBot/Types/ArrayOfMessageEntity.php';
require_once __DIR__ . '/TelegramBot/Events/EventCollection.php';
require_once __DIR__ . '/TelegramBot/BotApi.php';
require_once __DIR__ . '/TelegramBot/Client.php';

$db = new SQLiteDriver();

$rows = $db->table('mobile', 'm')
    ->left_join('user', 'u', 'u.id = m.user_id')
    ->fields(
        'm.id',
        'm.mobile',
        'm.heartbeat_at',
        'm.status',
        'u.push_tg_enable',
        'u.push_tg_token',
        'u.push_tg_chatid',
        'u.push_gotify_enable',
        'u.push_gotify_url',
        'u.push_gotify_token',
    )
    ->select();

foreach($rows as $row) {
    $status = intval($row['status']);
    $last_time = strtotime($row['heartbeat_at']);
    $diff_time = time() - $last_time;

    if ($diff_time > 600 && $status == 1) {
        // 离线
        $rtnCd = $db->table('mobile')
            ->fields('status')
            ->where('id = :id')
            ->bind(':status', 0)
            ->bind(':id', $row['id'])
            ->update();

        if ($rtnCd > 0) {
            $message = '手机号为【' . $row['mobile'] . '】的设备离线了';

            // 发送推送
            if ($row['push_tg_enable'] == 1) {
                push_telegram($row['push_tg_token'], $row['push_tg_chatid'], '#设备提醒' . PHP_EOL . $message);
            }

            if ($row['push_gotify_enable'] == 1) {
                push_gotify($row['push_gotify_url'], $row['push_gotify_token'], $message);
            }
        }
    }
    else if ($diff_time < 600 && $status == 0) {
        // 上线
        $rtnCd = $db->table('mobile')
            ->fields('status')
            ->where('id = :id')
            ->bind(':status', 1)
            ->bind(':id', $row['id'])
            ->update();

        if ($rtnCd > 0) {
            $message = '手机号为【' . $row['mobile'] . '】的设备上线了';

            // 发送推送
            if ($row['push_tg_enable'] == 1) {
                push_telegram($row['push_tg_token'], $row['push_tg_chatid'], '#设备提醒' . PHP_EOL . $message);
            }

            if ($row['push_gotify_enable'] == 1) {
                push_gotify($row['push_gotify_url'], $row['push_gotify_token'], $message);
            }
        }
    }
}

echo 'done.';

function push_telegram($token, $chatId, $message) {
    if ($token == '' || $chatId == '' || $message == '') return;

    try {
        $bot = new \TelegramBot\Api\Client($token);
        $bot->sendMessage($chatId, $message);
    } catch (\TelegramBot\Api\Exception $e) {
        $e->getMessage();
    }
}

function push_gotify($baseurl, $token, $message) {
    if ($baseurl == '' || $token == '' || $message == '') return;

    $push_data = [
        "title"=> '设备提醒',
        "message"=> $message,
        "priority"=> 5,
    ];

    $data_string = json_encode($push_data);

    $headers = [
        "Content-Type: application/json; charset=utf-8"
    ];

    $url = $baseurl . '?token=' . $token;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_exec($ch);
    curl_close($ch);
}

// 定时任务添加（每5分钟执行一次）：
// */5 * * * * /usr/bin/php /var/www/nps/sms/api/checkstatus.php >/dev/null 2>&1