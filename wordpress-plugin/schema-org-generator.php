<?php
/**
 * Plugin Name:       Schema.org Generator
 * Plugin URI:        https://github.com/therealcasey/schema-org
 * Description:       Auto-generate Schema.org JSON-LD structured data from any URL using AI via OpenRouter.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            PirateSERP
 * Author URI:        https://pirateserp.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       schema-org-generator
 * Domain Path:       /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SOG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SOG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SOG_VERSION', '1.0.0');

require_once SOG_PLUGIN_DIR . 'includes/autoload.php';

function sog_activate(): void {
    $default_prompt = sog_get_default_prompt();
    if (get_option('sog_system_prompt') === false) {
        add_option('sog_system_prompt', $default_prompt);
    }
}
register_activation_hook(__FILE__, 'sog_activate');

function sog_deactivate(): void {
    // Flush rewrite rules on deactivation in case shortcode pages
    // registered custom endpoints in the future.
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'sog_deactivate');

function sog_get_default_prompt(): string {
    return <<<'PROMPT'
You are a Schema.org structured data extraction assistant.

Given the following webpage content, identify the single most appropriate Schema.org type and extract all relevant properties.

Return ONLY a valid JSON object with this exact structure:
{
  "type": "SchemaOrgTypeName",
  "properties": {
    "name": "extracted value",
    "description": "extracted value"
  }
}

Rules:
- Use exact Schema.org property names (camelCase).
- Only include properties you are confident about based on the page content.
- For addresses, return a nested object with streetAddress, addressLocality, addressRegion, postalCode, addressCountry.
- For images, return the full absolute URL.
- For phone numbers, include the country code if visible.
- Do not fabricate data that is not present on the page.
- Prefer specific types (e.g. Restaurant over LocalBusiness) when the content clearly indicates one.

URL: {{url}}

Meta tags:
{{metatags}}

Page content:
{{content}}
PROMPT;
}

// Initialize all components.
add_action('init', function () {
    \Sog\Admin\SettingsPage::register();
    \Sog\Shortcode::register();
    \Sog\Ajax::register();
    \Sog\Output::register();
});
