<?php
/** @var array $_ Template variables */
$allowBots    = $_['allow_bots'];
$ncUserId     = $_['nc_user_id'];
$wpConfigured = $_['wp_configured'];
?>
<div id="v1rontalk-personal-settings">
    <h2><?php p($l->t('V1Ron Talk')); ?></h2>

    <?php if (!$wpConfigured): ?>
        <p class="v1rontalk-notice">
            <?php p($l->t('V1Ron Talk is not yet connected to a WordPress site. Ask your administrator to configure the connection.')); ?>
        </p>
    <?php else: ?>

    <div class="v1rontalk-section">
        <h3><?php p($l->t('Bot Chat Settings')); ?></h3>
        <p class="v1rontalk-desc">
            <?php p($l->t('Allow V1Ron AI characters to chat with you in Nextcloud Talk conversations.')); ?>
        </p>

        <div class="v1rontalk-row">
            <label class="v1rontalk-label" for="v1rontalk-allow-bots">
                <?php p($l->t('Allow bots to chat')); ?>
            </label>
            <input type="checkbox"
                   id="v1rontalk-allow-bots"
                   class="v1rontalk-toggle"
                   <?php echo $allowBots ? 'checked' : ''; ?>
                   data-nc-user-id="<?php p($ncUserId); ?>"
            >
        </div>
        <p class="v1rontalk-hint">
            <?php p($l->t('When enabled, AI character bots can respond to your messages in Talk. Credits are deducted per message.')); ?>
        </p>
    </div>

    <div class="v1rontalk-section">
        <h3><?php p($l->t('Account Sync')); ?></h3>
        <p class="v1rontalk-desc">
            <?php p($l->t('Sync your Nextcloud account with the V1Ron WordPress backend to access your assigned AI characters and credits.')); ?>
        </p>
        <button id="v1rontalk-sync-btn" class="button">
            <?php p($l->t('Sync Account')); ?>
        </button>
        <span id="v1rontalk-sync-status" class="v1rontalk-status"></span>

        <div id="v1rontalk-account-info" style="display:none; margin-top:12px;">
            <p><strong><?php p($l->t('Credits:')); ?></strong> <span id="v1rontalk-balance"></span></p>
            <p><strong><?php p($l->t('Assigned Characters:')); ?></strong> <span id="v1rontalk-char-count"></span></p>
        </div>
    </div>

    <?php endif; ?>
</div>

<style>
#v1rontalk-personal-settings {
    padding: 0 30px 30px;
    max-width: 600px;
}
.v1rontalk-section {
    margin-bottom: 28px;
    padding-bottom: 28px;
    border-bottom: 1px solid #e8e8e8;
}
.v1rontalk-section:last-child { border-bottom: none; }
.v1rontalk-section h3 { margin: 0 0 8px; font-size: 14px; font-weight: 600; }
.v1rontalk-desc { margin: 0 0 12px; font-size: 13px; color: #555; }
.v1rontalk-hint { margin-top: 8px; font-size: 12px; color: #888; }
.v1rontalk-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 4px;
}
.v1rontalk-label { font-size: 14px; }
.v1rontalk-notice { padding: 10px 14px; background: #fff3cd; border-radius: 6px; font-size: 13px; }
.v1rontalk-status { margin-left: 12px; font-size: 13px; color: #27ae60; }
</style>

<script>
(function () {
    'use strict';

    var toggle = document.getElementById('v1rontalk-allow-bots');
    var syncBtn = document.getElementById('v1rontalk-sync-btn');
    var syncStatus = document.getElementById('v1rontalk-sync-status');
    var accountInfo = document.getElementById('v1rontalk-account-info');

    function post(url, data, cb) {
        var req = new XMLHttpRequest();
        req.open('POST', url, true);
        req.setRequestHeader('Content-Type', 'application/json');
        req.setRequestHeader('requesttoken', OC.requestToken);
        req.onload = function () { cb(JSON.parse(req.responseText)); };
        req.send(JSON.stringify(data));
    }

    if (toggle) {
        toggle.addEventListener('change', function () {
            post(OC.generateUrl('/apps/v1rontalk/api/user/settings'), {
                allow_bots: toggle.checked,
            }, function (res) {
                if (!res.success) toggle.checked = !toggle.checked;
            });
        });
    }

    if (syncBtn) {
        syncBtn.addEventListener('click', function () {
            syncBtn.disabled = true;
            syncStatus.textContent = 'Syncing...';
            post(OC.generateUrl('/apps/v1rontalk/api/user/sync'), {}, function (res) {
                syncBtn.disabled = false;
                if (res.success) {
                    syncStatus.textContent = 'Synced!';
                    if (accountInfo) {
                        document.getElementById('v1rontalk-balance').textContent = res.balance;
                        document.getElementById('v1rontalk-char-count').textContent = (res.characters || []).length;
                        accountInfo.style.display = 'block';
                    }
                } else {
                    syncStatus.textContent = 'Sync failed: ' + (res.error || 'unknown error');
                    syncStatus.style.color = '#e74c3c';
                }
            });
        });
    }
}());
</script>
