# Church Giving

Lightweight WordPress donation plugin with Stripe and PayPal support, recurring giving, detailed email receipts, and a donor portal with magic link authentication.

## Features

- **One-time and recurring donations** via Stripe and PayPal
- **Multi-step donation form** with shortcode and Gutenberg block
- **Multiple funds** (General Fund, Building Fund, Missions, etc.)
- **Detailed email receipts** with BCC to admin, merge tags, and theme-overridable templates
- **Admin dashboard** with donation history, donor list, and fund management
- **Venmo support** via PayPal integration
- **Donor portal** with magic link authentication (no WordPress account needed)
- **Bot protection** with Cloudflare Turnstile or reCAPTCHA v3
- **Webhook-driven** payment status updates
- **CSV export** of donation data with status and date filtering
- **Dashboard widget** showing total raised, donor count, active recurring, and recent donations
- **Extensible** with filters and actions for customization

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Stripe account (for credit card processing)
- PayPal Business account (optional, for PayPal/Venmo)

## Installation

### Via Composer

```bash
composer require maranatha-tech/church-giving
```

### Manual Installation

1. Download the latest release and upload the plugin folder to `wp-content/plugins/`
2. Run `composer install --no-dev` inside the plugin folder
3. Activate the plugin in WordPress admin
4. Go to **Church Giving > Settings** to configure

## Configuration

1. Go to **Church Giving > Settings** in the WordPress admin
2. **General tab:** Set your church name, EIN, address, and default donation amounts
3. **Payment tab:** Enter your Stripe API keys and/or PayPal credentials
4. **Email tab:** Configure receipt email settings and tax-deductible statement
5. **Advanced tab:** Enable donor portal, set magic link expiration, bot protection, etc.

### Webhook Setup

#### Stripe
1. Go to your [Stripe Dashboard > Webhooks](https://dashboard.stripe.com/webhooks)
2. Add endpoint: `https://yoursite.com/wp-json/maranatha-giving/v1/webhook/stripe`
3. Subscribe to events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`, `charge.refunded`
4. Copy the webhook signing secret to Settings > Payment > Stripe Webhook Secret

#### PayPal
1. Go to your [PayPal Developer Dashboard > Webhooks](https://developer.paypal.com/dashboard/notifications/webhooks)
2. Add webhook URL: `https://yoursite.com/wp-json/maranatha-giving/v1/webhook/paypal`
3. Subscribe to events: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.REFUNDED`, `BILLING.SUBSCRIPTION.ACTIVATED`, `BILLING.SUBSCRIPTION.CANCELLED`, `BILLING.SUBSCRIPTION.SUSPENDED`, `PAYMENT.SALE.COMPLETED`
4. Copy the Webhook ID to Settings > Payment > PayPal Webhook ID

## Usage

### Shortcode

```
[maranatha_giving_form]
[maranatha_giving_form funds="1,2,3" amounts="25,50,100,250" show_recurring="yes"]
```

### Shortcode Options

| Attribute | Description | Default |
|-----------|-------------|---------|
| `funds` | Comma-separated fund IDs to show | All active funds |
| `amounts` | Comma-separated suggested amounts | From settings |
| `show_recurring` | Show recurring frequency options | `yes` |
| `form_id` | Unique ID for multiple forms on one page | `default` |

### Donor Portal

```
[maranatha_giving_portal]
```

## Email Receipts

Receipts are sent automatically when a donation is completed. Available merge tags:

- `{donor_first_name}`, `{donor_last_name}`
- `{donation_amount}`, `{donation_date}`
- `{fund_name}`, `{donation_type}`, `{transaction_id}`
- `{church_name}`, `{church_address}`, `{church_ein}`
- `{tax_statement}`, `{year_to_date_total}`, `{donor_portal_url}`

## Extensibility

### Filters
- `maranatha_giving_min_amount` — Override minimum donation amount per form
- `maranatha_giving_validate_donation` — Custom validation before processing
- `maranatha_giving_form_vars` — Modify form template variables
- `maranatha_giving_form_template` — Override form template path
- `maranatha_giving_receipt_subject` — Modify receipt email subject
- `maranatha_giving_receipt_body` — Modify receipt email body HTML
- `maranatha_giving_receipt_headers` — Modify receipt email headers
- `maranatha_giving_csv_headers` — Modify CSV export column headers
- `maranatha_giving_csv_row` — Modify individual CSV export rows

### Actions
- `maranatha_giving_donation_completed` — Fires when a donation is marked completed
- `maranatha_giving_donation_refunded` — Fires when a donation is refunded

## Development

The `vendor/` directory is gitignored and built via `composer install`. No build step is needed for JS/CSS — all assets are vanilla.

```bash
cd wp-content/plugins/church-giving
composer install
```

## License

GPL-2.0+
