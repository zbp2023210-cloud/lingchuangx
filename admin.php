<?php
require __DIR__.'/lib/bootstrap.php';

/* ============ 路由与权限 ============ */
if (isset($_GET['logout'])) {
    admin_logout();
    redirect('/admin.php');
}

$view = $_GET['view'] ?? 'dashboard';
if (!in_array($view, ['dashboard','users','generations','models','prompts','home_blocks','site_menus','announcements','vip_plans','points_packages','agents','cards','settings'], true)) {
    $view = 'dashboard';
}

/* ============ 登录处理 ============ */
if (!admin_user()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        if (admin_login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
            redirect('/admin.php');
        }
        flash('管理员账号或密码错误', 'error');
        redirect('/admin.php');
    }
    $flash = take_flash();
    ?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>管理员登录 · 灵创AI 管理后台</title>
<link rel="stylesheet" href="/assets/admin.css?v=20260901-users-v1">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">✦</div>
    <h1>灵创AI 管理后台</h1>
    <p class="sub">使用管理员账号安全登录</p>
    <?php if($flash):?><div class="login-error"><?=h($flash[0])?></div><?php endif;?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf()?>">
      <label>管理员账号</label>
      <input name="username" autocomplete="username" required>
      <label>管理员密码</label>
      <input name="password" type="password" autocomplete="current-password" required>
      <button type="submit">登 录</button>
    </form>
    <a class="login-back" href="/">← 返回网站前台</a>
  </div>
</div>
</body>
</html><?php
    exit;
}

