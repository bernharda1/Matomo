<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Infrastructure;

use Piwik\ArchiveProcessor;
use Piwik\Db;
use Piwik\Common;
use Piwik\Log;

/**
 * Base Archiver for GeoPrecision
 */
abstract class BaseArchiver
{
    protected ArchiveProcessor $archiveProcessor;
    protected string $pluginName;
    protected int $idSite;
    protected string $period;
    protected string $date;

    public function __construct(ArchiveProcessor $archiveProcessor)
    {
        $this->archiveProcessor = $archiveProcessor;
        $this->idSite = $archiveProcessor->getIdSite();
        $this->period = $archiveProcessor->getPeriod()->getLabel();
        $this->date = $archiveProcessor->getPeriod()->getRangeString();
    }

    abstract public function aggregate(): void;

    protected function getRawDataTableName(): string
    {
        return Common::prefixTable('plugin_' . strtolower($this->pluginName) . '_raw');
    }

    protected function getAggregateTableName(): string
    {
        return Common::prefixTable('period_' . strtolower($this->pluginName) . '_aggregate');
    }

    protected function log(string $message): void
    {
        Log::info("[{$this->pluginName} Archiver] {$message}");
    }

    protected function getDateRange(): array
    {
        $period = $this->archiveProcessor->getPeriod();
        return [
            'start' => $period->getStartDate()->toString(),
            'end' => $period->getEndDate()->toString(),
        ];
    }

    protected function saveMetric(string $name, float $value): void
    {
        $this->archiveProcessor->insertNumericRecord($name, $value);
    }

    protected function saveDataTable(string $name, array $data): void
    {
        $this->archiveProcessor->insertBlobRecord($name, serialize($data));
    }

    protected function isArchiveAlreadyDone(): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM " . Common::prefixTable('archive_numeric') . "
                    WHERE idarchive = ? AND name LIKE ?";
            $archiveId = $this->archiveProcessor->getArchiveId();
            $result = Db::fetchOne($sql, [$archiveId, $this->pluginName . '%']);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
}
