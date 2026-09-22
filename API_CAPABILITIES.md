# 灵境 AI API 能力缓存

> 本文件由网站服务端通过灵境 AI `/v1/skills` 和 `/v1/skills/guide` 同步生成。完整原始响应见服务器运行时缓存，不记录 API Key。

## 接入配置

- Base URL：`https://api.lk888.ai/api`
- 认证：`Authorization: Bearer {API_KEY}`
- Key 文件：`runtime/lingjing_api_key`
- Key 文件权限：600，所有者：www:www

## 网站已接入

- `GET /api.php?action=capabilities`：服务端代理平台能力列表
- `GET /api.php?action=guide`：服务端代理调用指南
- `GET /api.php?action=balance`：登录用户查询余额

以上接口不会向浏览器返回 API Key。API 代理使用 PHP cURL，Key 仅在服务器端读取。

## 最新同步结果

- 能力列表接口：请求成功
- 调用指南接口：请求成功
- 能力列表响应大小：68726 bytes
- 调用指南响应大小：23763 bytes
- 同步时间：2026-08-26

## 调用约束

以灵境 AI 实际返回的能力列表和指南为准，不硬编码未经确认的模型名。付费模型调用前应查询余额；余额不足时引导用户到灵境 AI 官网充值算力。

## 安全说明

- 不把 API Key 写入 PHP 源码、前端 JavaScript、HTML 或日志。
- 不在接口错误中输出请求头、Key 或完整敏感参数。
- `runtime/lingjing_api_key` 已设置为 600。
