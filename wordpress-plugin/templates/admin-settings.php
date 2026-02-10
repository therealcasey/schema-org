<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Schema Generator Settings</h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php settings_fields('sog_settings'); ?>

        <h2>System Prompt</h2>
        <p class="description">
            This prompt is sent to the LLM when generating schema from a URL.
            You can customize it to change extraction behavior.
        </p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sog_system_prompt">Prompt</label>
                </th>
                <td>
                    <textarea
                        id="sog_system_prompt"
                        name="sog_system_prompt"
                        rows="20"
                        cols="100"
                        class="large-text code"
                    ><?php echo esc_textarea(get_option('sog_system_prompt', sog_get_default_prompt())); ?></textarea>
                </td>
            </tr>
        </table>

        <h3>Available Placeholders</h3>
        <table class="widefat fixed striped" style="max-width: 600px;">
            <thead>
                <tr>
                    <th>Placeholder</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>{{content}}</code></td>
                    <td>The extracted text content of the page (required)</td>
                </tr>
                <tr>
                    <td><code>{{url}}</code></td>
                    <td>The target URL being parsed</td>
                </tr>
                <tr>
                    <td><code>{{metatags}}</code></td>
                    <td>Extracted meta and Open Graph tags</td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top: 1em;">
            <button
                type="button"
                id="sog-restore-default"
                class="button button-secondary"
            >
                Restore Default Prompt
            </button>
        </p>

        <?php submit_button('Save Prompt'); ?>
    </form>

    <script>
    document.getElementById('sog-restore-default').addEventListener('click', function() {
        if (confirm('Restore the prompt to the default? Your current prompt will be lost.')) {
            document.getElementById('sog_system_prompt').value = <?php echo wp_json_encode(sog_get_default_prompt()); ?>;
        }
    });
    </script>
</div>
