<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Theme resolver (Phase 1 foundation).
 * Keeps compatibility by only resolving existing legacy theme names.
 */
class Webshop_theme_engine
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    public function resolve_active_theme($webshop_settings)
    {
        $theme = 'default';
        if (is_object($webshop_settings) && isset($webshop_settings->webshop_theme)) {
            $theme = trim((string) $webshop_settings->webshop_theme);
        } elseif (is_array($webshop_settings) && isset($webshop_settings['webshop_theme'])) {
            $theme = trim((string) $webshop_settings['webshop_theme']);
        }

        return $theme !== '' ? $theme : 'default';
    }
}
