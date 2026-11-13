<?php
/** 真实 MariaDB 的提醒重试、日报回退和摘要批次确认；邮件替身不会向外部发送消息。 */
if (PHP_SAPI !== 'cli') { die('仅支持命令行运行'); }
require __DIR__ . '/lib-integration.php';
list($conn, $port) = it_connect_db();
$db = 'tcs_it_notify_' . getmypid() . '_' . mt_rand(1000, 9999);
define('SYSTEM_ROOT', dirname(__DIR__));
define('DB_PREFIX', 'tci_');
define('DB_NAME', $db);
define('SYSTEM_FN', 'Notification Integration');
define('SYSTEM_URL', 'http://127.0.0.1/');
require SYSTEM_ROOT . '/lib/request.php';
require SYSTEM_ROOT . '/lib/security.php';
require SYSTEM_ROOT . '/lib/sfc.functions.php';
require SYSTEM_ROOT . '/lib/class.mysqli.php';
require SYSTEM_ROOT . '/lib/class.option.php';
require SYSTEM_ROOT . '/lib/class.Reminder.php';
require SYSTEM_ROOT . '/lib/cron_system_daily_report.php';
require SYSTEM_ROOT . '/lib/cron_system_weekreport.php';
require SYSTEM_ROOT . '/plugins/ver4_monitor/cron/monitor.php';

class IntegrationReminderMailer extends Reminder
{
    public static bool $accept = false;
    public static array $messages = array();
    protected static function sendToUser($uid, $title, $content)
    {
        self::$messages[] = array('uid' => $uid, 'title' => $title, 'content' => $content);
        return array('ok' => self::$accept, 'detail' => self::$accept ? '本地邮件替身接受' : '本地邮件替身失败');
    }
}

