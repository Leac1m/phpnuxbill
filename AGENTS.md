# PHPNuxBill — Agent Guide

## Project

PHP Mikrotik billing system (hotspot/PPPoE). Custom PHP app, **no framework**. Smarty 4.5.3 templates, Idiorm ORM, MySQL.

## Entry points

- `index.php` → `system/boot.php` → `system/init.php` — main web app
- `system/api.php` — REST API (sets `$isApi = true` before including `init.php`)
- `system/cron.php` — CLI cron job (expiry, auto-renewal, router monitoring)
- `radius.php` — FreeRADIUS HTTP endpoint (auth/acct/authorize)
- `update.php` — self-update from GitHub
- `admin/index.php` — redirects to `?_route=admin/`

## Routing

URL param `?_route=` maps directly to `system/controllers/{handler}.php`. If `_route` is absent, the request URI path is parsed instead (clean URLs). `admin/` sub-route goes to `controllers/admin.php` then switches on `$routes[1]`.

Clean URLs (`/customers`, `/settings/app?foo=bar`) work via `.htaccess` — Apache rewrites non-file requests to `index.php` with `QSA`. Both `?_route=` and clean URLs resolve through the same path in `boot.php`.

Default route is `controllers/default.php` which redirects based on auth state.

## Auth & access

- Admin session: `$_SESSION['aid']` — use `_admin()` to guard
- Customer session: `$_SESSION['uid']` — use `_auth()` to guard
- CSRF handled by `Csrf::generateAndStoreToken()` / `Csrf::check()` — required on all POST forms
- Admin levels: SuperAdmin, Admin, Report, Agent, Sales (stored in `user_type`)

## Input helpers

- `_post($key)`, `_get($key)`, `_req($key)` — sanitized input
- `r2($url, $type, $msg)` — redirect with flash notification
- `_alert($text, $type, $url)` — render alert then die
- `_log($description, $type, $userid)` — audit log
- `Lang::T($key)` — translation lookup (JSON files in `system/lan/`)

## Key structures

| Path | Purpose |
|---|---|
| `system/controllers/` | Route handlers, one file per route name |
| `system/autoload/` | Custom class autoloader (spl_autoload_register), PEAR2 |
| `system/devices/` | Router drivers (MikrotikHotspot, MikrotikPppoe, Radius, RadiusRest, Dummy) |
| `system/paymentgateway/` | Payment gateway integrations |
| `system/plugin/` | Plugin system with `register_menu()`, `register_hook()` |
| `system/widgets/` | Dashboard widgets |
| `system/lan/*.json` | Translation files (english.json is default) |
| `ui/ui/admin/`, `ui/ui/customer/` | Smarty templates |
| `ui/ui_custom/` | Template overrides (gitignored) |
| `ui/themes/` | Theme templates (gitignored) |

## Hooks & plugins

- `register_hook('hook_name', 'function_name')` in `Hookers.php`
- `register_menu($name, $isAdmin, $function, $position, $icon, $label, $color, $auth)` — menu positions are `AFTER_DASHBOARD`, `CUSTOMERS`, `PREPAID`, `SERVICES`, `REPORTS`, `VOUCHER`, `AFTER_ORDER`, `NETWORK`, `SETTINGS`, `AFTER_PAYMENTGATEWAY` (admin); `AFTER_DASHBOARD`, `ORDER`, `HISTORY`, `ACCOUNTS` (customer)

## Setup & deps

- Copy `config.sample.php` → `config.php`, configure DB
- Run `install/phpnuxbill.sql` to create schema; `radius.sql` for FreeRADIUS
- Dependencies in `system/composer.json` (run `composer install` from `system/`)
- Root `composer.json` is just metadata — actual vendor deps live in `system/vendor/`
- Min PHP 8.2, requires PDO, MySQLi, GD, CURL, ZIP, Mbstring

## Docker

| File | When to use |
|---|---|
| `docker-compose.yml` | Dev — app + MySQL + FreeRADIUS (PHP 7.4) |
| `docker-compose.test.yml` | Testing without a domain — app + MariaDB + cron on HTTP port 80 (PHP 8.2) |
| `docker-compose.prod.yml` | Production — adds Caddy reverse proxy with auto Let's Encrypt (PHP 8.2) |
| `Dockerfile.prod` | PHP 8.2 Apache image used by test/prod compose files |
| `Caddyfile` | Caddy config for reverse proxy + TLS |

Test/prod compose files need a `.env` file with `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`. Run with:
```
docker compose -f docker-compose.test.yml up -d
```

## Commands

| Command | Purpose |
|---|---|
| `php system/cron.php` | Run cron (expiry, auto-renewal, router monitoring) |
| `http://host/?_route=...` | Web access via URL |

## API (`system/api.php`)

- Token auth: `{type}.{user_id}.{timestamp}.{sha1}` where `sha1 = sha1("$uid.$time.$api_secret")`) — or use `api_key` config value for SuperAdmin access
- Routes use same `controllers/` files but with `$isApi = true`
- Responses are JSON via `showResult()`

## DB / ORM quirks

- Idiorm fork in `system/orm.php`; uses `ORM::for_table('tbl_*')` exclusively
- Table prefix: `tbl_` (e.g., `tbl_customers`, `tbl_user_recharges`, `tbl_plans`)
- FreeRADIUS DB connection configured separately via `$radius_*` config vars
- `$db_password` / `$db_pass` legacy compatibility — both are read

## Gitignored dirs (don't commit changes to these)

`config.php`, `pages/`, `ui/themes/*`, `ui/ui_custom/*`, `system/cache/*`, `system/plugin/*`, `system/paymentgateway/*`, `system/uploads/*` (except defaults), `system/devices/*` (except defaults), `docs/` (except `.html` and `.md`)

## Other

- Lint, typecheck, and CI are currently not set up.
- **Testing:** We now encourage testing for critical workflows (e.g., auto-provisioning, billing, IP allocation). **Important:** To prevent environment issues (like missing PHP extensions), tests that can be run inside Docker containers *must* be run using Docker, rather than installing dependencies on the host machine.
  - **PHPUnit:** Placed in `tests/Unit/` and `tests/Integration/`. Run via Docker: `docker exec -it nuxbill-app bash -c "./phpunit.phar"` (or install composer in the container).
  - **Node.js (Jest):** Used for sidecar APIs (e.g., `wireguard-api/tests/`). Run within the sidecar container environment.
  - **E2E (Playwright):** Placed in `tests/e2e/`. Use for testing UI polling and complex admin flows.
- `PRODUCT.md` has brand/design guidance for UI work
- Config setting `$_app_stage = 'Live'` controls error display
- Voucher activation flow: `controllers/login.php` handles both voucher-only and voucher-with-registration
- `update.php` pulls from GitHub master zip — configurable via `$update_url`
