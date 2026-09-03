<?php

if (!defined('SYSTEM_ROOT')) { die('Insufficient Permissions'); }

require_once SYSTEM_ROOT . '/lib/class.SignManager.php';

/**
 * 成就 / 勋章系统
 *
 * 设计目标：
 *  - 静态定义所有可解锁成就（code / name / desc / tier / icon / metric / threshold）
 *  - 提供 ensureTable() 自动建表，避免升级脚本依赖
 *  - checkAndUnlock($uid) 一次性计算用户指标并解锁新成就，返回本次新解锁列表
 *  - unlocked($uid) / progress($uid) 用于展示页
 *
 * 调用时机：
 *  - lib/cron_system_sign.php 完成签到后批量检查今日活跃 uid
 *  - templates/achievements.php 提供「刷新成就」按钮即时调用
 *  - 任意业务点通过 doAction('achievements_check', $uid) 触发
 */
class Achievement
{
    const TABLE = 'user_achievements';

    // 成就 tier，决定卡片配色
    const TIER_BRONZE   = 'bronze';
    const TIER_SILVER   = 'silver';
    const TIER_GOLD     = 'gold';
    const TIER_PLATINUM = 'platinum';

    /**
     * 全部成就定义。键为唯一 code。
     * metric 可选值：current_streak / max_streak / total_success / total_signed_days / max_streak_30d
     * 任何新增成就只需在此数组追加一项即可，无需改动其他代码。
     */
    public static function definitions()
    {
        return array(
            'streak_3' => array(
                'name'      => '初露锋芒',
                'desc'      => '连续签到 3 天',
                'tier'      => self::TIER_BRONZE,
                'icon'      => 'i-flame',
                'metric'    => 'max_streak',
                'threshold' => 3,
            ),
            'streak_7' => array(
                'name'      => '一周不间断',
                'desc'      => '连续签到 7 天',
                'tier'      => self::TIER_BRONZE,
                'icon'      => 'i-flame',
                'metric'    => 'max_streak',
                'threshold' => 7,
            ),
            'streak_30' => array(
                'name'      => '月度坚守',
                'desc'      => '连续签到 30 天',
                'tier'      => self::TIER_SILVER,
                'icon'      => 'i-flame',
                'metric'    => 'max_streak',
                'threshold' => 30,
            ),
            'streak_100' => array(
                'name'      => '百日传奇',
                'desc'      => '连续签到 100 天',
                'tier'      => self::TIER_GOLD,
                'icon'      => 'i-flame',
                'metric'    => 'max_streak',
                'threshold' => 100,
            ),
            'streak_365' => array(
                'name'      => '全年无休',
                'desc'      => '连续签到 365 天',
                'tier'      => self::TIER_PLATINUM,
                'icon'      => 'i-award',
                'metric'    => 'max_streak',
                'threshold' => 365,
            ),
            'total_10' => array(
                'name'      => '初出茅庐',
                'desc'      => '累计成功签到 10 次',
                'tier'      => self::TIER_BRONZE,
                'icon'      => 'i-target',
                'metric'    => 'total_success',
                'threshold' => 10,
            ),
            'total_100' => array(
                'name'      => '勤奋吧友',
                'desc'      => '累计成功签到 100 次',
                'tier'      => self::TIER_SILVER,
                'icon'      => 'i-target',
                'metric'    => 'total_success',
                'threshold' => 100,
            ),
            'total_500' => array(
                'name'      => '签到达人',
                'desc'      => '累计成功签到 500 次',
                'tier'      => self::TIER_GOLD,
                'icon'      => 'i-target',
                'metric'    => 'total_success',
                'threshold' => 500,
            ),
            'total_2000' => array(
                'name'      => '签到大师',
                'desc'      => '累计成功签到 2000 次',
                'tier'      => self::TIER_PLATINUM,
                'icon'      => 'i-award',
                'metric'    => 'total_success',
                'threshold' => 2000,
            ),
            'days_30' => array(
                'name'      => '活跃分子',
                'desc'      => '累计签到 30 个不同日期',
                'tier'      => self::TIER_BRONZE,
                'icon'      => 'i-calendar',
                'metric'    => 'total_signed_days',
                'threshold' => 30,
            ),
            'days_100' => array(
                'name'      => '坚持不懈',
                'desc'      => '累计签到 100 个不同日期',
                'tier'      => self::TIER_SILVER,
                'icon'      => 'i-calendar',
                'metric'    => 'total_signed_days',
                'threshold' => 100,
            ),
            'days_365' => array(
                'name'      => '岁月留痕',
                'desc'      => '累计签到 365 个不同日期',
                'tier'      => self::TIER_GOLD,
                'icon'      => 'i-calendar',
                'metric'    => 'total_signed_days',
                'threshold' => 365,
            ),
        );
    }

