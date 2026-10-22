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

        // **显式设权限**：Swoole 进程的 umask 不一定是 022（宝塔的进程守护可能给 077），
        // 那样新文件会是 600，Nginx（www）读不到 → 图片 403。
        // 目录同理，确保 755 可遍历。T9.21 生产上就踩到了：头像上传成功但打不开。
        @chmod($dir, 0755);
        @chmod($target, 0644);

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
