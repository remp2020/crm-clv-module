# CRM CLV Module

[![Translation status @ Weblate](https://hosted.weblate.org/widgets/remp-crm/-/clv-module/svg-badge.svg)](https://hosted.weblate.org/projects/remp-crm/clv-module/)

## Installing module

We recommend using Composer for installation and update management.

```shell
composer require remp/crm-clv-module
```

## Enabling module

Add installed extension to your `app/config/config.neon` file.

```neon
extensions:
	- Crm\ClvModule\DI\ClvModuleExtension
```

## Usage

To compute customer-lifetime-value (CLV) for your users, schedule command `clv:compute` to be run periodically (depending on how often you want CLVs to be refreshed), e.g. using cron.