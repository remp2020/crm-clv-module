<?php

namespace Crm\ClvModule\Models\Scenarios;

use Crm\ApplicationModule\Criteria\Params\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Localization\ITranslator;

class CustomerLifetimeValueCriteria implements ScenariosCriteriaInterface
{
    private const BUCKET_25 = 'bucket_25';
    private const BUCKET_50 = 'bucket_50';
    private const BUCKET_75 = 'bucket_75';
    private const BUCKET_100 = 'bucket_100';

    private $translator;

    public function __construct(ITranslator $translator)
    {
        $this->translator = $translator;
    }

    public function params(): array
    {
        $options = [
            self::BUCKET_25 => $this->translator->translate('clv.criteria.clv_bucket.25'),
            self::BUCKET_50 => $this->translator->translate('clv.criteria.clv_bucket.50'),
            self::BUCKET_75 => $this->translator->translate('clv.criteria.clv_bucket.75'),
            self::BUCKET_100 => $this->translator->translate('clv.criteria.clv_bucket.100'),
        ];

        return [
            new StringLabeledArrayParam(
                'clv_bucket',
                $this->translator->translate('clv.criteria.clv_bucket.bucket_label'),
                $options
            ),
        ];
    }

    public function addCondition(Selection $selection, $values, IRow $criterionItemRow): bool
    {
        $conditions = [];
        foreach ($values->selection as $option) {
            switch ($option) {
                case self::BUCKET_25:
                    $conditions[] = ':customer_lifetime_values.period_amount >= percentile_0_amount 
                        AND :customer_lifetime_values.period_amount < percentile_25_amount';
                    break;
                case self::BUCKET_50:
                    $conditions[] = ':customer_lifetime_values.period_amount >= percentile_25_amount 
                        AND :customer_lifetime_values.period_amount < percentile_50_amount';
                    break;
                case self::BUCKET_75:
                    $conditions[] = ':customer_lifetime_values.period_amount >= percentile_50_amount 
                        AND :customer_lifetime_values.period_amount < percentile_75_amount';
                    break;
                case self::BUCKET_100:
                    $conditions[] = ':customer_lifetime_values.period_amount >= percentile_75_amount 
                        AND :customer_lifetime_values.period_amount <= percentile_100_amount';
                    break;
            }
        }

        if (count($conditions)) {
            $selection->where(implode(' OR ', $conditions));
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('clv.criteria.clv_bucket.label');
    }
}
