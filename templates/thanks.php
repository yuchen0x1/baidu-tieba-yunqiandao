<?php
if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}
global $i;

/*
 * 开源致谢板块
 * 本项目站在巨人的肩膀上：以下列出的开源项目、插件作者与社区贡献者，
 * 为本项目的诞生与成长提供了直接或间接的代码、灵感与支持。
 * 如需增补条目，直接往 $credits 数组里追加即可，无需改动页面结构。
 */

$credits = array(
    array(
        'group' => '核心框架',
        'desc' => '本项目的主程序架构、签到引擎与插件系统源自以下开源项目的传承与再造',
        'items' => array(
            array('name' => 'Tieba-Cloud-Sign（MoeNetwork）', 'url' => 'https://github.com/MoeNetwork/Tieba-Cloud-Sign', 'author' => 'Kenvix、mokeyjay、FYY 及 MoeNetwork 社区', 'note' => '百度贴吧云签到开山之作，本项目的直接上游。签到核心、插件钩子框架、模板机制均源自此项目（AGPL-3.0）。'),
            array('name' => 'Kenvix 及其开源作品', 'url' => 'https://kenvix.com', 'author' => 'Kenvix', 'note' => '原版主程序作者，wcurl（cURL 封装）、SFC 函数库等基础组件出自其手。'),
            array('name' => 'mokeyjay / FYY', 'url' => 'https://github.com/MoeNetwork/Tieba-Cloud-Sign', 'author' => 'mokeyjay、FYY', 'note' => '早期核心贡献者：分表机制、表单生成器（Former）等功能的基础。'),
        ),
    ),
    array(
        'group' => '插件生态',
        'desc' => '随本项目一同分发的插件，来自开源社区作者们的无私分享',
        'items' => array(
            array('name' => 'weltolk_sign_qq / weltolk_backup_qq', 'url' => 'https://github.com/weltolk', 'author' => 'weltolk', 'note' => 'QQ 机器人签到推送与 QQ 消息备份，支持私聊/群聊与 WebSocket。'),
            array('name' => 'ver4 系列插件（云灌水、一键封禁、云审查、转盘抽奖、签到排行、Flat UI）', 'url' => 'https://github.com/MoeNetwork/Tieba-Cloud-Sign', 'author' => 'ver4 及社区插件作者', 'note' => '贴吧运营类插件的标杆实现，本项目分发的 ver4_* 插件均在其开源版本基础上维护。'),
            array('name' => 'wmzz 系列插件（公告、关注、调试、Flat UI）', 'url' => 'https://github.com/MoeNetwork/Tieba-Cloud-Sign', 'author' => '无名智者（wmzz）', 'note' => '老牌实用插件，公告栏、一键关注等功能的源头。'),
            array('name' => '第三方插件分享社区', 'url' => 'https://github.com/MoeNetwork/Tieba-Cloud-Sign/discussions/264', 'author' => 'MoeNetwork Discussion #264 全体参与者', 'note' => '大量第三方插件（打码、点赞、邮箱验证等）经由该讨论区流通，是云签到生态最活跃的集散地。'),
        ),
    ),
    array(
        'group' => '部署与运维',
        'desc' => '让本项目能在各种环境下开箱即用的社区力量',
        'items' => array(
            array('name' => '官方 Docker 镜像', 'url' => 'https://hub.docker.com/r/moenetwork/tieba-cloud-sign', 'author' => 'MoeNetwork、@nevinen', 'note' => '容器化部署方案的先行者，本项目 Docker Compose 方案参考了其配置思路。'),
            array('name' => 'Gitee 社区镜像', 'url' => 'https://gitee.com/liwanglin12/Tieba-Cloud-Sign', 'author' => 'liwanglin12 等镜像维护者', 'note' => '为国内用户提供了稳定可用的下载通道。'),
            array('name' => 'Bootstrap / jQuery / PHP-CLI 工具链', 'url' => 'https://getbootstrap.com', 'author' => '各上游项目作者', 'note' => '前端 UI 框架与基础工具链。'),
        ),
    ),
);
?>

<section class="dashboard-hero">
    <div class="eyebrow hero-eyebrow">Open Source Credits</div>
    <h1>开源致谢</h1>
    <p>本项目基于 AGPL-3.0 协议开源，站立于开源社区的肩膀之上。以下是本项目直接或间接借鉴、衍生、分发代码的项目与作者，谨以此板块向他们致谢。</p>
</section>

<?php foreach ($credits as $creditGroup) { ?>
    <section class="panel panel-info">
        <div class="panel-heading"><span class="panel-title"><?php echo htmlspecialchars($creditGroup['group'], ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="panel-body">
            <p class="text-muted"><?php echo htmlspecialchars($creditGroup['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <ul class="cs-list">
            <?php foreach ($creditGroup['items'] as $item) { ?>
                <li class="cs-list-item cs-list-item--inline">
                    <strong>
                        <?php if (!empty($item['url'])) { ?>
                            <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php } else { ?>
                            <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php } ?>
                    </strong>
                    <span class="text-muted"> — <?php echo htmlspecialchars($item['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <br>
                    <span class="text-muted"><?php echo htmlspecialchars($item['note'], ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<section class="panel panel-primary">
    <div class="panel-heading"><span class="panel-title">关于协议</span></div>
    <div class="panel-body">
        <p class="text-muted">本程序遵循 <strong>AGPL-3.0</strong> 协议开源发行，衍生、分发的代码均保留原项目版权声明。如果你基于本项目进行了二次开发，也同样欢迎在此页面（或你自己的致谢页中）保留并续写这份名单。</p>
        <p class="text-muted">项目主页：<a href="<?php echo htmlspecialchars(PROJECT_GITHUB_URL, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(PROJECT_GITHUB_URL, ENT_QUOTES, 'UTF-8'); ?></a></p>
    </div>
</section>