/* ============ 提示词管理：增删改 ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_user()) {
    check_csrf();
    $act = $_POST['prompt_action'] ?? '';
    $pdo = db();
    try {
        if ($act === 'add') {
            $type  = in_array($_POST['ptype'] ?? '', ['image','video'], true) ? $_POST['ptype'] : 'image';
            $cat   = trim((string)($_POST['pcat'] ?? ''));
            $title = trim((string)($_POST['ptitle'] ?? ''));
            $text  = trim((string)($_POST['ptext'] ?? ''));
            if ($title === '' || $text === '') throw new InvalidArgumentException('标题和提示词内容不能为空');
            $sort = (int)($_POST['psort'] ?? 0);
            $enabled = !empty($_POST['penabled']) ? 1 : 0;
            $points = (int)($_POST['ppoints'] ?? 0);
            $tags = trim((string)($_POST['ptags'] ?? ''));
            $cover = trim((string)($_POST['pcover_url'] ?? ''));
            if (!empty($_FILES['pcover']) && is_uploaded_file($_FILES['pcover']['tmp_name'])) $cover = save_uploaded_file($_FILES['pcover'], 'cover')['url'];
            $media = trim((string)($_POST['pmedia_url'] ?? $_POST['pmedia_url_text'] ?? ''));
            if (!empty($_FILES['pmedia']) && is_uploaded_file($_FILES['pmedia']['tmp_name'])) $media = save_uploaded_file($_FILES['pmedia'], 'media')['url'];
            $s = $pdo->prepare('INSERT INTO prompts(type,cat,title,text,cover_url,media_url,points,tags,sort_order,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
            $s->execute([$type, $cat, $title, $text, $cover ?: null, $media ?: null, $points, $tags ?: null, $sort, $enabled]);
            $insertedId = (int)$pdo->lastInsertId();
            if ((string)site_setting('baidu_submit_auto_enabled', '0') === '1') {
                $siteHost = trim((string)site_setting('baidu_submit_site', ''));
                if ($siteHost !== '') {
                    $prefix = preg_match('#^https?://#i', $siteHost) ? rtrim($siteHost, '/') : ('https://' . rtrim($siteHost, '/'));
                    $pUrl = $prefix . '/?page=prompts&id=' . $insertedId;
                    @baidu_submit_urls([$pUrl]);
                }
            }
            flash('提示词已添加', 'info');
        } elseif ($act === 'edit') {
            $id = (int)($_POST['pid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的提示词 ID');
            $type  = in_array($_POST['ptype'] ?? '', ['image','video'], true) ? $_POST['ptype'] : 'image';
            $cat   = trim((string)($_POST['pcat'] ?? ''));
            $title = trim((string)($_POST['ptitle'] ?? ''));
            $text  = trim((string)($_POST['ptext'] ?? ''));
            if ($title === '' || $text === '') throw new InvalidArgumentException('标题和提示词内容不能为空');
            $sort = (int)($_POST['psort'] ?? 0);
            $enabled = !empty($_POST['penabled']) ? 1 : 0;
            $points = (int)($_POST['ppoints'] ?? 0);
            $tags = trim((string)($_POST['ptags'] ?? ''));
            $cover = trim((string)($_POST['pcover_url'] ?? ''));
            if (!empty($_FILES['pcover']) && is_uploaded_file($_FILES['pcover']['tmp_name'])) $cover = save_uploaded_file($_FILES['pcover'], 'cover')['url'];
            $media = trim((string)($_POST['pmedia_url'] ?? $_POST['pmedia_url_text'] ?? ''));
            if (!empty($_FILES['pmedia']) && is_uploaded_file($_FILES['pmedia']['tmp_name'])) $media = save_uploaded_file($_FILES['pmedia'], 'media')['url'];
            $s = $pdo->prepare('UPDATE prompts SET type=?, cat=?, title=?, text=?, cover_url=?, media_url=?, points=?, tags=?, sort_order=?, enabled=?, updated_at=NOW() WHERE id=?');
            $s->execute([$type, $cat, $title, $text, $cover ?: null, $media ?: null, $points, $tags ?: null, $sort, $enabled, $id]);
            if ((string)site_setting('baidu_submit_auto_enabled', '0') === '1') {
                $siteHost = trim((string)site_setting('baidu_submit_site', ''));
                if ($siteHost !== '') {
                    $prefix = preg_match('#^https?://#i', $siteHost) ? rtrim($siteHost, '/') : ('https://' . rtrim($siteHost, '/'));
                    $pUrl = $prefix . '/?page=prompts&id=' . $id;
                    @baidu_submit_urls([$pUrl]);
                }
            }
            flash('提示词已更新', 'info');
        } elseif ($act === 'delete') {
            $id = (int)($_POST['pid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的提示词 ID');
            $pdo->prepare('DELETE FROM prompts WHERE id=?')->execute([$id]);
            flash('提示词已删除', 'info');
        } elseif ($act === 'batch_delete') {
            $ids = array_map('intval', (array)($_POST['pids'] ?? []));
            $ids = array_values(array_filter($ids, function($v){ return $v > 0; }));
            if (!$ids) throw new InvalidArgumentException('请选择要删除的提示词');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare('DELETE FROM prompts WHERE id IN (' . $placeholders . ')')->execute($ids);
            flash('已批量删除 ' . count($ids) . ' 条提示词', 'info');
        } elseif ($act === 'toggle') {
            $id = (int)($_POST['pid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的提示词 ID');
            $pdo->prepare('UPDATE prompts SET enabled = 1 - enabled, updated_at=NOW() WHERE id=?')->execute([$id]);
            flash('状态已切换', 'info');
        } elseif ($act === 'model_rule_save') {
            $type = in_array($_POST['rtype'] ?? '', ['chat','image','video','audio'], true) ? $_POST['rtype'] : 'image';
            $name = trim((string)($_POST['rmodel_name'] ?? ''));
            if ($name === '') throw new InvalidArgumentException('模型 ID 不能为空');
            $label = trim((string)($_POST['rlabel'] ?? ''));
            $icon = trim((string)($_POST['ricon'] ?? ''));
            $points = max(0, (int)($_POST['rpoints'] ?? 0));
            $sort = (int)($_POST['rsort'] ?? 0);
            $enabled = !empty($_POST['renabled']) ? 1 : 0;
            $s = $pdo->prepare('INSERT INTO model_rules(type,model_name,label,icon,points,sort_order,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE label=VALUES(label),icon=VALUES(icon),points=VALUES(points),sort_order=VALUES(sort_order),enabled=VALUES(enabled),updated_at=NOW()');
            $s->execute([$type,$name,$label,$icon,$points,$sort,$enabled]);
            flash('模型积分规则已保存', 'info');
            redirect('/admin.php?view=models&cat=' . urlencode($type));
        } elseif ($act === 'user_add') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $email = trim((string)($_POST['email'] ?? ''));
            $points = (int)($_POST['points'] ?? 100);
            $vipDays = (int)($_POST['vip_days'] ?? 0);
            $status = !empty($_POST['status']) ? 1 : 0;
            if ($username === '') throw new InvalidArgumentException('用户名不能为空');
            if ($password === '') throw new InvalidArgumentException('密码不能为空');
            $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?');
            $chk->execute([$username]);
            if ((int)$chk->fetchColumn() > 0) throw new InvalidArgumentException('用户名已存在');
            $vipUntil = null;
            if ($vipDays > 0) {
                $vipUntil = date('Y-m-d H:i:s', time() + $vipDays * 86400);
            }
            $s = $pdo->prepare('INSERT INTO users(username, email, password_hash, points, vip_until, status, created_at, updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())');
            $s->execute([$username, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $points, $vipUntil, $status]);
            flash('用户已创建', 'info');
            redirect('/admin.php?view=users');
        } elseif ($act === 'user_edit') {
            $id = (int)($_POST['uid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的用户 ID');
            $points = (int)($_POST['points'] ?? 0);
            $email = trim((string)($_POST['email'] ?? ''));
            $vipDays = (int)($_POST['vip_days'] ?? 0);
            $status = !empty($_POST['status']) ? 1 : 0;
            $newPass = (string)($_POST['new_password'] ?? '');
            
            // 处理 VIP
            $vipUntilVal = trim((string)($_POST['vip_until'] ?? ''));
            $vipUntil = null;
            if ($vipUntilVal !== '') {
                $vipUntil = date('Y-m-d H:i:s', strtotime($vipUntilVal));
            } elseif ($vipDays > 0) {
                $vipUntil = date('Y-m-d H:i:s', time() + $vipDays * 86400);
            }

            if ($newPass !== '') {
                $s = $pdo->prepare('UPDATE users SET email=?, points=?, vip_until=?, status=?, password_hash=?, updated_at=NOW() WHERE id=?');
                $s->execute([$email ?: null, $points, $vipUntil, $status, password_hash($newPass, PASSWORD_DEFAULT), $id]);
            } else {
                $s = $pdo->prepare('UPDATE users SET email=?, points=?, vip_until=?, status=?, updated_at=NOW() WHERE id=?');
                $s->execute([$email ?: null, $points, $vipUntil, $status, $id]);
            }
            flash('用户信息已更新', 'info');
            redirect('/admin.php?view=users');
        } elseif ($act === 'user_delete') {
            $id = (int)($_POST['uid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的用户 ID');
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            flash('用户已删除', 'info');
            redirect('/admin.php?view=users');
        } elseif ($act === 'user_toggle') {
            $id = (int)($_POST['uid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的用户 ID');
            $pdo->prepare('UPDATE users SET status = 1 - status, updated_at=NOW() WHERE id=?')->execute([$id]);
            flash('用户状态已切换', 'info');
            redirect('/admin.php?view=users');
        } elseif ($act === 'user_adjust_points') {
            $id = (int)($_POST['uid'] ?? 0);
            $change = (int)($_POST['points_change'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的用户 ID');
            if ($change !== 0) {
                $pdo->prepare('UPDATE users SET points = GREATEST(0, points + ?), updated_at=NOW() WHERE id=?')->execute([$change, $id]);
                flash('积分已调整', 'info');
            }
            redirect('/admin.php?view=users');
        } elseif ($act === 'user_give_vip') {
            $id = (int)($_POST['uid'] ?? 0);
            $days = (int)($_POST['days'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的用户 ID');
            if ($days > 0) {
                $row = $pdo->query("SELECT vip_until FROM users WHERE id = {$id}")->fetch();
                $cur = ($row && $row['vip_until'] && strtotime($row['vip_until']) > time()) ? strtotime($row['vip_until']) : time();
                $newVip = date('Y-m-d H:i:s', $cur + $days * 86400);
                $pdo->prepare('UPDATE users SET vip_until=?, updated_at=NOW() WHERE id=?')->execute([$newVip, $id]);
                flash("已为用户赠送 {$days} 天VIP", 'info');
            } elseif ($days < 0) {
                $pdo->prepare('UPDATE users SET vip_until=NULL, updated_at=NOW() WHERE id=?')->execute([$id]);
                flash('已取消该用户VIP身份', 'info');
            }
            redirect('/admin.php?view=users');
        } elseif ($act === 'agent_save') {
            ensure_feature_tables();
            $id=(int)($_POST['agent_id']??0);
            $key=preg_replace('/[^a-z0-9_-]/i','',trim((string)($_POST['agent_key']??'')));
            $name=text_limit(trim((string)($_POST['agent_name']??'')),100);
            $subtitle=text_limit(trim((string)($_POST['agent_subtitle']??'')),180);
            $description=trim((string)($_POST['agent_description']??''));
            $icon=text_limit(trim((string)($_POST['agent_icon']??'✦')),20)?:'✦';
            $systemPrompt=trim((string)($_POST['agent_system_prompt']??''));
            $model=text_limit(trim((string)($_POST['agent_model']??'')),160);
            $points=max(0,min(100000,(int)($_POST['agent_points']??5)));
            $sort=(int)($_POST['agent_sort']??0);
            $enabled=!empty($_POST['agent_enabled'])?1:0;
            if($key===''||$name===''||$systemPrompt==='')throw new InvalidArgumentException('智能体标识、名称和系统提示词不能为空');
            if($id){$s=$pdo->prepare('UPDATE agent_configs SET agent_key=?,name=?,subtitle=?,description=?,icon=?,system_prompt=?,model=?,points=?,sort_order=?,enabled=?,updated_at=NOW() WHERE id=?');$s->execute([$key,$name,$subtitle,$description,$icon,$systemPrompt,$model,$points,$sort,$enabled,$id]);}else{$s=$pdo->prepare('INSERT INTO agent_configs(agent_key,name,subtitle,description,icon,system_prompt,model,points,sort_order,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?, ?,NOW(),NOW())');$s->execute([$key,$name,$subtitle,$description,$icon,$systemPrompt,$model,$points,$sort,$enabled]);}
            flash('智能体配置已保存','info');redirect('/admin.php?view=agents');
        } elseif ($act === 'agent_delete') {
            ensure_feature_tables();$id=(int)($_POST['agent_id']??0);if($id>0)$pdo->prepare('DELETE FROM agent_configs WHERE id=?')->execute([$id]);flash('智能体已删除','info');redirect('/admin.php?view=agents');
        } elseif ($act === 'agent_toggle') {
            ensure_feature_tables();$id=(int)($_POST['agent_id']??0);if($id>0)$pdo->prepare('UPDATE agent_configs SET enabled=1-enabled,updated_at=NOW() WHERE id=?')->execute([$id]);redirect('/admin.php?view=agents');
        } elseif ($act === 'site_settings_save') {
            $siteName = trim((string)($_POST['site_name'] ?? ''));
            if ($siteName === '') throw new InvalidArgumentException('网站名称不能为空');
            $siteLogo = trim((string)($_POST['site_logo'] ?? site_setting('site_logo', '')));
            if (!empty($_POST['clear_site_logo'])) $siteLogo = '';
            if (!empty($_FILES['site_logo_file']) && is_uploaded_file($_FILES['site_logo_file']['tmp_name'])) {
                $f = $_FILES['site_logo_file'];
                $size = (int)$f['size'];
                if ($size <= 0 || $size > 5*1024*1024) throw new InvalidArgumentException('网站图标文件大小超出限制（5MB）');
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg','ico'], true)) throw new InvalidArgumentException('不支持的图标图片类型：' . $ext);
                $dir = __DIR__ . '/uploads';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $name = date('YmdHis') . '_logo_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('网站图标上传失败');
                $siteLogo = '/uploads/' . $name;
            }
            $qrcode = trim((string)($_POST['service_qrcode'] ?? site_setting('service_qrcode', '')));
            if (!empty($_POST['clear_service_qrcode'])) $qrcode = '';
            if (!empty($_FILES['service_qrcode_file']) && is_uploaded_file($_FILES['service_qrcode_file']['tmp_name'])) {
                $f = $_FILES['service_qrcode_file'];
                $size = (int)$f['size'];
                if ($size <= 0 || $size > 10*1024*1024) throw new InvalidArgumentException('客服二维码文件大小超出限制（10MB）');
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) throw new InvalidArgumentException('不支持的二维码图片类型：' . $ext);
                $dir = __DIR__ . '/uploads';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $name = date('YmdHis') . '_service_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('客服二维码上传失败');
                $qrcode = '/uploads/' . $name;
            }
            $settings = [
                'site_name' => text_limit($siteName, 60),
                'site_subtitle' => text_limit(trim((string)($_POST['site_subtitle'] ?? '')), 80),
                'site_logo' => $siteLogo,
                'logo_text' => text_limit(trim((string)($_POST['logo_text'] ?? 'LC')) ?: 'LC', 6),
                'service_title' => text_limit(trim((string)($_POST['service_title'] ?? '')), 40),
                'service_subtitle' => text_limit(trim((string)($_POST['service_subtitle'] ?? '')), 60),
                'service_button' => text_limit(trim((string)($_POST['service_button'] ?? '')), 20),
                'service_url' => trim((string)($_POST['service_url'] ?? '/?page=service')) ?: '/?page=service',
                'service_qrcode' => $qrcode,
                'points_label' => text_limit(trim((string)($_POST['points_label'] ?? '-- 积分')) ?: '-- 积分', 30),
                'site_notice' => trim((string)($_POST['site_notice'] ?? '')),
            ];
            save_site_settings($settings);
            flash('前台基础设置已保存', 'info');
            redirect('/admin.php?view=settings');
        } elseif ($act === 'user_settings_save') {
            $settings = [
                'register_points' => (string)max(0, min(1000000, (int)($_POST['register_points'] ?? 100))),
                'register_enabled' => !empty($_POST['register_enabled']) ? '1' : '0',
                'register_enabled_vip' => !empty($_POST['register_enabled_vip']) ? '1' : '0',
                'register_vip_days' => (string)max(0, min(36500, (int)($_POST['register_vip_days'] ?? 0))),
                'min_password_len' => (string)max(4, min(64, (int)($_POST['min_password_len'] ?? 6))),
                'user_default_points' => (string)max(0, min(1000000, (int)($_POST['user_default_points'] ?? 100))),
                'checkin_enabled' => !empty($_POST['checkin_enabled']) ? '1' : '0',
                'checkin_points' => (string)max(0, min(1000, (int)($_POST['checkin_points'] ?? 1))),
            ];
            save_site_settings($settings);
            flash('用户设置已保存', 'info');
            redirect('/admin.php?view=settings&tab=user');
        } elseif ($act === 'footer_settings_save') {
            $settings = [
                'footer_copyright' => text_limit(trim((string)($_POST['footer_copyright'] ?? '')), 200),
                'footer_icp' => text_limit(trim((string)($_POST['footer_icp'] ?? '')), 80),
                'footer_icp_url' => trim((string)($_POST['footer_icp_url'] ?? '')),
                'footer_police_beian' => text_limit(trim((string)($_POST['footer_police_beian'] ?? '')), 80),
                'footer_police_url' => trim((string)($_POST['footer_police_url'] ?? '')),
                'footer_contact_email' => trim((string)($_POST['footer_contact_email'] ?? '')),
                'footer_contact_phone' => text_limit(trim((string)($_POST['footer_contact_phone'] ?? '')), 30),
                'footer_about_url' => trim((string)($_POST['footer_about_url'] ?? '')),
                'footer_privacy_url' => trim((string)($_POST['footer_privacy_url'] ?? '')),
                'footer_terms_url' => trim((string)($_POST['footer_terms_url'] ?? '')),
                'footer_friend_links' => trim((string)($_POST['footer_friend_links'] ?? '')),
            ];
            save_site_settings($settings);
            flash('底部设置已保存', 'info');
            redirect('/admin.php?view=settings&tab=footer');
        } elseif ($act === 'model_api_settings_save') {
            $settings = [
                'model_provider_default' => in_array($_POST['model_provider_default'] ?? '', ['lingjing','openai_custom','deepseek','claude','gemini','qwen'], true) ? $_POST['model_provider_default'] : 'lingjing',
                'lingjing_base_url' => rtrim(trim((string)($_POST['lingjing_base_url'] ?? 'https://api.lk888.ai/api')), '/'),
                'lingjing_default_model' => trim((string)($_POST['lingjing_default_model'] ?? '')),

                'openai_custom_enabled' => !empty($_POST['openai_custom_enabled']) ? '1' : '0',
                'openai_custom_base_url' => rtrim(trim((string)($_POST['openai_custom_base_url'] ?? 'https://api.openai.com/v1')), '/'),
                'openai_custom_model' => trim((string)($_POST['openai_custom_model'] ?? 'gpt-4o-mini')),

                'deepseek_enabled' => !empty($_POST['deepseek_enabled']) ? '1' : '0',
                'deepseek_base_url' => rtrim(trim((string)($_POST['deepseek_base_url'] ?? 'https://api.deepseek.com/v1')), '/'),
                'deepseek_model' => trim((string)($_POST['deepseek_model'] ?? 'deepseek-chat')),

                'claude_enabled' => !empty($_POST['claude_enabled']) ? '1' : '0',
                'claude_base_url' => rtrim(trim((string)($_POST['claude_base_url'] ?? 'https://api.anthropic.com')), '/'),
                'claude_model' => trim((string)($_POST['claude_model'] ?? 'claude-3-5-sonnet-20241022')),

                'gemini_enabled' => !empty($_POST['gemini_enabled']) ? '1' : '0',
                'gemini_base_url' => rtrim(trim((string)($_POST['gemini_base_url'] ?? 'https://generativelanguage.googleapis.com')), '/'),
                'gemini_model' => trim((string)($_POST['gemini_model'] ?? 'gemini-1.5-flash')),

                'qwen_enabled' => !empty($_POST['qwen_enabled']) ? '1' : '0',
                'qwen_base_url' => rtrim(trim((string)($_POST['qwen_base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1')), '/'),
                'qwen_model' => trim((string)($_POST['qwen_model'] ?? 'qwen-plus')),
            ];
            save_site_settings($settings);

            $saveKey = function($field, $file) {
                $k = trim((string)($_POST[$field] ?? ''));
                if ($k !== '') {
                    $p = __DIR__ . '/runtime/' . $file;
                    $d = dirname($p);
                    if (!is_dir($d)) @mkdir($d, 0775, true);
                    file_put_contents($p, $k, LOCK_EX);
                    @chmod($p, 0640);
                }
            };
            $saveKey('lingjing_api_key', 'lingjing_api_key');
            $saveKey('openai_custom_api_key', 'openai_custom_api_key');
            $saveKey('deepseek_api_key', 'deepseek_api_key');
            $saveKey('claude_api_key', 'claude_api_key');
            $saveKey('gemini_api_key', 'gemini_api_key');
            $saveKey('qwen_api_key', 'qwen_api_key');

            flash('模型API设置已保存', 'info');
            redirect('/admin.php?view=settings&tab=model_api');

        } elseif ($act === 'payment_settings_save') {
            $settings = [
                'payment_alipay_enabled' => !empty($_POST['payment_alipay_enabled']) ? '1' : '0',
                'payment_alipay_app_id' => trim((string)($_POST['payment_alipay_app_id'] ?? '')),
                'payment_alipay_private_key' => trim((string)($_POST['payment_alipay_private_key'] ?? '')),
                'payment_alipay_public_key' => trim((string)($_POST['payment_alipay_public_key'] ?? '')),
                'payment_alipay_mode' => in_array($_POST['payment_alipay_mode'] ?? '', ['web','f2f'], true) ? $_POST['payment_alipay_mode'] : 'web',
                'payment_alipay_h5' => !empty($_POST['payment_alipay_h5']) ? '1' : '0',

                'payment_wxpay_enabled' => !empty($_POST['payment_wxpay_enabled']) ? '1' : '0',
                'payment_wxpay_mch_id' => trim((string)($_POST['payment_wxpay_mch_id'] ?? '')),
                'payment_wxpay_app_id' => trim((string)($_POST['payment_wxpay_app_id'] ?? '')),
                'payment_wxpay_key' => trim((string)($_POST['payment_wxpay_key'] ?? '')),
                'payment_wxpay_h5' => !empty($_POST['payment_wxpay_h5']) ? '1' : '0',

                'payment_hupiv3_wx_enabled' => !empty($_POST['payment_hupiv3_wx_enabled']) ? '1' : '0',
                'payment_hupiv3_wx_appid' => trim((string)($_POST['payment_hupiv3_wx_appid'] ?? '')),
                'payment_hupiv3_wx_secret' => trim((string)($_POST['payment_hupiv3_wx_secret'] ?? '')),
                'payment_hupiv3_wx_gateway' => trim((string)($_POST['payment_hupiv3_wx_gateway'] ?? '')),

                'payment_hupiv3_ali_enabled' => !empty($_POST['payment_hupiv3_ali_enabled']) ? '1' : '0',
                'payment_hupiv3_ali_appid' => trim((string)($_POST['payment_hupiv3_ali_appid'] ?? '')),
                'payment_hupiv3_ali_secret' => trim((string)($_POST['payment_hupiv3_ali_secret'] ?? '')),
                'payment_hupiv3_ali_gateway' => trim((string)($_POST['payment_hupiv3_ali_gateway'] ?? '')),

                'payment_xunhu_wx_enabled' => !empty($_POST['payment_xunhu_wx_enabled']) ? '1' : '0',
                'payment_xunhu_wx_mchid' => trim((string)($_POST['payment_xunhu_wx_mchid'] ?? '')),
                'payment_xunhu_wx_key' => trim((string)($_POST['payment_xunhu_wx_key'] ?? '')),
                'payment_xunhu_wx_gateway' => trim((string)($_POST['payment_xunhu_wx_gateway'] ?? 'https://api.xunhupay.com/payment/do.html')),

                'payment_xunhu_ali_enabled' => !empty($_POST['payment_xunhu_ali_enabled']) ? '1' : '0',
                'payment_xunhu_ali_mchid' => trim((string)($_POST['payment_xunhu_ali_mchid'] ?? '')),
                'payment_xunhu_ali_key' => trim((string)($_POST['payment_xunhu_ali_key'] ?? '')),
                'payment_xunhu_ali_gateway' => trim((string)($_POST['payment_xunhu_ali_gateway'] ?? 'https://api.xunhupay.com/payment/do.html')),

                'payment_epay_ali_enabled' => !empty($_POST['payment_epay_ali_enabled']) ? '1' : '0',
                'payment_epay_ali_pid' => trim((string)($_POST['payment_epay_ali_pid'] ?? '')),
                'payment_epay_ali_key' => trim((string)($_POST['payment_epay_ali_key'] ?? '')),
                'payment_epay_ali_api' => trim((string)($_POST['payment_epay_ali_api'] ?? '')),

                'payment_epay_wx_enabled' => !empty($_POST['payment_epay_wx_enabled']) ? '1' : '0',
                'payment_epay_wx_pid' => trim((string)($_POST['payment_epay_wx_pid'] ?? '')),
                'payment_epay_wx_key' => trim((string)($_POST['payment_epay_wx_key'] ?? '')),
                'payment_epay_wx_api' => trim((string)($_POST['payment_epay_wx_api'] ?? '')),

                'payment_paypal_enabled' => !empty($_POST['payment_paypal_enabled']) ? '1' : '0',
                'payment_paypal_username' => trim((string)($_POST['payment_paypal_username'] ?? '')),
                'payment_paypal_password' => trim((string)($_POST['payment_paypal_password'] ?? '')),
                'payment_paypal_signature' => trim((string)($_POST['payment_paypal_signature'] ?? '')),
                'payment_paypal_currency' => trim((string)($_POST['payment_paypal_currency'] ?? 'USD')) ?: 'USD',
                'payment_paypal_rate' => trim((string)($_POST['payment_paypal_rate'] ?? '0.14')) ?: '0.14',
                'payment_paypal_sandbox' => !empty($_POST['payment_paypal_sandbox']) ? '1' : '0',

                'payment_usdt_enabled' => !empty($_POST['payment_usdt_enabled']) ? '1' : '0',
                'payment_usdt_address' => trim((string)($_POST['payment_usdt_address'] ?? '')),
                'payment_usdt_rate' => trim((string)($_POST['payment_usdt_rate'] ?? '0.14')) ?: '0.14',
                'payment_usdt_auto' => !empty($_POST['payment_usdt_auto']) ? '1' : '0',
            ];
            save_site_settings($settings);
            flash('支付设置已保存', 'info');
            redirect('/admin.php?view=settings&tab=payment');
        } elseif ($act === 'sms_settings_save') {
            $settings = [
                'sms_provider' => in_array($_POST['sms_provider'] ?? '', ['aliyun','tencent'], true) ? $_POST['sms_provider'] : '',
                'sms_access_key_id' => trim((string)($_POST['sms_access_key_id'] ?? '')),
                'sms_access_key_secret' => trim((string)($_POST['sms_access_key_secret'] ?? '')),
                'sms_sign_name' => text_limit(trim((string)($_POST['sms_sign_name'] ?? '')), 30),
                'sms_template_code' => trim((string)($_POST['sms_template_code'] ?? '')),
            ];
            save_site_settings($settings);
            flash('短信接口设置已保存', 'info');
            redirect('/admin.php?view=settings&tab=sms');
        } elseif ($act === 'mail_settings_save') {
            $settings = [
                'mail_driver' => 'smtp',
                'mail_host' => trim((string)($_POST['mail_host'] ?? '')),
                'mail_port' => (string)max(1, min(65535, (int)($_POST['mail_port'] ?? 465))),
                'mail_username' => trim((string)($_POST['mail_username'] ?? '')),
                'mail_password' => trim((string)($_POST['mail_password'] ?? '')),
                'mail_encryption' => in_array($_POST['mail_encryption'] ?? '', ['ssl','tls'], true) ? $_POST['mail_encryption'] : 'ssl',
                'mail_from_address' => trim((string)($_POST['mail_from_address'] ?? '')),
                'mail_from_name' => text_limit(trim((string)($_POST['mail_from_name'] ?? '')), 60),
            ];
            save_site_settings($settings);
            flash('邮件接口设置已保存', 'info');
            redirect('/admin.php?view=settings&tab=mail');
        } elseif ($act === 'seo_settings_save') {
            $settings = [
                'baidu_submit_site' => text_limit(trim((string)($_POST['baidu_submit_site'] ?? '')), 120),
                'baidu_submit_token' => text_limit(trim((string)($_POST['baidu_submit_token'] ?? '')), 120),
                'baidu_submit_auto_enabled' => !empty($_POST['baidu_submit_auto_enabled']) ? '1' : '0',
            ];
            save_site_settings($settings);
            flash('SEO 与收录设置已保存', 'info');
            redirect('/admin.php?view=settings&tab=seo');
        } elseif ($act === 'baidu_submit_manual') {
            $mode = trim((string)($_POST['push_mode'] ?? 'custom'));
            $urlsToSubmit = [];
            $siteHost = trim((string)site_setting('baidu_submit_site', ''));
            $prefix = '';
            if ($siteHost !== '') {
                $prefix = preg_match('#^https?://#i', $siteHost) ? rtrim($siteHost, '/') : ('https://' . rtrim($siteHost, '/'));
            }

            if ($mode === 'all') {
                if ($prefix === '') throw new InvalidArgumentException('请先配置并保存有效的百度站点域名（如 https://example.com）');
                // 汇总系统所有公开页面
                $urlsToSubmit[] = $prefix . '/';
                $urlsToSubmit[] = $prefix . '/?page=prompts';
                $urlsToSubmit[] = $prefix . '/?page=prompts&type=image';
                $urlsToSubmit[] = $prefix . '/?page=prompts&type=video';
                $urlsToSubmit[] = $prefix . '/?page=apps';
                $urlsToSubmit[] = $prefix . '/?page=agents';
                $urlsToSubmit[] = $prefix . '/?page=service';
                $urlsToSubmit[] = $prefix . '/?page=vip';

                // 获取启用的提示词
                try {
                    $prompts = $pdo->query('SELECT id FROM prompts WHERE enabled=1 ORDER BY id DESC LIMIT 500')->fetchAll();
                    foreach ($prompts as $p) {
                        $urlsToSubmit[] = $prefix . '/?page=prompts&id=' . (int)$p['id'];
                    }
                } catch (Exception $e) {}
            } else {
                $rawUrls = trim((string)($_POST['custom_urls'] ?? ''));
                if ($rawUrls === '') throw new InvalidArgumentException('请输入待推送的链接');
                $lines = preg_split('/[\r\n]+/', $rawUrls);
                foreach ($lines as $ln) {
                    $ln = trim($ln);
                    if ($ln !== '') $urlsToSubmit[] = $ln;
                }
            }

            $res = baidu_submit_urls($urlsToSubmit);
            if ($res['ok']) {
                $msg = '推送请求成功！本次成功推送 ' . (int)$res['success'] . ' 条，今日剩余配额 ' . (int)$res['remain'] . ' 条。';
                if (!empty($res['not_same_site'])) {
                    $msg .= ' [非本站链接 ' . count($res['not_same_site']) . ' 条]';
                }
                if (!empty($res['not_valid'])) {
                    $msg .= ' [不合规链接 ' . count($res['not_valid']) . ' 条]';
                }
                flash($msg, 'info');
            } else {
                flash('百度推送失败：' . ($res['message'] ?? '未知错误') . (isset($res['error']) ? ' (' . $res['error'] . ')' : ''), 'error');
            }
            redirect('/admin.php?view=settings&tab=seo');
        } elseif ($act === 'sitemap_generate') {
            try {
                require_once __DIR__ . '/sitemap.php';
                $urls = generate_site_urls();
                $xml = render_sitemap_xml($urls);
                file_put_contents(__DIR__ . '/sitemap.xml', $xml);
                flash('Sitemap 已成功更新并生成到网站根目录 (sitemap.xml)，共包含 ' . count($urls) . ' 个链接。', 'info');
            } catch (Throwable $e) {
                flash('生成 Sitemap 失败：' . $e->getMessage(), 'error');
            }
            redirect('/admin.php?view=settings&tab=seo');
        } elseif ($act === 'announcement_add' || $act === 'announcement_edit') {
            ensure_feature_tables();
            $id=(int)($_POST['aid']??0);$title=text_limit(trim((string)($_POST['atitle']??'')),160);$content=trim((string)($_POST['acontent']??''));$category=text_limit(trim((string)($_POST['acategory']??'系统通知'))?:'系统通知',40);$sort=(int)($_POST['asort']??0);$pinned=!empty($_POST['apinned'])?1:0;$enabled=!empty($_POST['aenabled'])?1:0;$starts=trim((string)($_POST['astarts']??''));$ends=trim((string)($_POST['aends']??''));if($title===''||$content==='')throw new InvalidArgumentException('公告标题和内容不能为空');$starts=$starts!==''?str_replace('T',' ',$starts):null;$ends=$ends!==''?str_replace('T',' ',$ends):null;if($id){$s=$pdo->prepare('UPDATE system_announcements SET title=?,content=?,category=?,is_pinned=?,enabled=?,sort_order=?,starts_at=?,ends_at=?,updated_at=NOW() WHERE id=?');$s->execute([$title,$content,$category,$pinned,$enabled,$sort,$starts,$ends,$id]);flash('系统公告已更新','info');}else{$s=$pdo->prepare('INSERT INTO system_announcements(title,content,category,is_pinned,enabled,sort_order,published_at,starts_at,ends_at,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),?,?,NOW(),NOW())');$s->execute([$title,$content,$category,$pinned,$enabled,$sort,$starts,$ends]);flash('系统公告已添加','info');}redirect('/admin.php?view=announcements');
        } elseif ($act === 'announcement_delete') {
            ensure_feature_tables();$id=(int)($_POST['aid']??0);if($id){$s=$pdo->prepare('DELETE FROM system_announcements WHERE id=?');$s->execute([$id]);}flash('系统公告已删除','info');redirect('/admin.php?view=announcements');
        } elseif ($act === 'announcement_toggle') {
            ensure_feature_tables();$id=(int)($_POST['aid']??0);if($id){$s=$pdo->prepare('UPDATE system_announcements SET enabled=1-enabled,updated_at=NOW() WHERE id=?');$s->execute([$id]);}redirect('/admin.php?view=announcements');
        } elseif ($act === 'vip_plan_add' || $act === 'vip_plan_edit') {
            ensure_feature_tables();$id=(int)($_POST['pid']??0);$name=text_limit(trim((string)($_POST['pname']??'')),80);$price=(float)($_POST['pprice']??0);$days=(int)($_POST['pdays']??0);$pts=(int)($_POST['ppoints']??0);$desc=trim((string)($_POST['pdesc']??''));$sort=(int)($_POST['psort']??0);$color=text_limit(trim((string)($_POST['pcolor']??'#2f63d8')),10);$icon=text_limit(trim((string)($_POST['picon']??'♛')),20);$enabled=!empty($_POST['penabled'])?1:0;if($name==='')throw new InvalidArgumentException('套餐名称不能为空');if($days<=0)throw new InvalidArgumentException('有效天数必须大于0');if($id){$s=$pdo->prepare('UPDATE vip_plans SET name=?,price=?,duration_days=?,points=?,description=?,sort_order=?,color=?,icon=?,enabled=?,updated_at=NOW() WHERE id=?');$s->execute([$name,$price,$days,$pts,$desc,$sort,$color,$icon,$enabled,$id]);flash('会员套餐已更新','info');}else{$s=$pdo->prepare('INSERT INTO vip_plans(name,price,duration_days,points,description,sort_order,color,icon,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW())');$s->execute([$name,$price,$days,$pts,$desc,$sort,$color,$icon,$enabled]);flash('会员套餐已添加','info');}redirect('/admin.php?view=vip_plans');
        } elseif ($act === 'vip_plan_delete') {
            ensure_feature_tables();$id=(int)($_POST['pid']??0);if($id){$s=$pdo->prepare('DELETE FROM vip_plans WHERE id=?');$s->execute([$id]);}flash('会员套餐已删除','info');redirect('/admin.php?view=vip_plans');
        } elseif ($act === 'vip_plan_toggle') {
            ensure_feature_tables();$id=(int)($_POST['pid']??0);if($id){$s=$pdo->prepare('UPDATE vip_plans SET enabled=1-enabled,updated_at=NOW() WHERE id=?');$s->execute([$id]);}redirect('/admin.php?view=vip_plans');
        } elseif ($act === 'points_pkg_add' || $act === 'points_pkg_edit') {
            $id=(int)($_POST['pkg_id']??0);
            $name=text_limit(trim((string)($_POST['pkg_name']??'')),80);
            $points=(int)($_POST['pkg_points']??0);
            $price=(float)($_POST['pkg_price']??0);
            $badge=text_limit(trim((string)($_POST['pkg_badge']??'')),30);
            $sort=(int)($_POST['pkg_sort']??0);
            $enabled=!empty($_POST['pkg_enabled'])?1:0;
            if($name==='') throw new InvalidArgumentException('加油包名称不能为空');
            if($points<=0) throw new InvalidArgumentException('充值积分必须大于0');
            if($price<0) throw new InvalidArgumentException('售价不能小于0');
            if($id){
                $s=$pdo->prepare('UPDATE points_packages SET name=?,points=?,price=?,badge=?,sort_order=?,enabled=? WHERE id=?');
                $s->execute([$name,$points,$price,$badge,$sort,$enabled,$id]);
                flash('积分加油包套餐已更新','info');
            }else{
                $s=$pdo->prepare('INSERT INTO points_packages(name,points,price,badge,sort_order,enabled,created_at) VALUES(?,?,?,?,?,?,NOW())');
                $s->execute([$name,$points,$price,$badge,$sort,$enabled]);
                flash('积分加油包套餐已添加','info');
            }
            redirect('/admin.php?view=points_packages');
        } elseif ($act === 'points_pkg_delete') {
            $id=(int)($_POST['pkg_id']??0);
            if($id){
                $s=$pdo->prepare('DELETE FROM points_packages WHERE id=?');
                $s->execute([$id]);
            }
            flash('积分加油包套餐已删除','info');
            redirect('/admin.php?view=points_packages');
        } elseif ($act === 'points_pkg_toggle') {
            $id=(int)($_POST['pkg_id']??0);
            if($id){
                $s=$pdo->prepare('UPDATE points_packages SET enabled=1-enabled WHERE id=?');
                $s->execute([$id]);
            }
            redirect('/admin.php?view=points_packages');
        } elseif ($act === 'home_add' || $act === 'home_edit') {
            $id = (int)($_POST['bid'] ?? 0);
            $kind = in_array($_POST['bkind'] ?? '', ['hero','ad'], true) ? $_POST['bkind'] : 'ad';
            $title = trim((string)($_POST['btitle'] ?? ''));
            if ($title === '') throw new InvalidArgumentException('模块标题不能为空');
            $subtitle = trim((string)($_POST['bsubtitle'] ?? ''));
            $badge = trim((string)($_POST['bbadge'] ?? ''));
            $image = trim((string)($_POST['bimage_url'] ?? ''));
            if (!empty($_FILES['bimage']) && is_uploaded_file($_FILES['bimage']['tmp_name'])) {
                $f = $_FILES['bimage'];
                $size = (int)$f['size'];
                if ($size <= 0 || $size > 50*1024*1024) throw new InvalidArgumentException('图片文件大小超出限制（50MB）');
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) throw new InvalidArgumentException('不支持的图片类型：' . $ext);
                $dir = __DIR__ . '/uploads';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('图片上传失败');
                $image = '/uploads/' . $name;
            }
            if ($image === '') throw new InvalidArgumentException('图片地址不能为空');
            $link = trim((string)($_POST['blink_url'] ?? '/')) ?: '/';
            $sort = (int)($_POST['bsort'] ?? 0);
            $enabled = !empty($_POST['benabled']) ? 1 : 0;
            if ($act === 'home_add') {
                $s = $pdo->prepare('INSERT INTO home_blocks(kind,title,subtitle,badge,image_url,link_url,sort_order,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())');
                $s->execute([$kind,$title,$subtitle,$badge,$image,$link,$sort,$enabled]);
                flash('首页模块已添加', 'info');
            } else {
                if ($id <= 0) throw new InvalidArgumentException('无效的模块 ID');
                $s = $pdo->prepare('UPDATE home_blocks SET kind=?,title=?,subtitle=?,badge=?,image_url=?,link_url=?,sort_order=?,enabled=?,updated_at=NOW() WHERE id=?');
                $s->execute([$kind,$title,$subtitle,$badge,$image,$link,$sort,$enabled,$id]);
                flash('首页模块已更新', 'info');
            }
            redirect('/admin.php?view=home_blocks');
        } elseif ($act === 'home_delete') {
            $id = (int)($_POST['bid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的模块 ID');
            $pdo->prepare('DELETE FROM home_blocks WHERE id=?')->execute([$id]);
            flash('首页模块已删除', 'info');
            redirect('/admin.php?view=home_blocks');
        } elseif ($act === 'menu_add' || $act === 'menu_edit') {
            $id = (int)($_POST['mid'] ?? 0);
            $label = trim((string)($_POST['mlabel'] ?? ''));
            if ($label === '') throw new InvalidArgumentException('菜单名称不能为空');
            $subtitle = trim((string)($_POST['msubtitle'] ?? ''));
            $icon = trim((string)($_POST['micon'] ?? '□')) ?: '□';
            $url = trim((string)($_POST['murl'] ?? '/')) ?: '/';
            $pageKey = trim((string)($_POST['mpage_key'] ?? ''));
            $badge = trim((string)($_POST['mbadge'] ?? ''));
            $sort = (int)($_POST['msort'] ?? 0);
            $enabled = !empty($_POST['menabled']) ? 1 : 0;
            if ($act === 'menu_add') {
                $s = $pdo->prepare('INSERT INTO site_menus(label,subtitle,icon,url,page_key,badge,sort_order,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())');
                $s->execute([$label,$subtitle,$icon,$url,$pageKey,$badge,$sort,$enabled]);
                flash('前台菜单已添加', 'info');
            } else {
                if ($id <= 0) throw new InvalidArgumentException('无效的菜单 ID');
                $s = $pdo->prepare('UPDATE site_menus SET label=?,subtitle=?,icon=?,url=?,page_key=?,badge=?,sort_order=?,enabled=?,updated_at=NOW() WHERE id=?');
                $s->execute([$label,$subtitle,$icon,$url,$pageKey,$badge,$sort,$enabled,$id]);
                flash('前台菜单已更新', 'info');
            }
            redirect('/admin.php?view=site_menus');
        } elseif ($act === 'card_batch_generate') {
            $type = in_array($_POST['card_type'] ?? '', ['points','vip'], true) ? $_POST['card_type'] : 'points';
            $points = (int)($_POST['points'] ?? 0);
            $vipDays = (int)($_POST['vip_days'] ?? 0);
            $count = (int)($_POST['count'] ?? 10);
            $remark = trim((string)($_POST['remark'] ?? ''));
            $cards = generate_batch_cards($type, $points, $vipDays, $count, $remark);
            flash('成功批量生成 ' . count($cards) . ' 张卡密', 'info');
            redirect('/admin.php?view=cards');
        } elseif ($act === 'card_delete') {
            $id = (int)($_POST['cid'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM recharge_cards WHERE id=?')->execute([$id]);
                flash('卡密已删除', 'info');
            }
            redirect('/admin.php?view=cards');
        } elseif ($act === 'menu_delete') {
            $id = (int)($_POST['mid'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('无效的菜单 ID');
            $pdo->prepare('DELETE FROM site_menus WHERE id=?')->execute([$id]);
            flash('前台菜单已删除', 'info');
            redirect('/admin.php?view=site_menus');
        } elseif ($act === 'sync') {
            $token = trim((string)($_POST['ltoken'] ?? ''));
            $pages = max(1, min(50, (int)($_POST['lpages'] ?? 50)));
            $loose = !empty($_POST['loose_sync']);
            $syncType = in_array($_POST['sync_type'] ?? '', ['image','video'], true) ? $_POST['sync_type'] : '';
            $added = 0; $updated = 0; $total = 0;
            $skip = ['bad_id'=>0,'type'=>0,'no_cover'=>0,'bad_title'=>0,'short_text'=>0,'keyword'=>0,'generic_portrait'=>0];
            for ($pg = 0; $pg < $pages; $pg++) {
                $items = lingjing_sync_page($token, $pg, $syncType);
                if ($items === null) break;
                foreach ($items as $it) {
                    $total++;
                    $srcId = (int)($it['id'] ?? 0);
                    if ($srcId <= 0) { $skip['bad_id']++; continue; }
                    $exists = $pdo->prepare('SELECT id FROM prompts WHERE source="lingjingx" AND source_id=? LIMIT 1');
                    $exists->execute([$srcId]);
                    $existingId = (int)$exists->fetchColumn();
                    $type = in_array($it['作品类型'] ?? 'image', ['image','video'], true) ? $it['作品类型'] : 'image';
                    if ($syncType !== '' && $type !== $syncType) { $skip['type']++; continue; }
                    $cats = is_array($it['分类'] ?? null) ? implode(',', $it['分类']) : '';
                    $full = trim((string)($it['作品标题'] ?? ''));
                    if ($full === '' || $full === '无') $full = '灵感作品 #' . $srcId;
                    $title = $full;
                    if (mb_strlen($title) > 24) $title = mb_substr($title, 0, 24) . '…';
                    $cover = trim((string)($it['作品链接'] ?? ''));
                    $text = trim((string)($it['提示词'] ?? ($it['prompt'] ?? '')));
                    if ($text === '') $text = $full;
                    if ($cover === '' || !preg_match('/^https?:\/\//i', $cover)) { $skip['no_cover']++; continue; }
                    if ($title === '' || mb_strlen($title) < 2 || preg_match('/^(1|2|3|4|5|6|7|8|9|0|无|未|test|demo|示例|默认|空白|blank)$/u', trim($title))) { $skip['bad_title']++; continue; }
                    $low = mb_strtolower($title . ' ' . $text);
                    if (!$loose) {
                        if (mb_strlen($text) < 20) { $skip['short_text']++; continue; }
                        if (preg_match('/\b(ai ppt|ppt|测试|test|demo|样例)\b/u', $low)) { $skip['keyword']++; continue; }
                        if ($type === 'image' && preg_match('/(^|\s)(学生|美女|人像摄影)(\s|$)/u', $title) && mb_strlen($text) < 120) { $skip['generic_portrait']++; continue; }
                    }
                    if ($existingId > 0) {
                        $s = $pdo->prepare('UPDATE prompts SET type=?, cat=?, title=?, text=?, cover_url=?, points=0, enabled=1, updated_at=NOW() WHERE id=?');
                        $s->execute([$type, $cats, $title, $text, $cover ?: null, $existingId]);
                        $updated++;
                    } else {
                        $s = $pdo->prepare('INSERT INTO prompts(type,cat,title,text,cover_url,points,source,source_id,enabled,created_at,updated_at) VALUES(?,?,?,?,?,0,?,?,1,NOW(),NOW())');
                        $s->execute([$type, $cats, $title, $text, $cover ?: null, 'lingjingx', $srcId]);
                        $added++;
                    }
                }
            }
            $skipped = array_sum($skip);
            $typeLabel = $syncType === 'video' ? '；仅同步视频模板' : ($syncType === 'image' ? '；仅同步图片模板' : '');
            flash("同步完成：拉取 {$total} 条，新增 {$added} 条，更新 {$updated} 条，过滤 {$skipped} 条（无效ID {$skip['bad_id']}，类型不符 {$skip['type']}，无封面 {$skip['no_cover']}，标题无效 {$skip['bad_title']}，正文过短 {$skip['short_text']}，关键词 {$skip['keyword']}，泛人像 {$skip['generic_portrait']}）" . ($loose ? '；已启用宽松找回模式' : '') . $typeLabel, 'info');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('/admin.php?view=prompts');
}

/* 复现灵境前端 JiaMi 模块的 token 签名（不保存原始 token） */
function lingjing_token_signature($token) {
    $seed = [188,125,42,166,175,21,53,39,111,122,214,134,92,108,3,33,63,102,67,255,152,119,164,104,166,113,195,231,106,99,61,121];
    $key = '';
    $state = 137;
    foreach ($seed as $value) {
        $key .= chr(($value ^ $state) & 255);
        $state = ($state * 17 + 23) & 255;
    }
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $mixed = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $alphaNum = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $mixedNum = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $pick = function ($chars, $length) {
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) $out .= $chars[random_int(0, $max)];
        return $out;
    };
    $nonce = $pick($lower, 5) . '.' . $pick('0123456789', 3) . '@' . $pick($mixed, 6) . '-' . $pick($alphaNum, 4) . '=' . $pick($mixedNum, 6);
    $input = $nonce . '|' . (string)$token;
    $bytes = unpack('C*', $input);
    $transformed = '';
    foreach ($bytes as $byte) $transformed .= chr(($byte + 7) & 255);
    $xored = '';
    $keyLen = strlen($key);
    for ($i = 0, $len = strlen($transformed); $i < $len; $i++) $xored .= chr(ord($transformed[$i]) ^ ord($key[$i % $keyLen]));
    return base64_encode(strrev($xored));
}

