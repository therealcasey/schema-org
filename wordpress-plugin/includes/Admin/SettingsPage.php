<?php

namespace Sog\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    public static function addMenuPage(): void
    {
        add_options_page(
            'Schema Generator',
            'Schema Generator',
            'manage_options',
            'schema-org-generator',
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting('sog_settings', 'sog_system_prompt', [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizePrompt'],
            'default' => sog_get_default_prompt(),
        ]);
    }

    public static function sanitizePrompt(string $value): string
    {
        $value = wp_kses_post($value);

        // Ensure the prompt still contains the required placeholders.
        $required = ['{{content}}'];
        foreach ($required as $placeholder) {
            if (strpos($value, $placeholder) === false) {
                add_settings_error(
                    'sog_system_prompt',
                    'missing_placeholder',
                    sprintf('The prompt must contain the %s placeholder.', $placeholder)
                );
                return get_option('sog_system_prompt', sog_get_default_prompt());
            }
        }

        return $value;
    }

    public static function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        require SOG_PLUGIN_DIR . 'templates/admin-settings.php';
    }
}
