<?php

declare(strict_types=1);

namespace HyperfTests\Unit\Cl\Ai;

use App\Service\Cl\Ai\DeepSeekClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 模型回复的首尾裁剪。
 *
 * 线上踩过：`trim($s, "“”「」")` 按字节匹配，把「完」「呀」这类汉字的末字节
 * （8C / 80，正来自「」「”」的拆分字节）一起削掉，留下残缺 UTF-8，
 * 写 cl_message 时报 1366 Incorrect string value。
 *
 * @internal
 * @coversNothing
 */
final class DeepSeekClientSanitizeTest extends TestCase
{
    public function testKeepsChineseWhoseLastByteLooksLikeFullWidthPunctuation(): void
    {
        foreach (['对啊 刚忙完', '刚说过了呀', '我在想你呢', '好想见你'] as $text) {
            self::assertSame($text, $this->sanitize($text));
            self::assertTrue(mb_check_encoding($text, 'UTF-8'));
        }
    }

    public function testStripsWrappingQuotesAndBrackets(): void
    {
        self::assertSame('你好', $this->sanitize('"你好"'));
        self::assertSame('晚安', $this->sanitize('「晚安」'));
        self::assertSame('早', $this->sanitize('“早”'));
        self::assertSame('刚忙完', $this->sanitize('  「刚忙完」  '));
    }

    public function testDropsParentheticalAside(): void
    {
        self::assertSame('我来了', $this->sanitize('我来了（笑）'));
        self::assertSame('好的', $this->sanitize('好的(微笑)'));
    }

    public function testValidUtf8SurvivesWithEmojiInside(): void
    {
        $text = '好开心😊';
        self::assertSame($text, $this->sanitize($text));
        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    private function sanitize(string $content): string
    {
        $client = (new \ReflectionClass(DeepSeekClient::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(DeepSeekClient::class, 'sanitize');
        $method->setAccessible(true);

        return $method->invoke($client, $content);
    }
}
