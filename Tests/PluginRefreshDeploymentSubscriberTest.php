<?php

declare(strict_types=1);

// Run with a compatible Shopware vendor/autoload.php as the first argument.
require $argv[1] ?? dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/EventSubscriber/PluginRefreshDeploymentSubscriber.php';

use Flagbit\Shopware\ShopwareMaintenance\EventSubscriber\PluginRefreshDeploymentSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$container = new Symfony\Component\DependencyInjection\ContainerBuilder();
$loader = new Symfony\Component\DependencyInjection\Loader\XmlFileLoader(
    $container,
    new Symfony\Component\Config\FileLocator(dirname(__DIR__) . '/Resources/config')
);
$loader->load('services.xml');
if ($container->getParameterBag()->all()['env(SHOPWARE_MAINTENANCE_DEPLOY_LOCK_PATH)'] !== '') {
    throw new RuntimeException('The default lock path must be an empty string.');
}

$kernel = new class implements HttpKernelInterface {
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        return new Response();
    }
};
$directory = sys_get_temp_dir() . '/maintenance-guard-' . bin2hex(random_bytes(8));
mkdir($directory);
$lock = $directory . '/deploy.lock';
$guard = new PluginRefreshDeploymentSubscriber($lock);
$check = static function (PluginRefreshDeploymentSubscriber $subscriber, string $route, bool $blocked) use ($kernel): void {
    $request = new Request();
    $request->attributes->set('_route', $route);
    $event = new ControllerEvent($kernel, static fn (): Response => new Response(), $request, HttpKernelInterface::MAIN_REQUEST);
    $actual = false;
    try {
        $subscriber->onKernelController($event);
    } catch (ServiceUnavailableHttpException $exception) {
        if ($exception->getStatusCode() !== 503) {
            throw new RuntimeException('Expected HTTP 503.');
        }
        $actual = true;
    }
    if ($actual !== $blocked) {
        throw new RuntimeException('Unexpected guard result for ' . $route);
    }
};
try {
    $check($guard, 'api.extension.refresh', false);
    touch($lock);
    $check($guard, 'api.extension.refresh', true);
    $check($guard, 'api.extension.installed', false);
    $check(new PluginRefreshDeploymentSubscriber(''), 'api.extension.refresh', false);
    if (!is_file($lock)) {
        throw new RuntimeException('The guard must not remove the deploy lock.');
    }
    unlink($lock);
    $check($guard, 'api.extension.refresh', false);
    echo "PASS: lock creation/removal, disabled guard, unrelated route, HTTP 503 and lock ownership.\n";
} finally {
    if (is_file($lock)) {
        unlink($lock);
    }
    rmdir($directory);
}
