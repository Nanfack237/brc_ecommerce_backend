<?php

namespace App\Services;

use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Http;

class SessionTracker
{
    public static function collect(Request $request): array
    {
        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $deviceType = 'desktop';
        if ($agent->isTablet())     $deviceType = 'tablet';
        elseif ($agent->isMobile()) $deviceType = 'mobile';

        $browser = $agent->browser() ?: 'Inconnu';
        $os      = $agent->platform() ?: 'Inconnu';
        $ip      = $request->ip();

        $city    = null;
        $country = null;

        try {
            $geo = cache()->remember("geoip_{$ip}", 3600, function () use ($ip) {
                $res = Http::timeout(2)
                    ->get("http://ip-api.com/json/{$ip}?fields=city,country,countryCode&lang=fr");
                return $res->ok() ? $res->json() : null;
            });

            if ($geo && isset($geo['countryCode'])) {
                $city    = $geo['city']        ?? null;
                $country = $geo['countryCode'] ?? null;
            }
        } catch (\Throwable) {
            // GeoIP non critique — on continue sans
        }

        return [
            'last_device_type' => $deviceType,
            'last_browser'     => $browser,
            'last_os'          => $os,
            'last_city'        => $city,
            'last_country'     => $country,
            'last_ip'          => $ip,
        ];
    }
}