/* 从 lingjingx.com 灵感广场同步一页 */
function lingjing_sync_page($token, $page, $type = '') {
    $api = 'https://api.lingkeai.ai/linggan/' . ($token !== '' ? 'guangchang' : 'shouye_guangchang');
    $payload = ['page' => $page];
    if (in_array($type, ['image','video'], true)) $payload['作品类型'] = $type;
    $headers = ['Content-Type: application/json', 'User-Agent: Mozilla/5.0'];
    if ($token !== '') {
        // 灵境前端并不直接发送 token，而是使用 JiaMi 模块生成签名头。
        $headers[] = 'token: ' . lingjing_token_signature($token);
        $headers[] = 'x-kongjian: geren';
    }
    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('同步网络请求失败');
    $data = json_decode($body, true);
    // 认证接口在不同账号/版本上可能返回 401；不要让整个同步失败，自动回退到公开广场接口。
    if ($token !== '' && is_array($data) && (int)($data['code'] ?? 0) === 401) {
        $fallback = curl_init('https://api.lingkeai.ai/linggan/shouye_guangchang');
        curl_setopt_array($fallback, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: Mozilla/5.0'],
        ]);
        $fallbackBody = curl_exec($fallback);
        curl_close($fallback);
        $fallbackData = json_decode((string)$fallbackBody, true);
        if (is_array($fallbackData) && (int)($fallbackData['code'] ?? 0) === 200) $data = $fallbackData;
    }
    if (!is_array($data) || ($data['code'] ?? 0) !== 200) {
        throw new RuntimeException('同步接口返回错误：' . ($data['msg'] ?? '未知错误'));
    }
    $list = $data['data']['list'] ?? [];
    if (!is_array($list) || count($list) === 0) return null;
    return $list;
}
$admin = admin_user();
$pdo = db();
try {
    $statUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $statGens  = (int)$pdo->query('SELECT COUNT(*) FROM generations')->fetchColumn();
    $statToday = (int)$pdo->query('SELECT COUNT(*) FROM generations WHERE DATE(created_at)=CURDATE()')->fetchColumn();
    $statTools = count(toolbox_items());
} catch (Exception $e) {
    $statUsers = $statGens = $statToday = $statTools = 0;
}

$nav = [
    'dashboard' => ['仪表盘', '▦'],
    'users'     => ['用户管理', '◉'],
    'generations' => ['创作记录', '✎'],
    'models'    => ['模型管理', '◈'],
    'prompts'   => ['提示词广场', '✧'],
    'home_blocks' => ['首页模块', '▣'],
    'site_menus' => ['前台菜单', '☰'],
    'announcements' => ['系统公告', '♢'],
    'vip_plans' => ['会员套餐', '♛'],
    'points_packages' => ['积分加油包', '💎'],
    'agents'    => ['智能体管理', '▦'],
    'cards'     => ['卡密管理', '🎟️'],
    'settings'  => ['系统设置', '⚙'],
];

function nav_active($view, $key) { return $view === $key ? ' class="active"' : ''; }
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($nav[$view][0])?> · 灵创AI 管理后台</title>
<link rel="stylesheet" href="/assets/admin.css?v=20260901-users-v1">
</head>
<body class="admin-body">
<!-- ===== 独立后台侧边栏 ===== -->
<aside class="admin-sidebar">
  <div class="admin-brand"><b>✦</b><div><strong>灵创AI 管理后台</strong><small>LINGCHUANG CONSOLE</small></div></div>
  <nav class="admin-nav">
    <div class="nav-group">运营管理</div>
    <a href="/admin.php"<?=nav_active($view,'dashboard')?>><i>▦</i><span>仪表盘</span></a>
    <a href="/admin.php?view=users"<?=nav_active($view,'users')?>><i>◉</i><span>用户管理</span></a>
    <a href="/admin.php?view=generations"<?=nav_active($view,'generations')?>><i>✎</i><span>创作记录</span></a>
    <div class="nav-group">平台配置</div>
    <a href="/admin.php?view=models"<?=nav_active($view,'models')?>><i>◈</i><span>模型管理</span></a>
    <a href="/admin.php?view=prompts"<?=nav_active($view,'prompts')?>><i>✧</i><span>提示词广场</span></a>
    <a href="/admin.php?view=home_blocks"<?=nav_active($view,'home_blocks')?>><i>▣</i><span>首页模块</span></a>
    <a href="/admin.php?view=site_menus"<?=nav_active($view,'site_menus')?>><i>☰</i><span>前台菜单</span></a>
<a href="/admin.php?view=announcements"<?=nav_active($view,'announcements')?>><i>♢</i><span>系统公告</span></a>
  <a href="/admin.php?view=vip_plans"<?=nav_active($view,'vip_plans')?>><i>♛</i><span>会员套餐</span></a>
  <a href="/admin.php?view=points_packages"<?=nav_active($view,'points_packages')?>><i>💎</i><span>积分加油包</span></a>
  <a href="/admin.php?view=agents"<?=nav_active($view,'agents')?>><i>▦</i><span>智能体管理</span></a>
  <a href="/admin.php?view=cards"<?=nav_active($view,'cards')?>><i>🎟️</i><span>卡密管理</span></a>
  <a href="/admin.php?view=settings"<?=nav_active($view,'settings')?>><i>⚙</i><span>系统设置</span></a>
  </nav>
  <div class="admin-side-foot"><span class="avatar">A</span><span class="name"><?=h($admin['username'])?></span></div>
</aside>

<!-- ===== 主区域 ===== -->
<div class="admin-main">
  <header class="admin-topbar">
    <h1><?=h($nav[$view][0])?></h1>
    <span class="crumb">灵创AI / <?=h($nav[$view][0])?></span>
    <span class="spacer"></span>
    <a class="top-link" href="/" target="_blank">⤢ 查看前台</a>
    <a class="logout" href="/admin.php?logout=1">退出登录</a>
  </header>

  <main class="admin-content">
<?php $flash = take_flash(); if ($flash): ?><div class="admin-flash <?=h($flash[1])?>"><?=h($flash[0])?></div><?php endif; ?>

<?php if ($view === 'dashboard'): ?>
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-icon blue">◉</div><div><b><?=$statUsers?></b><span>注册用户</span></div></div>
      <div class="stat-card"><div class="stat-icon green">✎</div><div><b><?=$statGens?></b><span>创作任务</span></div></div>
      <div class="stat-card"><div class="stat-icon orange">☀</div><div><b><?=$statToday?></b><span>今日新增</span></div></div>
      <div class="stat-card"><div class="stat-icon purple">⌘</div><div><b><?=$statTools?></b><span>启用工具</span></div></div>
    </div>

    <?php
    try {
        $recent = $pdo->query('SELECT g.*, u.username FROM generations g LEFT JOIN users u ON u.id=g.user_id ORDER BY g.id DESC LIMIT 8')->fetchAll();
    } catch (Exception $e) { $recent = []; }
    ?>
    <div class="panel">
      <div class="panel-head"><h3>最新创作动态</h3><a class="more" href="/admin.php?view=generations">查看全部 →</a></div>
      <div class="panel-body">
        <?php if (!$recent): ?>
          <div class="empty"><b>◌</b>暂无创作记录</div>
        <?php else: ?>
        <table class="admin-table">
          <tr><th>ID</th><th>用户</th><th>类型</th><th>提示词</th><th>状态</th><th>时间</th></tr>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td><?=(int)$r['id']?></td>
            <td><?=h($r['username'] ?? '—')?></td>
            <td><span class="tag <?=h($r['type'])?>"><?=h($r['type'])?></span></td>
            <td class="prompt-cell" title="<?=h($r['prompt'])?>"><?=h($r['prompt'])?></td>
            <td><span class="tag <?=h($r['status'])?>"><?=h($r['status'])?></span></td>
            <td><?=h(date('Y-m-d H:i', strtotime($r['created_at'])))?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h3>系统信息</h3></div>
      <div class="panel-body">
        <div class="info-line"><span class="k">当前管理员</span><span class="v"><?=h($admin['username'])?></span></div>
        <div class="info-line"><span class="k">PHP 版本</span><span class="v"><?=h(PHP_VERSION)?></span></div>
        <div class="info-line"><span class="k">数据库连接</span><span class="v"><?=$pdo ? '正常' : '异常'?></span></div>
        <div class="info-line"><span class="k">AI 服务</span><span class="v"><a target="_blank" href="https://lingjingx.com/">灵境 AI</a> </span></div>
      </div>
    </div>

