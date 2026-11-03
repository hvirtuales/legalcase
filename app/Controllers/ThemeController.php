<?php

namespace App\Controllers;

use App\Models\AppSettingModel;
use CodeIgniter\Controller;

class ThemeController extends Controller
{
    public function customCss()
    {
        // Get database instance
        $db = \Config\Database::connect();

        // Default color
        $primaryColor = '#e10e30';

        // Priority 1: Try to get global theme from settings
        $globalSettings = $db->table('app_settings')
            ->where('setting_key', 'theme_color_primary')
            ->get()
            ->getRowArray();

        if ($globalSettings && !empty($globalSettings['setting_value'])) {
            $primaryColor = $globalSettings['setting_value'];
        }

        // Priority 2: Try to get user-specific theme (overrides global)
        // Access CodeIgniter session directly
        $session = session();
        if ($session->has('user_id')) {
            $userId = $session->get('user_id');

            $userTheme = $db->table('users')
                ->select('theme_color')
                ->where('id', $userId)
                ->get()
                ->getRowArray();

            if ($userTheme && !empty($userTheme['theme_color'])) {
                $primaryColor = $userTheme['theme_color'];
            }
        }

        // Generate color variants
        $rgb = $this->hexToRgb($primaryColor);
        $hsl = $this->rgbToHsl($rgb['r'], $rgb['g'], $rgb['b']);

        $hoverColor = $this->hslToHex($hsl['h'], $hsl['s'], max($hsl['l'] - 10, 0));
        $lightColor = $this->hslToHex($hsl['h'], max($hsl['s'] - 40, 10), min($hsl['l'] + 40, 95));

        // Calculate contrast color (white or black) based on lightness
        // Formula: L = 0.2126 * R + 0.7152 * G + 0.0722 * B
        // Access RGB from earlier calculation
        $luminance = (0.2126 * $rgb['r'] + 0.7152 * $rgb['g'] + 0.0722 * $rgb['b']) / 255;
        $contrastColor = ($luminance > 0.5) ? '#1f2937' : '#ffffff'; // Dark text for light bg, White text for dark bg

        // Set content type and cache headers
        $this->response->setHeader('Content-Type', 'text/css; charset=utf-8');
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');

        $css = "
/* ============================================
   DYNAMIC THEME COLORS
   Generated from database settings via ThemeController
   ============================================ */

:root {
    --color-primary: {$primaryColor};
    --color-primary-hover: {$hoverColor};
    --color-primary-light: {$lightColor};
    --color-primary-contrast: {$contrastColor};
}
";

        return $this->response->setBody($css);
    }

    // Helper helper functions
    private function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        // Handle shorthand hex
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    private function rgbToHsl($r, $g, $b)
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r:
                    $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                    break;
                case $g:
                    $h = (($b - $r) / $d + 2) / 6;
                    break;
                case $b:
                    $h = (($r - $g) / $d + 4) / 6;
                    break;
            }
        }

        return ['h' => $h * 360, 's' => $s * 100, 'l' => $l * 100];
    }

    private function hslToHex($h, $s, $l)
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 60) {
            $r = $c;
            $g = $x;
            $b = 0;
        } elseif ($h < 120) {
            $r = $x;
            $g = $c;
            $b = 0;
        } elseif ($h < 180) {
            $r = 0;
            $g = $c;
            $b = $x;
        } elseif ($h < 240) {
            $r = 0;
            $g = $x;
            $b = $c;
        } elseif ($h < 300) {
            $r = $x;
            $g = 0;
            $b = $c;
        } else {
            $r = $c;
            $g = 0;
            $b = $x;
        }

        $r = round(($r + $m) * 255);
        $g = round(($g + $m) * 255);
        $b = round(($b + $m) * 255);

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}
