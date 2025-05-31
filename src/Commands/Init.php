<?php

namespace MohammadAlavi\ConfigSync\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Composer plugin that keeps project‑wide tooling configs in sync.
 */
final class Init extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('config-sync:init')
            ->setDescription(
                'Initialize config-sync by creating a default config-sync.json file in the root directory.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->getConfig();
        $this->copyConfigToRootDir($output, $config);

        return 0;
    }

    private function getConfig(): mixed
    {
        $configPath = __DIR__ . '/../config-sync.json';
        if (!file_exists($configPath)) {
            throw new \RuntimeException('config-sync.json file not found.');
        }

        $json = file_get_contents($configPath);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function copyConfigToRootDir(OutputInterface $output, $config): void
    {
        $root = getcwd();
        $target = $root . '/config-sync.json';

        if (file_exists($target)) {
            $output->writeln('<error>config-sync.json already exists — aborting.</error>');
            exit(1);
        }

        file_put_contents(
            $target,
            json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        );
        $output->writeln('<info>Created config-sync.json with default values.</info>');
    }
}
