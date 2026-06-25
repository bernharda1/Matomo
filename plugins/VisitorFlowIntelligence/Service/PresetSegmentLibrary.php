<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

/**
 * SB-021.2: PresetSegmentLibrary
 * 
 * Comprehensive preset segment library
 * Provides 20+ built-in segments for common use cases
 */
class PresetSegmentLibrary
{
    /**
     * Get all preset segments
     */
    public static function getAllPresets(): array
    {
        return array_merge(
            self::getDevicePresets(),
            self::getGeographyPresets(),
            self::getReferrerPresets(),
            self::getBehaviorPresets(),
            self::getTrafficPresets(),
            self::getEngagementPresets()
        );
    }

    /**
     * Get device-based presets
     */
    public static function getDevicePresets(): array
    {
        return [
            [
                'id' => 'mobile_visitors',
                'name' => 'Mobile Visitors',
                'category' => 'Device',
                'description' => 'All visitors using mobile devices',
                'rules' => [
                    ['field' => 'deviceType', 'operator' => '==', 'value' => 'mobile'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'tablet_visitors',
                'name' => 'Tablet Visitors',
                'category' => 'Device',
                'description' => 'All visitors using tablets',
                'rules' => [
                    ['field' => 'deviceType', 'operator' => '==', 'value' => 'tablet'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'desktop_visitors',
                'name' => 'Desktop Visitors',
                'category' => 'Device',
                'description' => 'All visitors on desktop computers',
                'rules' => [
                    ['field' => 'deviceType', 'operator' => '==', 'value' => 'desktop'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'mobile_tablet',
                'name' => 'Mobile & Tablet (Any Device)',
                'category' => 'Device',
                'description' => 'Visitors on mobile or tablet devices',
                'rules' => [
                    ['field' => 'deviceType', 'operator' => '!=', 'value' => 'desktop'],
                ],
                'operator' => 'AND',
            ],
        ];
    }

    /**
     * Get geography-based presets
     */
    public static function getGeographyPresets(): array
    {
        return [
            [
                'id' => 'de_visitors',
                'name' => 'Visitors from Germany',
                'category' => 'Geography',
                'description' => 'Traffic from Germany (DE)',
                'rules' => [
                    ['field' => 'country', 'operator' => '==', 'value' => 'de'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'eu_visitors',
                'name' => 'Visitors from Europe',
                'category' => 'Geography',
                'description' => 'Traffic from DACH region (DE, AT, CH)',
                'rules' => [
                    ['field' => 'country', 'operator' => 'in', 'value' => 'de,at,ch'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'non_de',
                'name' => 'Non-German Visitors',
                'category' => 'Geography',
                'description' => 'All traffic excluding Germany',
                'rules' => [
                    ['field' => 'country', 'operator' => '!=', 'value' => 'de'],
                ],
                'operator' => 'AND',
            ],
        ];
    }

    /**
     * Get referrer-based presets
     */
    public static function getReferrerPresets(): array
    {
        return [
            [
                'id' => 'direct_traffic',
                'name' => 'Direct Traffic',
                'category' => 'Referrer',
                'description' => 'Visitors who came directly (no referrer)',
                'rules' => [
                    ['field' => 'referrerType', 'operator' => '==', 'value' => 'direct'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'search_traffic',
                'name' => 'Search Traffic',
                'category' => 'Referrer',
                'description' => 'Visitors from search engines',
                'rules' => [
                    ['field' => 'referrerType', 'operator' => '==', 'value' => 'search'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'social_traffic',
                'name' => 'Social Media Traffic',
                'category' => 'Referrer',
                'description' => 'Visitors from social media platforms',
                'rules' => [
                    ['field' => 'referrerType', 'operator' => '==', 'value' => 'social'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'referral_traffic',
                'name' => 'Referral Traffic',
                'category' => 'Referrer',
                'description' => 'Visitors from other websites',
                'rules' => [
                    ['field' => 'referrerType', 'operator' => '==', 'value' => 'referral'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'organic_traffic',
                'name' => 'Organic Traffic',
                'category' => 'Referrer',
                'description' => 'Visitors from search and social (organic)',
                'rules' => [
                    ['field' => 'referrerType', 'operator' => 'in', 'value' => 'search,social'],
                ],
                'operator' => 'OR',
            ],
        ];
    }

    /**
     * Get behavior-based presets
     */
    public static function getBehaviorPresets(): array
    {
        return [
            [
                'id' => 'new_visitors',
                'name' => 'New Visitors',
                'category' => 'Behavior',
                'description' => 'First-time visitors',
                'rules' => [
                    ['field' => 'visitorType', 'operator' => '==', 'value' => 'new'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'returning_visitors',
                'name' => 'Returning Visitors',
                'category' => 'Behavior',
                'description' => 'Visitors who have been here before',
                'rules' => [
                    ['field' => 'visitorType', 'operator' => '==', 'value' => 'returning'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'high_engagement',
                'name' => 'High Engagement (10+ actions)',
                'category' => 'Behavior',
                'description' => 'Visitors with 10 or more page views',
                'rules' => [
                    ['field' => 'actionCount', 'operator' => '>=', 'value' => '10'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'low_engagement',
                'name' => 'Low Engagement (1-2 actions)',
                'category' => 'Behavior',
                'description' => 'Visitors with only 1-2 page views',
                'rules' => [
                    ['field' => 'actionCount', 'operator' => '<=', 'value' => '2'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'long_duration',
                'name' => 'Long Session (>5 min)',
                'category' => 'Behavior',
                'description' => 'Visitors who spent more than 5 minutes',
                'rules' => [
                    ['field' => 'visitDuration', 'operator' => '>', 'value' => '300'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'bounce_risk',
                'name' => 'Bounce Risk (<30 sec)',
                'category' => 'Behavior',
                'description' => 'Visitors at risk of bouncing',
                'rules' => [
                    ['field' => 'visitDuration', 'operator' => '<', 'value' => '30'],
                ],
                'operator' => 'AND',
            ],
        ];
    }

    /**
     * Get traffic-based presets
     */
    public static function getTrafficPresets(): array
    {
        return [
            [
                'id' => 'chrome_users',
                'name' => 'Chrome Browser Users',
                'category' => 'Browser',
                'description' => 'Visitors using Google Chrome',
                'rules' => [
                    ['field' => 'browserName', 'operator' => '==', 'value' => 'Chrome'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'firefox_users',
                'name' => 'Firefox Browser Users',
                'category' => 'Browser',
                'description' => 'Visitors using Mozilla Firefox',
                'rules' => [
                    ['field' => 'browserName', 'operator' => '==', 'value' => 'Firefox'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'safari_users',
                'name' => 'Safari Browser Users',
                'category' => 'Browser',
                'description' => 'Visitors using Apple Safari',
                'rules' => [
                    ['field' => 'browserName', 'operator' => '==', 'value' => 'Safari'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'windows_users',
                'name' => 'Windows OS Users',
                'category' => 'OS',
                'description' => 'Visitors on Windows operating system',
                'rules' => [
                    ['field' => 'osName', 'operator' => '==', 'value' => 'Windows'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'macos_users',
                'name' => 'macOS Users',
                'category' => 'OS',
                'description' => 'Visitors on Apple macOS',
                'rules' => [
                    ['field' => 'osName', 'operator' => '==', 'value' => 'macOS'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'linux_users',
                'name' => 'Linux OS Users',
                'category' => 'OS',
                'description' => 'Visitors on Linux operating system',
                'rules' => [
                    ['field' => 'osName', 'operator' => '==', 'value' => 'Linux'],
                ],
                'operator' => 'AND',
            ],
        ];
    }

    /**
     * Get engagement-based presets
     */
    public static function getEngagementPresets(): array
    {
        return [
            [
                'id' => 'goal_converters',
                'name' => 'Goal Converters',
                'category' => 'Engagement',
                'description' => 'Visitors who converted a goal',
                'rules' => [
                    ['field' => 'goalConversions', 'operator' => '>', 'value' => '0'],
                ],
                'operator' => 'AND',
            ],
            [
                'id' => 'multi_goal_converters',
                'name' => 'Multi-Goal Converters (2+)',
                'category' => 'Engagement',
                'description' => 'Visitors who converted multiple goals',
                'rules' => [
                    ['field' => 'goalConversions', 'operator' => '>=', 'value' => '2'],
                ],
                'operator' => 'AND',
            ],
        ];
    }

    /**
     * Get preset by ID
     */
    public static function getPreset(string $id): ?array
    {
        $presets = self::getAllPresets();

        foreach ($presets as $preset) {
            if ($preset['id'] === $id) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Get presets by category
     */
    public static function getPresetsByCategory(string $category): array
    {
        $presets = self::getAllPresets();

        return array_filter($presets, fn($p) => $p['category'] === $category);
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        $categories = [];

        foreach (self::getAllPresets() as $preset) {
            if (!in_array($preset['category'], $categories)) {
                $categories[] = $preset['category'];
            }
        }

        sort($categories);

        return $categories;
    }

    /**
     * Search presets by name or description
     */
    public static function search(string $query): array
    {
        $query = strtolower($query);
        $presets = self::getAllPresets();

        return array_filter($presets, function ($preset) use ($query) {
            return strpos(strtolower($preset['name']), $query) !== false ||
                   strpos(strtolower($preset['description']), $query) !== false;
        });
    }

    /**
     * Get total preset count
     */
    public static function count(): int
    {
        return count(self::getAllPresets());
    }

    /**
     * Export presets as JSON
     */
    public static function exportAsJson(): string
    {
        return json_encode(self::getAllPresets(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Import presets (for backup/restore)
     */
    public static function importFromJson(string $json): array
    {
        return json_decode($json, true) ?? [];
    }
}
