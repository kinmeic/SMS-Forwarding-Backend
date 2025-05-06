<?php
header("Content-Type:text/html; charset=utf-8");

/**
 *	配置实例
 */
$Config = array();

/**
 * SQLITE 数据库连接
 */
$Config['sqlite']['host'] = __DIR__ . '/../db/sms.db';
$Config['sqlite']['name'] = '';
$Config['sqlite']['user'] = '';
$Config['sqlite']['pswd'] = '';

/**
 *	版本号
 */
$Config['ver'] = '1.0.0';