<?php elseif ($view === 'users'): ?>
    <?php
    $kw = trim((string)($_GET['kw'] ?? ''));
    $filterStatus = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
    $filterVip = isset($_GET['vip']) && $_GET['vip'] !== '' ? (int)$_GET['vip'] : null;
    
    // 统计总数
    $totalUsers = 0;
    $totalVips = 0;
    $totalPoints = 0;
    try {
        $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $totalVips = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE vip_until IS NOT NULL AND vip_until > NOW()")->fetchColumn();
        $totalPoints = (int)$pdo->query('SELECT SUM(points) FROM users')->fetchColumn();
    } catch(Exception $e){}

    // 分页 & 查询
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($kw !== '') {
        $where[] = '(username LIKE ? OR email LIKE ?)';
        $params[] = "%{$kw}%";
        $params[] = "%{$kw}%";
    }
    if ($filterStatus !== null) {
        $where[] = 'status = ?';
        $params[] = $filterStatus;
    }
    if ($filterVip === 1) {
        $where[] = 'vip_until IS NOT NULL AND vip_until > NOW()';
    } elseif ($filterVip === 0) {
        $where[] = '(vip_until IS NULL OR vip_until <= NOW())';
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
    $cntStmt->execute($params);
    $filteredCount = (int)$cntStmt->fetchColumn();
    $totalPages = max(1, ceil($filteredCount / $perPage));

    $sql = "SELECT id, username, email, points, vip_until, status, created_at FROM users {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $editUser = null;
    if (isset($_GET['edit_id'])) {
        $st = $pdo->prepare('SELECT id, username, email, points, vip_until, status FROM users WHERE id=?');
        $st->execute([(int)$_GET['edit_id']]);
        $editUser = $st->fetch();
    }
    ?>

    <!-- 顶部概览数据卡片 -->
    <div class="stat-grid" style="margin-bottom:20px">
      <div class="stat-card"><div class="stat-icon blue">◉</div><div><b><?=$totalUsers?></b><span>用户总数</span></div></div>
      <div class="stat-card"><div class="stat-icon orange">♛</div><div><b><?=$totalVips?></b><span>有效VIP会员</span></div></div>
      <div class="stat-card"><div class="stat-icon green">◈</div><div><b><?=number_format($totalPoints)?></b><span>全站总积分</span></div></div>
    </div>

    <!-- 用户管理主面板 -->
    <div class="panel">
      <div class="panel-head">
        <h3>用户列表（<?=$filteredCount?>）</h3>
        <a class="more" href="/admin.php?view=users&add=1">+ 新增用户</a>
      </div>
      <div class="panel-body">

        <!-- 筛选与搜索栏 -->
        <form method="get" class="user-filter-bar">
          <input type="hidden" name="view" value="users">
          <input type="text" name="kw" value="<?=h($kw)?>" placeholder="搜索用户名 / 邮箱..." class="filter-input">
          <select name="status" class="filter-select">
            <option value="">全部状态</option>
            <option value="1" <?=$filterStatus===1?'selected':''?>>正常</option>
            <option value="0" <?=$filterStatus===0?'selected':''?>>禁用</option>
          </select>
          <select name="vip" class="filter-select">
            <option value="">全部身份</option>
            <option value="1" <?=$filterVip===1?'selected':''?>>VIP 会员</option>
            <option value="0" <?=$filterVip===0?'selected':''?>>普通用户</option>
          </select>
          <button type="submit" class="btn-primary" style="padding:7px 16px;font-size:12px">筛选</button>
          <?php if($kw!=='' || $filterStatus!==null || $filterVip!==null): ?>
            <a href="/admin.php?view=users" class="btn-cancel" style="padding:7px 12px;font-size:12px">重置</a>
          <?php endif; ?>
        </form>

        <!-- 新增用户弹窗/表单 -->
        <?php if(isset($_GET['add'])): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4>新增用户</h4>
              <a class="admin-modal-close" href="/admin.php?view=users">✕</a>
            </div>
            <form method="post" class="user-form-grid">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="prompt_action" value="user_add">
              <div class="prompt-form-grid admin-config-grid two">
                <label>用户名 *<input name="username" required placeholder="登录用户名"></label>
                <label>登录密码 *<input name="password" type="password" required placeholder="设置初始密码"></label>
                <label>电子邮箱<input name="email" type="email" placeholder="user@example.com"></label>
                <label>初始积分<input name="points" type="number" min="0" value="100"></label>
                <label>赠送VIP天数<input name="vip_days" type="number" min="0" value="0" placeholder="0 表示不赠送"></label>
                <label class="checkbox-label" style="align-self:center"><input type="checkbox" name="status" value="1" checked> 启用账户</label>
              </div>
              <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
                <button class="btn-primary" type="submit">确认新增</button>
                <a class="btn-cancel" href="/admin.php?view=users">取消</a>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- 编辑用户弹窗/表单 -->
        <?php if($editUser): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4>编辑用户：<?=h($editUser['username'])?> (ID: <?=$editUser['id']?>)</h4>
              <a class="admin-modal-close" href="/admin.php?view=users">✕</a>
            </div>
            <form method="post" class="user-form-grid">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="prompt_action" value="user_edit">
              <input type="hidden" name="uid" value="<?=(int)$editUser['id']?>">
              <div class="prompt-form-grid admin-config-grid two">
                <label>电子邮箱<input name="email" type="email" value="<?=h($editUser['email']??'')?>" placeholder="电子邮箱"></label>
                <label>重置密码<input name="new_password" type="password" placeholder="留空则不修改密码"></label>
                <label>积分余额<input name="points" type="number" min="0" value="<?=(int)$editUser['points']?>"></label>
                <label>VIP 到期时间<input name="vip_until" type="datetime-local" value="<?=$editUser['vip_until'] ? date('Y-m-d\TH:i', strtotime($editUser['vip_until'])) : ''?>"></label>
                <label class="checkbox-label" style="align-self:center"><input type="checkbox" name="status" value="1" <?=(int)$editUser['status']===1?'checked':''?>> 正常启用</label>
              </div>
              <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
                <button class="btn-primary" type="submit">保存修改</button>
                <a class="btn-cancel" href="/admin.php?view=users">取消</a>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- 用户列表表格 -->
        <?php if (!$users): ?>
          <div class="empty"><b>◉</b>未找到匹配的用户</div>
        <?php else: ?>
        <table class="admin-table user-table">
          <tr>
            <th>ID</th>
            <th>用户名 / 邮箱</th>
            <th>积分余额</th>
            <th>VIP状态</th>
            <th>账号状态</th>
            <th>注册时间</th>
            <th>操作</th>
          </tr>
          <?php foreach ($users as $u): 
            $isVip = !empty($u['vip_until']) && strtotime($u['vip_until']) > time();
          ?>
          <tr>
            <td><?=(int)$u['id']?></td>
            <td>
              <strong><?=h($u['username'])?></strong>
              <?php if(!empty($u['email'])): ?><div class="muted" style="font-size:11px"><?=h($u['email'])?></div><?php endif; ?>
            </td>
            <td>
              <span class="user-points-badge"><?=(int)$u['points']?> 积分</span>
              <!-- 快捷增减积分表单 -->
              <form method="post" style="display:inline-flex;align-items:center;gap:3px;margin-left:6px" title="快捷调整积分">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="user_adjust_points">
                <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
                <input type="number" name="points_change" placeholder="±分" style="width:54px;padding:2px 4px;font-size:11px;border:1px solid #cbd5e1;border-radius:4px" required>
                <button type="submit" class="action-link" style="border:1px solid #d0d7e2;padding:2px 5px;border-radius:4px;font-size:11px">改</button>
              </form>
            </td>
            <td>
              <?php if ($isVip): ?>
                <span class="vip-tag active" title="到期时间：<?=h($u['vip_until'])?>">♛ VIP (至 <?=date('Y-m-d', strtotime($u['vip_until']))?>)</span>
              <?php else: ?>
                <span class="vip-tag">普通用户</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="tag <?=((int)$u['status']===1?'on':'off')?>"><?=((int)$u['status']===1?'正常':'禁用')?></span>
            </td>
            <td><?=h(date('Y-m-d H:i', strtotime($u['created_at'])))?></td>
            <td class="action-cell">
              <a class="action-link" href="/admin.php?view=users&edit_id=<?=(int)$u['id']?>">编辑</a>
              
              <!-- 快捷赠送 30 天 VIP -->
              <form method="post" style="display:inline" onsubmit="return confirm('确定为该用户增送 30 天 VIP 吗？')">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="user_give_vip">
                <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
                <input type="hidden" name="days" value="30">
                <button class="action-link" type="submit" style="color:#8b5cf6">+30天VIP</button>
              </form>

              <!-- 切换启/停 -->
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="user_toggle">
                <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
                <button class="action-link" type="submit"><?=((int)$u['status']===1?'禁用':'解禁')?></button>
              </form>

              <!-- 删除用户 -->
              <form method="post" style="display:inline" onsubmit="return confirm('确定永久删除该用户吗？此操作无法恢复！')">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="user_delete">
                <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
                <button class="action-link danger" type="submit">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>

        <!-- 分页控件 -->
        <?php if($totalPages > 1): ?>
        <div class="admin-pagination" style="display:flex;justify-content:center;gap:6px;margin-top:20px">
          <?php for($i=1; $i<=$totalPages; $i++): ?>
            <a href="/admin.php?view=users&p=<?=$i?>&kw=<?=urlencode($kw)?>&status=<?=urlencode((string)$filterStatus)?>&vip=<?=urlencode((string)$filterVip)?>" class="page-link <?=$i===$page?'active':''?>" style="padding:5px 11px;border:1px solid #dbe2ee;border-radius:6px;text-decoration:none;font-size:12px;color:#334155;<?=$i===$page?'background:#2f63d8;color:#fff;border-color:#2f63d8;':''?>"><?=$i?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>
    </div>

<?php elseif ($view === 'generations'): ?>
    <?php
    try {
        $gens = $pdo->query('SELECT g.*, u.username FROM generations g LEFT JOIN users u ON u.id=g.user_id ORDER BY g.id DESC LIMIT 200')->fetchAll();
    } catch (Exception $e) { $gens = []; }
    ?>
    <div class="panel">
      <div class="panel-head"><h3>创作记录（<?=count($gens)?>）</h3></div>
      <div class="panel-body">
        <?php if (!$gens): ?>
          <div class="empty"><b>✎</b>暂无创作记录</div>
        <?php else: ?>
        <table class="admin-table">
          <tr><th>ID</th><th>用户</th><th>类型</th><th>提示词</th><th>结果</th><th>状态</th><th>时间</th></tr>
          <?php foreach ($gens as $g): ?>
          <tr>
            <td><?=(int)$g['id']?></td>
            <td><?=h($g['username'] ?? '—')?></td>
            <td><span class="tag <?=h($g['type'])?>"><?=h($g['type'])?></span></td>
            <td class="prompt-cell" title="<?=h($g['prompt'])?>"><?=h($g['prompt'])?></td>
            <td><?php if($g['result_url']):?><a class="media-link" href="<?=h($g['result_url'])?>" target="_blank">查看</a><?php else:?>—<?php endif;?></td>
            <td><span class="tag <?=h($g['status'])?>"><?=h($g['status'])?></span></td>
            <td><?=h(date('Y-m-d H:i', strtotime($g['created_at'])))?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>
    </div>

<?php elseif ($view === 'models'): ?>
    <?php
    $forceRefresh = isset($_GET['refresh_models']);
    $modelGroups = refresh_model_cache($forceRefresh);
    if ($forceRefresh) { sync_model_prices(); }
    $syncError = '';
    $cacheInfo = read_model_cache();
    $totalModels = array_sum(array_map('count', $modelGroups));
    $syncTime = $cacheInfo['ts'] ?: time();
    $cachedPrices = read_model_prices();

    /* 分类元信息 */
    $catMeta = [
        'chat'  => ['💬', '对话模型'],
        'image' => ['🖼', '图片模型'],
        'video' => ['🎬', '视频模型'],
        'audio' => ['🎵', '音频模型'],
    ];

    /* 当前分类 + 分页 */
    $activeCat = $_GET['cat'] ?? 'chat';
    if (!isset($catMeta[$activeCat])) $activeCat = 'chat';
    $perPage = isset($_GET['per']) ? (int)$_GET['per'] : 20;
    if (!in_array($perPage, [20, 30, 50], true)) $perPage = 20;
    $curPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $catModels = $modelGroups[$activeCat] ?? [];
    $totalCat = count($catModels);
    $totalPages = max(1, (int)ceil($totalCat / $perPage));
    if ($curPage > $totalPages) $curPage = $totalPages;
    $pageModels = array_slice($catModels, ($curPage - 1) * $perPage, $perPage);
    ?>
    <div class="panel">
      <div class="panel-head">
        <h3>灵境 AI 模型库（共 <?=$totalModels?> 个可用模型）</h3>
        <div class="panel-head-actions">
          <button type="button" class="btn-primary" id="syncModelsBtn">⇆ 同步大模型</button>
        </div>
      </div>
      <div class="panel-body">
        <?php if ($syncError): ?><div class="login-error" style="margin-bottom:16px"><?=h($syncError)?></div><?php endif; ?>
        <div class="info-line" style="margin-bottom:14px"><span class="k">数据来源</span><span class="v">灵境 AI (LK888) · <?=date('Y-m-d H:i', $syncTime)?> 同步 · 成本价按价格优先显示最低价，加载中…</span></div>

        <!-- 分类筛选 -->
        <div class="model-tabs">
          <?php foreach ($catMeta as $ck => $cm): $cnt = count($modelGroups[$ck] ?? []); ?>
          <a href="/admin.php?view=models&cat=<?=$ck?>&per=<?=$perPage?>" class="<?=$activeCat===$ck?'active':''?>">
            <?=$cm[0]?> <?=$cm[1]?> <b><?=$cnt?></b>
          </a>
          <?php endforeach; ?>
        </div>

        <!-- 每页数量 -->
        <div class="model-toolbar">
          <span class="muted">共 <?=$totalCat?> 个 <?=$catMeta[$activeCat][1]?>，第 <?=$curPage?> / <?=$totalPages?> 页</span>
          <span class="per-page">
            每页
            <?php foreach ([20, 30, 50] as $pp): ?>
            <a href="/admin.php?view=models&cat=<?=$activeCat?>&per=<?=$pp?>" class="<?=$perPage===$pp?'active':''?>"><?=$pp?></a>
            <?php endforeach; ?>
          </span>
        </div>

        <?php if ($totalCat === 0): ?>
          <div class="empty"><b>◈</b>该分类暂无模型</div>
        <?php else: ?>
        <div class="admin-table-wrap"><table class="admin-table model-table">
          <tr><th>模型 ID</th><th>名称</th><th>类型</th><th>成本价</th><th>积分规则</th><th>可用</th></tr>
          <?php foreach ($pageModels as $m): ?>
          <tr>
            <td><code class="model-code"><?=h($m['name'])?></code></td>
            <td><?=h($m['display_name'] ?? $m['name'])?></td>
            <td><span class="tag <?=h($activeCat)?>"><?=h($catMeta[$activeCat][1])?></span></td>
            <td class="price-cell" data-price-name="<?=h($m['name'])?>"><span class="price-loading">…</span></td>
            <?php $rules = model_rules(); $rule = $rules[$activeCat][$m['name']] ?? null; ?>
            <td>
              <form method="post" class="model-rule-form">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="model_rule_save">
                <input type="hidden" name="rtype" value="<?=h($activeCat)?>">
                <input type="hidden" name="rmodel_name" value="<?=h($m['name'])?>">
                <label class="model-rule-field"><span>前台显示名称</span><input name="rlabel" value="<?=h($rule['label'] ?? ($m['display_name'] ?? $m['name']))?>" placeholder="如：Seedance"></label>
                <label class="model-rule-field"><span>菜单图标</span><input name="ricon" value="<?=h($rule['icon'] ?? '')?>" placeholder="如：↗"></label>
                <label class="model-rule-field"><span>消耗积分</span><input name="rpoints" type="number" min="0" value="<?=($rule&&(int)$rule['points']>0)?(int)$rule['points']:default_model_points($activeCat)?>" placeholder="<?=default_model_points($activeCat)?>"></label>
                <label class="model-rule-field"><span>菜单排序</span><input name="rsort" type="number" value="<?=(int)($rule['sort_order'] ?? 0)?>" placeholder="0"></label>
                <label class="model-rule-check"><input type="checkbox" name="renabled" value="1" <?=(!$rule || (int)$rule['enabled']===1?'checked':'')?>> 显示</label>
                <button type="submit">保存</button>
              </form>
              <small class="model-rule-help">消耗积分显示当前前台实际扣费；未单独保存时使用类型默认值：文本5、图片20、视频198、音频80。</small>
            </td>
            <td><?= (!empty($m['available_for_this_key']) || !isset($m['available_for_this_key'])) ? '<span class="tag on">可用</span>' : '<span class="tag off">不可用</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </table></div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div class="model-pagination">
          <?php if ($curPage > 1): ?><a href="/admin.php?view=models&cat=<?=$activeCat?>&per=<?=$perPage?>&page=<?=$curPage-1?>">‹ 上一页</a><?php endif; ?>
          <?php
          $start = max(1, $curPage - 2);
          $end = min($totalPages, $curPage + 2);
          for ($pg = $start; $pg <= $end; $pg++): ?>
          <a href="/admin.php?view=models&cat=<?=$activeCat?>&per=<?=$perPage?>&page=<?=$pg?>" class="<?=$pg===$curPage?'active':''?>"><?=$pg?></a>
          <?php endfor; ?>
          <?php if ($curPage < $totalPages): ?><a href="/admin.php?view=models&cat=<?=$activeCat?>&per=<?=$perPage?>&page=<?=$curPage+1?>">下一页 ›</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="panel">
      <div class="panel-head"><h3>计费说明</h3></div>
      <div class="panel-body" style="color:#5a6b87;font-size:13px;line-height:1.9">
        灵境 AI 采用三种计费方式：<b>按次</b>（每次调用固定价格）、<b>按 Token</b>（输入/输出 Token 单价 × 用量 ÷ 100万）、<b>按秒</b>（视频时长 × 每秒价格）。最终价格 = 基础价格 × 所有选项系数 + 所有选项加成。价格随渠道与活动动态变化，本页数据来自平台实时报价接口。
      </div>
    </div>
    <script>
    (function(){
      var syncBtn = document.getElementById('syncModelsBtn');
      if (syncBtn) {
        syncBtn.addEventListener('click', function(){
          if (!confirm('确认同步大模型吗？将从灵境 AI 重新拉取所有模型目录并刷新本地缓存，同时同步所有模型的最新价格（图片/视频/音频/对话）。')) return;
          var orig = syncBtn.innerHTML;
          syncBtn.disabled = true;
          syncBtn.innerHTML = '⏳ 同步中…';
          window.location.href = '/admin.php?view=models&refresh_models=1';
          setTimeout(function(){ if (syncBtn) { syncBtn.disabled = false; syncBtn.innerHTML = orig; } }, 15000);
        });
      }
      var cells = document.querySelectorAll('.price-cell[data-price-name]');
      if (!cells.length) return;
      var names = Array.prototype.map.call(cells, function(c){ return c.getAttribute('data-price-name'); });
      var idx = 0, done = 0, failed = 0;
      /* 缓存价格（同步大模型时已拉取） */
      var __cachedPrices = <?=json_encode(($cachedPrices['prices']??[]), JSON_UNESCAPED_UNICODE)?>;
      function fill(name, label){
        var cell = document.querySelector('.price-cell[data-price-name="' + CSS.escape(name) + '"]');
        if (cell) cell.textContent = label || '—';
      }
      function priceLabel(d){
        if (!d) return '—';
        var label = '';
        function groupCost(g){
          var method = g.billing_method || '按次';
          if (method === '按token') {
            var i = Number(g.input_token_price || 0), o = Number(g.output_token_price || 0);
            if (i > 0 || o > 0) return i + o;
          }
          var base = Number(g.base_price || 0);
          return base > 0 ? base : Number.POSITIVE_INFINITY;
        }
        var group = null, bestCost = Number.POSITIVE_INFINITY;
        (d.channel_groups || []).forEach(function(g){
          var cost = groupCost(g);
          if (cost < bestCost) { bestCost = cost; group = g; }
        });
        if (group) {
          var method = group.billing_method || '按次';
          var base = group.base_price;
          if (method === '按token') {
            var i = Number(group.input_token_price||0), o = Number(group.output_token_price||0);
            label = '最低价 ¥' + i.toFixed(4) + '/M in · ¥' + o.toFixed(4) + '/M out';
          } else if (base !== null && base !== undefined) {
            if (method === '按秒') label = '最低价 ¥' + Number(base).toFixed(4) + '/秒';
            else label = '最低价 ¥' + Number(base).toFixed(4) + '/次';
          } else {
            label = '最低价 ' + method;
          }
          if (group.success_rate_24h) label += ' · 24h成功率 ' + Number(group.success_rate_24h).toFixed(2) + '%';
        }
        return label || '—';
      }
      /* 先填缓存价格 */
      names.forEach(function(n){
        var raw = __cachedPrices[n];
        if (raw) { fill(n, priceLabel(raw)); }
      });
      /* 未缓存的逐个请求 */
      names = names.filter(function(n){ return !__cachedPrices[n]; });
      idx = 0;
      function worker(){
        if (idx >= names.length) return;
        var name = names[idx++];
        fetch('/api.php?action=model-pricing&name=' + encodeURIComponent(name), {headers:{Accept:'application/json'}})
          .then(function(r){ return r.json(); })
          .then(function(x){
            if (!x || x.ok === false) throw new Error('err');
            fill(name, priceLabel(x.data));
          })
          .catch(function(){ fill(name, '—'); failed++; })
          .then(function(){ done++; if (done + failed >= names.length) { /* all done */ } worker(); });
      }
      for (var i = 0; i < 6; i++) worker();
    })();
    </script>

<?php elseif ($view === 'prompts'): ?>
    <?php
    $promptPageSize = 20;
    $promptTypeFilter = in_array($_GET['type'] ?? '', ['image','video'], true) ? $_GET['type'] : '';
    $promptTypeQuery = $promptTypeFilter !== '' ? '&type=' . urlencode($promptTypeFilter) : '';
    if ($promptTypeFilter !== '') {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM prompts WHERE type=?');
        $countStmt->execute([$promptTypeFilter]);
        $promptTotal = (int)$countStmt->fetchColumn();
    } else {
        $promptTotal = (int)$pdo->query('SELECT COUNT(*) FROM prompts')->fetchColumn();
    }
    $promptTotalPages = max(1, (int)ceil($promptTotal / $promptPageSize));
    $promptPage = max(1, min($promptTotalPages, (int)($_GET['p'] ?? 1)));
    $promptOffset = ($promptPage - 1) * $promptPageSize;
    if ($promptTypeFilter !== '') {
        $promptStmt = $pdo->prepare('SELECT * FROM prompts WHERE type=? ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?');
        $promptStmt->bindValue(1, $promptTypeFilter, PDO::PARAM_STR);
        $promptStmt->bindValue(2, $promptPageSize, PDO::PARAM_INT);
        $promptStmt->bindValue(3, $promptOffset, PDO::PARAM_INT);
    } else {
        $promptStmt = $pdo->prepare('SELECT * FROM prompts ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?');
        $promptStmt->bindValue(1, $promptPageSize, PDO::PARAM_INT);
        $promptStmt->bindValue(2, $promptOffset, PDO::PARAM_INT);
    }
    $promptStmt->execute();
    $prompts = $promptStmt->fetchAll();
    $editPrompt = null;
    if (isset($_GET['edit_id'])) {
        $editId = (int)$_GET['edit_id'];
        $editStmt = $pdo->prepare('SELECT * FROM prompts WHERE id=? LIMIT 1');
        $editStmt->execute([$editId]);
        $editPrompt = $editStmt->fetch() ?: null;
    }
    ?>
    <div class="panel">
      <div class="panel-head">
        <h3>提示词管理（<?=$promptTotal?> 条）<span class="panel-page-note">第 <?=$promptPage?> / <?=$promptTotalPages?> 页，每页 <?=$promptPageSize?> 条</span></h3>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <a class="more" href="/admin.php?view=prompts&p=<?=$promptPage?><?=$promptTypeQuery?>&add=1">+ 添加提示词</a>
          <a class="more" href="/admin.php?view=prompts&p=<?=$promptPage?><?=$promptTypeQuery?>&sync=1">⟳ 重新同步灵感广场</a>
          <button type="button" class="more" id="prompt-gallery-select-all">全选</button>
          <button type="button" class="more" id="prompt-gallery-batch-delete" style="color:#d9534a">批量删除</button>
        </div>
      </div>
      <div class="panel-body">
        <div class="prompt-admin-filters">
          <a class="<?=$promptTypeFilter===''?'active':''?>" href="/admin.php?view=prompts">全部</a>
          <a class="<?=$promptTypeFilter==='image'?'active':''?>" href="/admin.php?view=prompts&type=image">图片</a>
          <a class="<?=$promptTypeFilter==='video'?'active':''?>" href="/admin.php?view=prompts&type=video">视频</a>
        </div>
        <?php if (isset($_GET['sync'])): ?>
        <div class="sync-box">
          <h4>从 lingjingx.com 重新同步灵感广场</h4>
          <p>同步会按来源 ID 覆盖更新已存在的 lingjingx 模板，并新增不存在的模板。需要登录 lingjingx.com 后获取 token：打开 <b>https://lingjingx.com</b> 登录 → F12 开发者工具 → Application → Local Storage → 复制 <code>token</code> 的值。</p>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="sync">
            <input type="text" name="ltoken" placeholder="粘贴 token（eyJhbGciOi...）" style="width:48%;padding:10px 12px;border:1px solid #dbe2ee;border-radius:8px;font:inherit">
              <select name="sync_type" style="width:120px;padding:10px 12px;border:1px solid #dbe2ee;border-radius:8px;font:inherit">
                <option value="video" selected>视频模板</option>
                <option value="image">图片模板</option>
                <option value="">全部类型</option>
              </select>
              <input type="number" name="lpages" value="50" min="1" max="50" style="width:80px;padding:10px 12px;border:1px solid #dbe2ee;border-radius:8px;font:inherit" title="同步页数（每页20条）">
              <label class="sync-loose-label"><input type="checkbox" name="loose_sync" value="1"> 宽松同步/找回已删除</label>
              <button type="submit" class="btn-primary" style="padding:10px 22px">开始重新同步</button>
          </form>
          <p class="muted" style="font-size:12px;color:#8a96ab">提示：视频模板需要填写 lingjingx.com 登录 token 后同步；不填 token 时只能同步公开首页图片作品。选择“视频模板”会向灵境广场接口传入 <code>作品类型=video</code>，并过滤掉接口回退时混入的非视频结果。勾选“宽松同步/找回已删除”时，只强制要求有封面和标题，适合找回之前删除过但又被严格规则过滤的模板。</p>
        </div>
        <?php endif; ?>

<?php $promptModalOpen = isset($_GET['add']) || (isset($_GET['edit_id']) && ($_GET['edit_mode'] ?? '') === 'form'); ?>
<?php if ($promptModalOpen): ?>
<div class="prompt-edit-backdrop"></div>
<form method="post" class="prompt-form prompt-edit-form-modal" enctype="multipart/form-data">
          <div class="prompt-modal-head"><h4><?=$editPrompt?'编辑提示词':'新增提示词'?></h4><a href="/admin.php?view=prompts&p=<?=$promptPage?><?=$promptTypeQuery?>" aria-label="关闭">×</a></div>
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="prompt_action" value="<?=$editPrompt?'edit':'add'?>">
          <?php if ($editPrompt): ?><input type="hidden" name="pid" value="<?=(int)$editPrompt['id']?>"><?php endif; ?>
          <div class="prompt-form-grid">
            <label>类型
              <select name="ptype">
                <option value="image" <?=($editPrompt&&$editPrompt['type']==='image'?'selected':'')?>>图片</option>
                <option value="video" <?=($editPrompt&&$editPrompt['type']==='video'?'selected':'')?>>视频</option>
              </select>
            </label>
            <label>分类
              <input name="pcat" value="<?=h($editPrompt['cat']??'')?>" placeholder="如：电商主图、人像写真">
            </label>
            <label>标题
              <input name="ptitle" value="<?=h($editPrompt['title']??'')?>" required placeholder="提示词标题">
            </label>
            <label>排序
              <input name="psort" type="number" value="<?=(int)($editPrompt['sort_order']??0)?>" placeholder="数字越小越靠前">
            </label>
            <label>积分/价格
              <input name="ppoints" type="number" value="<?=(int)($editPrompt['points']??0)?>" placeholder="消耗积分，0=免费">
            </label>
            <label>标签（逗号分隔）
              <input name="ptags" value="<?=h($editPrompt['tags']??'')?>" placeholder="如：人像, 电影感, 电商">
            </label>
          </div>
          <label>提示词内容
            <textarea name="ptext" required placeholder="输入完整的提示词文本"><?=h($editPrompt['text']??'')?></textarea>
          </label>
          <div class="prompt-form-grid prompt-upload-grid">
            <label>封面图（上传或留空）
              <div class="upload-preview" data-role="cover-preview">
                <?php if($editPrompt && $editPrompt['cover_url']): ?><img src="<?=h($editPrompt['cover_url'])?>"><?php endif; ?>
              </div>
              <input type="file" name="pcover" accept="image/*" data-upload="cover" data-target="cover_url">
              <input type="hidden" name="pcover_url" value="<?=h($editPrompt['cover_url']??'')?>">
            </label>
            <label>视频/媒体文件（视频类型必填，或填 URL）
              <div class="upload-preview" data-role="media-preview">
                <?php if($editPrompt && $editPrompt['media_url']): ?><video src="<?=h($editPrompt['media_url'])?>" controls muted></video><?php endif; ?>
              </div>
              <input type="file" name="pmedia" accept="video/*" data-upload="media" data-target="media_url">
              <input type="hidden" name="pmedia_url" value="<?=h($editPrompt['media_url']??'')?>">
              <input type="text" name="pmedia_url_text" value="<?=h($editPrompt['media_url']??'')?>" placeholder="或粘贴媒体 URL">
            </label>
          </div>
          <div class="prompt-form-foot">
            <label class="checkbox-label"><input type="checkbox" name="penabled" value="1" <?=(!$editPrompt || (int)$editPrompt['enabled']===1?'checked':'')?>> 启用</label>
            <div class="prompt-form-btns">
              <button type="submit" class="btn-primary"><?=$editPrompt?'保存修改':'添加提示词'?></button>
              <a href="/admin.php?view=prompts&p=<?=$promptPage?><?=$promptTypeQuery?>" class="btn-cancel">取消</a>
            </div>
          </div>
        </form>
        <?php endif; ?>

        <div class="prompt-gallery-grid">
          <?php if (!$prompts): ?>
          <div class="prompt-gallery-empty"><b>✧</b><span>暂无提示词，点击右上角「+ 添加提示词」开始</span></div>
          <?php else: foreach ($prompts as $p): ?>
          <div class="prompt-gallery-card" role="button" tabindex="0" data-prompt-id="<?=(int)$p['id']?>" data-prompt-title="<?=h($p['title'])?>" data-prompt-text="<?=h($p['text'])?>" data-prompt-type="<?=h($p['type'])?>" data-prompt-cat="<?=h($p['cat'])?>" data-prompt-cover="<?=h($p['cover_url']??'')?>" data-prompt-media="<?=h($p['media_url']??'')?>">
            <label class="prompt-gallery-check-wrap" aria-label="选择提示词">
              <input type="checkbox" class="prompt-gallery-check" data-prompt-check value="<?=(int)$p['id']?>">
            </label>
            <div class="prompt-gallery-preview">
              <?php if ($p['type']==='video' && $p['media_url']): ?>
                <video src="<?=h($p['media_url'])?>" muted preload="metadata"></video>
              <?php elseif ($p['cover_url']): ?>
                <img src="<?=h($p['cover_url'])?>" alt="">
              <?php else: ?>
                <span>▧</span>
              <?php endif; ?>
            </div>
            <div class="prompt-gallery-title"><?=h($p['title'])?></div>
            <div class="prompt-gallery-actions">
              <a class="action-link prompt-gallery-edit-btn" href="/admin.php?view=prompts&p=<?=$promptPage?><?=$promptTypeQuery?>&edit_id=<?=(int)$p['id']?>&edit_mode=form">编辑</a>
              <form method="post" class="prompt-gallery-delete-form" onsubmit="return confirm('确定删除这个提示词模板吗？删除后不可恢复。')">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="delete">
                <input type="hidden" name="pid" value="<?=(int)$p['id']?>">
                <button type="submit" class="action-link danger">删除</button>
              </form>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
        <?php if ($promptTotalPages > 1): ?>
        <nav class="admin-pagination" aria-label="提示词分页">
          <?php $prevPage = max(1, $promptPage - 1); $nextPage = min($promptTotalPages, $promptPage + 1); ?>
          <a class="<?=$promptPage<=1?'disabled':''?>" href="/admin.php?view=prompts&p=<?=$prevPage?><?=$promptTypeQuery?>">上一页</a>
          <?php $startPage = max(1, $promptPage - 3); $endPage = min($promptTotalPages, $promptPage + 3); ?>
          <?php if ($startPage > 1): ?><a href="/admin.php?view=prompts&p=1<?=$promptTypeQuery?>">1</a><?php if ($startPage > 2): ?><span>...</span><?php endif; ?><?php endif; ?>
          <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a class="<?=$i===$promptPage?'active':''?>" href="/admin.php?view=prompts&p=<?=$i?><?=$promptTypeQuery?>"><?=$i?></a>
          <?php endfor; ?>
          <?php if ($endPage < $promptTotalPages): ?><?php if ($endPage < $promptTotalPages - 1): ?><span>...</span><?php endif; ?><a href="/admin.php?view=prompts&p=<?=$promptTotalPages?><?=$promptTypeQuery?>"><?=$promptTotalPages?></a><?php endif; ?>
          <a class="<?=$promptPage>=$promptTotalPages?'disabled':''?>" href="/admin.php?view=prompts&p=<?=$nextPage?><?=$promptTypeQuery?>">下一页</a>
        </nav>
        <?php endif; ?>

    <div class="prompt-detail-overlay" id="prompt-detail-overlay" hidden>
      <div class="prompt-detail-modal" role="dialog" aria-modal="true" aria-labelledby="prompt-detail-title">
        <button type="button" class="prompt-detail-close" id="prompt-detail-close" aria-label="关闭">×</button>
        <div class="prompt-detail-media" id="prompt-detail-media"></div>
        <div class="prompt-detail-body">
          <h3 id="prompt-detail-title"></h3>
          <div class="prompt-detail-meta" id="prompt-detail-meta"></div>
          <p id="prompt-detail-text"></p>
          <div class="prompt-detail-actions">
            <a class="btn-primary" id="prompt-detail-edit">编辑</a>
            <form method="post" id="prompt-detail-delete-form" onsubmit="return confirm('确定删除这个提示词模板吗？删除后不可恢复。')">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="prompt_action" value="delete">
              <input type="hidden" name="pid" id="prompt-detail-delete-id" value="">
              <button type="submit" class="btn-danger">删除</button>
            </form>
            <button type="button" class="btn-cancel" id="prompt-detail-dismiss">关闭</button>
          </div>
        </div>
      </div>
    </div>
    <script>
    (function(){
      var overlay = document.getElementById('prompt-detail-overlay');
      var media = document.getElementById('prompt-detail-media');
      var title = document.getElementById('prompt-detail-title');
      var meta = document.getElementById('prompt-detail-meta');
      var text = document.getElementById('prompt-detail-text');
      var edit = document.getElementById('prompt-detail-edit');
      var deleteId = document.getElementById('prompt-detail-delete-id');
      var selectAllBtn = document.getElementById('prompt-gallery-select-all');
      var batchDeleteBtn = document.getElementById('prompt-gallery-batch-delete');
      var promptPage = <?=json_encode($promptPage)?>;
      var promptTypeQuery = <?=json_encode($promptTypeQuery)?>;
      function getCheckedCards(){ return Array.prototype.slice.call(document.querySelectorAll('[data-prompt-check]:checked')); }
      function getCheckedIds(){ return getCheckedCards().map(function(cb){ return cb.value; }); }
      function toggleSelectAll(){
        var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-prompt-check]'));
        if (!boxes.length) return;
        var allChecked = boxes.every(function(cb){ return cb.checked; });
        boxes.forEach(function(cb){ cb.checked = !allChecked; });
      }
      if (selectAllBtn) selectAllBtn.addEventListener('click', function(){ toggleSelectAll(); });
      function closeDetail(){
        if (!overlay) return;
        overlay.hidden = true;
        document.body.classList.remove('prompt-detail-open');
      }
      if (batchDeleteBtn) batchDeleteBtn.addEventListener('click', function(){
        var ids = getCheckedIds();
        if (!ids.length) { alert('请先选择要删除的提示词'); return; }
        if (!confirm('确定批量删除选中的 ' + ids.length + ' 条提示词吗？删除后不可恢复。')) return;
        var form = document.createElement('form');
        form.method = 'post';
        form.action = '/admin.php?view=prompts&p=' + encodeURIComponent(promptPage) + promptTypeQuery;
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="batch_delete">' + ids.map(function(id){ return '<input type="hidden" name="pids[]" value="' + id + '">'; }).join('');
        document.body.appendChild(form);
        form.submit();
      });
      document.querySelectorAll('.prompt-gallery-card').forEach(function(card){
        var checkWrap = card.querySelector('.prompt-gallery-check-wrap');
        var actions = card.querySelector('.prompt-gallery-actions');
        if (checkWrap) checkWrap.addEventListener('click', function(e){ e.stopPropagation(); });
        if (actions) actions.addEventListener('click', function(e){ e.stopPropagation(); });
        card.addEventListener('click', function(e){
          if (e.target.closest('.prompt-gallery-actions') || e.target.closest('.prompt-gallery-check-wrap')) return;

          meta.textContent = (card.dataset.promptType === 'video' ? '视频' : '图片') + (card.dataset.promptCat ? ' · ' + card.dataset.promptCat : '');
          text.textContent = card.dataset.promptText || '';
          edit.href = '/admin.php?view=prompts&p=' + encodeURIComponent(promptPage) + promptTypeQuery + '&edit_id=' + encodeURIComponent(card.dataset.promptId) + '&edit_mode=form';
          deleteId.value = card.dataset.promptId || '';
          media.innerHTML = '';
          if (card.dataset.promptType === 'video' && card.dataset.promptMedia) {
            media.innerHTML = '<video src="' + card.dataset.promptMedia + '" controls autoplay muted></video>';
          } else if (card.dataset.promptCover) {
            media.innerHTML = '<img src="' + card.dataset.promptCover + '" alt="">';
          } else {
            media.innerHTML = '<span>▧</span>';
          }
          overlay.hidden = false;
          document.body.classList.add('prompt-detail-open');
        });
      });
      document.getElementById('prompt-detail-close').addEventListener('click', closeDetail);
      document.getElementById('prompt-detail-dismiss').addEventListener('click', closeDetail);
      overlay.addEventListener('click', function(e){ if(e.target === overlay) closeDetail(); });
      document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeDetail(); });
    })();
    (function(){
      // 文件上传：选择后先本地预览，再异步上传并写回正式 URL
      function renderUploadPreview(preview, kind, url, local){
        if (!preview || !url) return;
        preview.innerHTML = '';
        var el = document.createElement(kind === 'media' ? 'video' : 'img');
        el.src = url;
        if (kind === 'media') {
          el.controls = true;
          el.muted = true;
        }
        preview.appendChild(el);
        preview.classList.toggle('uploading', !!local);
      }
      document.querySelectorAll('.prompt-form').forEach(function(form){
        form.addEventListener('submit', function(e){
          if (form.querySelector('.upload-preview.uploading')) {
            e.preventDefault();
            alert('文件还在上传中，请稍等上传完成后再保存。');
          }
        });
      });
      document.querySelectorAll('input[type=file][data-upload]').forEach(function(input){
        input.addEventListener('change', function(){
          var file = input.files && input.files[0];
          if (!file) return;
          var kind = input.getAttribute('data-upload');
          var preview = input.closest('label').querySelector('[data-role="' + kind + '-preview"]');
          var localUrl = URL.createObjectURL(file);
          renderUploadPreview(preview, kind, localUrl, true);

          var fd = new FormData();
          fd.append('file', file);
          fd.append('kind', kind);
          input.disabled = true;
          fetch('/api.php?action=upload', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(x){
              if (!x || x.ok !== true || !x.data || !x.data.url) throw new Error((x && x.message) || '上传失败');
              var url = x.data.url;
              renderUploadPreview(preview, kind, url, false);
              URL.revokeObjectURL(localUrl);
              var form = input.closest('form');
              var hid = form.querySelector('input[name="p' + kind + '_url"]');
              if (hid) hid.value = url;
              var txt = form.querySelector('input[name="p' + kind + '_url_text"]');
              if (txt) txt.value = url;
            })
            .catch(function(e){ if(preview) preview.classList.remove('uploading'); alert(e.message + '，当前仅为本地预览，保存前请重新上传。'); })
            .then(function(){ input.disabled = false; });
        });
      });
    })();
    </script>

