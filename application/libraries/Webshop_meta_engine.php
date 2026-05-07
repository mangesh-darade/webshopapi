<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Meta renderer for dynamic CMS tags.
 */
class Webshop_meta_engine
{
    public function render_meta_html($tags, array $context = array())
    {
        if (!is_array($tags) || empty($tags)) {
            return '';
        }

        $lines = array();
        $titleWritten = false;
        foreach ($tags as $tag) {
            $entry = is_object($tag) ? (array) $tag : (is_array($tag) ? $tag : array());
            $property = isset($entry['property_name']) ? trim((string) $entry['property_name']) : '';
            if ($property === '') {
                continue;
            }
            $value = isset($entry['value']) ? $this->replace_placeholders((string) $entry['value'], $context) : '';
            if ($value === '') {
                continue;
            }

            // Core title/meta
            if ($property === 'title') {
                $lines[] = '<title>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</title>';
                $titleWritten = true;
                continue;
            }
            if ($property === 'meta_description') {
                $lines[] = '<meta name="description" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            if ($property === 'canonical') {
                $lines[] = '<link rel="canonical" href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            if ($property === 'robots') {
                $lines[] = '<meta name="robots" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }

            // OpenGraph/Twitter
            if (strpos($property, 'og:') === 0) {
                $lines[] = '<meta property="' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8') . '" content="'
                    . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            if (strpos($property, 'twitter:') === 0) {
                $lines[] = '<meta name="' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8') . '" content="'
                    . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }

            // Generic fallback
            $lines[] = '<meta name="' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8') . '" content="'
                . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
        }

        if (!$titleWritten && !empty($context['page_title'])) {
            array_unshift($lines, '<title>' . htmlspecialchars((string) $context['page_title'], ENT_QUOTES, 'UTF-8') . '</title>');
        }

        return implode("\n", $lines);
    }

    public function replace_placeholders($template, array $context = array())
    {
        if (!is_string($template) || $template === '') {
            return '';
        }
        return (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($context) {
            $key = isset($m[1]) ? (string) $m[1] : '';
            return isset($context[$key]) ? (string) $context[$key] : '';
        }, $template);
    }
}

