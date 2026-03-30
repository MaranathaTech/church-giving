/**
 * Church Giving — Admin JS
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Copy buttons for webhook URLs.
        document.querySelectorAll('.mg-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.dataset.target);
                if (!target) return;

                navigator.clipboard.writeText(target.textContent.trim()).then(function () {
                    var original = btn.textContent;
                    btn.textContent = 'Copied!';
                    setTimeout(function () { btn.textContent = original; }, 2000);
                });
            });
        });

        // Media library picker for church logo.
        var logoSelect = document.getElementById('mg-logo-select');
        var logoRemove = document.getElementById('mg-logo-remove');
        if (logoSelect && typeof wp !== 'undefined' && wp.media) {
            var mediaFrame = wp.media({
                title: 'Select Church Logo',
                library: { type: 'image' },
                multiple: false,
                button: { text: 'Use This Image' },
            });

            logoSelect.addEventListener('click', function () {
                mediaFrame.open();
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                document.getElementById('mg-church_logo').value = attachment.url;
                document.getElementById('mg-church_logo_id').value = attachment.id;
                document.getElementById('mg-logo-preview').innerHTML = '<img src="' + attachment.url + '" style="max-width:200px;height:auto;">';
                if (logoRemove) logoRemove.style.display = '';
            });

            if (logoRemove) {
                logoRemove.addEventListener('click', function () {
                    document.getElementById('mg-church_logo').value = '';
                    document.getElementById('mg-church_logo_id').value = '';
                    document.getElementById('mg-logo-preview').innerHTML = '';
                    logoRemove.style.display = 'none';
                });
            }
        }

        // Bot protection conditional fields.
        var botSelect = document.getElementById('mg-bot_protection');
        if (botSelect) {
            var siteKeyRow = document.getElementById('mg-bot-site-key-row');
            var secretKeyRow = document.getElementById('mg-bot_secret_key') ? document.getElementById('mg-bot_secret_key').closest('tr') : null;
            var thresholdRow = document.getElementById('mg-recaptcha_threshold') ? document.getElementById('mg-recaptcha_threshold').closest('tr') : null;
            var siteKeyDescTurnstile = document.getElementById('mg-bot-site-key-desc-turnstile');
            var siteKeyDescRecaptcha = document.getElementById('mg-bot-site-key-desc-recaptcha');

            function toggleBotFields() {
                var val = botSelect.value;
                var showKeys = val !== 'none';
                var showThreshold = val === 'recaptcha';
                if (siteKeyRow) siteKeyRow.style.display = showKeys ? '' : 'none';
                if (secretKeyRow) secretKeyRow.style.display = showKeys ? '' : 'none';
                if (thresholdRow) thresholdRow.style.display = showThreshold ? '' : 'none';

                // Swap site key description based on provider.
                if (siteKeyDescTurnstile) siteKeyDescTurnstile.style.display = (val === 'turnstile') ? '' : 'none';
                if (siteKeyDescRecaptcha) siteKeyDescRecaptcha.style.display = (val === 'recaptcha') ? '' : 'none';
            }

            toggleBotFields();
            botSelect.addEventListener('change', toggleBotFields);
        }

        // Test Stripe connection button.
        var testStripeBtn = document.getElementById('mg-test-stripe');
        if (testStripeBtn) {
            testStripeBtn.addEventListener('click', function () {
                var result = document.getElementById('mg-test-stripe-result');
                testStripeBtn.disabled = true;
                testStripeBtn.textContent = 'Testing...';

                fetch(mgAdmin.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=maranatha_giving_test_stripe&nonce=' + encodeURIComponent(mgAdmin.nonce),
                }).then(function (resp) {
                    return resp.json();
                }).then(function (data) {
                    if (result) {
                        result.textContent = data.success ? 'Connection successful!' : (data.data || 'Connection failed.');
                        result.style.color = data.success ? '#28a745' : '#dc3545';
                    }
                }).catch(function () {
                    if (result) {
                        result.textContent = 'Request failed.';
                        result.style.color = '#dc3545';
                    }
                }).finally(function () {
                    testStripeBtn.disabled = false;
                    testStripeBtn.textContent = 'Test Stripe Connection';
                });
            });
        }

        // Test PayPal connection button.
        var testPayPalBtn = document.getElementById('mg-test-paypal');
        if (testPayPalBtn) {
            testPayPalBtn.addEventListener('click', function () {
                var result = document.getElementById('mg-test-paypal-result');
                testPayPalBtn.disabled = true;
                testPayPalBtn.textContent = 'Testing...';

                fetch(mgAdmin.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=maranatha_giving_test_paypal&nonce=' + encodeURIComponent(mgAdmin.nonce),
                }).then(function (resp) {
                    return resp.json();
                }).then(function (data) {
                    if (result) {
                        result.textContent = data.success ? 'Connection successful!' : (data.data || 'Connection failed.');
                        result.style.color = data.success ? '#28a745' : '#dc3545';
                    }
                }).catch(function () {
                    if (result) {
                        result.textContent = 'Request failed.';
                        result.style.color = '#dc3545';
                    }
                }).finally(function () {
                    testPayPalBtn.disabled = false;
                    testPayPalBtn.textContent = 'Test PayPal Connection';
                });
            });
        }

        // Test email button.
        var testBtn = document.getElementById('mg-send-test-email');
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                var result = document.getElementById('mg-test-email-result');
                testBtn.disabled = true;
                testBtn.textContent = 'Sending...';

                fetch(mgAdmin.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=maranatha_giving_test_email&nonce=' + encodeURIComponent(mgAdmin.nonce),
                }).then(function (resp) {
                    return resp.json();
                }).then(function (data) {
                    if (result) {
                        result.textContent = data.success ? 'Test email sent!' : (data.data || 'Failed to send.');
                        result.style.color = data.success ? '#28a745' : '#dc3545';
                    }
                }).catch(function () {
                    if (result) {
                        result.textContent = 'Request failed.';
                        result.style.color = '#dc3545';
                    }
                }).finally(function () {
                    testBtn.disabled = false;
                    testBtn.textContent = 'Send Test Email';
                });
            });
        }
    });
})();