<?php elseif ($view === 'home_blocks'): ?>
    <?php
    $blocks = $pdo->query('SELECT * FROM home_blocks ORDER BY sort_order,id')->fetchAll();
    $editBlock = null;
    if (isset($_GET['edit_id'])) { foreach ($blocks as $b) { if ((int)$b['id'] === (int)$_GET['edit_id']) { $editBlock = $b; break; } } }
    ?>
    <div class="panel">
      <div class="panel-head"><h3>首页红框区域管理（<?=count($blocks)?> 项）</h3><a class="more" href="/admin.php?view=home_blocks&add=1">+ 添加模块</a></div>
      <div class="panel-body">
        <?php if (isset($_GET['add']) || $editBlock): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4><?=$editBlock ? '编辑首页模块' : '添加首页模块'?></h4>
              <a class="admin-modal-close" href="/admin.php?view=home_blocks">✕</a>
            </div>
            <form method="post" class="prompt-form" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="prompt_action" value="<?=$editBlock?'home_edit':'home_add'?>">
              <?php if ($editBlock): ?><input type="hidden" name="bid" value="<?=(int)$editBlock['id']?>"><?php endif; ?>
              <div class="prompt-form-grid admin-config-grid">
                <label>类型<select name="bkind"><option value="hero" <?=($editBlock&&$editBlock['kind']==='hero'?'selected':'')?>>主横幅</option><option value="ad" <?=(!$editBlock||$editBlock['kind']==='ad'?'selected':'')?>>广告卡片</option></select></label>
                <label>标题<input name="btitle" required value="<?=h($editBlock['title']??'')?>"></label>
                <label>角标/英文标识<input name="bbadge" value="<?=h($editBlock['badge']??'')?>"></label>
                <label>排序<input name="bsort" type="number" value="<?=(int)($editBlock['sort_order']??0)?>"></label>
              </div>
              <label>副标题<textarea name="bsubtitle" placeholder="主横幅建议填写，广告卡可留空"><?=h($editBlock['subtitle']??'')?></textarea></label>
              <div class="prompt-form-grid admin-config-grid two">
                <label>图片地址<input name="bimage_url" required value="<?=h($editBlock['image_url']??'')?>" placeholder="/uploads/xxx.webp 或 /assets/home/xxx.png"><input type="file" name="bimage" accept="image/*"></label>
                <label>点击链接<input name="blink_url" value="<?=h($editBlock['link_url']??'/')?>" placeholder="/?page=image"></label>
              </div>
              <div class="prompt-form-foot"><label class="checkbox-label"><input type="checkbox" name="benabled" value="1" <?=(!$editBlock||(int)$editBlock['enabled']===1?'checked':'')?>> 启用</label><div class="prompt-form-btns"><button class="btn-primary" type="submit">保存模块</button><a class="btn-cancel" href="/admin.php?view=home_blocks">取消</a></div></div>
            </form>
          </div>
        </div>
        <?php endif; ?>
        <table class="admin-table"><tr><th>ID</th><th>类型</th><th>标题</th><th>图片</th><th>链接</th><th>状态</th><th>排序</th><th>操作</th></tr><?php foreach($blocks as $b): ?><tr><td><?=(int)$b['id']?></td><td><?=h($b['kind']==='hero'?'主横幅':'广告卡')?></td><td><?=h($b['title'])?></td><td class="prompt-cell"><?=h($b['image_url'])?></td><td><?=h($b['link_url'])?></td><td><span class="tag <?=((int)$b['enabled']?'on':'off')?>"><?=((int)$b['enabled']?'启用':'停用')?></span></td><td><?=(int)$b['sort_order']?></td><td class="action-cell"><a class="action-link" href="/admin.php?view=home_blocks&edit_id=<?=(int)$b['id']?>">编辑</a><form method="post" style="display:inline" onsubmit="return confirm('确定删除这个首页模块吗？')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="home_delete"><input type="hidden" name="bid" value="<?=(int)$b['id']?>"><button class="action-link danger" type="submit">删除</button></form></td></tr><?php endforeach; ?></table>
      </div>
    </div>

<?php elseif ($view === 'site_menus'): ?>
    <?php
    $menus = $pdo->query('SELECT * FROM site_menus ORDER BY sort_order,id')->fetchAll();
    $editMenu = null;
    if (isset($_GET['edit_id'])) { foreach ($menus as $m) { if ((int)$m['id'] === (int)$_GET['edit_id']) { $editMenu = $m; break; } } }
    ?>
    <div class="panel">
      <div class="panel-head"><h3>前台左侧菜单管理（<?=count($menus)?> 项）</h3><a class="more" href="/admin.php?view=site_menus&add=1">+ 添加菜单</a></div>
      <div class="panel-body">
        <?php if (isset($_GET['add']) || $editMenu): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4><?=$editMenu ? '编辑菜单' : '添加菜单'?></h4>
              <a class="admin-modal-close" href="/admin.php?view=site_menus">✕</a>
            </div>
            <form method="post" class="prompt-form">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="prompt_action" value="<?=$editMenu?'menu_edit':'menu_add'?>">
              <?php if ($editMenu): ?><input type="hidden" name="mid" value="<?=(int)$editMenu['id']?>"><?php endif; ?>
              <div class="prompt-form-grid admin-config-grid">
                <label>菜单名称<input name="mlabel" required value="<?=h($editMenu['label']??'')?>"></label>
                <label>副标题<input name="msubtitle" value="<?=h($editMenu['subtitle']??'')?>"></label>
                <label>图标<input name="micon" value="<?=h($editMenu['icon']??'□')?>"></label>
                <label>角标<input name="mbadge" value="<?=h($editMenu['badge']??'')?>" placeholder="new / 火爆 / 推荐"></label>
                <label>链接<input name="murl" value="<?=h($editMenu['url']??'/')?>" placeholder="/?page=image"></label>
                <label>激活页标识<input name="mpage_key" value="<?=h($editMenu['page_key']??'')?>" placeholder="home/image/video/apps/history/profile"></label>
                <label>排序<input name="msort" type="number" value="<?=(int)($editMenu['sort_order']??0)?>"></label>
              </div>
              <div class="prompt-form-foot"><label class="checkbox-label"><input type="checkbox" name="menabled" value="1" <?=(!$editMenu||(int)$editMenu['enabled']===1)?'checked':''?>> 启用</label><div class="prompt-form-btns"><button class="btn-primary" type="submit">保存菜单</button><a class="btn-cancel" href="/admin.php?view=site_menus">取消</a></div></div>
            </form>
          </div>
        </div>
        <?php endif; ?>
        <table class="admin-table"><tr><th>ID</th><th>名称</th><th>副标题</th><th>图标</th><th>角标</th><th>链接</th><th>激活页</th><th>状态</th><th>排序</th><th>操作</th></tr><?php foreach($menus as $m): ?><tr><td><?=(int)$m['id']?></td><td><?=h($m['label'])?></td><td><?=h($m['subtitle'])?></td><td><?=h($m['icon'])?></td><td><?=h($m['badge'])?></td><td><?=h($m['url'])?></td><td><?=h($m['page_key'])?></td><td><span class="tag <?=((int)$m['enabled']?'on':'off')?>"><?=((int)$m['enabled']?'启用':'停用')?></span></td><td><?=(int)$m['sort_order']?></td><td class="action-cell"><a class="action-link" href="/admin.php?view=site_menus&edit_id=<?=(int)$m['id']?>">编辑</a><form method="post" style="display:inline" onsubmit="return confirm('确定删除这个菜单吗？')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="menu_delete"><input type="hidden" name="mid" value="<?=(int)$m['id']?>"><button class="action-link danger" type="submit">删除</button></form></td></tr><?php endforeach; ?></table>
      </div>
    </div>

<?php elseif ($view === 'announcements'): ?>
    <?php ensure_feature_tables();$announcements=system_announcements(false);$editAnnouncement=null;if(isset($_GET['edit_id'])){foreach($announcements as $aa){if((int)$aa['id']===(int)$_GET['edit_id']){$editAnnouncement=$aa;break;}}} ?>
    <div class="panel">
      <div class="panel-head"><h3>系统公告</h3><a class="more" href="/admin.php?view=announcements&add=1">+ 添加公告</a></div>
      <div class="panel-body">
        <?php if(isset($_GET['add'])||$editAnnouncement): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4><?=$editAnnouncement ? '编辑系统公告' : '添加系统公告'?></h4>
              <a class="admin-modal-close" href="/admin.php?view=announcements">✕</a>
            </div>
            <form method="post" class="prompt-form announcement-form">
              <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="<?=$editAnnouncement?'announcement_edit':'announcement_add'?>"><input type="hidden" name="aid" value="<?=(int)($editAnnouncement['id']??0)?>">
              <div class="prompt-form-grid admin-config-grid three">
                <label>公告标题<input name="atitle" required value="<?=h($editAnnouncement['title']??'')?>" placeholder="请输入公告标题"></label>
                <label>公告分类<input name="acategory" value="<?=h($editAnnouncement['category']??'系统通知')?>" placeholder="系统通知"></label>
                <label>排序<input name="asort" type="number" value="<?=(int)($editAnnouncement['sort_order']??0)?>"></label>
                <label>开始时间<input name="astarts" type="datetime-local" value="<?=!empty($editAnnouncement['starts_at'])?date('Y-m-d\TH:i',strtotime($editAnnouncement['starts_at'])):''?>"></label>
                <label>结束时间<input name="aends" type="datetime-local" value="<?=!empty($editAnnouncement['ends_at'])?date('Y-m-d\TH:i',strtotime($editAnnouncement['ends_at'])):''?>"></label>
                <label class="checkbox-label" style="align-self:center"><input type="checkbox" name="apinned" value="1" <?=!empty($editAnnouncement['is_pinned'])?'checked':''?>> 置顶公告</label>
              </div>
              <label>公告内容<textarea name="acontent" rows="8" required placeholder="请输入公告内容"><?=h($editAnnouncement['content']??'')?></textarea></label>
              <div class="prompt-form-foot"><label class="checkbox-label"><input type="checkbox" name="aenabled" value="1" <?=(!$editAnnouncement||(int)$editAnnouncement['enabled']===1)?'checked':''?>> 启用并向用户展示</label><div class="prompt-form-btns"><button class="btn-primary" type="submit">保存公告</button><a class="btn-cancel" href="/admin.php?view=announcements">取消</a></div></div>
            </form>
          </div>
        </div>
        <?php endif; ?>
        <?php if(!$announcements): ?><div class="empty"><b>♢</b>暂无系统公告，点击右上角添加</div><?php else: ?>
        <table class="admin-table"><tr><th>标题</th><th>分类</th><th>发布时间</th><th>状态</th><th>置顶</th><th>操作</th></tr><?php foreach($announcements as $aa): ?><tr><td><strong><?=h($aa['title'])?></strong><div class="muted announcement-preview"><?=h(text_limit($aa['content'],80))?></div></td><td><?=h($aa['category'])?></td><td><?=h($aa['published_at'])?></td><td><span class="tag <?=((int)$aa['enabled']?'on':'off')?>"><?=((int)$aa['enabled']?'启用':'停用')?></span></td><td><?=((int)$aa['is_pinned']?'是':'否')?></td><td class="action-cell"><a class="action-link" href="/admin.php?view=announcements&edit_id=<?=(int)$aa['id']?>">编辑</a><form method="post" style="display:inline" onsubmit="return confirm('确定删除这条公告吗？')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="announcement_delete"><input type="hidden" name="aid" value="<?=(int)$aa['id']?>"><button class="action-link danger" type="submit">删除</button></form><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="announcement_toggle"><input type="hidden" name="aid" value="<?=(int)$aa['id']?>"><button class="action-link" type="submit"><?=((int)$aa['enabled']?'停用':'启用')?></button></form></td></tr><?php endforeach; ?></table>
        <?php endif; ?>
      </div>
    </div>

