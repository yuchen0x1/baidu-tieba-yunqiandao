CREATE TABLE IF NOT EXISTS `{VAR-DB}`.`{VAR-TABLE}` (
  `id` int(30) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(30) unsigned NOT NULL,
  `pid` int(30) unsigned NOT NULL DEFAULT '0',
  `fid` int(30) unsigned NOT NULL DEFAULT '0',
  `tieba` varchar(200) DEFAULT NULL,
  `no` tinyint(1) NOT NULL DEFAULT '0',
  `status`  mediumint(8) UNSIGNED NOT NULL DEFAULT '0' ,
  `latest` varchar(10) NOT NULL DEFAULT '0',
  `last_error` text,
  PRIMARY KEY (`id`),
  INDEX `uid` (`uid`) USING BTREE ,
  INDEX `latest` (`latest`) USING BTREE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
