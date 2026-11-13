<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Validates that a value is safe to use as an image source that the PDF engine
 * may fetch: an inline data: image, a schemeless string (no remote fetch), or an
 * http(s) URL whose host resolves only to public IP addresses. Rejects other
 * schemes (file://, ftp://, ...) and URLs pointing at private/reserved/loopback/
 * link-local/CGNAT addresses, to prevent server-side request forgery.
 */
class PublicUrl implements Rule
{
    public function passes($attribute, $value)
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (! is_string($value)) {
            return false;
        }
        // Inline images are rendered without any network request.
        if (str_starts_with($value, 'data:')) {
            return true;
        }

        $parts = parse_url($value);
        if ($parts === false) {
            return false;
        }
        // No scheme: not a remote URL, the engine performs no outbound fetch.
        if (empty($parts['scheme'])) {
            return true;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        if (empty($parts['host'])) {
            return false;
        }

        return $this->hostIsPublic($parts['host']);
    }

    public function message()
    {
        return 'The :attribute must be a publicly reachable http(s) URL or an inline data image.';
    }

    private function hostIsPublic(string $host): bool
    {
        $host = trim($host);
        if ($host === '') {
            return false;
        }
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $rec) {
                    if (! empty($rec['ipv6'])) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }

        if ($ips === []) {
            return false; // fail closed on an unresolvable host
        }
        foreach ($ips as $ip) {
            if (! $this->ipIsPublic($ip)) {
                return false;
            }
        }

        return true;
    }

    private function ipIsPublic(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        // Unwrap IPv4-mapped IPv6 (e.g. ::ffff:169.254.169.254).
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && str_contains($ip, '.')) {
            $tail = substr(strrchr($ip, ':'), 1);
            if ($tail !== false && filter_var($tail, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip = $tail;
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            // Carrier-grade NAT 100.64.0.0/10 is not covered by the filter flags.
            $long = ip2long($ip);
            if ($long !== false && ($long & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
                return false;
            }

            return true;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
