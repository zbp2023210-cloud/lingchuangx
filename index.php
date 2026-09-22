<?php
require __DIR__.'/lib/bootstrap.php';$page=!empty($_GET['page'])?$_GET['page']:'home';$pageAliases=['prompt'=>'prompts','prompt_square'=>'prompts','prompt-square'=>'prompts','prompt_gallery'=>'prompts','gallery'=>'prompts'];if(isset($pageAliases[$page]))$page=$pageAliases[$page];
if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  $a=$_POST['action']??'';
  if($a==='register'||$a==='login'){
    try{
      $s=db()->prepare('SELECT * FROM users WHERE username=?');
      $s->execute([trim($_POST['username']??'')]);
      $old=$s->fetch();
      if($a==='login'){
        if($old&&password_verify($_POST['password']??'',$old['password_hash'])){
          $_SESSION['user_id']=$old['id'];
          redirect('/?login_notice=1');
        }
        flash('账号或密码错误','error');
        redirect('/?page=login');
      }
      if((string)site_setting('register_enabled','1')!=='1'){
        flash('当前暂未开放注册','error');
        redirect('/?page=login');
      }
      if(strlen($_POST['password']??'')<6){
        flash('密码至少需要 6 位','error');
        redirect('/?page=register');
      }
      $gift=max(0,(int)site_setting('register_points','100'));
      $inviteCodeInput=trim($_POST['invite_code']??'');
      $s=db()->prepare('INSERT INTO users(username,password_hash,points,status,created_at,updated_at) VALUES(?,?,?,1,NOW(),NOW())');
      $s->execute([trim($_POST['username']),password_hash($_POST['password'],PASSWORD_DEFAULT),$gift]);
      $newUid=db()->lastInsertId();
      $_SESSION['user_id']=$newUid;
      if($inviteCodeInput){
        record_invite_registration($newUid, $inviteCodeInput);
      }
      flash('注册成功，已赠送 '.$gift.' 创作积分');
      redirect('/');
    }catch(Exception $e){
      flash('数据库未连接或账号已存在','error');
      redirect('/?page='.$a);
    }
  }
  if($a==='update_profile'){
    require_user();
    $u=user();
    $nickname = trim((string)($_POST['nickname']??''));
    $avatar = trim((string)($_POST['avatar']??''));
    $newPassword = (string)($_POST['new_password']??'');
    
    // 如果有上传头像文件
    if(!empty($_FILES['avatar_file']['name'])){
      try {
        $upRes = save_uploaded_file($_FILES['avatar_file'], 'cover');
        if(!empty($upRes['url'])) {
          $avatar = $upRes['url'];
        }
      } catch(Exception $e) {
        flash($e->getMessage(), 'error');
        redirect('/?page=profile');
      }
    }
    
    try {
      $sql = 'UPDATE users SET nickname=?, avatar=?, updated_at=NOW()';
      $params = [$nickname ?: null, $avatar ?: null];
      
      if(!empty($newPassword)){
        if(strlen($newPassword) < 6){
          throw new InvalidArgumentException('新密码至少需要 6 位字符');
        }
        $sql .= ', password_hash=?';
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
      }
      
      $sql .= ' WHERE id=?';
      $params[] = (int)$u['id'];
      
      $st = db()->prepare($sql);
      $st->execute($params);
      flash('个人资料已成功更新！');
    } catch(Exception $e) {
      flash($e->getMessage() ?: '更新失败，请稍后重试', 'error');
    }
    redirect('/?page=profile');
  }
  if($a==='redeem_card'){
    require_user();
    $u=user();
    $code=trim($_POST['card_code']??'');
    $res=redeem_card($u['id'], $code);
    flash($res['message'], $res['ok']?'info':'error');
    redirect('/?page=profile');
  }
  if($a==='toggle_public'){
    require_user();
    $u=user();
    $gid=(int)($_POST['generation_id']??0);
    $st=db()->prepare('SELECT id, is_public FROM generations WHERE id=? AND user_id=?');
    $st->execute([$gid, $u['id']]);
    $item=$st->fetch();
    if($item){
      $newPub = $item['is_public'] ? 0 : 1;
      db()->prepare('UPDATE generations SET is_public=? WHERE id=?')->execute([$newPub, $gid]);
      flash($newPub ? '作品已公开发布至社区广场！' : '作品已设为私密。');
    }
    redirect('/?page=history');
  }
  if($a==='like_generation'){
    require_user();
    $u=user();
    $gid=(int)($_POST['generation_id']??0);
    if($gid > 0){
      try{
        $st = db()->prepare('INSERT INTO generation_likes(user_id, generation_id, created_at) VALUES(?,?,NOW())');
        $st->execute([$u['id'], $gid]);
        db()->prepare('UPDATE generations SET likes_count=likes_count+1 WHERE id=?')->execute([$gid]);
        flash('点赞成功！');
      }catch(Exception $e){
        flash('你已经为该作品点过赞了');
      }
    }
    redirect('/?page=community');
  }
  if($a==='logout'){session_destroy();redirect('/');}
  if($a==='generate'){require_user();$p=trim($_POST['prompt']??'');if(!$p){flash('请描述你的灵感','error');redirect('/');}flash('创作任务已提交，AI 服务接口配置后将返回真实结果');redirect('/');}
}
if($page==='login'||$page==='register'){
  $inviteQuery = trim($_GET['invite']??'');
  $isLogin = ($page === 'login');
  $regPoints = (int)site_setting('register_points', '100');
  $siteName = site_setting('site_name', '灵创AI');
  $siteSub = site_setting('site_subtitle', '下一代 AI 创意生产力工坊');
  $siteLogo = site_setting('site_logo', '');
  $logoText = site_setting('logo_text', 'LC');

  layout_start($isLogin ? '登录' : '注册', '', ['no_sidebar' => true, 'bare' => true]);
?>
<section class="auth-viewport">
  <div class="auth-ambient-glow">
    <div class="auth-glow-orb orb-1"></div>
    <div class="auth-glow-orb orb-2"></div>
    <div class="auth-glow-orb orb-3"></div>
  </div>

  <div class="auth-container">
    <!-- 左侧：AI 品牌与能力展示画板 -->
    <div class="auth-brand-pane">
      <div class="auth-brand-header">
        <div class="auth-brand-logo">
          <?php if($siteLogo): ?>
            <img src="<?=h($siteLogo)?>" alt="<?=h($siteName)?>">
          <?php else: ?>
            <span>✦</span>
          <?php endif; ?>
        </div>
        <div class="auth-brand-info">
          <h2><?=h($siteName)?></h2>
          <p><?=h($siteSub)?></p>
        </div>
      </div>

      <div class="auth-brand-hero">
        <span class="auth-brand-tag">✨ NEXT-GEN AI STUDIO</span>
        <h3>释放无限灵感<br>打造爆款视觉与创意</h3>
        <p>集成多模态大模型、文生视频、电商商拍生图与专业智能体工作流，助您一键加速商业创作。</p>
      </div>

      <div class="auth-feature-cards">
        <div class="auth-feat-item">
          <div class="auth-feat-icon">⚡</div>
          <div class="auth-feat-text">
            <strong>多模型无缝切换</strong>
            <small>DeepSeek / FLUX / 纳米香蕉 / 万相 / Seedance</small>
          </div>
        </div>
        <div class="auth-feat-item">
          <div class="auth-feat-icon">🎬</div>
          <div class="auth-feat-text">
            <strong>4K 电影级画质与视频</strong>
            <small>文生视频、视频转提示词、去水印与高清重绘</small>
          </div>
        </div>
        <div class="auth-feat-item">
          <div class="auth-feat-icon">🛍️</div>
          <div class="auth-feat-text">
            <strong>电商与餐饮专属智能体</strong>
            <small>爆款主图、场景融入、AI模特与方案级协同</small>
          </div>
        </div>
      </div>

      <div class="auth-brand-footer">
        <div class="auth-stat-chip">
          <b>100,000+</b>
          <small>累计创意生成</small>
        </div>
        <div class="auth-stat-chip">
          <b>99.9%</b>
          <small>高可用算力保障</small>
        </div>
      </div>
    </div>

    <!-- 右侧：交互式登录/注册卡片 -->
    <div class="auth-form-pane">
      <div class="auth-form-header">
        <div class="auth-tabs">
          <a href="/?page=login" class="auth-tab-btn <?=$isLogin ? 'active' : ''?>">
            <span>登录账号</span>
          </a>
          <a href="/?page=register<?=!empty($inviteQuery)?'&invite='.urlencode($inviteQuery):''?>" class="auth-tab-btn <?=!$isLogin ? 'active' : ''?>">
            <span>注册新账号</span>
            <?php if($regPoints > 0): ?>
              <em class="auth-tab-badge">+<?=$regPoints?> 积分</em>
            <?php endif; ?>
          </a>
        </div>
      </div>

      <div class="auth-form-body">
        <div class="auth-welcome-text">
          <h1><?=$isLogin ? '欢迎回来' : '开启 AI 创作之旅'?></h1>
          <p><?=$isLogin ? '请输入您的账号密码以继续创作' : '注册即刻获赠 '.$regPoints.' 点算力积分，探索超凡 AI 体验'?></p>
        </div>

        <?php if(!$isLogin && $regPoints > 0): ?>
        <div class="auth-bonus-banner">
          <div class="auth-bonus-icon">🎁</div>
          <div class="auth-bonus-info">
            <strong>新人专属创作礼包</strong>
            <span>首次注册成功立即赠送 <b><?=$regPoints?></b> 创作积分，可直接用于文生图与视频！</span>
          </div>
        </div>
        <?php endif; ?>

        <form method="post" class="auth-main-form" autocomplete="off">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="action" value="<?=$page?>">

          <div class="auth-field-group">
            <label class="auth-field-label" for="authUsername">账号 / 用户名</label>
            <div class="auth-input-wrapper">
              <span class="auth-input-icon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              </span>
              <input id="authUsername" name="username" type="text" placeholder="请输入用户名" required autofocus autocomplete="username">
            </div>
          </div>

          <div class="auth-field-group">
            <div class="auth-label-row">
              <label class="auth-field-label" for="authPassword">登录密码</label>
              <?php if($isLogin): ?>
                <a href="/?page=service" class="auth-forgot-link">忘记密码？</a>
              <?php endif; ?>
            </div>
            <div class="auth-input-wrapper">
              <span class="auth-input-icon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              </span>
              <input id="authPassword" name="password" type="password" placeholder="<?=$isLogin ? '请输入密码' : '设置密码（至少6位）'?>" required autocomplete="<?=$isLogin ? 'current-password' : 'new-password'?>">
              <button type="button" class="auth-password-toggle" onclick="toggleAuthPasswordVisibility(this)" title="显示/隐藏密码">
                <svg class="icon-eye" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg class="icon-eye-off" style="display:none" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
          </div>

          <?php if(!$isLogin): ?>
          <div class="auth-field-group">
            <label class="auth-field-label" for="authInvite">邀请码 <small class="auth-optional-tag">（选填 · 享额外特权奖励）</small></label>
            <div class="auth-input-wrapper">
              <span class="auth-input-icon">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
              </span>
              <input id="authInvite" name="invite_code" type="text" placeholder="输入邀请码（可获赠额外积分）" value="<?=h($inviteQuery)?>">
            </div>
          </div>
          <?php endif; ?>

          <div class="auth-submit-row">
            <button type="submit" class="auth-submit-btn">
              <span><?=$isLogin ? '立即登录' : '立即注册并领取积分'?></span>
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>
          </div>
        </form>

        <div class="auth-form-footer">
          <p>
            <?=$isLogin ? '还没有账号？' : '已有账号？'?>
            <a href="/?page=<?=$isLogin ? 'register' : 'login'?><?=!empty($inviteQuery)?'&invite='.urlencode($inviteQuery):''?>" class="auth-switch-link">
              <?=$isLogin ? '免费注册一个' : '直接登录'?>
            </a>
          </p>
          <div class="auth-legal-note">
            登录或注册即代表您已同意 <a href="/?page=service">服务条款</a> 与 <a href="/?page=service">隐私协议</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
function toggleAuthPasswordVisibility(btn) {
  var wrapper = btn.closest('.auth-input-wrapper');
  if(!wrapper) return;
  var input = wrapper.querySelector('input');
  var eye = btn.querySelector('.icon-eye');
  var eyeOff = btn.querySelector('.icon-eye-off');
  if(!input) return;
  if(input.type === 'password') {
    input.type = 'text';
    if(eye) eye.style.display = 'none';
    if(eyeOff) eyeOff.style.display = 'block';
  } else {
    input.type = 'password';
    if(eye) eye.style.display = 'block';
    if(eyeOff) eyeOff.style.display = 'none';
  }
}
</script>
<?php layout_end();exit;}
if(in_array($page,['profile'],true)) require_user();
$chatModels=[];$imageModels=[];
if($page==='chat'||$page==='text'||$page==='agents')$chatModels=raw_models('chat');
$agentKey=(string)($_GET['agent']??'catering-visual');$activeAgent=agent_config($agentKey);if(!$activeAgent&&$page==='agents'){$activeAgent=agent_config('catering-visual');if(!$activeAgent)$activeAgent=agent_config('ecommerce-image');if(!$activeAgent)$activeAgent=agent_config('ecommerce-video');if(!$activeAgent)$activeAgent=agent_config('comic-drama');if(!$activeAgent)$activeAgent=agent_config('ppt-builder');}
if($page==='image')$imageModels=raw_models('image');
$audioModels=[];if($page==='audio')$audioModels=raw_models('audio');
$promptTypeFilter=in_array($_GET['type']??'', ['image','video'], true)?$_GET['type']:'';$promptItems=[];try{if($promptTypeFilter){$st=db()->prepare('SELECT * FROM prompts WHERE enabled=1 AND type=? ORDER BY updated_at DESC, id DESC');$st->execute([$promptTypeFilter]);$promptItems=$st->fetchAll();}else{$promptItems=db()->query('SELECT * FROM prompts WHERE enabled=1 ORDER BY updated_at DESC, id DESC')->fetchAll();}}catch(Exception $e){}
$promptItems=$promptItems?:[
 ['type'=>'image','cat'=>'电商主图','title'=>'高级护肤品电商主图','text'=>'一瓶高端精华液置于奶油色大理石台面，柔和晨光，绿色植物投影，留白构图，商业摄影，超高细节，适合电商主图'],
 ['type'=>'image','cat'=>'人像写真','title'=>'电影感人像写真','text'=>'年轻女性站在城市天台，夕阳逆光，风吹动发丝，电影感色调，浅景深，真实皮肤质感，专业摄影'],
 ['type'=>'image','cat'=>'插画设计','title'=>'治愈系梦幻插画','text'=>'漂浮在云海上的小屋，星星与月亮环绕，柔和蓝紫色调，细腻数字插画，梦幻氛围，精致细节'],
 ['type'=>'image','cat'=>'产品场景','title'=>'科技产品场景图','text'=>'无线耳机置于未来感透明水晶台面，蓝色霓虹光，极简背景，产品广告摄影，干净构图，高级质感'],
 ['type'=>'video','cat'=>'文生视频','title'=>'城市延时摄影','text'=>'黄昏时分的现代城市天际线，车流形成光轨，云层缓慢移动，电影级延时摄影，4K，平滑镜头'],
 ['type'=>'video','cat'=>'人物视频','title'=>'人物舞蹈镜头','text'=>'一位舞者在霓虹灯下自由舞动，镜头环绕推进，服装随动作飘动，节奏感，电影级灯光，高清画面'],
 ['type'=>'video','cat'=>'自然风光','title'=>'山间云海航拍','text'=>'无人机穿越壮阔山谷，云海在山峰之间流动，阳光穿透云层，史诗感风景，平稳航拍镜头'],
 ['type'=>'video','cat'=>'商品视频','title'=>'商品广告短片','text'=>'香水瓶在黑色镜面上旋转，金色液体光泽，慢动作水花，奢华商业广告，专业布光，4K画质']
];
$videoModels=[];if($page==='video')$videoModels=raw_models('video');
$selectedTextFamily=model_family('chat', $_GET['model'] ?? '');
$selectedVideoFamily=model_family('video', $_GET['model'] ?? '');
$selectedImageFamily=model_family('image', $_GET['model'] ?? '');
$selectedAudioFamily=model_family('audio', $_GET['model'] ?? '');
layout_start();
if($page==='home'){$blocks=home_blocks();$hero=null;$ads=[];foreach($blocks as $b){if($b['kind']==='hero'&&$hero===null)$hero=$b;elseif($b['kind']==='ad')$ads[]=$b;}if(!$hero)$hero=default_home_blocks()[0];?><section class="clone-home"><div class="clone-hero-banner"><img src="<?=h($hero['image_url'])?>" alt="<?=h($hero['title'])?>"><div class="clone-hero-copy"><?php if(!empty($hero['badge'])):?><span><?=h($hero['badge'])?></span><?php endif;?><h1><?=h($hero['title'])?></h1><?php if(!empty($hero['subtitle'])):?><p><?=h($hero['subtitle'])?></p><?php endif;?><a href="<?=h($hero['link_url']?:'/')?>">立即创作</a></div></div><div class="clone-ad-row"><?php foreach(array_slice($ads,0,4) as $ad):?><a href="<?=h($ad['link_url']?:'/')?>"><img src="<?=h($ad['image_url'])?>" alt="<?=h($ad['title'])?>"><b><?=h($ad['title'])?></b></a><?php endforeach;?></div><section class="clone-prompt-head"><h2>提示词广场</h2><form class="clone-home-search" action="javascript:void(0)"><input name="q" data-home-prompt-search placeholder="搜索风格或场景描述..."></form></section><div class="clone-prompt-tabs"><a class="<?=($promptTypeFilter===''?'active':'')?>" data-home-filter="all" href="/">全部</a><a class="<?=($promptTypeFilter==='image'?'active':'')?>" data-home-filter="image" href="/?type=image">图片</a><a class="<?=($promptTypeFilter==='video'?'active':'')?>" data-home-filter="video" href="/?type=video">视频</a></div><div class="clone-card-grid"><?php foreach($promptItems as $p):$cover=!empty($p['cover_url'])?$p['cover_url']:'/assets/home/'.($p['type']==='video'?'category-video.svg':'shortcut-retouch.png');$catLabel=!empty($p['cat'])?explode(',',$p['cat'])[0]:($p['type']==='video'?'视频创意':'AI绘图');?><article class="clone-work-card" role="button" tabindex="0" data-home-prompt-card="1" data-prompt-title="<?=h($p['title'])?>" data-prompt-cover="<?=h($cover)?>" data-prompt-type="<?=h($p['type'])?>" data-prompt="<?=h($p['text'])?>" data-type="<?=h($p['type'])?>"><div class="clone-card-img <?=$p['type']?>"><img src="<?=h($cover)?>" alt="<?=h($p['title'])?>" loading="lazy"><small class="clone-card-cat"><?=h($catLabel)?></small><div class="home-prompt-hover"><span>立即创作</span></div></div><div class="clone-card-info"><h3><?=h($p['title'])?></h3><p><?=h($p['text'])?></p><div class="clone-card-foot"><span class="clone-card-tag"><?=h($p['type']==='video'?'视频创意':'AI绘图')?> · 灵境</span><div class="clone-card-actions"><button type="button" class="copy-prompt" data-prompt="<?=h($p['text'])?>" data-type="<?=h($p['type'])?>">复制</button><button type="button" class="use-prompt" data-prompt="<?=h($p['text'])?>" data-type="<?=h($p['type'])?>">创作</button></div></div></div></article><?php endforeach;?></div><div class="home-prompt-modal" id="home-prompt-modal" hidden><div class="home-prompt-dialog" role="dialog" aria-modal="true"><button type="button" class="home-prompt-close" aria-label="关闭">×</button><div class="home-prompt-preview-pane"><img class="home-prompt-img" src="" alt=""></div><div class="home-prompt-body"><div class="home-prompt-title-row"><span class="home-prompt-icon">✦</span><div><h3></h3><small class="home-prompt-source"></small></div><b class="home-prompt-badge"></b></div><div class="home-prompt-section-title">提示词内容</div><p></p><div class="home-prompt-section-title">生成结果</div><img class="home-prompt-thumb" src="" alt=""><div class="home-prompt-actions"><button type="button" class="home-prompt-close-btn">关闭</button><button type="button" class="generate-btn home-prompt-use">前往创作</button></div></div></div></div></section><?php }
elseif($page==='apps'||$page==='toolbox'){?><section class="inner tools-center"><div class="tools-title"><div><span class="eyebrow">AI TOOLBOX</span><h1>AI 工具箱</h1><p>为每一种创作需求，准备了恰到好处的智能工具。</p></div><input class="tool-search" type="search" placeholder="⌕ 搜索工具" autocomplete="off"></div><div class="tool-categories"><b data-cat="all" class="active">全部</b><?php $toolcats=[];foreach(tools() as $tc){$toolcats[$tc['category']]=1;}foreach(array_keys($toolcats) as $cat):?><span data-cat="<?=h($cat)?>"><?=h($cat)?></span><?php endforeach;?></div><div class="tool-showcase"><?php foreach(tools() as $t):?><a class="showcase-card" href="<?=h(tool_url($t['slug'] ?? 'apps'))?>" data-cat="<?=h($t['category'])?>" data-name="<?=h($t['name'])?>" data-desc="<?=h($t['description'])?>"><div class="showcase-icon"><?=h($t['icon'])?></div><div class="showcase-body"><strong><?=h($t['name'])?></strong><small><?=h($t['description'])?></small><span><?=h($t['category'])?>　·　<?=h($t['points'])?> 积分</span></div><i>→</i></a><?php endforeach;?></div></section><?php }
elseif($page==='agents'){
    $agents=agent_configs();
    if(!$activeAgent&&$agents)$activeAgent=$agents[0];
    $agentKey=$activeAgent['agent_key']??'catering-visual';
    $isCatering=($agentKey==='catering-visual');
    $isEcomImage=($agentKey==='ecommerce-image');
    $isEcomVideo=($agentKey==='ecommerce-video');
    $isComic=($agentKey==='comic-drama');
    $isPpt=($agentKey==='ppt-builder');

    if($isCatering || $isEcomImage || $isEcomVideo){
        $isImg = $isEcomImage;
        $isFood = $isCatering;
        $agentTitle = $isFood ? 'AI餐饮视觉' : ($isImg ? '电商生图' : '电商生视频');
        $agentSlogan = $isFood ? '秒懂餐厅需求 · 核准菜品与用途 · 菜品门店成套精修 · 批量出图交付' : ($isImg ? '秒懂商品图文 · 多轮追问需求 · 方案逐张精修 · 批量出图交付' : '秒懂商品图文 · 多轮追问需求 · 镜头逐条精修 · 并发出片交付');
        $memberCount = $isFood ? '8 位成员在线' : ($isImg ? '9 位成员在线' : '11 位成员在线');
        $leadDesc = $isFood ? '统筹整个拍摄项目，调度团队、把控菜品真实与平台合规' : ($isImg ? '统筹整个出图项目，调度团队、把控质量与节奏' : '统筹整个出片项目，调度团队、把控质量与节奏');
        
        $experts = $isFood ? [
            ['title'=>'需求访谈师','avatar'=>'👩‍💼','color'=>'#f59e0b'],
            ['title'=>'菜品鉴定师','avatar'=>'👨‍🍳','color'=>'#10b981'],
            ['title'=>'餐饮洞察师','avatar'=>'👨‍💻','color'=>'#6366f1'],
            ['title'=>'菜单文案师','avatar'=>'🧑‍🏫','color'=>'#ec4899'],
            ['title'=>'视觉方案师','avatar'=>'👨‍🎨','color'=>'#06b6d4'],
            ['title'=>'提示词工程师','avatar'=>'🧑‍🚀','color'=>'#8b5cf6'],
            ['title'=>'质检复核官','avatar'=>'🧑‍⚖️','color'=>'#10b981']
        ] : ($isImg ? [
            ['title'=>'需求访谈师','avatar'=>'👩‍💼','color'=>'#f59e0b'],
            ['title'=>'图片鉴定师','avatar'=>'👩‍🎨','color'=>'#10b981'],
            ['title'=>'商品洞察师','avatar'=>'👨‍💻','color'=>'#6366f1'],
            ['title'=>'卖点提炼师','avatar'=>'👩‍🏫','color'=>'#ec4899'],
            ['title'=>'爆款文案师','avatar'=>'👩‍💻','color'=>'#f43f5e'],
            ['title'=>'视觉方案师','avatar'=>'👨‍🎨','color'=>'#06b6d4'],
            ['title'=>'提示词工程师','avatar'=>'🧑‍🚀','color'=>'#8b5cf6'],
            ['title'=>'质检复核官','avatar'=>'🧑‍⚖️','color'=>'#10b981']
        ] : [
            ['title'=>'需求访谈师','avatar'=>'👩‍💼','color'=>'#f59e0b'],
            ['title'=>'素材鉴定师','avatar'=>'👩‍🎨','color'=>'#10b981'],
            ['title'=>'商品洞察官','avatar'=>'👨‍💻','color'=>'#6366f1'],
            ['title'=>'卖点提炼师','avatar'=>'🧑‍🏫','color'=>'#eab308'],
            ['title'=>'脚本文案师','avatar'=>'🧑‍💻','color'=>'#f97316'],
            ['title'=>'参考图规划师','avatar'=>'👨‍🎨','color'=>'#06b6d4'],
            ['title'=>'参考图提示词师','avatar'=>'🧑‍🚀','color'=>'#8b5cf6'],
            ['title'=>'分镜导演','avatar'=>'🎬','color'=>'#10b981'],
            ['title'=>'生视频提示词师','avatar'=>'🎥','color'=>'#f43f5e'],
            ['title'=>'质检复核官','avatar'=>'🧑‍⚖️','color'=>'#10b981']
        ]);
?>
<section class="ecom-agent-stage <?=($isFood?'is-catering':($isImg?'is-image':'is-video'))?>">
  <!-- 顶栏快捷操作 -->
  <div class="ecom-stage-topbar">
    <div class="ecom-top-left">
      <button type="button" class="ecom-pill-btn" onclick="document.querySelector('[data-ecom-form] textarea').value='';document.querySelector('[data-ecom-form] textarea').focus();">＋ 新任务</button>
      <a href="/?page=history" class="ecom-pill-btn">🕒 历史 ▾</a>
    </div>
    <div class="ecom-top-right">
      <button type="button" class="ecom-top-circle-btn ecom-vip-btn" title="创作特权" onclick="alert('特权尊享：高并发通道、4K极清导出、无限制提示词精修')">💎</button>
      <button type="button" class="ecom-top-circle-btn" title="智能矩阵工具" onclick="window.location.href='/?page=apps'">🎛</button>
    </div>
  </div>

  <!-- 头部 S2 标志区 -->
  <div class="ecom-hero-header">
    <div class="ecom-badge-gold-box">
      <div class="ecom-gold-icon"><?=$isFood?'🍴':'🛒'?></div>
      <div class="ecom-gold-text">
        <div class="ecom-tag-flow"><?=$isFood?'● 上架即用 · 食材不多画 · 全渠道成套':'● AI 团队协作 · 三道确认 · 全程可控'?></div>
        <div class="ecom-title-row">
          <h1><?=h($agentTitle)?></h1>
          <span class="ecom-s2-tag">S2</span>
        </div>
      </div>
    </div>
    <p class="ecom-slogan-line"><?=h($agentSlogan)?></p>
  </div>

  <!-- 为你效力的 AI 专家团队 -->
  <div class="ecom-team-board">
    <div class="ecom-team-header">
      <span>为你效力的 <b>AI <?=$isFood?'餐饮拍摄':'专家'?>团队</b></span>
      <span class="ecom-online-badge">● <?=h($memberCount)?></span>
    </div>

    <div class="ecom-team-layout">
      <!-- 出品总监/创意总监主管 -->
      <div class="ecom-leader-card">
        <div class="ecom-leader-avatar-wrap">
          <div class="ecom-leader-avatar"><?=$isFood?'👨‍🍳':'👨‍💼'?></div>
          <span class="ecom-online-dot"></span>
        </div>
        <div class="ecom-leader-meta">
          <div class="ecom-leader-name">
            <strong><?=$isFood?'出品总监':'创意总监'?></strong>
            <span class="ecom-role-badge">主管</span>
          </div>
          <p><?=h($leadDesc)?></p>
        </div>
      </div>

      <!-- 专家列表 -->
      <div class="ecom-experts-grid">
        <?php foreach($experts as $exp): ?>
        <div class="ecom-expert-item">
          <div class="ecom-expert-avatar">
            <span><?=$exp['avatar']?></span>
            <span class="ecom-online-dot"></span>
          </div>
          <span class="ecom-expert-name"><?=h($exp['title'])?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 观看教程按钮 -->
    <div class="ecom-tutorial-row">
      <button type="button" class="ecom-tutorial-pill" onclick="alert('【<?=$agentTitle?> 使用指南】\n1. 选好用途渠道、餐饮品类、出图重点与画面风格；\n2. 上传菜品/门店照片或输入菜品卖点信息；\n3. 点击立即开工，8 位 AI 餐饮视觉专家协同完成菜品成套精修与批量出图交付！')">
        ▶ 观看视频教程
      </button>
    </div>
  </div>

  <!-- 开始创作工作台 -->
  <div class="ecom-composer-container">
    <form class="ecom-composer-form" data-ecom-form="1" data-agent-form="1">
      <div class="ecom-form-head">
        <div class="ecom-bolt-icon">⚡</div>
        <div>
          <h3><?=$isFood?'开始拍菜':'开始创作'?></h3>
          <p><?= $isFood ? '选好用途与品类，上传菜品照，团队立即开工' : ($isImg ? '选好平台与风格，上传商品图，团队立即开工' : '上传商品图，选好画面方向，团队立即开工') ?></p>
        </div>
      </div>

      <div class="ecom-form-options">
        <?php if($isFood): ?>
        <!-- 用途渠道 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">🚀</span>
            <b>用途渠道</b>
          </div>
          <div class="ecom-chips-wrap" data-multi="channel">
            <button type="button" class="ecom-chip-btn active" data-val="外卖平台">✓ 外卖平台</button>
            <button type="button" class="ecom-chip-btn" data-val="点评/地图">点评/地图</button>
            <button type="button" class="ecom-chip-btn" data-val="菜单/点餐牌">菜单/点餐牌</button>
            <button type="button" class="ecom-chip-btn" data-val="小红书">小红书</button>
            <button type="button" class="ecom-chip-btn" data-val="抖音团购">抖音团购</button>
            <button type="button" class="ecom-chip-btn" data-val="朋友圈/社群">朋友圈/社群</button>
            <button type="button" class="ecom-chip-btn" data-val="公众号">公众号</button>
            <button type="button" class="ecom-chip-btn" data-val="门店物料">门店物料</button>
            <button type="button" class="ecom-chip-btn" data-val="Google 商家">Google 商家</button>
            <button type="button" class="ecom-chip-btn" data-val="Instagram">Instagram</button>
            <button type="button" class="ecom-chip-btn" data-val="海外外卖平台">海外外卖平台</button>
            <input type="text" name="custom_channel" class="ecom-custom-chip-input" placeholder="输入其他用途渠道（选填）">
          </div>
        </div>

        <!-- 餐饮品类 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">🍲</span>
            <b>餐饮品类</b>
          </div>
          <div class="ecom-chips-wrap" data-multi="category">
            <button type="button" class="ecom-chip-btn active" data-val="火锅">✓ 火锅</button>
            <button type="button" class="ecom-chip-btn" data-val="烧烤/铁板">烧烤/铁板</button>
            <button type="button" class="ecom-chip-btn" data-val="快餐/小吃">快餐/小吃</button>
            <button type="button" class="ecom-chip-btn" data-val="中式正餐">中式正餐</button>
            <button type="button" class="ecom-chip-btn" data-val="面馆/米粉">面馆/米粉</button>
            <button type="button" class="ecom-chip-btn" data-val="西餐/牛排">西餐/牛排</button>
            <button type="button" class="ecom-chip-btn" data-val="日料/寿司">日料/寿司</button>
            <button type="button" class="ecom-chip-btn" data-val="咖啡/茶饮">咖啡/茶饮</button>
            <button type="button" class="ecom-chip-btn" data-val="烘焙/甜品">烘焙/甜品</button>
            <input type="text" name="custom_category" class="ecom-custom-chip-input" placeholder="输入其他餐饮品类（选填）">
          </div>
        </div>

        <!-- 出图重点 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">🎯</span>
            <b>出图重点</b>
          </div>
          <div class="ecom-chips-wrap" data-multi="focus">
            <button type="button" class="ecom-chip-btn active" data-val="菜品图">✓ 菜品图</button>
            <button type="button" class="ecom-chip-btn" data-val="门店视觉">门店视觉</button>
            <button type="button" class="ecom-chip-btn" data-val="后厨/制作过程">后厨/制作过程</button>
            <button type="button" class="ecom-chip-btn" data-val="套餐/活动海报">套餐/活动海报</button>
            <button type="button" class="ecom-chip-btn" data-val="食材摆拍">食材摆拍</button>
            <input type="text" name="custom_focus" class="ecom-custom-chip-input" placeholder="输入其他出图重点（选填）">
          </div>
        </div>

        <!-- 画面风格 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">⭐</span>
            <b>画面风格</b>
          </div>
          <div class="ecom-chips-wrap" data-multi="style">
            <button type="button" class="ecom-chip-btn active" data-val="干净商业风">✓ 干净商业风</button>
            <button type="button" class="ecom-chip-btn" data-val="暗调高级感">暗调高级感</button>
            <button type="button" class="ecom-chip-btn" data-val="烟火市井气">烟火市井气</button>
            <button type="button" class="ecom-chip-btn" data-val="ins清新俯拍">ins清新俯拍</button>
            <button type="button" class="ecom-chip-btn" data-val="国潮新中式">国潮新中式</button>
            <button type="button" class="ecom-chip-btn" data-val="日式侘寂">日式侘寂</button>
            <button type="button" class="ecom-chip-btn" data-val="复古港风">复古港风</button>
            <input type="text" name="custom_style" class="ecom-custom-chip-input" placeholder="输入其他风格要求（选填）">
          </div>
        </div>

        <?php elseif($isImg): ?>
        <!-- 电商生图：目标平台 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">🏳</span>
            <b>目标平台</b>
            <small class="opt-tip">可多选</small>
          </div>
          <div class="ecom-chips-wrap" data-multi="platform">
            <button type="button" class="ecom-chip-btn" data-val="抖音">抖音</button>
            <button type="button" class="ecom-chip-btn" data-val="快手">快手</button>
            <button type="button" class="ecom-chip-btn active" data-val="拼多多">✓ 拼多多</button>
            <button type="button" class="ecom-chip-btn active" data-val="淘宝/天猫">✓ 淘宝/天猫</button>
            <button type="button" class="ecom-chip-btn" data-val="京东">京东</button>
            <button type="button" class="ecom-chip-btn" data-val="小红书">小红书</button>
            <button type="button" class="ecom-chip-btn" data-val="微信小店">微信小店</button>
            <button type="button" class="ecom-chip-btn" data-val="TikTok">TikTok</button>
            <button type="button" class="ecom-chip-btn" data-val="亚马逊">亚马逊</button>
            <button type="button" class="ecom-chip-btn" data-val="Shopee">Shopee</button>
            <button type="button" class="ecom-chip-btn" data-val="Lazada">Lazada</button>
            <button type="button" class="ecom-chip-btn" data-val="速卖通">速卖通</button>
            <button type="button" class="ecom-chip-btn" data-val="Temu">Temu</button>
            <button type="button" class="ecom-chip-btn" data-val="独立站">独立站</button>
            <button type="button" class="ecom-chip-btn" data-val="自定义">＋ 自定义</button>
          </div>
        </div>

        <!-- 交付内容 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">🖼</span>
            <b>交付内容</b>
          </div>
          <div class="ecom-chips-wrap" data-multi="deliverable">
            <button type="button" class="ecom-chip-btn active" data-val="图片">✓ 图片 <span class="req">必选</span></button>
            <button type="button" class="ecom-chip-btn active" data-val="文案">✓ 文案</button>
            <button type="button" class="ecom-chip-btn" data-val="Word文档">Word 文档</button>
          </div>
        </div>
        <?php else: ?>
        <!-- 电商生视频：画面方向 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">📱</span>
            <b>画面方向</b>
          </div>
          <div class="ecom-chips-wrap" data-single="aspect_ratio">
            <button type="button" class="ecom-chip-btn active" data-val="竖屏 9:16">📱 竖屏 <small>9:16</small></button>
            <button type="button" class="ecom-chip-btn" data-val="横屏 16:9">🖥 横屏 <small>16:9</small></button>
          </div>
        </div>

        <!-- 画面风格 -->
        <div class="ecom-opt-row">
          <div class="ecom-opt-label">
            <span class="opt-icon">🎨</span>
            <b>画面风格</b>
            <small class="opt-tip">可选</small>
          </div>
          <div class="ecom-chips-wrap" data-single="style">
            <button type="button" class="ecom-chip-btn active" data-val="不限">不限</button>
            <button type="button" class="ecom-chip-btn" data-val="简洁干净">简洁干净</button>
            <button type="button" class="ecom-chip-btn" data-val="高级质感">高级质感</button>
            <button type="button" class="ecom-chip-btn" data-val="生活种草">生活种草</button>
            <button type="button" class="ecom-chip-btn" data-val="促销热卖">促销热卖</button>
            <button type="button" class="ecom-chip-btn" data-val="科技酷炫">科技酷炫</button>
            <button type="button" class="ecom-chip-btn" data-val="自定义">自定义</button>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- 核心输入卡片 -->
      <div class="ecom-input-card">
        <label class="ecom-upload-box" title="<?=$isFood?'上传菜品/门店照片（支持多张）':'上传商品图（支持多张）'?>">
          <input type="file" accept="image/*" multiple hidden id="ecomImageUpload">
          <span class="plus-icon">＋</span>
          <span class="upload-txt"><?=$isFood?'菜品/门店照片':'商品图'?></span>
          <div class="ecom-upload-preview" id="ecomUploadPreview"></div>
        </label>

        <div class="ecom-textarea-box">
          <textarea name="prompt" required placeholder="<?=$isFood ? '说说你要拍什么，例如：这三道是我们的招牌菜，要上美团外卖主图；再来一张门头夜景发朋友圈...' : ($isImg ? '输入商品名称和详细信息，例如：XXX品牌玻尿酸精华液，30ml，主打三重保湿，敏感肌可用...' : '输入商品名称和详细信息，例如：XXX品牌玻尿酸精华液，30ml，主打三重保湿，想要种草带货感的短视频...') ?>"></textarea>
        </div>

        <div class="ecom-submit-actions">
          <select name="model" class="agent-model-select ecom-model-sel">
            <?php foreach($chatModels as $m):?><option value="<?=h($m['name'])?>" data-format="<?=h($m['api_format']??'openai')?>"><?=h($m['display_name']??$m['name'])?></option><?php endforeach;?>
            <?php if(!$chatModels):?><option value="<?=h($activeAgent['model']??'tt-5.6-luna')?>"><?=h($activeAgent['model']??'默认模型')?></option><?php endif;?>
          </select>
          <button type="submit" class="ecom-send-btn" title="立即生成">↑</button>
        </div>
      </div>

      <!-- 底部工具栏 -->
      <div class="ecom-footer-toolbar">
        <div class="ecom-foot-left">
          <button type="button" class="ecom-circle-icon-btn" title="参数微调" onclick="alert('参数设置：已自动开启<?=$isFood?'AI 餐饮商业拍摄引擎与食材保真 Prompt 矩阵':'高转化爆款文案引擎与精准电商提示词构建'?>')">🎛</button>
          <button type="button" class="ecom-pill-btn-small" onclick="alert('【玩法说明】\n1. 选好用途渠道、餐饮品类、出图重点与风格\n2. 上传菜品实拍图或输入招牌菜特色\n3. 团队将快速生成成套策划方案、爆款菜单文案与Prompt\n4. 支持一键复制至绘画与设计工作台出图！')">
            ❓ 玩法说明
          </button>
        </div>
        <div class="ecom-foot-right">
          <div class="ecom-mode-switch">
            <button type="button" class="ecom-switch-item active" title="图文模式">🗂</button>
            <button type="button" class="ecom-switch-item" title="网格模式">▦</button>
          </div>
          <button type="button" class="ecom-help-circle" title="在线帮助" onclick="window.location.href='/?page=service'">?</button>
        </div>
      </div>
    </form>

    <div class="agent-result ai-result ecom-result" data-agent-result="1"></div>
  </div>
</section>
<?php } elseif($isComic){ ?>
?><section class="comic-agent-workspace">
  <aside class="comic-agent-sidebar">
    <div class="comic-side-top">
      <button type="button" class="comic-new-project-btn" onclick="document.querySelector('[data-comic-form] textarea').value='';document.querySelector('[data-comic-form] textarea').focus();">＋ 新建项目</button>
    </div>
    <div class="comic-project-list">
      <div class="comic-empty-box">
        <div class="comic-empty-icon">📁</div>
        <p>创建你的第一个漫剧项目</p>
      </div>
    </div>
    <div class="comic-side-bottom">
      <a href="/?page=agents&agent=comic-drama" class="comic-side-pill active">
        <i>⚙</i> 智能引擎
      </a>
    </div>
  </aside>

  <main class="comic-agent-stage">
    <div class="comic-stage-topbar">
      <a href="javascript:void(0)" class="comic-tutorial-btn" onclick="alert('漫剧解说使用教程：
1. 输入小说/剧本文本或故事梗概
2. AI 将依次生成：角色库设定、场景图库、风格约束、故事脚本提炼与逐镜分镜
3. 可将分镜提示词直接用于 AI 绘画与视频生成！')">▶ 查看详细教程</a>
      <div class="comic-top-tools">
        <button type="button" class="comic-tool-circle" title="创作特权">💎</button>
        <button type="button" class="comic-tool-circle" title="矩阵工具">🎛</button>
      </div>
    </div>

    <div class="comic-stage-hero">
      <div class="comic-hero-icon-wrap">
        <div class="comic-hero-hex">
          <svg viewBox="0 0 100 100" class="hex-svg">
            <polygon points="50,5 90,27.5 90,72.5 50,95 10,72.5 10,27.5" fill="none" stroke="#2dd4bf" stroke-width="3"/>
            <polygon points="50,22 75,36 75,64 50,78 25,64 25,36" fill="none" stroke="#06b6d4" stroke-width="2"/>
            <circle cx="50" cy="50" r="4" fill="#06b6d4"/>
          </svg>
        </div>
      </div>
      <div class="comic-hero-badge">● AI 驱动</div>
      <h1 class="comic-hero-title">智能漫剧创作引擎</h1>
      <p class="comic-hero-desc">一键生成完整漫剧作品，从创意到成品的全流程智能化体验</p>
      <div class="comic-hero-actions">
        <button type="button" class="comic-btn-primary" onclick="document.querySelector('[data-comic-form] textarea').focus();">＋ 开始创建项目</button>
      </div>
    </div>

    <!-- 流程模块展示卡片 -->
    <div class="comic-engine-card-wrap">
      <div class="comic-engine-card" id="comicEngineCard">
        <div class="comic-engine-card-num" id="comicCardNum">03</div>
        <div class="comic-engine-card-content">
          <div class="comic-engine-card-icon" id="comicCardIcon">☀️</div>
          <div>
            <h3 id="comicCardTitle">风格引擎</h3>
            <p id="comicCardDesc">多种爆款漫剧画风随心切换，支持国漫玄幻、韩漫高光、赛博朋克、日漫赛璐珞与电影级真人写实，全套画风统一约束。</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 底部功能引擎栏 -->
    <div class="comic-engine-tabs">
      <div class="comic-tab-item" data-step="01" data-icon="👤" data-title="智能角色库" data-desc="精准提取小说男女主、配角的外貌特征、性格与专属 Prompt 提示词，保持角色画风与服装连续一致。">
        <span class="tab-icon">👤</span>
        <span class="tab-label">智能角色库</span>
      </div>
      <div class="comic-tab-item" data-step="02" data-icon="🖼" data-title="场景生成器" data-desc="智能拆解核心故事场景：古风仙侠大殿、现代摩天大厦、未来科技城、末日废土，生成高精度环境氛围描述。">
        <span class="tab-icon">🖼</span>
        <span class="tab-label">场景生成器</span>
      </div>
      <div class="comic-tab-item active" data-step="03" data-icon="☀️" data-title="风格引擎" data-desc="多种爆款漫剧画风随心切换，支持国漫玄幻、韩漫高光、赛博朋克、日漫赛璐珞与电影级真人写实，全套画风统一约束。">
        <span class="tab-icon">☀️</span>
        <span class="tab-label">风格引擎</span>
      </div>
      <div class="comic-tab-item" data-step="04" data-icon="📄" data-title="剧情创作" data-desc="把控黄金前 3 秒爆款钩子，自动生成多集剧情大纲、情绪起伏、核心冲突与高潮反转。">
        <span class="tab-icon">📄</span>
        <span class="tab-label">剧情创作</span>
      </div>
      <div class="comic-tab-item" data-step="05" data-icon="📈" data-title="剧情提炼" data-desc="将原著长文压缩提炼为适合短视频/漫剧传播的解说旁白文案，语言节奏紧凑、戏剧张力拉满。">
        <span class="tab-icon">📈</span>
        <span class="tab-label">剧情提炼</span>
      </div>
      <div class="comic-tab-item" data-step="06" data-icon="🪟" data-title="分镜生成" data-desc="自动逐镜输出分镜脚本：景别（特写/全景）、镜头运动、画面绘图 Prompt、旁白文案与音效提示。">
        <span class="tab-icon">🪟</span>
        <span class="tab-label">分镜生成</span>
      </div>
    </div>

    <!-- 底部创作输入交互区 -->
    <div class="comic-input-container">
      <form class="comic-input-form" data-comic-form="1" data-agent-form="1">
        <div class="comic-input-inner">
          <textarea name="prompt" required placeholder="输入小说片段、故事剧本、剧情大纲或创作构思，AI 将自动分析并一键生成角色库、风格设定、解说文案与分镜脚本..."></textarea>
          <div class="comic-input-foot">
            <div class="comic-foot-left">
              <label class="comic-model-label">
                <span>模型</span>
                <select name="model" class="agent-model-select">
                  <?php foreach($chatModels as $m):?><option value="<?=h($m['name'])?>" data-format="<?=h($m['api_format']??'openai')?>"><?=h($m['display_name']??$m['name'])?></option><?php endforeach;?>
                  <?php if(!$chatModels):?><option value="<?=h($activeAgent['model']??'tt-5.6-luna')?>"><?=h($activeAgent['model']??'默认模型')?></option><?php endif;?>
                </select>
              </label>
            </div>
            <button type="submit" class="comic-submit-btn">✦ 开始生成漫剧解说方案</button>
          </div>
        </div>
      </form>

      <div class="agent-result ai-result comic-result" data-agent-result="1"></div>
    </div>
  </main>
</section>
<?php } else { 
    $stageList=[
        ['step'=>'01','key'=>'research','title'=>'资料调研','desc'=>'识别用途、受众和表达目标','detail_title'=>'资料调研','detail_desc'=>'AI 会先分析你的输入内容，提取核心主题、业务场景与预期目标。','tags'=>['场景识别','受众画像','核心目标','输入解析']],
        ['step'=>'02','key'=>'outline','title'=>'大纲规划','desc'=>'自动拆解章节、逻辑和页数','detail_title'=>'大纲规划','detail_desc'=>'AI 会按商业汇报、产品方案、营销提案等场景组织章节，规划每页核心观点、承接逻辑和信息密度。','tags'=>['章节拆解','叙事逻辑','页数控制','核心观点']],
        ['step'=>'03','key'=>'copy','title'=>'页面文案','desc'=>'生成标题、正文和演讲备注','detail_title'=>'页面文案','detail_desc'=>'围绕每页观点生成清晰标题、要点正文、数据叙事和讲稿备注，不满意可带反馈重写。','tags'=>['标题润色','演讲备注','数据叙事','反馈改写']],
        ['step'=>'04','key'=>'render','title'=>'逐页生成','desc'=>'AI 直出完整页面或结构化演示方案','detail_title'=>'逐页生成','detail_desc'=>'一键合成完整结构、演讲内容与版式建议，可直接复制交付。','tags'=>['结构完整','图文一体','PPT成稿','txt讲稿']]
    ];
?><section class="agent-v2-container">
  <div class="agent-v2-top-actions">
    <button type="button" class="agent-v2-pill-btn" onclick="document.querySelector('[data-agent-form] textarea').value='';document.querySelector('[data-agent-form] textarea').focus();">＋ 新任务</button>
    <a href="/?page=history" class="agent-v2-pill-btn">🕒 历史 ▾</a>
  </div>

  <div class="agent-v2-header">
    <div class="agent-v2-hero-badge">
      <span class="agent-v2-main-icon"><?=h($activeAgent['icon']??'▤')?></span>
      <div class="agent-v2-pill-tag">● AI 智能体 · 多轮规划 · 每步可控</div>
    </div>
    <h1 class="agent-v2-title">一键生 <span>PPT</span></h1>
    <div class="agent-v2-quick-capsules">
      <span>☰ 输入主题 · AI 搭大纲</span>
      <span>💻 多轮规划 · 页页可控</span>
      <span>🖼 逐页生成 · 图文一体</span>
      <span>📥 PPT 成稿 · txt 讲稿</span>
    </div>
  </div>

  <div class="agent-v2-stage-section">
    <div class="agent-v2-stage-sidebar">
      <?php foreach($stageList as $idx=>$stg): ?>
      <div class="agent-v2-stage-tab <?=($idx===1?'active':'')?>" data-stage-index="<?=$idx?>" data-step="<?=$stg['step']?>" data-title="<?=h($stg['detail_title'])?>" data-desc="<?=h($stg['detail_desc'])?>" data-tags="<?=h(implode(',',$stg['tags']))?>">
        <span class="stage-tab-icon"><?=($idx===0?'📋':($idx===1?'📄':($idx===2?'🖥':'📱')))?><small><?=$stg['step']?></small></span>
        <div class="stage-tab-info">
          <b><?=h($stg['title'])?></b>
          <small><?=h($stg['desc'])?></small>
        </div>
        <span class="stage-arrow">›</span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="agent-v2-stage-card">
      <div class="stage-card-head">
        <span class="stage-badge-num" id="stageCardNum">02</span>
        <span class="stage-badge-ai">● AI 驱动</span>
      </div>
      <div class="stage-card-body">
        <div class="stage-card-icon-wrap" id="stageCardIcon">📄</div>
        <div class="stage-card-text">
          <h2 id="stageCardTitle"><?=h($stageList[1]['detail_title'])?></h2>
          <p id="stageCardDesc"><?=h($stageList[1]['detail_desc'])?></p>
          <div class="stage-card-tags" id="stageCardTags">
            <?php foreach($stageList[1]['tags'] as $tg): ?><span><?=h($tg)?></span><?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="stage-card-indicators">
        <span class="ind"></span><span class="ind active"></span><span class="ind"></span><span class="ind"></span>
      </div>
    </div>
  </div>

  <div class="agent-v2-composer-wrap">
    <form class="agent-v2-form" data-agent-form="1">
      <div class="agent-v2-input-box">
        <div class="agent-v2-upload-slot" title="上传参考资料（可选）">
          <span class="plus">＋</span>
          <span class="lbl">参考资料</span>
          <input type="file" multiple hidden>
        </div>
        <div class="agent-v2-textarea-wrap">
          <textarea name="prompt" required placeholder="输入 PPT 主题、用途、受众和你已有的资料，例如：为新品发布会生成商业路演 PPT，突出市场机会、产品卖点和落地计划。
可上传图片、PDF、Word、Excel、PPTX 等资料辅助理解，AI 将自动分析并分步规划生成。"></textarea>
        </div>
        <div class="agent-v2-input-actions">
          <select name="model" class="agent-model-select">
            <?php foreach($chatModels as $m):?><option value="<?=h($m['name'])?>" data-format="<?=h($m['api_format']??'openai')?>"><?=h($m['display_name']??$m['name'])?></option><?php endforeach;?>
            <?php if(!$chatModels):?><option value="<?=h($activeAgent['model']??'tt-5.6-luna')?>"><?=h($activeAgent['model']??'默认模型')?></option><?php endif;?>
          </select>
          <button type="submit" class="agent-v2-send-btn" title="提交生成">↑</button>
        </div>
      </div>

      <div class="agent-v2-footer-bar">
        <div class="foot-left">
          <button type="button" class="foot-circle-btn" title="高级设置">⚙</button>
          <div class="foot-switch-pill">
            <span class="active">🗂</span>
            <span>▦</span>
          </div>
          <a href="javascript:void(0)" class="foot-help-link" onclick="alert('玩法说明：
1. 输入主题与核心需求
2. 选择所需模型
3. 点击发送即可多轮规划并逐页生成完整方案')">❓ 玩法说明</a>
        </div>
        <div class="foot-right">
          <div class="agent-mode-capsules">
            <button type="button" class="mode-capsule active" data-mode="step">≡ 逐步确认</button>
            <button type="button" class="mode-capsule" data-mode="auto">⚡ 智能托管</button>
          </div>
        </div>
      </div>
    </form>

    <div class="agent-result ai-result" data-agent-result="1"></div>
  </div>
</section>
<?php } }
elseif($page==='service'){$site=site_settings();?><section class="inner"><div class="service-page"><div class="service-page-icon">▣</div><span class="eyebrow">CUSTOMER SERVICE</span><h1><?=h($site['service_title']?:'联系客服')?></h1><p><?=h($site['service_subtitle']?:'随为您服务')?></p><div class="service-card"><strong>客服微信</strong><?php if(!empty($site['service_qrcode'])):?><img class="service-qrcode" src="<?=h($site['service_qrcode'])?>" alt="客服微信二维码"><small>请使用微信扫码联系客服</small><?php else:?><small>暂未上传客服微信二维码，请在后台系统设置中添加。</small><?php endif;?><a class="generate-btn" href="/?page=profile">查看账户信息</a></div></div></section><?php }
elseif($page==='video2prompt'){render_prompt_extract_workspace('video');}
elseif($page==='image2prompt'){render_prompt_extract_workspace('image');}
elseif($page==='watermark-remove'){render_watermark_remove_workspace();}
elseif($page==='video-transcript'){render_video_transcript_workspace();}
elseif(false){?><section class="inner tools-center"><div class="tools-title"><div><span class="eyebrow">AI TOOLBOX</span><h1>更多工具</h1><p>为每一种创作需求，准备了恰到好处的智能工具。</p></div><input class="tool-search" type="search" placeholder="⌕ 搜索工具" autocomplete="off"></div><div class="tool-categories"><b data-cat="all" class="active">全部</b><?php $toolcats=[];foreach(tools() as $tc){$toolcats[$tc['category']]=1;}foreach(array_keys($toolcats) as $cat):?><span data-cat="<?=h($cat)?>"><?=h($cat)?></span><?php endforeach;?></div><div class="tool-showcase"><?php foreach(tools() as $t):?><a class="showcase-card" href="/?page=<?=h(tool_page($t['slug'] ?? 'apps'))?>" data-cat="<?=h($t['category'])?>" data-name="<?=h($t['name'])?>" data-desc="<?=h($t['description'])?>"><div class="showcase-icon"><?=h($t['icon'])?></div><div class="showcase-body"><strong><?=h($t['name'])?></strong><small><?=h($t['description'])?></small><span><?=h($t['category'])?>　·　<?=h($t['points'])?> 积分</span></div><i>→</i></a><?php endforeach;?></div></section><?php }
elseif($page==='tool'){$tool=toolbox_item($_GET['tool']??'prompt');?><section class="model-split-workspace"><section class="model-compose-pane"><div class="model-page-head"><h1><?=h($tool['label'])?> <em>AI工具箱</em></h1><p><?=h($tool['desc'])?></p></div><form class="model-compose-form" data-ai-form="<?=($_GET['tool']??'')==='prompt'?'text':'video'?>"><div class="mode-switch"><button type="button" class="active"><?=h($tool['label'])?></button><button type="button">灵创AI</button></div><?php if(($tool['key']??'')!=='prompt'):?><label class="ref-upload"><span class="optional">必选</span><strong>＋</strong><b>上传视频/图片</b><small>支持视频素材或参考图片</small><input type="file" accept="video/*,image/*" hidden></label><div class="upload-tip">⚡ 上传素材后可进行换人、动作迁移或超清增强</div><?php endif;?><div class="prompt-toolbar"><b>任务描述</b><span><button type="button">示例</button><button type="button">AI润色</button></span></div><textarea name="prompt" placeholder="<?php if($tool['key']==='prompt'):?>粘贴爆款内容链接、标题或文案，提取结构和创作提示词<?php elseif($tool['key']==='animate-mix'):?>描述需要替换的人物、服装、镜头风格和保留内容<?php elseif($tool['key']==='animate-move'):?>描述目标动作、人物姿态和运动幅度<?php else:?>描述需要增强的视频类型、清晰度目标和画面风格<?php endif;?>" required></textarea><label class="model-select-line">处理模型<select name="model"><option value="toolbox-<?=h($tool['key'])?>"><?=h($tool['label'])?> 专用模型</option></select></label><div class="model-options"><button type="button" class="active"><b>标准</b><small>推荐</small></button><button type="button"><b>高清</b><small>高质量</small></button><button type="button"><b>快速</b><small>优先速度</small></button><button type="button"><b>精修</b><small>细节增强</small></button></div><div class="model-submit-bar"><button type="submit" class="generate-btn">开始处理</button></div></form></section><section class="model-record-pane"><div class="record-head"><div><h2>生成记录</h2><p>共 0 条</p></div><div class="record-tabs"><button type="button" class="active">全部</button><button type="button">生成中</button><button type="button">成功</button><button type="button">失败</button></div></div><div class="record-empty"><strong>▧</strong><span>暂无生成记录</span></div><div class="ai-result" data-media-result="video"></div></section></section><?php }
elseif($page==='prompts'){?><section class="inner prompt-center"><div class="page-intro"><span class="eyebrow">PROMPT GALLERY</span><h1>提示词广场</h1><p>发现灵感，复制一句话，开启你的创作。</p></div><div class="prompt-tabs"><a class="prompt-filter <?=($promptTypeFilter===''?'active':'')?>" data-filter="all" href="/?page=prompts">全部</a><a class="prompt-filter <?=($promptTypeFilter==='image'?'active':'')?>" data-filter="image" href="/?page=prompts&type=image">图片</a><a class="prompt-filter <?=($promptTypeFilter==='video'?'active':'')?>" data-filter="video" href="/?page=prompts&type=video">视频</a></div><div class="prompt-waterfall"><?php foreach($promptItems as $p):$cover=!empty($p['cover_url'])?$p['cover_url']:'/assets/home/'.($p['type']==='video'?'category-video.svg':'shortcut-retouch.png');?><article class="prompt-card" data-prompt-type="<?=h($p['type'])?>" data-prompt-cat="<?=h($p['cat'])?>" data-prompt="<?=h($p['text'])?>" data-type="<?=h($p['type'])?>"><div class="prompt-cover <?=$p['type']?>"><div class="prompt-media"><?php if($p['type']==='video'&&!empty($p['media_url'])):?><video src="<?=h($p['media_url'])?>" muted playsinline preload="metadata" class="card-video"></video><?php else:?><img src="<?=h($cover)?>" alt="<?=h($p['title'])?>" class="card-img" loading="lazy"><?php endif;?><div class="prompt-hover-action"><span>立即使用</span></div></div><small class="prompt-cat-badge"><?=h($p['cat']?:($p['type']==='video'?'视频创意':'AI绘图'))?></small></div><div class="prompt-card-content"><h3><?=h($p['title'])?></h3><p><?=h($p['text'])?></p><div class="prompt-card-foot"><span class="prompt-tag"><?= $p['type']==='image'?'图片':'视频' ?> · 灵境 AI</span><div class="prompt-actions"><button type="button" class="copy-prompt" data-prompt="<?=h($p['text'])?>" data-type="<?=h($p['type'])?>">复制</button><button type="button" class="use-prompt">创作</button></div></div></div></article><?php endforeach;?></div></section><?php }
elseif($page==='video'){$selectedModel=$selectedVideoFamily['model'];?><section class="model-split-workspace"><section class="model-compose-pane"><div class="model-page-head"><h1><?=h($selectedVideoFamily['label'])?> 视频生成 <em>国产旗舰</em></h1><p><?=h($selectedVideoFamily['desc'])?> · 秒级响应，音画同步，中文理解能力强</p></div><form class="model-compose-form" data-ai-form="video"><input type="hidden" name="prompt_source" value="prompt-gallery"><div class="mode-switch"><button type="button" class="active">✎ 文生视频</button><button type="button">▧ 图生视频</button></div><label class="ref-upload"><span class="optional">可选</span><strong>＋</strong><b>上传图片</b><small>请先 登录 后上传图片</small><input type="file" accept="image/*" hidden></label><div class="upload-tip">⚡ 上传参考图像可提升生成质量</div><div class="prompt-toolbar"><b>提示词</b><span><button type="button">爆款复刻</button><button type="button">AI润色</button></span></div><textarea name="prompt" placeholder="根据主题描述生成内容，描述生成的场景、主题，一键成片" required></textarea><label class="model-select-line">视频模型<select name="model"><?php $hasSelected=false;foreach($videoModels as $m):$mn=$m['name']??'';$is=$mn===$selectedModel;if($is)$hasSelected=true;?><option value="<?=h($mn)?>" <?=$is?'selected':''?>><?=h($m['display_name']??$mn)?></option><?php endforeach;?><?php if(!$hasSelected):?><option value="<?=h($selectedModel)?>" selected><?=h($selectedVideoFamily['label'])?></option><?php endif;?></select></label><div class="model-options"><button type="button" class="active"><b>480p-5s</b><small>折扣渠道</small></button><button type="button"><b>480p-10s</b><small>折扣渠道</small></button><button type="button"><b>480p-15s</b><small>折扣渠道</small></button><button type="button"><b>720p-5s</b><small>折扣渠道</small></button><button type="button"><b>720p-10s</b><small>折扣渠道</small></button><button type="button"><b>720p-15s</b><small>折扣渠道</small></button></div><div class="model-submit-bar"><button type="submit" class="generate-btn">生成视频　消耗 <?=h($selectedVideoFamily['points']?:198)?> 积分</button></div></form></section><section class="model-record-pane"><div class="record-head"><div><h2>生成记录</h2><p>共 0 条</p></div><div class="record-tabs"><button type="button" class="active">全部</button><button type="button">生成中</button><button type="button">成功</button><button type="button">失败</button></div></div><div class="record-empty"><strong>▧</strong><span>暂无生成记录</span></div><div class="ai-result" data-media-result="video"></div></section></section><?php }
elseif($page==='audio'){$selectedModel=$selectedAudioFamily['model'];?><section class="model-split-workspace"><section class="model-compose-pane"><div class="model-page-head"><h1><?=h($selectedAudioFamily['label'])?> 音频生成 <em>AI音频</em></h1><p><?=h($selectedAudioFamily['desc'])?> · 支持语音、音乐和音频创作模型</p></div><form class="model-compose-form" data-ai-form="audio"><div class="mode-switch"><button type="button" class="active">♫ 音乐生成</button><button type="button">◌ 语音合成</button></div><div class="prompt-toolbar"><b>提示词</b><span><button type="button">风格参考</button><button type="button">AI润色</button></span></div><textarea name="prompt" placeholder="描述你想生成的音乐、语音内容、风格、情绪或用途..." required></textarea><div class="studio-row compact"><label>音频模型<select name="model"><?php $hasSelected=false;foreach($audioModels as $m):$mn=$m['name']??'';$is=$mn===$selectedModel;if($is)$hasSelected=true;?><option value="<?=h($mn)?>" <?=$is?'selected':''?>><?=h($m['display_name']??$mn)?></option><?php endforeach;?><?php if(!$hasSelected):?><option value="<?=h($selectedModel)?>" selected><?=h($selectedAudioFamily['label'])?></option><?php endif;?></select></label><label>输出类型<select name="audio_type"><option value="music">音乐/音频</option><option value="tts">语音合成</option></select></label></div><div class="model-options"><button type="button" class="active"><b>标准</b><small>基础质量</small></button><button type="button"><b>高清</b><small>高质量</small></button><button type="button"><b>长音频</b><small>扩展时长</small></button><button type="button"><b>商用</b><small>稳定通道</small></button></div><div class="model-submit-bar"><button type="submit" class="generate-btn">生成音频　消耗 <?=h($selectedAudioFamily['points']?:80)?> 积分</button></div></form></section><section class="model-record-pane"><div class="record-head"><div><h2>生成记录</h2><p>共 0 条</p></div><div class="record-tabs"><button type="button" class="active">全部</button><button type="button">生成中</button><button type="button">成功</button><button type="button">失败</button></div></div><div class="record-empty"><strong>▧</strong><span>暂无生成记录</span></div><div class="ai-result" data-media-result="audio"></div></section></section><?php }
elseif($page==='chat'){?><section class="chat-workspace"><div class="chat-service-head"><div class="service-avatar">↗</div><div><div class="service-tags"><span>智能助手</span><b>推荐</b><em class="model-pill" id="selected-model-label">选择模型</em></div><h1>运营文案助手</h1><p>文本创作、问答和任务协作</p></div><button type="button" class="new-chat">⊕　新对话</button></div><div class="login-notice">♟　<strong>登录后开始智能对话</strong><span>登录后会写入最近对话，刷新页面也能继续同一场聊天会话。</span><a href="/?page=login">立即登录</a></div><div class="assistant-welcome"><div class="assistant-avatar">↗</div><div class="assistant-bubble">你好，我可以帮你整理想法、生成内容和优化工作流程。</div></div><div class="suggestions"><div class="suggestion-title">♟　<strong>可以这样开始</strong>　助手建议</div><div><button type="button" class="suggestion-chip">帮我整理一份执行清单</button><button type="button" class="suggestion-chip">帮我把这段内容优化成更专业的表达</button></div></div><form class="chat-composer" data-ai-form="text"><textarea name="prompt" placeholder="输入你想聊的问题、文案或方案"></textarea><div class="chat-composer-foot"><span class="word-count">0 / 4000 字</span><label class="model-select-wrap">模型 <select name="model" id="chat-model-select"><?php foreach($chatModels as $m):?><option value="<?=h($m['name'])?>" data-format="<?=h($m['api_format']??'openai')?>" data-label="<?=h($m['display_name']??$m['name'])?>" <?=($m['name']==='tt-5.6-luna'?'selected':'')?>><?=h($m['display_name']??$m['name'])?></option><?php endforeach;?></select></label><span class="login-tag">登录后对话</span><button type="submit" class="generate-btn">↑　发送</button></div></form><div class="ai-result"></div></section><?php }
elseif($page==='image'){$selectedModel=$selectedImageFamily['model'];?><section class="model-split-workspace"><section class="model-compose-pane"><div class="model-page-head"><h1><?=h($selectedImageFamily['label'])?> 图片生成 <em>AI绘画</em></h1><p><?=h($selectedImageFamily['desc'])?> · 把你的想象变成可见画面</p></div><form class="model-compose-form image-control-panel" data-ai-form="image"><div class="mode-switch"><button type="button" data-mode="txt2img" class="active">✎ 文生图</button><button type="button" data-mode="img2img">▧ 图生图</button><button type="button" data-mode="inpaint">局部重绘</button></div><label class="ref-upload reference-upload" id="reference-upload"><span class="optional">可选</span><strong>＋</strong><b>上传图片</b><small>支持 JPG、PNG，最多 14 张</small><input type="file" accept="image/*" multiple hidden><span class="thumbs" id="ref-thumbs"></span></label><div class="prompt-toolbar"><b>提示词</b><span><button type="button">爆款复刻</button><button type="button">AI润色</button></span></div><textarea name="prompt" placeholder="描述主体、场景、风格、光线和氛围..." required></textarea><textarea name="negative" class="negative-prompt" placeholder="反向提示词，不希望出现的内容，可选"></textarea><label class="model-select-line">选择模型<select name="model"><?php $hasSelected=false;foreach($imageModels as $m):$mn=$m['name']??'';$is=$mn===$selectedModel;if($is)$hasSelected=true;?><option value="<?=h($mn)?>" <?=$is?'selected':''?>><?=h($m['display_name']??$mn)?></option><?php endforeach;?><?php if(!$hasSelected):?><option value="<?=h($selectedModel)?>" selected><?=h($selectedImageFamily['label'])?></option><?php endif;?></select></label><div class="model-options"><button type="button" class="active"><b>1:1</b><small>正方形</small></button><button type="button"><b>2:3</b><small>竖版</small></button><button type="button"><b>3:2</b><small>横版</small></button><button type="button"><b>16:9</b><small>宽屏</small></button></div><input type="hidden" name="size" value="1024x1024"><input type="hidden" name="quality" value="medium"><div class="model-submit-bar"><button type="submit" class="generate-btn">开始创作　消耗 <?=h($selectedImageFamily['points']?:20)?> 积分</button></div></form></section><section class="model-record-pane"><div class="record-head"><div><h2>生成记录</h2><p>共 0 条</p></div><div class="record-tabs"><button type="button" class="active">全部</button><button type="button">生成中</button><button type="button">成功</button><button type="button">失败</button></div></div><div class="record-empty"><strong>▧</strong><span>暂无生成记录</span></div><div class="ai-result" data-media-result="image"></div></section></section><?php }
elseif($page==='text'){$selectedModel=$selectedTextFamily['model'];?><section class="model-split-workspace"><section class="model-compose-pane"><div class="model-page-head"><h1><?=h($selectedTextFamily['label'])?> 文本生成 <em>AI文本</em></h1><p><?=h($selectedTextFamily['desc'])?> · 文案、问答、脚本和结构化内容创作</p></div><form class="model-compose-form" data-ai-form="text"><div class="mode-switch"><button type="button" class="active">Aa 文本创作</button><button type="button">▣ 长文写作</button></div><div class="prompt-toolbar"><b>输入内容</b><span><button type="button" class="suggestion-chip">写一篇产品卖点文案</button><button type="button" class="suggestion-chip">AI润色</button></span></div><textarea name="prompt" placeholder="输入你想生成、改写、总结或分析的内容..." required></textarea><label class="model-select-line">文本模型<select name="model"><?php $hasSelected=false;foreach($chatModels as $m):$mn=$m['name']??'';$is=$mn===$selectedModel;if($is)$hasSelected=true;?><option value="<?=h($mn)?>" data-format="<?=h($m['api_format']??'openai')?>" <?=$is?'selected':''?>><?=h($m['display_name']??$mn)?></option><?php endforeach;?><?php if(!$hasSelected):?><option value="<?=h($selectedModel)?>" selected><?=h($selectedTextFamily['label'])?></option><?php endif;?></select></label><div class="model-options"><button type="button" class="active"><b>通用</b><small>日常问答</small></button><button type="button"><b>文案</b><small>营销创作</small></button><button type="button"><b>长文</b><small>结构写作</small></button><button type="button"><b>代码</b><small>技术辅助</small></button></div><div class="model-submit-bar"><button type="submit" class="generate-btn">生成文本　消耗 <?=h($selectedTextFamily['points']?:5)?> 积分</button></div></form></section><section class="model-record-pane"><div class="record-head"><div><h2>生成记录</h2><p>共 0 条</p></div><div class="record-tabs"><button type="button" class="active">全部</button><button type="button">生成中</button><button type="button">成功</button><button type="button">失败</button></div></div><div class="record-empty"><strong>▧</strong><span>暂无生成记录</span></div><div class="ai-result" data-media-result="text"></div></section></section><?php }
elseif($page==='profile'){
  require_user();
  $u=user();
  $inviteCode = ensure_user_invite_code($u);
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $inviteLink = $scheme . '://' . $host . '/?page=register&invite=' . urlencode($inviteCode);
  $inviteCount = 0;
  $inviteEarned = 0;
  try{
    $st = db()->prepare('SELECT COUNT(*) as cnt, COALESCE(SUM(reward_points),0) as sum_pts FROM user_invites WHERE inviter_id=?');
    $st->execute([$u['id']]);
    $invStat = $st->fetch();
    $inviteCount = (int)($invStat['cnt']??0);
    $inviteEarned = (int)($invStat['sum_pts']??0);
  }catch(Exception $e){}
  
  $vipPlans = vip_plans(true);
  $pointPkgs = points_packages(true);
  $site = site_settings();
  $isVip = !empty($u['vip_until']) && strtotime($u['vip_until']) > time();
  $avatarUrl = !empty($u['avatar']) ? $u['avatar'] : '';
  $displayName = !empty($u['nickname']) ? $u['nickname'] : $u['username'];
?><section class="profile-page-container">
  <!-- 用户信息主卡片（含编辑按钮） -->
  <div class="user-hero-card">
    <div class="user-hero-main">
      <div class="user-hero-avatar-wrap" onclick="openProfileEditModal()" style="cursor:pointer;" title="点击修改头像和个人资料">
        <div class="user-hero-avatar">
          <?php if($avatarUrl): ?>
            <img src="<?=h($avatarUrl)?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
          <?php else: ?>
            <span><?=mb_substr($displayName, 0, 1, 'UTF-8')?></span>
          <?php endif; ?>
        </div>
        <?php if($isVip): ?><span class="user-vip-crown" title="VIP会员">👑</span><?php endif; ?>
        <span class="user-avatar-edit-badge">✎</span>
      </div>
      <div class="user-hero-meta">
        <div class="user-hero-name-row">
          <h2><?=h($displayName)?></h2>
          <?php if(!empty($u['nickname'])): ?>
            <small class="user-username-tag">(@<?=h($u['username'])?>)</small>
          <?php endif; ?>
          <?php if($isVip): ?>
            <span class="user-badge vip-active">👑 VIP 会员 (至 <?=date('Y-m-d', strtotime($u['vip_until']))?>)</span>
          <?php else: ?>
            <span class="user-badge vip-free">普通创作者</span>
          <?php endif; ?>
          <button type="button" class="user-edit-profile-btn" onclick="openProfileEditModal()">✎ 编辑资料</button>
        </div>
        <p class="user-hero-sub">ID: #<?=(int)$u['id']?> · 注册于 <?=date('Y-m-d', strtotime($u['created_at']))?></p>
      </div>
    </div>
    
    <div class="user-hero-stats">
      <div class="hero-stat-box">
        <div class="stat-label">创作积分余额</div>
        <div class="stat-value highlight"><?=h($u['points'])?> <small>pts</small></div>
      </div>
      <div class="hero-stat-box">
        <div class="stat-label">已邀请好友</div>
        <div class="stat-value"><?=$inviteCount?> <small>人</small></div>
      </div>
      <div class="hero-stat-box">
        <div class="stat-label">邀请获赠积分</div>
        <div class="stat-value"><?=$inviteEarned?> <small>pts</small></div>
      </div>
    </div>
  </div>

  <!-- VIP 会员订阅专区 -->
  <div class="profile-section-card" id="vip-section">
    <div class="section-title-wrap">
      <div class="section-badge-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">👑</div>
      <div>
        <h3>开通 VIP 会员尊享特权</h3>
        <p>解锁超清多模态大模型、尊享优先排队通道、海量创作积分即刻到账</p>
      </div>
    </div>
    <div class="package-card-grid">
      <?php foreach($vipPlans as $vp): 
        $color = $vp['color'] ?: '#3b82f6';
      ?>
        <div class="package-plan-card vip-plan-card" style="--plan-theme:<?=h($color)?>">
          <div class="plan-top-tag"><?=h($vp['icon']?:'👑')?> <?=h($vp['name'])?></div>
          <div class="plan-price-wrap">
            <span class="currency">¥</span>
            <span class="amount"><?=number_format((float)$vp['price'], 1)?></span>
            <span class="unit">/ <?=(int)$vp['duration_days']?>天</span>
          </div>
          <div class="plan-points-badge">+<?=(int)$vp['points']?> 赠送积分</div>
          <p class="plan-desc"><?=h($vp['description'] ?: '全模态AI极速生成、专属通道')?></p>
          <button type="button" class="plan-buy-btn" onclick="openPaymentModal('vip', <?=(int)$vp['id']?>, '<?=h($vp['name'])?>', '<?=number_format((float)$vp['price'], 2)?>')">立即开通</button>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 积分充值套餐专区 -->
  <div class="profile-section-card" id="points-section">
    <div class="section-title-wrap">
      <div class="section-badge-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;">💎</div>
      <div>
        <h3>积分充值加油包</h3>
        <p>充值积分永久有效，灵活按需消费，支持绘画、视频、文本和智能体方案生成</p>
      </div>
    </div>
    <div class="package-card-grid points-grid">
      <?php foreach($pointPkgs as $pkg): ?>
        <div class="package-plan-card points-plan-card">
          <?php if(!empty($pkg['badge'])): ?><div class="plan-corner-ribbon"><?=h($pkg['badge'])?></div><?php endif; ?>
          <div class="points-amount-wrap">
            <span class="points-num"><?=(int)$pkg['points']?></span>
            <span class="points-unit">积分</span>
          </div>
          <div class="plan-price-wrap">
            <span class="currency">¥</span>
            <span class="amount"><?=number_format((float)$pkg['price'], 1)?></span>
          </div>
          <p class="plan-desc">单次平均仅需低至 0.02元 / 积分</p>
          <button type="button" class="plan-buy-btn points-btn" onclick="openPaymentModal('points', <?=(int)$pkg['id']?>, '<?=h($pkg['name'])?> (<?=(int)$pkg['points']?>积分)', '<?=number_format((float)$pkg['price'], 2)?>')">立即充值</button>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 邀请与卡密兑换并列布局 -->
  <div class="profile-two-columns">
    <!-- 邀请好友 -->
    <div class="profile-sub-card">
      <div class="sub-card-head">
        <span class="sub-card-icon">🎁</span>
        <div>
          <h4>邀请好友获赠积分</h4>
          <small>每成功邀请 1 位新用户，双方均可自动获赠创作积分！</small>
        </div>
      </div>
      <div class="invite-inputs-box">
        <div class="invite-item">
          <label>我的专属邀请码</label>
          <div class="copy-input-group">
            <input type="text" readonly value="<?=h($inviteCode)?>" id="myInviteCode">
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('myInviteCode').value);flash('邀请码已复制到剪贴板！');">复制邀请码</button>
          </div>
        </div>
        <div class="invite-item">
          <label>我的专属推广链接</label>
          <div class="copy-input-group">
            <input type="text" readonly value="<?=h($inviteLink)?>" id="myInviteLink">
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('myInviteLink').value);flash('推广链接已复制，发给好友注册即可！');">复制链接</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 卡密兑换与登出 -->
    <div class="profile-sub-card">
      <div class="sub-card-head">
        <span class="sub-card-icon">🎟️</span>
        <div>
          <h4>卡密兑换（积分/VIP）</h4>
          <small>输入从官方店铺、活动或代理处获取的卡密串即刻到账</small>
        </div>
      </div>
      <form method="post" class="redeem-form">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="action" value="redeem_card">
        <div class="copy-input-group">
          <input type="text" name="card_code" placeholder="输入卡密字符串（如 P2503...）" required>
          <button type="submit" class="redeem-btn">立即兑换</button>
        </div>
      </form>
      <div class="profile-foot-actions">
        <form method="post" style="display:inline-block;">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="action" value="logout">
          <button class="logout-btn" type="submit">退出当前账号</button>
        </form>
        <a href="<?=h($site['service_url']?:'/?page=service')?>" class="help-btn">联系客服咨询</a>
      </div>
    </div>
  </div>
