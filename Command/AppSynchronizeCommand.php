<?php

declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\Command;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:sync',
    description: 'Install/uninstall apps as defined in file config/apps.php',
)]
class AppSynchronizeCommand extends Command
{
    private const CONFIG_FILE_PATH = 'config/apps.php';

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

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = Path::join($this->projectDir, self::CONFIG_FILE_PATH);
        if (!file_exists($configPath)) {
            $output->writeln(sprintf('%s not found', $configPath));

            return self::FAILURE;
        }

        $apps = require $configPath;
        if (!is_array($apps)) {
            throw new \RuntimeException('Invalid apps config: expected array');
        }

        $errorSum = $this->installUninstallApps($apps, $output);

        if ($errorSum > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, array<string, bool>> $apps
     * @param OutputInterface $output
     *
     * @return int
     */
    private function installUninstallApps(array $apps, OutputInterface $output): int
    {
        $enabledApps = [];
        $disabledApps = [];
        foreach ($apps as $app => $isEnabled) {
            if ($isEnabled) {
                $enabledApps[] = $app;
                continue;
            }

            $disabledApps[] = $app;
        }

        $uninstallFailed = []; // 0 = uninstall fine, 1 = uninstall failed
        foreach ($disabledApps as $disabledApp) {
            $uninstallFailed[$disabledApp] = $this->executeAppUninstall($disabledApp, $output);
        }

        $installFailed = []; // 0 = install fine, 1 = install failed
        foreach ($enabledApps as $enabledPlugin) {
            $installFailed[$enabledPlugin] = $this->executeAppInstall($enabledPlugin, $output);
        }

        return array_sum($uninstallFailed) + array_sum($installFailed);
    }

    private function executeAppUninstall(string $disabledPlugin, OutputInterface $output): int
    {
        try {
            $this->runCommand([
                'command' => 'app:uninstall',
                'name' => $disabledPlugin,
            ], $output);
        } catch (Exception|ExceptionInterface $e) {
            $this->logger->error('Error while uninstalling app: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
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
        } catch (Exception|ExceptionInterface $e) {
            $this->logger->error('Error while installing app: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array $parameters
     * @param OutputInterface $output
     *
     * @return int
     * @throws ExceptionInterface
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
}
