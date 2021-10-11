<?php declare(strict_types=1);

namespace Flagbit\Shopware\ShopwareMaintenance\Command;


use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $filePath = $this->projectDir . '/config/config.yaml';
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
        if ($name !== null) {
            $this->output->writeln(sprintf('Current config scope: "%s"', $name));
        }
        foreach ($config as $key => $value) {
            $currentValue = $this->systemConfigService->get($key, $salesChannelId);
            $this->output->writeln(sprintf('Current value: "%s" for key: "%s"', $currentValue, $key));
            // using string comparison for all values (array|bool|float|int|string|null) simplified
            if ((string) $currentValue !== (string) $value) {
                $this->systemConfigService->set($key, $value, $salesChannelId);
                $this->output->writeln(sprintf('Changed value to: "%s" for key: "%s"', $value, $key));
            } else {
                $this->output->writeln(sprintf('Did not changed the value for key: "%s"', $key));
            }
        }
    }

    private function executeSalesChannelConfigSync(array $yaml): void
    {
        $salesChannels = $this->getSalesChannels();
        foreach ($salesChannels as $name => $salesChannelId) {
            if (isset($yaml[$name])) {
                $this->executeConfigSet($yaml[$name], $name, $salesChannelId);
                $this->output->writeln('---------------------------------------');
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
}
