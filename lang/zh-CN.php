<?php

/**
 * 简体中文语言包（V5.13 A5）
 * 键值词典，供 I18N::t() 调用。默认语言，亦是系统内置回退。
 */
return array(
    // 通用
    'common.ok'          => '确定',
    'common.cancel'      => '取消',
    'common.save'        => '保存',
    'common.delete'      => '删除',
    'common.confirm'     => '确认',
    'common.back'        => '返回',
    'common.submit'      => '提交',
    'common.search'      => '搜索',
    'common.operate'     => '操作',
    'common.status'      => '状态',
    'common.enabled'     => '启用',
    'common.disabled'    => '禁用',
    'common.unknown'     => '未知',
    'common.empty'       => '暂无数据',
    'common.loading'     => '加载中…',
    'common.success'     => '操作成功',
    'common.failed'      => '操作失败',
    'common.error'       => '出错了',
    'common.no_permission' => '权限不足',

    // 导航
    'nav.dashboard'      => '工作台',
    'nav.baidu_account'  => '百度账号',
    'nav.account_center' => '账号中心',
    'nav.tieba_sign'     => '贴吧与签到',
    'nav.calendar'       => '签到日历',
    'nav.settings'       => '个人设置',
    'nav.thanks'         => '开源致谢',
    'nav.admin.tools'    => '工具箱',
    'nav.admin.settings' => '设置中心',
    'nav.admin.users'    => '用户管理',
    'nav.admin.invite'   => '邀请码管理',
    'nav.admin.stat'     => '统计信息',
    'nav.admin.sign'     => '签到看板',
    'nav.admin.cron'     => '计划任务',
    'nav.admin.totp'     => '双因素认证',
    'nav.admin.plugins'  => '插件管理',
    'nav.admin.update'   => '系统更新',

    // 登录 / 注册
    'login.title'        => '登录',
    'login.username'     => '账号',
    'login.password'     => '密码',
    'login.remember'     => '记住登录状态',
    'login.submit'       => '登录',
    'login.no_account'   => '还没有账号？',
    'login.register'     => '立即注册',
    'login.forgot'       => '忘记密码？',
    'reg.title'          => '注册',
    'reg.email'          => '邮箱',
    'reg.repeat_password'=> '确认密码',
    'reg.invite_code'    => '邀请码',
    'reg.submit'         => '注册',

    // 计划任务
    'cron.new'           => '添加新计划任务',
    'cron.run_all'       => '运行全部计划任务',
    'cron.enable'        => '启用',
    'cron.disable'       => '禁用',
    'cron.retry'         => '重试',
    'cron.uninstall'     => '卸载',
    'cron.last_success'  => '上次成功',
    'cron.last_failed'   => '上次失败',
    'cron.running'       => '运行中',
    'cron.never_run'     => '从未运行',
    'cron.one_time'      => '一次性任务',
    'cron.always'        => '始终运行的任务',
    'cron.interval'      => '执行间隔：{sec} 秒',

    // 签到
    'sign.done'          => '签到完成：{name}',
    'sign.fail'          => '签到失败：{name}',
    'sign.account_offline' => '账号已掉线：{name}',

    // 双因素认证
    'totp.enabled'       => '已开启',
    'totp.disabled'      => '未开启',
    'totp.verify_code'   => '6 位验证码',
    'totp.verify_submit' => '验证并登录',
);
