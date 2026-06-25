<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Service;

use Piwik\Cache as MatomoCache;

/**
 * SB-015: Cache Manager for GeoPrecision API Responses
 * 
 * Provides caching for geographic precision data
 * TTL: 1 hour (day), 24 hours (week), 7 days (month)
 */
class CacheManager
{
    private const CACHE_PREFIX = 'cache_geoprecision_';
    private const TTL_DAY = 3600;
    private const TTL_WEEK = 86400;
    private const TTL_MONTH = 604800;
    private const TTL_YEAR = 2592000;

    private MatomoCache $cache;

    public function __construct()
    {
        $this->cache = MatomoCache::getInstance();
    }

    public function get(int $idSite, string $period, string $date, ?string $segment, string $method)
    {
        $key = $this->getCacheKey($idSite, $period, $date, $segment, $method);
        return $this->cache->fetch($key);
    }

    public function set(int $idSite, string $period, string $date, ?string $segment, string $method, $data): bool
    {
        $key = $this->getCacheKey($idSite, $period, $date, $segment, $method);
        $ttl = $this->getTTLForPeriod($period);
        return $this->cache->save($key, $data, $ttl);
    }

    public function invalidateSite(int $idSite): int
    {
        $this->cache->flushAll();
        return 1;
    }

    public function invalidateDateRange(int $idSite, string $dateStart, string $dateEnd): int
    {
        $this->cache->flushAll();
        return 1;
    }

    private function getCacheKey(int $idSite, string $period, string $date, ?string $segment, string $method): string
    {
        $segmentHash = $segment ? md5($segment) : 'none';
        return self::CACHE_PREFIX . "{$idSite}_{$period}_{$date}_{$segmentHash}_{$method}";
    }

    private function getTTLForPeriod(string $period): int
    {
        return match ($period) {
            'day' => self::TTL_DAY,
            'week' => self::TTL_WEEK,
            'month' => self::TTL_MONTH,
            'year' => self::TTL_YEAR,
            default => self::TTL_DAY,
        };
    }
}
