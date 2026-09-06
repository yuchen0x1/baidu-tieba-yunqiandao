<?php
/**
 * 迁移 007100：失败通知去重窗口
 * 新增选项 cron_notify_interval（秒，默认 3600，0 = 每次耗尽都告警），
 * 与 lib/class.cron.php 的渠道告警节流配套；幂等，可重复执行。
 */
return array(
    'version' => '007100',
    'desc' => 'V7.0：新增 cron_notify_interval 失败通知去重选项',
    'up' => function ($m, $prefix) {
        $k = $m->real_escape_string('cron_notify_interval');
        $row = $m->once_fetch_array("SELECT COUNT(*) AS c FROM `{$prefix}options` WHERE `name` = '{$k}'");
        if (empty($row['c'])) {
            $m->pquery("INSERT IGNORE INTO `{$prefix}options` (`name`, `value`) VALUES (?, ?)", array('cron_notify_interval', '3600'));
        }
    },
    'down' => function ($m, $prefix) {
        $m->pquery("DELETE FROM `{$prefix}options` WHERE `name` = ?", array('cron_notify_interval'));
    },
);