    /**
     * 建表（幂等）。字段说明：
     *  - uid           用户 id
     *  - achievement   成就 code（对应 definitions 的键）
     *  - unlocked_at   解锁时间戳
     *  唯一索引保证同一用户同一成就只解锁一次。
     */
    public static function ensureTable()
    {
        static $ready = false;
        if ($ready) { return; }
        global $m;
        $m->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::TABLE . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uid` INT UNSIGNED NOT NULL DEFAULT 0,
            `achievement` VARCHAR(64) NOT NULL DEFAULT '',
            `unlocked_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uid_achievement` (`uid`, `achievement`),
            KEY `uid` (`uid`),
            KEY `achievement` (`achievement`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        $ready = true;
    }

    /**
     * 拉取某用户的所有指标，一次查询避免 N+1。
     * 返回数组：current_streak / max_streak / total_success / total_signed_days / max_streak_30d
     */
    protected static function userStats($uid)
    {
        global $m;
        SignManager::ensureHistoryTable();
        $uid = (int) $uid;
        if ($uid <= 0) {
            return array(
                'current_streak'     => 0,
                'max_streak'         => 0,
                'total_success'      => 0,
                'total_signed_days'  => 0,
                'max_streak_30d'     => 0,
            );
        }
        // streak 复用 SignManager 已有逻辑
        $streak = SignManager::streak($uid);
        // 聚合总量与不同签到天数（一年窗口足够覆盖所有阈值）
        $since = time() - 86400 * 366;
        $sql = "SELECT
            SUM(`status` = 'success') AS total_success,
            COUNT(DISTINCT DATE(FROM_UNIXTIME(`created_at`))) AS total_signed_days
            FROM `" . DB_PREFIX . SignManager::HISTORY_TABLE . "`
            WHERE `uid` = {$uid} AND `created_at` >= {$since}";
        $row = $m->once_fetch_array($sql);
        return array(
            'current_streak'     => (int) $streak['current_streak'],
            'max_streak'         => (int) $streak['max_streak'],
            'total_success'      => (int) ($row['total_success'] ?? 0),
            'total_signed_days'  => (int) ($row['total_signed_days'] ?? 0),
            'max_streak_30d'     => 0,
        );
    }

    /**
     * 检查并解锁某用户的所有应得成就。
     * @param int $uid
     * @return array 本次新解锁的成就 code 列表
     */
    public static function checkAndUnlock($uid)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        if ($uid <= 0) { return array(); }

        $stats    = self::userStats($uid);
        $already  = self::unlocked($uid, false); // 不带 unlocked_at，仅 code 集合
        $newCodes = array();
        $now      = time();
        foreach (self::definitions() as $code => $def) {
            if (isset($already[$code])) { continue; }
            $value = isset($stats[$def['metric']]) ? (int) $stats[$def['metric']] : 0;
            if ($value >= (int) $def['threshold']) {
                $m->query("INSERT INTO `" . DB_PREFIX . self::TABLE . "`
                    (`uid`, `achievement`, `unlocked_at`) VALUES ({$uid}, '" . $m->real_escape_string($code) . "', {$now})
                    ON DUPLICATE KEY UPDATE `unlocked_at` = `unlocked_at`");
                $newCodes[] = $code;
            }
        }
        return $newCodes;
    }

    /**
     * 获取用户已解锁成就。
     * @param int $uid
     * @param bool $withDetails true 返回 [code=>['unlocked_at'=>ts, ...]]，false 返回 [code=>1] 集合
     * @return array
     */
    public static function unlocked($uid, $withDetails = true)
    {
        global $m;
        self::ensureTable();
        $uid = (int) $uid;
        $result = $m->query("SELECT `achievement`, `unlocked_at` FROM `" . DB_PREFIX . self::TABLE . "` WHERE `uid` = {$uid}");
        $rows = array();
        while ($row = $m->fetch_array($result)) {
            $code = (string) $row['achievement'];
            if ($withDetails) {
                $rows[$code] = array('unlocked_at' => (int) $row['unlocked_at']);
            } else {
                $rows[$code] = 1;
            }
        }
        return $rows;
    }

    /**
     * 返回带进度信息的成就列表，供展示页使用。
     * 每个 item：[code, name, desc, tier, icon, threshold, current, progress, unlocked, unlocked_at]
     */
    public static function progress($uid)
    {
        $uid     = (int) $uid;
        $stats   = self::userStats($uid);
        $unlocked = self::unlocked($uid, true);
        $items   = array();
        foreach (self::definitions() as $code => $def) {
            $current  = isset($stats[$def['metric']]) ? (int) $stats[$def['metric']] : 0;
            $thresh   = (int) $def['threshold'];
            $progress = $thresh > 0 ? min(100, (int) round($current / $thresh * 100)) : 0;
            $items[] = array(
                'code'        => $code,
                'name'        => $def['name'],
                'desc'        => $def['desc'],
                'tier'        => $def['tier'],
                'icon'        => $def['icon'],
                'threshold'   => $thresh,
                'current'     => $current,
                'progress'    => $progress,
                'unlocked'    => isset($unlocked[$code]),
                'unlocked_at' => isset($unlocked[$code]) ? (int) $unlocked[$code]['unlocked_at'] : 0,
                'metric'      => $def['metric'],
            );
        }
        return $items;
    }

    /**
     * 简要统计供仪表盘卡片使用。
     */
    public static function summary($uid)
    {
        $uid = (int) $uid;
        $items = self::progress($uid);
        $unlockedCount = 0;
        $total = count($items);
        $latest = null;
        foreach ($items as $it) {
            if ($it['unlocked']) {
                $unlockedCount++;
                if ($latest === null || $it['unlocked_at'] > $latest['unlocked_at']) {
                    $latest = $it;
                }
            }
        }
        return array(
            'total'          => $total,
            'unlocked'       => $unlockedCount,
            'locked'         => $total - $unlockedCount,
            'latest'         => $latest,
            'progress_pct'   => $total > 0 ? (int) round($unlockedCount / $total * 100) : 0,
        );
    }
}
