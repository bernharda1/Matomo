<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

/**
 * SB-022.3: Export - Advanced CSV/PDF Exporter
 * 
 * Formats analytics data for export with custom columns and styling
 */
class AnalyticsExporter
{
    private SegmentAnalyticsService $analyticsService;

    public function __construct(SegmentAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Export to CSV with custom columns
     */
    public function exportToCSV(int $segmentId, array $columns = [], int $days = 30): string
    {
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        
        if (empty($columns)) {
            $columns = ['visits', 'visitors', 'bounce_rate', 'conversion_rate', 'avg_session_duration'];
        }

        $csv = "Segment Analytics Export\n";
        $csv .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $csv .= "Period: Last $days days\n\n";

        // Key Metrics Section
        $csv .= "KEY METRICS\n";
        $csv .= "Metric,Value\n";
        
        foreach ($columns as $column) {
            if (isset($analytics['metrics'][$column])) {
                $label = ucwords(str_replace('_', ' ', $column));
                $value = $this->formatValue($analytics['metrics'][$column], $column);
                $csv .= "\"$label\",\"$value\"\n";
            }
        }

        // Trends Section
        $csv .= "\nTRENDS (Daily)\n";
        $csv .= "Date,Visits,Visitors,Bounce Rate\n";
        
        foreach ($analytics['trends'] as $date => $trend) {
            $csv .= "$date,";
            $csv .= $trend['visits'] ?? 0;
            $csv .= ",";
            $csv .= $trend['visitors'] ?? 0;
            $csv .= ",";
            $csv .= round($trend['bounce_rate'] ?? 0, 2);
            $csv .= "\n";
        }

        // Traffic Breakdown
        $csv .= "\nTRAFFIC SOURCES\n";
        $csv .= "Source,Visits,Visitors,Bounce Rate\n";
        foreach ($analytics['top_sources'] as $source) {
            $csv .= "\"{$source['traffic_source']}\",";
            $csv .= $source['visits'] . ",";
            $csv .= $source['visitors'] . ",";
            $csv .= round($source['bounce_rate'] ?? 0, 2) . "\n";
        }

        // Device Breakdown
        $csv .= "\nDEVICES\n";
        $csv .= "Device,Visits,Visitors,Avg Duration\n";
        foreach ($analytics['device_breakdown'] as $device) {
            $csv .= "\"{$device['device_type']}\",";
            $csv .= $device['visits'] . ",";
            $csv .= $device['visitors'] . ",";
            $csv .= round($device['avg_duration'] ?? 0, 1) . "\n";
        }

        // Top Pages
        $csv .= "\nTOP PAGES\n";
        $csv .= "Page,Views,Unique Visits,Avg Time\n";
        foreach ($analytics['top_pages'] as $page) {
            $csv .= "\"" . addslashes($page['page_name']) . "\",";
            $csv .= $page['views'] . ",";
            $csv .= $page['unique_visits'] . ",";
            $csv .= round($page['avg_time'] ?? 0, 1) . "\n";
        }

        return $csv;
    }

    /**
     * Export to JSON
     */
    public function exportToJSON(int $segmentId, int $days = 30): string
    {
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);

        $export = [
            'metadata' => [
                'segment_id' => $segmentId,
                'generated_at' => date('Y-m-d H:i:s'),
                'period_days' => $days,
            ],
            'analytics' => $analytics,
        ];

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export to HTML report
     */
    public function exportToHTML(int $segmentId, int $days = 30): string
    {
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        $timestamp = date('Y-m-d H:i:s');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Segment Analytics Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            background: #f9f9f9;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2196F3;
            border-bottom: 2px solid #2196F3;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            font-size: 18px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .metric-card {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #2196F3;
        }
        .metric-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Segment Analytics Report</h1>
        <p>Generated: $timestamp | Period: Last $days days</p>

        <h2>Key Metrics</h2>
        <div class="metrics-grid">
HTML;

        foreach ($analytics['metrics'] as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            $formatted = $this->formatValue($value, $key);
            $html .= <<<HTML
            <div class="metric-card">
                <div class="metric-label">$label</div>
                <div class="metric-value">$formatted</div>
            </div>
HTML;
        }

        $html .= "</div>";

        // Traffic Sources Table
        $html .= "<h2>Traffic Sources</h2>";
        $html .= "<table><thead><tr><th>Source</th><th>Visits</th><th>Visitors</th><th>Bounce Rate</th></tr></thead><tbody>";
        foreach ($analytics['top_sources'] as $source) {
            $html .= "<tr>";
            $html .= "<td>{$source['traffic_source']}</td>";
            $html .= "<td>{$source['visits']}</td>";
            $html .= "<td>{$source['visitors']}</td>";
            $html .= "<td>" . round($source['bounce_rate'] ?? 0, 2) . "%</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";

        // Top Pages Table
        $html .= "<h2>Top Pages</h2>";
        $html .= "<table><thead><tr><th>Page</th><th>Views</th><th>Unique Visits</th><th>Avg Time</th></tr></thead><tbody>";
        foreach (array_slice($analytics['top_pages'], 0, 10) as $page) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($page['page_name']) . "</td>";
            $html .= "<td>{$page['views']}</td>";
            $html .= "<td>{$page['unique_visits']}</td>";
            $html .= "<td>" . round($page['avg_time'] ?? 0, 1) . "s</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";

        $html .= <<<HTML
        <div class="footer">
            <p>This report was automatically generated. Please do not modify.</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Format value based on metric type
     */
    private function formatValue($value, string $key): string
    {
        if ($key === 'bounce_rate' || $key === 'conversion_rate' || $key === 'returning_rate') {
            return round((float)$value, 1) . '%';
        }
        
        if ($key === 'avg_session_duration' || $key === 'avg_duration' || $key === 'avg_time') {
            $secs = (int)$value;
            $mins = floor($secs / 60);
            $secs = $secs % 60;
            return $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
        }

        if (is_numeric($value)) {
            return number_format((int)$value);
        }

        return (string)$value;
    }

    /**
     * Create exportable filename
     */
    public function getExportFilename(int $segmentId, string $format): string
    {
        $date = date('Y-m-d-His');
        return "segment-{$segmentId}-analytics-{$date}.{$format}";
    }

    /**
     * Validate export format
     */
    public function isValidFormat(string $format): bool
    {
        return in_array($format, ['csv', 'json', 'html']);
    }
}
