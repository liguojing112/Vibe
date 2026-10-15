<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\LevelConfig;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<LevelConfig>
 */
final class LevelConfigRepository extends IRepository
{
    public function __construct(
        protected readonly LevelConfig $model
    ) {}

    public function allOrdered(): Collection
    {
        return $this->model->newQuery()->orderBy('level')->get();
    }

    public function findByLevel(int $level): ?LevelConfig
    {
        return $this->model->newQuery()->where('level', $level)->first();
    }

    /**
     * 语音/视频单价的区间（各等级里的最小/最大值）。
     *
     * 「示例价格」和「单价范围」两处都用它：P01 的快捷卡、P02 的提示、P16 的横幅
     * 要一个全局示例价，P13 要一个区间。各算一遍的话，改了等级配置只会有一边跟着变。
     *
     * @return array{voice_min: int, voice_max: int, video_min: int, video_max: int}
     */
    public function callPriceRange(): array
    {
        $voice = [];
        $video = [];
        foreach ($this->allOrdered() as $level) {
            $voice[] = (int) $level->voice_price;
            $video[] = (int) $level->video_price;
        }

        return [
            'voice_min' => $voice === [] ? 0 : min($voice),
            'voice_max' => $voice === [] ? 0 : max($voice),
            'video_min' => $video === [] ? 0 : min($video),
            'video_max' => $video === [] ? 0 : max($video),
        ];
    }
}
