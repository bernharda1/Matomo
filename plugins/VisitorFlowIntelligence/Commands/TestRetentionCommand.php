<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\RetentionManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TestRetentionCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this->setName('visitorflow:test-retention');
        $this->setDescription('Test VisitorFlowIntelligence data retention with dry-run mode.');
        $this->addOption(
            'execute',
            null,
            InputOption::VALUE_NONE,
            'Actually delete data. Without this flag, only reports what would be deleted.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = !$input->getOption('execute');
        $manager = new RetentionManager();
        $result = $manager->purgeOldData($dryRun);

        if ($dryRun) {
            $output->writeln('<info>[DRY-RUN MODE]</info>');
            $output->writeln('<comment>No data will be deleted. Re-run with --execute to actually delete.</comment>');
        } else {
            $output->writeln('<info>[EXECUTION MODE]</info>');
            $output->writeln('<error>Data has been deleted!</error>');
        }

        $output->writeln('');
        $output->writeln(sprintf('Raw data records to delete: <fg=cyan>%d</>', $result['rawDeleted']));
        $output->writeln(sprintf('Aggregate records to delete: <fg=cyan>%d</>', $result['aggregateDeleted']));
        $output->writeln(sprintf('Total records: <fg=cyan>%d</>', $result['rawDeleted'] + $result['aggregateDeleted']));

        return 0;
    }
}