try {
    $conn->query("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $sql = str_replace(array('{VAR-DB}', '{VAR-COOKIE-SALT}', '{VAR-SYSTEM-URL}'), array($db, 'fixture-salt', SYSTEM_URL), file_get_contents(SYSTEM_ROOT . '/setup/install.template.sql'));
    it_check('全新安装结构可用', it_import_sql($conn, $db, $sql, DB_PREFIX));
    $host = (getenv('TCS_IT_DB_HOST') ?: '127.0.0.1') . ':' . $port;
    $m = new wmysql($host, getenv('TCS_IT_DB_USER') ?: 'root', getenv('TCS_IT_DB_PASS') ?: '', $db);
    $i = array('user' => array('uid' => 0, 'opt' => array()), 'opt' => array('notify_enable' => '0', 'daily_report_hour' => '0'));
    $m->query("INSERT INTO tci_users (name,pw,email,role,t) VALUES ('notify1','fixture','','user','tieba'),('notify2','fixture','notify@example.test','user','tieba')");
    $users = array();
    $rs = $m->query('SELECT id FROM tci_users ORDER BY id');
    while ($row = $m->fetch_array($rs)) { $users[] = (int) $row['id']; }
    foreach ($users as $uid) {
        foreach (array('remind_sign_fail', 'remind_daily_summary', 'weekly_report_enable') as $key) { option::uset($key, '1', $uid); }
        option::uset('weekly_report_day', date('w'), $uid);
        SignManager::logTiebaResult(1, $uid, '测试吧', 'error', 1, '最新失败原因', 0);
        SignManager::logTiebaResult(1, $uid, '测试吧', 'success', 0, '后续成功记录', 2);
    }
    $failed = IntegrationReminderMailer::checkSignFailures();
    it_check('发送失败按用户报告', count($failed) === 2 && !$failed[0]['ok']);
    it_check('失败原因不被后续成功覆盖', strpos(IntegrationReminderMailer::$messages[0]['content'], '最近一次失败原因：最新失败原因') !== false);
    it_check('失败不写当日去重标记', option::uget('remind_last_sent_sign_fail', $users[0]) === null);
    it_check('记录安全的失败状态', json_decode(option::uget('remind_result_sign_fail', $users[0]), true)['status'] === 'failed');
    IntegrationReminderMailer::$accept = true;
    $retry = IntegrationReminderMailer::checkSignFailures();
    it_check('失败可在下次恢复发送', count($retry) === 2 && $retry[0]['ok']);
    it_check('接受后才写当日去重标记', option::uget('remind_last_sent_sign_fail', $users[0]) === date('Ymd'));
    it_check('同日成功后不重复发送', IntegrationReminderMailer::checkSignFailures() === array());

    $i['opt']['notify_enable'] = '1';
    $i['opt']['notify_channel'] = 'none';
    $daily = IntegrationReminderMailer::sendDailySummary();
    it_check('未选择渠道仍回退邮箱', count($daily) === 2 && $daily[0]['ok']);
    it_check('日报使用尝试次数避免重复贴吧计数', strpos(IntegrationReminderMailer::$messages[4]['content'], '共尝试签到 2 次') !== false);
    it_check('回退成功记录已接受', json_decode(option::uget('remind_result_daily_summary', $users[0]), true)['status'] === 'accepted');
    it_check('日报去重保留', IntegrationReminderMailer::sendDailySummary() === array());

    foreach ($users as $uid) { option::udela('remind_last_sent_daily_summary', $uid); }
    // 一个无邮箱用户、一个无用户记录的历史 UID：均在发送前返回，绝不调用邮件传输。
    $m->pquery('DELETE FROM tci_users WHERE id=?', array($users[1]));
    $cron = cron_system_daily_report(); // 实际无邮箱、无渠道，必须报告失败。
    it_check('日报调度不把尝试数当成功数且支持零点', strpos($cron, '渠道已接受 0 位用户，失败 2 位') !== false);
    it_check('双渠道失败不写已发送标记', option::uget('remind_last_sent_daily_summary', $users[0]) === null);
    $weekly = cron_system_weekreport();
    it_check('周报跳过不计成功', strpos($weekly, '渠道已接受 0 位用户，失败或跳过 1 位') !== false);
    it_check('周报未发送不锁定六天', option::uget('weekly_report_lastrun', $users[0]) === null);
    it_check('周报记录未发送状态', json_decode(option::uget('remind_result_weekreport', $users[0]), true)['status'] === 'skipped');

    $m->query('CREATE TABLE tci_ver4_monitor_hit (id INT PRIMARY KEY, uid INT, notified INT DEFAULT 0)');
    for ($n = 1; $n <= 35; $n++) { $m->pquery('INSERT INTO tci_ver4_monitor_hit (id,uid) VALUES (?,?)', array($n, $users[0])); }
    $m->pquery('INSERT INTO tci_ver4_monitor_hit (id,uid) VALUES (36,?)', array($users[1]));
    it_check('失败摘要不确认记录', !ver4_monitor_acknowledge_digest($users[0], range(1, 30), array('ok' => false)));
    it_check('跳过摘要不确认记录', !ver4_monitor_acknowledge_digest($users[0], range(1, 30), array('ok' => true, 'delivered' => false)));
    it_check('成功确认首批三十条', ver4_monitor_acknowledge_digest($users[0], range(1, 30), array('ok' => true, 'delivered' => true)));
    it_check('未发送批次保持待推送', (int) it_query_value($conn, $db, 'SELECT COUNT(*) FROM tci_ver4_monitor_hit WHERE uid=' . $users[0] . ' AND notified=0') === 5);
    ver4_monitor_acknowledge_digest($users[0], array(36), array('ok' => true));
    it_check('不能确认其他用户的记录', (int) it_query_value($conn, $db, 'SELECT notified FROM tci_ver4_monitor_hit WHERE id=36') === 0);
} finally {
    // 只清理本次创建的随机测试库。
    $conn->query("DROP DATABASE `{$db}`");
    $conn->close();
}
it_finish();
