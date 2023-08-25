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

use function array_key_exists;
use function file_exists;
use function implode;
use function is_array;
use function sprintf;

class ConfigSynchronizeCommand extends Command
{
    // phpcs:ignore
    protected static $defaultName       = 'config:sync';
    private static string $defaultScope = 'global';
    private OutputInterface $output;

    public function __construct(
        private string $projectDir,
        private SystemConfigService $systemConfigService,
        private EntityRepositoryInterface $salesChannelRepository,
    ) {
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
            'config/config.yaml',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $this->output = $output;
        $configPath   = $input->getArgument('config_path');
        $filePath     = $this->projectDir . '/' . $configPath;
        if (! file_exists($filePath)) {
            $this->output->writeln(sprintf('%s not found', $filePath));

            return self::FAILURE;
        }

        $yamlReader = new Yaml();
        $yaml       = $yamlReader::parseFile($filePath);

        $this->executeGlobalConfigSync($yaml);
        $this->executeSalesChannelConfigSync($yaml);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $yaml */
    private function executeGlobalConfigSync(array $yaml): void
    {
        if (! isset($yaml[self::$defaultScope])) {
            return;
        }

        $this->output->writeln('---------------------------------------');
        $this->executeConfigSet($yaml[self::$defaultScope], self::$defaultScope);
        $this->output->writeln('---------------------------------------');
    }

    /** @param array<string, mixed> $config */
    private function executeConfigSet(array $config, string $name, string|null $salesChannelId = null): void
    {
        $this->output->writeln(sprintf('Current config scope: "%s"', $name));
        $this->output->writeln('---------------------------------------');
        foreach ($config as $key => $value) {
            $currentValue         = $this->systemConfigService->get($key, $salesChannelId);
            $currentValueAsString = $this->valueToString($currentValue);
            $valueAsString        = $this->valueToString($value);
            $this->output->writeln(sprintf('Current value: "%s" for key: "%s"', $currentValueAsString, $key));
            // using string comparison for all values (array|bool|float|int|string|null) simplified
            if ($currentValueAsString !== $valueAsString) {
                $this->systemConfigService->set($key, $value, $salesChannelId);
                $this->output->writeln(sprintf('Changed value to: "%s" for key: "%s"', $valueAsString, $key));
            } else {
                $this->output->writeln(sprintf('Did not changed the value for key: "%s"', $key));
            }
        }
    }

    /** @param array<string, mixed> $yaml */
    private function executeSalesChannelConfigSync(array $yaml): void
    {
        $salesChannelUpdated = $salesChannelNotUpdated = [];
        $salesChannels       = $this->getSalesChannels();
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
            if (array_key_exists($idNotUpdated, $salesChannelUpdated) !== false) {
                continue;
            }

            $this->output->writeln(
                sprintf('>>> No config update for SalesChannel with id: "%s" <<<', $idNotUpdated),
            );
        }
    }

    /** @return array<string, string> */
    private function getSalesChannels(): array
    {
        $salesChannels = [];
        $criteria      = new Criteria();
        $criteria->addAssociation('translations');

        $salesChannelIds = $this->salesChannelRepository->search($criteria, Context::createDefaultContext());
        foreach ($salesChannelIds->getEntities()->getElements() as $salesChannel) {
            foreach ($salesChannel->getTranslations()->getElements() as $translation) {
                $salesChannels[$translation->getName()] = $translation->getSalesChannelId();
            }
        }

        return $salesChannels;
    }

    /** @param scalar|list<scalar> $value */
    private function valueToString(string|int|float|bool|array $value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }
}
