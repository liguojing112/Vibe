<?php

declare(strict_types=1);

namespace App\Service\Cl\Ai;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 大模型调用（OpenAI 兼容协议）。
 *
 * 只做一件事：给一组 messages，返回一段话 + token 用量。
 * 人设怎么拼、什么时候调用，都在 [AiChatService] 里。
 */
final class DeepSeekClient
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return null|array{content: string, prompt_tokens: int, completion_tokens: int}
     *         失败（没配 key / 超时 / 返回异常）一律返回 null，调用方当作「这次不回」处理
     */
    public function chat(array $messages): ?array
    {
        $cfg = (array) $this->config->get('ai', []);
        $apiKey = (string) ($cfg['api_key'] ?? '');
        if ($apiKey === '') {
            $this->logger->warning('AI 代聊未配置 AI_API_KEY，跳过');

            return null;
        }

        $baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://api.deepseek.com'), '/');

        try {
            $client = $this->clientFactory->create(['timeout' => (float) ($cfg['timeout'] ?? 30)]);

            $payload = [
                'model' => (string) ($cfg['model'] ?? 'deepseek-chat'),
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
                $this->logger->warning('AI 代聊请求失败', ['status' => $status, 'body' => mb_substr($body, 0, 300)]);

                return null;
            }

            $data = json_decode($body, true);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                return null;
            }

            return [
                'content' => $this->sanitize($content),
                'prompt_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            ];
        } catch (Throwable $e) {
            // 模型挂了不能影响正常发消息，这里吞掉只记日志
            $this->logger->warning('AI 代聊调用异常：' . $e->getMessage());

            return null;
        }
    }

    /** 去掉模型偶尔会加的前后缀（引号、括号里的旁白），并压掉多余换行。 */
    private function sanitize(string $content): string
    {
        $content = trim($content, " \t\n\r\"'“”「」");

        // 「（笑）」这类括号旁白容易让对话变味，直接去掉
        $content = preg_replace('/（[^）]{0,10}）|\([^)]{0,10}\)/u', '', $content) ?? $content;

        return trim(preg_replace('/\n{2,}/u', "\n", $content) ?? $content);
    }
}
