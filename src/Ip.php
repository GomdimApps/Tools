<?php

namespace GomdimApps\Tools;

use Illuminate\Support\Facades\Cache;

class Ip
{
    public static function getDetails(?string $ip): ?array
    {
        $ip = trim((string) $ip);

        if (blank($ip)) {
            return null;
        }

        if (self::isLocalIp($ip)) {
            return [
                'status' => 'fail',
                'message' => 'local_ip',
            ];
        }

        return Cache::remember("ip_details:{$ip}", config('tools.ip.cache_ttl', 86400), function () use ($ip) {
            $fields = 'status,message,continent,continentCode,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,mobile,proxy,hosting,query';

            // 1. Try the primary API
            $api = RequestCall::make("http://ip-api.com/json/{$ip}")
                ->timeout(3)
                ->withQuery(['fields' => $fields])
                ->execute();

            if ($api->isSuccessful() && data_get($api->json(), 'status') === 'success') {
                return $api->json();
            }

            // 2. Try the fallback
            $fallback = RequestCall::make("https://ipapi.co/{$ip}/json/")
                ->timeout(3)
                ->execute();

            if ($fallback->isSuccessful() && ! isset($fallback->json()['error'])) {
                $data = $fallback->json();

                return [
                    'query' => data_get($data, 'ip', $ip),
                    'status' => 'success',
                    'country' => data_get($data, 'country_name'),
                    'countryCode' => data_get($data, 'country_code'),
                    'region' => data_get($data, 'region_code'),
                    'regionName' => data_get($data, 'region'),
                    'city' => data_get($data, 'city'),
                    'zip' => data_get($data, 'postal'),
                    'lat' => data_get($data, 'latitude'),
                    'lon' => data_get($data, 'longitude'),
                    'timezone' => data_get($data, 'timezone'),
                    'currency' => data_get($data, 'currency'),
                    'isp' => data_get($data, 'org'),
                    'as' => data_get($data, 'asn'),
                    'mobile' => false,
                    'proxy' => false,
                    'hosting' => false,
                ];
            }

            // 3. Total failure
            return [
                'status' => 'fail',
                'message' => 'fetch_error',
            ];
        });
    }

    private static function isLocalIp(string $ip): bool
    {
        if (in_array($ip, ['localhost', '—'])) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
