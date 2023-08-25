<?php

declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\Command;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Framework\Plugin\Exception\PluginBaseClassNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_keys;
use function array_sum;
use function file_exists;
use function sprintf;

class PluginSynchronizeCommand extends Command
{
    // phpcs:ignore
    protected static $defaultName = 'plugin:sync';

    public function __construct(
        private string $projectDir,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Install/uninstall plugins as defined in file config/plugins.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! file_exists($this->projectDir . '/config/plugins.php')) {
            $output->writeln(sprintf('%s not found', $this->projectDir . '/config/plugins.php'));

            return 1;
        }

        $plugins         = require $this->projectDir . '/config/plugins.php';
        $disabledPlugins = array_keys(array_filter($plugins, static function ($isEnabled) {
            return $isEnabled === false;
        }));
        $enabledPlugins  = array_keys(array_filter($plugins, static function ($isEnabled) {
            return $isEnabled === true;
        }));

        foreach ($disabledPlugins as $disabledPlugin) {
            $this->runCommand([
                'command' => 'plugin:uninstall',
                'plugins' => [$disabledPlugin],
            ], $output);
        }

        $installFailed = []; // 0 = install fine, 1 = install failed
        foreach ($enabledPlugins as $enabledPlugin) {
            $installFailed[$enabledPlugin] = $this->executePluginInstall($enabledPlugin, $output);
        }

        $errorSum = (int) array_sum($installFailed);
        if ($errorSum > self::SUCCESS) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws ExceptionInterface
     */
    private function runCommand(array $parameters, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new RuntimeException('No application initialised');
        }

        $output->writeln('');

        $command = $application->find($parameters['command']);
        unset($parameters['command']);

        $input = new ArrayInput($parameters);
        $input->setInteractive(false);

        return $command->run($input, $output);
    }

    private function executePluginInstall(string $enabledPlugin, OutputInterface $output): int
    {
        try {
            $this->runCommand([
                'command' => 'plugin:install',
                'plugins' => [$enabledPlugin],
                '--activate' => true,
            ], $output);
        } catch (PluginBaseClassNotFoundException) {
            $this->logger->error('Execute plugin:refresh before plugin:sync !');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
