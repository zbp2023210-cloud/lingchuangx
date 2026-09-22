<?php
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
// API 端点：禁止把 PHP 提示/弃用噪声输出到响应体，避免污染 JSON（错误仍写入错误日志）
@ini_set('display_errors', '0');
ob_start();
$action = $_GET['action'] ?? '';
try {
    if (!in_array($action, ['capabilities','guide','balance','models','model','model-pricing','media-models','chat','media-generate','task-status','save','upload','daily-checkin','agent-chat','render-announcement','prompt-extract','resolve-video-link','video-transcript'], true)) { http_response_code(404); echo json_encode(['error'=>'unknown_action'], JSON_UNESCAPED_UNICODE); exit; }
    if (in_array($action, ['balance','chat','media-generate','task-status','save','daily-checkin','agent-chat','prompt-extract','resolve-video-link','video-transcript'], true)) require_api_user();
    if ($action === 'upload') { if(!admin_user()) throw new InvalidArgumentException('后台登录已过期，请重新登录后再上传'); $result=handle_upload(); }
    elseif ($action === 'daily-checkin') { $result=ensure_user_checkin((int)user()['id']); if(!$result) throw new RuntimeException('签到失败，请稍后重试'); }
    elseif ($action === 'render-announcement') {
        $id = (int)($_GET['id'] ?? 0);
        $s = db()->prepare('SELECT * FROM system_announcements WHERE id=? LIMIT 1');
        $s->execute([$id]);
        $ann = $s->fetch();
        if(!$ann) throw new InvalidArgumentException('公告不存在');
        $result = ['html' => render_rich_announcement_content($ann['content'])];
    }
    elseif ($action === 'capabilities') $result=lingjing()->capabilities();
    elseif ($action === 'guide') $result=lingjing()->guide();
    elseif ($action === 'balance') $result=lingjing()->balance();
    elseif ($action === 'models') $result=lingjing()->models($_GET['type'] ?? null);
    elseif ($action === 'model') { $name=$_GET['name']??''; if(!$name) throw new InvalidArgumentException('model name 必填'); $result=lingjing()->model($name); }
    elseif ($action === 'model-pricing') { $name=$_GET['name']??''; if(!$name) throw new InvalidArgumentException('model name 必填'); $result=lingjing()->modelPricing($name); }
    elseif ($action === 'media-models') $result=lingjing()->mediaModels($_GET['type'] ?? 'image');
    elseif ($action === 'task-status') { $id=$_GET['task_id']??''; if(!$id) throw new InvalidArgumentException('task_id 必填'); $result=lingjing()->taskStatus($id); }
    elseif ($action === 'agent-chat') {
        $body=json_decode(file_get_contents('php://input'),true);
        if(!is_array($body)) throw new InvalidArgumentException('请求体必须是 JSON');
        $agent=agent_config($body['agent_key']??'ecommerce-image');
        if(!$agent||(int)$agent['enabled']!==1)throw new InvalidArgumentException('智能体不存在或已停用');
        $prompt=trim((string)($body['prompt']??''));
        if($prompt==='')throw new InvalidArgumentException('请输入商品或创作需求描述');
        $model=trim((string)($body['model']??''))?:trim((string)$agent['model']);
        if($model==='')$model='tt-5.6-luna';
        $format=$body['_format']??'openai';
        $extra='';
        if(!empty($body['platforms'])&&is_array($body['platforms']))$extra.="【目标平台】：".implode('、',$body['platforms'])."\n";
        if(!empty($body['deliverables'])&&is_array($body['deliverables']))$extra.="【交付内容】：".implode('、',$body['deliverables'])."\n";
        if(!empty($body['channels'])&&is_array($body['channels']))$extra.="【用途渠道】：".implode('、',$body['channels'])."\n";
        if(!empty($body['categories'])&&is_array($body['categories']))$extra.="【餐饮品类】：".implode('、',$body['categories'])."\n";
        if(!empty($body['focuses'])&&is_array($body['focuses']))$extra.="【出图重点】：".implode('、',$body['focuses'])."\n";
        if(!empty($body['aspect_ratio']))$extra.="【画面方向/比例】：".$body['aspect_ratio']."\n";
        if(!empty($body['style']))$extra.="【画面风格】：".$body['style']."\n";
        if(!empty($body['styles'])&&is_array($body['styles']))$extra.="【画面风格】：".implode('、',$body['styles'])."\n";
        if(!empty($body['images'])&&is_array($body['images']))$extra.="【已上传参考素材】：".count($body['images'])." 张\n";
        $fullUserPrompt=($extra?($extra."\n【需求详情】：\n"):"").$prompt;
        $payload=['model'=>$model,'messages'=>[['role'=>'system','content'=>$agent['system_prompt']],['role'=>'user','content'=>$fullUserPrompt]],'stream'=>false];
        $result=lingjing()->chat($payload,$format);
    }
    elseif ($action === 'resolve-video-link') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) throw new InvalidArgumentException('请求体必须是 JSON');
        $url = trim((string)($body['url'] ?? ''));
        if ($url === '') throw new InvalidArgumentException('请输入视频链接');
        $resolved = resolve_video_link($url);
        $result = ['video_url'=>$resolved['video_url'],'title'=>$resolved['title']??'','cover'=>$resolved['cover']??'','platform'=>$resolved['platform']??'other'];
    }
    elseif ($action === 'video-transcript') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) throw new InvalidArgumentException('请求体必须是 JSON');
        $url = trim((string)($body['url'] ?? ''));
        if ($url === '') throw new InvalidArgumentException('请输入视频链接');
        $lang = (($body['lang'] ?? 'zh') === 'en') ? 'en' : 'zh';
        $model = trim((string)($body['model'] ?? ''));
        $allowedModels = [];
        foreach (transcript_gemini_models() as $pm) { $allowedModels[$pm['name']] = true; }
        if ($model === '' || !isset($allowedModels[$model])) { $model = (transcript_gemini_models()[0]['name'] ?? 'gem-3.8-flash'); }
        // Step 1: Resolve link
        $resolved = resolve_video_link($url);
        $videoUrl = $resolved['video_url'];
        if ($videoUrl === '') throw new RuntimeException('无法获取视频地址，请更换链接重试');
        // Step 2: Build Gemini multimodal payload
        $sysPrompt = $lang === 'en'
            ? "You are a professional video transcription assistant. The user provides a video file or URL. Transcribe ALL spoken content accurately, preserving speaker turns and paragraph breaks. Output ONLY the transcript text with no commentary, no Markdown fences, no preamble. If the video has no speech, describe what is shown instead."
            : "你是一位专业的视频语音转文字助手。用户会提供一段视频，请准确识别视频中所有语音内容并转为文字稿。保留说话人切换和段落分隔。只输出转录文本，不要输出任何解释、Markdown 代码块或开场白。如果视频没有语音，请描述画面内容。";
        $userText = $lang === 'en'
            ? 'Please transcribe all spoken content in this video.'
            : '请转录这段视频中的所有语音内容，生成完整的文字稿。';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role'=>'system','content'=>$sysPrompt],
                ['role'=>'user','content'=>[
                    ['type'=>'text','text'=>$userText],
                    ['type'=>'video_url','video_url'=>['url'=>$videoUrl,'mime_type'=>'video/mp4']],
                ]],
            ],
            'stream' => false,
        ];
        $resp = lingjing()->chat($payload, 'gemini');
        // Extract text from Gemini response
        $text = '';
        if (isset($resp['candidates'][0]['content']['parts'])) {
            foreach ($resp['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) $text .= $part['text'];
            }
        }
        $text = prompt_extract_clean_output($text);
        if ($text === '') throw new RuntimeException('AI 未能识别该视频的语音内容，请确认视频包含可识别的语音后重试');
        $result = ['transcript'=>$text,'title'=>$resolved['title']??'','platform'=>$resolved['platform']??'other','model'=>$model];
    }
    elseif ($action === 'prompt-extract') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) throw new InvalidArgumentException('请求体必须是 JSON');
        $mode = in_array($body['mode'] ?? '', ['image','video'], true) ? $body['mode'] : 'image';
        $lang = (($body['lang'] ?? 'zh') === 'en') ? 'en' : 'zh';
        $style = text_limit(trim((string)($body['style'] ?? '')), 80);
        $model = trim((string)($body['model'] ?? ''));
        $allowedModels = [];
        foreach (prompt_extract_models() as $pm) { $allowedModels[$pm['name']] = true; }
        if ($model === '' || !isset($allowedModels[$model])) { $model = prompt_extract_default_model(); }
        $images = prompt_extract_normalize_images($body['images'] ?? [], $mode);
        if (!$images) { throw new InvalidArgumentException($mode === 'video' ? '未读取到有效的视频帧，请重新上传视频' : '未读取到有效的图片，请重新上传图片'); }
        $content = [['type'=>'text','text'=>prompt_extract_user_instruction($mode, count($images))]];
        foreach ($images as $idx => $dataUrl) {
            if ($mode === 'video') { $content[] = ['type'=>'text','text'=>'【第 '.($idx+1).' 帧 / 共 '.count($images).' 帧】']; }
            $content[] = ['type'=>'image_url','image_url'=>['url'=>$dataUrl]];
        }
        $payload = [
            'model' => $model,
            'messages' => [
                ['role'=>'system','content'=>prompt_extract_system_prompt($mode, $lang, $style)],
                ['role'=>'user','content'=>$content],
            ],
            'stream' => false,
        ];
        $resp = lingjing()->chat($payload, 'openai');
        $text = $resp['choices'][0]['message']['content'] ?? '';
        if (is_array($text)) { $parts = []; foreach ($text as $seg) { if (is_string($seg)) $parts[] = $seg; elseif (is_array($seg) && isset($seg['text'])) $parts[] = (string)$seg['text']; } $text = implode("\n", $parts); }
        $text = prompt_extract_clean_output($text);
        if ($text === '') {
            $reason = $resp['choices'][0]['message']['reasoning_content'] ?? '';
            if (is_string($reason)) { $text = prompt_extract_clean_output($reason); }
        }
        if ($text === '') { throw new RuntimeException('AI 未返回有效提示词，请更换素材或模型后重试'); }
        $result = ['prompt'=>$text,'mode'=>$mode,'model'=>$model,'lang'=>$lang,'frames'=>count($images)];
    }
    elseif ($action === 'chat') { $body=json_decode(file_get_contents('php://input'),true); if(!is_array($body)) throw new InvalidArgumentException('请求体必须是 JSON'); $format=$body['_format']??'openai';unset($body['_format']);$result=lingjing()->chat($body,$format); }
    elseif ($action === 'media-generate') { $body=json_decode(file_get_contents('php://input'),true); if(!is_array($body)) throw new InvalidArgumentException('请求体必须是 JSON'); if(!empty($_SESSION['media_task_id'])){try{$old=lingjing()->taskStatus($_SESSION['media_task_id']);if(empty($old['is_final'])){$result=['task_id'=>(int)$_SESSION['media_task_id'],'reused'=>true];}else{unset($_SESSION['media_task_id']);}}catch(Throwable $ignore){$result=['task_id'=>(int)$_SESSION['media_task_id'],'reused'=>true];}} if(!isset($result)){ $created=lingjing()->mediaGenerate($body); $result=$created; $newId=$created['data']['task_id']??null; if($newId!==null)$_SESSION['media_task_id']=(string)$newId; } }
    else { $body=json_decode(file_get_contents('php://input'),true); if(!is_array($body)) throw new InvalidArgumentException('请求体必须是 JSON'); $prompt=trim($body['prompt']??'');$type=$body['type']??'text';$url=trim($body['result_url']??'');if($prompt===''||!in_array($type,['text','image','video','audio'],true))throw new InvalidArgumentException('保存参数不完整');$s=db()->prepare('INSERT INTO generations(user_id,type,prompt,result_url,status,cost,created_at) VALUES(?,?,?,?,"completed",0,NOW())');$s->execute([user()['id'],$type,$prompt,$url]);$result=['saved'=>true,'id'=>db()->lastInsertId()]; }
    ob_clean(); echo json_encode(['ok'=>true,'data'=>$result], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (preg_match('/concurrency|concurrent|rate[ ._-]*limit|too many/i', $message)) {
        $message = '当前已有生成任务正在处理中，请等待任务完成后再生成。';
        http_response_code(409);
    } else {
        http_response_code($e instanceof InvalidArgumentException ? 400 : 502);
    }
    ob_clean(); echo json_encode(['ok'=>false,'error'=>'request_failed','message'=>$message], JSON_UNESCAPED_UNICODE);
}
