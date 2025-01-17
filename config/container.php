<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Shlinkio\Shlink\Core\Config\EnvVars;
use Symfony\Component\Lock;

use const Shlinkio\Shlink\LOCAL_LOCK_FACTORY;

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

// Workaround to make this compatible with both openswoole 22 and earlier versions.
if (! function_exists('swoole_set_process_name')) {
    // phpcs:disable
    function swoole_set_process_name(string $name): void
    {
        OpenSwoole\Util::setProcessName($name);
    }
    // phpcs:enable
}

// This is one of the first files loaded. Configure the timezone here
date_default_timezone_set(EnvVars::TIMEZONE->loadFromEnv(date_default_timezone_get()));

// This class alias tricks the ConfigAbstractFactory to return Lock\Factory instances even with a different service name
// It needs to be placed here as individual config files will not be loaded once config is cached
if (! class_exists(LOCAL_LOCK_FACTORY)) {
    class_alias(Lock\LockFactory::class, LOCAL_LOCK_FACTORY);
}

return (static function (): ServiceManager {
    $config = require __DIR__ . '/config.php';
    $container = new ServiceManager($config['dependencies']);
    $container->setService('config', $config);

    return $container;
})();
