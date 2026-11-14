# Changelog

## 2.0.1 – Fixes after go-live (14 Nov 2026)

Found while testing the live store end to end (orders, emails, iCarry, admin).
Live and dev both run this version.

### For customers
- My account → Wishlist opens the wishlist; it used to jump to the Orders list.
- Logging out goes to the home page. Before, it opened the login popup aimed
  at My account, so logging in again landed on Orders.
- Product page (computer): the Shop menu stays open over the product photo;
  the photo zoom used to take over and close the menu.
- Header (computer) shows "My account" instead of "Login" once logged in.
- Checkout on phones: "Order summary" lines up with the total.
- Order emails: Elvane's black design and footer ("Elvane Clothing ·
  Jalpaiguri, West Bengal · elvaneclothing.com · info@elvaneclothing.com")
  instead of the red design and the old agency credit; closing line "Thank you
  for shopping with Elvane."

### For the admin
- An order no longer overwrites the name or email of an account that already
  has them (it had renamed an admin to the order's name). New phone-login
  accounts still get their name and email from their first order.

### Technical
- `ops/release/email-branding.php`: restyles the YayMail templates (dry run /
  `apply` with a backup of the old templates).
- Settings changed on live, not in code: sender Elvane Clothing
  <noreply@elvaneclothing.com> with Reply-To info@, admin login at
  /management-area (WPS Hide Login), Shiprocket off, iCarry connector
  reconnected.

### Known, not fixed
- A struck-through price can show one paisa more (e.g. ₹1,699.01) for
  logged-in customers outside West Bengal; the amount charged is correct.

## 2.0.0 – Elvane Clothing v2 (live since 13 Nov 2026)

The redesigned store. Version 1 is the current live site on the Mixtas theme;
version 2 replaces it with the Innovecouture child theme and the `elvane-store`
plugin, tested on https://dev.elvaneclothing.com from 28 Oct to 13 Nov 2026.
Products, categories, customers and orders stay as they are on live: only the
code, theme, plugins and some settings change.

How to release it: the "Elvane Go-Live Runbook" doc and `ops/release/`.

### For customers

**New look**
- New design and logo, modelled on a minimal fashion-label style: large photo
  banners, 2:3 product cards with gaps, clean type, light scroll animations
  (turned off for visitors who ask for reduced motion).
- Rotating announcement bar and a moving word strip on the home page.
- Footer with Instagram, Facebook, Privacy policy and Terms only; it stays at the
  bottom on short pages.
- Readable Privacy policy, Terms and About pages on phones and desktop.

**Phones**
- Shop-style header: menu and search on the left, logo in the centre, account and
  bag (with item count) on the right.
- Product photos swipe left and right, without a thumbnail strip or counter.
- Home banners stacked full screen, including the Cargo / Polos pair.
- Scrolling account tabs, mobile order cards, full-screen search and menu panel.

**Shopping**
- Header Shop menu built from the product categories, sub-categories in capitals.
- Live search with product photos and prices (FiboSearch).
- Colour swatches with the matching photos; zoom on hover and a full-screen
  photo viewer.
- No "Sale" badge and no "8 in stock" line (only "Out of stock").
- Add to cart opens the side cart straight away, with no "View cart" bar.
- Wishlist (WPC Smart Wishlist) with a heart on every product.
- Buy now goes straight to checkout; guests log in first, on the same page.

**Login and account**
- Login with mobile number and a one-time code (Firebase, checked on the server);
  existing customers are found by their phone number. An optional password can
  be set.
- The login popup opens in place for wishlist, checkout and Buy now, and returns
  the customer to the page they came from.
- My account opens on Orders; each order shows the courier, tracking number and
  shipment status.

**Checkout and payment**
- Cleaner checkout: one summary panel, aligned rows, square option boxes.
- Delivery charge by payment method (COD ₹100, prepaid free), set by the admin
  and shown before the order is placed; a loader while totals update.
- Razorpay pay page with a clear message when a payment is not completed.
- Only one coupon per order; coupons can be limited to a customer's first order.
- GST shown as CGST + SGST (West Bengal) or IGST, adding up exactly to the
  checkout total.
- Shorter order-complete page (the invoice has the details).
- Gift card box and WhatsApp button removed.

**Legal**
- Elvane's own Privacy Policy and Terms and Conditions (IT Act, DPDP Act 2023,
  Consumer Protection (E-Commerce) Rules 2020), with the Grievance Officer's
  details. A few points (dates, delivery days, returns) are still to be
  confirmed before release.

### For the admin

Everything customer-facing is managed in WordPress, not in code:

- **Banners → Home banners:** home page banners (photo or MP4 video, full or half
  width, link to a category or any address, button text, order).
- **Products → Categories:** category banner photo or video; categories also
  build the Shop menu.
- **Banners → Site settings:** announcement bar, social links, "New arrivals"
  section, word strip, login popup photo, COD and prepaid delivery charges.
- **Marketing → Coupons:** "First order only" box.
- **Appearance → Customize → Site identity:** logo.
- **Orders:** iCarry shipment card, shipment status dropdown and an hourly
  status sync with iCarry's tracking.
- Analytics count every order (including unpaid COD) and update immediately.

Full guide: the "Elvane Admin Guide" doc.

### Technical

- Code in git: child theme `innovecouture-child` 2.0.0, plugin `elvane-store`
  2.0.0. Deploy with `/usr/local/bin/elvane-deploy` (lint, sync, cache flush,
  rollback copy).
- Header, footer, home page and menu generated by `ops/elementor/`; first home
  banners by `ops/banners/seed.php`.
- Database settings by `ops/release/golive-settings.php` (dry run, or `apply`
  with a backup of the old values): tax rounding, stock display, Analytics, Terms
  page, store address (Jalpaiguri, West Bengal), photo zoom/slider, side-cart
  auto-open, search prices, plugins to switch off.
- Theme and plugin versions to install: `ops/manifest/`. New on live:
  Innovecouture 44 + child, Vamtam Elementor integration, FiboSearch, WPC Smart
  Wishlist. Updated: WooCommerce 11.2.1, Elementor 4.3.4, Smart Variations
  Images 5.2.35, Razorpay 4.8.8, Side Cart 2.8.2, YITH Order Tracking 2.51.0.
- Smoke test: `ops/tests/smoke.sh <site>`.

### Not in this release

- GST changes from the CA's answers (18% on the delivery charge, GST per piece,
  credit notes, 18% above ₹2,500).
- Contact Us and FAQs content (pages still hold theme sample text).
- A ₹0.01 struck-through price that can appear in the cart from GST rounding.
