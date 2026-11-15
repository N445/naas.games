<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    // %env()% is forbidden in routing config, so the value is frozen into the container at compile time.
    $container->parameters()->set('app.admin_path', '/'.trim($_SERVER['ADMIN_PATH'] ?? 'admin', '/'));

    // Without these resources, editing ADMIN_PATH in a .env file would leave the old path compiled in dev.
    $projectDir = \dirname(__DIR__, 2);
    $env = $builder->getParameter('kernel.environment');
    foreach (['.env', '.env.local', ".env.$env", ".env.$env.local"] as $file) {
        $path = "$projectDir/$file";
        $builder->addResource(is_file($path) ? new FileResource($path) : new FileExistenceResource($path));
    }
};
