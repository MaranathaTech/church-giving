/**
 * Church Giving — Donation Form (Vanilla JS)
 */
(function () {
    'use strict';

    var config = window.mgFormConfig || {};
    var stripe, elements, paymentElement;
    var selectedAmount = 0;
    var selectedFrequency = 'one-time';
    var selectedGateway = config.stripeEnabled ? 'stripe' : (config.paypalEnabled ? 'paypal' : '');
    var botToken = '';
    var turnstileWidgetId = null;
    var currentStep = 1;

    // Turnstile callback — receives token after execute().
    window.mgTurnstileCallback = function (token) {
        botToken = token;
    };

    // Fetch a fresh nonce from the server to avoid stale cached tokens.
    function refreshNonce() {
        fetch(config.restUrl + 'nonce', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.nonce) {
                    config.nonce = data.nonce;
                }
            })
            .catch(function () { /* keep the embedded nonce as fallback */ });
    }

    document.addEventListener('DOMContentLoaded', init);

    function handleStripeRedirectReturn(form) {
        var params = new URLSearchParams(window.location.search);
        var redirectStatus = params.get('redirect_status');
        if (!params.get('payment_intent') && !params.get('payment_intent_client_secret')) return;

        // Hide everything inside the form and show a result message.
        Array.prototype.forEach.call(form.children, function (child) {
            child.style.display = 'none';
        });

        var msg;
        var type;
        if (redirectStatus === 'succeeded') {
            msg = 'Thank you for your generous gift! A receipt has been sent to your email.';
            type = 'success';
        } else if (redirectStatus === 'processing') {
            msg = 'Your payment is being processed. You will receive a receipt by email.';
            type = 'success';
        } else {
            msg = 'Payment was not completed. Please try again.';
            type = 'error';
        }

        var div = document.createElement('div');
        div.className = 'mg-message ' + (type === 'success' ? 'mg-success' : 'mg-error-msg');
        div.style.display = 'block';
        div.style.padding = '24px';
        div.style.fontSize = '16px';
        div.style.textAlign = 'center';
        div.style.marginTop = '0';
        div.textContent = msg;
        form.insertBefore(div, form.firstChild);

        // Clean the URL so refresh doesn't re-trigger.
        window.history.replaceState({}, '', window.location.pathname);
    }

    function init() {
        var form = document.querySelector('.mg-donation-form');
        if (!form) return;

        // Handle return from Stripe 3D Secure redirect.
        handleStripeRedirectReturn(form);

        // Fetch a fresh nonce to avoid stale tokens from page caching.
        refreshNonce();

        // Set initial amount from first active button.
        var firstAmountBtn = form.querySelector('.mg-amount-btn.mg-active');
        if (firstAmountBtn && firstAmountBtn.dataset.amount !== 'custom') {
            selectedAmount = parseFloat(firstAmountBtn.dataset.amount);
        }

        bindAmountButtons(form);
        bindFrequencyButtons(form);
        bindPaymentTabs(form);
        bindSubmit(form);
        bindStepNavigation(form);

        if (config.stripeEnabled && config.stripePk) {
            initStripe(form);
        }

        if (config.paypalEnabled) {
            initPayPal(form);
        }

        // Explicitly render invisible Turnstile widget.
        if (config.botProtection === 'turnstile' && config.botSiteKey && typeof turnstile !== 'undefined') {
            var tsContainer = document.getElementById('mg-turnstile-container');
            if (tsContainer) {
                turnstileWidgetId = turnstile.render(tsContainer, {
                    sitekey: config.botSiteKey,
                    callback: window.mgTurnstileCallback,
                    size: 'invisible',
                });
            }
        }

        updateSubmitVisibility(form);
    }

    function bindStepNavigation(form) {
        form.querySelectorAll('.mg-btn-next').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var nextStep = parseInt(btn.dataset.next, 10);
                if (validateStep(form, currentStep)) {
                    goToStep(form, nextStep);
                }
            });
        });

        form.querySelectorAll('.mg-btn-back').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var prevStep = parseInt(btn.dataset.prev, 10);
                goToStep(form, prevStep);
            });
        });
    }

    function goToStep(form, step) {
        // Hide all steps.
        form.querySelectorAll('.mg-step').forEach(function (s) {
            s.classList.remove('mg-step-active');
        });

        // Show target step.
        var target = form.querySelector('.mg-step-' + step);
        if (target) target.classList.add('mg-step-active');

        // Clear step error.
        var errorEl = form.querySelector('#mg-step-' + currentStep + '-error');
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.remove('mg-visible');
        }

        currentStep = step;

        // Hide lead-in on steps 2 and 3, show on step 1.
        var leadIn = form.querySelector('.mg-lead-in');
        if (leadIn) {
            leadIn.style.display = step === 1 ? '' : 'none';
        }

        // Scroll form into view.
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validateStep(form, step) {
        if (step === 1) {
            if (selectedAmount < (config.minAmount || 1)) {
                showStepError(form, 1, 'Please select or enter an amount of at least $' + (config.minAmount || 1) + '.');
                return false;
            }
            hideStepError(form, 1);
            return true;
        }

        if (step === 2) {
            var firstName = (form.querySelector('#mg-first-name') || {}).value || '';
            var lastName = (form.querySelector('#mg-last-name') || {}).value || '';
            var email = (form.querySelector('#mg-email') || {}).value || '';
            var missing = [];

            if (!firstName.trim()) missing.push('first name');
            if (!lastName.trim()) missing.push('last name');
            if (!email.trim() || !email.includes('@')) missing.push('a valid email address');

            if (missing.length) {
                showStepError(form, 2, 'Please enter ' + missing.join(', ') + '.');
                return false;
            }
            hideStepError(form, 2);
            return true;
        }

        return true;
    }

    function showStepError(form, step, msg) {
        var el = form.querySelector('#mg-step-' + step + '-error');
        if (!el) return;
        el.textContent = msg;
        el.classList.add('mg-visible');
    }

    function hideStepError(form, step) {
        var el = form.querySelector('#mg-step-' + step + '-error');
        if (!el) return;
        el.textContent = '';
        el.classList.remove('mg-visible');
    }

    function bindAmountButtons(form) {
        var buttons = form.querySelectorAll('.mg-amount-btn');
        var customWrap = form.querySelector('.mg-custom-amount');
        var customInput = form.querySelector('#mg-custom-amount');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.remove('mg-active'); });
                btn.classList.add('mg-active');

                if (btn.dataset.amount === 'custom') {
                    if (customWrap) customWrap.style.display = 'block';
                    if (customInput) {
                        customInput.focus();
                        selectedAmount = parseFloat(customInput.value) || 0;
                    }
                } else {
                    if (customWrap) customWrap.style.display = 'none';
                    selectedAmount = parseFloat(btn.dataset.amount);
                }
                updateSubmitText(form);
                updateStripeAmount();
            });
        });

        if (customInput) {
            customInput.addEventListener('input', function () {
                selectedAmount = parseFloat(this.value) || 0;
                updateSubmitText(form);
                updateStripeAmount();
            });
        }
    }

    function bindFrequencyButtons(form) {
        var buttons = form.querySelectorAll('.mg-freq-btn');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.remove('mg-active'); });
                btn.classList.add('mg-active');
                selectedFrequency = btn.dataset.frequency;
                updateSubmitText(form);
            });
        });
    }

    function bindPaymentTabs(form) {
        var tabs = form.querySelectorAll('.mg-payment-tab');
        var panels = form.querySelectorAll('.mg-payment-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('mg-active'); });
                panels.forEach(function (p) { p.classList.remove('mg-active'); });
                tab.classList.add('mg-active');
                selectedGateway = tab.dataset.gateway;

                var panel = form.querySelector('.mg-payment-' + selectedGateway);
                if (panel) panel.classList.add('mg-active');

                updateSubmitVisibility(form);
            });
        });
    }

    function updateSubmitVisibility(form) {
        var submitBtn = form.querySelector('#mg-submit');
        if (!submitBtn) return;

        // Hide the submit button for PayPal/Venmo — they have their own buttons.
        if (selectedGateway === 'paypal' || selectedGateway === 'venmo') {
            submitBtn.style.display = 'none';
        } else {
            submitBtn.style.display = '';
        }
    }

    function initStripe(form) {
        if (typeof Stripe === 'undefined') return;

        stripe = Stripe(config.stripePk);
        mountStripeElements(form);
    }

    var stripeAppearance = {
        theme: 'stripe',
        variables: { colorPrimary: '#2c3e50', borderRadius: '6px' },
    };

    function mountStripeElements(form) {
        var initialAmount = selectedAmount > 0 ? selectedAmount : (config.minAmount || 5);

        if (paymentElement) {
            paymentElement.unmount();
            paymentElement = null;
        }

        // Use deferred mode for collecting card details up front.
        // The mode is always 'payment' — for subscriptions we'll
        // re-init Elements with the real client_secret before confirming.
        elements = stripe.elements({
            mode: 'payment',
            amount: Math.round(initialAmount * 100),
            currency: (config.currency || 'usd').toLowerCase(),
            paymentMethodCreation: 'manual',
            appearance: stripeAppearance,
        });

        paymentElement = elements.create('payment');
        var container = form.querySelector('#mg-stripe-elements');
        if (container) {
            paymentElement.mount(container);
        }

        paymentElement.on('change', function (event) {
            var errorEl = form.querySelector('#mg-stripe-errors');
            if (errorEl) {
                errorEl.textContent = event.error ? event.error.message : '';
            }
        });
    }

    function updateStripeAmount() {
        if (elements && selectedAmount > 0) {
            elements.update({
                amount: Math.round(selectedAmount * 100),
            });
        }
    }

    function initPayPal(form) {
        if (typeof paypal === 'undefined') return;

        // One-time PayPal buttons.
        var paypalContainer = form.querySelector('#mg-paypal-buttons');
        if (paypalContainer) {
            renderPayPalButtons(form, paypalContainer, 'paypal');
        }

        // Venmo buttons (separate container).
        var venmoContainer = form.querySelector('#mg-venmo-buttons');
        if (venmoContainer) {
            renderPayPalButtons(form, venmoContainer, 'venmo');
        }
    }

    function renderPayPalButtons(form, container, gateway) {
        var fundingSource = gateway === 'venmo' ? paypal.FUNDING.VENMO : paypal.FUNDING.PAYPAL;

        var buttonConfig = {
            fundingSource: fundingSource,
            style: {
                layout: 'vertical',
                color: gateway === 'venmo' ? 'blue' : 'gold',
                shape: 'rect',
                label: 'donate',
            },
            createOrder: function () {
                return handlePayPalCreateOrder(form, gateway);
            },
            onApprove: function (data) {
                return handlePayPalApprove(form, data, gateway);
            },
            onError: function (err) {
                var messageEl = form.querySelector('#mg-form-message');
                showMessage(messageEl, 'Payment could not be completed. Please try again.', 'error');
            },
        };

        var buttons = paypal.Buttons(buttonConfig);
        if (buttons.isEligible()) {
            buttons.render(container);
        }
    }

    async function handlePayPalCreateOrder(form, gateway) {
        var messageEl = form.querySelector('#mg-form-message');
        hideMessage(messageEl);

        // Validate fields.
        var firstName = (form.querySelector('#mg-first-name') || {}).value || '';
        var lastName = (form.querySelector('#mg-last-name') || {}).value || '';
        var email = (form.querySelector('#mg-email') || {}).value || '';

        if (!firstName.trim() || !lastName.trim()) {
            showMessage(messageEl, 'Please enter your full name.', 'error');
            throw new Error('Name required');
        }
        if (!email.trim() || !email.includes('@')) {
            showMessage(messageEl, 'Please enter a valid email address.', 'error');
            throw new Error('Email required');
        }
        if (selectedAmount < (config.minAmount || 1)) {
            showMessage(messageEl, 'Please select or enter a valid amount.', 'error');
            throw new Error('Amount required');
        }

        // Bot protection for PayPal flow.
        var ppBotToken = '';
        if (config.botProtection === 'recaptcha' && config.botSiteKey && typeof grecaptcha !== 'undefined') {
            ppBotToken = await grecaptcha.execute(config.botSiteKey, { action: 'donate' });
        } else if (config.botProtection === 'turnstile') {
            if (botToken) {
                ppBotToken = botToken;
            } else if (typeof turnstile !== 'undefined') {
                var tsWidget = document.querySelector('.cf-turnstile');
                if (tsWidget) {
                    turnstile.execute(tsWidget);
                    await new Promise(function (resolve) {
                        var attempts = 0;
                        var check = setInterval(function () {
                            if (botToken || attempts >= 50) {
                                clearInterval(check);
                                ppBotToken = botToken;
                                resolve();
                            }
                            attempts++;
                        }, 100);
                    });
                }
            }
        }

        var fundEl = form.querySelector('#mg-fund');
        var type = selectedFrequency === 'one-time' ? 'one-time' : 'recurring';

        var body = {
            amount: selectedAmount,
            email: email.trim(),
            first_name: firstName.trim(),
            last_name: lastName.trim(),
            gateway: gateway,
            type: type,
            frequency: selectedFrequency === 'one-time' ? 'monthly' : selectedFrequency,
            fund_id: fundEl ? fundEl.value : null,
            form_id: config.formId || 'default',
            mg_nonce: config.nonce,
            bot_token: ppBotToken,
        };

        var resp = await fetch(config.restUrl + 'donate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        var data = await resp.json();
        if (!resp.ok) {
            showMessage(messageEl, data.error || 'Could not create order.', 'error');
            throw new Error(data.error || 'Create order failed');
        }

        // Store donation_id for capture.
        form.dataset.pendingDonationId = data.donation_id;

        return data.order_id;
    }

    async function handlePayPalApprove(form, data, gateway) {
        var messageEl = form.querySelector('#mg-form-message');

        try {
            var resp = await fetch(config.restUrl + 'paypal/capture', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: data.orderID,
                    donation_id: parseInt(form.dataset.pendingDonationId || '0', 10),
                    mg_nonce: config.nonce,
                }),
            });

            var result = await resp.json();
            if (!resp.ok || !result.success) {
                throw new Error(result.error || 'Capture failed.');
            }

            showConfirmation(form, buildSuccessMessage(form, result.gateway || gateway));
        } catch (err) {
            showMessage(messageEl, err.message, 'error');
        }
    }

    function bindSubmit(form) {
        var submitBtn = form.querySelector('#mg-submit');
        if (!submitBtn) return;

        submitBtn.addEventListener('click', function () {
            handleSubmit(form);
        });
    }

    async function handleSubmit(form) {
        var submitBtn = form.querySelector('#mg-submit');
        var textEl = form.querySelector('.mg-submit-text');
        var spinnerEl = form.querySelector('.mg-submit-spinner');
        var messageEl = form.querySelector('#mg-form-message');

        // Validate.
        var firstName = (form.querySelector('#mg-first-name') || {}).value || '';
        var lastName = (form.querySelector('#mg-last-name') || {}).value || '';
        var email = (form.querySelector('#mg-email') || {}).value || '';
        var fundEl = form.querySelector('#mg-fund');
        var fundId = fundEl ? fundEl.value : null;

        if (!firstName.trim() || !lastName.trim()) {
            showMessage(messageEl, 'Please enter your full name.', 'error');
            return;
        }
        if (!email.trim() || !email.includes('@')) {
            showMessage(messageEl, 'Please enter a valid email address.', 'error');
            return;
        }
        if (selectedAmount < (config.minAmount || 1)) {
            showMessage(messageEl, 'Please select or enter a valid amount.', 'error');
            return;
        }

        // Disable button.
        submitBtn.disabled = true;
        textEl.style.display = 'none';
        spinnerEl.style.display = 'inline-block';
        hideMessage(messageEl);

        try {
            // Bot protection — get token before submitting.
            var currentBotToken = '';
            if (config.botProtection === 'recaptcha' && config.botSiteKey && typeof grecaptcha !== 'undefined') {
                currentBotToken = await grecaptcha.execute(config.botSiteKey, { action: 'donate' });
            } else if (config.botProtection === 'turnstile' && typeof turnstile !== 'undefined') {
                // Reset and execute to get a fresh token.
                botToken = '';
                if (turnstileWidgetId !== null) {
                    turnstile.reset(turnstileWidgetId);
                    turnstile.execute(turnstileWidgetId);
                }
                // Wait for the callback to fire.
                await new Promise(function (resolve) {
                    var attempts = 0;
                    var check = setInterval(function () {
                        if (botToken || attempts >= 50) {
                            clearInterval(check);
                            currentBotToken = botToken;
                            resolve();
                        }
                        attempts++;
                    }, 100);
                });
            }

            // Validate Stripe Payment Element before creating intent.
            if (selectedGateway === 'stripe' && elements) {
                var submitResult = await elements.submit();
                if (submitResult.error) {
                    throw new Error(submitResult.error.message);
                }
            }

            // Step 1: Call our REST endpoint to create PaymentIntent / Subscription.
            var type = selectedFrequency === 'one-time' ? 'one-time' : 'recurring';
            var body = {
                amount: selectedAmount,
                email: email.trim(),
                first_name: firstName.trim(),
                last_name: lastName.trim(),
                gateway: selectedGateway,
                type: type,
                frequency: selectedFrequency === 'one-time' ? 'monthly' : selectedFrequency,
                fund_id: fundId,
                form_id: config.formId || 'default',
                mg_nonce: config.nonce,
                bot_token: currentBotToken,
            };

            var resp = await fetch(config.restUrl + 'donate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            var data = await resp.json();

            if (!resp.ok) {
                throw new Error(data.error || 'Something went wrong. Please try again.');
            }

            // Step 2: Confirm payment with Stripe.
            if (selectedGateway === 'stripe' && data.client_secret) {
                var result;
                if (data.type === 'recurring') {
                    // For subscriptions, the server-created PaymentIntent has
                    // different settings than our deferred Elements instance.
                    // Extract the payment method first, then confirm directly.
                    var pmResult = await stripe.createPaymentMethod({
                        elements: elements,
                        params: {
                            billing_details: {
                                name: firstName.trim() + ' ' + lastName.trim(),
                                email: email.trim(),
                            },
                        },
                    });
                    if (pmResult.error) {
                        throw new Error(pmResult.error.message);
                    }
                    result = await stripe.confirmCardPayment(data.client_secret, {
                        payment_method: pmResult.paymentMethod.id,
                        return_url: window.location.href,
                    });
                } else {
                    result = await stripe.confirmPayment({
                        elements: elements,
                        clientSecret: data.client_secret,
                        confirmParams: {
                            return_url: window.location.href,
                            payment_method_data: {
                                billing_details: {
                                    name: firstName.trim() + ' ' + lastName.trim(),
                                    email: email.trim(),
                                },
                            },
                        },
                        redirect: 'if_required',
                    });
                }

                if (result.error) {
                    throw new Error(result.error.message);
                }

                // Payment succeeded (or is processing).
                showConfirmation(form, buildSuccessMessage(form, 'stripe'));
                return;
            }
        } catch (err) {
            showMessage(messageEl, err.message, 'error');
        } finally {
            submitBtn.disabled = false;
            textEl.style.display = 'inline';
            spinnerEl.style.display = 'none';
            // Reset Turnstile so a fresh token can be obtained on retry.
            botToken = '';
            if (config.botProtection === 'turnstile' && typeof turnstile !== 'undefined' && turnstileWidgetId !== null) {
                turnstile.reset(turnstileWidgetId);
            }
        }
    }

    function buildSuccessMessage(form, gateway) {
        if (config.confirmationMessage) {
            var firstName = (form.querySelector('#mg-first-name') || {}).value || '';
            var fundEl = form.querySelector('#mg-fund');
            var fundName = fundEl && fundEl.options ? (fundEl.options[fundEl.selectedIndex] || {}).text || 'General Fund' : 'General Fund';
            var freqLabel = selectedFrequency === 'one-time' ? 'One-Time' : selectedFrequency.charAt(0).toUpperCase() + selectedFrequency.slice(1);
            var gatewayName = gateway === 'venmo' ? 'Venmo' : (gateway === 'paypal' ? 'PayPal' : 'Stripe');

            return config.confirmationMessage
                .replace(/\{donor_first_name\}/g, firstName.trim())
                .replace(/\{donation_amount\}/g, '$' + selectedAmount.toFixed(2))
                .replace(/\{frequency\}/g, freqLabel)
                .replace(/\{fund_name\}/g, fundName)
                .replace(/\{gateway\}/g, gatewayName);
        }

        // Fallback to default messages.
        if (gateway === 'paypal' || gateway === 'venmo') {
            var gName = gateway === 'venmo' ? 'Venmo' : 'PayPal';
            return 'Thank you for your generous gift via ' + gName + '! A receipt has been sent to your email.';
        }
        if (selectedFrequency !== 'one-time') {
            var fName = selectedFrequency.charAt(0).toUpperCase() + selectedFrequency.slice(1);
            return 'Thank you! Your ' + fName.toLowerCase() + ' gift of $' + selectedAmount.toFixed(2) + ' has been set up. A receipt has been sent to your email.';
        }
        return 'Thank you for your generous gift! A receipt has been sent to your email.';
    }

    function updateSubmitText(form) {
        var textEl = form.querySelector('.mg-submit-text');
        if (!textEl) return;

        var amountStr = selectedAmount > 0 ? ' $' + selectedAmount.toFixed(2) : '';
        var freqLabel = '';
        if (selectedFrequency !== 'one-time') {
            freqLabel = ' ' + selectedFrequency.charAt(0).toUpperCase() + selectedFrequency.slice(1);
        }
        textEl.textContent = 'Give' + amountStr + freqLabel;
    }

    function showConfirmation(form, msg) {
        // Hide all form children and show a standalone confirmation.
        Array.prototype.forEach.call(form.children, function (child) {
            child.style.display = 'none';
        });
        var div = document.createElement('div');
        div.className = 'mg-message mg-success';
        div.style.cssText = 'display:block;padding:24px;font-size:16px;text-align:center;margin:0;';
        div.textContent = msg;
        form.insertBefore(div, form.firstChild);
    }

    function showMessage(el, msg, type) {
        if (!el) return;
        el.textContent = msg;
        el.className = 'mg-message ' + (type === 'success' ? 'mg-success' : 'mg-error-msg');
        el.style.display = 'block';
    }

    function hideMessage(el) {
        if (!el) return;
        el.style.display = 'none';
    }

    function resetForm(form) {
        var inputs = form.querySelectorAll('.mg-input');
        inputs.forEach(function (input) { input.value = ''; });

        // Reset to first amount.
        var buttons = form.querySelectorAll('.mg-amount-btn');
        buttons.forEach(function (b) { b.classList.remove('mg-active'); });
        if (buttons[0]) {
            buttons[0].classList.add('mg-active');
            selectedAmount = parseFloat(buttons[0].dataset.amount) || 0;
        }

        // Hide custom amount field.
        var customWrap = form.querySelector('.mg-custom-amount');
        if (customWrap) customWrap.style.display = 'none';

        // Reset frequency.
        var freqBtns = form.querySelectorAll('.mg-freq-btn');
        freqBtns.forEach(function (b) { b.classList.remove('mg-active'); });
        var firstFreq = form.querySelector('.mg-freq-btn');
        if (firstFreq) {
            firstFreq.classList.add('mg-active');
            selectedFrequency = 'one-time';
        }

        updateSubmitText(form);

        // Reset bot protection.
        botToken = '';
        if (config.botProtection === 'turnstile' && typeof turnstile !== 'undefined') {
            var tsWidget = form.querySelector('.cf-turnstile');
            if (tsWidget) turnstile.reset(tsWidget);
        }

        // Return to step 1.
        goToStep(form, 1);
    }
})();
