<?php

declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\Command;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\PluginBaseClassNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:sync',
    description: 'Install/uninstall plugins as defined in file config/plugins.php',
)]
class PluginSynchronizeCommand extends Command
{
    public const GROUP_CORE = 'core';
    public const GROUP_THIRD_PARTY = 'third_party';
    public const GROUP_AGENCY = 'agency';
    public const GROUP_PROJECT = 'project';

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
        if (!file_exists($this->projectDir . '/config/plugins.php')) {
            $output->writeln(sprintf('%s not found', $this->projectDir . '/config/plugins.php'));

            return 1;
        }

        $pluginGroups = require $this->projectDir . '/config/plugins.php';

        $errorSum = $this->installUninstallPluginGroup($pluginGroups, self::GROUP_CORE, $output);
        $errorSum += $this->installUninstallPluginGroup($pluginGroups, self::GROUP_THIRD_PARTY, $output);
        $errorSum += $this->installUninstallPluginGroup($pluginGroups, self::GROUP_AGENCY, $output);
        $errorSum += $this->installUninstallPluginGroup($pluginGroups, self::GROUP_PROJECT, $output);

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
    private function installUninstallPluginGroup(array $pluginGroups, string $groupName, OutputInterface $output): int
    {
        if (!array_key_exists($groupName, $pluginGroups)) {
            return 0;
        }

        $plugins = $pluginGroups[$groupName];
        $disabledPlugins = array_keys(array_filter($plugins, function ($isEnabled) {
            return $isEnabled === false;
        }));
        $enabledPlugins = array_keys(array_filter($plugins, function ($isEnabled) {
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

    private function executePluginInstall(string $enabledPlugin, OutputInterface $output): int
    {
        try {
            $this->runCommand([
                'command' => 'plugin:install',
                'plugins' => [$enabledPlugin],
                '--activate' => true,
            ], $output);
        } catch (PluginBaseClassNotFoundException $baseClassNotFoundException) {
            $this->logger->error('Execute plugin:refresh before plugin:sync !');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
