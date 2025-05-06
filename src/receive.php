<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/api/config.inc.php';
require_once __DIR__ . '/api/framework/SQLiteDriver.class.php';

require_once __DIR__ . '/api/TelegramBot/TypeInterface.php';
require_once __DIR__ . '/api/TelegramBot/BaseType.php';
require_once __DIR__ . '/api/TelegramBot/Types/User.php';
require_once __DIR__ . '/api/TelegramBot/Types/Chat.php';
require_once __DIR__ . '/api/TelegramBot/Types/Message.php';
require_once __DIR__ . '/api/TelegramBot/Types/MessageEntity.php';
require_once __DIR__ . '/api/TelegramBot/Types/ArrayOfMessageEntity.php';
require_once __DIR__ . '/api/TelegramBot/Events/EventCollection.php';
require_once __DIR__ . '/api/TelegramBot/BotApi.php';
require_once __DIR__ . '/api/TelegramBot/Client.php';

require_once __DIR__ . '/api/PHPMailer/class.phpmailer.php';
require_once __DIR__ . '/api/PHPMailer/class.smtp.php';

// 接口消息
$result = array(
	'status' => 'failed',
	'message' => ''
);

$message = urldecode(request('message'));
$receiver = urldecode(request('receiver'));
$receiver = str_replace('+', '', $receiver);
$receiver = trim($receiver);
$sender = urldecode(request('sender'));
$sender = str_replace('+', '', $sender);
$sender = trim($sender);
$device = urldecode(request('device'));

// 区号处理
$receiver_without_86 = (strpos($receiver, "86") === 0) ? substr($receiver, 2) : $receiver;

if ($message == '') {
	$result['message'] = '消息不能为空';
}
else {
	// 查询收件人是否存在
	$db = new SQLiteDriver();

	$exists = $db->table('mobile')
		->fields('user_id', 'mobile')
		->where('mobile = :mobile OR mobile = :mobile_without_86')
		->bind(':mobile', $receiver)
		->bind(':mobile_without_86', $receiver_without_86)
		->find();

	if (!empty($exists)) {
		// 插入信息
		$db->reset();
		$rtnCd = $db->table('inbox')
			->fields('message', 'sender_mobile', 'receiver_mobile', 'device', 'user_id')
			->bind(':message', $message)
			->bind(':sender_mobile', $sender)
			->bind(':receiver_mobile', $exists['mobile'])
			->bind(':device', $device)
			->bind(':user_id', $exists['user_id'])
			->insert();

		if ($rtnCd > 0) {
			$result['status'] = 'ok';
			$result['data'] = $rtnCd;
		}

		// 获取推送配置信息
		$db->reset();
		$pushConfigs = $db->table('user')
			->fields('push_tg_enable', 'push_tg_token', 'push_tg_chatid', 'push_email_enable', 'push_email', 'push_gotify_enable', 'push_gotify_url', 'push_gotify_token')
			->where('id = :id')
			->bind(':id', $exists['user_id'])
			->find();

		if (!empty($pushConfigs)) {
			$result['push'] = array();

			$tpl = <<< 'PUSH_MESSAGE'
				{message}
				发件人：{sender}
				收件人：{receiver}
				设备：{device}
				PUSH_MESSAGE;

			$push_message = str_replace(
				array('{message}', '{sender}', '{receiver}', '{device}'),
				array($message, $sender, $receiver, $device),
				$tpl
			);

			if (
				$pushConfigs['push_tg_enable'] == 1
				&& $pushConfigs['push_tg_token'] != ''
				&& $pushConfigs['push_tg_chatid'] != ''
			) {
				// 推送到Telegram
				try {
					$bot = new \TelegramBot\Api\Client($pushConfigs['push_tg_token']);
					$bot->sendMessage($pushConfigs['push_tg_chatid'], '#消息推送' . PHP_EOL . $push_message);
					$result['push'][] = 'telegram';
				} catch (\TelegramBot\Api\Exception $e) {
					$e->getMessage();
				}
			}

			if (
				$pushConfigs['push_email_enable'] == 1
				&& $pushConfigs['push_email'] != ''
			) {
				// 推送到邮件
				try {
					$mailer = new PHPMailer();
					$mailer->SMTPDebug = false;
					$mailer->CharSet = 'UTF-8';
					$mailer->isSMTP();
					$mailer->Host = 'smtp.126.com';
					$mailer->SMTPAuth = true;
					$mailer->Username = 'kinmeic@126.com';
					$mailer->Password = 'VWCICANOBMHDSXOL';
					$mailer->SMTPSecure = '';
					$mailer->Port = 25;
					$mailer->setFrom('kinmeic@126.com', 'No1.通知小助手');
					$mailer->isHTML(true);
					$mailer->addAddress($pushConfigs['push_email']);
					$mailer->Subject = '新消息推送';
					$mailer->Body = urldecode($push_message);
					$response = $mailer->send();
					$result['push'][] = 'mail';
				} catch (Exception $e) {
					$e->getMessage();
				}
			}

			if (
				$pushConfigs['push_gotify_enable'] == 1
				&& $pushConfigs['push_gotify_url'] != ''
				&& $pushConfigs['push_gotify_token'] != ''
			) {
				// 推送到Gotify
				$push_data = [
					"title"=> "短信转发",
					"message"=> $push_message,
					"priority"=> 5,
				];

				$data_string = json_encode($push_data);

				$headers = [
					"Content-Type: application/json; charset=utf-8"
				];

				$url = $pushConfigs['push_gotify_url'] . '?token=' . $pushConfigs['push_gotify_token'];

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers );
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
				if(curl_exec($ch) !== false) {
					$result['push'][] = 'gotify';
				}
				curl_close($ch);
			}
		}
	}
	else {
		$result['message'] = '无效的收件人';
	}
}

// 输出返回消息
echo json_encode($result);

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