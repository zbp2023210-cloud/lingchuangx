<?php
// 使用说明：
// 1. 将此文件复制为 config.php
// 2. 填入你的数据库连接信息
// 3. 配置项支持通过环境变量覆盖（如 LINGCHUANGX_DB_PASS）
return [
    'app_name' => '灵创AI',
    'app_url' => 'https://lingchuangx.com',
    'db' => [
        'host' => getenv('LINGCHUANGX_DB_HOST') ?: '127.0.0.1',
        'name' => getenv('LINGCHUANGX_DB_NAME') ?: 'lingchuangx',
        'user' => getenv('LINGCHUANGX_DB_USER') ?: 'lingchuangx',
        'pass' => getenv('LINGCHUANGX_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'lingjing' => [
        'base_url' => getenv('LINGJING_API_BASE') ?: 'https://api.lk888.ai/api',
        'key_file' => dirname(__FILE__) . '/runtime/lingjing_api_key',
        'model' => getenv('LINGJING_MODEL') ?: '',
    ],
];
