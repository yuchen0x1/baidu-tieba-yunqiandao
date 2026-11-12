<?php

if (!defined('SYSTEM_ROOT')) {
    die('Insufficient Permissions');
}

/**
 * 打码平台接入类
 * 当百度要求输入验证码时，调用第三方打码平台自动识别。
 * 已支持：超级鹰（chaojiying.com）
 */
class dama
{
    /**
     * 判断打码平台是否已启用并配置完整
     * @return bool
     */
    public static function enabled()
    {
        return option::get('dama_type') === 'chaojiying'
            && SecretBox::get('dama_username') !== ''
            && SecretBox::get('dama_password') !== '';
    }

    /**
     * 识别一张验证码图片
     * @param string $imageData 图片二进制内容
     * @param int    $codeType  打码平台验证码类型（超级鹰 codetype），1902=常见4-6位英文数字
     * @return string|false 识别结果，失败返回 false
     */
    public static function solve($imageData, $codeType = 1902)
    {
        if (!self::enabled() || empty($imageData)) {
            return false;
        }
        switch (option::get('dama_type')) {
            case 'chaojiying':
                return self::chaojiying($imageData, $codeType);
            default:
                return false;
        }
    }

    /**
     * 超级鹰打码
     * @param string $imageData 图片二进制内容
     * @param int    $codeType  验证码类型
     * @return string|false
     */
    private static function chaojiying($imageData, $codeType)
    {
        $param = array(
            'user'      => SecretBox::get('dama_username'),
            'pass2'     => SecretBox::get('dama_password'),
            'softid'    => option::get('dama_softid'),
            'codetype'  => $codeType,
            'file_base64' => base64_encode($imageData),
        );
        $ch = new wcurl('https://upload.chaojiying.net/Upload/Processing.php', array(
            'Content-Type: application/x-www-form-urlencoded',
        ));
        misc::applyProxy($ch);
        $response = json_decode($ch->post($param), true);
        if (!empty($response['err_no']) || empty($response['pic_str'])) {
            // 识别失败时退还题分，避免白白消耗
            if (!empty($response['pic_id']) && !empty($param['softid'])) {
                $refund = new wcurl('https://upload.chaojiying.net/Upload/ReportError.php');
                misc::applyProxy($refund);
                $refund->post(array(
                    'user'   => $param['user'],
                    'pass2'  => $param['pass2'],
                    'softid' => $param['softid'],
                    'id'     => $response['pic_id'],
                ));
            }
            return false;
        }
        return $response['pic_str'];
    }
}
