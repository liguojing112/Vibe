<?php

declare(strict_types=1);

namespace App\Exception;

use App\Http\Api\ApiResult;
use App\Model\Enums\Cl\BizCode;

/**
 * App 接口业务异常（docs/02 第 2.3 节、docs/05 第 3 节）。
 * 由 ClBusinessExceptionHandler 输出成 HTTP 200 + {code: 业务码, message, data}。
 */
class ClBusinessException extends \RuntimeException
{
    private ApiResult $response;

    public function __construct(BizCode $code, ?string $message = null, mixed $data = null)
    {
        $this->response = ApiResult::error($code, $message, $data);
        parent::__construct($message ?? $code->message());
    }

    public function getResponse(): ApiResult
    {
        return $this->response;
    }
}
