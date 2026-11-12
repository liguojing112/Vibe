<?php

declare(strict_types=1);

namespace App\Service\Cl\Ai;

use App\Service\Cl\Config\AppConfigService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 大模型调用（OpenAI 兼容协议）。
 *
 * 模型地址 / 模型名 / API Key 优先读后台「AI 模型」配置（`cl_config` 的 `ai.*`），
 * 后台留空时回退 `.env` 的 `AI_*`，这样客户可以自己接任意 OpenAI 兼容服务。
 */
final class DeepSeekClient
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly ConfigInterface $config,
        private readonly AppConfigService $configService,
        private readonly LoggerInterface $logger,
    ) {}

    /** 最近一次失败的原因（成功时清空）。只在同一协程里紧接着读，别跨请求依赖它。 */
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return null|array{content: string, prompt_tokens: int, completion_tokens: int}
     *         失败（没配 key / 超时 / 返回异常）一律返回 null，调用方当作「这次不回」处理
     */
    public function chat(array $messages): ?array
    {
        $this->lastError = null;

        $cfg = (array) $this->config->get('ai', []);
        $apiKey = $this->setting('ai.api_key', (string) ($cfg['api_key'] ?? ''));
        if ($apiKey === '') {
            $this->lastError = 'AI 模型未配置 API Key';
            $this->logger->warning('AI 代聊未配置 API Key，跳过');

            return null;
        }

        $baseUrl = rtrim($this->setting('ai.base_url', (string) ($cfg['base_url'] ?? 'https://api.deepseek.com')), '/');
        $model = $this->setting('ai.model', (string) ($cfg['model'] ?? 'deepseek-chat'));

        try {
            // http_errors=false：4xx 也把响应体拿回来，才能把「Insufficient Balance」
            // 翻译成运营看得懂的「余额不足」；否则只会抛异常、消息里全是原始 JSON。
            $client = $this->clientFactory->create([
                'timeout' => (float) ($cfg['timeout'] ?? 30),
                'http_errors' => false,
            ]);

            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => (int) ($cfg['max_tokens'] ?? 120),
                'temperature' => (float) ($cfg['temperature'] ?? 1.1),
            ];
            if (($cfg['thinking'] ?? 'disabled') === 'disabled') {
                $payload['thinking'] = ['type' => 'disabled'];
            }

            $response = $client->post($baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
            if ($status !== 200) {
                $this->lastError = $this->describeError($status, $body);
                $this->logger->warning('AI 代聊请求失败', ['status' => $status, 'body' => mb_substr($body, 0, 300)]);

                return null;
            }

            $data = json_decode($body, true);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                $this->lastError = 'AI 模型返回了空内容';

                return null;
            }

            return [
                'content' => $this->sanitize($content),
                'prompt_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            ];
        } catch (Throwable $e) {
            // 模型挂了不能影响正常发消息，这里吞掉只记日志
            $message = $e->getMessage();
            $this->lastError = stripos($message, 'Insufficient Balance') !== false
                ? 'AI 模型账户余额不足，请充值后再试'
                : 'AI 模型调用异常：' . mb_substr($message, 0, 120);
            $this->logger->warning('AI 代聊调用异常：' . $message);

            return null;
        }
    }

    /**
     * 把上游的错误翻译成运营看得懂的一句话。
     *
     * 「Insufficient Balance」这种必须显式说成「余额不足」——
     * 之前只记在日志里，后台只显示「生成失败」，客户会以为是软件的问题。
     */
    private function describeError(int $status, string $body): string
    {
        $payload = json_decode($body, true);
        $message = is_array($payload) ? (string) ($payload['error']['message'] ?? '') : '';

        if (stripos($message, 'Insufficient Balance') !== false) {
            return 'AI 模型账户余额不足，请充值后再试';
        }
        if (stripos($message, 'invalid api key') !== false || $status === 401) {
            return 'AI 模型 API Key 无效，请到「AI 模型设置」检查';
        }
        if ($status === 429) {
            return 'AI 模型调用过于频繁，请稍后再试';
        }

        return sprintf('AI 模型返回 %d：%s', $status, mb_substr($message !== '' ? $message : $body, 0, 120));
    }

    /** 后台「AI 模型」配置优先；留空回退 `.env` 的默认值。 */
    private function setting(string $key, string $fallback): string
    {
        $value = trim((string) $this->configService->get($key, ''));

        return $value === '' ? $fallback : $value;
    }

    /** 去掉模型偶尔会加的前后缀（引号、括号里的旁白），并压掉多余换行。 */
    private function sanitize(string $content): string
    {
        // 必须按「字符」裁，不能用 trim($s, "“”「」")：trim 的 charlist 是字节集合，
        // 「」= E3 80 8C 拆开后 8C 会匹配到「完」E5 AE 8C 的末字节，把汉字削成半个字符，
        // 写库时报 1366 Incorrect string value。
        $content = preg_replace('/^[\s"\'“”「」]+|[\s"\'“”「」]+$/u', '', $content) ?? $content;

        // 「（笑）」这类括号旁白容易让对话变味，直接去掉
        $content = preg_replace('/（[^）]{0,10}）|\([^)]{0,10}\)/u', '', $content) ?? $content;

        return trim(preg_replace('/\n{2,}/u', "\n", $content) ?? $content);
    }
}
