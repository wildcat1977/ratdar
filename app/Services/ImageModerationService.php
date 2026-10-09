<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ImageModerationService
{
    private Client $client;
    private string $model;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'timeout'  => config('services.claude.timeout', 15),
            'headers'  => [
                'x-api-key'         => config('services.claude.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
        ]);

        $this->model = config('services.claude.model', 'claude-haiku-5-5');
    }

    /**
     * 審核圖片路徑（本地暫存路徑或絕對路徑）。
     * 先用 Intervention Image 縮到長邊 ≤ 1024px 再送審，節省 Token 與頻寬。
     *
     * @param  string $imagePath
     * @param  string $type  rat | poison（決定 prompt）
     * @return array{is_valid: bool, confidence: float, reason: string}
     */
    public function moderate(string $imagePath, string $type = 'rat'): array
    {
        try {
            $base64 = $this->prepareBase64($imagePath);

            $prompt = $type === 'poison' ? $this->poisonPrompt() : $this->ratPrompt();

            $payload = [
                'model'      => $this->model,
                'max_tokens' => 64,
                // Haiku 5.5 預設會先 thinking；64 token 會整個被 thinking 吃掉、沒有 text 區塊，
                // 結果靜默變成「通過」。這是單純的是非判斷，關掉 thinking（跟 Haiku 4.5 行為一致）。
                'thinking'   => ['type' => 'disabled'],
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'  => 'image',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => 'image/jpeg',
                                    'data'       => $base64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ];

            $response = $this->client->post('/v1/messages', ['json' => $payload]);
            $body     = json_decode($response->getBody()->getContents(), true);
            $text     = trim(self::extractText($body));

            // Extract JSON even if model wraps it in markdown
            if (preg_match('/\{[^}]+\}/', $text, $m)) {
                $text = $m[0];
            }

            $result = json_decode($text, true);

            $usage = $body['usage'] ?? [];

            Log::channel('moderation')->info('moderation.result', [
                'model'         => $body['model'] ?? $this->model,
                'is_valid'      => (bool) ($result['is_valid'] ?? true),
                'confidence'    => (float) ($result['confidence'] ?? 0.5),
                'raw_text'      => $text,
                'input_tokens'  => $usage['input_tokens']  ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
                'total_tokens'  => isset($usage['input_tokens'], $usage['output_tokens'])
                    ? $usage['input_tokens'] + $usage['output_tokens']
                    : null,
            ]);

            return [
                'is_valid'   => (bool) ($result['is_valid'] ?? true),
                'confidence' => (float) ($result['confidence'] ?? 0.5),
                'reason'     => 'claude',
            ];
        } catch (GuzzleException $e) {
            // API 失敗時預設放行，避免誤擋正常上傳
            Log::channel('moderation')->warning('moderation.api_error', [
                'model'   => $this->model,
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
            ]);
            Log::warning('ImageModerationService: API error', [
                'message' => $e->getMessage(),
            ]);

            return ['is_valid' => true, 'confidence' => 0.0, 'reason' => 'api_error'];
        } catch (\Throwable $e) {
            Log::channel('moderation')->warning('moderation.error', [
                'model'   => $this->model,
                'message' => $e->getMessage(),
            ]);
            Log::warning('ImageModerationService: unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return ['is_valid' => true, 'confidence' => 0.0, 'reason' => 'error'];
        }
    }

    /** @param  array<string, mixed>  $body */
    public static function extractText(array $body): string
    {
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                return $block['text'];
            }
        }

        return '{}';
    }

    /**
     * 將圖片縮小至長邊 ≤ 1024px 後轉為 JPEG Base64。
     */
    private function prepareBase64(string $imagePath): string
    {
        $manager = new \Intervention\Image\ImageManager(
            new \Intervention\Image\Drivers\Gd\Driver()
        );

        $image = $manager->decode($imagePath);

        // Scale down if larger than 1024px on longest side
        $w = $image->width();
        $h = $image->height();
        if ($w > 1024 || $h > 1024) {
            if ($w >= $h) {
                $image->scale(width: 1024);
            } else {
                $image->scale(height: 1024);
            }
        }

        $jpeg = $image->encode(new \Intervention\Image\Encoders\JpegEncoder(quality: 70));

        return base64_encode($jpeg->toString());
    }

    private function ratPrompt(): string
    {
        return <<<'PROMPT'
<task>
你是城市環境衛生稽查系統的 AI 審核員。判斷這張圖片是否與鼠患通報相關，應被接受。
</task>

<accept_if>
以下任一情況請接受（is_valid=true）：
- 圖中出現老鼠或老鼠屍體，無論距離遠近、體型大小、是否清晰
- 老鼠出現在畫面角落、背景或僅占畫面一小部分
- 夜晚街景、巷弄、騎樓、下水道等城市環境照片，即使主體不突出
- 垃圾堆積、廢棄物、糞便等鼠患相關環境髒亂
- 模糊、逆光、光線昏暗但場景與城市環境衛生相關
</accept_if>

<reject_if>
以下情況才拒絕（is_valid=false）：
- 清晰可辨的人臉
- 不雅或色情內容
- 明確與鼠患及城市環境衛生完全無關的照片（如：純風景、室內自拍、食物特寫）
</reject_if>

<guidance>
審核標準應寬鬆，傾向接受。寧可接受模糊相關，也不誤擋真實通報。
confidence 代表你對這次判斷的把握度（0.0–1.0）。若場景模糊難判，請給低 confidence。
</guidance>

<output>
嚴格只輸出 JSON，不得包含任何其他文字：{"is_valid":true,"confidence":0.9}
</output>
PROMPT;
    }

    private function poisonPrompt(): string
    {
        return <<<'PROMPT'
<task>
你是城市寵物與野生動物保護系統的 AI 審核員。判斷這張圖片是否疑似包含被隨意放置的老鼠藥／毒餌。
</task>

<accept_if>
以下任一情況請接受（is_valid=true）：
- 地面、盆栽、花圃、騎樓、巷弄角落的彩色顆粒（藍/綠/粉紅色蠟塊或穀粒，常見抗凝血劑外觀）
- 標示有「滅鼠」「老鼠藥」「殺鼠」字樣的包裝、紙片、容器
- 黏鼠板、捕鼠籠、誘餌站（bait station）等鼠害防治用具
- 疑似毒餌的物品，即使不確定也算
</accept_if>

<reject_if>
以下情況才拒絕（is_valid=false）：
- 清晰可辨的人臉
- 不雅或色情內容
- 明確與毒餌或鼠害防治完全無關的照片（如：純食物、自拍、室內生活照）
</reject_if>

<guidance>
審核標準應寬鬆，傾向接受。寧可接受模糊相關，也不誤擋真實通報。
confidence 代表你對這次判斷的把握度（0.0–1.0）。若場景模糊難判，請給低 confidence。
</guidance>

<output>
嚴格只輸出 JSON，不得包含任何其他文字：{"is_valid":true,"confidence":0.9}
</output>
PROMPT;
    }
}
