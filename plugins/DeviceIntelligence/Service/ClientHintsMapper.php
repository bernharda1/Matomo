<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence\Service;

final class ClientHintsMapper
{
    private const MAX_UADATA_BYTES = 8192;

    /**
     * @return array<string, mixed>
     */
    public function mapRaw(string $uaDataRaw): array
    {
        $uaDataRaw = trim($uaDataRaw);
        if ($uaDataRaw === '') {
            throw new \DomainException('uaDataRaw must not be empty.');
        }

        if (strlen($uaDataRaw) > self::MAX_UADATA_BYTES) {
            throw new \DomainException('uaDataRaw exceeds max payload size of 8KB.');
        }

        $decoded = json_decode($uaDataRaw, true);
        if (!is_array($decoded)) {
            throw new \DomainException('uaDataRaw is not valid JSON.');
        }

        $browserName = $this->extractBrowserName($decoded);
        $browserVersion = $this->extractBrowserVersion($decoded, $browserName);
        $osName = $this->normalizeString((string) ($decoded['platform'] ?? 'unknown'));
        $osVersion = $this->normalizeString((string) ($decoded['platformVersion'] ?? 'unknown'));
        $model = $this->normalizeString((string) ($decoded['model'] ?? 'unknown'));
        $brand = $this->extractBrand($decoded, $browserName);

        $isMobile = (bool) ($decoded['mobile'] ?? false);
        $deviceType = $isMobile ? 'smartphone' : 'desktop';

        return [
            'clientHintsPresent' => true,
            'deviceType' => $deviceType,
            'brand' => $brand,
            'model' => $model,
            'osName' => $osName,
            'osVersion' => $osVersion,
            'browserName' => $browserName,
            'browserVersion' => $browserVersion,
            'uaDataRaw' => $decoded,
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractBrowserName(array $decoded): string
    {
        $brands = $decoded['brands'] ?? $decoded['fullVersionList'] ?? [];
        if (!is_array($brands)) {
            return 'unknown';
        }

        foreach ($brands as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $candidate = $this->normalizeString((string) ($entry['brand'] ?? ''));
            if ($candidate === 'unknown') {
                continue;
            }

            if (str_contains(strtolower($candidate), 'not') && str_contains(strtolower($candidate), 'brand')) {
                continue;
            }

            return $candidate;
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractBrowserVersion(array $decoded, string $browserName): string
    {
        $fullVersionList = $decoded['fullVersionList'] ?? [];
        if (!is_array($fullVersionList)) {
            return 'unknown';
        }

        foreach ($fullVersionList as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $brand = $this->normalizeString((string) ($entry['brand'] ?? ''));
            if ($browserName !== 'unknown' && strtolower($brand) !== strtolower($browserName)) {
                continue;
            }

            $version = $this->normalizeString((string) ($entry['version'] ?? 'unknown'));
            if ($version !== 'unknown') {
                return $version;
            }
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractBrand(array $decoded, string $browserName): string
    {
        $brands = $decoded['brands'] ?? [];
        if (!is_array($brands)) {
            return 'unknown';
        }

        foreach ($brands as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $brand = $this->normalizeString((string) ($entry['brand'] ?? 'unknown'));
            if ($brand === 'unknown') {
                continue;
            }

            if (strtolower($brand) === strtolower($browserName)) {
                return $brand;
            }
        }

        return 'unknown';
    }

    private function normalizeString(string $value): string
    {
        $value = trim($value);

        return $value === '' ? 'unknown' : $value;
    }
}
