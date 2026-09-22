# 灵创 AI（LingChuang X）

一站式 AI 创作与聚合服务平台，支持文本创作、图片生成、视频生成、音频创作、智能体工作流、提示词广场、AI 工具箱、积分会员体系与 SEO / 收录管理。

仓库地址：[github.com/zbp2023210-cloud/lingchuangx](https://github.com/zbp2023210-cloud/lingchuangx)

在线站点：<https://lingchuangx.com>

---

## ✨ 功能特性

### 前台（`index.php`）
- **统一工作台布局**：左侧功能导航、顶部搜索栏、Agent 模式切换、灵感输入框、快捷工具卡片。
- **多模态 AI 创作**
  - 文本生成（`/page=text`）：文案、问答、长文、代码
  - 图片生成（`/page=image`）：文生图、图生图、局部重绘
  - 视频生成（`/page=video`）：文生视频、图生视频，支持时长 / 清晰度选项
  - 音频生成（`/page=audio`）：音乐生成、语音合成
  - 智能对话（`/page=chat`）：可切换多模型，登录用户自动记录最近对话
- **AI 工具箱（`/page=apps`、`/page=toolbox`）**
  - 电商主图、爆款复制、模特试衣、商品精修、无痕去水印、视频转文字、视频提词等
  - 支持按分类筛选、搜索、积分扣减
- **提示词广场（`/page=prompts`）**
  - 图片 / 视频提示词卡片瀑布流，支持按类型筛选、搜索、一键复制
  - 已发布提示词详情页（`?page=prompts&id=N`）参与 SEO 收录
- **智能体（`/page=agents`）**：PPT 生成、餐饮视觉、电商图片 / 视频、漫画剧等预设智能体
- **个人中心（`/page=profile`）**：注册、登录、资料、积分、签到、VIP 开通记录
- **客服 & VIP**：客服二维码展示（`/page=service`）、VIP 会员套餐页

### 后台（`admin.php`）
- **提示词管理**：增删改查、启用 / 禁用、同步拉取外部灵境 AI 提示词、导出 CSV
- **AI 工具箱管理**：配置 `ai_tools` 数据（名称、积分、分类、排序、开关）
- **智能体管理**：`agent_configs` 表管理，可设置系统提示词与专用模型
- **VIP / 积分加油包管理**：`vip_plans`、`points_packages`
- **系统公告管理**：富文本格式化卡片、置顶、定时生效
- **用户管理 / 数据看板**：会员、消费、留存等基础统计
- **系统配置（SEO 与收录）**
  - 百度搜索资源平台 API 配置：站点域名、准入 Token、自动推送开关
  - 手动批量推送：一键推送全站公开页 + 已启用提示词，或自定义多行 URL
  - 站点地图（`/sitemap.xml`、`/sitemap.php`）静态 + 动态生成
  - 模型供应商开关（OpenAI / DeepSeek / Claude / Gemini / 通义千问 / 灵境）
  - 支付渠道配置（支付宝 / 微信 / 汇付 / PayPal / USDT 等）
  - 短信、邮件、注册、签到等运行参数
- **登录保护**：CSRF Token、会话鉴权、密码 Hash（bcrypt / Argon2）

### 工程能力
- **SEO / 收录**
  - `robots.txt`：屏蔽后台与私人页面，允许抓取公开页
  - `sitemap.php`：浏览器访问实时生成标准 XML 站点地图
  - `sitemap.xml`：静态文件版本，通过后台按钮一键重新生成
  - 百度搜索资源平台主动推送 API 对接
- **外部 AI 模型聚合**：支持灵境 AI、OpenAI 兼容接口、DeepSeek、Anthropic、Google Gemini、阿里通义千问
- **支付聚合**：支付宝 App / Web / H5、微信 Native / H5、汇付天下、PayPal、USDT
- **短信 / 邮件**：支持 SMTP 与多通道短信 Provider 配置

---

## 🛠 技术栈

| 层 | 技术 |
|---|---|
| 后端语言 | PHP 8.0（部分代码兼容 PHP 7.3+），无框架，原生 PHP + cURL |
| Web 服务器 | Nginx（宝塔面板） |
| 数据库 | MySQL 5.7+（utf8mb4），PDO 访问 |
| 会话 / 安全 | PHP Session、CSRF Token、`password_hash()`（bcrypt）、密钥文件 600 权限 |
| 前端 | 原生 HTML / CSS / JavaScript（`assets/`），无构建工具，移动端适配 |
| SEO | `sitemap.xml`（动态 + 静态）、`robots.txt`、百度主动推送 API |
| AI 集成 | 灵境 AI（`api.lk888.ai`）OpenAI 兼容 + Gemini + Anthropic + DeepSeek + 通义千问 |
| 支付 | 支付宝（Web / H5 / App）、微信支付（Native / H5）、汇付天下、PayPal、USDT |
| 日志 / 缓存 | `runtime/` 目录运行时缓存与日志（不进 Git） |

---

## 📂 目录结构

```
lingchuangx/
├── index.php                # 前台路由、首页、登录注册、工作台、个人中心
├── admin.php                # 后台登录和管理（提示词、工具箱、智能体、SEO 等）
├── api.php                  # 服务端 AI 代理接口（能力、指南、余额等）
├── sync-prompts.php         # 外部灵境 AI 提示词同步脚本（CLI / Cron）
├── sitemap.php              # 动态 Sitemap XML 生成器
├── sitemap.xml              # 静态 Sitemap（由后台按钮或 CLI 生成）
├── robots.txt               # 爬虫规则
├── schema.sql               # MySQL 初始化表结构与默认数据
├── config.example.php       # 数据库配置模板（请复制为 config.php）
├── .env.example             # 数据库环境变量模板
├── lib/
│   ├── bootstrap.php        # 数据库连接、会话、CSRF、业务函数、站点配置
│   └── lingjing.php         # 灵境 AI HTTP 客户端
├── assets/
│   ├── app.css / app.js     # 前台样式与交互
│   ├── admin.css            # 后台样式
│   ├── prompt_extract.js    # 视频 / 图片提词前端
│   ├── video_transcript.js  # 视频转文字
│   ├── watermark_remove.js  # 去水印
│   └── home/                # 首页资源图
├── runtime/                 # 运行时文件（API Key、模型缓存、日志）——不进 Git
└── uploads/                 # 用户上传图片 ——不进 Git
```

---

## 🚀 本地部署

### 1. 环境要求
- PHP 8.0+（启用 `pdo_mysql`、`curl`、`zip` 扩展）
- MySQL 5.7+ / MariaDB 10.3+
- Nginx 或 Apache

### 2. 初始化数据库
```bash
mysql -u root -p -e "CREATE DATABASE lingchuangx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p lingchuangx < schema.sql
```

### 3. 配置数据库
复制配置模板：
```bash
cp config.example.php config.php
cp .env.example .env
```
编辑 `config.php`（或导出 `LINGCHUANGX_DB_*` 环境变量）填入你的数据库连接信息：
```php
'db' => [
    'host' => getenv('LINGCHUANGX_DB_HOST') ?: '127.0.0.1',
    'name' => getenv('LINGCHUANGX_DB_NAME') ?: 'lingchuangx',
    'user' => getenv('LINGCHUANGX_DB_USER') ?: 'lingchuangx',
    'pass' => getenv('LINGCHUANGX_DB_PASS') ?: '',
    'charset' => 'utf8mb4',
],
```

### 4. 配置 AI 模型 Key
灵境 AI API Key 存放于 `runtime/lingjing_api_key`（权限 600，不进 Git）：
```bash
mkdir -p runtime
echo "你的灵境AI_API_KEY" > runtime/lingjing_api_key
chmod 600 runtime/lingjing_api_key
```

### 5. 启动
把站点根目录指向本目录，导入 `schema.sql` 后访问域名即可。

---

## 🔒 安全说明

- **不在代码仓库提交任何敏感信息**：`.gitignore` 已排除 `config.php`、`runtime/*`、`uploads/*`。
- 灵境 AI Key 仅服务端读取，文件权限 600。
- 后台使用 CSRF Token 与密码 Hash 保护。
- `.env.example` 中不包含真实数据库密码。

---

## 📄 License

内部项目，暂无开源许可。
