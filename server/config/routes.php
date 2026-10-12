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
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Router;

Router::get('/', static function () {
    return 'welcome use mineAdmin';
});

Router::get('/favicon.ico', static function () {
    return '';
});

/**
 * WebSocket 握手路由（docs/05 第 5 节）。
 *
 * 路由必须挂在 `ws` 这个名字上，Hyperf 才会用 WebSocket 的 CoreMiddleware 去做 101 升级；
 * 类本身要实现 OnOpen / OnMessage / OnClose 三个接口。
 */
Router::addServer('ws', static function () {
    Router::get('/ws', \App\WebSocket\ChatWsController::class);
});

/**
 * 上传的图片直接读盘返回，让 StorageInterface::url() 给出的地址真的能访问
 * （docs/02 第 2.4 节）。生产环境建议改由 Nginx 托管 server/storage/uploads。
 * 只读 storage/uploads 一个目录，并做了目录穿越防护。
 */
Router::get('/uploads/{path:.+}', static function (Request $request) {
    $root = realpath(BASE_PATH . '/storage/uploads');
    $file = realpath(BASE_PATH . '/storage/uploads/' . (string) $request->route('path'));
    if ($root === false || $file === false
        || ! str_starts_with($file, $root . \DIRECTORY_SEPARATOR)
        || ! is_file($file)) {
        return (new Response())->withStatus(404);
    }

    return (new Response())
        ->withHeader('Content-Type', (string) (mime_content_type($file) ?: 'application/octet-stream'))
        ->withHeader('Cache-Control', 'public, max-age=86400')
        ->withBody(new SwooleStream((string) file_get_contents($file)));
});