<?php elseif ($view === 'vip_plans'): ?>
    <?php ensure_feature_tables();$plans=vip_plans(false);$editPlan=null;if(isset($_GET['edit_id'])){foreach($plans as $p){if((int)$p['id']===(int)$_GET['edit_id']){$editPlan=$p;break;}}} ?>
    <div class="panel">
      <div class="panel-head"><h3>会员套餐</h3><a class="more" href="/admin.php?view=vip_plans&add=1">+ 添加套餐</a></div>
      <div class="panel-body">
        <?php if(isset($_GET['add'])||$editPlan): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4><?=$editPlan ? '编辑会员套餐' : '添加会员套餐'?></h4>
              <a class="admin-modal-close" href="/admin.php?view=vip_plans">✕</a>
            </div>
            <form method="post" class="prompt-form vip-form">
              <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="<?=$editPlan?'vip_plan_edit':'vip_plan_add'?>"><input type="hidden" name="pid" value="<?=(int)($editPlan['id']??0)?>">
              <div class="prompt-form-grid admin-config-grid three">
                <label>套餐名称<input name="pname" required value="<?=h($editPlan['name']??'')?>" placeholder="如：月度会员"></label>
                <label>价格（元）<input name="pprice" type="number" step="0.01" min="0" value="<?=h($editPlan['price']??'0')?>" placeholder="0.00"></label>
                <label>有效天数<input name="pdays" type="number" min="1" required value="<?=(int)($editPlan['duration_days']??30)?>" placeholder="30"></label>
                <label>赠送积分<input name="ppoints" type="number" min="0" value="<?=(int)($editPlan['points']??0)?>" placeholder="0"></label>
                <label>排序<input name="psort" type="number" value="<?=(int)($editPlan['sort_order']??0)?>" placeholder="0"></label>
                <label>颜色代码<input name="pcolor" type="text" value="<?=h($editPlan['color']??'#2f63d8')?>" placeholder="#2f63d8"></label>
                <label>图标<input name="picon" type="text" value="<?=h($editPlan['icon']??'♛')?>" placeholder="♛"></label>
                <label class="checkbox-label" style="align-self:center"><input type="checkbox" name="penabled" value="1" <?=(!$editPlan||(int)$editPlan['enabled']===1)?'checked':''?>> 启用</label>
              </div>
              <label>套餐描述<textarea name="pdesc" rows="4" placeholder="描述套餐权益，如：无限次AI生成、优先客服等"><?=h($editPlan['description']??'')?></textarea></label>
              <div class="prompt-form-foot"><div class="prompt-form-btns"><button class="btn-primary" type="submit">保存套餐</button><a class="btn-cancel" href="/admin.php?view=vip_plans">取消</a></div></div>
            </form>
          </div>
        </div>
        <?php endif; ?>
        <?php if(!$plans): ?><div class="empty"><b>♛</b>暂无会员套餐，点击右上角添加</div><?php else: ?>
        <table class="admin-table"><tr><th>图标</th><th>名称</th><th>价格</th><th>天数</th><th>积分</th><th>排序</th><th>状态</th><th>操作</th></tr><?php foreach($plans as $p): ?><tr><td style="font-size:20px;color:<?=h($p['color']??'#2f63d8')?>"><?=h($p['icon']??'♛')?></td><td><strong><?=h($p['name'])?></strong><?php if(!empty($p['description'])):?><div class="muted announcement-preview"><?=h(text_limit($p['description'],60))?></div><?php endif;?></td><td>¥<?=number_format((float)$p['price'],2)?></td><td><?=(int)$p['duration_days']?>天</td><td><?=(int)$p['points']?></td><td><?=(int)$p['sort_order']?></td><td><span class="tag <?=((int)$p['enabled']?'on':'off')?>"><?=((int)$p['enabled']?'启用':'停用')?></span></td><td class="action-cell"><a class="action-link" href="/admin.php?view=vip_plans&edit_id=<?=(int)$p['id']?>">编辑</a><form method="post" style="display:inline" onsubmit="return confirm('确定删除这个套餐吗？')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="vip_plan_delete"><input type="hidden" name="pid" value="<?=(int)$p['id']?>"><button class="action-link danger" type="submit">删除</button></form><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="vip_plan_toggle"><input type="hidden" name="pid" value="<?=(int)$p['id']?>"><button class="action-link" type="submit"><?=((int)$p['enabled']?'停用':'启用')?></button></form></td></tr><?php endforeach; ?></table>
        <?php endif; ?>
      </div>
    </div>

<?php elseif ($view === 'points_packages'): ?>
    <?php
    $pkgs = [];
    try {
        $pkgs = $pdo->query('SELECT * FROM points_packages ORDER BY sort_order, id')->fetchAll();
    } catch(Exception $e){}
    $editPkg = null;
    if (isset($_GET['edit_id'])) {
        foreach ($pkgs as $p) {
            if ((int)$p['id'] === (int)$_GET['edit_id']) {
                $editPkg = $p;
                break;
            }
        }
    }
    ?>
    <div class="panel">
      <div class="panel-head"><h3>积分充值加油包管理（<?=count($pkgs)?>）</h3><a class="more" href="/admin.php?view=points_packages&add=1">+ 添加加油包</a></div>
      <div class="panel-body">
        <?php if(isset($_GET['add']) || $editPkg): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4><?=$editPkg ? '编辑积分加油包' : '添加积分加油包'?></h4>
              <a class="admin-modal-close" href="/admin.php?view=points_packages">✕</a>
            </div>
            <form method="post" class="prompt-form">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="prompt_action" value="<?=$editPkg ? 'points_pkg_edit' : 'points_pkg_add'?>">
              <input type="hidden" name="pkg_id" value="<?=(int)($editPkg['id'] ?? 0)?>">
              <div class="prompt-form-grid admin-config-grid three">
                <label>套餐名称 *<input name="pkg_name" required value="<?=h($editPkg['name'] ?? '')?>" placeholder="如：小试牛刀包 / 200 积分"></label>
                <label>到账积分 *<input name="pkg_points" type="number" min="1" required value="<?=(int)($editPkg['points'] ?? 200)?>" placeholder="200"></label>
                <label>售价（元）*<input name="pkg_price" type="number" step="0.01" min="0" required value="<?=h($editPkg['price'] ?? '9.90')?>" placeholder="9.90"></label>
                <label>角标文案<input name="pkg_badge" value="<?=h($editPkg['badge'] ?? '')?>" placeholder="如：尝鲜、热门、特惠、超值"></label>
                <label>排序<input name="pkg_sort" type="number" value="<?=(int)($editPkg['sort_order'] ?? 0)?>" placeholder="0"></label>
                <label class="checkbox-label" style="align-self:center"><input type="checkbox" name="pkg_enabled" value="1" <?=(!$editPkg || (int)$editPkg['enabled'] === 1) ? 'checked' : ''?>> 启用并展示在前台</label>
              </div>
              <div class="prompt-form-foot">
                <div class="prompt-form-btns">
                  <button class="btn-primary" type="submit">保存加油包</button>
                  <a class="btn-cancel" href="/admin.php?view=points_packages">取消</a>
                </div>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
        <?php if(!$pkgs): ?>
          <div class="empty"><b>💎</b>暂无积分加油包套餐，点击右上角添加</div>
        <?php else: ?>
        <table class="admin-table">
          <tr>
            <th>ID</th>
            <th>套餐名称</th>
            <th>到账积分</th>
            <th>售价</th>
            <th>角标</th>
            <th>排序</th>
            <th>状态</th>
            <th>操作</th>
          </tr>
          <?php foreach($pkgs as $p): ?>
          <tr>
            <td><?=(int)$p['id']?></td>
            <td><strong><?=h($p['name'])?></strong></td>
            <td><b style="color:#0284c7"><?=(int)$p['points']?></b> pts</td>
            <td>¥<?=number_format((float)$p['price'], 2)?></td>
            <td><?php if(!empty($p['badge'])): ?><span class="tag orange"><?=h($p['badge'])?></span><?php else: ?>—<?php endif; ?></td>
            <td><?=(int)$p['sort_order']?></td>
            <td><span class="tag <?=((int)$p['enabled'] ? 'on' : 'off')?>"><?=((int)$p['enabled'] ? '启用' : '停用')?></span></td>
            <td class="action-cell">
              <a class="action-link" href="/admin.php?view=points_packages&edit_id=<?=(int)$p['id']?>">编辑</a>
              <form method="post" style="display:inline" onsubmit="return confirm('确定删除这个积分加油包吗？')">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="points_pkg_delete">
                <input type="hidden" name="pkg_id" value="<?=(int)$p['id']?>">
                <button class="action-link danger" type="submit">删除</button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="prompt_action" value="points_pkg_toggle">
                <input type="hidden" name="pkg_id" value="<?=(int)$p['id']?>">
                <button class="action-link" type="submit"><?=((int)$p['enabled'] ? '停用' : '启用')?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </div>
    </div>

<?php elseif ($view === 'agents'): ?>
    <?php ensure_feature_tables();$agents=agent_configs(false);$editAgent=null;if(isset($_GET['edit_id'])){foreach($agents as $ag){if((int)$ag['id']===(int)$_GET['edit_id']){$editAgent=$ag;break;}}} ?>
    <div class="panel">
      <div class="panel-head"><h3>智能体配置（<?=count($agents)?>）</h3><a class="more" href="/admin.php?view=agents&add=1">+ 添加智能体</a></div>
      <div class="panel-body">
        <?php if(isset($_GET['add'])||$editAgent): ?>
        <div class="admin-modal-overlay">
          <div class="admin-modal-dialog">
            <div class="admin-modal-header">
              <h4><?=$editAgent ? '编辑智能体' : '添加智能体'?></h4>
              <a class="admin-modal-close" href="/admin.php?view=agents">✕</a>
            </div>
            <form method="post" class="prompt-form agent-form">
              <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="agent_save"><input type="hidden" name="agent_id" value="<?=(int)($editAgent['id']??0)?>">
              <div class="prompt-form-grid admin-config-grid three">
                <label>唯一标识 *<input name="agent_key" required pattern="[A-Za-z0-9_-]+" value="<?=h($editAgent['agent_key']??'')?>" placeholder="如：app-builder"></label>
                <label>智能体名称 *<input name="agent_name" required value="<?=h($editAgent['name']??'')?>" placeholder="一键生成APP智能体"></label>
                <label>副标题<input name="agent_subtitle" value="<?=h($editAgent['subtitle']??'')?>" placeholder="从想法到应用方案"></label>
                <label>图标<input name="agent_icon" value="<?=h($editAgent['icon']??'✦')?>" placeholder="▦"></label>
                <label>调用模型<input name="agent_model" value="<?=h($editAgent['model']??'')?>" placeholder="留空使用默认模型"></label>
                <label>消耗积分<input name="agent_points" type="number" min="0" value="<?=(int)($editAgent['points']??5)?>"></label>
                <label>排序<input name="agent_sort" type="number" value="<?=(int)($editAgent['sort_order']??0)?>"></label>
                <label class="checkbox-label" style="align-self:center"><input type="checkbox" name="agent_enabled" value="1" <?=(!$editAgent||(int)$editAgent['enabled']===1)?'checked':''?>> 启用并展示</label>
              </div>
              <label>卡片说明<textarea name="agent_description" rows="3" placeholder="前台卡片展示说明"><?=h($editAgent['description']??'')?></textarea></label>
              <label>系统提示词 *<textarea name="agent_system_prompt" rows="8" required placeholder="定义智能体的角色、输出结构和约束..."><?=h($editAgent['system_prompt']??'')?></textarea></label>
              <div class="prompt-form-foot"><div class="prompt-form-btns"><button class="btn-primary" type="submit">保存配置</button><a class="btn-cancel" href="/admin.php?view=agents">取消</a></div></div>
            </form>
          </div>
        </div>
        <?php endif; ?>
        <?php if(!$agents): ?><div class="empty"><b>▦</b>暂无智能体配置</div><?php else: ?>
        <table class="admin-table"><tr><th>图标</th><th>名称</th><th>标识</th><th>说明</th><th>模型</th><th>积分</th><th>状态</th><th>操作</th></tr><?php foreach($agents as $ag): ?><tr><td style="font-size:20px"><?=h($ag['icon'])?></td><td><strong><?=h($ag['name'])?></strong><div class="muted"><?=h($ag['subtitle'])?></div></td><td><code><?=h($ag['agent_key'])?></code></td><td class="prompt-cell"><?=h($ag['description'])?></td><td><?=h($ag['model']?:'默认模型')?></td><td><?=(int)$ag['points']?></td><td><span class="tag <?=((int)$ag['enabled']?'on':'off')?>"><?=((int)$ag['enabled']?'启用':'停用')?></span></td><td class="action-cell"><a class="action-link" href="/admin.php?view=agents&edit_id=<?=(int)$ag['id']?>">编辑</a><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="agent_toggle"><input type="hidden" name="agent_id" value="<?=(int)$ag['id']?>"><button class="action-link" type="submit"><?=((int)$ag['enabled']?'停用':'启用')?></button></form><form method="post" style="display:inline" onsubmit="return confirm('确定删除这个智能体吗？')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="prompt_action" value="agent_delete"><input type="hidden" name="agent_id" value="<?=(int)$ag['id']?>"><button class="action-link danger" type="submit">删除</button></form></td></tr><?php endforeach; ?></table>
        <?php endif; ?>
      </div>
    </div>

<?php elseif ($view === 'cards'): ?>
    <?php
    $cards = [];
    $totalCards = 0;
    $unusedCards = 0;
    try {
        $totalCards = (int)$pdo->query('SELECT COUNT(*) FROM recharge_cards')->fetchColumn();
        $unusedCards = (int)$pdo->query('SELECT COUNT(*) FROM recharge_cards WHERE status=0')->fetchColumn();
        $st = $pdo->query('SELECT c.*, u.username as used_username FROM recharge_cards c LEFT JOIN users u ON u.id=c.used_by ORDER BY c.id DESC LIMIT 100');
        $cards = $st->fetchAll();
    } catch(Exception $e){}
    ?>
    <div class="stat-grid" style="margin-bottom:20px;">
      <div class="stat-card"><div class="stat-icon blue">🎟️</div><div><b><?=$totalCards?></b><span>总卡密数</span></div></div>
      <div class="stat-card"><div class="stat-icon green">✦</div><div><b><?=$unusedCards?></b><span>未兑换卡密</span></div></div>
      <div class="stat-card"><div class="stat-icon purple">✓</div><div><b><?=$totalCards - $unusedCards?></b><span>已核销兑换</span></div></div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h3>批量生成卡密</h3>
      </div>
      <div class="panel-body">
        <form method="post" class="prompt-form">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="prompt_action" value="card_batch_generate">
          <div class="prompt-form-grid admin-config-grid three">
            <label>卡密类型
              <select name="card_type">
                <option value="points">积分卡（增加创作积分）</option>
                <option value="vip">VIP 会员卡（赠送天数）</option>
              </select>
            </label>
            <label>包含积分点数
              <input type="number" name="points" value="100" min="0" step="10">
            </label>
            <label>包含 VIP 天数
              <input type="number" name="vip_days" value="0" min="0">
            </label>
            <label>生成数量（张）
              <input type="number" name="count" value="10" min="1" max="500">
            </label>
            <label style="grid-column: span 2;">批次备注说明
              <input type="text" name="remark" placeholder="如：淘宝店专享 / 社群活动发卡">
            </label>
          </div>
          <div class="prompt-form-foot">
            <button class="btn-primary" type="submit">立即批量生成</button>
          </div>
        </form>
      </div>
    </div>

    <div class="panel" style="margin-top:20px;">
      <div class="panel-head">
        <h3>卡密列表（最新 100 条）</h3>
      </div>
      <div class="panel-body">
        <?php if(!$cards): ?>
          <div class="empty"><b>🎟️</b>暂无卡密记录，请先在上方批量生成</div>
        <?php else: ?>
          <table class="admin-table">
            <tr>
              <th>ID</th>
              <th>卡密字符串</th>
              <th>类型</th>
              <th>面额权益</th>
              <th>状态</th>
              <th>使用者</th>
              <th>使用时间</th>
              <th>备注</th>
              <th>操作</th>
            </tr>
            <?php foreach($cards as $c): ?>
            <tr>
              <td><?=(int)$c['id']?></td>
              <td><code style="user-select:all;font-weight:bold;color:#4f46e5;"><?=h($c['card_code'])?></code></td>
              <td><?=$c['card_type']==='vip'?'VIP卡':'积分卡'?></td>
              <td>
                <?php if($c['points']>0): ?><strong>+<?=$c['points']?></strong> 积分<?php endif; ?>
                <?php if($c['vip_days']>0): ?><strong>+<?=$c['vip_days']?></strong> 天VIP<?php endif; ?>
              </td>
              <td>
                <span class="tag <?=((int)$c['status']===0?'on':'off')?>"><?=((int)$c['status']===0?'未兑换':'已使用')?></span>
              </td>
              <td><?=h($c['used_username'] ?: '—')?></td>
              <td><?=h($c['used_at'] ?: '—')?></td>
              <td><small class="muted"><?=h($c['remark'] ?: '—')?></small></td>
              <td class="action-cell">
                <form method="post" style="display:inline" onsubmit="return confirm('确定删除这张卡密吗？')">
                  <input type="hidden" name="csrf" value="<?=csrf()?>">
                  <input type="hidden" name="prompt_action" value="card_delete">
                  <input type="hidden" name="cid" value="<?=(int)$c['id']?>">
                  <button class="action-link danger" type="submit">删除</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>
    </div>

