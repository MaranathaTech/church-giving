=== Church Giving ===
Contributors: maranathatech
Tags: donations, giving, church, stripe, paypal
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.2.6
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight donation plugin with Stripe and PayPal support, recurring giving, email receipts, and a donor portal.

== Description ==

Church Giving is a lightweight, configurable WordPress donation plugin designed for churches and nonprofits. It supports one-time and recurring donations via Stripe and PayPal, sends detailed email receipts, and provides a donor portal with magic link authentication.

**Features:**

* One-time and recurring donations
* Stripe (credit/debit card) and PayPal/Venmo support
* Configurable donation form via shortcode or Gutenberg block
* Multiple designated funds
* Detailed email receipts with admin BCC
* Donor portal with giving history and subscription management
* Webhook-driven payment status updates
* No WordPress user accounts required for donors

== Installation ==

1. Upload the `church-giving` folder to `/wp-content/plugins/`
2. Run `composer install --no-dev` in the plugin directory
3. Activate the plugin through the Plugins menu
4. Go to Church Giving > Settings to configure

== Changelog ==

= 1.2.6 =
* Fix recurring Stripe subscriptions with Payment Element
* Fix confirmation message display after payment
* Add bot protection (Cloudflare Turnstile / reCAPTCHA v3)
* Add donor portal visual improvements
* Fix settings tabs overwriting each other's checkboxes
* Fix nonce validation with page caching (Cloudflare)

= 1.0.0 =
* Initial release with Stripe one-time donations, email receipts, admin dashboard, and fund management.
