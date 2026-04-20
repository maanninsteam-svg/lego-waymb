<?php

if (!function_exists('_dtier')) {
    /**
     * Applies optional device-tier multiplier.
     */
    function _dtier(string $userAgent, $maxPct = 0, $enabled = false): float
    {
        if (!$enabled) {
            return 1.0;
        }

        $pct = (float)$maxPct;
        if ($pct <= 0) {
            return 1.0;
        }

        $ua = strtolower($userAgent);
        $isMobile = strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false;
        if (!$isMobile) {
            return 1.0;
        }

        // Multiplier capped to avoid extreme pricing.
        $factor = 1.0 + min($pct, 1.0);
        return max(1.0, $factor);
    }
}

