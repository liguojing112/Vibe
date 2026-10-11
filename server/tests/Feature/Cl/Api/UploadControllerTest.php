<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 图片上传（docs/05 第 4.2 节、docs/02 第 2.4 节）。
 *
 * @internal
 * @coversNothing
 */
final class UploadControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private const PHONE = '13600136000';

    private const PASSWORD = 'abc12345';

    private Client $client;

    private string $token = '';

    /**
     * @var string[] 测试结束要删掉的临时文件
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->clearLoginRate();
        $this->clearUsers();

        $registered = $this->client->post('/api/v1/auth/register', [
            'phone' => self::PHONE,
            'password' => self::PASSWORD,
            'gender' => User::GENDER_MALE,
            'nickname' => '上传测试',
        ]);
        $this->token = (string) $registered['data']['access_token'];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->clearUsers();
        $this->clearLoginRate();
        parent::tearDown();
    }

    public function testUploadJpgSuccess(): void
    {
        $result = $this->upload($this->makeJpeg(), 'avatar');

        self::assertSame(200, $result['code']);
        $url = (string) $result['data']['url'];
        self::assertStringContainsString('/uploads/cl/', $url);
        self::assertStringEndsWith('.jpg', $url);
    }

    public function testFakeJpgFileIsRejected(): void
    {
        // 内容不是图片，只是把后缀改成了 .jpg
        $result = $this->upload($this->makeTextFile('fake.jpg', 'this is definitely not an image'), 'avatar');

        self::assertSame(30004, $result['code']);
        self::assertStringContainsString('只支持 jpg / png / webp', (string) $result['message']);
    }

    public function testTooLargeImageIsRejected(): void
    {
        // 6MB，配置 chat.image_max_mb 默认是 5
        $result = $this->upload($this->makeTextFile('big.jpg', str_repeat('a', 6 * 1024 * 1024)), 'chat');

        self::assertSame(30004, $result['code']);
        self::assertStringContainsString('不能超过 5MB', (string) $result['message']);
    }

    public function testUploadWithoutTokenReturns401(): void
    {
        $result = $this->client->file('/api/v1/upload/image?scene=avatar', [
            'name' => 'file',
            'file' => $this->makeJpeg(),
        ]);

        self::assertSame(401, $result['code']);
    }

    public function testInvalidSceneReturns422(): void
    {
        self::assertSame(422, $this->upload($this->makeJpeg(), 'not-a-scene')['code']);
    }

    /**
     * @return array<string,mixed>
     */
    private function upload(string $file, string $scene): array
    {
        return $this->client->file('/api/v1/upload/image?scene=' . $scene, [
            'name' => 'file',
            'file' => $file,
        ], ['Authorization' => 'Bearer ' . $this->token]);
    }

    /**
     * 生成一张真实的小 JPEG（用 GD，保证 finfo 认得出 image/jpeg）。
     */
    private function makeJpeg(): string
    {
        $file = BASE_PATH . '/runtime/test-upload-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $file);
        imagedestroy($image);
        $this->tempFiles[] = $file;

        return $file;
    }

    private function makeTextFile(string $name, string $contents): string
    {
        $file = BASE_PATH . '/runtime/test-upload-' . uniqid() . '-' . $name;
        file_put_contents($file, $contents);
        $this->tempFiles[] = $file;

        return $file;
    }

    private function clearUsers(): void
    {
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }

    private function clearLoginRate(): void
    {
        $redis = make(Redis::class);
        $keys = $redis->keys('cl:login_rate:*');
        if (! empty($keys)) {
            $redis->del(...$keys);
        }
    }
}
