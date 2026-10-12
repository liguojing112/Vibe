#!/usr/bin/env php
<?php

use Mine\AppStore\Plugin;

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
ini_set('memory_limit', '1G');

error_reporting(E_ALL);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);
! defined('START_TIME') && define('START_TIME', time());    // 启动时间
! defined('HF_VERSION') && define('HF_VERSION', '3.1');     // 定义hyperf版本号

require BASE_PATH . '/vendor/autoload.php';

/*
 * 时区统一成 Asia/Shanghai。
 *
 * 不设的话 PHP 默认 UTC，而 MySQL 那边用的是宿主机的时区（本机是 +08），
 * 于是「在线」「今天」这类判断会整体偏 8 小时 ——
 * 例如 docs/06 要求会员免费额度按 **Asia/Shanghai 自然日** 重置，UTC 下会变成早上 8 点才重置。
 */
date_default_timezone_set(($_ENV['APP_TIMEZONE'] ?? $_SERVER['APP_TIMEZONE'] ?? '') ?: 'Asia/Shanghai');

// Self-called anonymous function that creates its own scope and keep the global namespace clean.
(function () {
    Plugin::init();
    Hyperf\Di\ClassLoader::init();
    /** @var Psr\Container\ContainerInterface $container */
    $container = require BASE_PATH . '/config/container.php';

    $application = $container->get(Hyperf\Contract\ApplicationInterface::class);
    $application->run();
})();
