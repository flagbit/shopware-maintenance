<?php

declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class PluginRefreshDeploymentSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Guard only the admin refresh route; CLI and indirect refresh calls remain unchanged.
        if ($event->getRequest()->attributes->get('_route') !== 'api.extension.refresh') {
            return;
        }

        // Recognize only <deploy_path>/releases/<release>; resolve symlinks first.
        // Local checkouts and other layouts are left unchanged, without guessing a lock path.
        $projectDir = realpath($this->projectDir);
        if ($projectDir === false || basename(dirname($projectDir)) !== 'releases') {
            return;
        }

        $deployerDir = dirname($projectDir, 2) . '/.dep';
        if (!is_dir($deployerDir)) {
            return;
        }

        $deployLockPath = $deployerDir . '/deploy.lock';
        // Deployer owns this file. We only check it; this does not drain in-flight requests.
        clearstatcache(true, $deployLockPath);
        if (is_file($deployLockPath)) {
            throw new ServiceUnavailableHttpException(null, 'Deployment is in progress. Please try again later.');
        }
    }
}