<?php elseif ($view === 'settings'): ?>
    <?php
    $settings = site_settings();
    $tab = $_GET['tab'] ?? 'basic';
    $validTabs = ['basic','footer','model_api','payment','sms','mail','seo','user'];
    if (!in_array($tab, $validTabs, true)) $tab = 'basic';
    $navItems = [
        'basic'    => ['⚙', '基础设置'],
        'footer'   => ['⊥', '底部设置'],
        'model_api'=> ['⇆', '模型API'],
        'payment'  => ['₿', '支付设置'],
        'sms'      => ['✉', '短信接口'],
        'mail'     => ['@', '邮件接口'],
        'seo'      => ['🔍', 'SEO与收录'],
        'user'     => ['◉', '用户设置'],
    ];
    ?>
    <div class="settings-wrap">
      <div class="settings-nav">
        <?php foreach ($navItems as $k => $v): ?>
        <a href="?view=settings&tab=<?=$k?>" class="<?=$tab===$k?'active':''?>">
          <span class="nav-icon"><?=$v[0]?></span>
          <span class="nav-label"><?=$v[1]?></span>
        </a>
        <?php endforeach; ?>
        <div class="settings-nav-divider"></div>
      </div>

      <div class="settings-content">
        <?php if ($tab === 'basic'): ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">⚙</span>
            <h3>基础设置</h3>
            <span class="card-sub">品牌、侧栏与公告</span>
          </div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="site_settings_save">
            <input type="hidden" name="site_logo" value="<?=h($settings['site_logo']??'')?>">
            <input type="hidden" name="service_qrcode" value="<?=h($settings['service_qrcode']??'')?>">
            <div class="settings-card-body">
              <div class="form-grid">
                <div class="settings-field">
                  <span class="field-label">网站名称 <span class="field-required">*</span></span>
                  <input name="site_name" required value="<?=h($settings['site_name'])?>" placeholder="灵创AI">
                </div>
                <div class="settings-field">
                  <span class="field-label">网站副标题</span>
                  <input name="site_subtitle" value="<?=h($settings['site_subtitle'])?>" placeholder="AI爆款工坊">
                </div>
                <div class="settings-field">
                  <span class="field-label">Logo 文字</span>
                  <input name="logo_text" maxlength="6" value="<?=h($settings['logo_text'])?>" placeholder="LC">
                </div>
                <div class="settings-field">
                  <span class="field-label">客服标题</span>
                  <input name="service_title" value="<?=h($settings['service_title'])?>" placeholder="联系客服">
                </div>
                <div class="settings-field">
                  <span class="field-label">客服副标题</span>
                  <input name="service_subtitle" value="<?=h($settings['service_subtitle'])?>" placeholder="随为您服务">
                </div>
                <div class="settings-field">
                  <span class="field-label">客服按钮文案</span>
                  <input name="service_button" value="<?=h($settings['service_button'])?>" placeholder="立即咨询">
                </div>
                <div class="settings-field">
                  <span class="field-label">客服跳转链接</span>
                  <input name="service_url" value="<?=h($settings['service_url'])?>" placeholder="/?page=service">
                </div>
                <div class="settings-field">
                  <span class="field-label">侧栏积分显示</span>
                  <input name="points_label" value="<?=h($settings['points_label'])?>" placeholder="-- 积分">
                </div>
              </div>

              <hr class="settings-divider">

              <div class="form-grid">
                <div class="settings-field field-full">
                  <span class="field-label">网站图标 (Logo)</span>
                  <input type="file" name="site_logo_file" accept="image/*,.svg,.ico">
                  <?php if(!empty($settings['site_logo'])): ?>
                  <div style="display:flex;align-items:center;gap:12px;margin-top:8px;padding:10px 14px;background:#fafbfd;border-radius:8px;border:1px solid #eef1f6">
                    <img src="<?=h($settings['site_logo'])?>" alt="网站图标" style="width:40px;height:40px;object-fit:contain;border-radius:8px;border:1px solid #dbe2ee;background:#1a1a24;padding:4px">
                    <span style="flex:1;font-size:12px;color:#6e7f99;word-break:break-all"><?=h($settings['site_logo'])?></span>
                    <label class="checkbox-wrap" style="flex-shrink:0;padding:0"><input type="checkbox" name="clear_site_logo" value="1"> 删除</label>
                  </div>
                  <?php else: ?>
                  <span style="font-size:12px;color:#8a96ab;margin-top:4px;display:block">暂未上传图标，将使用默认「Logo 文字」徽标</span>
                  <?php endif; ?>
                </div>
              </div>

              <hr class="settings-divider">

              <div class="form-grid">
                <div class="settings-field field-full">
                  <span class="field-label">客服微信二维码</span>
                  <input type="file" name="service_qrcode_file" accept="image/*">
                  <?php if(!empty($settings['service_qrcode'])): ?>
                  <div style="display:flex;align-items:center;gap:12px;margin-top:8px;padding:10px 14px;background:#fafbfd;border-radius:8px;border:1px solid #eef1f6">
                    <img src="<?=h($settings['service_qrcode'])?>" alt="客服二维码" style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #dbe2ee;background:#fff">
                    <span style="flex:1;font-size:12px;color:#6e7f99;word-break:break-all"><?=h($settings['service_qrcode'])?></span>
                    <label class="checkbox-wrap" style="flex-shrink:0;padding:0"><input type="checkbox" name="clear_service_qrcode" value="1"> 删除</label>
                  </div>
                  <?php else: ?>
                  <span style="font-size:12px;color:#8a96ab;margin-top:4px;display:block">暂未上传客服二维码</span>
                  <?php endif; ?>
                </div>
              </div>

              <hr class="settings-divider">

              <div class="form-grid">
                <div class="settings-field field-full">
                  <span class="field-label">前台公告</span>
                  <textarea name="site_notice" rows="3" placeholder="留空则不在前台显示公告"><?=h($settings['site_notice'])?></textarea>
                </div>
              </div>
            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">🔒 基础配置影响前台整体展示</span>
              <button class="btn-primary" type="submit">保存基础设置</button>
            </div>
          </form>
        </div>

        <?php elseif ($tab === 'footer'): ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">⊥</span>
            <h3>底部设置</h3>
            <span class="card-sub">前台页面底部信息</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="footer_settings_save">
            <div class="settings-card-body">
              <div class="form-grid">
                <div class="settings-field">
                  <span class="field-label">版权信息</span>
                  <input name="footer_copyright" value="<?=h($settings['footer_copyright'])?>" placeholder="© 2025 灵创AI 版权所有">
                </div>
                <div class="settings-field">
                  <span class="field-label">ICP备案号</span>
                  <input name="footer_icp" value="<?=h($settings['footer_icp']??'')?>" placeholder="京ICP备XXXXXXXX号">
                </div>
                <div class="settings-field">
                  <span class="field-label">ICP备案链接</span>
                  <input name="footer_icp_url" value="<?=h($settings['footer_icp_url']??'')?>" placeholder="https://beian.miit.gov.cn/">
                </div>
                <div class="settings-field">
                  <span class="field-label">公安联网备案号</span>
                  <input name="footer_police_beian" value="<?=h($settings['footer_police_beian']??'')?>" placeholder="京公网安备 11010802020000号">
                </div>
                <div class="settings-field">
                  <span class="field-label">公安备案查询链接</span>
                  <input name="footer_police_url" value="<?=h($settings['footer_police_url']??'')?>" placeholder="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=...">
                </div>
                <div class="settings-field">
                  <span class="field-label">联系邮箱</span>
                  <input name="footer_contact_email" type="email" value="<?=h($settings['footer_contact_email']??'')?>" placeholder="contact@example.com">
                </div>
                <div class="settings-field">
                  <span class="field-label">联系电话</span>
                  <input name="footer_contact_phone" value="<?=h($settings['footer_contact_phone']??'')?>" placeholder="400-000-0000">
                </div>
                <div class="settings-field">
                  <span class="field-label">关于我们链接</span>
                  <input name="footer_about_url" value="<?=h($settings['footer_about_url']??'')?>" placeholder="/?page=about">
                </div>
                <div class="settings-field">
                  <span class="field-label">隐私政策链接</span>
                  <input name="footer_privacy_url" value="<?=h($settings['footer_privacy_url']??'')?>" placeholder="/?page=privacy">
                </div>
                <div class="settings-field">
                  <span class="field-label">服务条款链接</span>
                  <input name="footer_terms_url" value="<?=h($settings['footer_terms_url']??'')?>" placeholder="/?page=terms">
                </div>
                <div class="settings-field field-full">
                  <span class="field-label">友情链接 (每行一条：名称 | 链接 或 名称 链接)</span>
                  <textarea name="footer_friend_links" rows="4" placeholder="例如：