</section>

<!-- 个人信息编辑弹窗 -->
<div id="profileEditModal" class="profile-modal-overlay" style="display:none;" onclick="if(event.target===this)closeProfileEditModal()">
  <div class="profile-modal-dialog">
    <div class="profile-modal-header">
      <h4>编辑账号资料</h4>
      <a href="javascript:void(0)" class="profile-modal-close" onclick="closeProfileEditModal()">✕</a>
    </div>
    <form method="post" enctype="multipart/form-data" class="profile-edit-form">
      <input type="hidden" name="csrf" value="<?=csrf()?>">
      <input type="hidden" name="action" value="update_profile">
      
      <div class="profile-avatar-field-row">
        <div class="profile-preview-avatar" id="editAvatarPreview">
          <?php if($avatarUrl): ?>
            <img src="<?=h($avatarUrl)?>" alt="preview">
          <?php else: ?>
            <span><?=mb_substr($displayName, 0, 1, 'UTF-8')?></span>
          <?php endif; ?>
        </div>
        <div class="profile-avatar-upload-col">
          <label class="profile-avatar-upload-btn">
            <span>📷 上传新头像</span>
            <input type="file" name="avatar_file" accept="image/*" onchange="previewAvatar(event)">
          </label>
          <small>支持 JPG、PNG、WebP 格式图片</small>
        </div>
      </div>

      <div class="profile-form-group">
        <label>登录账号 (不可修改)</label>
        <input type="text" value="<?=h($u['username'])?>" readonly disabled class="profile-input-readonly">
      </div>

      <div class="profile-form-group">
        <label>用户个性昵称</label>
        <input type="text" name="nickname" value="<?=h($u['nickname']??'')?>" placeholder="设置您的个性昵称 (如: 极速创作者)" class="profile-input" maxlength="30">
      </div>

      <div class="profile-form-group">
        <label>网络头像图片 URL (可选，留空使用上传的文件)</label>
        <input type="text" name="avatar" value="<?=h($avatarUrl)?>" placeholder="https://..." class="profile-input" id="editAvatarUrlInput" oninput="previewAvatarUrl(this.value)">
      </div>

      <div class="profile-form-group">
        <label>修改新密码 (不修改请留空)</label>
        <input type="password" name="new_password" placeholder="留空表示保持原密码不变" class="profile-input" autocomplete="new-password">
      </div>

      <div class="profile-form-foot">
        <button type="button" class="profile-btn-cancel" onclick="closeProfileEditModal()">取消</button>
        <button type="submit" class="profile-btn-submit">保存资料</button>
      </div>
    </form>
  </div>
