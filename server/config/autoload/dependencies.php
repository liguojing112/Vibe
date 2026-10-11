<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */
use App\Service\Cl\Storage\LocalStorage;
use App\Service\Cl\Storage\StorageInterface;
use App\Service\PassportService;
use Mine\JwtAuth\Interfaces\CheckTokenInterface;
use Mine\Upload\Factory;
use Mine\Upload\UploadInterface;

return [
    UploadInterface::class => Factory::class,
    CheckTokenInterface::class => PassportService::class,
    // 畅撩交友：图片存储，本期用本地磁盘（docs/02 第 2.4 节）
    StorageInterface::class => LocalStorage::class,
];
