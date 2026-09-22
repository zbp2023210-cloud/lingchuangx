CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(60) NOT NULL UNIQUE,
 email VARCHAR(120) DEFAULT NULL,
 password_hash VARCHAR(255) NOT NULL,
 avatar VARCHAR(255) DEFAULT NULL,
 points INT NOT NULL DEFAULT 100,
 vip_until DATETIME DEFAULT NULL,
 status TINYINT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS generations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 type VARCHAR(20) NOT NULL,
 prompt TEXT NOT NULL,
 result_url VARCHAR(500) DEFAULT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'completed',
 cost INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 INDEX idx_gen_user_created (user_id, created_at),
 CONSTRAINT fk_gen_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ai_tools (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 slug VARCHAR(100) NOT NULL UNIQUE,
 description VARCHAR(255) NOT NULL,
 icon VARCHAR(20) NOT NULL DEFAULT '✦',
 category VARCHAR(40) NOT NULL DEFAULT '创作',
 points INT NOT NULL DEFAULT 5,
 enabled TINYINT NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS admin_users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(60) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO ai_tools (name,slug,description,icon,category,points,sort_order) VALUES
('AI 灵感绘画','image','输入一句话，生成独特视觉作品','◈','AI 绘画',8,1),
('智能文案','copywriting','广告、短视频和社媒文案一键生成','Aa','AI 写作',3,2),
('AI 对话助手','chat','随时交流，解决问题与激发灵感','✦','AI 对话',1,3),
('文章创作','article','从主题到成稿，快速完成长文创作','▤','AI 写作',5,4),
('视频转提示词','video2prompt','上传视频，AI 逆向解析运镜与风格生成提示词','▷','AI 工具',0,5),
('图片转提示词','image2prompt','上传图片，AI 逆向解析构图与光线生成提示词','▨','AI 工具',0,6),
('无痕无水印','watermark-remove','短视频去水印高清下载','◌','AI 工具',0,7),
('视频文案提取','video-transcript','AI 识别视频语音生成文案','▤','AI 工具',0,8);
