<?php
/**
 * Local Stripe TEST MODE configuration (copy to stripe_local.php).
 *
 * 1. Copy this file to stripe_local.php in the same directory (_base.php lives here).
 * 2. Paste your TEST keys from Stripe Dashboard / Stripe CLI (never commit stripe_local.php).
 * 3. Do not use live keys (sk_live_...) for local development.
 *
 * Keys:
 * - STRIPE_SECRET_KEY: Developers → API keys → Secret key (test mode) → sk_test_...
 * - STRIPE_WEBHOOK_SECRET: From `stripe listen` output (whsec_...) for local testing,
 *   or from Dashboard → Webhooks → signing secret for a deployed endpoint.
 */

if (!function_exists('putenv')) {
    return;
}

// Replace the placeholders below with your own test values (keep quotes).
putenv('STRIPE_SECRET_KEY=sk_test_REPLACE_ME');
putenv('STRIPE_WEBHOOK_SECRET=whsec_REPLACE_ME');
