<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure;

use Piwik\Common;
use Piwik\Db;

/**
 * SB-021.1: SegmentRepository
 * 
 * Persistence layer for custom segments
 */
class SegmentRepository
{
    private const TABLE_SEGMENTS = 'visitorflow_segments';
    private const TABLE_SEGMENT_SHARES = 'visitorflow_segment_shares';
    private const TABLE_SEGMENT_USAGE = 'visitorflow_segment_usage';

    /**
     * Save new segment
     */
    public function save(
        int $siteId,
        ?int $userId,
        string $name,
        string $description,
        string $query,
        array $rules,
        string $operator,
        bool $isPublic
    ): int {
        $table = Common::prefixTable(self::TABLE_SEGMENTS);

        $data = [
            'site_id' => $siteId,
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'query' => $query,
            'rules' => json_encode($rules),
            'operator' => $operator,
            'is_public' => (int)$isPublic,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        Db::query(
            "INSERT INTO {$table} (site_id, user_id, name, description, query, rules, operator, is_public, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array_values($data)
        );

        return (int)Db::get()->lastInsertId();
    }

    /**
     * Update segment
     */
    public function update(
        int $segmentId,
        ?string $name = null,
        ?string $description = null,
        ?array $rules = null,
        ?string $operator = null,
        ?string $query = null,
        ?bool $isPublic = null
    ): bool {
        $table = Common::prefixTable(self::TABLE_SEGMENTS);
        $updates = [];
        $values = [];

        if ($name !== null) {
            $updates[] = 'name = ?';
            $values[] = $name;
        }

        if ($description !== null) {
            $updates[] = 'description = ?';
            $values[] = $description;
        }

        if ($query !== null) {
            $updates[] = 'query = ?';
            $values[] = $query;
        }

        if ($rules !== null) {
            $updates[] = 'rules = ?';
            $values[] = json_encode($rules);
        }

        if ($operator !== null) {
            $updates[] = 'operator = ?';
            $values[] = $operator;
        }

        if ($isPublic !== null) {
            $updates[] = 'is_public = ?';
            $values[] = (int)$isPublic;
        }

        $updates[] = 'updated_at = ?';
        $values[] = time();

        $values[] = $segmentId;

        $updateClause = implode(', ', $updates);

        Db::query(
            "UPDATE {$table} SET {$updateClause} WHERE id = ?",
            $values
        );

        return true;
    }

    /**
     * Delete segment
     */
    public function delete(int $segmentId): bool
    {
        $table = Common::prefixTable(self::TABLE_SEGMENTS);
        $sharesTable = Common::prefixTable(self::TABLE_SEGMENT_SHARES);
        $usageTable = Common::prefixTable(self::TABLE_SEGMENT_USAGE);

        // Delete shares
        Db::query("DELETE FROM {$sharesTable} WHERE segment_id = ?", [$segmentId]);

        // Delete usage
        Db::query("DELETE FROM {$usageTable} WHERE segment_id = ?", [$segmentId]);

        // Delete segment
        Db::query("DELETE FROM {$table} WHERE id = ?", [$segmentId]);

        return true;
    }

    /**
     * Get segment by ID
     */
    public function getById(int $segmentId, int $siteId): ?array
    {
        $table = Common::prefixTable(self::TABLE_SEGMENTS);

        $result = Db::query(
            "SELECT * FROM {$table} WHERE id = ? AND site_id = ?",
            [$segmentId, $siteId]
        );

        $row = $result->fetch();
        if ($row) {
            $row['rules'] = json_decode($row['rules'] ?? '[]', true);
        }

        return $row ?: null;
    }

    /**
     * Get all segments for site
     */
    public function getAll(
        int $siteId,
        ?int $userId = null,
        bool $onlyPublic = false
    ): array {
        $table = Common::prefixTable(self::TABLE_SEGMENTS);

        $conditions = ['site_id = ?'];
        $values = [$siteId];

        if ($onlyPublic) {
            $conditions[] = 'is_public = 1';
        } elseif ($userId) {
            $conditions[] = '(user_id = ? OR is_public = 1)';
            $values[] = $userId;
        }

        $whereClause = implode(' AND ', $conditions);

        $results = Db::query(
            "SELECT * FROM {$table} WHERE {$whereClause} ORDER BY created_at DESC",
            $values
        );

        $segments = [];
        while ($row = $results->fetch()) {
            $row['rules'] = json_decode($row['rules'] ?? '[]', true);
            $segments[] = $row;
        }

        return $segments;
    }

    /**
     * Get preset segments
     */
    public function getPresets(int $siteId): array
    {
        return [
            [
                'id' => 'preset_mobile',
                'name' => 'Mobile Visitors',
                'query' => 'deviceType==mobile',
                'rules' => [['field' => 'deviceType', 'operator' => '==', 'value' => 'mobile']],
            ],
            [
                'id' => 'preset_desktop',
                'name' => 'Desktop Visitors',
                'query' => 'deviceType==desktop',
                'rules' => [['field' => 'deviceType', 'operator' => '==', 'value' => 'desktop']],
            ],
            [
                'id' => 'preset_direct_traffic',
                'name' => 'Direct Traffic',
                'query' => 'referrerType==direct',
                'rules' => [['field' => 'referrerType', 'operator' => '==', 'value' => 'direct']],
            ],
            [
                'id' => 'preset_search_traffic',
                'name' => 'Search Traffic',
                'query' => 'referrerType==search',
                'rules' => [['field' => 'referrerType', 'operator' => '==', 'value' => 'search']],
            ],
        ];
    }

    /**
     * Share segment with user
     */
    public function share(
        int $segmentId,
        int $userId,
        string $permission
    ): void {
        $table = Common::prefixTable(self::TABLE_SEGMENT_SHARES);

        // Check if already shared
        $existing = Db::query(
            "SELECT id FROM {$table} WHERE segment_id = ? AND user_id = ?",
            [$segmentId, $userId]
        )->fetch();

        if ($existing) {
            Db::query(
                "UPDATE {$table} SET permission = ?, updated_at = ? WHERE segment_id = ? AND user_id = ?",
                [$permission, time(), $segmentId, $userId]
            );
        } else {
            Db::query(
                "INSERT INTO {$table} (segment_id, user_id, permission, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
                [$segmentId, $userId, $permission, time(), time()]
            );
        }
    }

    /**
     * Count segment usages
     */
    public function countUsages(int $segmentId): int
    {
        $table = Common::prefixTable(self::TABLE_SEGMENT_USAGE);

        $result = Db::query(
            "SELECT COUNT(*) as count FROM {$table} WHERE segment_id = ?",
            [$segmentId]
        );

        $row = $result->fetch();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get last used timestamp
     */
    public function getLastUsed(int $segmentId): ?int
    {
        $table = Common::prefixTable(self::TABLE_SEGMENT_USAGE);

        $result = Db::query(
            "SELECT MAX(used_at) as last_used FROM {$table} WHERE segment_id = ?",
            [$segmentId]
        );

        $row = $result->fetch();
        return $row['last_used'] ? (int)$row['last_used'] : null;
    }

    /**
     * Count segment shares
     */
    public function countShares(int $segmentId): int
    {
        $table = Common::prefixTable(self::TABLE_SEGMENT_SHARES);

        $result = Db::query(
            "SELECT COUNT(*) as count FROM {$table} WHERE segment_id = ?",
            [$segmentId]
        );

        $row = $result->fetch();
        return (int)($row['count'] ?? 0);
    }
}
