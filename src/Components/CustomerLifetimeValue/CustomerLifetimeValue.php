<?php

namespace Crm\ClvModule\Components\CustomerLifetimeValue;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\ClvModule\Repositories\CustomerLifetimeValuesRepository;

/**
 * This widget displays customer lifetime value thermometer in user details
 *
 * @package Crm\ClvModule\Components
 */
class CustomerLifetimeValue extends BaseLazyWidget
{
    private const DEFAULT_THERMOMETER_HEIGHT_PX = 200;
    private const MIN_THERMOMETER_QUARTILE_HEIGHT_PX = 30;
    private const MAX_THERMOMETER_QUARTILE_HEIGHT_PX = 120;

    private $templateName = 'customer_lifetime_value.latte';

    private $customerLifetimeValuesRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        CustomerLifetimeValuesRepository $customerLifetimeValuesRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->customerLifetimeValuesRepository = $customerLifetimeValuesRepository;
    }

    public function header($id = '')
    {
        return 'Customer lifetime value';
    }

    public function identifier()
    {
        return 'customerlifetimevalue';
    }

    public function render($userId)
    {
        $clv = $this->customerLifetimeValuesRepository->findBy('user_id', $userId);

        if ($clv) {
            $quartiles = [];
            $quartiles['q1'] = ($clv->percentile_25_amount - $clv->percentile_0_amount)/($clv->percentile_100_amount - $clv->percentile_0_amount) * self::DEFAULT_THERMOMETER_HEIGHT_PX;
            $quartiles['q2'] = ($clv->percentile_50_amount - $clv->percentile_25_amount)/($clv->percentile_100_amount - $clv->percentile_0_amount) * self::DEFAULT_THERMOMETER_HEIGHT_PX;
            $quartiles['q3'] = ($clv->percentile_75_amount - $clv->percentile_50_amount)/($clv->percentile_100_amount - $clv->percentile_0_amount) * self::DEFAULT_THERMOMETER_HEIGHT_PX;
            $quartiles['q4'] = ($clv->percentile_100_amount - $clv->percentile_75_amount)/($clv->percentile_100_amount - $clv->percentile_0_amount) * self::DEFAULT_THERMOMETER_HEIGHT_PX;

            // compute height of user value (measured from 0% quartile value) distributed among quartiles heights (to scale correctly)
            $userValueQuartiles['q1'] = min(1, max($clv->period_amount - $clv->percentile_0_amount, 0) / ($clv->percentile_25_amount - $clv->percentile_0_amount)) * $quartiles['q1'];
            $userValueQuartiles['q2'] = min(1, max($clv->period_amount - $clv->percentile_25_amount, 0) / ($clv->percentile_50_amount - $clv->percentile_25_amount)) * $quartiles['q2'];
            $userValueQuartiles['q3'] = min(1, max($clv->period_amount - $clv->percentile_50_amount, 0) / ($clv->percentile_75_amount - $clv->percentile_50_amount)) * $quartiles['q3'];
            $userValueQuartiles['q4'] = min(1, max($clv->period_amount - $clv->percentile_75_amount, 0) / ($clv->percentile_100_amount - $clv->percentile_75_amount)) * $quartiles['q4'];

            // First, scale quartile heights up (ratio is kept) so each has at least self::MIN_THERMOMETER_QUARTILE_HEIGHT_PX height
            $minHeight = min($quartiles);
            if ($minHeight < self::MIN_THERMOMETER_QUARTILE_HEIGHT_PX) {
                $increaseBy = (self::MIN_THERMOMETER_QUARTILE_HEIGHT_PX/$minHeight);
                foreach ($quartiles as $key => $value) {
                    $quartiles[$key] = $value * $increaseBy;
                }
                foreach ($userValueQuartiles as $key => $value) {
                    $userValueQuartiles[$key] = $value * $increaseBy;
                }
            }

            // Second, scale quartile heights down (ratio may not be kept) so each has max self::MAX_THERMOMETER_QUARTILE_HEIGHT_PX height
            foreach ($quartiles as $quartileNumber => $quartileHeight) {
                if ($quartileHeight > self::MAX_THERMOMETER_QUARTILE_HEIGHT_PX) {
                    // de-scale both quartile height and related userValueQuartile height
                    $userValueQuartiles[$quartileNumber] = $userValueQuartiles[$quartileNumber] * self::MAX_THERMOMETER_QUARTILE_HEIGHT_PX / $quartileHeight;
                    $quartiles[$quartileNumber] = self::MAX_THERMOMETER_QUARTILE_HEIGHT_PX;
                }
            }

            // Sum userValue (will be absolutely positioned from the bottom quartile)
            $quartiles['userValue'] = array_sum($userValueQuartiles);

            // Round heights to whole pixels
            foreach ($quartiles as $key => $value) {
                $quartiles[$key] = (int) $value;
            }

            $this->template->quartiles = $quartiles;
        }

        $this->template->clv = $clv;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
