<?php

/**
 * 官方服务接入配置（公开常量——本文件只允许出现「公开」信息）。
 *
 * 边界约定：
 *  - 这里永远不放入任何私钥、实例 Secret、Provider Key。实例 Secret 在后台填写，
 *    经 P::encrypt 加密后存库；License 签发私钥只存在于官方服务器（env/Secret Manager）。
 *  - 公钥、协议版本、默认端点属于公开协议信息，随开源仓库分发是预期行为。
 */

/*
 * License 验签公钥（RSA-2048，PEM 格式，单行 base64 之间的换行必须保留）。
 *
 * 首次部署官方授权服务时，用 tools/official-keygen.php 生成密钥对：
 *   私钥 → 官方服务器（env GW_LICENSE_PRIVATE_KEY，绝不入库入仓）
 *   公钥 → 粘贴到下面常量（随开源仓库分发）
 *
 * 留空 = License 验证功能关闭（纯社区版，所有官方能力保持未启用）。
 */
if (!defined('TCS_OFFICIAL_LICENSE_PUBKEY')) {
    define('TCS_OFFICIAL_LICENSE_PUBKEY', '');
}

/*
 * 官方网关协议版本。请求头 X-TCS-Protocol 携带，网关按版本协商响应结构。
 */
if (!defined('TCS_OFFICIAL_PROTOCOL_VERSION')) {
    define('TCS_OFFICIAL_PROTOCOL_VERSION', 'v1');
}
