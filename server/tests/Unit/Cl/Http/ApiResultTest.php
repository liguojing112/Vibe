<?php

declare(strict_types=1);

namespace HyperfTests\Unit\Cl\Http;

use App\Http\Api\ApiResult;
use App\Model\Enums\Cl\BizCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class ApiResultTest extends TestCase
{
    public function testSuccess(): void
    {
        $result = ApiResult::success(['id' => 1]);
        self::assertSame(
            ['code' => 200, 'message' => '成功', 'data' => ['id' => 1]],
            $result->toArray()
        );
    }

    public function testErrorUsesBizCode(): void
    {
        $result = ApiResult::error(BizCode::COIN_NOT_ENOUGH, null, ['balance' => 4, 'needed' => 5]);
        $array = $result->toArray();
        self::assertSame(20001, $array['code']);
        self::assertSame('金币余额不足', $array['message']);
        self::assertSame(['balance' => 4, 'needed' => 5], $array['data']);
    }

    public function testErrorWithCustomMessage(): void
    {
        $result = ApiResult::error(BizCode::ACCOUNT_LOCKED, '密码错误次数过多，已锁定 15 分钟');
        self::assertSame(10003, $result->toArray()['code']);
        self::assertSame('密码错误次数过多，已锁定 15 分钟', $result->toArray()['message']);
    }

    public function testPage(): void
    {
        $result = ApiResult::page([['id' => 1]], 120, 1, 20);
        self::assertSame(
            [
                'code' => 200,
                'message' => '成功',
                'data' => [
                    'list' => [['id' => 1]],
                    'total' => 120,
                    'page' => 1,
                    'page_size' => 20,
                    'has_more' => true,
                ],
            ],
            $result->toArray()
        );
    }

    public function testPageLastPageHasNoMore(): void
    {
        $result = ApiResult::page([], 20, 1, 20);
        self::assertFalse($result->toArray()['data']['has_more']);
    }

    public function testCursor(): void
    {
        $result = ApiResult::cursor([['id' => 9]], false);
        self::assertSame(
            ['list' => [['id' => 9]], 'has_more' => false],
            $result->toArray()['data']
        );
    }
}
