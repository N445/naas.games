<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    // %env()% is forbidden in routing config, so the value is frozen into the container at compile time.
    $container->parameters()->set('app.admin_path', $_SERVER['ADMIN_PATH'] ?? '/admin');
};
