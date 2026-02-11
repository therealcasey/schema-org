<?php

namespace Sog;

if (! defined('ABSPATH')) {
    exit;
}

class Shortcode
{
    public static function register(): void
    {
        add_shortcode('schema_generator', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets(): void
    {
        if (! is_singular()) {
            return;
        }

        global $post;
        if (! $post instanceof \WP_Post || ! has_shortcode($post->post_content, 'schema_generator')) {
            return;
        }

        wp_enqueue_style(
            'sog-schema-app',
            SOG_PLUGIN_URL . 'assets/css/schema-app.css',
            [],
            SOG_VERSION,
        );

        wp_enqueue_script(
            'sog-schema-app',
            SOG_PLUGIN_URL . 'assets/js/schema-app.js',
            [],
            SOG_VERSION,
            true,
        );

        wp_localize_script('sog-schema-app', 'sogConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sog_generate'),
            'hasApiKey' => self::userHasApiKey(),
        ]);
    }

    /**
     * @param array<string, string>|string $atts Shortcode attributes.
     */
    public static function render(array|string $atts = []): string
    {
        ob_start();
        require SOG_PLUGIN_DIR . 'templates/schema-form.php';

        return (string) ob_get_clean();
    }

    private static function userHasApiKey(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        $key = get_user_meta(get_current_user_id(), 'sog_openrouter_api_key', true);

        return $key !== '' && $key !== false;
    }
}
