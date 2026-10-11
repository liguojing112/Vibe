<?php

declare(strict_types=1);

namespace App\Model\Enums\Cl;

/**
 * App 接口业务错误码（docs/05 第 3 节）。
 * 业务错误一律 HTTP 200，body 里的 code 用这里的值；message 是能直接给用户看的中文。
 *
 * 说明：文案直接写在枚举里（不走 i18n）——这是固定的中文用户文案，不做多语言，
 * 且 phpunit 容器里翻译器取不到 key，放在这里测试才确定。
 */
enum BizCode: int
{
    case LOGIN_FAILED = 10001;
    case ACCOUNT_EXISTS = 10002;
    case ACCOUNT_LOCKED = 10003;
    case ACCOUNT_BANNED = 10004;
    case TOO_MANY_REQUESTS = 10005;
    case OLD_PASSWORD_WRONG = 10006;

    case COIN_NOT_ENOUGH = 20001;
    case POINT_NOT_ENOUGH = 20002;
    case WITHDRAW_NOT_ALLOWED = 20003;

    case USER_NOT_FOUND = 30001;
    case BLOCKED_BY_TARGET = 30002;
    case SAME_GENDER_NOT_ALLOWED = 30003;
    case INVALID_MESSAGE_CONTENT = 30004;

    case STATUS_NOT_ALLOWED = 40001;
    case PROFILE_AUDITING = 40002;
    case REAL_NAME_FORMAT_ERROR = 40003;
    case VIP_REQUIRED = 40004;

    case FEATURE_NOT_OPEN = 50001;

    /**
     * 默认中文提示。带占位符的场景（例如 10003 的锁定分钟数、10004 的封禁原因）
     * 在抛异常时用 message 参数覆盖。
     */
    public function message(): string
    {
        return match ($this) {
            self::LOGIN_FAILED => '账号或密码错误',
            self::ACCOUNT_EXISTS => '账号已存在',
            self::ACCOUNT_LOCKED => '密码错误次数过多，账号已锁定，请稍后再试',
            self::ACCOUNT_BANNED => '账号已被封禁',
            self::TOO_MANY_REQUESTS => '操作太频繁，请稍后再试',
            self::OLD_PASSWORD_WRONG => '旧密码错误',
            self::COIN_NOT_ENOUGH => '金币余额不足',
            self::POINT_NOT_ENOUGH => '积分不足',
            self::WITHDRAW_NOT_ALLOWED => '提现条件不满足',
            self::USER_NOT_FOUND => '对方不存在或已注销',
            self::BLOCKED_BY_TARGET => '对方已设置不接收你的消息',
            self::SAME_GENDER_NOT_ALLOWED => '不能给同性发送',
            self::INVALID_MESSAGE_CONTENT => '消息内容不合法',
            self::STATUS_NOT_ALLOWED => '当前状态不允许此操作',
            self::PROFILE_AUDITING => '资料审核中，请勿重复提交',
            self::REAL_NAME_FORMAT_ERROR => '实名信息格式错误',
            self::VIP_REQUIRED => '需要开通会员',
            self::FEATURE_NOT_OPEN => '功能暂未开放',
        };
    }
}
