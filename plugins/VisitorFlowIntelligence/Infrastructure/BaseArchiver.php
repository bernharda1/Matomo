<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure;

use Piwik\ArchiveProcessor;
use Piwik\Db;
use Piwik\Common;
use Piwik\Log;

/**
 * SB-014.1: Base Archiver for VisitorFlowIntelligence
 * 
 * Handles pre-aggregation of visitor flow data for fast report generation
 * Runs during Matomo's scheduled archiving process (daily, weekly, monthly)
 */
abstract class BaseArchiver
{
    /**
     * @var ArchiveProcessor
     */
    protected ArchiveProcessor $archiveProcessor;

    /**
     * @var string Plugin name
     */
    protected string $pluginName;

    /**
     * @var string Site ID
     */
    protected int $idSite;

    /**
     * @var string Date range for archiving
     */
    protected string $period;

    /**
     * @var string Date string (YYYY-MM-DD)
     */
    protected string $date;

    public function __construct(ArchiveProcessor $archiveProcessor)
    {
        $this->archiveProcessor = $archiveProcessor;
        $this->idSite = $archiveProcessor->getIdSite();
        $this->period = $archiveProcessor->getPeriod()->getLabel();
        $this->date = $archiveProcessor->getPeriod()->getRangeString();
    }

    /**
     * Main archiving process
     */
    abstract public function aggregate(): void;

    /**
     * Query raw data table
     */
    protected function getRawDataTableName(): string
    {
        return Common::prefixTable('plugin_' . strtolower($this->pluginName) . '_raw');
    }

    /**
     * Query aggregate data table
     */
    protected function getAggregateTableName(): string
    {
        return Common::prefixTable('period_' . strtolower($this->pluginName) . '_aggregate');
    }

    /**
     * Log archiving action
     */
    protected function log(string $message): void
    {
        Log::info("[{$this->pluginName} Archiver] {$message}");
    }

    /**
     * Get date range for this period
     */
    protected function getDateRange(): array
    {
        $period = $this->archiveProcessor->getPeriod();
        return [
            'start' => $period->getStartDate()->toString(),
            'end' => $period->getEndDate()->toString(),
        ];
    }

    /**
     * Save numeric metric to archive
     */
    protected function saveMetric(string $name, float $value): void
    {
        $this->archiveProcessor->insertNumericRecord($name, $value);
    }

    /**
     * Save table (data table) to archive
     */
    protected function saveDataTable(string $name, array $data): void
    {
        $this->archiveProcessor->insertBlobRecord($name, serialize($data));
    }

    /**
     * Check if archiving should run (avoid duplicate runs)
     */
    protected function isArchiveAlreadyDone(): bool
    {
        try {
            $sql = "
                SELECT COUNT(*) FROM " . Common::prefixTable('archive_numeric') . "
                WHERE idarchive = ? AND name LIKE ?
            ";

            $archiveId = $this->archiveProcessor->getArchiveId();
            $result = Db::fetchOne($sql, [$archiveId, $this->pluginName . '%']);

            return !empty($result);
        } catch (\Exception $e) {
            // Archive table might not exist yet
            return false;
        }
    }
}