</div>
<script>
window.openProfileEditModal = function(){
  var m = document.getElementById('profileEditModal');
  if(m) {
    m.style.display = 'flex';
  }
};
window.closeProfileEditModal = function(){
  var m = document.getElementById('profileEditModal');
  if(m) {
    m.style.display = 'none';
  }
};
window.previewAvatar = function(e){
  var file = e && e.target && e.target.files ? e.target.files[0] : null;
  if(!file) return;
  var reader = new FileReader();
  reader.onload = function(evt){
    var box = document.getElementById('editAvatarPreview');
    if(box) box.innerHTML = '<img src="' + evt.target.result + '" alt="preview">';
  };
  reader.readAsDataURL(file);
};
window.previewAvatarUrl = function(url){
  if(!url) return;
  var box = document.getElementById('editAvatarPreview');
  if(box && /^https?:\/\//i.test(url)){
    box.innerHTML = '<img src="' + url + '" alt="preview">';
  }
};
</script>

<!-- 支付/开通确认弹窗 -->
<div id="paymentModal" class="payment-modal" style="display:none;">
  <div class="payment-backdrop" onclick="closePaymentModal()"></div>
  <div class="payment-card">
    <button type="button" class="payment-close" onclick="closePaymentModal()">×</button>
    <div class="payment-head">
      <div class="payment-icon">💳</div>
      <div>
        <h3 id="payModalTitle">确认订单</h3>
        <p id="payModalDesc">请选择支付方式以完成订单</p>
      </div>
    </div>
    <div class="payment-order-info">
      <div class="order-row">
        <span>商品名称</span>
        <strong id="payItemName">月度会员</strong>
      </div>
      <div class="order-row">
        <span>应付金额</span>
        <strong class="pay-amount-text" id="payItemPrice">¥29.90</strong>
      </div>
    </div>
    <div class="pay-methods-list">
      <div class="pay-method-item active">
        <span class="pay-method-icon wx">💬</span>
        <div class="pay-method-meta">
          <b>微信支付</b>
          <small>支持微信扫码 / 快捷支付</small>
        </div>
        <span class="pay-radio">✓</span>
      </div>
      <div class="pay-method-item" onclick="flash('暂未配置此支付通道，推荐微信支付或联系客服购买卡密')">
        <span class="pay-method-icon ali">🌐</span>
        <div class="pay-method-meta">
          <b>支付宝 / 卡密直充</b>
          <small>官方卡密或企业通道</small>
        </div>
        <span class="pay-radio">○</span>
      </div>
    </div>
    <div class="payment-action-wrap">
      <button type="button" class="payment-confirm-btn" onclick="submitUserPayment()">确认前往支付</button>
      <a href="<?=h($site['service_url']?:'/?page=service')?>" class="payment-service-link">人工客服通道 / 对公转账</a>
    </div>
  </div>
