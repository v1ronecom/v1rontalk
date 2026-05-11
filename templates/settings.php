<div id="v1rontalk-settings" class="section">
    <h2>
        <img class="svg" src="<?php p(image_path('v1rontalk', 'app.svg')); ?>" alt="V1Ron">
        V1Ron Talk Bot — Admin Settings
    </h2>

    <p class="settings-hint">
        Connect Nextcloud to your WordPress site running the V1Ron Digital Human Manager plugin.
        AI characters are automatically registered as Talk bots your users can chat with.
    </p>

    <!-- ── Connection Form ──────────────────────────────────────────── -->
    <form id="v1rontalk-settings-form">
        <div class="v1ron-setting-row">
            <label for="v1ron-wp-url">WordPress Site URL</label>
            <input type="url"
                   id="v1ron-wp-url"
                   name="wordpress_url"
                   value="<?php p($_['wordpress_url']); ?>"
                   placeholder="https://your-wordpress-site.com"
                   required>
            <p class="v1ron-setting-hint">
                Base URL of your WordPress site where V1RonDHM is installed.
                Must be reachable from this Nextcloud server.
            </p>
        </div>

        <div class="v1ron-setting-row">
            <label for="v1ron-api-key">V1Ron API Key</label>
            <input type="password"
                   id="v1ron-api-key"
                   name="api_key"
                   placeholder="<?php p($_['has_api_key'] ? '•••••••• (leave blank to keep current)' : 'Paste API key from WordPress'); ?>">
            <p class="v1ron-setting-hint">
                Found in WordPress → V1Ron → Settings → Nextcloud Bridge tab.
                Generate a new key there if you haven't already.
            </p>
        </div>

        <div class="v1ron-setting-row">
            <label for="v1ron-bot-user">Talk Bot System User</label>
            <input type="text"
                   id="v1ron-bot-user"
                   name="bot_system_user"
                   value="<?php p($_['bot_system_user']); ?>"
                   placeholder="v1ron_bot">
            <p class="v1ron-setting-hint">
                Nextcloud username the bots post as inside Talk conversations.
                Create a dedicated account (e.g. <code>v1ron_bot</code>) and enter it here.
            </p>
        </div>

        <div class="v1ron-setting-row">
            <label>
                <input type="checkbox"
                       name="auto_register"
                       value="1"
                       <?php p($_['auto_register'] ? 'checked' : ''); ?>>
                Auto-register characters as Talk bots on save
            </label>
            <p class="v1ron-setting-hint">
                When checked, saving these settings immediately fetches all public characters
                from WordPress and registers them as Talk bots. Uncheck only if you manage
                bot registrations manually.
            </p>
        </div>

        <div class="v1ron-setting-row">
            <button type="submit" class="button primary" id="v1ron-save-btn">
                Save &amp; Sync Characters
            </button>
            <span id="v1ron-save-msg" style="margin-left:10px;"></span>
        </div>
    </form>

    <hr style="margin:30px 0;">

    <!-- ── Character Sync ────────────────────────────────────────────── -->
    <h3>Character Bot Sync</h3>
    <p style="margin-bottom:12px; color:#555; font-size:13px;">
        Use this whenever you add or rename characters in WordPress and want them
        to appear immediately in Talk without restarting the app.
    </p>

    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <button class="button" id="v1ron-sync-bots-btn">↻ Sync Characters Now</button>
        <button class="button" id="v1ron-check-bots-btn">Check Connection</button>
        <span id="v1ron-sync-bots-msg" style="font-size:13px;"></span>
    </div>

    <div id="v1ron-bot-list" style="margin-top:16px;"></div>

    <hr style="margin:30px 0;">

    <!-- ── How to Use ────────────────────────────────────────────────── -->
    <h3>How to Use</h3>

    <div class="v1ron-howto">
        <div class="v1ron-howto-step">
            <span class="v1ron-step-num">1</span>
            <div>
                <strong>Configure the connection above</strong><br>
                Enter your WordPress URL and API key, then click
                <em>Save &amp; Sync Characters</em>. All public characters are fetched
                from WordPress and registered as Talk bots automatically.
            </div>
        </div>

        <div class="v1ron-howto-step">
            <span class="v1ron-step-num">2</span>
            <div>
                <strong>Sync after adding new characters</strong><br>
                When you publish a new character in WordPress, click
                <em>↻ Sync Characters Now</em> on this page — or ask your admin to do so.
                The character immediately appears as a new bot in Talk.
            </div>
        </div>

        <div class="v1ron-howto-step">
            <span class="v1ron-step-num">3</span>
            <div>
                <strong>Users connect in their Personal Settings</strong><br>
                Each Nextcloud user goes to their <strong>Personal Settings → V1Ron Talk</strong>
                tab and clicks <em>Connect &amp; Sync</em>. This links their Nextcloud account
                to their V1Ron credits and assigned characters.
            </div>
        </div>

        <div class="v1ron-howto-step">
            <span class="v1ron-step-num">4</span>
            <div>
                <strong>Start a Talk conversation with a character bot</strong><br>
                In Nextcloud Talk, open <em>New conversation</em> and search for the
                character's name. Select it to start a 1-on-1 chat, or add the bot to
                a group conversation and <strong>@mention</strong> it to chat.
            </div>
        </div>

        <div class="v1ron-howto-step">
            <span class="v1ron-step-num">5</span>
            <div>
                <strong>File commands inside chat</strong><br>
                Characters can read and write your Nextcloud files. Just say:<br>
                <code>Read file `Documents/notes.txt`</code> &nbsp;or&nbsp;
                <code>Save to `output.txt`</code>
            </div>
        </div>

        <div class="v1ron-howto-step">
            <span class="v1ron-step-num">6</span>
            <div>
                <strong>Group chat &amp; credits</strong><br>
                When a user @mentions a bot in a group conversation, credits are deducted
                from their V1Ron balance. Set the group chat credit multiplier in
                WordPress → V1Ron → Settings → Nextcloud Bridge.
            </div>
        </div>
    </div>
