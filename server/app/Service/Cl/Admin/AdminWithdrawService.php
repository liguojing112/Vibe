<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Model\Cl\WithdrawOrder;
use App\Repository\Cl\WithdrawOrderRepository;
use App\Service\Cl\Withdraw\WithdrawService;

/**
 * 后台提现审核（docs/07 第 3.13 节、docs/06 第 9.2 节）。
 *
 * 三个动作**直接用 `WithdrawService` 里那三个方法**（T6.3 写的）：
 * 状态机、冻结积分的去处、给女方的通知都在那儿守着了，
 * 这里只负责「列表 / 导出」和把参数传对。
 */
final class AdminWithdrawService
{
    /** 导出一次最多多少行。 */
    private const EXPORT_LIMIT = 5000;

    private const STATUS_TEXTS = [
        WithdrawOrder::STATUS_PENDING => '待审核',
        WithdrawOrder::STATUS_APPROVED => '待打款',
        WithdrawOrder::STATUS_REJECTED => '已驳回',
        WithdrawOrder::STATUS_PAID => '已打款',
    ];

    private const ACCOUNT_TEXTS = [
        'ALIPAY' => '支付宝',
        'WECHAT' => '微信',
        'BANK' => '银行卡',
    ];

    public function __construct(
        private readonly WithdrawOrderRepository $repository,
        private readonly WithdrawService $withdrawService,
    ) {}

    /**
     * @return array{list: array<int,array<string,mixed>>, total: int, amount_fen: int, actual_fen: int}
     */
    public function list(?int $status, string $from, string $to, int $page, int $pageSize): array
    {
        $result = $this->repository->pageForAdmin($status, $from, $to, $page, $pageSize);

        foreach ($result['list'] as &$row) {
            $row['status_text'] = self::STATUS_TEXTS[$row['status']] ?? (string) $row['status'];
            $row['account_type_text'] = self::ACCOUNT_TEXTS[$row['account_type']] ?? (string) $row['account_type'];
        }
        unset($row);

        return $result;
    }

    public function approve(int $orderId, int $reviewerId): void
    {
        $this->withdrawService->approve($orderId, $reviewerId);
    }

    public function reject(int $orderId, int $reviewerId, string $reason): void
    {
        $this->withdrawService->reject($orderId, $reviewerId, $reason);
    }

    public function markPaid(int $orderId, int $payerId, string $payProof): void
    {
        $this->withdrawService->markPaid($orderId, $payerId, $payProof);
    }

    /**
     * 导出给财务打款用（docs/07 第 3.13 节）。
     *
     * **收款账号是明文**（财务要照着打款），但不带身份证号和用户手机号 ——
     * 那两样这张表用不上。
     *
     * @return array{headers: string[], rows: array<int,array<int,string>>}
     */
    public function exportRows(?int $status, string $from, string $to): array
    {
        $headers = ['单号', '用户', '等级', '实名姓名', '申请金额(元)', '手续费(元)', '实际到账(元)', '冻结积分', '收款方式', '收款账号', '收款人', '状态', '驳回原因', '打款备注', '申请时间'];

        $rows = [];
        foreach ($this->repository->pageForAdmin($status, $from, $to, 1, self::EXPORT_LIMIT)['list'] as $row) {
            $rows[] = [
                (string) $row['order_no'],
                (string) $row['user_nickname'],
                'Lv' . (int) $row['level'],
                (string) $row['real_name'],
                $this->yuan((int) $row['amount_fen']),
                $this->yuan((int) $row['fee_fen']),
                $this->yuan((int) $row['actual_fen']),
                (string) (int) $row['points'],
                self::ACCOUNT_TEXTS[$row['account_type']] ?? (string) $row['account_type'],
                (string) $row['account_no'],
                (string) $row['account_name'],
                self::STATUS_TEXTS[$row['status']] ?? (string) $row['status'],
                (string) $row['reject_reason'],
                (string) $row['pay_proof'],
                (string) $row['created_at'],
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** 分 → 元（两位小数，整数算）。 */
    private function yuan(int $fen): string
    {
        return sprintf('%d.%02d', intdiv($fen, 100), $fen % 100);
    }
}
