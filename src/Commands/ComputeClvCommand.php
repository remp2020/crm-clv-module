<?php

namespace Crm\ClvModule\Commands;

use Crm\ApiModule\Repository\UserSourceAccessesRepository;
use Crm\ClvModule\Repositories\CustomerLifetimeValuesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use MathPHP\Statistics\Average;
use MathPHP\Statistics\Descriptive;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ComputeClvCommand extends Command
{
    private const SECONDS_IN_DAY = 86400;

    private $subscriptionsRepository;

    private $userSourceAccessesRepository;

    private $calculatedQuartiles;

    private $customerLifetimeValuesRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        UserSourceAccessesRepository $userSourceAccessesRepository,
        CustomerLifetimeValuesRepository $customerLifetimeValuesRepository
    ) {
        parent::__construct();
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->userSourceAccessesRepository = $userSourceAccessesRepository;
        $this->customerLifetimeValuesRepository = $customerLifetimeValuesRepository;
    }

    protected function configure()
    {
        $this->setName('clv:compute')
            ->setDescription('Compute customer-lifetime values')
            ->addOption(
                'memory_limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Optionally sets PHP script execution memory_limit to given value (in MiBs).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('memory_limit')) {
            ini_set('memory_limit', $input->getOption('memory_limit') .'M');
        } else {
            ini_set('memory_limit', '512M');
        }

        // LAST [0, -12 months] period by default
        $periodEnd = new DateTime();
        $periodStart = (clone $periodEnd)->sub(new \DateInterval('P365D'));

        $output->writeln('Computing CLV (customer-lifetime-value) for users having active subscription(s) in period [<info>' . $periodStart->format('Y-m-d H:i:s') .'</info>, <info>' . $periodEnd->format('Y-m-d H:i:s') . '</info>]');

        // Only users with paid subscriptions are considered
        $userIds = $this->getUserIds($periodStart, $periodEnd);

        $userData = [];

        $output->writeln(' * Fetched user IDs, starting processing.' . $this->memory());

        foreach (array_chunk($userIds, 10000) as $userIdsChunk) {
            $sql = <<<SQL
SELECT s.user_id, s.start_time, s.end_time, s.length, s.subscription_type_id, s.type, s.is_recurrent, p.amount, p.payment_gateway_id,
DATEDIFF(NOW(), u.last_sign_in_at) AS last_sign_in_days,
DATEDIFF(NOW(), u.created_at) AS created_at_in_days
FROM subscriptions s
JOIN users u ON u.id = s.user_id
LEFT JOIN payments p ON p.subscription_id = s.id
WHERE s.is_paid = 1 AND p.id IS NOT NULL
AND s.start_time <= ? AND s.end_time >= ?
AND s.user_id IN (?)
SQL;

            $subscriptions = $this->subscriptionsRepository->getDatabase()->query($sql, $periodEnd, $periodStart, $userIdsChunk);

            // For these parameters, only a single value will be chosen based on a subscription having the biggest partial_amount (and max start_time)
            $parametersToDecide = [
                'subscription_type_id' => [],
                'is_recurrent' => [],
                'type' => [],
                'length' => [],
                'amount' => [],
                'payment_gateway_id' => [],
            ];

            foreach ($subscriptions as $sub) {
                if (!array_key_exists($sub->user_id, $userData)) {
                    $userData[$sub->user_id] = [
                        'period_paid_sub_count' => 0,
                        'period_amount' => 0,
                        'period_active_days' => 0,
                        'days_since_first_paid_sub' => null,
                        'partial_amount' => 0,
                        'last_sign_in_days' => null,
                        'created_at_in_days' => null,
                        '_decide' => $parametersToDecide, // helper parameter, will be unset later
                    ];
                }

                $userData[$sub->user_id]['period_paid_sub_count']++;
                $userData[$sub->user_id]['period_amount'] += $sub->amount;

                $userData[$sub->user_id]['last_sign_in_days'] = $sub->last_sign_in_days;
                $userData[$sub->user_id]['created_at_in_days'] = $sub->created_at_in_days;

                if ($sub->amount > 0) {
                    $userData[$sub->user_id]['days_since_first_paid_sub'] = min(array_filter([
                        $userData[$sub->user_id]['days_since_first_paid_sub'],
                        (int) (($periodEnd->getTimestamp() - $sub->start_time->getTimestamp()) / self::SECONDS_IN_DAY)
                    ]));
                }

                $subscriptionIntervalStart = max($sub->start_time, $periodStart);
                $subscriptionIntervalEnd = min($sub->end_time, $periodEnd);

                $days = (int) (($subscriptionIntervalEnd->getTimestamp() - $subscriptionIntervalStart->getTimestamp()) / self::SECONDS_IN_DAY);
                $dailyPrice = $sub->amount / $sub->length;
                $partialAmount = $days * $dailyPrice;

                $userData[$sub->user_id]['period_active_days'] += $days;
                $userData[$sub->user_id]['partial_amount'] += $partialAmount;

                foreach ($userData[$sub->user_id]['_decide'] as $paramToDecide => $counts) {
                    if (!array_key_exists((string) $sub->$paramToDecide, $counts)) {
                        $userData[$sub->user_id]['_decide'][$paramToDecide][$sub->$paramToDecide] = [
                            'partial_amount' => 0,
                            'start_time' => null,
                        ];
                    }

                    $userData[$sub->user_id]['_decide'][$paramToDecide][$sub->$paramToDecide]['partial_amount'] += $partialAmount;

                    // array_filter clears null (initial value) if present
                    $startTimes = array_filter([
                        $userData[$sub->user_id]['_decide'][$paramToDecide][$sub->$paramToDecide]['start_time'],
                        $sub->start_time
                    ]);

                    $userData[$sub->user_id]['_decide'][$paramToDecide][$sub->$paramToDecide]['start_time'] = max($startTimes);
                }
            }
            $subscriptions = null;

            foreach ($userIdsChunk as $userId) {
                if (isset($userData[$userId])) {
                    foreach ($userData[$userId]['_decide'] as $paramToDecide => $counts) {
                        uasort($counts, function ($a, $b) {
                            if ($a['partial_amount'] < $b['partial_amount']) {
                                return -1;
                            }
                            if ($a['partial_amount'] > $b['partial_amount']) {
                                return 1;
                            }
                            if ($a['start_time'] < $b['start_time']) {
                                return -1;
                            }
                            if ($a['start_time'] > $b['start_time']) {
                                return 1;
                            }
                            return 0;
                        });

                        $userData[$userId][$paramToDecide] = array_key_last($counts);
                    }

                    unset($userData[$userId]['_decide']);
                }
            }

            $lastAccesses = $this->userSourceAccessesRepository->getTable()
                ->select('DATEDIFF(NOW(), MAX(last_accessed_at)) AS last_access_date_in_days, user_id')
                ->where(['user_id IN (?)' => $userIdsChunk])
                ->group('user_id');

            foreach ($lastAccesses as $row) {
                if (isset($userData[$row->user_id])) {
                    $userData[$row->user_id]['last_access_date_in_days'] = $row->last_access_date_in_days;
                }
            }

            $output->writeln('   * Processed <info>' . count($userIdsChunk) . '</info> user IDs, aggregated data size: <info>' . count($userData) . '</info>' . $this->memory());
        }

        $this->calculateUserParameterValuesQuartiles($output, $userData);

        $output->writeln(' * User amounts are sorted. Starting to compute actual CLVs.' . $this->memory());

        $i = 1;
        $total = count($userData);
        foreach ($userData as $userId => $userValues) {
            $percentiles = $this->percentiles($userValues);
            if ($i % 5000 === 0) {
                $output->writeln("   * CLV computed for {$i}/{$total} users" . $this->memory());
            }

            $this->customerLifetimeValuesRepository->add(
                $userId,
                $periodStart,
                $periodEnd,
                $userValues['period_amount'],
                $percentiles[0],
                $percentiles[25],
                $percentiles[50],
                $percentiles[75],
                $percentiles[100]
            );
            $i++;
        }
        $output->writeln(' * Customer lifetime values updated for total <info>' . count($userData) . '</info> users.');
        return 0;
    }

    /**
     * getUserIds fetches users to calculate. The implementation is extracted to hide memory optimizations from
     * the main application flow.
     */
    private function getUserIds(DateTime $periodStart, DateTime $periodEnd): array
    {
        $args = [
            'is_paid = ?' => 1,
            'start_time <= ?' => $periodEnd,
            'end_time >= ?' => $periodStart,
        ];

        $query = $this->subscriptionsRepository->getTable()
            ->select('user_id')
            ->where($args)
            ->group('user_id')
            ->getSql();

        $result = $this->subscriptionsRepository->getDatabase()->queryArgs($query, array_values($args))->fetchAssoc('user_id=user_id');
        return array_values($result);
    }

    /**
     * For each parameter (e.g. subscription_type_id) and possible parameter value (e.g. subscription_type_id = 84)
     * prepare array of period_amounts spent by users having given value of this parameter.
     *
     * Once this array is complete, generate *calculatedQuartiles* based on period_amounts for each
     * parameter-value combination.
     *
     * @param OutputInterface $output
     * @param array $userData
     * @throws \MathPHP\Exception\BadDataException
     */
    private function calculateUserParameterValuesQuartiles(OutputInterface $output, array &$userData)
    {
        $sortedUserAmountsInPeriod = [];
        foreach ($userData as $userId => $userValues) {
            foreach ($userValues as $paramKey => $value) {
                $valueString = $this->userValueToString($value);

                if (!array_key_exists($paramKey, $sortedUserAmountsInPeriod)) {
                    $sortedUserAmountsInPeriod[$paramKey] = [];
                }

                if (!array_key_exists($valueString, $sortedUserAmountsInPeriod[$paramKey])) {
                    $sortedUserAmountsInPeriod[$paramKey][$valueString] = [];
                }

                $sortedUserAmountsInPeriod[$paramKey][$valueString][] = $userValues['period_amount'];
            }
        }

        $output->writeln(' * Matched user value parameters, starting sort.' . $this->memory());

        foreach ($sortedUserAmountsInPeriod as $paramKey => $values) {
            $output->writeln('   * Generating period amount based quartiles for param <info>' . $paramKey . '</info>' . $this->memory());
            foreach ($values as $value => $amounts) {
                $valueString = $this->userValueToString($value);
                $quartilesKey = $this->quartilesKey($paramKey, $valueString);
                $this->calculatedQuartiles[$quartilesKey] = Descriptive::quartiles($amounts);
            }
        }
    }

    /**
     * @param $userParameters
     * @return float[]
     *
     * Percentiles returns averaged amount-based percentiles matching the group user belongs to.
     *
     * It calculates amount-based spent percentiles for each parameter separately. Afterwards it averages out
     * percentile for each parameter and returns this as a final percentile that can be used to visualize CLV.
     */
    private function percentiles(array $userParameters): array
    {
        $parameterPercentiles = [];
        foreach ($userParameters as $parameter => $value) {
            $valueString = $this->userValueToString($value);

            $quartilesKey = $this->quartilesKey($parameter, $valueString);
            $quartiles = $this->calculatedQuartiles[$quartilesKey];

            $parameterPercentiles[0][$parameter] = $quartiles['0%'];
            $parameterPercentiles[25][$parameter] = $quartiles['Q1'];
            $parameterPercentiles[50][$parameter] = $quartiles['Q2'];
            $parameterPercentiles[75][$parameter] = $quartiles['Q3'];
            $parameterPercentiles[100][$parameter] = $quartiles['100%'];
        }

        return [
            0 => Average::mean($parameterPercentiles[0]),
            25 => Average::mean($parameterPercentiles[25]),
            50 => Average::mean($parameterPercentiles[50]),
            75 => Average::mean($parameterPercentiles[75]),
            100 => Average::mean($parameterPercentiles[100]),
        ];
    }

    private function userValueToString($value): string
    {
        if ($value instanceof DateTime) {
            return $value->format(DATE_RFC3339);
        }
        return (string) $value;
    }

    private function quartilesKey($parameter, $value)
    {
        return "$parameter::$value";
    }

    private function memory()
    {
        return ' (<comment>' . round(memory_get_usage(true) / 1024 / 1024, 2) . ' MiB</comment>)';
    }
}
