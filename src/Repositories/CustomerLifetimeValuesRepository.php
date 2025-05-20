<?php

namespace Crm\ClvModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Utils\DateTime;

class CustomerLifetimeValuesRepository extends Repository
{
    protected $tableName = 'customer_lifetime_values';

    final public function add(
        $userId,
        DateTime $periodStart,
        DateTime $periodEnd,
        float $periodAmount,
        float $percentile0Amount,
        float $percentile25Amount,
        float $percentile50Amount,
        float $percentile75Amount,
        float $percentile100Amount,
    ) {
        $now = new DateTime();

        $data = [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_amount' => $periodAmount,
            'percentile_0_amount' => $percentile0Amount,
            'percentile_25_amount' => $percentile25Amount,
            'percentile_50_amount' => $percentile50Amount,
            'percentile_75_amount' => $percentile75Amount,
            'percentile_100_amount' => $percentile100Amount,
            'updated_at' => $now,
        ];

        $row = $this->findBy('user_id', $userId);
        if ($row) {
            $this->update($row, $data);
            return $row;
        }

        return $this->insert(['user_id' => $userId, 'created_at' => $now] + $data);
    }
}
