<?php

//特别警告：禁止使用记事本编辑！

////////////////////////////以下选项只需在使用MySQL时填写////////////////////////////
//MySQL 数据库地址，普通主机一般为localhost，Docker 部署时可使用环境变量 DB_HOST 覆盖
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1');
//MySQL 数据库用户名，可使用环境变量 DB_USER 覆盖
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
//MySQL 数据库密码，可使用环境变量 DB_PASSWD 覆盖
define('DB_PASSWD', getenv('DB_PASSWD') !== false ? getenv('DB_PASSWD') : '');
//MySQL 数据库名称，可使用环境变量 DB_NAME 覆盖
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'tiebacloud');
//MySQL 启用SSL连接，如需启用请将值改为1，或设置环境变量 DB_SSL=1
define('DB_SSL', getenv('DB_SSL') !== false ? (int) getenv('DB_SSL') : 0);

////////////////////////////以下选项使用任何数据库都需填写////////////////////////////
//数据库前缀，建议保持默认，可使用环境变量 DB_PREFIX 覆盖
define('DB_PREFIX', getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'tc_');

///////////////////////////////////////其他设置///////////////////////////////////////
//停用CSRF防御
//说明在 https://github.com/yuchen0x1/tieba-cloud-sign/wiki/%E5%85%B3%E4%BA%8E%E4%BA%91%E7%AD%BE%E5%88%B0CSRF%E9%98%B2%E5%BE%A1
//可使用环境变量 ANTI_CSRF 覆盖，例如 ANTI_CSRF=false
define('ANTI_CSRF', getenv('ANTI_CSRF') !== false ? filter_var(getenv('ANTI_CSRF'), FILTER_VALIDATE_BOOLEAN) : (getenv('CSRF') !== false ? filter_var(getenv('CSRF'), FILTER_VALIDATE_BOOLEAN) : true));

//加密用盐，留空为不使用，可使用环境变量 SYSTEM_SALT 覆盖
define('SYSTEM_SALT', getenv('SYSTEM_SALT') !== false ? getenv('SYSTEM_SALT') : '');