</div>

<style>
    #v1rontalk-settings .v1ron-setting-row { margin: 20px 0; }
    #v1rontalk-settings .v1ron-setting-row label { display:block; font-weight:600; margin-bottom:5px; }
    #v1rontalk-settings .v1ron-setting-row input[type="url"],
    #v1rontalk-settings .v1ron-setting-row input[type="password"],
    #v1rontalk-settings .v1ron-setting-row input[type="text"] { width:100%; max-width:500px; }
    #v1rontalk-settings .v1ron-setting-hint { margin:3px 0 0; opacity:.7; font-size:.9em; }
    #v1rontalk-settings code { background:#f0f0f0; padding:2px 6px; border-radius:3px; font-size:.9em; }

    .v1ron-howto { display:flex; flex-direction:column; gap:18px; max-width:680px; }
    .v1ron-howto-step { display:flex; gap:16px; align-items:flex-start; }
    .v1ron-step-num {
        flex-shrink:0;
        width:28px; height:28px;
        border-radius:50%;
        background:#0082c9; color:#fff;
        display:flex; align-items:center; justify-content:center;
        font-weight:700; font-size:13px;
    }
    .v1ron-howto-step > div { font-size:13px; line-height:1.6; padding-top:4px; }
    .v1ron-howto-step strong { display:block; margin-bottom:2px; font-size:14px; }
</style>