百度 | https://www.baidu.com
灵境AI | https://www.lk888.ai"><?=h($settings['footer_friend_links']??'')?></textarea>
                </div>
              </div>
            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">ⓘ 留空则不显示对应项</span>
              <button class="btn-primary" type="submit">保存底部设置</button>
            </div>
          </form>
        </div>

        <?php elseif ($tab === 'model_api'): ?>
        <?php
        $maskKey = function($file) {
            $p = __DIR__ . '/runtime/' . $file;
            if (is_readable($p) && @filesize($p) > 0) {
                $k = trim(file_get_contents($p));
                if (strlen($k) >= 12) return substr($k, 0, 4) . '••••••••' . substr($k, -4);
                return '••••••••';
            }
            return '';
        };
        $curProvider = $settings['model_provider_default'] ?? 'lingjing';
        ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">⇆</span>
            <h3>模型API设置</h3>
            <span class="card-sub">灵境 AI（默认）及主流第三方模型接口扩展</span>
          </div>
          <form method="post" onsubmit="return confirm('修改模型 API 配置可能影响对应模型的调用，确认保存？')">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="model_api_settings_save">
            <div class="settings-card-body pay-settings-body">

              <!-- 默认服务商通道选择 -->
              <div class="pay-channel-block" style="border-left:4px solid #2563eb">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>默认主通道设置</h4>
                  </div>
                  <span class="pay-header-desc">设置系统全局及未指定时的默认 AI 接口提供商（优先灵境 AI）</span>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">主通道路由</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">全局默认通道</span>
                      <div class="pay-row-content">
                        <select name="model_provider_default" style="max-width:320px;height:36px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px;font-size:13px;background:#fff">
                          <option value="lingjing" <?=$curProvider==='lingjing'?'selected':''?>>✦ 灵境 AI (默认推荐，支持图文视音频全矩阵)</option>
                          <option value="openai_custom" <?=$curProvider==='openai_custom'?'selected':''?>>OpenAI / 兼容自定义通道</option>
                          <option value="deepseek" <?=$curProvider==='deepseek'?'selected':''?>>DeepSeek (深度求索)</option>
                          <option value="claude" <?=$curProvider==='claude'?'selected':''?>>Anthropic (Claude)</option>
                          <option value="gemini" <?=$curProvider==='gemini'?'selected':''?>>Google Gemini</option>
                          <option value="qwen" <?=$curProvider==='qwen'?'selected':''?>>通义千问 (DashScope)</option>
                        </select>
                        <div class="pay-row-desc">默认通道将作为系统底座。灵境 AI 支持图像生图、生视频与全部专家智能体。</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 1. 灵境 AI 旗舰通道 (默认) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>灵境 AI (官方旗舰，默认通道)</h4>
                  </div>
                  <span class="pay-header-desc">支持文生图、文生视频、图生视频、音频与文本多模态全功能</span>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">接口参数</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">API 地址</span>
                      <div class="pay-row-content">
                        <input name="lingjing_base_url" value="<?=h($settings['lingjing_base_url'])?>" placeholder="https://api.lk888.ai/api">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">默认文本模型</span>
                      <div class="pay-row-content">
                        <input name="lingjing_default_model" value="<?=h($settings['lingjing_default_model'])?>" placeholder="如：tt-5.6-luna（留空使用服务端默认）">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API Key</span>
                      <div class="pay-row-content">
                        <input name="lingjing_api_key" type="password" placeholder="<?=$maskKey('lingjing_api_key')?:'留空保持当前，输入覆盖'?>" autocomplete="off">
                        <div class="pay-row-desc">
                          <?php if ($maskKey('lingjing_api_key')): ?>
                            <span style="color:#10b981">● 已配置秘钥 (<?=$maskKey('lingjing_api_key')?>)</span>
                          <?php else: ?>
                            <span style="color:#ef4444">○ 未配置秘钥</span>，<a href="https://lingjingx.com/" target="_blank" rel="noopener">前往灵境注册获取</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 2. OpenAI / 自定义中转通道 -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>OpenAI / 自定义兼容接口 (OneAPI / NewAPI)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="openai_custom_enabled" value="1" <?=((string)($settings['openai_custom_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">支持标准 OpenAI v1 格式的中转网关与自建 API</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置参数</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">API Base URL</span>
                      <div class="pay-row-content">
                        <input name="openai_custom_base_url" value="<?=h($settings['openai_custom_base_url']??'https://api.openai.com/v1')?>" placeholder="https://api.openai.com/v1">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">默认调用模型</span>
                      <div class="pay-row-content">
                        <input name="openai_custom_model" value="<?=h($settings['openai_custom_model']??'gpt-4o-mini')?>" placeholder="如：gpt-4o, gpt-4o-mini, o1-mini">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API Key</span>
                      <div class="pay-row-content">
                        <input name="openai_custom_api_key" type="password" placeholder="<?=$maskKey('openai_custom_api_key')?:'输入 API Key（sk-...）'?>" autocomplete="off">
                        <div class="pay-row-desc">
                          <?php if ($maskKey('openai_custom_api_key')): ?>
                            <span style="color:#10b981">● 已配置秘钥 (<?=$maskKey('openai_custom_api_key')?>)</span>
                          <?php else: ?>
                            <span style="color:#ef4444">○ 未配置秘钥</span>，<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">前往 OpenAI 注册获取 Key</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 3. DeepSeek (深度求索) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>DeepSeek (深度求索官方通道)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="deepseek_enabled" value="1" <?=((string)($settings['deepseek_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">直连 DeepSeek 官方 API，高性价比推理能力</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置参数</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">API 地址</span>
                      <div class="pay-row-content">
                        <input name="deepseek_base_url" value="<?=h($settings['deepseek_base_url']??'https://api.deepseek.com/v1')?>" placeholder="https://api.deepseek.com/v1">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">默认模型</span>
                      <div class="pay-row-content">
                        <input name="deepseek_model" value="<?=h($settings['deepseek_model']??'deepseek-chat')?>" placeholder="deepseek-chat 或 deepseek-reasoner">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API Key</span>
                      <div class="pay-row-content">
                        <input name="deepseek_api_key" type="password" placeholder="<?=$maskKey('deepseek_api_key')?:'输入 DeepSeek API Key'?>" autocomplete="off">
                        <div class="pay-row-desc">
                          <?php if ($maskKey('deepseek_api_key')): ?>
                            <span style="color:#10b981">● 已配置秘钥 (<?=$maskKey('deepseek_api_key')?>)</span>
                          <?php else: ?>
                            <span style="color:#ef4444">○ 未配置秘钥</span>，<a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener">前往 DeepSeek 开放平台注册获取 Key</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 4. Anthropic Claude -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>Anthropic (Claude 3 / 3.5)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="claude_enabled" value="1" <?=((string)($settings['claude_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">Anthropic 官方或第三方兼容 Messages 接口</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置参数</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">API 地址</span>
                      <div class="pay-row-content">
                        <input name="claude_base_url" value="<?=h($settings['claude_base_url']??'https://api.anthropic.com')?>" placeholder="https://api.anthropic.com">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">默认模型</span>
                      <div class="pay-row-content">
                        <input name="claude_model" value="<?=h($settings['claude_model']??'claude-3-5-sonnet-20241022')?>" placeholder="如：claude-3-5-sonnet-20241022">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API Key</span>
                      <div class="pay-row-content">
                        <input name="claude_api_key" type="password" placeholder="<?=$maskKey('claude_api_key')?:'输入 Claude API Key'?>" autocomplete="off">
                        <div class="pay-row-desc">
                          <?php if ($maskKey('claude_api_key')): ?>
                            <span style="color:#10b981">● 已配置秘钥 (<?=$maskKey('claude_api_key')?>)</span>
                          <?php else: ?>
                            <span style="color:#ef4444">○ 未配置秘钥</span>，<a href="https://console.anthropic.com/" target="_blank" rel="noopener">前往 Anthropic 控制台注册获取 Key</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 5. Google Gemini -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>Google Gemini</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="gemini_enabled" value="1" <?=((string)($settings['gemini_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">谷歌 Gemini 1.5 系列原生或兼容接口</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置参数</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">API 地址</span>
                      <div class="pay-row-content">
                        <input name="gemini_base_url" value="<?=h($settings['gemini_base_url']??'https://generativelanguage.googleapis.com')?>" placeholder="https://generativelanguage.googleapis.com">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">默认模型</span>
                      <div class="pay-row-content">
                        <input name="gemini_model" value="<?=h($settings['gemini_model']??'gemini-1.5-flash')?>" placeholder="gemini-1.5-flash 或 gemini-1.5-pro">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API Key</span>
                      <div class="pay-row-content">
                        <input name="gemini_api_key" type="password" placeholder="<?=$maskKey('gemini_api_key')?:'输入 Google Gemini API Key'?>" autocomplete="off">
                        <div class="pay-row-desc">
                          <?php if ($maskKey('gemini_api_key')): ?>
                            <span style="color:#10b981">● 已配置秘钥 (<?=$maskKey('gemini_api_key')?>)</span>
                          <?php else: ?>
                            <span style="color:#ef4444">○ 未配置秘钥</span>，<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">前往 Google AI Studio 免费获取 Key</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 6. 阿里云通义千问 (DashScope) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>通义千问 (阿里云百炼 / DashScope)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="qwen_enabled" value="1" <?=((string)($settings['qwen_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">使用阿里云 DashScope OpenAI 兼容模式</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置参数</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">兼容 Base URL</span>
                      <div class="pay-row-content">
                        <input name="qwen_base_url" value="<?=h($settings['qwen_base_url']??'https://dashscope.aliyuncs.com/compatible-mode/v1')?>" placeholder="https://dashscope.aliyuncs.com/compatible-mode/v1">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">默认模型</span>
                      <div class="pay-row-content">
                        <input name="qwen_model" value="<?=h($settings['qwen_model']??'qwen-plus')?>" placeholder="qwen-plus, qwen-turbo, qwen-max">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API Key</span>
                      <div class="pay-row-content">
                        <input name="qwen_api_key" type="password" placeholder="<?=$maskKey('qwen_api_key')?:'输入 DashScope API Key（sk-...）'?>" autocomplete="off">
                        <div class="pay-row-desc">
                          <?php if ($maskKey('qwen_api_key')): ?>
                            <span style="color:#10b981">● 已配置秘钥 (<?=$maskKey('qwen_api_key')?>)</span>
                          <?php else: ?>
                            <span style="color:#ef4444">○ 未配置秘钥</span>，<a href="https://bailian.console.aliyun.com/" target="_blank" rel="noopener">前往阿里云百炼注册获取 Key</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">🔒 所有第三方 API Key 仅以加密权限存储于服务端 runtime/，不写入公开数据库</span>
              <button class="btn-primary" type="submit">保存模型API设置</button>
            </div>
          </form>
        </div>


        <?php elseif ($tab === 'payment'): ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">₿</span>
            <h3>支付设置</h3>
            <span class="card-sub">根据需求启用并配置各类支付通道</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="payment_settings_save">
            <div class="settings-card-body pay-settings-body">

              <!-- 1. 支付宝（官方企业支付-新应用模式） -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>支付宝（官方企业支付-新应用模式）</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_alipay_enabled" value="1" <?=((string)($settings['payment_alipay_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">支付宝商户后台推荐签约电脑网站支付，当面付，手机网站支付，<a href="https://www.kancloud.cn/rizhuti/ritheme/1961638" target="_blank" rel="noopener noreferrer">配置教程</a></span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">开放平台-应用appid</span>
                      <div class="pay-row-content">
                        <input name="payment_alipay_app_id" value="<?=h($settings['payment_alipay_app_id']??'')?>" placeholder="">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">开放平台-应用私钥</span>
                      <div class="pay-row-content">
                        <textarea name="payment_alipay_private_key" rows="4" placeholder=""><?=h($settings['payment_alipay_private_key']??'')?></textarea>
                        <div class="pay-row-desc">请注意这里是应用私钥，就是你用工具生成的应用私钥</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">开放平台-支付宝公钥</span>
                      <div class="pay-row-content">
                        <textarea name="payment_alipay_public_key" rows="4" placeholder=""><?=h($settings['payment_alipay_public_key']??'')?></textarea>
                        <div class="pay-row-desc">请注意这里是支付宝后台中的公钥，不是你生成的那个应用私钥，如果支付成功后，网站支付状态不刷新或者后台的订单显示未支付，请检查公钥是否支付宝公钥和https证书是否正常，一般更换https证书即可，各大支付平台对ssl证书都有一定的安全性验证，个别有时候无法通知，换一个ssl证书即可</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">应用接口模式</span>
                      <div class="pay-row-content">
                        <div class="radio-group">
                          <label><input type="radio" name="payment_alipay_mode" value="f2f" <?=($settings['payment_alipay_mode']??'')==='f2f'?'checked':''?>> 当面付(需签约当面付产品)</label>
                          <label><input type="radio" name="payment_alipay_mode" value="web" <?=($settings['payment_alipay_mode']??'web')==='web'?'checked':''?>> 电脑网站支付(需签约电脑网站支付产品)</label>
                        </div>
                        <div class="pay-row-desc">自2021年初开始，支付宝官方风控系统对异地跨地区进行当面付扫码支付或者信用卡以及分期付款的异常用户，容易被风控商户，建议非必要情况下不要使用当面付模式，关闭此项，如果是个人的商户，没有电脑网站支付产品，只能硬刚当面付，没有其他办法。</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">手机端自动跳转H5支付</span>
                      <div class="pay-row-content pay-switch-row">
                        <label class="switch-btn red">
                          <input type="checkbox" name="payment_alipay_h5" value="1" <?=((string)($settings['payment_alipay_h5']??'0')==='1'?'checked':'')?>>
                          <span class="switch-slider"></span>
                        </label>
                        <span class="pay-inline-desc">(需签约手机网站支付产品，只支持手机浏览器打开唤醒APP支付，并不能在应用内，如QQ/微信/支付宝内部浏览器无效)</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 2. 微信支付（官方企业支付） -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>微信支付（官方企业支付）</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_wxpay_enabled" value="1" <?=((string)($settings['payment_wxpay_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">微信官方商户后台推荐签约native产品，JSAPI产品，h5支付产品</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">微信支付商户号</span>
                      <div class="pay-row-content">
                        <input name="payment_wxpay_mch_id" value="<?=h($settings['payment_wxpay_mch_id']??'')?>" placeholder="">
                        <div class="pay-row-desc">微信支付商户号 PartnerID 通过微信支付商户资料审核后邮件发送</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">公众号或小程序APPID</span>
                      <div class="pay-row-content">
                        <input name="payment_wxpay_app_id" value="<?=h($settings['payment_wxpay_app_id']??'')?>" placeholder="">
                        <div class="pay-row-desc">公众号APPID 通过微信支付商户资料审核后邮件发送,开通jsapi支付和配置公众号手机内直接登录的用户注意,如果是小程序的appid,请到支付商户绑定公众号appid授权,这里填写为公众号即可</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">微信支付API密钥</span>
                      <div class="pay-row-content">
                        <input name="payment_wxpay_key" type="password" value="<?=h($settings['payment_wxpay_key']??'')?>" placeholder="" autocomplete="off">
                        <div class="pay-row-desc">帐户设置-安全设置-API安全-API密钥-设置API密钥</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">手机跳转H5支付</span>
                      <div class="pay-row-content pay-switch-row">
                        <label class="switch-btn red">
                          <input type="checkbox" name="payment_wxpay_h5" value="1" <?=((string)($settings['payment_wxpay_h5']??'0')==='1'?'checked':'')?>>
                          <span class="switch-slider"></span>
                        </label>
                        <span class="pay-inline-desc">移动端自动切换为跳转支付（需开通H5支付，只支持手机浏览器打开唤醒APP支付，并不能在应用内，如QQ/微信/支付宝内部浏览器无效）</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 3. 虎皮椒(微信) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>虎皮椒(微信)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_hupiv3_wx_enabled" value="1" <?=((string)($settings['payment_hupiv3_wx_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">无需企业资质，个人用户推荐，微信完美收款，无资质可以用此方法完美替代*_*</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-note-row">
                      虎皮椒V3 <a href="https://admin.xunhupay.com/" target="_blank" rel="noopener noreferrer">注册地址</a>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">APPID</span>
                      <div class="pay-row-content">
                        <input name="payment_hupiv3_wx_appid" value="<?=h($settings['payment_hupiv3_wx_appid']??'')?>" placeholder="">
                        <div class="pay-row-desc">APPID</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">APPSECRET</span>
                      <div class="pay-row-content">
                        <input name="payment_hupiv3_wx_secret" type="password" value="<?=h($settings['payment_hupiv3_wx_secret']??'')?>" placeholder="" autocomplete="off">
                        <div class="pay-row-desc">密钥</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">支付网关</span>
                      <div class="pay-row-content">
                        <input name="payment_hupiv3_wx_gateway" value="<?=h($settings['payment_hupiv3_wx_gateway']??'')?>" placeholder="">
                        <div class="pay-row-desc">必填</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 4. 虎皮椒(支付宝) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>虎皮椒(支付宝)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_hupiv3_ali_enabled" value="1" <?=((string)($settings['payment_hupiv3_ali_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">稳定第三方服务商渠道</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-note-row">
                      虎皮椒（讯虎支付）V3 <a href="https://admin.xunhupay.com/" target="_blank" rel="noopener noreferrer">注册地址</a>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">APPID</span>
                      <div class="pay-row-content">
                        <input name="payment_hupiv3_ali_appid" value="<?=h($settings['payment_hupiv3_ali_appid']??'')?>" placeholder="">
                        <div class="pay-row-desc">APPID</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">APPSECRET</span>
                      <div class="pay-row-content">
                        <input name="payment_hupiv3_ali_secret" type="password" value="<?=h($settings['payment_hupiv3_ali_secret']??'')?>" placeholder="" autocomplete="off">
                        <div class="pay-row-desc">密钥</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">支付网关</span>
                      <div class="pay-row-content">
                        <input name="payment_hupiv3_ali_gateway" value="<?=h($settings['payment_hupiv3_ali_gateway']??'')?>" placeholder="">
                        <div class="pay-row-desc">必填</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 5. 讯虎(微信H5支付) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>讯虎(微信H5支付)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_xunhu_wx_enabled" value="1" <?=((string)($settings['payment_xunhu_wx_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">支持电PC端扫码，移动端H5唤醒支付，微信内JSAPI支付，无资质可以用此方法完美替代*_*</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-note-row">
                      讯虎支付 --&gt;&gt; <a href="https://admin.xunhupay.com/" target="_blank" rel="noopener noreferrer">注册地址</a>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">MCHID</span>
                      <div class="pay-row-content">
                        <input name="payment_xunhu_wx_mchid" value="<?=h($settings['payment_xunhu_wx_mchid']??'')?>" placeholder="">
                        <div class="pay-row-desc">MCHID</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">Private Key</span>
                      <div class="pay-row-content">
                        <input name="payment_xunhu_wx_key" type="password" value="<?=h($settings['payment_xunhu_wx_key']??'')?>" placeholder="" autocomplete="off">
                        <div class="pay-row-desc">密钥</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">支付网关</span>
                      <div class="pay-row-content">
                        <input name="payment_xunhu_wx_gateway" value="<?=h($settings['payment_xunhu_wx_gateway']??'https://api.xunhupay.com/payment/do.html')?>" placeholder="https://api.xunhupay.com/payment/do.html">
                        <div class="pay-row-desc">一般不用动，如虎皮椒官方有调整手动更新即可</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 6. 讯虎(支付宝H5支付) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>讯虎(支付宝H5支付)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_xunhu_ali_enabled" value="1" <?=((string)($settings['payment_xunhu_ali_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">稳定第三方服务商渠道*_*</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-note-row">
                      讯虎支付 --&gt;&gt; <a href="https://admin.xunhupay.com/" target="_blank" rel="noopener noreferrer">注册地址</a>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">MCHID</span>
                      <div class="pay-row-content">
                        <input name="payment_xunhu_ali_mchid" value="<?=h($settings['payment_xunhu_ali_mchid']??'')?>" placeholder="">
                        <div class="pay-row-desc">MCHID</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">Private Key</span>
                      <div class="pay-row-content">
                        <input name="payment_xunhu_ali_key" type="password" value="<?=h($settings['payment_xunhu_ali_key']??'')?>" placeholder="" autocomplete="off">
                        <div class="pay-row-desc">密钥</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">支付网关</span>
                      <div class="pay-row-content">
                        <input name="payment_xunhu_ali_gateway" value="<?=h($settings['payment_xunhu_ali_gateway']??'https://api.xunhupay.com/payment/do.html')?>" placeholder="https://api.xunhupay.com/payment/do.html">
                        <div class="pay-row-desc">一般不用动，如虎皮椒官方有调整手动更新即可</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 7. 易支付(支付宝通道) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>易支付(支付宝通道)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_epay_ali_enabled" value="1" <?=((string)($settings['payment_epay_ali_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">易支付(支付宝通道)，本API接口为彩虹易支付版本SDK接口</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">商户ID</span>
                      <div class="pay-row-content">
                        <input name="payment_epay_ali_pid" value="<?=h($settings['payment_epay_ali_pid']??'')?>" placeholder="">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">商户KEY</span>
                      <div class="pay-row-content">
                        <input name="payment_epay_ali_key" type="password" value="<?=h($settings['payment_epay_ali_key']??'')?>" placeholder="" autocomplete="off">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">支付API地址</span>
                      <div class="pay-row-content">
                        <input name="payment_epay_ali_api" value="<?=h($settings['payment_epay_ali_api']??'')?>" placeholder="">
                        <div class="pay-row-desc">请填写你的易支付-接口地址,格式为:http[s]://www.xxxxx.xx/记得协议和最后的/别少</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 8. 易支付(微信通道) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>易支付(微信通道)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_epay_wx_enabled" value="1" <?=((string)($settings['payment_epay_wx_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">易支付(微信通道)，本API接口为彩虹易支付版本SDK接口</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">商户ID</span>
                      <div class="pay-row-content">
                        <input name="payment_epay_wx_pid" value="<?=h($settings['payment_epay_wx_pid']??'')?>" placeholder="">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">商户KEY</span>
                      <div class="pay-row-content">
                        <input name="payment_epay_wx_key" type="password" value="<?=h($settings['payment_epay_wx_key']??'')?>" placeholder="" autocomplete="off">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">支付API地址</span>
                      <div class="pay-row-content">
                        <input name="payment_epay_wx_api" value="<?=h($settings['payment_epay_wx_api']??'')?>" placeholder="">
                        <div class="pay-row-desc">请填写你的易支付-接口地址,格式为:http[s]://www.xxxxx.xx/记得协议和最后的/别少</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 9. PayPal (贝宝) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>PayPal (贝宝)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_paypal_enabled" value="1" <?=((string)($settings['payment_paypal_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">贝宝国际支付，需要企业版</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-note-row">
                      查看你的paypal秘钥信息：<a href="https://www.paypal.com/businessprofile/mytools/apiaccess/firstparty/signature" target="_blank" rel="noopener noreferrer">https://www.paypal.com/businessprofile/mytools/apiaccess/firstparty/signature</a>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API用户名</span>
                      <div class="pay-row-content">
                        <input name="payment_paypal_username" value="<?=h($settings['payment_paypal_username']??'')?>" placeholder="">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">API密码</span>
                      <div class="pay-row-content">
                        <input name="payment_paypal_password" type="password" value="<?=h($settings['payment_paypal_password']??'')?>" placeholder="" autocomplete="off">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">签名</span>
                      <div class="pay-row-content">
                        <input name="payment_paypal_signature" value="<?=h($settings['payment_paypal_signature']??'')?>" placeholder="">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">结算货币</span>
                      <div class="pay-row-content">
                        <input name="payment_paypal_currency" value="<?=h($settings['payment_paypal_currency']??'USD')?>" placeholder="USD">
                        <div class="pay-row-desc">列如(USD：美元、EUR：欧元、GBP：英镑、JPY：日元、CAD：加拿大元、AUD：澳大利亚元、CHF：瑞士法郎、CNY：人民币、SEK：瑞典克朗、NZD：新西兰元)</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">货币汇率</span>
                      <div class="pay-row-content">
                        <input name="payment_paypal_rate" value="<?=h($settings['payment_paypal_rate']??'0.14')?>" placeholder="0.14">
                        <div class="pay-row-desc">1元等于多少结算货币,例如你设置结算货币为USD，则1元=0.7美元</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">沙盒调试模式</span>
                      <div class="pay-row-content pay-switch-row">
                        <label class="switch-btn red">
                          <input type="checkbox" name="payment_paypal_sandbox" value="1" <?=((string)($settings['payment_paypal_sandbox']??'0')==='1'?'checked':'')?>>
                          <span class="switch-slider"></span>
                        </label>
                        <span class="pay-inline-desc">不是测试账户调试时切勿开启！</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 10. USDT支付 (trc20) -->
              <div class="pay-channel-block">
                <div class="pay-channel-header">
                  <div class="pay-channel-title">
                    <h4>USDT支付 (trc20)</h4>
                  </div>
                  <div class="pay-channel-switch-wrap">
                    <label class="switch-btn">
                      <input type="checkbox" name="payment_usdt_enabled" value="1" <?=((string)($settings['payment_usdt_enabled']??'0')==='1'?'checked':'')?>>
                      <span class="switch-slider"></span>
                    </label>
                    <span class="pay-header-desc">支持自动回调，手动补单(后台商城管理-订单管理中补单)，因trongrid.io主网获取最新交易数据一般都有延迟，建议双重检查</span>
                  </div>
                </div>
                <div class="pay-channel-content">
                  <div class="pay-table-label">配置详情</div>
                  <div class="pay-table-form">
                    <div class="pay-row">
                      <span class="pay-row-title">钱包地址 (trc20)</span>
                      <div class="pay-row-content">
                        <input name="payment_usdt_address" value="<?=h($settings['payment_usdt_address']??'')?>" placeholder="">
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">结算汇率</span>
                      <div class="pay-row-content">
                        <input name="payment_usdt_rate" value="<?=h($settings['payment_usdt_rate']??'0.14')?>" placeholder="0.14">
                        <div class="pay-row-desc">1元等于多少USDT,默认0.14</div>
                      </div>
                    </div>
                    <div class="pay-row">
                      <span class="pay-row-title">自动回调</span>
                      <div class="pay-row-content pay-switch-row">
                        <label class="switch-btn red">
                          <input type="checkbox" name="payment_usdt_auto" value="1" <?=((string)($settings['payment_usdt_auto']??'0')==='1'?'checked':'')?>>
                          <span class="switch-slider"></span>
                        </label>
                        <div class="pay-row-desc" style="margin-top:6px;width:100%">开启自动回调后，用户在打开支付弹窗后，系统会自动根据接口trongrid.io查询交易信息中是否有当前订单时间区间内改订单金额交易，自动判断并回调，如果您网站订单并发数量巨大，同一时间同一订单金额可能会重复，建议关闭，或网络状态不好也建议关闭，可以在收到U的时候去你网站后台订单管理中手动补单</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">ⓘ 保存后支付配置立即生效</span>
              <button class="btn-primary" type="submit">保存支付设置</button>
            </div>
          </form>
        </div>

        <?php elseif ($tab === 'sms'): ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">✉</span>
            <h3>短信接口设置</h3>
            <span class="card-sub">验证码等系统通知</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="sms_settings_save">
            <div class="settings-card-body">
              <div class="form-grid">
                <div class="settings-field">
                  <span class="field-label">服务商</span>
                  <select name="sms_provider">
                    <option value="">-- 请选择 --</option>
                    <option value="aliyun" <?=$settings['sms_provider']==='aliyun'?'selected':''?>>阿里云短信</option>
                    <option value="tencent" <?=$settings['sms_provider']==='tencent'?'selected':''?>>腾讯云短信</option>
                  </select>
                </div>
                <div class="settings-field">
                  <span class="field-label">AccessKey ID</span>
                  <input name="sms_access_key_id" value="<?=h($settings['sms_access_key_id'])?>" placeholder="短信服务商 AccessKey">
                </div>
                <div class="settings-field">
                  <span class="field-label">AccessKey Secret</span>
                  <input name="sms_access_key_secret" type="password" value="<?=h($settings['sms_access_key_secret'])?>" placeholder="短信服务商 Secret" autocomplete="off">
                </div>
                <div class="settings-field">
                  <span class="field-label">短信签名</span>
                  <input name="sms_sign_name" value="<?=h($settings['sms_sign_name'])?>" placeholder="如：灵创AI">
                </div>
                <div class="settings-field">
                  <span class="field-label">模板编码</span>
                  <input name="sms_template_code" value="<?=h($settings['sms_template_code'])?>" placeholder="如：SMS_123456789">
                </div>
              </div>
            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">ⓘ 需要在短信服务商后台申请模板</span>
              <button class="btn-primary" type="submit">保存短信设置</button>
            </div>
          </form>
        </div>

        <?php elseif ($tab === 'mail'): ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">@</span>
            <h3>邮件接口设置</h3>
            <span class="card-sub">SMTP 邮件服务</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="mail_settings_save">
            <div class="settings-card-body">
              <div class="form-grid">
                <div class="settings-field">
                  <span class="field-label">驱动</span>
                  <select name="mail_driver"><option value="smtp" selected>SMTP</option></select>
                </div>
                <div class="settings-field">
                  <span class="field-label">SMTP 服务器</span>
                  <input name="mail_host" value="<?=h($settings['mail_host'])?>" placeholder="smtp.example.com">
                </div>
                <div class="settings-field">
                  <span class="field-label">端口</span>
                  <input name="mail_port" type="number" value="<?=(int)$settings['mail_port']?>" placeholder="465">
                </div>
                <div class="settings-field">
                  <span class="field-label">加密方式</span>
                  <select name="mail_encryption"><option value="ssl" <?=$settings['mail_encryption']==='ssl'?'selected':''?>>SSL</option><option value="tls" <?=$settings['mail_encryption']==='tls'?'selected':''?>>TLS</option></select>
                </div>
                <div class="settings-field">
                  <span class="field-label">用户名</span>
                  <input name="mail_username" value="<?=h($settings['mail_username'])?>" placeholder="noreply@example.com">
                </div>
                <div class="settings-field">
                  <span class="field-label">密码</span>
                  <input name="mail_password" type="password" value="<?=h($settings['mail_password'])?>" placeholder="SMTP 登录密码" autocomplete="off">
                </div>
                <div class="settings-field">
                  <span class="field-label">发件地址</span>
                  <input name="mail_from_address" value="<?=h($settings['mail_from_address'])?>" placeholder="noreply@example.com">
                </div>
                <div class="settings-field">
                  <span class="field-label">发件人名称</span>
                  <input name="mail_from_name" value="<?=h($settings['mail_from_name'])?>" placeholder="灵创AI">
                </div>
              </div>
            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">ⓘ 建议使用 SSL 465 端口</span>
              <button class="btn-primary" type="submit">保存邮件设置</button>
            </div>
          </form>
        </div>

        <?php elseif ($tab === 'seo'): ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">🔍</span>
            <h3>百度收录与推送配置</h3>
            <span class="card-sub">配置百度搜索资源平台 API 接口，实现自动推送与批量手动收录</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="seo_settings_save">
            <div class="settings-card-body">
              <div class="form-grid">
                <div class="settings-field field-full">
                  <span class="field-label">百度站点域名 (Site) <span class="field-required">*</span></span>
                  <input name="baidu_submit_site" value="<?=h($settings['baidu_submit_site']??'')?>" placeholder="例如：https://yourdomain.com 或 yourdomain.com">
                  <span class="field-desc">在百度搜索资源平台验证并绑定的站点域名（建议填写包含 http:// 或 https:// 的完整域名）</span>
                </div>
                <div class="settings-field field-full">
                  <span class="field-label">准入 Token <span class="field-required">*</span></span>
                  <input name="baidu_submit_token" value="<?=h($settings['baidu_submit_token']??'')?>" placeholder="在百度搜索资源平台「普通收录 - API提交」中获取的 Token">
                  <span class="field-desc">百度平台分配的准入密钥 Token，用于 API 鉴权</span>
                </div>
                <div class="settings-field field-full">
                  <span class="field-label">自动推送开关</span>
                  <label class="checkbox-wrap">
                    <input type="checkbox" name="baidu_submit_auto_enabled" value="1" <?=((string)($settings['baidu_submit_auto_enabled']??'0')==='1'?'checked':'')?>> 
                    发布或更新提示词时自动推送到百度收录接口
                  </label>
                </div>
              </div>
            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">ⓘ 接口文档参见 <a href="https://ziyuan.baidu.com/linksubmit/index" target="_blank" rel="noopener noreferrer">百度搜索资源平台</a></span>
              <button class="btn-primary" type="submit">保存 SEO 配置</button>
            </div>
          </form>
        </div>

        <!-- 手动批量推送卡片 -->
        <div class="settings-card" style="margin-top:16px">
          <div class="settings-card-head">
            <span class="card-icon" style="background:#edf5ff;color:#2f63d8">🚀</span>
            <h3>手动推送链接</h3>
            <span class="card-sub">主动将网站页面或自定义链接推送到百度，加快蜘蛛抓取收录</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="baidu_submit_manual">
            <div class="settings-card-body">
              <div class="form-grid">
                <div class="settings-field field-full">
                  <span class="field-label">推送模式</span>
                  <div style="display:flex;gap:20px;align-items:center;margin-top:4px">
                    <label class="checkbox-wrap"><input type="radio" name="push_mode" value="all" checked onchange="document.getElementById('custom-urls-wrap').style.display='none'"> 一键推送全站链接（首页、提示词广场、工具箱、智能体及已启用的提示词详情）</label>
                    <label class="checkbox-wrap"><input type="radio" name="push_mode" value="custom" onchange="document.getElementById('custom-urls-wrap').style.display='block'"> 自定义链接列表</label>
                  </div>
                </div>
                <div class="settings-field field-full" id="custom-urls-wrap" style="display:none">
                  <span class="field-label">自定义链接列表（每行一条完整 URL）</span>
                  <textarea name="custom_urls" rows="6" placeholder="https://yourdomain.com/&#10;https://yourdomain.com/?page=prompts&#10;https://yourdomain.com/?page=apps"></textarea>
                </div>
              </div>
            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">💡 建议先保存好上方的站点域名和 Token 后再进行推送操作</span>
              <button class="btn-primary" type="submit">立即执行推送</button>
            </div>
          </form>
        </div>

        <!-- 站点地图 (Sitemap) 卡片 -->
        <div class="settings-card" style="margin-top:16px">
          <div class="settings-card-head">
            <span class="card-icon" style="background:#f6ffed;color:#52c41a">🗺️</span>
            <h3>站点地图 (Sitemap.xml)</h3>
            <span class="card-sub">生成符合搜索引擎规范的标准 XML 站点地图</span>
          </div>
          <div class="settings-card-body">
            <div style="font-size:13px;color:var(--text-sub);line-height:1.6">
              <p style="margin:0 0 8px">当前站点地图访问地址：</p>
              <div style="background:var(--bg-tag);padding:8px 12px;border-radius:6px;font-family:monospace;font-size:12px;display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <span><a href="/sitemap.xml" target="_blank" style="color:var(--accent);text-decoration:none">/sitemap.xml</a>（静态文件） 或 <a href="/sitemap.php" target="_blank" style="color:var(--accent);text-decoration:none">/sitemap.php</a>（实时动态生成）</span>
                <?php if (file_exists(__DIR__ . '/sitemap.xml')): ?>
                  <span style="color:#52c41a;font-size:11px">已生成（最后更新：<?=date('Y-m-d H:i:s', filemtime(__DIR__ . '/sitemap.xml'))?>）</span>
                <?php else: ?>
                  <span style="color:#faad14;font-size:11px">未生成静态文件</span>
                <?php endif; ?>
              </div>
              <p style="margin:0">点击下方按钮可立即重新扫描数据库中已启用的提示词与系统页面，生成最新的静态 <code>sitemap.xml</code> 文件。</p>
            </div>
          </div>
          <div class="settings-card-foot">
            <span class="foot-hint">💡 可直接将 <code>/sitemap.xml</code> 提交给百度、Google、Bing、360 等各大搜索引擎资源平台</span>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?=csrf()?>">
              <input type="hidden" name="prompt_action" value="sitemap_generate">
              <button class="btn-primary" type="submit">立即生成 / 更新 Sitemap</button>
            </form>
          </div>
        </div>

        <?php elseif ($tab === 'user'): ?>
        <div class="settings-card">
          <div class="settings-card-head">
            <span class="card-icon">◉</span>
            <h3>用户设置</h3>
            <span class="card-sub">注册、登录与签到相关配置</span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="prompt_action" value="user_settings_save">
            <div class="settings-card-body">
              <div class="form-grid">
                <div class="settings-field">
                  <span class="field-label">开放前台注册</span>
                  <label class="checkbox-wrap"><input type="checkbox" name="register_enabled" value="1" <?=((string)$settings['register_enabled']==='1'?'checked':'')?>> 允许用户在前台自行注册</label>
                </div>
                <div class="settings-field">
                  <span class="field-label">注册赠送积分</span>
                  <input name="register_points" type="number" min="0" max="1000000" value="<?=(int)$settings['register_points']?>">
                  <span class="field-desc">新用户注册时自动赠送的积分数量</span>
                </div>
                <div class="settings-field">
                  <span class="field-label">注册赠送VIP</span>
                  <label class="checkbox-wrap"><input type="checkbox" name="register_enabled_vip" value="1" <?=((string)($settings['register_enabled_vip']??'0')==='1'?'checked':'')?>> 新用户注册送VIP体验</label>
                </div>
                <div class="settings-field">
                  <span class="field-label">VIP体验天数</span>
                  <input name="register_vip_days" type="number" min="0" max="36500" value="<?=(int)($settings['register_vip_days']??0)?>">
                  <span class="field-desc">注册赠送VIP的天数，0为不赠送</span>
                </div>
                <div class="settings-field">
                  <span class="field-label">密码最小长度</span>
                  <input name="min_password_len" type="number" min="4" max="64" value="<?=(int)($settings['min_password_len']??6)?>">
                  <span class="field-desc">用户注册时密码的最小字符数</span>
                </div>
                <div class="settings-field">
                  <span class="field-label">新用户默认积分</span>
                  <input name="user_default_points" type="number" min="0" max="1000000" value="<?=(int)($settings['user_default_points']??100)?>">
                  <span class="field-desc">用户创建时默认持有积分（注册赠送外）</span>
                </div>
              </div>
              <hr class="settings-divider">
              <div class="form-grid">
                <div class="settings-field">
                  <span class="field-label">每日签到</span>
                  <label class="checkbox-wrap"><input type="checkbox" name="checkin_enabled" value="1" <?=((string)($settings['checkin_enabled']??'1')==='1'?'checked':'')?>> 启用每日签到功能</label>
                </div>
                <div class="settings-field">
                  <span class="field-label">签到奖励积分</span>
                  <input name="checkin_points" type="number" min="0" max="1000" value="<?=(int)($settings['checkin_points']??1)?>">
                  <span class="field-desc">用户每日签到获得的积分数量</span>
                </div>
              </div>
            </div>
            <div class="settings-card-foot">
              <span class="foot-hint">ⓘ 用户设置保存后立即生效</span>
              <button class="btn-primary" type="submit">保存用户设置</button>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <!-- 安全说明 -->
        <div class="settings-card" style="margin-top:16px">
          <div class="settings-card-head">
            <span class="card-icon" style="background:#fef3f0;color:#c93f38">🔒</span>
            <h3>安全说明</h3>
          </div>
          <div class="settings-card-body" style="font-size:13px;color:#5a6b87;line-height:1.8">
            管理员密码以 bcrypt 哈希存储于服务器 <code style="background:#e8ecf3;padding:2px 7px;border-radius:5px;font-size:12px">runtime/admin_password_hash</code> 文件中，项目代码中不保存明文密码。如需修改密码，请在服务器上更新该哈希文件或环境变量。
          </div>
        </div>
      </div>
    </div>

<?php endif; ?>

  </main>
</div>
</body>
</html>