</div>

<script>
function openPaymentModal(type, id, name, price) {
  document.getElementById('payItemName').textContent = name;
  document.getElementById('payItemPrice').textContent = '¥' + price;
  document.getElementById('payModalTitle').textContent = type === 'vip' ? '开通 VIP 会员' : '充值创作积分';
  document.getElementById('paymentModal').style.display = 'flex';
}
function closePaymentModal() {
  document.getElementById('paymentModal').style.display = 'none';
}
function submitUserPayment() {
  var serviceUrl = <?=json_encode($site['service_url']?:'/?page=service')?>;
  flash('正在连接支付网关…如需大额或对公充值可直接联系客服');
  setTimeout(function(){
    window.location.href = serviceUrl;
  }, 1000);
}
</script>
<?php }
elseif($page==='community'){
  $commItems = [];
  try{
    $st = db()->query('SELECT g.*, u.username FROM generations g LEFT JOIN users u ON u.id=g.user_id WHERE g.is_public=1 AND g.status="success" ORDER BY g.likes_count DESC, g.id DESC LIMIT 60');
    $commItems = $st->fetchAll();
  }catch(Exception $e){}
?><section class="inner prompt-center">
  <div class="page-intro">
    <span class="eyebrow">COMMUNITY SHOWCASE</span>
    <h1>作品广场</h1>
    <p>探索平台创作者公开的优秀作品，支持点赞与一键同款生成。</p>
  </div>
  <?php if(!$commItems): ?>
    <div class="error-card">
      <strong>空</strong>
      <h3>作品广场正在汇集灵感</h3>
      <p>前往「资产管理」，将你的满意作品设为公开，即可展示在广场中。</p>
      <a class="generate-btn" href="/?page=history">去我的作品</a>
    </div>
  <?php else: ?>
    <div class="prompt-waterfall">
      <?php foreach($commItems as $it): 
        $targetPage = ($it['type']==='video'?'video':($it['type']==='image'?'image':'chat'));
      ?>
      <article class="prompt-card">
        <div class="prompt-cover <?=$it['type']?>">
          <div class="prompt-media">
            <?php if($it['type']==='video' && !empty($it['result_url'])): ?>
              <video src="<?=h($it['result_url'])?>" muted playsinline loop onmouseenter="this.play()" onmouseleave="this.pause()" class="card-video"></video>
            <?php elseif(!empty($it['result_url'])): ?>
              <img src="<?=h($it['result_url'])?>" alt="<?=h(mb_substr($it['prompt'],0,20))?>" class="card-img" loading="lazy">
            <?php else: ?>
              <div style="height:180px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.05);">文本创作</div>
            <?php endif; ?>
          </div>
          <small class="prompt-cat-badge">@<?=h($it['username']?:'创作者')?></small>
        </div>
        <div class="prompt-card-content">
          <p style="font-size:13px;line-height:1.5;margin-bottom:8px;"><?=h(mb_substr($it['prompt'],0,90))?><?=mb_strlen($it['prompt'])>90?'…':''?></p>
          <div class="prompt-card-foot">
            <span class="prompt-tag">❤️ <?=(int)$it['likes_count']?> 赞</span>
            <div class="prompt-actions" style="display:flex;gap:6px;">
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf" value="<?=csrf()?>">
                <input type="hidden" name="action" value="like_generation">
                <input type="hidden" name="generation_id" value="<?=(int)$it['id']?>">
                <button type="submit" class="copy-prompt">👍 点赞</button>
              </form>
              <a href="/?page=<?=$targetPage?>&prompt=<?=urlencode($it['prompt'])?>" class="use-prompt" style="text-decoration:none;display:inline-flex;align-items:center;">⚡ 同款</a>
            </div>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section><?php }
