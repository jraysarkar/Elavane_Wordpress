# Elvane Store (plugin)

Store logic that used to live in the Mixtas theme's `functions.php`, moved
into a plugin so it keeps working whatever theme is active.

| File | What it does |
|---|---|
| `includes/phone-login.php`, `assets/phone-login.*` | Phone OTP login popup (Firebase). The server verifies Firebase's ID token with Google before logging anyone in. |
| `includes/account.php` | Account, checkout and wishlist require login (popup, then back to the page). "Forgot Password?" wording. |
| `includes/checkout.php`, `assets/checkout-payment.js` | Delivery: COD ₹100 incl. GST, Razorpay free — shown live in the checkout block. |
| `includes/checkout.php`, `assets/buy-now.js` | "Buy now": only this item, straight to checkout (works with variations). |
| `includes/checkout.php` | WELCOME200 first order only; name/email copied from the order to the customer; thank-you text. |
| `includes/gst-labels.php` | Order tax lines named CGST 2.5% / SGST 2.5% or IGST 5% (the GST plugin leaves them as "Tax"); admin "Recalculate" uses the order's own state. Existing orders: `wp elvane gst-labels` (`--dry-run` first). |

## Setup

Firebase web config is stored in the database, not in git:

```bash
wp option update elvane_firebase_config '{"apiKey":"…","authDomain":"…","projectId":"…","storageBucket":"…","messagingSenderId":"…","appId":"…"}' --format=json
```

The site's domain must be listed under Firebase console → Authentication →
Settings → Authorized domains. Without the option set, the popup and the
required-login redirects are switched off (so nobody can be locked out).

Replaces the older `elvane-payment-based-delivery` plugin — deactivate it.
