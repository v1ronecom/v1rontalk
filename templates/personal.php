<?php
/** @var array $_ Template variables */
$allowBots    = $_['allow_bots'];
$ncUserId     = $_['nc_user_id'];
$wpConfigured = $_['wp_configured'];
?>
<div id="v1rontalk-personal">

    <h2><?php p($l->t('V1Ron Talk AI')); ?></h2>
    <p class="vt-intro">
        <?php p($l->t('Connect your account to the V1Ron AI backend and start chatting with AI character bots inside Nextcloud Talk.')); ?>
    </p>

    <?php if (!$wpConfigured): ?>
    <!-- ── Not configured ──────────────────────────────────────────── -->
    <div class="vt-notice vt-notice-warn">
        <strong><?php p($l->t('Not connected yet')); ?></strong><br>
        <?php p($l->t('Your administrator has not connected this Nextcloud to a V1Ron WordPress site. Ask them to fill in the settings under Administration → V1Ron Talk Bot.')); ?>
    </div>

    <?php else: ?>
    <!-- ── Connect / Sync ──────────────────────────────────────────── -->
    <div class="vt-section" id="vt-connect-section">
        <h3><?php p($l->t('1 — Connect your account')); ?></h3>
        <p class="vt-desc">
            <?php p($l->t('Click Connect to link your Nextcloud account with V1Ron. This syncs your credits and unlocks the AI character bots assigned to you.')); ?>
        </p>

        <div class="vt-connect-bar">
            <button id="vt-sync-btn" class="button primary">
                <?php p($l->t('Connect &amp; Sync')); ?>
            </button>
            <span id="vt-sync-status"></span>
        </div>

        <!-- Account info shown after a successful sync -->
        <div id="vt-account-info" class="vt-account-info" style="display:none;">
            <div class="vt-account-row">
                <span class="vt-account-icon">💎</span>
                <div>
                    <strong id="vt-balance">—</strong>
                    <span class="vt-account-label"><?php p($l->t('credits available')); ?></span>
                </div>
            </div>
            <div class="vt-account-row">
                <span class="vt-account-icon">🤖</span>
                <div>
                    <strong id="vt-char-count">—</strong>
                    <span class="vt-account-label"><?php p($l->t('AI character bots accessible')); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Character bot list ──────────────────────────────────────── -->
    <div class="vt-section" id="vt-chars-section" style="display:none;">
        <h3><?php p($l->t('2 — Your AI character bots')); ?></h3>
        <p class="vt-desc">
            <?php p($l->t('These bots are available to you in Nextcloud Talk. Open Talk, click "New conversation" and search for the character name to start chatting.')); ?>
        </p>
        <div id="vt-char-list" class="vt-char-list"></div>
    </div>

    <!-- ── How to chat ─────────────────────────────────────────────── -->
    <div class="vt-section" id="vt-howto-section" style="display:none;">
        <h3><?php p($l->t('3 — How to chat')); ?></h3>
        <div class="vt-steps">
            <div class="vt-step">
                <span class="vt-step-n">1</span>
                <div><?php p($l->t('Open Nextcloud Talk and click the pencil icon to start a new conversation.')); ?></div>
            </div>
            <div class="vt-step">
                <span class="vt-step-n">2</span>
                <div><?php p($l->t('Search for the character name (shown above). Select it to open a 1-on-1 chat.')); ?></div>
            </div>
            <div class="vt-step">
                <span class="vt-step-n">3</span>
                <div><?php p($l->t('Type your message and send. Each message deducts credits from your balance.')); ?></div>
            </div>
            <div class="vt-step">
                <span class="vt-step-n">4</span>
                <div>
                    <?php p($l->t('In group chats, @mention the bot name to trigger a reply. Credits are deducted only when you @mention a bot.')); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Bot preference ──────────────────────────────────────────── -->
    <div class="vt-section">
        <h3><?php p($l->t('Bot chat preference')); ?></h3>
        <div class="vt-toggle-row">
            <input type="checkbox"
                   id="vt-allow-bots"
                   <?php echo $allowBots ? 'checked' : ''; ?>>
            <label for="vt-allow-bots">
                <?php p($l->t('Allow AI bots to respond to my messages')); ?>
            </label>
        </div>
        <p class="vt-hint">
            <?php p($l->t('Uncheck to pause all bot responses. Your credits will not be deducted while this is off.')); ?>
        </p>
        <span id="vt-pref-status" class="vt-status-text"></span>
    </div>

    <?php endif; ?>
