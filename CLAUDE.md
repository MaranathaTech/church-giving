# Church Giving — CLAUDE.md

## Overview

Lightweight WordPress donation plugin with Stripe/PayPal/Venmo support, recurring giving, email receipts, donor portal with magic link authentication, and CSV export. Published by Maranatha Technologies.

## Architecture

- **Bootstrap:** `maranatha-giving.php` → `Maranatha_Giving` singleton
- **DB layer:** Custom tables via `$wpdb` — `class-database.php` base with per-entity CRUD classes
- **Settings:** Single option `maranatha_giving_settings` (array), accessed via `Maranatha_Giving::get_option()`
- **REST API:** Namespace `maranatha-giving/v1`, public endpoints authenticated by nonce or webhook signature
- **Templates:** Overridable in theme at `{theme}/maranatha-giving/`
- **Gateways:** Stripe (PHP SDK), PayPal (REST API via `wp_remote_*`), Venmo (via PayPal SDK)

## Key Patterns

- All tables prefixed `{$wpdb->prefix}maranatha_giving_` (donors, funds, donations, subscriptions)
- Admin pages use `WP_List_Table` for listings
- Webhooks are REST routes with `permission_callback: __return_true`, secured by gateway signature verification
- Email via `wp_mail()` — test button in Settings → Email tab
- Frontend JS is vanilla (no jQuery), CSS uses `.mg-` namespace with CSS custom properties
- Donor portal uses HMAC-signed cookie auth (no WordPress user accounts)
- Bot protection supports Turnstile and reCAPTCHA v3 (invisible, fail-open)

## Extensibility Hooks

### Filters
- `maranatha_giving_min_amount` — override minimum donation amount per form
- `maranatha_giving_validate_donation` — custom validation before processing (return string to reject)
- `maranatha_giving_form_vars` — modify form template variables
- `maranatha_giving_form_template` — override form template path
- `maranatha_giving_receipt_subject` — modify receipt email subject
- `maranatha_giving_receipt_body` — modify receipt email body HTML
- `maranatha_giving_receipt_headers` — modify receipt email headers
- `maranatha_giving_csv_headers` — modify CSV export column headers
- `maranatha_giving_csv_row` — modify individual CSV export rows

### Actions
- `maranatha_giving_donation_completed` — fires when a donation is marked completed
- `maranatha_giving_donation_refunded` — fires when a donation is refunded

## Settings Keys

- `church_name`, `church_ein`, `church_address`, `church_phone`, `church_website`, `church_logo`, `church_logo_id`
- `currency`, `default_amounts`, `allow_custom_amount`, `min_amount`
- `stripe_enabled`, `stripe_mode`, `stripe_test_publishable`, `stripe_test_secret`, `stripe_live_publishable`, `stripe_live_secret`, `stripe_webhook_secret`
- `paypal_enabled`, `paypal_mode`, `paypal_client_id`, `paypal_secret`, `paypal_webhook_id`, `venmo_enabled`
- `admin_bcc_email`, `email_from_name`, `email_from_address`, `email_reply_to`, `receipt_subject`, `receipt_body`, `tax_statement`
- `confirmation_message` — merge tags: `{donor_first_name}`, `{donation_amount}`, `{frequency}`, `{fund_name}`, `{gateway}`
- `bot_protection` — `none` / `turnstile` / `recaptcha`
- `bot_site_key`, `bot_secret_key`, `recaptcha_threshold`
- `form_heading`, `form_lead_in`
- `delete_data_on_uninstall`, `enable_magic_link`, `magic_link_expiration`, `donor_portal_page`, `custom_css`

## AJAX Endpoints (admin)

- `maranatha_giving_test_stripe` — Test Stripe connection (retrieves balance)
- `maranatha_giving_test_paypal` — Test PayPal connection (requests access token)
- `maranatha_giving_test_email` — Send test receipt email to admin

## Build

- `vendor/` is gitignored — run `composer install` to build
- Stripe SDK: `stripe/stripe-php ^16.0`
- No build step for JS/CSS (vanilla, no bundler)

## Testing

- Set Stripe test keys in Settings → Payment Gateways
- Set PayPal sandbox keys in Settings → Payment Gateways
- Use `[maranatha_giving_form]` shortcode to embed donation form
- Use `[maranatha_giving_portal]` shortcode for donor portal
- Stripe webhook URL: `{site}/wp-json/maranatha-giving/v1/webhook/stripe`
- PayPal webhook URL: `{site}/wp-json/maranatha-giving/v1/webhook/paypal`
- CSV export: Donations list → Export CSV button (top-right)
