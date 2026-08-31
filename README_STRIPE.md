Stripe integration — setup notes

1) Add environment variables (Windows PowerShell example)

```powershell
$env:STRIPE_SECRET_KEY = 'sk_test_...'
$env:STRIPE_WEBHOOK_SECRET = 'whsec_...'
# Start PHP built-in server in workspace root
php -S localhost:8800 -t .
```

Alternatively, copy the template and fill in your test keys:

```powershell
copy stripe_local.example.php stripe_local.php
# Edit stripe_local.php and replace sk_test_REPLACE_ME and whsec_REPLACE_ME
```

`stripe_local.php` is loaded automatically by `_base.php` and is listed in `.gitignore`.

On production, set `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` in your webserver environment.

2) Optional: use composer and official SDK

If you prefer the official SDK, run:

```powershell
composer require stripe/stripe-php
```

Then modify `order/create_checkout.php` and `order/stripe_webhook.php` to use the SDK for session creation and signature verification.

3) Configure a Stripe webhook

- In the Stripe Dashboard, add an endpoint: `https://yourhost/order/stripe_webhook.php`
- Subscribe to `checkout.session.completed` (and other events as needed).
- Copy the webhook signing secret into `STRIPE_WEBHOOK_SECRET`.

4) How the flow works

- User posts from `order/checkout.php` to `order/create_checkout.php`.
- Server computes authoritative totals and writes a pending file `order/pending_{id}.json`.
- Server creates a Stripe Checkout Session with `metadata[pending_id]` and redirects user to Stripe.
- When Stripe confirms payment, it sends `checkout.session.completed` to `order/stripe_webhook.php`. The webhook finalizes the order using the pending file (safe server-side confirmation).
- The user is also redirected back to `order/checkout_success.php` which will finalize using session data if webhook did not run yet.

5) Testing locally

- Use the Stripe CLI to forward webhooks locally:

```powershell
stripe listen --forward-to http://localhost:8800/order/stripe_webhook.php
```

- Create a test Checkout session from the site and complete payment with Stripe test cards (e.g., `4242 4242 4242 4242`).
