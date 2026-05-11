<div id="v1rontalk-settings" class="section">
    <h2>
        <img class="svg" src="<?php p(image_path('v1rontalk', 'app.svg')); ?>" alt="V1Ron">
        V1Ron Talk Bot — Settings
    </h2>

    <p class="settings-hint">
        Connect your Nextcloud to a WordPress instance running the V1Ron Digital Human Manager plugin.
        Characters will appear as bots in Nextcloud Talk.
    </p>

    <form id="v1rontalk-settings-form">
        <!-- WordPress URL -->
        <div class="v1ron-setting-row">
            <label for="v1ron-wp-url">WordPress Site URL</label>
            <input type="url"
                   id="v1ron-wp-url"
                   name="wordpress_url"
                   value="<?php p($_['wordpress_url']); ?>"
                   placeholder="https://your-wordpress-site.com"
                   required>
            <p class="v1ron-setting-hint">
                The base URL of your WordPress site where V1RonDHM is installed.
                Must be publicly accessible from this Nextcloud server.
            </p>
        </div>

        <!-- API Key -->
        <div class="v1ron-setting-row">
            <label for="v1ron-api-key">V1Ron API Key</label>
            <input type="password"
                   id="v1ron-api-key"
                   name="api_key"
                   placeholder="<?php p($_['has_api_key'] ? '•••••••• (leave blank to keep)' : 'Enter API key'); ?>">
            <p class="v1ron-setting-hint">
                API key from WordPress settings (V1Ron → Settings → Nextcloud Bridge).
                Generate one on your WordPress site.
            </p>
        </div>

        <!-- Bot System User -->
        <div class="v1ron-setting-row">
            <label for="v1ron-bot-user">Talk Bot System User</label>
            <input type="text"
                   id="v1ron-bot-user"
                   name="bot_system_user"
                   value="<?php p($_['bot_system_user']); ?>"
                   placeholder="v1ron_bot">
            <p class="v1ron-setting-hint">
                Nextcloud user ID that the bot will use to send messages in Talk.
                Create a dedicated user (e.g. "v1ron_bot") for this purpose.
            </p>
        </div>

        <!-- Auto Register -->
        <div class="v1ron-setting-row">
            <label>
                <input type="checkbox"
                       name="auto_register"
                       value="1"
                       <?php p($_['auto_register'] ? 'checked' : ''); ?>>
                Auto-register characters as Talk bots
            </label>
            <p class="v1ron-setting-hint">
                Automatically fetch characters from WordPress and register them as bots in Talk.
                Disable if you want to manually manage bot registrations.
            </p>
        </div>

        <div class="v1ron-setting-row">
            <button type="submit" class="button primary" id="v1ron-save-btn">
                Save Settings
            </button>
            <span id="v1ron-save-msg" style="margin-left: 10px;"></span>
        </div>
    </form>

    <hr style="margin: 30px 0;">

    <h3>Talk Bot Status</h3>
    <div id="v1ron-bot-status">
        <p>Click "Check Status" to see registered bot status.</p>
        <button class="button" id="v1ron-check-bots-btn">Check Bot Status</button>
        <div id="v1ron-bot-list" style="margin-top: 15px;"></div>
    </div>

    <hr style="margin: 30px 0;">

    <h3>How to Use</h3>
    <ol style="line-height: 1.8;">
        <li><strong>Configure</strong> the WordPress URL and API key above</li>
        <li><strong>Save Settings</strong> — bots will auto-register on next page load</li>
        <li><strong>Open Nextcloud Talk</strong> and search for your characters in the contact list</li>
        <li><strong>Start chatting!</strong> Characters can read, search, and save files in your Nextcloud when you ask them</li>
        <li><strong>File commands:</strong> Say <code>Read file `Documents/notes.txt`</code> or <code>Save to `output.txt`</code></li>
    </ol>
</div>