<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
document.addEventListener('DOMContentLoaded', function () {
    var saveBtn     = document.getElementById('v1ron-save-btn');
    var saveMsg     = document.getElementById('v1ron-save-msg');
    var syncBtn     = document.getElementById('v1ron-sync-bots-btn');
    var syncMsg     = document.getElementById('v1ron-sync-bots-msg');
    var checkBtn    = document.getElementById('v1ron-check-bots-btn');
    var botList     = document.getElementById('v1ron-bot-list');

    var saveUrl  = '<?php p(\OC::$server->get(\OCP\IURLGenerator::class)->linkToRoute('v1rontalk.settings.save')); ?>';
    var loadUrl  = '<?php p(\OC::$server->get(\OCP\IURLGenerator::class)->linkToRoute('v1rontalk.settings.load')); ?>';
    // Use direct app URL to avoid camelCase ambiguity in linkToRoute for underscore method names
    var syncUrl  = OC.generateUrl('/apps/v1rontalk/api/settings/sync-bots');

    function req(url, method, body, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            try { cb(null, JSON.parse(xhr.responseText)); }
            catch (e) { cb(new Error('Invalid JSON response')); }
        };
        xhr.onerror = function () { cb(new Error('Network error')); };
        xhr.send(body ? JSON.stringify(body) : null);
    }

    // ── Save form ────────────────────────────────────────────────────
    document.getElementById('v1rontalk-settings-form').addEventListener('submit', function (e) {
        e.preventDefault();
        saveBtn.disabled = true;
        saveMsg.style.color = '';
        saveMsg.textContent = 'Saving…';

        var fd = new FormData(e.target);
        var data = {};
        fd.forEach(function (v, k) { data[k] = v; });
        data.auto_register = fd.get('auto_register') === '1';

        req(saveUrl, 'POST', data, function (err, res) {
            saveBtn.disabled = false;
            if (err || !res.success) {
                saveMsg.textContent = '✗ ' + (err ? err.message : (res.error || 'Unknown error'));
                saveMsg.style.color = '#e74c3c';
                return;
            }
            var n = res.bots_registered;
            saveMsg.textContent = '✓ Saved. ' + (typeof n === 'number'
                ? n + ' character bot' + (n !== 1 ? 's' : '') + ' registered in Talk.'
                : 'Bots will register on next app boot.');
            saveMsg.style.color = '#27ae60';
        });
    });

    // ── Sync bots ───────────────────────────────────────────────────
    syncBtn.addEventListener('click', function () {
        syncBtn.disabled = true;
        syncMsg.style.color = '';
        syncMsg.textContent = 'Syncing characters…';
        botList.innerHTML = '';

        req(syncUrl, 'POST', {}, function (err, res) {
            syncBtn.disabled = false;
            if (err || !res.success) {
                syncMsg.textContent = '✗ ' + (err ? err.message : (res.error || 'Sync failed'));
                syncMsg.style.color = '#e74c3c';
                return;
            }
            var n = res.bots_registered || 0;
            syncMsg.textContent = '✓ ' + n + ' character bot' + (n !== 1 ? 's' : '') + ' registered.';
            syncMsg.style.color = '#27ae60';
            botList.innerHTML = n > 0
                ? '<p style="font-size:13px;color:#555;">Users can now find these bots in Talk by searching the character name. Tell users to go to <strong>Personal Settings → V1Ron Talk</strong> to connect their account.</p>'
                : '<p style="font-size:13px;color:#888;">No public characters found on the WordPress site. Make sure at least one character is published and set to public.</p>';
        });
    });

    // ── Check connection ─────────────────────────────────────────────
    checkBtn.addEventListener('click', function () {
        checkBtn.disabled = true;
        botList.innerHTML = '<p style="font-size:13px;">Checking…</p>';

        req(loadUrl, 'GET', null, function (err, res) {
            checkBtn.disabled = false;
            var d = (res && res.ocs) ? res.ocs.data : res;
            if (err || !d || !d.success) {
                botList.innerHTML = '<p style="color:#e74c3c;font-size:13px;">✗ ' + (err ? err.message : (d && d.error) || 'Could not load settings') + '</p>';
                return;
            }
            var html = '';
            html += d.wordpress_url
                ? '<p style="font-size:13px;">✓ WordPress: <code>' + d.wordpress_url + '</code></p>'
                : '<p style="color:#e74c3c;font-size:13px;">✗ No WordPress URL configured</p>';
            html += d.has_api_key
                ? '<p style="font-size:13px;">✓ API key is set</p>'
                : '<p style="color:#e74c3c;font-size:13px;">✗ No API key — bots cannot connect</p>';
            html += d.bot_system_user
                ? '<p style="font-size:13px;">✓ Bot system user: <code>' + d.bot_system_user + '</code></p>'
                : '<p style="color:#e67e22;font-size:13px;">⚠ No bot system user — set one above</p>';
            botList.innerHTML = html;
        });
    });
});
</script>
