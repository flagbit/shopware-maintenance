<?php

declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class PluginRefreshDeploymentSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly string $deployLockPath)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Guard only the admin refresh route; CLI and indirect refresh calls remain unchanged.
        if ($event->getRequest()->attributes->get('_route') !== 'api.extension.refresh'
            || $this->deployLockPath === '') {
            return;
        }

        // Deployer owns this file. We only check it; this does not drain in-flight requests.
        clearstatcache(true, $this->deployLockPath);
        if (is_file($this->deployLockPath)) {
            throw new ServiceUnavailableHttpException(null, 'Deployment is in progress. Please try again later.');
        }
    }
}
