<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use App\Service\Cl\User\ProfileService;
use Hyperf\Carbon\Carbon;
use Hyperf\Validation\Request\FormRequest;

/**
 * 改资料（docs/01 P14、docs/05 第 4.2 节）。
 *
 * 注意这里**没有** `gender` 规则：性别注册后不能改，服务端也只认白名单字段，
 * 所以客户端即使传了 gender 也不会生效。
 */
class UpdateProfileRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'nickname' => 'sometimes|string|min:2|max:12',
            'avatar' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string|max:' . ProfileService::MAX_BIO_LENGTH,
            // 必须满 18 岁：生日不能晚于「今天往前推 18 年」
            'birthday' => 'sometimes|nullable|date|before_or_equal:' . $this->youngestBirthday(),
            'city' => 'sometimes|nullable|string|max:32',
            'height' => 'sometimes|nullable|integer|min:120|max:250',
            'job' => 'sometimes|nullable|string|max:32',
            'education' => 'sometimes|nullable|string|max:16',
            'dating_purpose' => 'sometimes|nullable|string|in:认真交友,兴趣搭子,长期关系',
            'tags' => 'sometimes|nullable|array|max:' . ProfileService::MAX_TAGS,
            'tags.*' => 'integer|exists:cl_tag,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'nickname' => '昵称',
            'avatar' => '头像',
            'bio' => '自我介绍',
            'birthday' => '生日',
            'city' => '城市',
            'height' => '身高',
            'job' => '职业',
            'education' => '学历',
            'dating_purpose' => '交友期待',
            'tags' => '兴趣标签',
        ];
    }

    public function messages(): array
    {
        return [
            'birthday.before_or_equal' => '年龄必须满 ' . ProfileService::MIN_AGE . ' 岁',
            'tags.max' => '兴趣标签最多 ' . ProfileService::MAX_TAGS . ' 个',
            'bio.max' => '自我介绍最多 ' . ProfileService::MAX_BIO_LENGTH . ' 字',
        ];
    }

    private function youngestBirthday(): string
    {
        return Carbon::now()->subYears(ProfileService::MIN_AGE)->toDateString();
    }
}
