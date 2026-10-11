<?php

declare(strict_types=1);

namespace App\Service\Cl\Storage;

use App\Exception\ClBusinessException;
use App\Model\Enums\Cl\BizCode;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\Support\env;

/**
 * 本地磁盘存储（docs/02 第 2.4 节）：
 * 存到 server/storage/uploads/cl/{yyyymm}/{随机名}.{ext}，只允许 jpg/png/webp，按真实文件类型校验。
 */
class LocalStorage implements StorageInterface
{
    /**
     * 允许的「真实」MIME → 后缀。改后缀不算数，必须用 finfo 读真实类型。
     */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        protected readonly AppConfigService $configService,
    ) {}

    public function save(UploadedFile $file, string $scene): string
    {
        if (! $file->isValid()) {
            throw new ClBusinessException(BizCode::INVALID_MESSAGE_CONTENT, '图片上传失败，请重试');
        }

        $maxMb = (int) $this->configService->get('chat.image_max_mb', 5);
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            throw new ClBusinessException(BizCode::INVALID_MESSAGE_CONTENT, "图片不能超过 {$maxMb}MB");
        }

        $mime = (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname()) ?: '';
        $ext = self::ALLOWED_MIME[$mime] ?? null;
        if ($ext === null) {
            throw new ClBusinessException(BizCode::INVALID_MESSAGE_CONTENT, '只支持 jpg / png / webp 图片');
        }

        $relative = 'cl/' . date('Ym') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $this->root() . '/' . $relative;
        $dir = \dirname($target);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->moveTo($target);

        return $relative;
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl(), '/') . '/uploads/' . ltrim($path, '/');
    }

    /**
     * 上传根目录（也是静态文件路由映射的目录）。
     */
    public function root(): string
    {
        return BASE_PATH . '/storage/uploads';
    }

    private function baseUrl(): string
    {
        return (string) env('APP_URL', 'http://127.0.0.1:9501');
    }
}
