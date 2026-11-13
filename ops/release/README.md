# Production release

The full go-live runbook (steps, owners, checks, rollback) is the shared
"Elvane Go-Live Runbook" doc. This folder holds the script it uses:

- `golive-settings.php` — the store settings kept in the database (tax rounding,
  stock display, Analytics, Terms page, photo zoom/slider, side-cart auto-open,
  search prices, plugins to deactivate). Dry run by default; `apply` saves the
  old values to /root/backups/golive-settings-<time>.json first.

      wp eval-file golive-settings.php          # what would change
      wp eval-file golive-settings.php apply    # change it

Release notes: `CHANGELOG.md` (2.0.0).

Other release scripts: `ops/elementor/build_templates.py` + `install.php`
(header, footer, home page, menu, logo), `ops/banners/seed.php` (first home
banners), `ops/content/legal/` (policy texts), `ops/tests/smoke.sh <url>`.
