<?php

declare(strict_types=1);

namespace App\Service\Cl\Storage;

use Hyperf\HttpMessage\Upload\UploadedFile;

/**
 * 图片存储抽象（docs/02 第 2.3、2.4 节）。
 * 本期只实现本地磁盘；以后换 OSS/COS 只需换实现。
 */
interface StorageInterface
{
    /**
     * 保存图片，返回相对路径（例如 cl/202610/ab12cd....jpg）。
     */
    public function save(UploadedFile $file, string $scene): string;

    /**
     * 相对路径 → App 能直接访问的 URL。
     */
    public function url(string $path): string;
}
