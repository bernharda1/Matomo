<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\MigrationManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * SB-013.5 Test Command
 * 
 * Test database migration execution with dry-run and execute options
 * 
 * Usage:
 *   ./console visitorflow:test-migrations --status
 *   ./console visitorflow:test-migrations --execute
 *   ./console visitorflow:test-migrations --dry-run
 */
class TestMigrationsCommand extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorflow:test-migrations');
        $this->setDescription('Test VisitorFlowIntelligence database migrations (SB-013)');
        $this->addOption('status', null, InputOption::VALUE_NONE, 'Show migration status');
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Execute pending migrations');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate migration without applying');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pluginPath = PIWIK_INCLUDE_PATH . '/plugins/VisitorFlowIntelligence';
        $migrationPath = $pluginPath . '/Infrastructure/Migrations';

        $manager = new MigrationManager('VisitorFlowIntelligence', $migrationPath);

        if ($input->getOption('status')) {
            return $this->showStatus($output, $manager);
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('<info>Dry-run mode (no changes will be made)</info>');
            return $this->showStatus($output, $manager);
        }

        if ($input->getOption('execute')) {
            return $this->executeMigrations($output, $manager);
        }

        // Default: show status
        return $this->showStatus($output, $manager);
    }

    private function showStatus(OutputInterface $output, MigrationManager $manager): int
    {
        $status = $manager->getStatus();

        $output->writeln('');
        $output->writeln('<info>Migration Status: ' . $status['plugin'] . '</info>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Metric', 'Value']);
        $table->addRows([
            ['Total Migrations', $status['total_migrations']],
            ['Completed', $status['completed']],
            ['Pending', $status['pending']],
        ]);
        $table->render();

        $output->writeln('');
        $output->writeln('<info>Completed Versions:</info>');
        if (empty($status['completed_versions'])) {
            $output->writeln('<comment>None</comment>');
        } else {
            foreach ($status['completed_versions'] as $version) {
                $output->writeln("  ✓ {$version}");
            }
        }

        $output->writeln('');
        $output->writeln('<info>All Available Versions:</info>');
        foreach ($status['all_versions'] as $version) {
            $completed = in_array($version, $status['completed_versions']) ? '✓' : '○';
            $output->writeln("  [{$completed}] {$version}");
        }

        $output->writeln('');
        if ($status['pending'] > 0) {
            $output->writeln('<comment>Run with --execute to apply pending migrations</comment>');
        }
        $output->writeln('');

        return 0;
    }

    private function executeMigrations(OutputInterface $output, MigrationManager $manager): int
    {
        $output->writeln('');
        $output->writeln('<info>Executing pending migrations...</info>');
        $output->writeln('');

        try {
            $executed = $manager->migrate();

            if (empty($executed)) {
                $output->writeln('<comment>No pending migrations to execute</comment>');
            } else {
                $output->writeln('<info>Successfully executed migrations:</info>');
                foreach ($executed as $version) {
                    $output->writeln("  ✓ {$version}");
                }
            }

            $output->writeln('');
            $this->showStatus($output, $manager);

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Migration failed: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
