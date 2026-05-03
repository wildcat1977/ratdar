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

        $this->model = config('services.claude.model', 'claude-haiku-4-5');
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
            $text     = trim($body['content'][0]['text'] ?? '{}');

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
你是城市環境稽查系統的 AI 守門員。判斷這張圖片是否包含老鼠、老鼠屍體、明顯鼠患或鼠患相關環境髒亂（如垃圾堆、廢棄物堆積、糞便）。

若包含以下任何項目，視為無效：清晰可辨的人臉、不雅/色情內容、血腥暴力（非動物）、與環境衛生完全無關的一般生活照或自拍。

模糊圖片、可疑環境圖片，請給予寬鬆判斷，傾向 valid。

嚴格只輸出 JSON，不得包含任何其他文字：{"is_valid":true,"confidence":0.0}
PROMPT;
    }

    private function poisonPrompt(): string
    {
        return <<<'PROMPT'
你是城市寵物與野生動物保護系統的 AI 守門員。判斷這張圖片是否疑似包含被隨意放置的老鼠藥／毒餌。

可視為有效（valid）的內容包含：
- 散落或放置於地面、盆栽、花圃、騎樓、巷弄角落的彩色顆粒（藍色/綠色/粉紅色蠟塊或穀粒，常見抗凝血劑外觀）
- 標示有「滅鼠」「老鼠藥」「殺鼠」字樣的包裝、紙片、容器
- 黏鼠板、捕鼠籠、誘餌站（bait station）等明顯的鼠害防治用具

若包含以下任何項目，視為無效（is_valid=false）：
- 清晰可辨的人臉、不雅／色情內容
- 與毒餌或鼠害防治完全無關的一般生活照、食物特寫、自拍

模糊圖片、可疑環境圖片，請給予寬鬆判斷，傾向 valid。

嚴格只輸出 JSON，不得包含任何其他文字：{"is_valid":true,"confidence":0.0}
PROMPT;
    }
}
