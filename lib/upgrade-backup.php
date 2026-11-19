<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/** Save the instance's tables before an administrator upgrades its database. */
function upgrade_auto_backup()
{
    global $m;
    $dir = SYSTEM_ROOT . '/data/backups';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('无法创建升级备份目录，数据库迁移已暂停');
    }
    $result = $m->pquery(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
         AND BINARY LEFT(TABLE_NAME, CHAR_LENGTH(?)) = BINARY ?
         ORDER BY TABLE_NAME",
        array(DB_PREFIX, DB_PREFIX)
    );
    if (!$result || $m->num_rows($result) === 0) {
        throw new RuntimeException('无法读取本站数据表，数据库迁移已暂停');
    }

    $file = $dir . '/pre-upgrade_' . date('Y-m-d_His') . '-' . bin2hex(random_bytes(4)) . '.sql';
    $temporary = $file . '.tmp';
    $stream = @fopen($temporary, 'xb');
    if ($stream === false) {
        throw new RuntimeException('无法创建数据库备份文件，数据库迁移已暂停');
    }
    $transaction = false;
    try {
        if (!$m->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY')) {
            throw new RuntimeException('无法建立一致性备份快照，数据库迁移已暂停');
        }
        $transaction = true;
        $tables = 0;
        while ($row = $m->fetch_row($result)) {
            $dump = dataBak($row[0]);
            if (!is_string($dump) || fwrite($stream, $dump) !== strlen($dump)) {
                throw new RuntimeException('数据库备份写入不完整，数据库迁移已暂停');
            }
            $tables++;
        }
        if (!fflush($stream)) {
            throw new RuntimeException('数据库备份刷新失败，数据库迁移已暂停');
        }
        if (!$m->query('COMMIT')) {
            throw new RuntimeException('无法结束一致性备份快照，数据库迁移已暂停');
        }
        $transaction = false;
        fclose($stream);
        if (!rename($temporary, $file)) {
            throw new RuntimeException('数据库备份无法保存，数据库迁移已暂停');
        }
    } finally {
        if ($transaction) {
            $m->query('ROLLBACK');
        }
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
    return '已备份数据库：' . basename($file) . '（' . $tables . ' 张表）';
}
