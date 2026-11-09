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
    public const GROUP_CORE = 'core';
    public const GROUP_THIRD_PARTY = 'third_party';
    public const GROUP_AGENCY = 'agency';
    public const GROUP_PROJECT = 'project';
    private const SEQUENTIAL_GROUPS = [
        self::GROUP_CORE,
        self::GROUP_THIRD_PARTY,
        self::GROUP_AGENCY,
        self::GROUP_PROJECT,
    ];

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

        $appGroups = require $configPath;
        if (!is_array($appGroups)) {
            throw new \RuntimeException('Invalid apps config: expected array');
        }

        $errorSum = 0;
        foreach (self::SEQUENTIAL_GROUPS as $group) {
            $errorSum += $this->installUninstallAppGroup($appGroups, $group, $output);
        }

        if ($errorSum > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, array<string, bool>> $appsGroups
     * @param string $groupName
     * @param OutputInterface $output
     *
     * @return int
     */
    private function installUninstallAppGroup(array $appsGroups, string $groupName, OutputInterface $output): int
    {
        if (!array_key_exists($groupName, $appsGroups)) {
            return 0;
        }

        $apps = $appsGroups[$groupName];
        if (!is_array($apps)) {
            throw new \RuntimeException(sprintf(
                'Invalid apps config for group "%s": expected array',
                $groupName
            ));
        }

        $appsToInstall = [];
        $appsToUninstall = [];
        foreach ($apps as $app => $shouldBeInstalled) {
            if (!is_bool($shouldBeInstalled)) {
                throw new \RuntimeException(sprintf(
                    'Invalid value for app "%s" in group "%s": expected boolean',
                    $app,
                    $groupName
                ));
            }

            if ($shouldBeInstalled) {
                $appsToInstall[] = $app;
                continue;
            }

            $appsToUninstall[] = $app;
        }

        $uninstallFailed = 0;
        foreach ($appsToUninstall as $appToUninstall) {
            $uninstallFailed += $this->executeAppUninstall($appToUninstall, $output);
        }

        $installFailed = 0;
        foreach ($appsToInstall as $appToInstall) {
            $installFailed += $this->executeAppInstall($appToInstall, $output);
        }

        return $uninstallFailed + $installFailed;
    }

    private function executeAppUninstall(string $appName, OutputInterface $output): int
    {
        try {
            $this->logger->info(sprintf('Uninstalling app: %s', $appName));
            $exitCode = $this->runCommand([
                'command' => 'app:uninstall',
                'name' => $appName,
            ], $output);
            if ($exitCode !== self::SUCCESS) {
                $this->logger->error(sprintf(
                    'Error while uninstalling app: %s (exit code %d)',
                    $appName,
                    $exitCode,
                ));

                return self::FAILURE;
            }
            $this->logger->info(sprintf('Successfully uninstalled app: %s', $appName));
        } catch (Exception|ExceptionInterface $e) {
            $this->logger->error('Error while uninstalling app: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function executeAppInstall(string $appName, OutputInterface $output): int
    {
        try {
            $this->logger->info(sprintf('Installing app: %s', $appName));
            $exitCode = $this->runCommand([
                'command' => 'app:install',
                'name' => $appName,
                '--activate' => true,
                '--force' => true,
            ], $output);
            if ($exitCode !== self::SUCCESS) {
                $this->logger->error(sprintf('Error while installing app: %s (exit code %d)', $appName, $exitCode));

                return self::FAILURE;
            }
            $this->logger->info(sprintf('Successfully installed app: %s', $appName));
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

        $input = new ArrayInput($parameters);
        $input->setInteractive(false);

        return $command->run($input, $output);
    }
}
