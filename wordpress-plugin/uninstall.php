<?php
/**
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * Removes all data stored by the plugin:
 * - Plugin options (system prompt)
 * - User meta (OpenRouter API keys)
 * - Post meta (saved JSON-LD schemas)
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options.
delete_option('sog_system_prompt');

// Remove API keys from all users.
delete_metadata('user', 0, 'sog_openrouter_api_key', '', true);

// Remove saved schemas from all posts.
delete_metadata('post', 0, '_sog_schema_json_ld', '', true);