<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('v1rontalk-settings-form');
        const saveBtn = document.getElementById('v1ron-save-btn');
        const saveMsg = document.getElementById('v1ron-save-msg');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            saveBtn.disabled = true;
            saveMsg.textContent = 'Saving...';

            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => { data[key] = value; });
            data.auto_register = formData.get('auto_register') === '1';

            console.log('[V1RonTalk] Saving settings:', data);
            console.log('[V1RonTalk] requestToken:', OC?.requestToken);

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'requesttoken': OC?.requestToken ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                };
                const resp = await fetch('<?php p(\OC::$server->get(\OCP\IURLGenerator::class)->linkToRoute('v1rontalk.settings.save')); ?>', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(data),
                });
                console.log('[V1RonTalk] Save response status:', resp.status);
                const text = await resp.text();
                console.log('[V1RonTalk] Save response body:', text);
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseErr) {
                    console.error('[V1RonTalk] Failed to parse response as JSON:', text);
                    saveMsg.textContent = '✗ Server returned non-JSON response. Check console.';
                    saveMsg.style.color = 'red';
                    return;
                }
                if (result.success) {
                    saveMsg.textContent = '✓ Saved successfully! Bots will register on next app boot.';
                    saveMsg.style.color = 'green';
                } else {
                    const errMsg = result.error || result.ocs?.data?.error || 'Unknown';
                    console.error('[V1RonTalk] Save error:', errMsg);
                    saveMsg.textContent = '✗ Error: ' + errMsg;
                    saveMsg.style.color = 'red';
                }
            } catch (err) {
                console.error('[V1RonTalk] Network error:', err);
                saveMsg.textContent = '✗ Network error: ' + err.message;
                saveMsg.style.color = 'red';
            } finally {
                saveBtn.disabled = false;
            }
        });

        // Bot status check
        document.getElementById('v1ron-check-bots-btn').addEventListener('click', async function () {
            const list = document.getElementById('v1ron-bot-list');
            list.innerHTML = '<p>Checking...</p>';

            try {
                const resp = await fetch('<?php p(\OC::$server->get(\OCP\IURLGenerator::class)->linkToRoute('v1rontalk.settings.load')); ?>', {
                    headers: {
                        'requesttoken': OC?.requestToken ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                console.log('[V1RonTalk] Load settings response status:', resp.status);
                const text = await resp.text();
                console.log('[V1RonTalk] Load settings response body:', text);
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseErr) {
                    console.error('[V1RonTalk] Failed to parse settings response:', text);
                    list.innerHTML = '<p>✗ Server returned non-JSON response. Check console.</p>';
                    return;
                }
                // Support both direct JSON and OCS envelope formats
                const data = result.ocs?.data ?? result;
                if (data.success) {
                    list.innerHTML = '<p>✓ Connected to WordPress URL: <code>' + (data.wordpress_url || '(empty)') + '</code></p>';
                    if (data.has_api_key) {
                        list.innerHTML += '<p>✓ API key is configured</p>';
                    } else {
                        list.innerHTML += '<p>✗ No API key configured</p>';
                    }
                } else {
                    const errMsg = data.error || 'Unknown';
                    console.error('[V1RonTalk] Settings load error:', errMsg);
                    list.innerHTML = '<p>✗ Error: ' + errMsg + '</p>';
                }
            } catch (err) {
                console.error('[V1RonTalk] Settings load network error:', err);
                list.innerHTML = '<p>✗ Network error: ' + err.message + '</p>';
            }
        });
    });
</script>

<style>
    #v1rontalk-settings .v1ron-setting-row {
        margin: 20px 0;
    }
    #v1rontalk-settings .v1ron-setting-row label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
    }
    #v1rontalk-settings .v1ron-setting-row input[type="url"],
    #v1rontalk-settings .v1ron-setting-row input[type="password"],
    #v1rontalk-settings .v1ron-setting-row input[type="text"] {
        width: 100%;
        max-width: 500px;
    }
    #v1rontalk-settings .v1ron-setting-hint {
        margin: 3px 0 0;
        opacity: 0.7;
        font-size: 0.9em;
    }
    #v1rontalk-settings code {
        background: #f0f0f0;
        padding: 2px 6px;
        border-radius: 3px;
    }
</style>
