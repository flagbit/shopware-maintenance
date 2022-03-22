<?php

declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigSynchronizeCommand extends Command
{
    protected static $defaultName = 'config:sync';
    private static string $defaultScope = 'global';
    private string $projectDir;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $salesChannelRepository;
    private OutputInterface $output;

    public function __construct(
        string $projectDir,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $salesChannelRepository
    ) {
        $this->projectDir = $projectDir;
        $this->systemConfigService = $systemConfigService;
        $this->salesChannelRepository = $salesChannelRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Update system config like defined in file config/config.yaml');
        $this->addArgument(
            'config_path',
            InputArgument::OPTIONAL,
            'Path to config yaml',
            'config/config.yaml'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $this->output = $output;
        $configPath = $input->getArgument('config_path');
        $filePath = $this->projectDir . '/' . $configPath;
        if (!file_exists($filePath)) {
            $this->output->writeln(sprintf('%s not found', $filePath));

            return 1;
        }

        $yamlReader = new Yaml();
        $yaml = $yamlReader::parseFile($filePath);

        $this->executeGlobalConfigSync($yaml);
        $this->executeSalesChannelConfigSync($yaml);

        return 0;
    }

    private function executeGlobalConfigSync(array $yaml): void
    {
        if (isset($yaml[self::$defaultScope])) {
            $this->output->writeln('---------------------------------------');
            $this->executeConfigSet($yaml[self::$defaultScope], self::$defaultScope);
            $this->output->writeln('---------------------------------------');
        }
    }

    private function executeConfigSet(array $config, string $name, ?string $salesChannelId = null): void
    {
        $this->output->writeln(sprintf('Current config scope: "%s"', $name));
        $this->output->writeln('---------------------------------------');
        foreach ($config as $key => $value) {
            $currentValue = $this->systemConfigService->get($key, $salesChannelId);
            $strValue = $this->valueToString($value);
            $strCurrentValue = $this->valueToString($currentValue);
            $this->output->writeln(sprintf('Current value: "%s" for key: "%s"', $strCurrentValue, $key));
            // using string comparison for all values (array|bool|float|int|string|null) simplified
            if ($strCurrentValue !== $strValue) {
                $this->systemConfigService->set($key, $value, $salesChannelId);
                $this->output->writeln(sprintf('Changed value to: "%s" for key: "%s"', $strValue, $key));
            } else {
                $this->output->writeln(sprintf('Did not changed the value for key: "%s"', $key));
            }
        }
    }

    private function executeSalesChannelConfigSync(array $yaml): void
    {
        $salesChannelUpdated = $salesChannelNotUpdated = [];
        $salesChannels = $this->getSalesChannels();
        foreach ($salesChannels as $name => $salesChannelId) {
            if (isset($yaml[$name])) {
                $this->executeConfigSet($yaml[$name], $name, $salesChannelId);
                $salesChannelUpdated[$salesChannelId] = $name;
                $this->output->writeln('---------------------------------------');
            } else {
                $salesChannelNotUpdated[$salesChannelId] = $name;
            }
        }

        // put info message only if the salesChannel was totally not updated,
        // we get a salesChannel for every translation, to work in yaml file with the name of the translation
        foreach ($salesChannelNotUpdated as $idNotUpdated => $name) {
            if (array_key_exists($idNotUpdated, $salesChannelUpdated) === false) {
                $this->output->writeln(
                    sprintf('>>> No config update for SalesChannel with id: "%s" <<<', $idNotUpdated)
                );
            }
        }
    }

    private function getSalesChannels(): array
    {
        $salesChannels = [];
        $criteria = new Criteria();
        $criteria->addAssociation('translations');

        $salesChannelIds = $this->salesChannelRepository->search($criteria, Context::createDefaultContext());
        foreach ($salesChannelIds->getEntities()->getElements() as $salesChannel) {
            foreach ($salesChannel->getTranslations()->getElements() as $translation) {
                $salesChannels[$translation->getName()] = $translation->getSalesChannelId();
            }
        }

        return $salesChannels;
    }

    private function valueToString($value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }
}
