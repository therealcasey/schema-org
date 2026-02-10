<?php

namespace Sog;

if (! defined('ABSPATH')) {
    exit;
}

class Ajax
{
    public static function register(): void
    {
        add_action('wp_ajax_sog_save_api_key', [self::class, 'saveApiKey']);
        add_action('wp_ajax_sog_generate_schema', [self::class, 'generateSchema']);
        add_action('wp_ajax_sog_save_schema', [self::class, 'saveSchema']);
    }

    /**
     * Store the user's OpenRouter API key in user_meta.
     */
    public static function saveApiKey(): void
    {
        check_ajax_referer('sog_generate', 'nonce');

        $key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        if (empty($key)) {
            wp_send_json_error(['message' => 'API key is required.']);
        }

        update_user_meta(get_current_user_id(), 'sog_openrouter_api_key', $key);
        wp_send_json_success(['message' => 'API key saved.']);
    }

    /**
     * Fetch a URL, extract content, and send to OpenRouter for schema generation.
     */
    public static function generateSchema(): void
    {
        check_ajax_referer('sog_generate', 'nonce');

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if (empty($url)) {
            wp_send_json_error(['message' => 'A valid URL is required.']);
        }

        $api_key = get_user_meta(get_current_user_id(), 'sog_openrouter_api_key', true);
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Please save your OpenRouter API key first.']);
        }

        // 1. Fetch the page HTML.
        $html = self::fetchUrl($url);
        if (is_wp_error($html)) {
            wp_send_json_error(['message' => 'Failed to fetch URL: ' . $html->get_error_message()]);
        }

        // 2. Extract content and meta tags.
        $extracted = self::extractContent($html);

        // 3. Build the prompt from admin settings.
        $system_prompt = get_option('sog_system_prompt', sog_get_default_prompt());
        $system_prompt = str_replace('{{url}}', $url, $system_prompt);
        $system_prompt = str_replace('{{metatags}}', $extracted['metatags'], $system_prompt);
        $system_prompt = str_replace('{{content}}', $extracted['content'], $system_prompt);

        // 4. Call OpenRouter.
        $result = self::callOpenRouter($api_key, $system_prompt);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'OpenRouter error: ' . $result->get_error_message()]);
        }

        // 5. Parse the LLM response into structured data.
        $schema = self::parseLlmResponse($result);
        if ($schema === null) {
            wp_send_json_error(['message' => 'Could not parse a valid schema from the AI response.', 'raw' => $result]);
        }

        wp_send_json_success($schema);
    }

    /**
     * Save generated schema JSON-LD to a post's meta.
     */
    public static function saveSchema(): void
    {
        check_ajax_referer('sog_generate', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $json_ld = isset($_POST['json_ld']) ? wp_unslash($_POST['json_ld']) : '';

        if (empty($post_id) || empty($json_ld)) {
            wp_send_json_error(['message' => 'Post ID and JSON-LD are required.']);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'You do not have permission to edit this post.']);
        }

        // Validate that the JSON-LD is valid JSON.
        $decoded = json_decode($json_ld, true);
        if ($decoded === null) {
            wp_send_json_error(['message' => 'Invalid JSON-LD.']);
        }

        update_post_meta($post_id, '_sog_schema_json_ld', $json_ld);
        wp_send_json_success(['message' => 'Schema saved to page.']);
    }

    /**
     * Fetch a remote URL using WordPress HTTP API.
     */
    private static function fetchUrl(string $url): string|\WP_Error
    {
        $response = wp_remote_get($url, [
            'timeout' => 20,
            'user-agent' => 'SchemaOrgGenerator/' . SOG_VERSION . ' (WordPress)',
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return new \WP_Error('http_error', "URL returned HTTP $code");
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Extract readable text content and meta tags from HTML.
     */
    private static function extractContent(string $html): array
    {
        $metatags = '';
        $content = '';

        // Suppress DOM parsing warnings for malformed HTML.
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Extract meta tags (standard + Open Graph).
        $metas = $xpath->query('//meta[@name or @property]');
        $meta_lines = [];
        foreach ($metas as $meta) {
            $key = $meta->getAttribute('name') ?: $meta->getAttribute('property');
            $val = $meta->getAttribute('content');
            if ($key && $val) {
                $meta_lines[] = "$key: $val";
            }
        }

        // Extract <title>.
        $titles = $xpath->query('//title');
        if ($titles->length > 0) {
            array_unshift($meta_lines, 'title: ' . trim($titles->item(0)->textContent));
        }

        $metatags = implode("\n", $meta_lines);

        // Extract existing JSON-LD if present.
        $json_ld_scripts = $xpath->query('//script[@type="application/ld+json"]');
        $existing_schemas = [];
        foreach ($json_ld_scripts as $script) {
            $existing_schemas[] = trim($script->textContent);
        }
        if ($existing_schemas) {
            $metatags .= "\n\nExisting JSON-LD on page:\n" . implode("\n", $existing_schemas);
        }

        // Extract body text: remove script/style tags, then get text.
        $remove_tags = $xpath->query('//script | //style | //noscript | //iframe');
        foreach ($remove_tags as $tag) {
            $tag->parentNode->removeChild($tag);
        }

        $body = $xpath->query('//body');
        if ($body->length > 0) {
            $content = $body->item(0)->textContent;
        }

        // Clean up whitespace.
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        // Truncate to avoid exceeding token limits (~12k chars ≈ ~3k tokens).
        if (mb_strlen($content) > 12000) {
            $content = mb_substr($content, 0, 12000) . "\n\n[Content truncated]";
        }

        return [
            'metatags' => $metatags,
            'content' => $content,
        ];
    }

    /**
     * Send the assembled prompt to OpenRouter and return the response text.
     */
    private static function callOpenRouter(string $api_key, string $prompt): string|\WP_Error
    {
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'anthropic/claude-sonnet-4-5-20250929',
                'max_tokens' => 2048,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $err = $data['error']['message'] ?? "HTTP $code";
            return new \WP_Error('openrouter_error', $err);
        }

        if (! isset($data['choices'][0]['message']['content'])) {
            return new \WP_Error('openrouter_error', 'Unexpected response format from OpenRouter.');
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Parse the LLM's text response into a structured schema array.
     */
    private static function parseLlmResponse(string $text): ?array
    {
        // The LLM may wrap JSON in markdown code fences — strip them.
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $data = json_decode(trim($text), true);
        if (! is_array($data) || ! isset($data['type'])) {
            return null;
        }

        return [
            'type' => sanitize_text_field($data['type']),
            'properties' => is_array($data['properties'] ?? null) ? $data['properties'] : [],
        ];
    }
}
