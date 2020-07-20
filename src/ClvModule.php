<?php

namespace Crm\ClvModule;

use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Widget\WidgetManagerInterface;
use Crm\ClvModule\Commands\ComputeClvCommand;
use Crm\ClvModule\Components\CustomerLifetimeValue\CustomerLifetimeValue;

class ClvModule extends CrmModule
{
    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(ComputeClvCommand::class));
    }

    public function registerWidgets(WidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.box',
            $this->getInstance(CustomerLifetimeValue::class),
            1800
        );
    }
}
