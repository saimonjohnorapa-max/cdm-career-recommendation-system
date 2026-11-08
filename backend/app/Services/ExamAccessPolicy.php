<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class ExamAccessPolicy
{
    public function denial(Request $request): ?string
    {
        $agent = $request->userAgent() ?? '';
        if ($request->header('Sec-CH-UA-Mobile') === '?1'
            || $request->header('X-Exam-Device') === 'mobile'
            || preg_match('/android|iphone|ipad|ipod|mobile|tablet|kindle|silk|opera mini|windows phone/i', $agent)) {
            return 'Mobile phones and tablets cannot take the entrance examination. Use a desktop or laptop at the designated campus examination site. You can still view your results on mobile.';
        }

        $networks = config('exam.allowed_networks', []);
        if (!$networks || !IpUtils::checkIp($this->clientIp($request), $networks)) {
            return 'The entrance examination is available only on the configured campus examination network. Please contact the examination proctor. You can still view your results from this device.';
        }

        return null;
    }

    private function clientIp(Request $request): string
    {
        // Do not use request->ip(): the portal's general proxy middleware trusts all peers.
        $peer = (string) $request->server('REMOTE_ADDR', '');
        $proxies = config('exam.trusted_proxies', []);
        if (!$proxies || !IpUtils::checkIp($peer, $proxies)) return $peer;

        $chain = array_map('trim', explode(',', $request->header('X-Forwarded-For', '')));
        $chain[] = $peer;
        foreach (array_reverse($chain) as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) return '';
            if (!IpUtils::checkIp($ip, $proxies)) return $ip;
        }
        return '';
    }
}
