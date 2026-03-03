<?php
/**
 * Stripe configuration — MedTravel
 *
 * Copy this file to config/stripe.php and fill in real values.
 * This file is already in .gitignore (the config/ directory should be).
 *
 * To go live:
 *   1. Replace sk_test_ with sk_live_ and whsec_ from your Stripe Dashboard.
 *   2. Add the webhook URL to Stripe Dashboard → Webhooks:
 *      https://medtravel.com.co/api/stripe_webhook.php
 *   3. Make sure composer require stripe/stripe-php has been run, or that
 *      lib/stripe/init.php exists.
 */

// ── Secret key ────────────────────────────────────────────────────────────────
// Leave empty to disable Stripe; payments will be created as status=draft.
if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY', '');   // e.g. sk_test_4eC39Hq...
}

// ── Publishable key (used in front-end JS if needed) ─────────────────────────
if (!defined('STRIPE_PUBLISHABLE_KEY')) {
    define('STRIPE_PUBLISHABLE_KEY', '');  // e.g. pk_test_TY...
}

// ── Webhook signing secret ────────────────────────────────────────────────────
// From Stripe Dashboard → Webhooks → your endpoint → Signing secret
// Leave empty to reject all incoming webhook calls (503 response → Stripe retries).
if (!defined('STRIPE_WEBHOOK_SECRET')) {
    define('STRIPE_WEBHOOK_SECRET', ''); // e.g. whsec_...
}
