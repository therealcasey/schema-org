<?php

namespace Sog;

if (! defined('ABSPATH')) {
    exit;
}

class Output
{
    public static function register(): void
    {
        add_action('wp_head', [self::class, 'injectJsonLd']);
    }

    /**
     * If the current post/page has saved schema JSON-LD, inject it into <head>.
     */
    public static function injectJsonLd(): void
    {
        if (! is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return;
        }

        $json_ld = get_post_meta($post_id, '_sog_schema_json_ld', true);
        if (empty($json_ld)) {
            return;
        }

        // Validate it's actual JSON before outputting.
        $decoded = json_decode($json_ld, true);
        if ($decoded === null) {
            return;
        }

        // Re-encode to ensure clean output.
        $clean_json = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        echo '<script type="application/ld+json">' . $clean_json . '</script>' . "\n";
    }
}