elseif($page==='history'){ $u=user(); if(!$u){ ?>
<section class="inner"><div class="error-card"><strong>未登录</strong><h1>最近对话</h1><p>登录后即可查看你的创作历史。</p><a class="generate-btn" href="/?page=login">立即登录</a></div></section>
<?php }else{ $histItems=[]; try{$st=db()->prepare('SELECT id,type,prompt,result_url,status,is_public,cost,created_at FROM generations WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$st->execute([$u['id']]);$histItems=$st->fetchAll();}catch(Exception $e){} ?>
<section class="inner history-page"><div class="page-intro"><span class="eyebrow">CREATION HISTORY</span><h1>最近对话与资产</h1><p>共 <?=count($histItems)?> 条创作记录，可一键公开发布至作品广场</p></div>
<?php if(!$histItems):?><div class="error-card"><strong>空</strong><h3>还没有创作记录</h3><p>去生成你的第一张图片或视频吧。</p><a class="generate-btn" href="/">去创作</a></div>
<?php else:?><div class="history-list"><?php foreach($histItems as $g):$ru=$g['result_url'];?><div class="history-item"><div class="history-item-head"><b><?=h($g['type'])?></b><em><?=h($g['status'])?></em></div><p><?=h(mb_substr($g['prompt'],0,120))?><?=mb_strlen($g['prompt'])>120?'…':''?></p><div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;"><small><?=h($g['created_at'])?><?php if($ru):?> · <a href="<?=h($ru)?>" target="_blank" rel="noopener">查看大图/视频</a><?php endif;?></small><form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="toggle_public"><input type="hidden" name="generation_id" value="<?=(int)$g['id']?>"><button type="submit" class="action-link" style="border:1px solid #cbd5e1;padding:2px 8px;border-radius:4px;font-size:11px;background:#fff;cursor:pointer;"><?=$g['is_public']?'🔒 设为私密':'🌐 发布到作品广场'?></button></form></div></div><?php endforeach;?></div>
<?php endif;?></section>
<?php } }else{ http_response_code(404); ?>
<section class="inner"><div class="error-card"><strong>404</strong><h1>页面不存在</h1><p>你访问的页面不存在或已下线，请返回首页。</p><a class="generate-btn" href="/">返回首页</a></div></section>
<?php }layout_end();