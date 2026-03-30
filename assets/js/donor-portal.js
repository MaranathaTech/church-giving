/**
 * Church Giving — Donor Portal (Vanilla JS)
 */
(function () {
    'use strict';

    var config = window.mgPortalConfig || {};

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        var portal = document.querySelector('.mg-portal');
        if (!portal) return;

        // Login form.
        var sendLinkBtn = portal.querySelector('#mg-portal-send-link');
        if (sendLinkBtn) {
            sendLinkBtn.addEventListener('click', function () {
                handleSendLink(portal);
            });
        }

        // Portal tabs.
        var tabs = portal.querySelectorAll('.mg-portal-tab');
        var panels = portal.querySelectorAll('.mg-portal-panel');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('mg-active'); });
                panels.forEach(function (p) { p.classList.remove('mg-active'); });
                tab.classList.add('mg-active');
                var panel = portal.querySelector('#mg-panel-' + tab.dataset.tab);
                if (panel) panel.classList.add('mg-active');
            });
        });

        // Profile save.
        var saveBtn = portal.querySelector('#mg-profile-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                handleSaveProfile(portal);
            });
        }

        // Explicitly render invisible Turnstile widget.
        if (config.bot_protection === 'turnstile' && config.bot_site_key && typeof turnstile !== 'undefined') {
            var tsContainer = document.getElementById('mg-portal-bot-widget');
            if (tsContainer) {
                portalTurnstileId = turnstile.render(tsContainer, {
                    sitekey: config.bot_site_key,
                    callback: window.mgPortalTurnstileCallback,
                    size: 'invisible',
                });
            }
        }

        // Load data if authenticated.
        if (config.authenticated) {
            loadHistory(portal, 1);
            loadSubscriptions(portal);
        }
    }

    /**
     * Acquire a bot protection token based on configured provider.
     * Returns empty string if no bot protection is configured.
     */
    async function getBotToken() {
        if (!config.bot_protection || !config.bot_site_key) {
            return '';
        }

        if (config.bot_protection === 'recaptcha' && typeof grecaptcha !== 'undefined') {
            return await grecaptcha.execute(config.bot_site_key, { action: 'portal_login' });
        }

        if (config.bot_protection === 'turnstile' && typeof turnstile !== 'undefined' && portalTurnstileId !== null) {
            portalBotToken = '';
            turnstile.reset(portalTurnstileId);
            turnstile.execute(portalTurnstileId);
            // Wait for callback.
            return await new Promise(function (resolve) {
                var attempts = 0;
                var check = setInterval(function () {
                    if (portalBotToken || attempts >= 50) {
                        clearInterval(check);
                        resolve(portalBotToken || '');
                    }
                    attempts++;
                }, 100);
            });
        }

        return '';
    }

    // Turnstile state.
    var portalBotToken = '';
    var portalTurnstileId = null;

    // Global callback for Turnstile invisible widget.
    window.mgPortalTurnstileCallback = function (token) {
        portalBotToken = token;
    };

    async function handleSendLink(portal) {
        var emailInput = portal.querySelector('#mg-portal-email');
        var btn = portal.querySelector('#mg-portal-send-link');
        var textEl = btn.querySelector('.mg-submit-text');
        var spinner = btn.querySelector('.mg-submit-spinner');
        var messageEl = portal.querySelector('#mg-portal-message');

        var email = (emailInput || {}).value || '';
        if (!email.trim() || !email.includes('@')) {
            showMsg(messageEl, 'Please enter a valid email address.', 'error');
            return;
        }

        btn.disabled = true;
        textEl.style.display = 'none';
        spinner.style.display = 'inline-block';

        try {
            var botToken = await getBotToken();

            var resp = await fetch(config.restUrl + 'magic-link', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: email.trim(),
                    mg_nonce: config.nonce,
                    bot_token: botToken,
                }),
            });
            var data = await resp.json();
            showMsg(messageEl, data.message || 'Check your email for a login link.', 'success');
            emailInput.value = '';
        } catch (err) {
            showMsg(messageEl, 'Something went wrong. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            textEl.style.display = 'inline';
            spinner.style.display = 'none';
            // Reset Turnstile token for next attempt.
            portalBotToken = '';
            if (config.bot_protection === 'turnstile' && typeof turnstile !== 'undefined') {
                var widget = document.querySelector('#mg-portal-bot-widget');
                if (widget) turnstile.reset(widget);
            }
        }
    }

    async function loadHistory(portal, page) {
        var container = portal.querySelector('#mg-donation-history');
        var pagination = portal.querySelector('#mg-history-pagination');

        try {
            var resp = await fetch(config.restUrl + 'donor/history?page=' + page, {
                credentials: 'same-origin',
            });
            var data = await resp.json();

            if (!data.donations || data.donations.length === 0) {
                container.innerHTML = '<p class="mg-no-data">No donations found.</p>';
                pagination.style.display = 'none';
                return;
            }

            var html = '<table class="mg-history-table"><thead><tr>'
                + '<th>Date</th><th>Amount</th><th>Fund</th><th>Type</th><th>Status</th><th></th>'
                + '</tr></thead><tbody>';

            data.donations.forEach(function (d) {
                html += '<tr>'
                    + '<td>' + esc(d.date) + '</td>'
                    + '<td>$' + esc(d.amount) + '</td>'
                    + '<td>' + esc(d.fund) + '</td>'
                    + '<td>' + esc(d.type) + '</td>'
                    + '<td>' + statusBadge(d.status) + '</td>'
                    + '<td><button class="mg-resend-btn" data-donation-id="' + d.id + '">Resend Receipt</button></td>'
                    + '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;

            // Bind resend buttons.
            container.querySelectorAll('.mg-resend-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    handleResendReceipt(btn);
                });
            });

            // Pagination.
            if (data.total_pages > 1) {
                var pagHtml = '';
                for (var i = 1; i <= data.total_pages; i++) {
                    pagHtml += '<button class="mg-page-btn' + (i === page ? ' mg-active' : '') + '" data-page="' + i + '">' + i + '</button>';
                }
                pagination.innerHTML = pagHtml;
                pagination.style.display = 'flex';
                pagination.querySelectorAll('.mg-page-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        loadHistory(portal, parseInt(btn.dataset.page, 10));
                    });
                });
            } else {
                pagination.style.display = 'none';
            }
        } catch (err) {
            container.innerHTML = '<p class="mg-no-data">Could not load history.</p>';
        }
    }

    async function loadSubscriptions(portal) {
        var container = portal.querySelector('#mg-subscriptions-list');

        try {
            var resp = await fetch(config.restUrl + 'donor/subscriptions', {
                credentials: 'same-origin',
            });
            var data = await resp.json();

            if (!data.subscriptions || data.subscriptions.length === 0) {
                container.innerHTML = '<p class="mg-no-data">No recurring gifts found.</p>';
                return;
            }

            var html = '<table class="mg-subs-table"><thead><tr>'
                + '<th>Amount</th><th>Frequency</th><th>Fund</th><th>Status</th><th></th>'
                + '</tr></thead><tbody>';

            data.subscriptions.forEach(function (s) {
                var actionBtn = '';
                if (s.status.toLowerCase() === 'active') {
                    actionBtn = '<button class="mg-cancel-btn" data-sub-id="' + s.id + '">Cancel</button>';
                }
                html += '<tr>'
                    + '<td>$' + esc(s.amount) + '</td>'
                    + '<td>' + esc(s.frequency) + '</td>'
                    + '<td>' + esc(s.fund) + '</td>'
                    + '<td>' + statusBadge(s.status) + '</td>'
                    + '<td>' + actionBtn + '</td>'
                    + '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;

            // Bind cancel buttons.
            container.querySelectorAll('.mg-cancel-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    handleCancelSubscription(portal, btn);
                });
            });
        } catch (err) {
            container.innerHTML = '<p class="mg-no-data">Could not load subscriptions.</p>';
        }
    }

    async function handleResendReceipt(btn) {
        var donationId = btn.dataset.donationId;
        btn.disabled = true;
        btn.textContent = 'Sending...';

        try {
            var resp = await fetch(config.restUrl + 'donor/resend-receipt', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ donation_id: parseInt(donationId, 10) }),
            });
            var data = await resp.json();
            btn.textContent = data.success ? 'Sent!' : 'Failed';
        } catch (err) {
            btn.textContent = 'Failed';
        }
    }

    async function handleCancelSubscription(portal, btn) {
        if (!confirm('Are you sure you want to cancel this recurring gift?')) return;

        var subId = btn.dataset.subId;
        btn.disabled = true;
        btn.textContent = 'Cancelling...';

        try {
            var resp = await fetch(config.restUrl + 'donor/cancel-subscription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ subscription_id: parseInt(subId, 10) }),
            });
            var data = await resp.json();
            if (data.success) {
                loadSubscriptions(portal);
            } else {
                btn.textContent = 'Error';
            }
        } catch (err) {
            btn.textContent = 'Error';
        }
    }

    async function handleSaveProfile(portal) {
        var btn = portal.querySelector('#mg-profile-save');
        var messageEl = portal.querySelector('#mg-profile-message');

        var profileData = {
            first_name:    (portal.querySelector('#mg-profile-first-name') || {}).value || '',
            last_name:     (portal.querySelector('#mg-profile-last-name') || {}).value || '',
            phone:         (portal.querySelector('#mg-profile-phone') || {}).value || '',
            address_line1: (portal.querySelector('#mg-profile-address') || {}).value || '',
            city:          (portal.querySelector('#mg-profile-city') || {}).value || '',
            state:         (portal.querySelector('#mg-profile-state') || {}).value || '',
            zip:           (portal.querySelector('#mg-profile-zip') || {}).value || '',
        };

        btn.disabled = true;
        btn.textContent = 'Saving...';

        try {
            var resp = await fetch(config.restUrl + 'donor/profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(profileData),
            });
            var data = await resp.json();
            showMsg(messageEl, data.success ? 'Profile saved!' : 'Could not save profile.', data.success ? 'success' : 'error');
        } catch (err) {
            showMsg(messageEl, 'Something went wrong.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save Profile';
        }
    }

    function statusBadge(status) {
        var s = esc(status || '').toLowerCase();
        return '<span class="mg-status-badge mg-status-' + s + '">' + esc(status) + '</span>';
    }

    function showMsg(el, msg, type) {
        if (!el) return;
        el.textContent = msg;
        el.className = 'mg-message ' + (type === 'success' ? 'mg-success' : 'mg-error-msg');
        el.style.display = 'block';
    }

    function esc(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
})();
