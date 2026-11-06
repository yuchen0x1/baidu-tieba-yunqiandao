SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `{VAR-PREFIX}baiduid`;
CREATE TABLE `{VAR-PREFIX}baiduid` (
  `id` int(30) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(30) unsigned NOT NULL,
  `bduss` text NOT NULL,
  `stoken` text NULL DEFAULT NULL,
  `proxy` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '' NOT NULL,
  `name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' NOT NULL,
  `portrait` varchar(40) COLLATE utf8mb4_general_ci DEFAULT '' NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`) USING BTREE,
  KEY `portrait` (`portrait`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `{VAR-PREFIX}cron`;
CREATE TABLE `{VAR-PREFIX}cron` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL,
  `orde` int(10) NOT NULL DEFAULT '0',
  `file` varchar(100) DEFAULT '' NOT NULL,
  `no` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `desc` text,
  `freq` int(10) NOT NULL DEFAULT '0',
  `lastdo`  int(10) UNSIGNED NOT NULL DEFAULT 0 ,
  `log` text,
  `enable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `nextrun` int(10) unsigned NOT NULL DEFAULT '0',
  `retry` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `max_retry` tinyint(3) unsigned NOT NULL DEFAULT '2',
  `last_status` varchar(20) NOT NULL DEFAULT '',
  `last_duration` int(10) unsigned NOT NULL DEFAULT '0',
  `running` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `running_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`name`),
  UNIQUE KEY `name` (`name`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




INSERT INTO `{VAR-PREFIX}cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `lastdo`, `log`) VALUES ('system_sign', '0', 'lib/cron_system_sign.php', '0', '每天对所有贴吧进行签到\r\n忽略或卸载此任务会导致停止签到', '0', '0', '');
INSERT INTO `{VAR-PREFIX}cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `lastdo`, `log`) VALUES ('system_sign_retry', '1', 'lib/cron_system_sign_retry.php', '0', '对所有签到失败的贴吧进行复签\r\n忽略或卸载此任务会导致停止复签', '0', '0', '');
INSERT INTO `{VAR-PREFIX}cron` (`name`, `orde`, `file`, `no`, `desc`, `freq`, `lastdo`, `log`) VALUES ('system_dbbackup', '9', 'lib/cron_system_dbbackup.php', '0', '每日自动备份数据库到 data/backups 目录\r\n启用后需在设置中开启定时备份', '86400', '0', '');




