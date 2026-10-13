<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Report;
use App\Repository\IRepository;

/**
 * @extends IRepository<Report>
 */
final class ReportRepository extends IRepository
{
    public function __construct(
        protected readonly Report $model
    ) {}
}
