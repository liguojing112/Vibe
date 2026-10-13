<?php

declare(strict_types=1);

/**
 * Hyperf 定时任务（docs/02 第 2.4 节：用 Hyperf 自己的 crontab，不靠宿主机的 cron）。
 *
 * `enable` 必须是 true：`CrontabRegisterListener` 读的就是它，缺这个文件（或写成 false）时
 * **所有 `#[Crontab]` 都不会注册**，而且不报错 —— 属于「静默不生效」的那类坑。
 *
 * 具体任务写在 `app/Crontab/` 下的类上（用 `#[Crontab]` 注解）。
 */
return [
    'enable' => true,
    'crontab' => [],
];
