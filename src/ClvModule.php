<?php

namespace Crm\ClvModule;

use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Widget\LazyWidgetManagerInterface;
use Crm\ClvModule\Commands\ComputeClvCommand;
use Crm\ClvModule\Components\CustomerLifetimeValue\CustomerLifetimeValue;
use Crm\ClvModule\Models\Scenarios\CustomerLifetimeValueCriteria;

class ClvModule extends CrmModule
{
    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(ComputeClvCommand::class));
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.box',
            CustomerLifetimeValue::class,
            1800
        );
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register(
            'user',
            'clv_bucket',
            $this->getInstance(CustomerLifetimeValueCriteria::class)
        );
    }
}
