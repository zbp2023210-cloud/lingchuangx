<?php
/**
 * 灵创AI提示词广场每日同步 CLI。
 * 默认同步灵境公开灵感广场，不读取或记录登录 Token。
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require __DIR__ . '/lib/bootstrap.php';

$pages = max(1, min(50, (int)(getenv('LINGCHUANGX_PROMPT_SYNC_PAGES') ?: 10)));
$type = getenv('LINGCHUANGX_PROMPT_SYNC_TYPE') ?: '';
if (!in_array($type, ['', 'image', 'video'], true)) $type = '';
$logFile = __DIR__ . '/runtime/prompt_sync.log';
$lockFile = __DIR__ . '/runtime/prompt_sync.lock';

function sync_log($message) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    sync_log('已有同步任务运行，跳过本次执行');
    exit(0);
}

try {
    $pdo = db();
    $added = 0;
    $updated = 0;
    $total = 0;
    $skip = ['bad_id'=>0, 'type'=>0, 'no_cover'=>0, 'bad_title'=>0, 'short_text'=>0, 'keyword'=>0, 'generic_portrait'=>0];

    for ($page = 0; $page < $pages; $page++) {
        $items = sync_public_page($page, $type);
        if ($items === null) break;
        foreach ($items as $item) {
            $total++;
            $sourceId = (int)($item['id'] ?? 0);
            if ($sourceId <= 0) { $skip['bad_id']++; continue; }
            $itemType = in_array($item['作品类型'] ?? 'image', ['image', 'video'], true) ? $item['作品类型'] : 'image';
            if ($type !== '' && $itemType !== $type) { $skip['type']++; continue; }
            $category = is_array($item['分类'] ?? null) ? implode(',', $item['分类']) : '';
            $fullTitle = trim((string)($item['作品标题'] ?? ''));
            if ($fullTitle === '' || $fullTitle === '无') $fullTitle = '灵感作品 #' . $sourceId;
            $title = mb_strlen($fullTitle, 'UTF-8') > 24 ? mb_substr($fullTitle, 0, 24, 'UTF-8') . '…' : $fullTitle;
            $cover = trim((string)($item['作品链接'] ?? ''));
            $text = trim((string)($item['提示词'] ?? ($item['prompt'] ?? '')));
            if ($text === '') $text = $fullTitle;
            if ($cover === '' || !preg_match('/^https?:\/\//i', $cover)) { $skip['no_cover']++; continue; }
            if ($title === '' || mb_strlen($title, 'UTF-8') < 2 || preg_match('/^(1|2|3|4|5|6|7|8|9|0|无|未|test|demo|示例|默认|空白|blank)$/iu', trim($title))) { $skip['bad_title']++; continue; }
            $low = mb_strtolower($title . ' ' . $text, 'UTF-8');
            if (mb_strlen($text, 'UTF-8') < 20) { $skip['short_text']++; continue; }
            if (preg_match('/\b(ai ppt|ppt|测试|test|demo|样例)\b/iu', $low)) { $skip['keyword']++; continue; }
            if ($itemType === 'image' && preg_match('/(^|\s)(学生|美女|人像摄影)(\s|$)/u', $title) && mb_strlen($text, 'UTF-8') < 120) { $skip['generic_portrait']++; continue; }

            $exists = $pdo->prepare('SELECT id FROM prompts WHERE source=? AND source_id=? LIMIT 1');
            $exists->execute(['lingjingx', $sourceId]);
            $existingId = (int)$exists->fetchColumn();
            if ($existingId > 0) {
                $stmt = $pdo->prepare('UPDATE prompts SET type=?,cat=?,title=?,text=?,cover_url=?,points=0,enabled=1,updated_at=NOW() WHERE id=?');
                $stmt->execute([$itemType, $category, $title, $text, $cover, $existingId]);
                $updated++;
            } else {
                $stmt = $pdo->prepare('INSERT INTO prompts(type,cat,title,text,cover_url,points,source,source_id,enabled,created_at,updated_at) VALUES(?,?,?,?,?,0,?,?,1,NOW(),NOW())');
                $stmt->execute([$itemType, $category, $title, $text, $cover, 'lingjingx', $sourceId]);
                $added++;
            }
        }
    }
    $skipped = array_sum($skip);
    sync_log("同步完成：拉取 {$total} 条，新增 {$added} 条，更新 {$updated} 条，过滤 {$skipped} 条；页数 {$pages}" . ($type !== '' ? "；类型 {$type}" : '；公开全部类型'));
    sync_log('过滤明细：' . json_encode($skip, JSON_UNESCAPED_UNICODE));
    exit(0);
} catch (Throwable $e) {
    sync_log('同步失败：' . $e->getMessage());
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

function sync_public_page($page, $type = '') {
    $payload = ['page' => $page];
    if (in_array($type, ['image', 'video'], true)) $payload['作品类型'] = $type;
    $ch = curl_init('https://api.lingkeai.ai/linggan/shouye_guangchang');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: LingchuangX-PromptSync/1.0'],
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) throw new RuntimeException('同步网络请求失败' . ($error ? '：' . $error : ''));
    $data = json_decode($body, true);
    if (!is_array($data) || ($data['code'] ?? 0) !== 200) {
        throw new RuntimeException('同步接口返回错误：HTTP ' . $httpCode . '；' . ($data['msg'] ?? '未知错误'));
    }
    $list = $data['data']['list'] ?? [];
    return is_array($list) && $list ? $list : null;
}
