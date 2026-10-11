<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Service\Cl\Storage\StorageInterface;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Request;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Validation\ValidationException;

/**
 * 图片上传（docs/05 第 4.2 节）。要登录。
 */
#[OA\HyperfServer(name: 'http')]
final class UploadController
{
    /**
     * scene 取值（docs/05 第 4.2 节）。
     */
    private const SCENES = ['avatar', 'photo', 'chat', 'report', 'real_name'];

    public function __construct(
        private readonly StorageInterface $storage,
    ) {}

    #[Post(
        path: '/api/v1/upload/image',
        operationId: 'clUploadImage',
        summary: '上传图片',
        tags: ['app:upload']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function image(Request $request): ApiResult
    {
        $scene = (string) $request->input('scene', '');
        if (! in_array($scene, self::SCENES, true)) {
            throw ValidationException::withMessages(['scene' => ['scene 取值不正确']]);
        }

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => ['请选择图片']]);
        }

        $path = $this->storage->save($file, $scene);

        return ApiResult::success(['url' => $this->storage->url($path)]);
    }
}
