<?php

declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\Command;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sync',
    description: 'Install/uninstall apps as defined in file config/apps.php',
)]
class AppSynchronizeCommand extends Command
{
    private const CONFIG_FILE_PATH = '/config/apps.php';

    private string $projectDir;
    private LoggerInterface $logger;

    public function __construct(
        string $projectDir,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->projectDir = $projectDir;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!file_exists($this->projectDir . self::CONFIG_FILE_PATH)) {
            $output->writeln(sprintf('%s not found', $this->projectDir . self::CONFIG_FILE_PATH));

            return self::FAILURE;
        }

        $apps = require $this->projectDir . self::CONFIG_FILE_PATH;

        $errorSum = $this->installUninstallApps($apps, $output);

        if ($errorSum > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, array<string, bool>> $plugins
     * @param OutputInterface $output
     *
     * @return int
     */
    private function installUninstallApps(array $apps, OutputInterface $output): int
    {
        $disabledPlugins = array_keys(array_filter($apps, function ($isEnabled) {
            return $isEnabled === false;
        }));
        $enabledPlugins = array_keys(array_filter($apps, function ($isEnabled) {
            return $isEnabled === true;
        }));

        foreach ($disabledPlugins as $disabledPlugin) {
            $this->runCommand([
                'command' => 'app:uninstall',
                'name' => $disabledPlugin,
            ], $output);
        }

        $installFailed = []; // 0 = install fine, 1 = install failed
        foreach ($enabledPlugins as $enabledPlugin) {
            $installFailed[$enabledPlugin] = $this->executeAppInstall($enabledPlugin, $output);
        }

        return array_sum($installFailed);
    }

    /**
     * @param array $parameters
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    private function runCommand(array $parameters, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('No application initialised');
        }

        $output->writeln('');

        $command = $application->find($parameters['command']);
        unset($parameters['command']);

        $input = new ArrayInput($parameters);
        $input->setInteractive(false);

        return $command->run($input, $output);
    }

    private function executeAppInstall(string $enabledPlugin, OutputInterface $output): int
    {
        try {
            $this->runCommand([
                'command' => 'app:install',
                'name' => $enabledPlugin,
                '--activate' => true,
                '--force' => true,
            ], $output);
        } catch (Exception $e) {
            $this->logger->error('Error while installing app: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
