# Elvane Clothing – WordPress code

Version control for **Elvane's own code** on elvaneclothing.com (WooCommerce).

## What is in git (and what is not)

| In git | Not in git (why) |
|---|---|
| `wp-content/themes/innovecouture-child/` – all design/code changes | WordPress core (installed on the server) |
| `wp-content/plugins/elvane-*` – Elvane's custom plugins | Third-party & premium themes/plugins (licences; versions listed in `ops/manifest/`) |
| `wp-content/mu-plugins/` – must-use plugins for every environment | `wp-content/uploads/` (product images – backed up separately) |
| `ops/dev-only/` – helpers that run **only on dev** (e.g. email blocker) | The database, `wp-config.php`, API keys (secrets) |
| `ops/deploy/` – the server deploy script | |

Never edit the parent theme `innovecouture` – updates would overwrite it. Put every change in the child theme.

> Elementor layouts, menus, products and plugin settings are stored in the **database**, not in git.
> When a PR needs such changes, list them in the PR description so they can be repeated on live.

## Branches and environments

| Branch | Environment | Deploy |
|---|---|---|
| `develop` | Dev – http://167.71.233.143 | Automatic on every push (GitHub Actions) |
| `main` | Live – https://elvaneclothing.com | **Manual only**, set up at go-live |

Workflow: branch from `develop` → open a PR into `develop` → CI checks → merge → auto-deploy to dev → test → when approved, PR `develop` → `main`.

## Deploy

`.github/workflows/deploy-dev.yml` uploads the repo to the server and runs
`/usr/local/bin/elvane-deploy dev`, which:

1. PHP-lints everything,
2. mirrors only the Elvane-owned paths above into `wp-content`,
3. on dev, adds the dev-only mu-plugins (on live it removes them),
4. flushes WordPress and Elementor caches,
5. keeps the previous version in `/var/backups/elvane-deploy/<timestamp>` for rollback.

Required GitHub secrets (Settings → Secrets and variables → Actions, environment `dev`):
`DEV_HOST`, `DEV_SSH_KEY`, `DEV_KNOWN_HOSTS`.

## CI

`.github/workflows/ci.yml` runs on every PR/push: PHP 8.3 syntax check, deploy-script check, and a guard that fails if secrets, SQL dumps or archives are committed.

## Manifests

`ops/manifest/plugins.csv` and `ops/manifest/themes-core.txt` record the exact plugin/theme/core versions on dev. Refresh them after installing or updating anything.
