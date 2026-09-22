<?php
ob_start();
session_start();
function ensure_feature_tables(){static $done=false;if($done)return;$done=true;try{$p=db();$p->exec("CREATE TABLE IF NOT EXISTS system_announcements(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(160) NOT NULL,content TEXT NOT NULL,category VARCHAR(40) NOT NULL DEFAULT '系统通知',is_pinned TINYINT NOT NULL DEFAULT 0,enabled TINYINT NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,published_at DATETIME NOT NULL,starts_at DATETIME NULL,ends_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(enabled),INDEX(published_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS user_checkins(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,checkin_date DATE NOT NULL,reward_points INT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,UNIQUE KEY uniq_user_date(user_id,checkin_date),INDEX(user_id,checkin_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS agent_configs(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,agent_key VARCHAR(60) NOT NULL UNIQUE,name VARCHAR(100) NOT NULL,subtitle VARCHAR(180) NOT NULL DEFAULT '',description TEXT NOT NULL,icon VARCHAR(20) NOT NULL DEFAULT '✦',system_prompt TEXT NOT NULL,model VARCHAR(160) NOT NULL DEFAULT '',points INT NOT NULL DEFAULT 5,sort_order INT NOT NULL DEFAULT 0,enabled TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(enabled),INDEX(sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("INSERT IGNORE INTO agent_configs(agent_key,name,subtitle,description,icon,system_prompt,model,points,sort_order,enabled,created_at,updated_at) VALUES('ppt-builder','一键生成PPT智能体','从主题到完整演示文稿','输入主题、受众和演讲目标，自动完成资料梳理、PPT大纲、逐页内容、视觉建议和演讲备注。','▤','你是一名资深演示设计师、内容策划和行业研究员。请根据用户的主题、受众、场景和目标，生成一份可直接制作PPT的完整方案。必须使用中文 Markdown，并严格输出：1. 演示目标与受众；2. 整体叙事线；3. PPT目录；4. 逐页内容（页码、标题、核心信息、页面文案、图表或配图建议、版式建议）；5. 关键数据与待补充资料；6. 演讲者备注；7. 视觉风格与配色建议。内容要有逻辑、可落地，避免空泛套话。','',8,20,1,NOW(),NOW())");$p->exec("CREATE TABLE IF NOT EXISTS vip_plans(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80) NOT NULL,price DECIMAL(10,2) NOT NULL DEFAULT 0,duration_days INT NOT NULL DEFAULT 0,points INT NOT NULL DEFAULT 0,description TEXT NOT NULL,enabled TINYINT NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,color VARCHAR(10) NOT NULL DEFAULT '#2f63d8',icon VARCHAR(20) NOT NULL DEFAULT '♛',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(enabled),INDEX(sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS vip_until DATETIME DEFAULT NULL");}catch(Exception $e){}}
function vip_plans($onlyActive=true){ensure_feature_tables();try{$sql='SELECT * FROM vip_plans';if($onlyActive)$sql.=' WHERE enabled=1';$sql.=' ORDER BY sort_order ASC, id ASC';$rows=db()->query($sql)->fetchAll();return $rows?:[];}catch(Exception $e){return[];}}
function points_packages($onlyActive=true){try{$sql='SELECT * FROM points_packages';if($onlyActive)$sql.=' WHERE enabled=1';$sql.=' ORDER BY sort_order ASC, id ASC';$rows=db()->query($sql)->fetchAll();return $rows?:[];}catch(Exception $e){return[];}}
function agent_configs($onlyActive=true){ensure_feature_tables();try{$sql='SELECT * FROM agent_configs';if($onlyActive)$sql.=' WHERE enabled=1';$sql.=' ORDER BY sort_order ASC,id ASC';$rows=db()->query($sql)->fetchAll();return $rows?:[];}catch(Exception $e){return[];}}
function agent_config($key){foreach(agent_configs(false) as $agent){if((string)$agent['agent_key']===(string)$key)return $agent;}return null;}

$config = require dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/lingjing.php';
function lingjing(){ static $client; if(!$client) $client=new LingjingClient(); return $client; }
function db() { static $pdo; global $config; if (!$pdo) { if (!$config['db']['pass']) throw new RuntimeException('database-not-configured'); $dsn='mysql:host='.$config['db']['host'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset']; $pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); } return $pdo; }
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} function text_limit($v,$len){$v=(string)$v;return function_exists('mb_substr')?mb_substr($v,0,$len,'UTF-8'):substr($v,0,$len);} function redirect($u){header('Location: '.$u);exit;} function csrf(){if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(16));return $_SESSION['csrf'];} function check_csrf(){if(!isset($_POST['csrf'])||!hash_equals($_SESSION['csrf']??'',$_POST['csrf'])){http_response_code(419);exit('请求已过期，请刷新页面重试');}}
function render_rich_announcement_content($content){
    if(empty($content)) return '';
    // 如果已经是 HTML 结构，直接返回
    if(strpos($content, '<div class="ann-') !== false || strpos($content, '<section') !== false) {
        return $content;
    }
    
    // 按行或段落进行智能格式化解析
    $lines = explode("
", trim($content));
    $html = '';
    $inCard = false;
    
    foreach($lines as $rawLine){
        $line = trim($rawLine);
        if($line === '') {
            continue;
        }
        
        // 匹配主分类/分隔标题，如 01 新模型上线 或 ◆ 新功能 / ● 新模型上线
        if(preg_match('/^(?:\d{1,2}\s+|[●◆■★]\s*)(.+)$/u', $line, $m) || (mb_strlen($line) <= 12 && (strpos($line, '模型') !== false || strpos($line, '功能') !== false || strpos($line, '上线') !== false || strpos($line, '优化') !== false))) {
            if($inCard) { $html .= '</div>'; $inCard = false; }
            $sectionTitle = !empty($m[1]) ? $m[1] : $line;
            $html .= '<div class="ann-section-divider"><span class="dot"></span><span>' . h($sectionTitle) . '</span><span class="line"></span></div>';
            continue;
        }
        
        // 匹配模型/功能小卡片标题 (如 TT-6 astra / 万相 3.0 / TT Image 2.5)
        if(preg_match('/^(TT[-\s\w\.]+|万相[\w\.]+|MiniMax[\w\.]+|千问[\w\.]+|FB-[\w\.]+|GEM[\w\.]+|[A-Z0-9\s\.\-]{3,20})(?:\s*·\s*(.+))?$/iu', $line, $m)){
            if($inCard) { $html .= '</div>'; }
            $inCard = true;
            $title = trim($m[1]);
            $subTag = trim($m[2] ?? '');
            
            $html .= '<div class="ann-card">';
            $html .= '<div class="ann-tags-row">';
            $html .= '<span class="ann-tag-pill">' . h($title) . '</span>';
            if($subTag !== '') {
                $html .= '<span class="ann-tag-pill cyan">' . h($subTag) . '</span>';
            }
            $html .= '</div>';
            continue;
        }
        
        // 如果是处于卡片内部的内容
        if($inCard) {
            // 检查是否是标签特征行 (例如 按次·一张一结 / 官转 / 格式等)
            if(strpos($line, '·') !== false && mb_strlen($line) <= 40 && strpos($line, '。') === false) {
                $pills = explode(' ', $line);
                $html .= '<div class="ann-feature-pills">';
                foreach($pills as $pill){
                    if(trim($pill) !== '') {
                        $html .= '<span class="ann-feat-pill">' . h(trim($pill)) . '</span>';
                    }
                }
                $html .= '</div>';
            } else {
                // 普通说明段落，高亮关键名词
                $formatted = h($line);
                $formatted = preg_replace('/(比例|清晰度|2K|4K|30秒|1080P|自适应思考|深度思考|超长上下文)/u', '<span class="highlight-orange">$1</span>', $formatted);
                $html .= '<p>' . $formatted . '</p>';
            }
        } else {
            // 普通段落
            $html .= '<div class="ann-card"><p>' . h($line) . '</p></div>';
        }
    }
    
    if($inCard) { $html .= '</div>'; }
    return $html;
}

function user(){static $u=false;if($u!==false)return $u;$u=null;if(!empty($_SESSION['user_id']))try{$s=db()->prepare('SELECT * FROM users WHERE id=? AND status=1');$s->execute([$_SESSION['user_id']]);$u=$s->fetch();}catch(Exception $e){}return $u;}
function system_announcements($onlyActive=true){ensure_feature_tables();try{$sql='SELECT * FROM system_announcements';if($onlyActive)$sql.=' WHERE enabled=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())';$sql.=' ORDER BY is_pinned DESC, sort_order ASC, published_at DESC, id DESC';$rows=db()->query($sql)->fetchAll();return $rows?:[];}catch(Exception $e){return[];}}
function latest_system_announcement(){ $rows=system_announcements(true); return $rows[0]??null; }
function user_unread_announcement($userId){
    $a = latest_system_announcement();
    if(!$a) return null;
    $readId = (int)($_SESSION['last_read_announcement_id'] ?? 0);
    if($readId >= (int)$a['id']) return null;
    return $a;
}
function user_daily_checkin($userId){ensure_feature_tables();$today=date('Y-m-d');try{$s=db()->prepare('SELECT * FROM user_checkins WHERE user_id=? AND checkin_date=? LIMIT 1');$s->execute([(int)$userId,$today]);return $s->fetch()?:null;}catch(Exception $e){return null;}}
function ensure_user_checkin($userId){
    $today = date('Y-m-d');
    $existing = user_daily_checkin($userId);
    if($existing) return $existing;
    try{
        $p = db();
        $p->beginTransaction();
        $s = $p->prepare('SELECT points FROM users WHERE id=? FOR UPDATE');
        $s->execute([(int)$userId]);
        $points = $s->fetchColumn();
        if($points === false){
            $p->rollBack();
            return null;
        }

        // 计算连续签到天数
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $st = $p->prepare('SELECT consecutive_days FROM user_checkins WHERE user_id=? AND checkin_date=? LIMIT 1');
        $st->execute([(int)$userId, $yesterday]);
        $lastConsecutive = (int)($st->fetchColumn() ?: 0);
        $consecutiveDays = $lastConsecutive + 1;

        // 阶梯奖励计算：基础奖励 + 阶梯奖励（连续3天+3积分，连续7天+10积分）
        $baseReward = (int)site_setting('checkin_points', '1');
        $bonus = 0;
        if($consecutiveDays % 7 === 0) {
            $bonus = 10;
        } elseif($consecutiveDays % 3 === 0) {
            $bonus = 3;
        }
        $reward = $baseReward + $bonus;

        $p->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$reward, (int)$userId]);
        $p->prepare('INSERT INTO user_checkins(user_id, checkin_date, reward_points, consecutive_days, created_at) VALUES(?,?,?,?,NOW())')
          ->execute([(int)$userId, $today, $reward, $consecutiveDays]);
        $p->commit();
        return [
            'user_id' => (int)$userId,
            'checkin_date' => $today,
            'reward_points' => $reward,
            'consecutive_days' => $consecutiveDays,
            'bonus' => $bonus,
            'points_after' => (int)$points + $reward,
            'is_new' => 1
        ];
    }catch(Exception $e){
        if(isset($p) && $p instanceof PDO && $p->inTransaction()) $p->rollBack();
        return user_daily_checkin($userId);
    }
}
function redeem_card($userId, $cardCode){
    $cardCode = trim((string)$cardCode);
    if(!$cardCode) return ['ok' => false, 'message' => '请输入有效的卡密'];
    try{
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM recharge_cards WHERE card_code=? FOR UPDATE');
        $st->execute([$cardCode]);
        $card = $st->fetch();
        if(!$card) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '卡密不存在或已作废'];
        }
        if((int)$card['status'] !== 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '卡密已被使用或失效'];
        }

        $pts = (int)$card['points'];
        $vDays = (int)$card['vip_days'];

        // 标记已使用
        $pdo->prepare('UPDATE recharge_cards SET status=1, used_by=?, used_at=NOW() WHERE id=?')->execute([(int)$userId, $card['id']]);

        // 充值积分
        if($pts > 0){
            $pdo->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$pts, (int)$userId]);
        }

        // 充值 VIP
        if($vDays > 0){
            $st = $pdo->prepare('SELECT vip_until FROM users WHERE id=?');
            $st->execute([(int)$userId]);
            $curVip = $st->fetchColumn();
            $baseTs = ($curVip && strtotime($curVip) > time()) ? strtotime($curVip) : time();
            $newVip = date('Y-m-d H:i:s', strtotime("+{$vDays} days", $baseTs));
            $pdo->prepare('UPDATE users SET vip_until=? WHERE id=?')->execute([$newVip, (int)$userId]);
        }

        $pdo->commit();
        $desc = [];
        if($pts > 0) $desc[] = "{$pts} 创作积分";
        if($vDays > 0) $desc[] = "{$vDays} 天 VIP 会员";
        return ['ok' => true, 'message' => '卡密兑换成功！已获得：' . implode(' + ', $desc)];
    }catch(Exception $e){
        if(isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => '兑换失败：' . $e->getMessage()];
    }
}

