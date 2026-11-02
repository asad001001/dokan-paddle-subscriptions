# Dokan Paddle Vendor Subscriptions

A WordPress plugin that lets marketplace vendors on **Dokan Pro** buy and manage their vendor subscription packs through **Paddle Billing** instead of the default gateways.

## Features

- **Auto-sync packs to Paddle** – saving a Dokan subscription pack creates the matching Paddle product and price (recurring or one-time, with optional trial).
- **Vendor dashboard page** – `Dashboard → Subscription` shows the current pack, expiry date and available plans, with Paddle.js v2 overlay checkout.
- **Signed webhooks** – `POST /wp-json/dpvs/v1/webhook` verifies the `Paddle-Signature` header (HMAC-SHA256 + replay window) before activating, renewing or cancelling a vendor's pack.
- **Self-service cancellation** – vendors cancel from the dashboard; access stays until the end of the billing period.
- **Admin settings** – sandbox/live mode, API key, webhook secret and client-side token.

## Requirements

| Dependency | Version |
|---|---|
| WordPress | 6.0+ |
| PHP | 7.4+ |
| WooCommerce | latest |
| Dokan Pro | with the *Vendor Subscription* module enabled |
| Paddle | Billing account (sandbox or live) |

## Installation

1. Copy the `dokan-paddle-subscriptions/` folder into `wp-content/plugins/`.
2. Activate **Dokan Paddle Vendor Subscriptions** in *Plugins*.
3. Open **Paddle Subscriptions** in the admin menu and fill in:
   - **Mode** – Sandbox while testing, Live in production
   - **API Key** – Paddle → Developer tools → Authentication
   - **Client-side token** – same screen, used by Paddle.js
   - **Webhook secret** – shown when you create the notification destination below
4. In Paddle → *Notifications*, add a destination `https://your-site.com/wp-json/dpvs/v1/webhook` and subscribe to `subscription.activated`, `subscription.trialing`, `subscription.updated` and `subscription.canceled`.
5. Edit and save each Dokan subscription pack once so it is created in Paddle.

## Project structure

```
dokan-paddle-subscriptions/
├── dokan-paddle-subscriptions.php        # Plugin bootstrap
├── includes/
│   ├── class-plugin.php                  # Singleton, wires everything together
│   ├── class-admin-settings.php          # Settings page + options storage
│   ├── class-paddle-client.php           # Minimal Paddle Billing REST client
│   ├── class-vendor-subscription-gateway.php  # Pack sync + subscription lifecycle
│   ├── class-dashboard-ui.php            # Vendor dashboard menu, page, cancel action
│   └── class-webhook-handler.php         # Signed webhook endpoint
├── templates/
│   └── vendor-subscription-status.php    # Dashboard page markup
└── assets/js/
    └── vendor-subscriptions.js           # Opens Paddle checkout
```

## How it works

```
Admin saves pack ──► Paddle product + price created, IDs stored in post meta
Vendor clicks plan ─► Paddle.js checkout (customData: user_id, pack_id)
Paddle webhook ────► signature verified ─► Dokan pack activated / renewed / cancelled
```

## Notes

- Prices are sent to Paddle in **USD**, converted to the lowest unit (cents) as Paddle requires.
- Editing a pack's price after it has synced does not update Paddle yet (Paddle prices are immutable; a new price would need to be created).

## License

GPL-2.0-or-later
