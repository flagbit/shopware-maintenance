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

        $enabledApps = [];
        $disabledApps = [];
        foreach ($apps as $app => $isEnabled) {
            if (!is_bool($isEnabled)) {
                throw new \RuntimeException(sprintf(
                    'Invalid value for app "%s" in group "%s": expected boolean',
                    $app,
                    $groupName
                ));
            }

            if ($isEnabled) {
                $enabledApps[] = $app;
                continue;
            }

            $disabledApps[] = $app;
        }

        $uninstallFailed = 0;
        foreach ($disabledApps as $disabledApp) {
            $uninstallFailed += $this->executeAppUninstall($disabledApp, $output);
        }

        $installFailed = 0;
        foreach ($enabledApps as $enabledPlugin) {
            $installFailed += $this->executeAppInstall($enabledPlugin, $output);
        }

        return $uninstallFailed + $installFailed;
    }

    private function executeAppUninstall(string $disabledApp, OutputInterface $output): int
    {
        try {
            $this->logger->info(sprintf('Uninstalling app: %s', $disabledApp));
            $this->runCommand([
                'command' => 'app:uninstall',
                'name' => $disabledApp,
            ], $output);
            $this->logger->info(sprintf('Successfully uninstalled app: %s', $disabledApp));
        } catch (Exception|ExceptionInterface $e) {
            $this->logger->error('Error while uninstalling app: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function executeAppInstall(string $enabledApp, OutputInterface $output): int
    {
        try {
            $this->logger->info(sprintf('Installing app: %s', $enabledApp));
            $this->runCommand([
                'command' => 'app:install',
                'name' => $enabledApp,
                '--activate' => true,
                '--force' => true,
            ], $output);
            $this->logger->info(sprintf('Successfully installed app: %s', $enabledApp));
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