DROP TABLE IF EXISTS `{VAR-PREFIX}options`;
CREATE TABLE `{VAR-PREFIX}options` (
  `name` varchar(191) NOT NULL,
  `value` text NOT NULL,
  UNIQUE KEY `name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




INSERT INTO `{VAR-PREFIX}options` VALUES ('system_url', '{VAR-SYSTEM-URL}');
INSERT INTO `{VAR-PREFIX}options` VALUES ('system_name', '贴吧云签到');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_limit', '10');
INSERT INTO `{VAR-PREFIX}options` VALUES ('tb_max', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_mode', 'a:2:{i:0;s:1:\"1\";i:1;s:1:\"3\";}');
INSERT INTO `{VAR-PREFIX}options` VALUES ('footer', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('ann', 'Hello World!');
INSERT INTO `{VAR-PREFIX}options` VALUES ('enable_reg', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('reg_mail_verify', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('captcha', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('yr_reg', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('yr_reg_multi', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('icp', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_mode', 'MAIL');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_name', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_yourname', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_host', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_port', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_auth', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_smtpname', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_smtppw', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('fb', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cloud', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('enable_addtieba', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('pwdmode', 'md5(md5(md5($pwd)))');
INSERT INTO `{VAR-PREFIX}options` VALUES ('retry_max', '10');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_order', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('fb_tables', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('dev', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_last_do_time', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_last_do', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_isdoing', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('db_backup_enable', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('db_backup_keep', '7');
INSERT INTO `{VAR-PREFIX}options` VALUES ('db_backup_email', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_pw', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_sleep', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_jitter', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_shuffle', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('client_version', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('client_rotate', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('proxy_type', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('proxy_host', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('proxy_user', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('proxy_pass', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('dama_type', 'none');
INSERT INTO `{VAR-PREFIX}options` VALUES ('dama_username', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('dama_password', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('dama_softid', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cktime', '999999');
INSERT INTO `{VAR-PREFIX}options` VALUES ('bduss_num', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_multith', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_asyn', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_asyn', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_sign_again', 'a:2:{s:3:\"num\";i:0;s:6:\"lastdo\";s:10:\"2000-01-01\";}');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_hour', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('mail_secure', 'none');
INSERT INTO `{VAR-PREFIX}options` VALUES ('freetable', 'tieba');
INSERT INTO `{VAR-PREFIX}options` VALUES ('core_version', '7.3.1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('core_revision', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('vid', '10000');
INSERT INTO `{VAR-PREFIX}options` VALUES ('update_server', '0');
#INSERT INTO `{VAR-PREFIX}options` VALUES ('toolpw', '{VAR-TOOLPW}');
INSERT INTO `{VAR-PREFIX}options` VALUES ('sign_scan', '2');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_enable', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_channel', 'none');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_serverchan_key', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_telegram_token', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_telegram_chatid', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_webhook_url', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_bark_url', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_pushplus_token', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_dingtalk_webhook', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_dingtalk_secret', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_wecom_webhook', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_feishu_webhook', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_wxpusher_token', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_wxpusher_uid', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_ntfy_server', 'https://ntfy.sh');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_ntfy_topic', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_on_fail', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('notify_on_health', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('api_token', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('crypto_key', '{VAR-COOKIE-SALT}');
INSERT INTO `{VAR-PREFIX}options` VALUES ('system_keywords', '贴吧云签到');
INSERT INTO `{VAR-PREFIX}options` VALUES ('system_description', '百度贴吧云签到，在服务器上配置好就无需进行任何操作便可以实现贴吧的全自动签到。配合插件使用还可实现云灌水、点赞、封禁、删帖、审查等功能。Git代码库 https://github.com/yuchen0x1/tieba-cloud-sign');
INSERT INTO `{VAR-PREFIX}options` VALUES ('bbs_us', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('bbs_pw', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('health_auto_disable', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('health_fail_max', '3');


DROP TABLE IF EXISTS `{VAR-PREFIX}plugins`;
CREATE TABLE `{VAR-PREFIX}plugins` (
  `name` varchar(50) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `ver` varchar(15) DEFAULT '' NOT NULL,
  `options` text,
  `order` int(10) unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




INSERT INTO `{VAR-PREFIX}plugins` VALUES ('wmzz_debug', '0', '2.0', '', '0');




DROP TABLE IF EXISTS `{VAR-PREFIX}tieba`;
CREATE TABLE `{VAR-PREFIX}tieba` (
  `id` int(30) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(30) unsigned NOT NULL,
  `pid` int(30) unsigned NOT NULL DEFAULT '0',
  `fid` int(30) unsigned NOT NULL DEFAULT '0',
  `tieba` varchar(200) DEFAULT '' NOT NULL,
  `no` tinyint(1) NOT NULL DEFAULT '0',
  `status`  mediumint(8) UNSIGNED NOT NULL DEFAULT '0' ,
  `latest` varchar(10) NOT NULL DEFAULT '0',
  `last_error` text,
  PRIMARY KEY (`id`),
  INDEX `uid` (`uid`) USING BTREE ,
  INDEX `latest` (`latest`) USING BTREE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;








DROP TABLE IF EXISTS `{VAR-PREFIX}users`;
CREATE TABLE `{VAR-PREFIX}users` (
  `id` int(30) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `pw` TEXT NOT NULL,
  `email` varchar(40) NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `t` varchar(20) NOT NULL DEFAULT 'tieba',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_login` INT UNSIGNED NOT NULL DEFAULT 0,
  `auth_version` INT UNSIGNED NOT NULL DEFAULT 0,
  `api_token_hash` CHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE,
  UNIQUE KEY `api_token_hash` (`api_token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;








DROP TABLE IF EXISTS `{VAR-PREFIX}users_options`;
CREATE TABLE `{VAR-PREFIX}users_options` (
  `id` int(30) NOT NULL AUTO_INCREMENT,
  `uid` int(30) NOT NULL,
  `name` varchar(191) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`) USING BTREE,
  KEY `name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `{VAR-PREFIX}baiduid_health`;
CREATE TABLE `{VAR-PREFIX}baiduid_health` (
  `pid` INT UNSIGNED NOT NULL PRIMARY KEY,
  `name` VARCHAR(40) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'unknown',
  `last_ok` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_fail` INT UNSIGNED NOT NULL DEFAULT 0,
  `fail_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_msg` TEXT,
  `updated_at` INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `{VAR-PREFIX}cron_log_batch`;
CREATE TABLE `{VAR-PREFIX}cron_log_batch` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` INT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(50) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT '',
  `message` TEXT,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`),
  KEY `action` (`action`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `{VAR-PREFIX}sign_history`;
CREATE TABLE `{VAR-PREFIX}sign_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` INT UNSIGNED NOT NULL DEFAULT 0,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `tieba` VARCHAR(200) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT '',
  `error_code` INT NOT NULL DEFAULT 0,
  `message` VARCHAR(500) NOT NULL DEFAULT '',
  `exp_gained` INT UNSIGNED NOT NULL DEFAULT 0,
  `sign_rank` INT UNSIGNED NOT NULL DEFAULT 0,
  `forum_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pid_created_at` (`pid`, `created_at`),
  KEY `uid_created_at` (`uid`, `created_at`),
  KEY `created_at` (`created_at`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `{VAR-PREFIX}login_audit`;
CREATE TABLE `{VAR-PREFIX}login_audit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `ua` VARCHAR(255) NOT NULL DEFAULT '',
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `ts` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `ip` (`ip`),
  KEY `ts` (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `{VAR-PREFIX}invites`;
CREATE TABLE `{VAR-PREFIX}invites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL DEFAULT '',
  `max_uses` INT UNSIGNED NOT NULL DEFAULT 1,
  `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `expire` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `{VAR-PREFIX}task_runs`;
CREATE TABLE `{VAR-PREFIX}task_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(40) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT '',
  `message` TEXT,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `started_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `finished_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `status` (`status`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `{VAR-PREFIX}api_rate_limits`;
CREATE TABLE `{VAR-PREFIX}api_rate_limits` (
  `token_hash` VARCHAR(64) NOT NULL DEFAULT '',
  `window_start` INT UNSIGNED NOT NULL DEFAULT 0,
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`token_hash`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_max_retry', '2');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_retry_backoff', '300');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_lock_timeout', '600');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_notify_on_fail', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cron_notify_interval', '3600');
INSERT INTO `{VAR-PREFIX}options` VALUES ('metrics_token', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('api_rate_limit', '60');
INSERT INTO `{VAR-PREFIX}options` VALUES ('webhook_enable', '0');
INSERT INTO `{VAR-PREFIX}options` VALUES ('webhook_url', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('webhook_secret', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('webhook_events', '');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cleanup_enable', '1');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cleanup_sign_history_days', '180');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cleanup_task_runs_days', '90');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cleanup_cron_log_days', '30');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cleanup_plugin_log_days', '90');
INSERT INTO `{VAR-PREFIX}options` VALUES ('cleanup_api_rate_days', '7');

DROP TABLE IF EXISTS `{VAR-PREFIX}schema_migrations`;
CREATE TABLE `{VAR-PREFIX}schema_migrations` (
  `version` VARCHAR(64) NOT NULL DEFAULT '',
  `applied_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `checksum` CHAR(64) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'success',
  `started_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `finished_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `batch_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_message` TEXT,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

# 新安装基线：结构与最新安装模板一致，无需重放历史迁移（checksum 留空按 legacy 成功处理）
INSERT INTO `{VAR-PREFIX}schema_migrations` (`version`, `applied_at`, `description`, `status`, `started_at`, `finished_at`) VALUES
  ('001001', UNIX_TIMESTAMP(), 'V5.13：cron 表调度增强列 + task_runs + api_rate_limits + 新增选项', 'success', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  ('007000', UNIX_TIMESTAMP(), 'V7.0：升级基线校验与调度结构修复（幂等，无破坏性变更）', 'success', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  ('007100', UNIX_TIMESTAMP(), 'V7.0：新增 cron_notify_interval 失败通知去重选项', 'success', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  ('007200', UNIX_TIMESTAMP(), 'V7.3：签到历史表增加成长经验/签到排名/贴吧等级支持', 'success', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