</div>

<style>
#v1rontalk-personal {
    padding: 0 30px 40px;
    max-width: 640px;
}
#v1rontalk-personal h2 { margin-bottom: 4px; }
.vt-intro {
    font-size: 13px;
    color: #555;
    margin: 0 0 24px;
}
.vt-section {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 1px solid #e8e8e8;
}
.vt-section:last-child { border-bottom: none; }
.vt-section h3 { margin: 0 0 8px; font-size: 14px; font-weight: 700; color: #333; }
.vt-desc { margin: 0 0 14px; font-size: 13px; color: #555; line-height: 1.5; }
.vt-notice {
    padding: 14px 18px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
    margin-bottom: 20px;
}
.vt-notice-warn { background: #fff8e1; border-left: 4px solid #f9a825; }

/* Connect bar */
.vt-connect-bar { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
#vt-sync-status { font-size: 13px; }

/* Account info */
.vt-account-info {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    padding: 14px 18px;
    background: #f0f7ff;
    border: 1px solid #c9e3f7;
    border-radius: 8px;
    margin-top: 12px;
}
.vt-account-row { display: flex; align-items: center; gap: 10px; }
.vt-account-icon { font-size: 20px; }
.vt-account-row strong { font-size: 18px; font-weight: 700; color: #0082c9; display: block; }
.vt-account-label { font-size: 11px; color: #666; }

/* Character list */
.vt-char-list { display: flex; flex-direction: column; gap: 10px; }
.vt-char-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
}
.vt-char-card.vt-assigned { border-left: 4px solid #0082c9; }
.vt-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: #e3f2fd;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 700; color: #0082c9;
}
.vt-avatar img { width: 100%; height: 100%; object-fit: cover; }
.vt-char-info { flex: 1; min-width: 0; }
.vt-char-name { font-weight: 600; font-size: 14px; margin: 0 0 2px; }
.vt-char-desc { font-size: 12px; color: #666; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vt-char-badge {
    font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 10px;
    white-space: nowrap; flex-shrink: 0;
}
.vt-badge-assigned { background: #e3f2fd; color: #0082c9; }
.vt-badge-public   { background: #f3f3f3; color: #777; }
.vt-talk-tip { font-size: 11px; color: #888; margin: 10px 0 0; }

/* How to steps */
.vt-steps { display: flex; flex-direction: column; gap: 14px; }
.vt-step { display: flex; gap: 14px; align-items: flex-start; }
.vt-step-n {
    flex-shrink: 0;
    width: 24px; height: 24px;
    border-radius: 50%;
    background: #0082c9; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; margin-top: 1px;
}
.vt-step > div { font-size: 13px; line-height: 1.6; }

/* Toggle */
.vt-toggle-row { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.vt-toggle-row label { font-size: 14px; cursor: pointer; }
.vt-hint { font-size: 12px; color: #888; margin: 0 0 6px; }
.vt-status-text { font-size: 12px; }
</style>

<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
(function () {
    'use strict';

    var syncBtn    = document.getElementById('vt-sync-btn');
    var syncStatus = document.getElementById('vt-sync-status');
    var acctInfo   = document.getElementById('vt-account-info');
    var charsSection = document.getElementById('vt-chars-section');
    var howtoSection = document.getElementById('vt-howto-section');
    var charList   = document.getElementById('vt-char-list');
    var allowToggle = document.getElementById('vt-allow-bots');
    var prefStatus = document.getElementById('vt-pref-status');

    var syncUrl     = OC.generateUrl('/apps/v1rontalk/api/user/sync');
    var settingsUrl = OC.generateUrl('/apps/v1rontalk/api/user/settings');

    function post(url, body, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            try { cb(null, JSON.parse(xhr.responseText)); }
            catch (e) { cb(new Error('Server returned an unexpected response')); }
        };
        xhr.onerror = function () { cb(new Error('Network error')); };
        xhr.send(JSON.stringify(body));
    }

    function initials(name) {
        return (name || '?').trim().charAt(0).toUpperCase();
    }

    function truncate(s, n) {
        return s && s.length > n ? s.slice(0, n) + '…' : (s || '');
    }

    function renderChars(characters) {
        if (!charList) return;
        if (!characters || characters.length === 0) {
            charList.innerHTML = '<p style="font-size:13px;color:#888;">No characters found. Ask your administrator to sync characters from WordPress.</p>';
            return;
        }

        var html = '';
        characters.forEach(function (c) {
            var isAssigned = c.is_assigned || c.affection > 0;
            html += '<div class="vt-char-card' + (isAssigned ? ' vt-assigned' : '') + '">';
            html += '<div class="vt-avatar">';
            if (c.avatar) {
                html += '<img src="' + c.avatar + '" alt="' + c.name + '">';
            } else {
                html += initials(c.name);
            }
            html += '</div>';
            html += '<div class="vt-char-info">';
            html += '<p class="vt-char-name">' + c.name + '</p>';
            html += '<p class="vt-char-desc">' + truncate(c.description, 90) + '</p>';
            html += '</div>';
            html += '<span class="vt-char-badge ' + (isAssigned ? 'vt-badge-assigned' : 'vt-badge-public') + '">';
            html += isAssigned ? 'Assigned to you' : 'Public';
            html += '</span>';
            html += '</div>';
        });

        html += '<p class="vt-talk-tip">Open Nextcloud Talk → New conversation → search the character name above to start chatting.</p>';
        charList.innerHTML = html;
    }

    // ── Connect & Sync ───────────────────────────────────────────────
    if (syncBtn) {
        syncBtn.addEventListener('click', function () {
            syncBtn.disabled = true;
            syncStatus.style.color = '';
            syncStatus.textContent = 'Connecting…';

            post(syncUrl, {}, function (err, res) {
                syncBtn.disabled = false;

                if (err || !res.success) {
                    syncStatus.textContent = '✗ ' + (err ? err.message : (res.error || 'Connection failed'));
                    syncStatus.style.color = '#e74c3c';
                    return;
                }

                syncStatus.textContent = '✓ Connected';
                syncStatus.style.color = '#27ae60';
                syncBtn.textContent = '↻ Re-sync';

                // Show account info
                var balanceEl = document.getElementById('vt-balance');
                var countEl   = document.getElementById('vt-char-count');
                if (balanceEl) balanceEl.textContent = res.balance || '0';
                if (countEl)   countEl.textContent   = (res.characters || []).length;
                if (acctInfo)  acctInfo.style.display = '';

                // Render character list and reveal sections
                renderChars(res.characters || []);
                if (charsSection)  charsSection.style.display  = '';
                if (howtoSection)  howtoSection.style.display  = '';
            });
        });
    }

    // ── Allow bots toggle ────────────────────────────────────────────
    if (allowToggle) {
        allowToggle.addEventListener('change', function () {
            prefStatus.textContent = 'Saving…';
            prefStatus.style.color = '';

            post(settingsUrl, { allow_bots: allowToggle.checked }, function (err, res) {
                if (err || !res.success) {
                    allowToggle.checked = !allowToggle.checked;
                    prefStatus.textContent = '✗ Could not save preference';
                    prefStatus.style.color = '#e74c3c';
                } else {
                    prefStatus.textContent = allowToggle.checked
                        ? '✓ Bots will respond to your messages'
                        : '✓ Bots are paused';
                    prefStatus.style.color = '#27ae60';
                }
                setTimeout(function () { prefStatus.textContent = ''; }, 3000);
            });
        });
    }
}());
</script>
