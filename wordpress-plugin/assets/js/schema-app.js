(function () {
    'use strict';

    // ── Helpers ────────────────────────────────────────────────────────────

    function $(selector) {
        return document.querySelector(selector);
    }

    function show(el) {
        if (typeof el === 'string') el = $(el);
        if (el) el.style.display = '';
    }

    function hide(el) {
        if (typeof el === 'string') el = $(el);
        if (el) el.style.display = 'none';
    }

    function setStatus(selector, message, isError) {
        var el = $(selector);
        if (!el) return;
        el.textContent = message;
        el.className = 'sog-status' + (isError ? ' sog-status--error' : ' sog-status--success');
    }

    function clearStatus(selector) {
        var el = $(selector);
        if (!el) return;
        el.textContent = '';
        el.className = 'sog-status';
    }

    function ajaxPost(action, data) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', sogConfig.nonce);
        for (var key in data) {
            if (Object.prototype.hasOwnProperty.call(data, key)) {
                formData.append(key, data[key]);
            }
        }
        return fetch(sogConfig.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        }).then(function (res) { return res.json(); });
    }

    // ── JSON-LD Builder ───────────────────────────────────────────────────

    function buildJsonLd() {
        var type = $('#sog-schema-type').value;
        if (!type) return null;

        var props = collectPropertiesFromForm();
        var schema = {
            '@context': 'https://schema.org',
            '@type': type,
        };

        for (var key in props) {
            if (Object.prototype.hasOwnProperty.call(props, key) && props[key] !== '') {
                try {
                    var parsed = JSON.parse(props[key]);
                    if (typeof parsed === 'object') {
                        schema[key] = parsed;
                        continue;
                    }
                } catch (e) {
                    // Not JSON, use as string.
                }
                schema[key] = props[key];
            }
        }

        return schema;
    }

    function updatePreview() {
        var schema = buildJsonLd();
        var previewEl = $('.sog-preview__code');
        if (!schema || !previewEl) {
            if (previewEl) previewEl.textContent = '';
            return;
        }
        previewEl.textContent = JSON.stringify(schema, null, 2);
    }

    // ── Property Form Management ──────────────────────────────────────────

    function collectPropertiesFromForm() {
        var props = {};
        var rows = document.querySelectorAll('.sog-prop');
        for (var i = 0; i < rows.length; i++) {
            var keyInput = rows[i].querySelector('.sog-prop__key');
            var valInput = rows[i].querySelector('.sog-prop__value');
            if (keyInput && valInput && keyInput.value.trim()) {
                props[keyInput.value.trim()] = valInput.value;
            }
        }
        return props;
    }

    function renderProperties(properties) {
        var container = $('#sog-properties');
        if (!container) return;
        container.innerHTML = '';

        for (var key in properties) {
            if (Object.prototype.hasOwnProperty.call(properties, key)) {
                var val = properties[key];
                if (typeof val === 'object' && val !== null) {
                    val = JSON.stringify(val, null, 2);
                }
                addPropertyRow(container, key, val);
            }
        }
    }

    function addPropertyRow(container, key, value) {
        var row = document.createElement('div');
        row.className = 'sog-prop';

        var keyInput = document.createElement('input');
        keyInput.type = 'text';
        keyInput.className = 'sog__input sog-prop__key';
        keyInput.placeholder = 'Property name';
        keyInput.value = key || '';

        var valInput = document.createElement('textarea');
        valInput.className = 'sog__input sog-prop__value';
        valInput.placeholder = 'Value';
        valInput.value = value || '';
        valInput.rows = (typeof value === 'string' && value.indexOf('\n') !== -1) ? 4 : 1;

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'sog-btn sog-btn--danger sog-btn--sm';
        removeBtn.textContent = 'Remove';
        removeBtn.addEventListener('click', function () {
            row.remove();
            updatePreview();
        });

        keyInput.addEventListener('input', updatePreview);
        valInput.addEventListener('input', updatePreview);

        row.appendChild(keyInput);
        row.appendChild(valInput);
        row.appendChild(removeBtn);
        container.appendChild(row);
    }

    // ── Event Handlers ────────────────────────────────────────────────────

    function onSaveKey() {
        var key = $('#sog-api-key').value.trim();
        if (!key) {
            setStatus('#sog-key-status', 'Please enter an API key.', true);
            return;
        }
        clearStatus('#sog-key-status');
        ajaxPost('sog_save_api_key', { api_key: key }).then(function (resp) {
            if (resp.success) {
                setStatus('#sog-key-status', 'Key saved.', false);
                sogConfig.hasApiKey = true;
            } else {
                setStatus('#sog-key-status', resp.data.message, true);
            }
        }).catch(function () {
            setStatus('#sog-key-status', 'Network error. Please try again.', true);
        });
    }

    function onGenerate() {
        var url = $('#sog-url').value.trim();
        if (!url) {
            setStatus('#sog-generate-status', 'Please enter a URL.', true);
            return;
        }
        if (!sogConfig.hasApiKey) {
            setStatus('#sog-generate-status', 'Please save your OpenRouter API key first.', true);
            return;
        }

        clearStatus('#sog-generate-status');
        hide('#sog-results');
        show('#sog-loading');

        ajaxPost('sog_generate_schema', { url: url }).then(function (resp) {
            hide('#sog-loading');
            if (resp.success) {
                var data = resp.data;
                var typeSelect = $('#sog-schema-type');
                if (typeSelect) {
                    var found = false;
                    for (var i = 0; i < typeSelect.options.length; i++) {
                        if (typeSelect.options[i].value === data.type) {
                            found = true;
                            break;
                        }
                    }
                    if (!found && data.type) {
                        var opt = document.createElement('option');
                        opt.value = data.type;
                        opt.textContent = data.type;
                        typeSelect.appendChild(opt);
                    }
                    typeSelect.value = data.type;
                }

                renderProperties(data.properties);
                updatePreview();
                show('#sog-results');
            } else {
                setStatus('#sog-generate-status', resp.data.message, true);
            }
        }).catch(function () {
            hide('#sog-loading');
            setStatus('#sog-generate-status', 'Network error. Please try again.', true);
        });
    }

    function onCopy() {
        var schema = buildJsonLd();
        if (!schema) return;
        var text = '<script type="application/ld+json">\n' + JSON.stringify(schema, null, 2) + '\n<\/script>';
        navigator.clipboard.writeText(text).then(function () {
            setStatus('#sog-save-status', 'Copied to clipboard.', false);
        });
    }

    function onSave() {
        var schema = buildJsonLd();
        if (!schema) {
            setStatus('#sog-save-status', 'No schema to save.', true);
            return;
        }
        var jsonLd = JSON.stringify(schema);

        var postId = 0;
        var match = document.body.className.match(/(?:^|\s)postid-(\d+)/);
        if (match) {
            postId = match[1];
        }
        if (!postId) {
            match = document.body.className.match(/(?:^|\s)page-id-(\d+)/);
            if (match) postId = match[1];
        }

        if (!postId) {
            setStatus('#sog-save-status', 'Could not determine the current page ID. Copy the JSON-LD manually instead.', true);
            return;
        }

        clearStatus('#sog-save-status');
        ajaxPost('sog_save_schema', { post_id: postId, json_ld: jsonLd }).then(function (resp) {
            if (resp.success) {
                setStatus('#sog-save-status', 'Schema saved. It will appear in the page\'s <head>.', false);
            } else {
                setStatus('#sog-save-status', resp.data.message, true);
            }
        }).catch(function () {
            setStatus('#sog-save-status', 'Network error. Please try again.', true);
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────

    function init() {
        var saveKeyBtn = $('#sog-save-key');
        var generateBtn = $('#sog-generate');
        var addPropBtn = $('#sog-add-property');
        var copyBtn = $('#sog-copy');
        var saveBtn = $('#sog-save');
        var typeSelect = $('#sog-schema-type');

        if (saveKeyBtn) saveKeyBtn.addEventListener('click', onSaveKey);
        if (generateBtn) generateBtn.addEventListener('click', onGenerate);
        if (copyBtn) copyBtn.addEventListener('click', onCopy);
        if (saveBtn) saveBtn.addEventListener('click', onSave);
        if (typeSelect) typeSelect.addEventListener('change', updatePreview);

        if (addPropBtn) {
            addPropBtn.addEventListener('click', function () {
                addPropertyRow($('#sog-properties'), '', '');
            });
        }

        var urlInput = $('#sog-url');
        if (urlInput) {
            urlInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    onGenerate();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
