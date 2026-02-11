<?php

namespace Sog;

if (! defined('ABSPATH')) {
    exit;
}

class Ajax
{
    private const NONCE_ACTION = 'sog_generate';
    private const NONCE_FIELD = 'nonce';
    private const META_API_KEY = 'sog_openrouter_api_key';
    private const META_SCHEMA = '_sog_schema_json_ld';
    private const MAX_CONTENT_LENGTH = 12000;

    public static function register(): void
    {
        add_action('wp_ajax_sog_save_api_key', [self::class, 'saveApiKey']);
        add_action('wp_ajax_sog_generate_schema', [self::class, 'generateSchema']);
        add_action('wp_ajax_sog_save_schema', [self::class, 'saveSchema']);
    }

    public static function saveApiKey(): never
    {
        self::verifyRequest();

        $key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        if ($key === '') {
            wp_send_json_error(['message' => 'API key is required.']);
        }

        update_user_meta(get_current_user_id(), self::META_API_KEY, $key);
        wp_send_json_success(['message' => 'API key saved.']);
    }

    public static function generateSchema(): never
    {
        self::verifyRequest();

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if ($url === '') {
            wp_send_json_error(['message' => 'A valid URL is required.']);
        }

        $api_key = get_user_meta(get_current_user_id(), self::META_API_KEY, true);
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Please save your OpenRouter API key first.']);
        }

        $html = self::fetchUrl($url);
        if (is_wp_error($html)) {
            wp_send_json_error(['message' => 'Failed to fetch URL: ' . $html->get_error_message()]);
        }

        $extracted = self::extractContent($html);

        $system_prompt = get_option('sog_system_prompt', sog_get_default_prompt());
        $system_prompt = str_replace(
            ['{{url}}', '{{metatags}}', '{{content}}'],
            [$url, $extracted['metatags'], $extracted['content']],
            $system_prompt,
        );

        $result = self::callOpenRouter($api_key, $system_prompt);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'OpenRouter error: ' . $result->get_error_message()]);
        }

        $schema = self::parseLlmResponse($result);
        if ($schema === null) {
            wp_send_json_error(['message' => 'Could not parse a valid schema from the AI response.', 'raw' => $result]);
        }

        wp_send_json_success($schema);
    }

    public static function saveSchema(): never
    {
        self::verifyRequest();

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $json_ld = isset($_POST['json_ld']) ? wp_unslash($_POST['json_ld']) : '';

        if ($post_id === 0 || $json_ld === '') {
            wp_send_json_error(['message' => 'Post ID and JSON-LD are required.']);
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'You do not have permission to edit this post.'], 403);
        }

        $decoded = json_decode($json_ld, true);
        if (! is_array($decoded)) {
            wp_send_json_error(['message' => 'Invalid JSON-LD.']);
        }

        update_post_meta($post_id, self::META_SCHEMA, $json_ld);
        wp_send_json_success(['message' => 'Schema saved to page.']);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private static function verifyRequest(): void
    {
        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in.'], 401);
        }

        check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD);
    }

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

    private static function extractContent(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Meta tags (standard + Open Graph).
        $metas = $xpath->query('//meta[@name or @property]');
        $meta_lines = [];
        foreach ($metas as $meta) {
            $key = $meta->getAttribute('name') ?: $meta->getAttribute('property');
            $val = $meta->getAttribute('content');
            if ($key !== '' && $val !== '') {
                $meta_lines[] = "$key: $val";
            }
        }

        $titles = $xpath->query('//title');
        if ($titles->length > 0) {
            array_unshift($meta_lines, 'title: ' . trim($titles->item(0)->textContent));
        }

        $metatags = implode("\n", $meta_lines);

        // Existing JSON-LD on the page.
        $json_ld_scripts = $xpath->query('//script[@type="application/ld+json"]');
        $existing_schemas = [];
        foreach ($json_ld_scripts as $script) {
            $existing_schemas[] = trim($script->textContent);
        }
        if ($existing_schemas !== []) {
            $metatags .= "\n\nExisting JSON-LD on page:\n" . implode("\n", $existing_schemas);
        }

        // Body text — strip non-content tags first.
        $remove_tags = $xpath->query('//script | //style | //noscript | //iframe');
        foreach ($remove_tags as $tag) {
            $tag->parentNode->removeChild($tag);
        }

        $content = '';
        $body = $xpath->query('//body');
        if ($body->length > 0) {
            $content = $body->item(0)->textContent;
        }

        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            $content = mb_substr($content, 0, self::MAX_CONTENT_LENGTH) . "\n\n[Content truncated]";
        }

        return [
            'metatags' => $metatags,
            'content' => $content,
        ];
    }

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

    private static function parseLlmResponse(string $text): ?array
    {
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
