<?php

declare(strict_types=1);

namespace App\Service\Cl\Pay;

use App\Exception\ClBusinessException;
use App\Model\Enums\Cl\BizCode;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Guzzle\ClientFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 万世码支付 / 易支付（epay）客户端。
 *
 * 只做协议层：签名、下单、验签、查单。什么时候下单、到账后怎么加金币在
 * `RechargePayService` 里。
 *
 * 关键约束（经理原话）：**不要跳转码支付网站**。`mapi.php` 返回的是支付宝付款链接，
 * 由 App 自己画二维码、自己交给支付宝 App。
 *
 * 密钥只在服务端读配置，不写日志、不下发 App。
 */
final class EpayService
{
    private const DEFAULT_BASE_URL = 'https://xpay.wsmfk.com';

    /** 服务器端取码接口（docs: https://xpay.wsmfk.com/doc/epay_mapi）。 */
    private const CREATE_PATH = '/xpay/epay/mapi.php';

    /** 查询单个订单（docs: https://xpay.wsmfk.com/doc/epay/api/order_one）。 */
    private const QUERY_PATH = '/xpay/epay/api.php';

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly AppConfigService $configService,
        private readonly LoggerInterface $logger,
    ) {}

    /** 开关打开且 pid / key / 站点地址都填了，才能下单。 */
    public function enabled(): bool
    {
        return (bool) $this->configService->get('pay.enabled', false)
            && $this->pid() !== ''
            && $this->key() !== ''
            && $this->baseUrl() !== '';
    }

    /**
     * 向码支付取支付链接。
     *
     * @return array{pay_url: string, trade_no: string}
     */
    public function createPayment(string $orderNo, int $amountFen, string $subject, string $clientIp): array
    {
        $params = [
            'pid' => $this->pid(),
            'type' => $this->payType(),
            'out_trade_no' => $orderNo,
            'notify_url' => $this->notifyUrl(),
            'name' => mb_substr($subject, 0, 40),
            'money' => self::fenToYuan($amountFen),
            'clientip' => $clientIp,
            'device' => 'mobile',
            'sign_type' => 'MD5',
        ];
        $returnUrl = $this->returnUrl();
        if ($returnUrl !== '') {
            $params['return_url'] = $returnUrl;
        }
        $params['sign'] = self::sign($params, $this->key());

        $body = $this->post($this->baseUrl() . self::CREATE_PATH, $params);
        $data = json_decode($body, true);
        if (! is_array($data) || (int) ($data['code'] ?? 0) !== 1) {
            $this->logger->warning('码支付下单失败', [
                'order_no' => $orderNo,
                'code' => is_array($data) ? ($data['code'] ?? null) : null,
                'msg' => is_array($data) ? ($data['msg'] ?? null) : mb_substr($body, 0, 200),
            ]);
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '支付服务暂时不可用，请稍后再试');
        }

        // qrcode 是支付宝付款链接；payurl 是网页跳转地址。App 要的是前者。
        $payUrl = (string) ($data['qrcode'] ?? '');
        if ($payUrl === '') {
            $payUrl = (string) ($data['payurl'] ?? '');
        }
        if ($payUrl === '') {
            $this->logger->warning('码支付没有返回可用支付链接', ['order_no' => $orderNo]);
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '支付服务没有返回付款链接，请稍后再试');
        }

        return [
            'pay_url' => $payUrl,
            'trade_no' => (string) ($data['trade_no'] ?? ''),
        ];
    }

    /**
     * 异步通知验签（docs: https://xpay.wsmfk.com/doc/epay_notify）。
     *
     * 除了签名，还核对 pid；金额和订单号由调用方再核对。
     */
    public function verifyNotify(array $params): bool
    {
        $sign = strtolower(trim((string) ($params['sign'] ?? '')));
        if ($sign === '') {
            return false;
        }
        if ((string) ($params['pid'] ?? '') !== $this->pid()) {
            return false;
        }
        $signType = strtolower((string) ($params['sign_type'] ?? 'md5'));
        if ($signType !== 'md5') {
            return false;
        }

        return hash_equals(self::sign($params, $this->key()), $sign);
    }

    /**
     * 主动查单（后台补单用）。返回 true 表示码支付确认已支付。
     */
    public function isPaidByQuery(string $orderNo): bool
    {
        $url = $this->baseUrl() . self::QUERY_PATH;
        $client = $this->clientFactory->create(['timeout' => 10.0]);

        try {
            $response = $client->get($url, [
                'query' => [
                    'act' => 'order',
                    'pid' => $this->pid(),
                    'key' => $this->key(),
                    'out_trade_no' => $orderNo,
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('码支付查单异常', ['order_no' => $orderNo, 'error' => $e->getMessage()]);
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '查询支付结果失败，请稍后再试');
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        if (! is_array($data) || (int) ($data['code'] ?? 0) !== 1) {
            $this->logger->warning('码支付查单失败', [
                'order_no' => $orderNo,
                'code' => is_array($data) ? ($data['code'] ?? null) : null,
                'msg' => is_array($data) ? ($data['msg'] ?? null) : mb_substr($body, 0, 200),
            ]);
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '查询支付结果失败，请稍后再试');
        }

        return (int) ($data['status'] ?? 0) === 1;
    }

    /**
     * 易支付 MD5 签名：过滤空值 + sign/sign_type，参数名 ASCII 升序，
     * 拼成 `a=b&c=d&e=f` 后直接接密钥做 md5（小写）。纯函数，测试直接调。
     */
    public static function sign(array $params, string $key): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter(
            $params,
            static fn (mixed $value): bool => $value !== '' && $value !== null,
        );
        ksort($params, \SORT_STRING);

        $pairs = [];
        foreach ($params as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return strtolower(md5(implode('&', $pairs) . $key));
    }

    /** 分 → 元字符串（两位小数），只用整数运算。 */
    public static function fenToYuan(int $fen): string
    {
        $negative = $fen < 0 ? '-' : '';
        $abs = abs($fen);

        return $negative . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', \STR_PAD_LEFT);
    }

    /** 元字符串 → 分。格式不对返回 null。 */
    public static function yuanToFen(string $yuan): ?int
    {
        $yuan = trim($yuan);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $yuan)) {
            return null;
        }

        [$whole, $decimal] = array_pad(explode('.', $yuan, 2), 2, '0');

        return (int) $whole * 100 + (int) str_pad($decimal, 2, '0', \STR_PAD_RIGHT);
    }

    public function expireMinutes(): int
    {
        return max(1, (int) $this->configService->get('pay.order_expire_minutes', 30));
    }

    private function post(string $url, array $params): string
    {
        $client = $this->clientFactory->create(['timeout' => 10.0]);

        try {
            $response = $client->post($url, ['form_params' => $params]);
        } catch (Throwable $e) {
            $this->logger->warning('码支付请求异常', ['order_no' => $params['out_trade_no'] ?? '', 'error' => $e->getMessage()]);
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '支付服务暂时不可用，请稍后再试');
        }

        return (string) $response->getBody();
    }

    private function baseUrl(): string
    {
        $url = trim((string) $this->configService->get('pay.epay_url', self::DEFAULT_BASE_URL));

        return $url === '' ? self::DEFAULT_BASE_URL : rtrim($url, '/');
    }

    private function pid(): string
    {
        return trim((string) $this->configService->get('pay.epay_pid', ''));
    }

    private function key(): string
    {
        return trim((string) $this->configService->get('pay.epay_key', ''));
    }

    private function payType(): string
    {
        $type = trim((string) $this->configService->get('pay.epay_type', 'alipay'));

        return $type === '' ? 'alipay' : $type;
    }

    private function notifyUrl(): string
    {
        return trim((string) $this->configService->get('pay.notify_url', ''));
    }

    private function returnUrl(): string
    {
        return trim((string) $this->configService->get('pay.return_url', ''));
    }
}
