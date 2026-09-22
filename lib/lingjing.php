<?php
/**
 * 灵境 AI 服务端客户端。
 * API Key 只从 runtime/lingjing_api_key 读取，绝不返回给浏览器或写入日志。
 */
class LingjingClient
{
    private $baseUrl = 'https://api.lk888.ai/api';
    private $keyFile;

    public function __construct($keyFile = null)
    {
        global $config;
        $this->baseUrl = isset($config['lingjing']['base_url']) ? rtrim($config['lingjing']['base_url'], '/') : $this->baseUrl;
        $this->keyFile = $keyFile ?: (isset($config['lingjing']['key_file']) ? $config['lingjing']['key_file'] : dirname(__DIR__) . '/runtime/lingjing_api_key');
    }

    private function key()
    {
        if (!is_readable($this->keyFile)) {
            throw new RuntimeException('灵境 AI Key 文件不存在或不可读');
        }
        $key = trim(file_get_contents($this->keyFile));
        if ($key === '' || strlen($key) < 20) {
            throw new RuntimeException('灵境 AI Key 未正确配置');
        }
        return $key;
    }

    public function request($method, $path, ?array $payload = null)
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Authorization: Bearer ' . $this->key(), 'Accept: application/json'];
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_HTTPHEADER => $headers];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $error) throw new RuntimeException('灵境 AI 网络请求失败');
        $data = json_decode($body, true);
        if (!is_array($data)) throw new RuntimeException('灵境 AI 返回格式异常');
        if ($status >= 400) {
            $error = isset($data['error']) && is_array($data['error']) ? $data['error'] : $data;
            $type = isset($error['type']) ? $error['type'] : 'api_error';
            $message = isset($error['message']) ? $error['message'] : '远程接口返回错误';
            throw new RuntimeException('灵境 AI 请求失败：' . $type . '，' . $message);
        }
        return $data;
    }

    public function capabilities() { return $this->request('GET', '/v1/skills'); }
    public function guide() { return $this->request('GET', '/v1/skills/guide'); }
    public function balance() { return $this->request('GET', '/v1/skills/balance'); }
    public function models($type = null) { return $this->request('GET', '/v1/skills/models' . ($type ? '?type=' . rawurlencode($type) : '')); }
    public function model($name) { return $this->request('GET', '/v1/skills/models/' . rawurlencode($name)); }
    public function mediaModels($type = 'image') { return $this->request('GET', '/v1/media/models?type=' . rawurlencode($type)); }
    public function modelPricing($name) { return $this->request('GET', '/v1/skills/models/' . rawurlencode((string)$name) . '/pricing'); }
    public function chat(array $payload, $format = 'openai') {
        global $config;
        $settings = function_exists('site_settings') ? site_settings() : [];
        $provider = $settings['model_provider_default'] ?? 'lingjing';

        // 若非灵境 AI，尝试路由至第三方服务商
        if ($provider !== 'lingjing') {
            $thirdResult = $this->dispatchThirdPartyChat($provider, $payload, $settings, $format);
            if ($thirdResult !== null) return $thirdResult;
        }

        if ($format === 'anthropic') {
            if (!isset($payload['max_tokens'])) $payload['max_tokens'] = 2048;
            return $this->request('POST', '/v1/messages', $payload);
        }
        if ($format === 'gemini') {
            $model = $payload['model']; $contents=[];
            foreach (($payload['messages'] ?? []) as $message) {
                $role = $message['role']==='assistant' ? 'model' : 'user';
                $rawContent = $message['content'] ?? '';
                $parts = [];
                if (is_array($rawContent)) {
                    foreach ($rawContent as $part) {
                        if (!is_array($part)) { $parts[]=['text'=>(string)$part]; continue; }
                        $type = $part['type'] ?? '';
                        if ($type === 'text') {
                            $parts[] = ['text' => (string)($part['text'] ?? '')];
                        } elseif ($type === 'image_url') {
                            $url = $part['image_url']['url'] ?? '';
                            if (preg_match('#^data:(image/[a-z+]+);base64,(.+)$#i', $url, $dm)) {
                                $parts[] = ['inline_data'=>['mime_type'=>$dm[1],'data'=>$dm[2]]];
                            } elseif ($url !== '') {
                                $parts[] = ['file_data'=>['file_uri'=>$url,'mime_type'=>'image/jpeg']];
                            }
                        } elseif ($type === 'video_url') {
                            $url = $part['video_url']['url'] ?? '';
                            $mime = $part['video_url']['mime_type'] ?? 'video/mp4';
                            if (preg_match('#^data:(video/[a-z+]+);base64,(.+)$#i', $url, $dm)) {
                                $parts[] = ['inline_data'=>['mime_type'=>$dm[1],'data'=>$dm[2]]];
                            } elseif ($url !== '') {
                                $parts[] = ['file_data'=>['file_uri'=>$url,'mime_type'=>$mime]];
                            }
                        } elseif ($type === 'audio_url') {
                            $url = $part['audio_url']['url'] ?? '';
                            $mime = $part['audio_url']['mime_type'] ?? 'audio/mpeg';
                            if (preg_match('#^data:(audio/[a-z+]+);base64,(.+)$#i', $url, $dm)) {
                                $parts[] = ['inline_data'=>['mime_type'=>$dm[1],'data'=>$dm[2]]];
                            } elseif ($url !== '') {
                                $parts[] = ['file_data'=>['file_uri'=>$url,'mime_type'=>$mime]];
                            }
                        } else {
                            $textVal = $part['text'] ?? json_encode($part, JSON_UNESCAPED_UNICODE);
                            $parts[] = ['text' => (string)$textVal];
                        }
                    }
                } else {
                    $parts[] = ['text' => (string)$rawContent];
                }
                if ($parts) $contents[] = ['role'=>$role,'parts'=>$parts];
            }
            return $this->request('POST', '/v1beta/models/'.rawurlencode($model).':generateContent', ['contents'=>$contents]);
        }
        return $this->request('POST', '/v1/chat/completions', $payload);
    }

    private function dispatchThirdPartyChat($provider, array $payload, array $settings, $format)
    {
        $rtDir = dirname(__DIR__) . '/runtime';
        $endpoint = '';
        $apiKey = '';
        $model = $payload['model'] ?? '';

        if ($provider === 'openai_custom' && !empty($settings['openai_custom_enabled'])) {
            $endpoint = rtrim($settings['openai_custom_base_url'] ?: 'https://api.openai.com/v1', '/') . '/chat/completions';
            $keyFile = $rtDir . '/openai_custom_api_key';
            if (is_readable($keyFile)) $apiKey = trim(file_get_contents($keyFile));
            if (!empty($settings['openai_custom_model']) && empty($payload['model'])) $payload['model'] = $settings['openai_custom_model'];
            return $this->callOpenAIStandard($endpoint, $apiKey, $payload);
        }

        if ($provider === 'deepseek' && !empty($settings['deepseek_enabled'])) {
            $endpoint = rtrim($settings['deepseek_base_url'] ?: 'https://api.deepseek.com/v1', '/') . '/chat/completions';
            $keyFile = $rtDir . '/deepseek_api_key';
            if (is_readable($keyFile)) $apiKey = trim(file_get_contents($keyFile));
            if (!empty($settings['deepseek_model']) && empty($payload['model'])) $payload['model'] = $settings['deepseek_model'];
            return $this->callOpenAIStandard($endpoint, $apiKey, $payload);
        }

        if ($provider === 'qwen' && !empty($settings['qwen_enabled'])) {
            $endpoint = rtrim($settings['qwen_base_url'] ?: 'https://dashscope.aliyuncs.com/compatible-mode/v1', '/') . '/chat/completions';
            $keyFile = $rtDir . '/qwen_api_key';
            if (is_readable($keyFile)) $apiKey = trim(file_get_contents($keyFile));
            if (!empty($settings['qwen_model']) && empty($payload['model'])) $payload['model'] = $settings['qwen_model'];
            return $this->callOpenAIStandard($endpoint, $apiKey, $payload);
        }

        if ($provider === 'claude' && !empty($settings['claude_enabled'])) {
            $base = rtrim($settings['claude_base_url'] ?: 'https://api.anthropic.com', '/');
            $endpoint = $base . '/v1/messages';
            $keyFile = $rtDir . '/claude_api_key';
            if (is_readable($keyFile)) $apiKey = trim(file_get_contents($keyFile));
            if (!empty($settings['claude_model']) && empty($payload['model'])) $payload['model'] = $settings['claude_model'];
            return $this->callClaudeStandard($endpoint, $apiKey, $payload);
        }

        if ($provider === 'gemini' && !empty($settings['gemini_enabled'])) {
            $keyFile = $rtDir . '/gemini_api_key';
            if (is_readable($keyFile)) $apiKey = trim(file_get_contents($keyFile));
            $base = rtrim($settings['gemini_base_url'] ?: 'https://generativelanguage.googleapis.com', '/');
            $targetModel = !empty($settings['gemini_model']) ? $settings['gemini_model'] : ($payload['model'] ?: 'gemini-1.5-flash');
            $endpoint = $base . '/v1beta/models/' . rawurlencode($targetModel) . ':generateContent?key=' . urlencode($apiKey);
            return $this->callGeminiStandard($endpoint, $payload);
        }

        return null;
    }

    private function callOpenAIStandard($endpoint, $apiKey, array $payload)
    {
        if ($apiKey === '') throw new RuntimeException('未配置第三方 API Key');
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('请求第三方 API 超时或网络失败');
        $data = json_decode($body, true);
        if (!is_array($data)) throw new RuntimeException('第三方 API 响应数据异常');
        if ($status >= 400) {
            $msg = $data['error']['message'] ?? ($data['message'] ?? 'HTTP ' . $status);
            throw new RuntimeException('第三方 API 返回错误：' . $msg);
        }
        return $data;
    }

    private function callClaudeStandard($endpoint, $apiKey, array $payload)
    {
        if ($apiKey === '') throw new RuntimeException('未配置 Claude API Key');
        $messages = [];
        $system = '';
        foreach (($payload['messages'] ?? []) as $m) {
            if ($m['role'] === 'system') $system .= ($system ? "
" : '') . $m['content'];
            else $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string)$m['content']];
        }
        $bodyData = [
            'model' => $payload['model'] ?: 'claude-3-5-sonnet-20241022',
            'max_tokens' => $payload['max_tokens'] ?? 4096,
            'messages' => $messages,
        ];
        if ($system !== '') $bodyData['system'] = $system;

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($bodyData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
        $res = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res === false) throw new RuntimeException('请求 Claude API 超时或网络失败');
        $data = json_decode($res, true);
        if ($status >= 400) throw new RuntimeException('Claude API 错误：' . ($data['error']['message'] ?? $res));
        // 格式化兼容 OpenAI 返回结构
        $txt = '';
        if (!empty($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $c) if (($c['type'] ?? '') === 'text') $txt .= $c['text'];
        }
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $txt]]], 'raw' => $data];
    }

    private function callGeminiStandard($endpoint, array $payload)
    {
        $parts = [];
        foreach (($payload['messages'] ?? []) as $m) {
            $parts[] = ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => (string)$m['content']]]];
        }
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_POSTFIELDS => json_encode(['contents' => $parts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
        $res = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res === false) throw new RuntimeException('请求 Gemini API 超时或网络失败');
        $data = json_decode($res, true);
        if ($status >= 400) throw new RuntimeException('Gemini API 错误：' . ($data['error']['message'] ?? $res));
        $txt = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $txt]]], 'raw' => $data];
    }

    public function mediaGenerate(array $payload) { return $this->request('POST', '/v1/media/generate', $payload); }
    public function taskStatus($taskId) { return $this->request('GET', '/v1/skills/task-status?task_id=' . rawurlencode((string)$taskId)); }

    public function rawKeyFilePath() { return $this->keyFile; }
}