function generate_batch_cards($type='points', $points=100, $vipDays=0, $count=10, $remark=''){
    $count = max(1, min(500, (int)$count));
    $pdo = db();
    $genList = [];
    for($i = 0; $i < $count; $i++){
        $code = strtoupper(substr($type, 0, 1)) . date('ymd') . strtoupper(bin2hex(random_bytes(6)));
        $st = $pdo->prepare('INSERT INTO recharge_cards(card_code, card_type, points, vip_days, status, remark, created_at) VALUES(?,?,?,?,0,?,NOW())');
        $st->execute([$code, $type, (int)$points, (int)$vipDays, (string)$remark]);
        $genList[] = $code;
    }
    return $genList;
}

function ensure_user_invite_code($user){
    if(!empty($user['invite_code'])) return $user['invite_code'];
    $code = strtoupper(substr(md5($user['id'].':'.$user['username'].':salt'), 0, 8));
    try{
        db()->prepare('UPDATE users SET invite_code=? WHERE id=?')->execute([$code, $user['id']]);
    }catch(Exception $e){}
    return $code;
}

function record_invite_registration($newUserId, $inviteCode){
    $inviteCode = trim((string)$inviteCode);
    if(!$inviteCode) return 0;
    try{
        $pdo = db();
        $st = $pdo->prepare('SELECT id, points FROM users WHERE invite_code=? AND id!=? LIMIT 1');
        $st->execute([$inviteCode, $newUserId]);
        $inviter = $st->fetch();
        if(!$inviter) return 0;
        $inviterId = (int)$inviter['id'];
        $rewardPoints = (int)site_setting('invite_reward_points', '50');
        $inviteeBonus = (int)site_setting('invitee_bonus_points', '20');

        $pdo->beginTransaction();
        // 绑定邀请人
        $pdo->prepare('UPDATE users SET invited_by=?, points=points+? WHERE id=?')->execute([$inviterId, $inviteeBonus, $newUserId]);
        // 奖励邀请人
        $pdo->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$rewardPoints, $inviterId]);
        // 记录邀请表
        $pdo->prepare('INSERT INTO user_invites(inviter_id, invitee_id, reward_points, created_at) VALUES(?,?,?,NOW())')
            ->execute([$inviterId, $newUserId, $rewardPoints]);
        $pdo->commit();
        return $inviterId;
    }catch(Exception $e){
        if(isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        return 0;
    }
}
function should_show_daily_checkin($userId){return !user_daily_checkin($userId);} function require_api_user(){if(!user()){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'login_required','message'=>'请先登录后再生成或保存'],JSON_UNESCAPED_UNICODE);exit;}} function require_user(){if(!user())redirect('/?page=login');} function admin_user(){static $a=false;if($a!==false)return $a;$a=null;if(!empty($_SESSION['admin_user_id'])){if($_SESSION['admin_user_id']==='file:admin')return $a=['id'=>0,'username'=>'admin'];try{$s=db()->prepare('SELECT id,username FROM admin_users WHERE id=?');$s->execute([$_SESSION['admin_user_id']]);$a=$s->fetch();}catch(Exception $e){}}return $a;} function require_admin(){if(!admin_user())redirect('/admin.php');} function admin_login($username,$password){global $config;$username=trim((string)$username);$password=(string)$password;if($username===''||$password==='')return false;$expectedUser=getenv('LINGCHUANGX_ADMIN_USER')?:'admin';$hash=getenv('LINGCHUANGX_ADMIN_PASSWORD_HASH')?:'';$hashFile=dirname(__DIR__).'/runtime/admin_password_hash';if($hash===''&&is_readable($hashFile))$hash=trim(file_get_contents($hashFile));if(hash_equals($expectedUser,$username)&&$hash!==''&&password_verify($password,$hash)){try{$s=db()->prepare('INSERT INTO admin_users(username,password_hash,created_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)');$s->execute([$username,$hash]);$_SESSION['admin_user_id']=db()->lastInsertId();if(!$_SESSION['admin_user_id']){$s=db()->prepare('SELECT id FROM admin_users WHERE username=?');$s->execute([$username]);$_SESSION['admin_user_id']=$s->fetchColumn();}}catch(Exception $e){return false;}session_regenerate_id(true);return true;}try{$s=db()->prepare('SELECT id,password_hash FROM admin_users WHERE username=?');$s->execute([$username]);$row=$s->fetch();if($row&&password_verify($password,$row['password_hash'])){session_regenerate_id(true);$_SESSION['admin_user_id']=$row['id'];return true;}}catch(Exception $e){}return false;} function admin_logout(){unset($_SESSION['admin_user_id']);session_regenerate_id(true);} function flash($m,$t='info'){$_SESSION['flash']=[$m,$t];} function take_flash(){$v=$_SESSION['flash']??null;unset($_SESSION['flash']);return $v;}
function save_uploaded_file($file,$kind='cover'){if(empty($file)||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('未收到上传文件');$kind=$kind==='media'?'media':'cover';$size=(int)$file['size'];$max=50*1024*1024;if($size<=0||$size>$max)throw new InvalidArgumentException('文件大小超出限制（50MB）');$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));$allowImg=['jpg','jpeg','png','gif','webp'];$allowVid=['mp4','webm','mov','m4v'];$allowed=($kind==='media')?$allowVid:$allowImg;if(!in_array($ext,$allowed,true))throw new InvalidArgumentException('不支持的文件类型：'.$ext);$dir=dirname(__DIR__).'/uploads';if(!is_dir($dir))mkdir($dir,0755,true);$name=date('YmdHis').'_'.bin2hex(random_bytes(6)).'.'.$ext;$dest=$dir.'/'.$name;if(!move_uploaded_file($file['tmp_name'],$dest))throw new RuntimeException('文件保存失败');return ['url'=>'/uploads/'.$name,'kind'=>$kind,'size'=>$size,'ext'=>$ext];} function handle_upload(){$kind=$_POST['kind']??'cover';return save_uploaded_file($_FILES['file']??null,$kind);}
function tools(){try{return db()->query('SELECT * FROM ai_tools WHERE enabled=1 ORDER BY sort_order')->fetchAll();}catch(Exception $e){return [['name'=>'电商主图直出','slug'=>'image','description'=>'上传商品图，一键生成高转化主图','icon'=>'▧','category'=>'电商设计','points'=>8],['name'=>'商品精修','slug'=>'image','description'=>'智能抠图，高质感精修输出','icon'=>'◉','category'=>'图片处理','points'=>6],['name'=>'爆款主图复制','slug'=>'image','description'=>'参考爆款快速生成商品主图','icon'=>'✣','category'=>'电商设计','points'=>8],['name'=>'商品详情页/A+','slug'=>'image','description'=>'智能生成商品详情内容','icon'=>'▤','category'=>'电商设计','points'=>8],['name'=>'商品场景图','slug'=>'image','description'=>'精准识别产品，适配商品场景','icon'=>'▱','category'=>'图片处理','points'=>6],['name'=>'电商海报','slug'=>'image','description'=>'一键生成营销海报','icon'=>'▥','category'=>'电商设计','points'=>5],['name'=>'模特试衣','slug'=>'image','description'=>'平铺图一键上身试穿','icon'=>'♧','category'=>'电商设计','points'=>8],['name'=>'换模特','slug'=>'image','description'=>'自由更换商业模特','icon'=>'◎','category'=>'图片处理','points'=>8]];}}
function tool_page($slug){$m=['image'=>'image','chat'=>'chat','copywriting'=>'text','article'=>'text'];$slug=strtolower((string)$slug);return isset($m[$slug])?$m[$slug]:'apps';}
function default_site_menus(){return [['label'=>'首页','subtitle'=>'一站式创作平台','icon'=>'⌂','url'=>'/','page_key'=>'home','badge'=>'','sort_order'=>10],['label'=>'资产管理','subtitle'=>'管理生成的作品','icon'=>'□','url'=>'/?page=history','page_key'=>'history','badge'=>'','sort_order'=>20],['label'=>'AI文本','subtitle'=>'','icon'=>'Aa','url'=>'/?page=text','page_key'=>'text','badge'=>'new','sort_order'=>25],['label'=>'AI视频','subtitle'=>'','icon'=>'↗','url'=>'/?page=video','page_key'=>'video','badge'=>'new','sort_order'=>30],['label'=>'AI绘画','subtitle'=>'','icon'=>'✎','url'=>'/?page=image','page_key'=>'image','badge'=>'火爆','sort_order'=>40],['label'=>'AI音频','subtitle'=>'','icon'=>'♫','url'=>'/?page=audio','page_key'=>'audio','badge'=>'new','sort_order'=>45],['label'=>'AI工具箱','subtitle'=>'','icon'=>'⌘','url'=>'/?page=apps','page_key'=>'apps','badge'=>'推荐','sort_order'=>50],['label'=>'智能体','subtitle'=>'一键生成PPT方案','icon'=>'▤','url'=>'/?page=agents','page_key'=>'agents','badge'=>'new','sort_order'=>55],['label'=>'个人中心','subtitle'=>'套餐充值卡密兑换','icon'=>'⚑','url'=>'/?page=profile','page_key'=>'profile','badge'=>'','sort_order'=>60]];}
function site_menus(){try{$rows=db()->query('SELECT * FROM site_menus WHERE enabled=1 ORDER BY sort_order,id')->fetchAll();return $rows?:default_site_menus();}catch(Exception $e){return default_site_menus();}}
function default_home_blocks(){return [['kind'=>'hero','title'=>'灵创AI 爆款内容工坊','subtitle'=>'商品主图、视频脚本、提示词和电商视觉一站式生成。','badge'=>'AI CREATIVE FACTORY','image_url'=>'/assets/home/shortcut-main.png','link_url'=>'/?page=image','sort_order'=>10],['kind'=>'ad','title'=>'新手教程指南','subtitle'=>'','badge'=>'','image_url'=>'/assets/home/category-canvas.png','link_url'=>'/?page=prompts','sort_order'=>20],['kind'=>'ad','title'=>'AI换装模特','subtitle'=>'','badge'=>'','image_url'=>'/assets/home/shortcut-detail.png','link_url'=>'/?page=image','sort_order'=>30],['kind'=>'ad','title'=>'AI餐饮视觉','subtitle'=>'','badge'=>'','image_url'=>'/assets/home/hot-poster.png','link_url'=>'/?page=image','sort_order'=>40],['kind'=>'ad','title'=>'建筑室内设计','subtitle'=>'','badge'=>'','image_url'=>'/assets/home/hot-scene.png','link_url'=>'/?page=image','sort_order'=>50]];}
function home_blocks(){try{$rows=db()->query('SELECT * FROM home_blocks WHERE enabled=1 ORDER BY sort_order,id')->fetchAll();return $rows?:default_home_blocks();}catch(Exception $e){return default_home_blocks();}}
function page_from_url($url){$parts=parse_url((string)$url);if(empty($parts['query']))return ((string)$url==='/'||$url==='')?'home':'';parse_str($parts['query'],$q);return $q['page']??'';}
function default_site_settings(){return ['site_name'=>'灵创AI','site_subtitle'=>'AI爆款工坊','site_logo'=>'','logo_text'=>'LC','service_title'=>'联系客服','service_subtitle'=>'随为您服务','service_button'=>'立即咨询','service_url'=>'/?page=service','service_qrcode'=>'','points_label'=>'-- 积分','register_points'=>'100','register_enabled'=>'1','register_enabled_vip'=>'0','register_vip_days'=>'0','min_password_len'=>'6','user_default_points'=>'100','checkin_enabled'=>'1','checkin_points'=>'1','site_notice'=>'','footer_copyright'=>'','footer_icp'=>'','footer_icp_url'=>'','footer_contact_email'=>'','footer_contact_phone'=>'','footer_about_url'=>'','footer_privacy_url'=>'','footer_terms_url'=>'','footer_police_beian'=>'','footer_police_url'=>'','footer_friend_links'=>'','lingjing_base_url'=>'https://api.lk888.ai/api','lingjing_default_model'=>'','model_provider_default'=>'lingjing','openai_custom_enabled'=>'0','openai_custom_base_url'=>'https://api.openai.com/v1','openai_custom_model'=>'gpt-4o-mini','deepseek_enabled'=>'0','deepseek_base_url'=>'https://api.deepseek.com/v1','deepseek_model'=>'deepseek-chat','claude_enabled'=>'0','claude_base_url'=>'https://api.anthropic.com','claude_model'=>'claude-3-5-sonnet-20241022','gemini_enabled'=>'0','gemini_base_url'=>'https://generativelanguage.googleapis.com','gemini_model'=>'gemini-1.5-flash','qwen_enabled'=>'0','qwen_base_url'=>'https://dashscope.aliyuncs.com/compatible-mode/v1','qwen_model'=>'qwen-plus','payment_alipay_enabled'=>'0','payment_alipay_app_id'=>'','payment_alipay_private_key'=>'','payment_alipay_public_key'=>'','payment_alipay_mode'=>'web','payment_alipay_h5'=>'0','payment_wxpay_enabled'=>'0','payment_wxpay_mch_id'=>'','payment_wxpay_app_id'=>'','payment_wxpay_key'=>'','payment_wxpay_h5'=>'0','payment_hupiv3_wx_enabled'=>'0','payment_hupiv3_wx_appid'=>'','payment_hupiv3_wx_secret'=>'','payment_hupiv3_wx_gateway'=>'','payment_hupiv3_ali_enabled'=>'0','payment_hupiv3_ali_appid'=>'','payment_hupiv3_ali_secret'=>'','payment_hupiv3_ali_gateway'=>'','payment_xunhu_wx_enabled'=>'0','payment_xunhu_wx_mchid'=>'','payment_xunhu_wx_key'=>'','payment_xunhu_wx_gateway'=>'https://api.xunhupay.com/payment/do.html','payment_xunhu_ali_enabled'=>'0','payment_xunhu_ali_mchid'=>'','payment_xunhu_ali_key'=>'','payment_xunhu_ali_gateway'=>'https://api.xunhupay.com/payment/do.html','payment_epay_ali_enabled'=>'0','payment_epay_ali_pid'=>'','payment_epay_ali_key'=>'','payment_epay_ali_api'=>'','payment_epay_wx_enabled'=>'0','payment_epay_wx_pid'=>'','payment_epay_wx_key'=>'','payment_epay_wx_api'=>'','payment_paypal_enabled'=>'0','payment_paypal_username'=>'','payment_paypal_password'=>'','payment_paypal_signature'=>'','payment_paypal_currency'=>'USD','payment_paypal_rate'=>'0.14','payment_paypal_sandbox'=>'0','payment_usdt_enabled'=>'0','payment_usdt_address'=>'','payment_usdt_rate'=>'0.14','payment_usdt_auto'=>'0','payment_notify_url'=>'','sms_provider'=>'','sms_access_key_id'=>'','sms_access_key_secret'=>'','sms_sign_name'=>'','sms_template_code'=>'','mail_driver'=>'smtp','mail_host'=>'','mail_port'=>'465','mail_username'=>'','mail_password'=>'','mail_encryption'=>'ssl','mail_from_address'=>'','mail_from_name'=>'','baidu_submit_site'=>'','baidu_submit_token'=>'','baidu_submit_auto_enabled'=>'0',];}
function site_settings(){static $settings=null;if($settings!==null)return $settings;$settings=default_site_settings();try{$rows=db()->query('SELECT setting_key,setting_value FROM site_settings')->fetchAll();foreach($rows as $r)$settings[$r['setting_key']]=$r['setting_value'];}catch(Exception $e){}return $settings;}
function site_setting($key,$default=null){$s=site_settings();return array_key_exists($key,$s)?$s[$key]:$default;}
function save_site_settings($values){$pdo=db();$sql='INSERT INTO site_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()';$st=$pdo->prepare($sql);foreach($values as $k=>$v)$st->execute([$k,(string)$v]);}
function model_group_label($name,$display){$txt=strtolower($name.' '.$display);if(strpos($txt,'seedance')!==false||strpos($txt,'sd ')!==false)return 'Seedance';if(strpos($txt,'hailuo')!==false||strpos($txt,'海螺')!==false)return '海螺';if(strpos($txt,'wan')!==false||strpos($txt,'万相')!==false)return 'Wan';if(strpos($txt,'kling')!==false||strpos($txt,'keling')!==false||strpos($txt,'可灵')!==false)return 'Keling';if(strpos($txt,'veo')!==false)return 'Veo';if(strpos($txt,'sora')!==false)return 'Sora';if(strpos($txt,'vidu')!==false)return 'Vidu';if(strpos($txt,'gpt')!==false||strpos($txt,'tt-image')!==false)return 'GPT Image';if(strpos($txt,'banana')!==false||strpos($txt,'纳米香蕉')!==false)return '纳米香蕉';if(strpos($txt,'flux')!==false)return 'Flux';if(strpos($txt,'suno')!==false)return 'Suno';if(strpos($txt,'tts')!==false||strpos($txt,'语音')!==false)return '语音';if(strpos($txt,'music')!==false||strpos($txt,'音乐')!==false)return '音乐';return $display?:$name;}
function model_icon($type,$label){if($type==='chat')return 'Aa';if($type==='audio')return strpos($label,'音乐')!==false||strpos($label,'Suno')!==false?'♫':'◌';if($type==='video')return '↗';return '✦';}
function default_model_points($type){return $type==='video'?198:($type==='audio'?80:($type==='chat'?5:20));}
function model_cache_file(){return dirname(__DIR__).'/runtime/model_cache.json';}
function read_model_cache(){static $data=null;if($data!==null)return $data;$file=model_cache_file();if(is_readable($file)){try{$json=json_decode(file_get_contents($file),true);if(is_array($json))return $data=$json;}catch(Exception $e){}}return $data=['ts'=>0,'groups'=>[]];}
function write_model_cache($groups){$file=model_cache_file();$dir=dirname($file);if(!is_dir($dir)){@mkdir($dir,0775,true);}if(file_exists($file)&&!is_writable($file)){@chmod($file,0664);}if(file_exists($file)&&!is_writable($file)){@unlink($file);}@file_put_contents($file,json_encode(['ts'=>time(),'groups'=>$groups],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function refresh_model_cache($force=false){$cache=read_model_cache();if(!$force&&!empty($cache['groups'])&&time()-(int)$cache['ts']<86400)return $cache['groups'];$groups=$cache['groups']??[];foreach(['image','video','audio'] as $type){try{$r=lingjing()->mediaModels($type);$rows=$r['models']??[];if(is_array($rows)&&$rows)$groups[$type]=$rows;}catch(Exception $e){}}try{$r=lingjing()->models('chat');$rows=$r['models']??[];if(is_array($rows)&&$rows)$groups['chat']=$rows;}catch(Exception $e){}if($groups)write_model_cache($groups);return $groups;}
function model_prices_file(){return dirname(model_cache_file()).'/model_prices.json';}
function read_model_prices(){try{$f=model_prices_file();if(!is_readable($f))return[];$d=json_decode(file_get_contents($f),true);return is_array($d)?$d:[];}catch(Exception $e){return[];}}
function write_model_prices($prices){$f=model_prices_file();$d=dirname($f);if(!is_dir($d))@mkdir($d,0775,true);if(file_exists($f)&&!is_writable($f)){@chmod($f,0664);}if(file_exists($f)&&!is_writable($f)){@unlink($f);}@file_put_contents($f,json_encode(['ts'=>time(),'prices'=>$prices],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);@chmod($f,0664);}
function sync_model_prices(){$cache=read_model_cache();$groups=$cache['groups']??[];$prices=[];foreach($groups as $type=>$models){foreach($models as $m){$name=$m['name']??'';if($name==='')continue;try{$prices[$name]=lingjing()->modelPricing($name);}catch(Exception $e){}}}write_model_prices($prices);return $prices;}
function raw_models($type){static $cache=[];if(isset($cache[$type]))return $cache[$type];$all=read_model_cache();if(!empty($all['groups'][$type]))return $cache[$type]=$all['groups'][$type];$groups=refresh_model_cache(false);return $cache[$type]=$groups[$type]??[];}
function model_rules(){try{$rows=db()->query('SELECT * FROM model_rules ORDER BY type,sort_order,id')->fetchAll();$out=[];foreach($rows as $r)$out[$r['type']][$r['model_name']]=$r;return $out;}catch(Exception $e){return [];}}
function model_families($type){$models=raw_models($type);$rules=model_rules();$out=[];foreach($models as $i=>$m){$name=$m['name']??'';if($name==='')continue;$display=$m['display_name']??$name;$rule=$rules[$type][$name]??null;$label=$rule&&$rule['label']!==''?$rule['label']:model_group_label($name,$display);$key=preg_replace('/[^a-z0-9]+/','-',strtolower($name));$out[]=['key'=>$key,'label'=>$label,'desc'=>$display,'icon'=>$rule&&$rule['icon']!==''?$rule['icon']:model_icon($type,$label),'model'=>$name,'points'=>($rule&&(int)$rule['points']>0)?(int)$rule['points']:default_model_points($type),'enabled'=>$rule?((int)$rule['enabled']===1):1,'sort_order'=>$rule?(int)$rule['sort_order']:$i];}usort($out,function($a,$b){return $a['sort_order']<=>$b['sort_order'];});$out=array_values(array_filter($out,function($x){return $x['enabled'];}));if(!$out){if($type==='audio')return [['key'=>'suno-v4-5','label'=>'Suno','desc'=>'音乐生成','icon'=>'♫','model'=>'suno-v4.5','points'=>0,'enabled'=>1,'sort_order'=>10]];if($type==='video')return [['key'=>'doubao-seedance-2-5-260628','label'=>'Seedance','desc'=>'SD 2.5 文生','icon'=>'↗','model'=>'doubao-seedance-2-5-260628','points'=>0,'enabled'=>1,'sort_order'=>10]];return [['key'=>'tt-image-2','label'=>'GPT Image','desc'=>'GPT Image 2','icon'=>'✦','model'=>'tt-image-2','points'=>0,'enabled'=>1,'sort_order'=>10]];}return $out;}
function video_model_families(){return model_families('video');}
function image_model_families(){return model_families('image');}
function audio_model_families(){return model_families('audio');}
function toolbox_items(){return [['key'=>'prompt','label'=>'爆款','desc'=>'一键提取爆款内容','icon'=>'▣','url'=>'/?page=tool&tool=prompt'],['key'=>'video2prompt','label'=>'视频转提示词','desc'=>'AI 逆向解析视频生成提示词','icon'=>'▷','url'=>'/?page=video2prompt'],['key'=>'image2prompt','label'=>'图片转提示词','desc'=>'AI 逆向解析图片生成提示词','icon'=>'▨','url'=>'/?page=image2prompt'],['key'=>'watermark-remove','label'=>'无痕无水印','desc'=>'短视频去水印高清下载','icon'=>'◌','url'=>'/?page=watermark-remove'],['key'=>'video-transcript','label'=>'视频文案提取','desc'=>'AI 识别视频语音生成文案','icon'=>'▤','url'=>'/?page=video-transcript'],['key'=>'animate-mix','label'=>'换人','desc'=>'一键视频换人','icon'=>'∪','url'=>'/?page=tool&tool=animate-mix'],['key'=>'animate-move','label'=>'动作','desc'=>'一键将人物融入动作','icon'=>'◷','url'=>'/?page=tool&tool=animate-move'],['key'=>'video_enhance','label'=>'视频转超清','desc'=>'将视频超分提高画质','icon'=>'▱','url'=>'/?page=tool&tool=video_enhance']];}
function tool_url($slug){$slug=strtolower((string)$slug);foreach(toolbox_items() as $it){if($it['key']===$slug)return $it['url'];}return '/?page='.tool_page($slug);}
function prompt_extract_models(){static $list=null;if($list!==null)return $list;$preferred=['tt-5.6-luna','qwen3.8-max-0902','doubao-seed-2-1-pro-260628','kimi-k3','tt-6-astra','glm-5.3-flash','deepseek-v4-flash-vision-exp','qwen3.7-max','doubao-seed-evolving','tt-5.4','MiniMax-M3','stepfun/step-3.7-flash'];$hit=[];$rest=[];foreach(raw_models('chat') as $m){$name=trim((string)($m['name']??''));if($name==='')continue;if(($m['api_format']??'openai')!=='openai')continue;if(array_key_exists('available_for_this_key',$m)&&!$m['available_for_this_key'])continue;$tags=$m['tags']??[];if(!is_array($tags)||!in_array('多模态',$tags,true))continue;$item=['name'=>$name,'label'=>(trim((string)($m['display_name']??''))?:$name)];if(in_array($name,$preferred,true))$hit[$name]=$item;else $rest[$name]=$item;}$out=[];foreach($preferred as $p){if(isset($hit[$p]))$out[]=$hit[$p];}foreach($rest as $r){if(count($out)>=20)break;$out[]=$r;}if(!$out)$out=[['name'=>'tt-5.6-luna','label'=>'TT-5.6 luna']];return $list=$out;}
function prompt_extract_default_model(){$list=prompt_extract_models();return $list[0]['name'];}
function prompt_extract_normalize_images($images,$mode){if(!is_array($images))return [];$max=$mode==='video'?12:4;$out=[];foreach($images as $raw){if(count($out)>=$max)break;if(!is_string($raw))continue;$raw=trim($raw);if($raw==='')continue;if(!preg_match('#^data:image/(jpeg|jpg|png|webp|gif);base64,([A-Za-z0-9+/=]+)$#',$raw,$m))continue;$b64=$m[2];$len=strlen($b64);if($len<128||$len>9*1024*1024)continue;$out[]='data:image/'.($m[1]==='jpg'?'jpeg':$m[1]).';base64,'.$b64;}return $out;}
function prompt_extract_clean_output($text){$text=trim((string)$text);if($text==='')return '';$text=str_replace(["\r\n","\r"],"\n",$text);if(preg_match('/^```[a-zA-Z0-9_-]*[ \t]*\n?([\s\S]*?)\n?```$/',$text,$m))$text=trim($m[1]);$text=preg_replace('/^\s*(好的|当然|以下是|下面是)[^\n]{0,40}\n+/u','',$text);$text=preg_replace("/\n{3,}/","\n\n",$text);return trim($text);}
function prompt_extract_user_instruction($mode,$count){if($mode==='video')return '下面是一段视频按时间顺序抽取的 '.$count.' 帧关键帧（每帧前标注了序号）。请按系统要求的格式输出可直接使用的视频生成提示词。';return '下面是要分析的 '.$count.' 张图片。请按系统要求的格式输出可直接使用的图像生成提示词。';}
function prompt_extract_system_prompt($mode,$lang,$style){
    $styleNote=trim((string)$style);
    if($mode==='video'){
        if($lang==='en'){
            $p="You are a top-tier prompt reverse-engineering engineer for AI VIDEO generation. The user gives you key frames extracted from a video in chronological order (each frame is prefixed with its index). Turn the video into a production-ready prompt usable in Seedance, Veo, Kling, Wan, Sora or Vidu.\n\nAnalyze these dimensions: 1) Subject & action (identity, appearance, what it does, direction, amplitude, rhythm). 2) Scene & environment (location, background, time of day, weather, set details). 3) Camera movement (push in / pull out / pan / truck / tracking / orbit / crane / handheld / static), speed, start and end framing. 4) Shot size & composition (wide/medium/close-up changes, angle, depth of field, aspect ratio). 5) Lighting & color (source, changes, color temperature, dominant palette, contrast). 6) Style & texture (live action / animation / 3D, cinematic look, film grain, lens flare, VFX). 7) Pacing & mood.\n\nOutput EXACTLY this structure, no extra commentary, no Markdown code fences:\nSubject & Action: ...\nScene & Environment: ...\nCamera Movement: ...\nShot & Composition: ...\nLighting & Color: ...\nStyle & Texture: ...\nPacing & Mood: ...\nFinal Prompt: <one continuous comma-separated paragraph of 60-160 words that fuses all of the above into a ready-to-paste video prompt, explicitly including camera movement and action>\n\nRules: describe only what the frames actually show; infer motion and camera work reasonably but never invent plot. Use precise filmmaking terminology. Output the result directly with no preamble.";
            if($styleNote!=='')$p.="\nAdditional style keywords the user wants honored: ".$styleNote;
            return $p;
        }
        $p="你是一位顶尖的 AI 视频生成提示词逆向工程师。用户会给你一段视频按时间顺序抽取的关键帧（每帧前标注了序号），你需要把这段视频翻译成可直接投喂给 Seedance、Veo、可灵 Kling、Wan、Sora、Vidu 等文生视频模型的高质量提示词。\n\n请逐项分析以下维度：1) 主体与动作：主体身份、外观、在做什么、动作方向与幅度、动作节奏。2) 场景与环境：地点、背景元素、时间、天气、环境细节。3) 运镜方式：推/拉/摇/移/跟拍/环绕/升降/手持/固定，运动速度，起始与结束景别。4) 景别与构图：远景/全景/中景/近景/特写的切换、拍摄角度、景深、画幅比例。5) 光线与色彩：光源方向与性质、光线变化、色温、主色调、对比度。6) 风格与质感：实拍/动画/3D、电影感、胶片颗粒、镜头眩光、特效。7) 节奏与氛围：剪辑节奏、情绪基调、动态强度。\n\n严格按以下结构输出，不要输出多余解释，不要使用 Markdown 代码块：\n主体与动作：...\n场景环境：...\n运镜方式：...\n景别构图：...\n光线色彩：...\n风格质感：...\n节奏氛围：...\n完整提示词：<把以上要素浓缩成一段 80-200 字、逗号分隔、可直接粘贴使用的连贯视频提示词，必须包含运镜与动作描述>\n\n要求：只描述关键帧中真实呈现的内容，动作与运镜做合理推断，绝不臆造剧情；用词具体专业，使用影视行业术语；直接输出结果，不要写开场白。";
        if($styleNote!=='')$p.="\n用户额外希望体现的风格关键词：".$styleNote;
        return $p;
    }
    if($lang==='en'){
        $p="You are a top-tier prompt reverse-engineering engineer for AI IMAGE generation. The user gives you one or more images. Turn the visual content into a production-ready prompt usable in Midjourney, GPT Image, Nano Banana, Seedream, FLUX or Stable Diffusion.\n\nAnalyze these dimensions: 1) Subject (what it is, count, appearance, material, pose, expression). 2) Scene & environment (location, background, time, weather, spatial layers). 3) Composition & viewpoint (shot type, camera angle, depth of field, subject placement, aspect ratio). 4) Lighting (direction and quality, soft/hard, color temperature, highlights and shadows, ambient light). 5) Color (dominant palette, color harmony, saturation, contrast). 6) Style & medium (art movement, photography style, illustration/3D/film/oil painting, platform references). 7) Texture & detail (grain, sharpness, render quality). 8) Mood & atmosphere.\n\nOutput EXACTLY this structure, no extra commentary, no Markdown code fences:\nSubject: ...\nScene: ...\nComposition & Lens: ...\nLighting: ...\nColor: ...\nStyle: ...\nTexture & Detail: ...\nMood: ...\nFinal Prompt: <one continuous comma-separated paragraph of 60-160 words that condenses all of the above into a ready-to-paste image prompt>\n\nRules: describe only what is actually visible; never invent elements that are not in the image. Use specific, professional vocabulary instead of vague praise. If several images are given, merge their shared characteristics into a single prompt. Output the result directly with no preamble.";
        if($styleNote!=='')$p.="\nAdditional style keywords the user wants honored: ".$styleNote;
        return $p;
    }
    $p="你是一位顶尖的 AI 图像生成提示词逆向工程师。用户会给你一张或多张图片，你需要像专业提示词工程师那样，把画面翻译成可直接投喂给 Midjourney、GPT Image、Nano Banana、Seedream、FLUX、Stable Diffusion 等图像模型的高质量提示词。\n\n请逐项分析以下维度：1) 主体：主体是什么、数量、外观细节、材质、姿态动作、表情。2) 场景与环境：地点、背景元素、时间、天气、空间层次。3) 构图与镜头：景别（特写/中景/全景）、拍摄角度（平视/俯视/仰视/航拍）、景深、主体位置、画幅比例。4) 光线：光源方向与性质（顺光/逆光/侧光/伦勃朗光）、软硬、色温、高光与阴影、氛围光。5) 色彩：主色调、配色关系、饱和度、对比度。6) 风格与媒介：艺术流派、摄影风格、插画/3D/胶片/油画、平台或艺术家参考风格。7) 质感与细节：纹理、颗粒、锐度、渲染质量。8) 情绪与氛围。\n\n严格按以下结构输出，不要输出多余解释，不要使用 Markdown 代码块：\n主体：...\n场景：...\n构图与镜头：...\n光线：...\n色彩：...\n风格：...\n细节质感：...\n氛围：...\n完整提示词：<把以上要素浓缩成一段 80-200 字、逗号分隔、可直接粘贴使用的连贯提示词>\n\n要求：只描述画面中真实可见的内容，绝不臆造不存在的元素；用词具体专业，避免空泛形容；若给了多张图片，请综合它们的共同特征输出一条提示词；直接输出结果，不要写开场白。";
    if($styleNote!=='')$p.="\n用户额外希望体现的风格关键词：".$styleNote;
    return $p;
}
function render_prompt_extract_workspace($mode){$isVideo=$mode==='video';$models=prompt_extract_models();$default=prompt_extract_default_model();$title=$isVideo?'视频转提示词':'图片转提示词';$loggedIn=(bool)user();?>
<section class="model-split-workspace pe-workspace">
  <section class="model-compose-pane">
    <div class="model-page-head">
      <h1><?=h($title)?> <em>AI工具箱</em></h1>
      <p><?=$isVideo?'上传视频，AI 逐帧逆向解析运镜、光线与风格，生成可直接使用的视频提示词':'上传图片，AI 逆向解析构图、光线与风格，生成可直接使用的图像提示词'?></p>
    </div>
    <form class="model-compose-form pe-form" data-pe-mode="<?=$mode?>" data-pe-auth="<?=$loggedIn?'1':'0'?>">
<?php if(!$loggedIn):?>
      <div class="login-notice">♟　<strong>登录后开始解析</strong><span>登录后即可使用 AI 逆向解析，解析结果会保留在本地历史记录中。</span><a href="/?page=login">立即登录</a></div>
<?php endif;?>
      <div class="pe-drop" id="peDrop">
        <strong>＋</strong>
        <b><?=$isVideo?'点击或拖放视频文件到此处':'点击或拖放图片文件到此处'?></b>
        <small><?=$isVideo?'支持 MP4 / WebM / MOV，最大 50MB':'支持 JPG / PNG / WebP，最多 4 张，单张最大 10MB'?></small>
        <input type="file" id="peFile" accept="<?=$isVideo?'video/*':'image/*'?>"<?=$isVideo?'':' multiple'?> hidden>
      </div>
      <div class="pe-video-name" id="peMeta" hidden></div>
      <div class="pe-preview" id="pePreview" hidden></div>
      <div class="pe-grid">
        <label class="pe-field">输出语言
          <select id="peLang"><option value="zh" selected>中文</option><option value="en">English</option></select>
        </label>
        <label class="pe-field">分析模型
          <select id="peModel"><?php foreach($models as $pm):?><option value="<?=h($pm['name'])?>"<?=$pm['name']===$default?' selected':''?>><?=h($pm['label'])?></option><?php endforeach;?></select>
        </label>
      </div>
      <label class="pe-field">风格补充（可选）<input type="text" id="peStyle" maxlength="80" placeholder="例如：电影感、赛博朋克、国风写实"></label>
      <div class="upload-tip">⚡ <?=$isVideo?'视频在浏览器本地抽帧后送交 AI 分析，原片不会上传':'图片在浏览器本地压缩后送交 AI 分析，原图不会上传'?></div>
      <div class="model-submit-bar"><button type="submit" class="generate-btn" id="peSubmit" disabled>开始解析</button></div>
    </form>
  </section>
  <section class="model-record-pane">
    <div class="record-head">
      <div><h2>解析结果</h2><p id="peStatus">等待上传素材</p></div>
      <button type="button" class="pe-clear" id="peClear">清空</button>
    </div>
    <div class="record-empty" id="peEmpty"><strong>▧</strong><span>上传<?=$isVideo?'视频':'图片'?>后点击「开始解析」，AI 将生成结构化提示词</span></div>
    <div class="ai-result pe-result" id="peResult"></div>
  </section>
</section>
<script><?php require dirname(__DIR__).'/assets/prompt_extract.js'; ?></script>
<?php }
/* ===== 视频链接解析（去水印 / 文案提取共用） ===== */
function resolve_video_link($url){
    $url=trim((string)$url);
    if($url==='') throw new InvalidArgumentException('请输入视频链接');
    if(!filter_var($url,FILTER_VALIDATE_URL)) throw new InvalidArgumentException('链接格式不正确，请检查后重试');
    $host=parse_url($url,PHP_URL_HOST)?:'';
    // 抖音 / TikTok
    if(preg_match('/(douyin\.com|iesdouyin\.com|tiktok\.com)/i',$host)){
        return resolve_douyin_link($url);
    }
    // 快手
    if(preg_match('/(kuaishou\.com|chenzhongtech\.com)/i',$host)){
        return resolve_kuaishou_link($url);
    }
    // B站
    if(preg_match('/(bilibili\.com|b23\.tv)/i',$host)){
        return resolve_bilibili_link($url);
    }
    // 小红书
    if(preg_match('/(xiaohongshu\.com|xhslink\.com)/i',$host)){
        return resolve_xiaohongshu_link($url);
    }
    // 微博
    if(preg_match('/(weibo\.(com|cn)|t\.cn)/i',$host)){
        return resolve_weibo_link($url);
    }
    // YouTube
    if(preg_match('/(youtube\.com|youtu\.be)/i',$host)){
        return resolve_youtube_link($url);
    }
    // 通用：直接当作视频 URL 尝试
    return ['video_url'=>$url,'title'=>'','cover'=>'','platform'=>'other'];
}
function _resolve_http_get($url,$headers=[],$maxRedirects=8,$timeout=20){
    $ch=curl_init($url);
    $defaultHeaders=['User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1','Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8','Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>$maxRedirects,CURLOPT_TIMEOUT=>$timeout,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_HTTPHEADER=>array_merge($defaultHeaders,$headers),CURLOPT_ENCODING=>'']);
    $body=curl_exec($ch);
    $info=curl_getinfo($ch);
    $err=curl_error($ch);
    curl_close($ch);
    if($err) throw new RuntimeException('网络请求失败：'.$err);
    return ['body'=>$body,'url'=>$info['url']??$url,'http_code'=>(int)($info['http_code']??0)];
}
function _extract_router_data($html){
    // 从 HTML 中提取 window._ROUTER_DATA = {...} 的 JSON（按大括号配平截取，避免非贪婪截断）
    $pos=stripos($html,'_ROUTER_DATA');
    if($pos===false) return null;
    $eq=strpos($html,'=',$pos);
    if($eq===false) return null;
    $start=strpos($html,'{',$eq);
    if($start===false) return null;
    $depth=0;$inStr=false;$esc=false;$len=strlen($html);
    for($i=$start;$i<$len;$i++){
        $ch=$html[$i];
        if($inStr){
            if($esc){$esc=false;}
            elseif($ch==='\\'){$esc=true;}
            elseif($ch==='"'){$inStr=false;}
            continue;
        }
        if($ch==='"'){$inStr=true;continue;}
        if($ch==='{')$depth++;
        elseif($ch==='}'){
            $depth--;
            if($depth===0){
                $json=substr($html,$start,$i-$start+1);
                $data=json_decode($json,true);
                return is_array($data)?$data:null;
            }
        }
    }
    return null;
}
function _douyin_aweme_id($url){
    $url=(string)$url;
    if(preg_match('#/(?:video|note|share/video)/(\d{15,25})#',$url,$m)) return $m[1];
    if(preg_match('#(?:modal_id|aweme_id|item_id|vid)=(\d{15,25})#',$url,$m)) return $m[1];
    if(preg_match('#(\d{18,20})#',$url,$m)) return $m[1];
    return '';
}
function _douyin_pick_video($data){
    // 在 _ROUTER_DATA 中定位视频信息：loaderData -> *video*/page -> videoInfoRes -> item_list[0]
    $candidates=[];
    $ld=$data['loaderData']??null;
    if(is_array($ld)){
        foreach($ld as $key=>$node){
            if(!is_array($node)) continue;
            if(stripos((string)$key,'video')!==false||stripos((string)$key,'note')!==false) $candidates[]=$node;
        }
    }
    $candidates[]=$data;
    foreach($candidates as $node){
        $list=$node['videoInfoRes']['item_list']??null;
        if(!is_array($list)&&isset($node['aweme_detail'])) $list=[$node['aweme_detail']];
        if(!is_array($list)) continue;
        foreach($list as $item){
            if(!is_array($item)) continue;
            $video=$item['video']??[];
            $url='';
            foreach(['play_addr','play_addr_lowbr','download_addr','bit_rate'] as $k){
                if(!isset($video[$k])) continue;
                if($k==='bit_rate'&&is_array($video[$k])){
                    foreach($video[$k] as $br){
                        $u=$br['play_addr']['url_list'][0]??'';
                        if($u!==''){$url=$u;break 2;}
                    }
                    continue;
                }
                $u=$video[$k]['url_list'][0]??'';
                if($u!==''){$url=$u;break;}
            }
            if($url==='') continue;
            $cover=$video['cover']['url_list'][0]??($video['origin_cover']['url_list'][0]??'');
            $title=$item['desc']??($item['title']??'');
            return ['url'=>$url,'title'=>$title,'cover'=>$cover];
        }
    }
    return null;
}
function resolve_douyin_link($url){
    $html='';$finalUrl=$url;$awemeId='';
    // Step 1: 跟随分享短链，拿到真实地址与 aweme_id
    try{
        $res=_resolve_http_get($url,[],8,20);
        $html=$res['body'];$finalUrl=$res['url'];
        $awemeId=_douyin_aweme_id($finalUrl);
        if($awemeId==='') $awemeId=_douyin_aweme_id($html);
    }catch(Throwable $e){
        $awemeId=_douyin_aweme_id($url);
        if($awemeId==='') throw new RuntimeException('无法访问该抖音链接，请检查链接是否正确');
    }
    if($awemeId==='') throw new RuntimeException('未能识别视频 ID，请使用抖音 App「分享 → 复制链接」得到的完整链接');
    $videoUrl='';$title='';$cover='';
    // Step 2: 解析分享页 _ROUTER_DATA（当前抖音网页端主要数据来源）
    $shareUrls=[
        'https://www.iesdouyin.com/share/video/'.$awemeId.'/',
        'https://www.douyin.com/video/'.$awemeId,
    ];
    foreach($shareUrls as $su){
        try{
            $r=_resolve_http_get($su,['Referer: https://www.douyin.com/'],5,20);
            $data=_extract_router_data($r['body']);
            if($data){
                $picked=_douyin_pick_video($data);
                if($picked){$videoUrl=$picked['url'];$title=$picked['title'];$cover=$picked['cover'];break;}
            }
            // 兜底：直接从 HTML 抓取 play_addr / playApi
            if(preg_match('#"playApi"\s*:\s*"([^"]+)"#',$r['body'],$m)){
                $videoUrl=str_replace(['\\u002F','\/'],['/','/'],$m[1]);break;
            }
            if(preg_match('#"play_addr"\s*:\s*\{[^}]*"url_list"\s*:\s*\[\s*"([^"]+)"#',$r['body'],$m)){
                $videoUrl=str_replace(['\\u002F','\/'],['/','/'],$m[1]);break;
            }
            if(!$title&&preg_match('#<title>([^<]+)</title>#',$r['body'],$m)){
                $title=trim(html_entity_decode(strip_tags($m[1]),ENT_QUOTES,'UTF-8'));
            }
        }catch(Throwable $e){/* 尝试下一个入口 */}
    }
    if(!$videoUrl) throw new RuntimeException('无法解析该抖音视频，可能视频已删除、设为私密或需要登录，请更换链接重试');
    // Step 3: 去水印（CDN 路径 playwm -> play，并清理水印参数）
    $videoUrl=str_replace(['/playwm/','/playwm?','&watermark=1','&watermark=0','ratio=720p'],['/play/','/play?','','',''],$videoUrl);
    $videoUrl=html_entity_decode($videoUrl,ENT_QUOTES,'UTF-8');
    if($title==='') $title='抖音视频 '.$awemeId;
    return ['video_url'=>$videoUrl,'title'=>$title,'cover'=>$cover,'platform'=>'douyin','aweme_id'=>$awemeId];
}
function _find_video_in_data($data,$depth=0){
    if($depth>12||!is_array($data)) return null;
    // 检查当前层级是否有 play_addr / playAddr
    foreach(['play_addr','playAddr','play_api','playApi','download_addr','downloadAddr'] as $key){
        if(isset($data[$key])){
            $node=$data[$key];
            $url='';
            if(is_string($node)) $url=$node;
            elseif(is_array($node)){
                $url=$node['url_list'][0]??$node['uri']??$node['url']??'';
            }
            if($url!==''){
                $url=str_replace('\\u002F','/',$url);
                $title=$data['desc']??$data['title']??'';
                $cover='';
                if(isset($data['video']['cover']['url_list'][0])) $cover=$data['video']['cover']['url_list'][0];
                elseif(isset($data['cover'])) $cover=$data['cover'];
                return ['url'=>$url,'title'=>$title,'cover'=>$cover];
            }
        }
    }
    // 递归子节点
    foreach($data as $v){
        if(is_array($v)){
            $r=_find_video_in_data($v,$depth+1);
            if($r) return $r;
        }
    }
    return null;
}
function resolve_kuaishou_link($url){
    $res=_resolve_http_get($url,['Referer: https://www.kuaishou.com/']);
    $html=$res['body'];
    $videoUrl='';$title='';$cover='';
    if(preg_match('#"playUrl"\s*:\s*"([^"]+)"#',$html,$m)){
        $videoUrl=str_replace('\\u002F','/',$m[1]);
    }
    if(!$videoUrl && preg_match('#"srcNoMark"\s*:\s*"([^"]+)"#',$html,$m)){
        $videoUrl=str_replace('\\u002F','/',$m[1]);
    }
    if(preg_match('#"caption"\s*:\s*"([^"]*)"#',$html,$m)) $title=$m[1];
    if(preg_match('#"poster"\s*:\s*"([^"]+)"#',$html,$m)) $cover=str_replace('\\u002F','/',$m[1]);
    if(!$videoUrl) throw new RuntimeException('无法解析该快手视频链接，请确认链接正确');
    return ['video_url'=>$videoUrl,'title'=>$title,'cover'=>$cover,'platform'=>'kuaishou'];
}
function resolve_bilibili_link($url){
    $res=_resolve_http_get($url,['Referer: https://www.bilibili.com/']);
    $html=$res['body'];
    $title='';$cover='';
    if(preg_match('#<title>([^<]+)</title>#',$html,$m)) $title=trim(strip_tags($m[1]));
    if(preg_match('#"pic"\s*:\s*"([^"]+)"#',$html,$m)) $cover=str_replace('\\u002F','/',$m[1]);
    // B站需要额外API获取视频流，这里返回页面信息让前端处理
    // 简化：返回原始链接作为占位，提示用户B站暂不支持自动解析
    throw new RuntimeException('B站视频解析暂未支持，请使用抖音、快手、小红书等平台的分享链接');
}
function resolve_xiaohongshu_link($url){
    $res=_resolve_http_get($url,['Referer: https://www.xiaohongshu.com/']);
    $html=$res['body'];
    $videoUrl='';$title='';$cover='';
    if(preg_match('#"originVideoKey"\s*:\s*"([^"]+)"#',$html,$m)){
        $videoUrl='https://sns-video-bd.xhscdn.com/'.$m[1];
    }
    if(!$videoUrl && preg_match('#"videoUrl"\s*:\s*"([^"]+)"#',$html,$m)){
        $videoUrl=str_replace('\\u002F','/',$m[1]);
    }
    if(preg_match('#"title"\s*:\s*"([^"]*)"#',$html,$m)) $title=$m[1];
    if(preg_match('#"cover"\s*:\s*"([^"]+)"#',$html,$m)) $cover=str_replace('\\u002F','/',$m[1]);
    if(!$videoUrl) throw new RuntimeException('无法解析该小红书视频链接，请确认链接正确且包含视频内容');
    return ['video_url'=>$videoUrl,'title'=>$title,'cover'=>$cover,'platform'=>'xiaohongshu'];
}
function resolve_weibo_link($url){
    $res=_resolve_http_get($url,['Referer: https://weibo.com/']);
    $html=$res['body'];
    $videoUrl='';
    if(preg_match('#"stream_url_hd"\s*:\s*"([^"]+)"#',$html,$m)){
        $videoUrl=str_replace('\\u002F','/',$m[1]);
    }
    if(!$videoUrl && preg_match('#"stream_url"\s*:\s*"([^"]+)"#',$html,$m)){
        $videoUrl=str_replace('\\u002F','/',$m[1]);
    }
    if(!$videoUrl) throw new RuntimeException('无法解析该微博视频链接');
    return ['video_url'=>$videoUrl,'title'=>'','cover'=>'','platform'=>'weibo'];
}
function resolve_youtube_link($url){
    throw new RuntimeException('YouTube 视频解析暂未支持，请使用国内平台（抖音、快手、小红书等）的分享链接');
}
function transcript_gemini_models(){
    static $list=null;if($list!==null)return $list;
    $preferred=['gem-3.8-flash','gem-3.7-flash','gem-3.6-flash','gem-3.5-flash','gem-3.1-flash','gem-3-flash'];
    $hit=[];$rest=[];
    foreach(raw_models('chat') as $m){
        $name=trim((string)($m['name']??''));if($name==='')continue;
        if(($m['api_format']??'')!=='gemini')continue;
        if(array_key_exists('available_for_this_key',$m)&&!$m['available_for_this_key'])continue;
        $tags=$m['tags']??[];
        if(!is_array($tags)||!in_array('多模态',$tags,true))continue;
        $item=['name'=>$name,'label'=>(trim((string)($m['display_name']??''))?:$name)];
        if(in_array($name,$preferred,true))$hit[$name]=$item;else $rest[$name]=$item;
    }
    $out=[];foreach($preferred as $p){if(isset($hit[$p]))$out[]=$hit[$p];}
    foreach($rest as $r){if(count($out)>=12)break;$out[]=$r;}
    if(!$out)$out=[['name'=>'gem-3.8-flash','label'=>'GEM 3.8 flash']];
    return $list=$out;
}
function render_watermark_remove_workspace(){$loggedIn=(bool)user();?>
<section class="model-split-workspace pe-workspace">
  <section class="model-compose-pane">
    <div class="model-page-head">
      <h1>无痕无水印 <em>AI工具箱</em></h1>
      <p>粘贴短视频分享链接，一键解析高清无水印视频并下载保存</p>
    </div>
    <form class="model-compose-form wm-form" data-wm-auth="<?=$loggedIn?'1':'0'?>">
<?php if(!$loggedIn):?>
      <div class="login-notice">♟ <strong>登录后开始解析</strong><span>登录后可使用视频解析与下载功能。</span><a href="/?page=login">立即登录</a></div>
<?php endif;?>
      <label class="pe-field">视频链接<input type="url" id="wmUrl" placeholder="粘贴抖音、快手、小红书等平台的分享链接" required autocomplete="off"></label>
      <div class="upload-tip">⚡ 支持抖音、快手、小红书、微博等平台；解析在服务器端完成，不会保存您的链接</div>
      <div class="model-submit-bar"><button type="submit" class="generate-btn" id="wmSubmit" disabled>解析视频</button></div>
    </form>
  </section>
  <section class="model-record-pane">
    <div class="record-head">
      <div><h2>解析结果</h2><p id="wmStatus">等待输入链接</p></div>
      <button type="button" class="pe-clear" id="wmClear">清空</button>
    </div>
    <div class="record-empty" id="wmEmpty"><strong>▧</strong><span>粘贴视频分享链接后点击「解析视频」，即可获取无水印高清视频</span></div>
    <div class="ai-result pe-result" id="wmResult"></div>
  </section>
</section>
<script><?php require dirname(__DIR__).'/assets/watermark_remove.js'; ?></script>
<?php }
function render_video_transcript_workspace(){$loggedIn=(bool)user();$models=transcript_gemini_models();$default=$models[0]['name']??'gem-3.8-flash';?>
<section class="model-split-workspace pe-workspace">
  <section class="model-compose-pane">
    <div class="model-page-head">
      <h1>视频文案提取 <em>AI工具箱</em></h1>
      <p>粘贴视频链接，AI 自动识别语音内容并生成完整文案，支持多平台</p>
    </div>
    <form class="model-compose-form vt-form" data-vt-auth="<?=$loggedIn?'1':'0'?>">
<?php if(!$loggedIn):?>
      <div class="login-notice">♟ <strong>登录后开始提取</strong><span>登录后可使用 AI 视频文案提取功能。</span><a href="/?page=login">立即登录</a></div>
<?php endif;?>
      <label class="pe-field">视频链接<input type="url" id="vtUrl" placeholder="粘贴抖音、快手、小红书等平台的分享链接" required autocomplete="off"></label>
      <div class="pe-grid">
        <label class="pe-field">输出语言<select id="vtLang"><option value="zh" selected>中文</option><option value="en">English</option></select></label>
        <label class="pe-field">识别模型<select id="vtModel"><?php foreach($models as $pm):?><option value="<?=h($pm['name'])?>"<?=$pm['name']===$default?' selected':''?>><?=h($pm['label'])?></option><?php endforeach;?></select></label>
      </div>
      <div class="upload-tip">⚡ AI 将自动识别视频中的语音内容并转为文字，适合口播、教程、访谈类视频</div>
      <div class="model-submit-bar"><button type="submit" class="generate-btn" id="vtSubmit" disabled>提取文案</button></div>
    </form>
  </section>
  <section class="model-record-pane">
    <div class="record-head">
      <div><h2>提取结果</h2><p id="vtStatus">等待输入链接</p></div>
      <button type="button" class="pe-clear" id="vtClear">清空</button>
    </div>
    <div class="record-empty" id="vtEmpty"><strong>▧</strong><span>粘贴视频链接后点击「提取文案」，AI 将识别语音并生成文字稿</span></div>
    <div class="ai-result pe-result" id="vtResult"></div>
  </section>
</section>
<script><?php require dirname(__DIR__).'/assets/video_transcript.js'; ?></script>
<?php }
function render_toolbox_submenu($cur){$current=$_GET['tool']??'';if($current===''&&in_array($cur,['video2prompt','image2prompt','watermark-remove','video-transcript'],true))$current=$cur;?><div class="clone-submenu toolbox-submenu <?=($cur==='apps'||$cur==='tool'||in_array($cur,['video2prompt','image2prompt','watermark-remove','video-transcript'],true))?'open':''?>"><?php foreach(toolbox_items() as $it):?><a class="<?=$current===$it['key']?'active':''?>" href="<?=h($it['url'])?>"><i><?=h($it['icon'])?></i><span><b><?=h($it['label'])?></b><small><?=h($it['desc'])?></small></span></a><?php endforeach;?></div><?php }
 function toolbox_item($key){foreach(toolbox_items() as $it){if($it['key']===$key)return $it;}return toolbox_items()[0];}
 function render_agent_submenu($cur){$current=$_GET['agent']??'ecommerce-image';$agents=agent_configs();?><div class="clone-submenu agent-submenu <?=$cur==='agents'?'open':''?>"><?php foreach($agents as $agent):?><a class="<?=$cur==='agents'&&$current===$agent['agent_key']?'active':''?>" href="/?page=agents&agent=<?=urlencode($agent['agent_key'])?>"><i><?=h($agent['icon'])?></i><span><b><?=h($agent['name'])?></b><small><?=h($agent['subtitle'])?></small></span></a><?php endforeach;?></div><?php }
function model_family($type,$key){$list=model_families($type);foreach($list as $f){if($f['key']===$key||$f['model']===$key)return $f;}return $list[0];}
function render_model_submenu($type,$cur){$families=model_families($type);$current=$_GET['model']??'';$page=$type==='chat'?'text':$type;$open=($type==='chat'&&$cur==='text')||$cur===$type;?><div class="clone-submenu <?=$open?'open':''?>"><?php foreach($families as $f):?><a class="<?=$open&&($current===$f['key']||$current===$f['model'])?'active':''?>" href="/?page=<?=$page?>&model=<?=h($f['key'])?>"><i><?=h($f['icon'])?></i><span><b><?=h($f['label'])?></b><small><?=h($f['desc'])?></small></span></a><?php endforeach;?></div><?php }
function layout_start($title='灵创AI', $activeNav='', $opts=[]){
  if(is_array($activeNav)){$opts=$activeNav;$activeNav='';}
  global $config;$u=user();$f=take_flash();$site=site_settings();
  $announcement = ($u && !empty($_GET['login_notice'])) ? user_unread_announcement((int)$u['id']) : null;
  $showCheckin=$u?should_show_daily_checkin((int)$u['id']):false;
  $cur=!empty($_GET['page'])?$_GET['page']:'home';
  $menus=site_menus();
  $isVip=!empty($u['vip_until'])&&strtotime($u['vip_until'])>time();
  $avatarUrl=!empty($u['avatar'])?$u['avatar']:'';
  $displayName=!empty($u['nickname'])?$u['nickname']:($u['username']??'');
  $siteLogo=!empty($site['site_logo'])?$site['site_logo']:'';
  $noSidebar = !empty($opts['no_sidebar']) || !empty($opts['standalone']) || !empty($opts['bare']);
  $noTopbar = !empty($opts['no_topbar']) || !empty($opts['bare']);
  if(ob_get_level()===0) ob_start();?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> · <?=h($site['site_name'])?></title><?php if($siteLogo):?><link rel="icon" href="<?=h($siteLogo)?>"><?php endif;?><script>(function(){try{if(localStorage.getItem('lingchuang_theme')==='light'){document.documentElement.classList.add('clone-light');}}catch(e){}})();</script><link rel="stylesheet" href="/assets/app.css?v=2026091203"><script defer src="/assets/app.js?v=2026091203"></script><style><?=file_get_contents(dirname(__DIR__).'/assets/app.css')?></style></head><body class="app-body clone-shell"><script>(function(){try{if(localStorage.getItem('lingchuang_theme')==='light'){document.body.classList.add('clone-light');}}catch(e){}})();</script>
<?php if(!$noSidebar): ?>
<aside class="clone-sidebar">
  <a class="clone-logo" href="/">
    <?php if($siteLogo): ?>
      <img class="clone-logo-img" src="<?=h($siteLogo)?>" alt="<?=h($site['site_name'])?>">
    <?php else: ?>
      <b><?=h($site['logo_text'])?></b>
    <?php endif; ?>
    <span><strong><?=h($site['site_name'])?></strong><small><?=h($site['site_subtitle'])?></small></span>
  </a>

  <nav class="clone-menu"><?php foreach($menus as $m):$pk=$m['page_key']?:page_from_url($m['url']);?><a class="<?=($pk===$cur||($pk==='apps'&&in_array($cur,['tool','video2prompt','image2prompt','watermark-remove','video-transcript'],true)))?'active ':''?><?=($pk==='text'||$pk==='video'||$pk==='image'||$pk==='audio'||$pk==='apps'||$pk==='agents')?'has-submenu expanded':''?>" href="<?=($pk==='text'||$pk==='video'||$pk==='image'||$pk==='audio'||$pk==='apps'||$pk==='agents')?'javascript:void(0)':h($m['url']?:'/')?>"><i><?=h($m['icon']?:'□')?></i><span><b><?=h($m['label'])?></b><?php if(!empty($m['subtitle'])):?><small><?=h($m['subtitle'])?></small><?php endif;?><?php if(!empty($m['badge'])):?><em><?=h($m['badge'])?></em><?php endif;?></span><?php if($pk==='text'||$pk==='video'||$pk==='image'||$pk==='audio'||$pk==='apps'||$pk==='agents'):?><strong class="clone-menu-arrow">⌄</strong><?php endif;?></a><?php if($pk==='text')render_model_submenu('chat',$cur);elseif($pk==='video')render_model_submenu('video',$cur);elseif($pk==='image')render_model_submenu('image',$cur);elseif($pk==='audio')render_model_submenu('audio',$cur);elseif($pk==='apps')render_toolbox_submenu($cur);elseif($pk==='agents')render_agent_submenu($cur);?><?php endforeach;?></nav><div class="clone-service"><div><i>▣</i><span><b><?=h($site['service_title'])?></b><small><?=h($site['service_subtitle'])?></small></span></div><a href="<?=h($site['service_url']?:'/?page=service')?>"><?=h($site['service_button'])?></a></div></aside>
<?php endif; ?>
<section class="clone-page <?=$noSidebar?'no-sidebar page-bare':''?>">
<?php if(!$noTopbar): 
  $latestAnn = latest_system_announcement();
  $popularKeywords = ['电商主图', '人像写真', '电影感视频', '治愈插画', 'Logo设计'];
?>
<header class="clone-topbar">
  <div class="topbar-center-area">
    <!-- 全局智能搜索框 -->
    <form class="topbar-search-form" id="topbarSearchForm" action="/" method="GET" onsubmit="return handleTopbarSearch(event, this)">
      <div class="topbar-search-wrapper">
        <svg class="topbar-search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" name="q" id="topbarSearchInput" class="topbar-search-input" placeholder="搜索全站提示词、功能模型与灵感..." autocomplete="off">
        <kbd class="topbar-search-kbd">Ctrl K</kbd>
      </div>
    </form>

    <!-- 热门提示词胶囊与最新公告 -->
    <div class="topbar-capsules-bar">
      <?php if($latestAnn): ?>
        <button type="button" class="topbar-ann-pill" onclick="openSystemAnnouncementModalDirect()" title="<?=h($latestAnn['title'])?>">
          <span class="ann-pill-badge">📢 公告</span>
          <span class="ann-pill-text"><?=h($latestAnn['title'])?></span>
        </button>
      <?php endif; ?>

      <div class="topbar-hot-tags">
        <span class="hot-tags-label">🔥 热门：</span>
        <?php foreach($popularKeywords as $kw): ?>
          <a href="javascript:void(0)" class="topbar-tag-pill" onclick="quickFillSearch('<?=h($kw)?>')"><?=h($kw)?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="clone-top-actions"><button type="button" id="globalThemeToggleBtn" title="切换明暗模式" onclick="(function(btn){
  var isLight = document.body.classList.toggle('clone-light');
  document.documentElement.classList.toggle('clone-light', isLight);
  try{localStorage.setItem('lingchuang_theme', isLight?'light':'dark');}catch(e){}
  btn.textContent = isLight ? '☾' : '☼';
  btn.title = isLight ? '切换为暗黑模式' : '切换为明亮模式';
})(this)">☼</button><a class="clone-points" href="/?page=profile#points-section"><i class="clone-points-icon">◎</i><b class="clone-points-num"><?=h($u ? $u['points'] : ($site['points_label']?:'--'))?></b><span class="clone-points-unit">积分</span></a><?php if($u):?>
  <!-- 右上角账号设置下拉菜单 -->
  <div class="topbar-user-profile-box" id="topbarUserProfileBox">
    <div class="topbar-user-trigger" onclick="toggleTopUserDropdown(event)">
      <div class="topbar-user-avatar">
        <?php if($avatarUrl): ?>
          <img src="<?=h($avatarUrl)?>" alt="avatar">
        <?php else: ?>
          <span><?=mb_substr($displayName, 0, 1, 'UTF-8')?></span>
        <?php endif; ?>
        <?php if($isVip): ?><span class="top-vip-crown" title="VIP会员">👑</span><?php endif; ?>
      </div>
      <div class="topbar-user-info">
        <b><?=h($displayName)?></b>
        <small><?=$isVip ? '👑 VIP 会员' : '普通创作者'?></small>
      </div>
      <i class="topbar-user-arrow">▾</i>
    </div>
    
    <div class="topbar-user-dropdown" id="topbarUserDropdown" style="display:none;">
      <a href="/?page=profile" class="user-sb-item">
        <span class="user-sb-item-icon">👤</span>
        <div>
          <strong>个人中心</strong>
          <small>账号信息与资料编辑</small>
        </div>
      </a>
      <a href="/?page=profile#vip-section" class="user-sb-item">
        <span class="user-sb-item-icon">👑</span>
        <div>
          <strong>会员升级</strong>
          <small>开通专属尊享特权</small>
        </div>
      </a>
      <a href="/?page=profile#points-section" class="user-sb-item">
        <span class="user-sb-item-icon">💎</span>
        <div>
          <strong>积分包购买</strong>
          <small>创作积分即时充值</small>
        </div>
      </a>
      <hr class="user-sb-divider">
      <form method="post" action="/" style="margin:0;">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="user-sb-item user-sb-logout">
          <span class="user-sb-item-icon">🚪</span>
          <div>
            <strong>退出登录</strong>
            <small>安全退出当前账号</small>
          </div>
        </button>
      </form>
    </div>
  </div>
  <script>
  function toggleTopUserDropdown(e){
    e.stopPropagation();
    var dd = document.getElementById('topbarUserDropdown');
    if(dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
  }
  document.addEventListener('click', function(e){
    var box = document.getElementById('topbarUserProfileBox');
    var dd = document.getElementById('topbarUserDropdown');
    if(dd && box && !box.contains(e.target)){
      dd.style.display = 'none';
    }
  });
  </script>
<?php else:?><a class="clone-login" href="/?page=login">登录</a><?php endif;?></div></header>
<?php endif; ?><?php if(!empty($site['site_notice'])):?><div class="flash info"><?=h($site['site_notice'])?></div><?php endif;?><?php if($f):?><div class="flash <?=h($f[1])?>"><?=h($f[0])?></div><?php endif;?><?php if($u&&$showCheckin):?>
<div id="dailyCheckinModal" class="daily-checkin-modal">
  <div class="daily-checkin-backdrop" onclick="(function(el){el.closest('.daily-checkin-modal').remove();})(this)"></div>
  <div class="daily-checkin-card" style="position:relative;z-index:10000;">
    <button type="button" class="daily-checkin-close" onclick="(function(el){el.closest('.daily-checkin-modal').remove();})(this)">×</button>
    <div class="daily-checkin-icon">✦</div>
    <div class="daily-checkin-kicker">每日签到</div>
    <h2>今天也来签到吧</h2>
    <p>每日签到可获得积分奖励，连续签到奖励更多</p>
    <div class="daily-checkin-reward">+1 积分</div>
    <button type="button" class="daily-checkin-btn" id="dailyCheckinBtn" onclick="(function(btn){
      btn.disabled=true;
      btn.textContent='签到中…';
      fetch('/api.php?action=daily-checkin',{headers:{Accept:'application/json'}})
        .then(function(r){return r.json();})
        .then(function(x){
          if(!x.ok) throw new Error(x.message||'签到失败');
          btn.textContent='已签到 +'+(x.data.reward_points||1)+' 积分';
          btn.classList.add('done');
          var topPts = document.querySelector('.clone-points');
          if (topPts && x.data && x.data.points_after !== undefined) {
            topPts.textContent = '◎ ' + x.data.points_after + ' 积分';
          }
          setTimeout(function(){
            var m=btn.closest('.daily-checkin-modal');
            if(m)m.remove();
          },900);
        })
        .catch(function(e){
          btn.disabled=false;
          btn.textContent='立即签到';
          alert(e.message||'签到失败，请稍后重试');
        });
    })(this)">立即签到</button>
    <div class="daily-checkin-note">签到后积分将自动到账</div>
  </div>
</div>
<?php endif;?><?php 
$allAnnouncements = system_announcements(true);
$latestActiveAnn = $allAnnouncements[0] ?? null;
$showAnnDirect = ($u && $announcement);
if($latestActiveAnn):
  $curAnn = ($showAnnDirect && $announcement) ? $announcement : $latestActiveAnn;
  $curIndex = 0;
  foreach($allAnnouncements as $idx=>$item){
    if((int)$item['id']===(int)$curAnn['id']){$curIndex=$idx;break;}
  }
?>
<div id="systemAnnouncementModal" class="system-announcement-modal" <?=$showAnnDirect?'':'style="display:none;"'?>>
  <div class="system-announcement-backdrop" onclick="(function(el){var m=el.closest('.system-announcement-modal');if(m)m.style.display='none';})(this)"></div>
  <div class="system-announcement-card" style="position:relative;z-index:10000;">
    <button type="button" class="system-announcement-close" onclick="(function(el){var m=el.closest('.system-announcement-modal');if(m)m.style.display='none';})(this)">✕</button>
    <div class="system-announcement-head">
      <div class="system-announcement-bell">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22ZM18 16V11C18 7.93 16.37 5.36 13.5 4.68V4C13.5 3.17 12.83 2.5 12 2.5C11.17 2.5 10.5 3.17 10.5 4V4.68C7.64 5.36 6 7.92 6 11V16L4 18V19H20V18L18 16ZM16 17H8V11C8 8.52 9.51 6.5 12 6.5C14.49 6.5 16 8.52 16 11V17Z" fill="#fbbf24"/>
        </svg>
      </div>
      <div class="system-announcement-title-wrap">
        <h2>系统公告 <span style="font-size:12px;opacity:0.6;font-weight:400"><?=h($curAnn['category']??'系统通知')?></span></h2>
        <div class="system-announcement-time">发布时间：<?=h($curAnn['published_at'])?> <span class="ann-star">✦</span></div>
      </div>
    </div>
    <div class="system-announcement-body">
      <!-- 顶部高亮导语条 -->
      <div class="ann-hero-banner">
        <div class="ann-hero-kicker">LATEST · 这一轮你能摸到的</div>
        <p><?=h($curAnn['title'])?></p>
      </div>

      <!-- 公告主体内容渲染 -->
      <div class="ann-content-render">
        <?=render_rich_announcement_content($curAnn['content'])?>
      </div>
    </div>
    <div class="system-announcement-foot">
      <button type="button" class="ann-nav-btn" <?=$curIndex<count($allAnnouncements)-1?'':'disabled'?> onclick="switchAnnouncement(<?=$curIndex+1?>)">‹ 上一条</button>
      <button type="button" class="ann-nav-btn latest-btn" onclick="(function(el){var m=el.closest('.system-announcement-modal');if(m)m.style.display='none';})(this)">♟ 最新 (我知道了)</button>
      <button type="button" class="ann-nav-btn" <?=$curIndex>0?'':'disabled'?> onclick="switchAnnouncement(<?=$curIndex-1?>)">下一条 ›</button>
    </div>
  </div>
</div>
<script>
window.__announcementsData = <?=json_encode($allAnnouncements, JSON_UNESCAPED_UNICODE)?>;
function switchAnnouncement(idx){
  var list = window.__announcementsData;
  if(!list || !list[idx]) return;
  var item = list[idx];
  var m = document.getElementById('systemAnnouncementModal');
  if(!m) return;
  var timeEl = m.querySelector('.system-announcement-time');
  if(timeEl) timeEl.innerHTML = '发布时间：' + item.published_at + ' <span class="ann-star">✦</span>';
  var bannerP = m.querySelector('.ann-hero-banner p');
  if(bannerP) bannerP.textContent = item.title;
  var renderBox = m.querySelector('.ann-content-render');
  if(renderBox) {
    fetch('/api.php?action=render-announcement&id=' + item.id)
      .then(function(r){return r.json();})
      .then(function(d){if(d && d.ok && d.data && d.data.html) renderBox.innerHTML = d.data.html;})
      .catch(function(){
        renderBox.innerHTML = '<div class="ann-card"><p>' + (item.content||'').replace(/\n/g, '<br>') + '</p></div>';
      });
  }
  var footBtns = m.querySelectorAll('.system-announcement-foot .ann-nav-btn');
  if(footBtns[0]) {
    footBtns[0].disabled = (idx >= list.length - 1);
    footBtns[0].setAttribute('onclick', 'switchAnnouncement(' + (idx + 1) + ')');
  }
  if(footBtns[2]) {
    footBtns[2].disabled = (idx <= 0);
    footBtns[2].setAttribute('onclick', 'switchAnnouncement(' + (idx - 1) + ')');
  }
}
</script>
<?php endif;?><main class="clone-main"><?php }
function parse_friend_links($text){
  $text = trim((string)$text);
  if($text === '') return [];
  $lines = preg_split('/[\r\n]+/', $text);
  $links = [];
  foreach($lines as $line){
    $line = trim($line);
    if($line === '') continue;
    $name = '';
    $url = '';
    if(strpos($line, '|') !== false){
      $parts = explode('|', $line, 2);
      $name = trim($parts[0]);
      $url = trim($parts[1]);
    } elseif(strpos($line, '｜') !== false){
      $parts = explode('｜', $line, 2);
      $name = trim($parts[0]);
      $url = trim($parts[1]);
    } elseif(preg_match('/^(\S+)\s+(\S+)$/u', $line, $matches)){
      $name = trim($matches[1]);
      $url = trim($matches[2]);
    } else {
      $name = $line;
      $url = $line;
    }
    if($name !== '' && $url !== ''){
      $links[] = ['name' => $name, 'url' => $url];
    }
  }
  return $links;
}
function layout_end($opts = []){
  $bare = !empty($opts['bare']) || (!empty($GLOBALS['opts']) && !empty($GLOBALS['opts']['bare']));
  if(!$bare):
    $site = site_settings();
    $copyright = trim((string)($site['footer_copyright'] ?? ''));
    $icp = trim((string)($site['footer_icp'] ?? ''));
    $icpUrl = trim((string)($site['footer_icp_url'] ?? ''));
    $police = trim((string)($site['footer_police_beian'] ?? ''));
    $policeUrl = trim((string)($site['footer_police_url'] ?? ''));
    $email = trim((string)($site['footer_contact_email'] ?? ''));
    $phone = trim((string)($site['footer_contact_phone'] ?? ''));
    $aboutUrl = trim((string)($site['footer_about_url'] ?? ''));
    $privacyUrl = trim((string)($site['footer_privacy_url'] ?? ''));
    $termsUrl = trim((string)($site['footer_terms_url'] ?? ''));
    $friendLinks = parse_friend_links($site['footer_friend_links'] ?? '');

    $hasMeta = ($copyright !== '' || $icp !== '' || $police !== '' || $email !== '' || $phone !== '' || $aboutUrl !== '' || $privacyUrl !== '' || $termsUrl !== '');
    $hasLinks = !empty($friendLinks);
    if($hasMeta || $hasLinks):
?>
<footer class="site-footer">
  <div class="site-footer-inner">
    <?php if($hasLinks): ?>
    <div class="site-footer-links">
      <span class="site-footer-links-label">友情链接：</span>
      <div class="site-footer-links-list">
        <?php foreach($friendLinks as $fl): ?>
          <a href="<?=h($fl['url'])?>" target="_blank" rel="noopener nofollow" class="site-footer-link-item"><?=h($fl['name'])?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if($hasMeta): ?>
    <div class="site-footer-meta">
      <?php if($copyright): ?>
        <span class="site-footer-item site-footer-copy"><?=h($copyright)?></span>
      <?php endif; ?>

      <?php if($icp): ?>
        <span class="site-footer-item site-footer-icp">
          <?php if($icpUrl): ?>
            <a href="<?=h($icpUrl)?>" target="_blank" rel="noopener nofollow"><?=h($icp)?></a>
          <?php else: ?>
            <?=h($icp)?>
          <?php endif; ?>
        </span>
      <?php endif; ?>

      <?php if($police): ?>
        <span class="site-footer-item site-footer-police">
          <svg class="police-icon" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.7c0 4.67-3.13 9.04-7 10.18-3.87-1.14-7-5.51-7-10.18V6.3l7-3.12z"/></svg>
          <?php if($policeUrl): ?>
            <a href="<?=h($policeUrl)?>" target="_blank" rel="noopener nofollow"><?=h($police)?></a>
          <?php else: ?>
            <?=h($police)?>
          <?php endif; ?>
        </span>
      <?php endif; ?>

      <?php if($aboutUrl): ?>
        <span class="site-footer-item"><a href="<?=h($aboutUrl)?>">关于我们</a></span>
      <?php endif; ?>

      <?php if($privacyUrl): ?>
        <span class="site-footer-item"><a href="<?=h($privacyUrl)?>">隐私政策</a></span>
      <?php endif; ?>

      <?php if($termsUrl): ?>
        <span class="site-footer-item"><a href="<?=h($termsUrl)?>">服务条款</a></span>
      <?php endif; ?>

      <?php if($email): ?>
        <span class="site-footer-item site-footer-contact">联系邮箱: <a href="mailto:<?=h($email)?>"><?=h($email)?></a></span>
      <?php endif; ?>

      <?php if($phone): ?>
        <span class="site-footer-item site-footer-contact">客服电话: <?=h($phone)?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</footer>
<?php endif; ?>
<?php endif; ?>
</main></section></body></html><?php }
if(!empty($config['db']['pass'])){try{ $p=db(); $p->exec("CREATE TABLE IF NOT EXISTS users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(60) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,points INT NOT NULL DEFAULT 100,status TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS generations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,type VARCHAR(20) NOT NULL,prompt TEXT NOT NULL,result_url VARCHAR(500) DEFAULT NULL,status VARCHAR(20) NOT NULL DEFAULT 'completed',cost INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,INDEX(user_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS admin_users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(60) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS prompts(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type VARCHAR(20) NOT NULL DEFAULT 'image',cat VARCHAR(60) NOT NULL DEFAULT '',title VARCHAR(120) NOT NULL,text TEXT NOT NULL,sort_order INT NOT NULL DEFAULT 0,enabled TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(type),INDEX(enabled)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS site_menus(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(80) NOT NULL,subtitle VARCHAR(160) NOT NULL DEFAULT '',icon VARCHAR(20) NOT NULL DEFAULT '□',url VARCHAR(255) NOT NULL DEFAULT '/',page_key VARCHAR(40) NOT NULL DEFAULT '',badge VARCHAR(40) NOT NULL DEFAULT '',sort_order INT NOT NULL DEFAULT 0,enabled TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(enabled),INDEX(sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS home_blocks(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,kind VARCHAR(20) NOT NULL DEFAULT 'ad',title VARCHAR(120) NOT NULL,subtitle VARCHAR(255) NOT NULL DEFAULT '',badge VARCHAR(80) NOT NULL DEFAULT '',image_url VARCHAR(500) NOT NULL DEFAULT '',link_url VARCHAR(255) NOT NULL DEFAULT '/',sort_order INT NOT NULL DEFAULT 0,enabled TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(kind),INDEX(enabled),INDEX(sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS model_rules(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type VARCHAR(20) NOT NULL,model_name VARCHAR(160) NOT NULL,label VARCHAR(120) NOT NULL DEFAULT '',icon VARCHAR(20) NOT NULL DEFAULT '',points INT NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 0,enabled TINYINT NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uniq_type_model(type,model_name),INDEX(type),INDEX(enabled)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$p->exec("CREATE TABLE IF NOT EXISTS site_settings(setting_key VARCHAR(80) NOT NULL PRIMARY KEY,setting_value TEXT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$initCheck=$p->query("SELECT COUNT(*) FROM site_settings WHERE setting_key='__schema_inited'")->fetchColumn();if(!$initCheck){foreach(default_site_settings() as $k=>$v){$cnt=$p->prepare("SELECT COUNT(*) FROM site_settings WHERE setting_key=?");$cnt->execute([$k]);if(!(int)$cnt->fetchColumn()){$ins=$p->prepare("INSERT INTO site_settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW())");$ins->execute([$k,$v]);}}foreach(default_site_menus() as $m){$cnt=$p->prepare("SELECT COUNT(*) FROM site_menus WHERE label=?");$cnt->execute([$m['label']]);if(!(int)$cnt->fetchColumn()){$ins=$p->prepare("INSERT INTO site_menus(label,subtitle,icon,url,page_key,badge,sort_order,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,1,NOW(),NOW())");$ins->execute([$m['label'],$m['subtitle'],$m['icon'],$m['url'],$m['page_key'],$m['badge'],$m['sort_order']]);}}foreach(default_home_blocks() as $b){$cnt=$p->prepare("SELECT COUNT(*) FROM home_blocks WHERE kind=? AND title=?");$cnt->execute([$b['kind'],$b['title']]);if(!(int)$cnt->fetchColumn()){$ins=$p->prepare("INSERT INTO home_blocks(kind,title,subtitle,badge,image_url,link_url,sort_order,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,1,NOW(),NOW())");$ins->execute([$b['kind'],$b['title'],$b['subtitle'],$b['badge'],$b['image_url'],$b['link_url'],$b['sort_order']]);}}$p->exec("INSERT INTO site_settings(setting_key,setting_value,updated_at) VALUES('__schema_inited','1',NOW()) ON DUPLICATE KEY UPDATE setting_value='1',updated_at=NOW()");}}catch(Exception $e){}}


function baidu_submit_urls(array $urls, $site = null, $token = null) {
    $settings = site_settings();
    $site = trim((string)($site ?: ($settings['baidu_submit_site'] ?? '')));
    $token = trim((string)($token ?: ($settings['baidu_submit_token'] ?? '')));
    if ($site === '' || $token === '') {
        return ['ok' => false, 'error' => 'missing_config', 'message' => '未配置百度站点域名或准入Token'];
    }
    // 过滤与去重 URL
    $cleanUrls = [];
    foreach ($urls as $u) {
        $u = trim((string)$u);
        if ($u !== '' && preg_match('#^https?://#i', $u)) {
            $cleanUrls[] = $u;
        }
    }
    $cleanUrls = array_values(array_unique($cleanUrls));
    if (empty($cleanUrls)) {
        return ['ok' => false, 'error' => 'empty_urls', 'message' => '没有待提交的有效URL'];
    }

    $apiUrl = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($token);
    $postBody = implode("\n", $cleanUrls);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $postBody,
        CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'error' => 'curl_error', 'message' => '请求百度接口失败: ' . $curlErr];
    }

    $res = json_decode((string)$response, true);
    if (!is_array($res)) {
        return ['ok' => false, 'error' => 'invalid_response', 'message' => '百度接口返回异常: ' . substr((string)$response, 0, 200)];
    }

    if (isset($res['error'])) {
        return [
            'ok' => false,
            'error' => (string)$res['error'],
            'message' => $res['message'] ?? '百度接口返回错误',
            'raw' => $res
        ];
    }

    return [
        'ok' => true,
        'remain' => (int)($res['remain'] ?? 0),
        'success' => (int)($res['success'] ?? 0),
        'not_same_site' => (array)($res['not_same_site'] ?? []),
        'not_valid' => (array)($res['not_valid'] ?? []),
        'raw' => $res
    ];
}