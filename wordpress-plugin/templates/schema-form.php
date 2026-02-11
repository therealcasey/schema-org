<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="sog-app" class="sog">

    <!-- Step 1: API Key -->
    <div id="sog-apikey-section" class="sog__section">
        <h3 class="sog__title">OpenRouter API Key</h3>
        <p class="sog__description">Your key is stored securely on the server and never exposed in page source.</p>
        <div class="sog__field">
            <input
                type="password"
                id="sog-api-key"
                class="sog__input"
                placeholder="sk-or-..."
                autocomplete="off"
            />
            <button type="button" id="sog-save-key" class="sog-btn sog-btn--secondary">Save Key</button>
        </div>
        <div id="sog-key-status" class="sog-status"></div>
    </div>

    <!-- Step 2: URL Input -->
    <div id="sog-url-section" class="sog__section">
        <h3 class="sog__title">Generate Schema from URL</h3>
        <div class="sog__field">
            <input
                type="url"
                id="sog-url"
                class="sog__input"
                placeholder="https://example.com"
            />
            <button type="button" id="sog-generate" class="sog-btn sog-btn--primary">Generate Schema</button>
        </div>
        <div id="sog-generate-status" class="sog-status"></div>
    </div>

    <!-- Loading indicator -->
    <div id="sog-loading" class="sog-loading" style="display:none;">
        <div class="sog-loading__spinner"></div>
        <span class="sog-loading__text">Analyzing page content...</span>
    </div>

    <!-- Step 3: Results / Edit Form -->
    <div id="sog-results" class="sog__section" style="display:none;">
        <h3 class="sog__title">Extracted Schema</h3>

        <div class="sog__group">
            <label class="sog__label" for="sog-schema-type">Schema Type</label>
            <select id="sog-schema-type" class="sog__input">
                <option value="">-- Select --</option>
                <option value="Article">Article</option>
                <option value="BlogPosting">BlogPosting</option>
                <option value="Event">Event</option>
                <option value="FAQPage">FAQPage</option>
                <option value="LocalBusiness">LocalBusiness</option>
                <option value="Organization">Organization</option>
                <option value="Person">Person</option>
                <option value="Product">Product</option>
                <option value="Recipe">Recipe</option>
                <option value="Restaurant">Restaurant</option>
                <option value="Service">Service</option>
                <option value="WebPage">WebPage</option>
                <option value="WebSite">WebSite</option>
            </select>
        </div>

        <!-- Dynamic property fields populated by JS -->
        <div id="sog-properties" class="sog__properties"></div>

        <button type="button" id="sog-add-property" class="sog-btn sog-btn--secondary">+ Add Property</button>

        <!-- Preview -->
        <h3 class="sog__title">JSON-LD Preview</h3>
        <pre id="sog-preview" class="sog-preview"><code class="sog-preview__code"></code></pre>

        <div class="sog__actions">
            <button type="button" id="sog-copy" class="sog-btn sog-btn--secondary">Copy to Clipboard</button>
            <button type="button" id="sog-save" class="sog-btn sog-btn--primary">Save to Page</button>
        </div>
        <div id="sog-save-status" class="sog-status"></div>
    </div>
</div>
