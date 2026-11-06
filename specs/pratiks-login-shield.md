# Specification: Pratiks Login Shield (`pratiks-login-shield`)

## 1. Overview
WordPress websites are under perpetual brute-force automated login attacks. Automated bots continuously hit `/wp-login.php` attempting credential stuffing and dictionary attacks, exhausting server resources and risking account takeover.
While plugins like WPS Hide Login have 2M+ active installs, they suffer from critical flaws:
1. No rate limiting or lockout mechanism (they only rename the login page; once discovered or if XML-RPC is probed, attackers can attack unimpeded).
2. Users frequently lock themselves out if they forget their custom slug or if an .htaccess/rewrite breaks, requiring cPanel/FTP access to rename plugin folders.
3. Abandoned alternatives like Limit Login Attempts (300k+ active installs) are out of date and lack custom login URL protection.

**Pratiks Login Shield** provides an all-in-one, zero-dependency, ultra-secure login protection shield combining:
1. **Custom Login URL**: Renames and hides `/wp-login.php` with a user-defined slug, returning 404 or redirecting unauthorized direct access to `wp-login.php`.
2. **Brute Force Rate Limiting**: Tracks failed authentication attempts by client IP with customizable threshold (default: 4 attempts) and lockout window (default: 20 minutes). Displays friendly warnings with remaining attempts count.
3. **Anti-Lockout Emergency Key & WP-CLI Recovery**:
   - A secret rescue key (`?shield_rescue=<token>`) configured in settings, allowing instant bypass if the custom slug is forgotten.
   - WP-CLI command: `wp pratiks-login-shield reset` to restore default settings and clear all lockouts.
   - Environment constant bypass: `define( 'PRATIKS_LOGIN_SHIELD_DISABLE', true );`.
4. **XML-RPC Multicall Protection**: Optional toggle to block XML-RPC authentication and multicall attacks.
5. **Dashboard Management**:
   - Admin settings under **Settings > Login Shield**.
   - Active Lockouts table with 1-click IP unlocking.
   - Emergency Rescue URL with 1-click clipboard copy.

---

## 2. Technical Architecture

### 2.1 File Structure
```
pratiks-login-shield/
├── pratiks-login-shield.php      # Main plugin bootstrap
├── src/
│   ├── Plugin.php                # Master orchestrator
│   ├── Auth/
│   │   ├── LoginUrlHandler.php   # Custom login URL rewrites and wp-login.php interception
│   │   ├── RateLimiter.php       # Failed attempts tracking, IP lockout, and notices
│   │   └── XmlRpcBlocker.php     # XML-RPC protection
│   ├── Settings/
│   │   └── SettingsPage.php      # Settings page under Settings > Login Shield
│   ├── Cli/
│   │   └── Command.php           # WP-CLI commands (`wp pratiks-login-shield`)
│   ├── Abilities.php             # WordPress Abilities API declarations
│   └── Privacy.php               # WP Privacy Exporter and Eraser
├── tests/
│   ├── Unit/                     # PHPUnit tests
│   └── e2e/                      # Playwright E2E tests
└── uninstall.php                 # Cleanup all options and transients on deletion
```

### 2.2 Data Model
All plugin configuration is stored under a single option: `pratiks_login_shield_settings`:
- `login_slug`: string (sanitized title, default empty/disabled)
- `max_attempts`: int (positive integer, default 4)
- `lockout_duration`: int (lockout duration in minutes, default 20)
- `disable_xmlrpc`: bool (default false)
- `rescue_key`: string (64-char hex token generated on activation or regeneration)

Active lockouts and failed attempts are stored in:
- `pratiks_login_shield_attempts`: transient / option storing array: `[ ip => [ 'count' => int, 'first_attempt' => int, 'last_attempt' => int ] ]`
- `pratiks_login_shield_lockouts`: option storing array: `[ ip => [ 'locked_until' => int, 'attempts' => int, 'last_user' => string ] ]`

### 2.3 Custom Login URL Mechanism
1. When `login_slug` is set (e.g. `secret-portal`):
   - Requests directly to `/wp-login.php` without valid actions (or without `shield_rescue` token) are intercepted in `wp_loaded` or `init`, returning a 404 response or redirecting to home.
   - Requests to the custom slug `/secret-portal` are intercepted in `init` or `parse_request`:
     - Sets `$GLOBALS['pagenow'] = 'wp-login.php'`.
     - Includes `ABSPATH . 'wp-login.php'`.
     - Exits.
   - Filters:
     - `login_url`: filters returned URL to point to custom slug.
     - `site_url` and `network_site_url`: rewrites `wp-login.php` references to the custom slug.
     - `wp_redirect`: rewrites login redirects.
     - `lostpassword_url`, `register_url`: properly preserved or handled.
2. Anti-Lockout Emergency Bypass:
   - If user visits `/wp-login.php?shield_rescue=TOKEN`, bypass is authorized, cookie or session-less access to login screen is granted.
   - If constant `PRATIKS_LOGIN_SHIELD_DISABLE` is defined as true, all shielding and redirects are bypassed.

### 2.4 Rate Limiting & Lockout
1. `authenticate` filter (priority 30):
   - Check if current client IP is locked out in `pratiks_login_shield_lockouts`.
   - If locked out, return `WP_Error( 'shield_locked_out', ... )` displaying remaining lockout minutes.
2. `wp_login_failed` action:
   - Increment failed attempts for client IP.
   - If attempts >= `max_attempts`, record lockout in `pratiks_login_shield_lockouts` with `locked_until = time() + ( lockout_duration * 60 )`, and clear attempts.
   - If attempts < `max_attempts`, display warning on next login form render showing remaining attempts: `WP_Error( 'shield_attempt_failed', 'Invalid credentials. You have X attempts remaining before lockout.' )`.
3. `wp_login` action:
   - On successful authentication, clear failed attempts record for this IP.

### 2.5 WP-CLI Commands
- `wp pratiks-login-shield status`: shows current slug, lockout counts, active lockouts.
- `wp pratiks-login-shield reset`: clears custom login slug, clears all active lockouts and attempts.
- `wp pratiks-login-shield unlock <ip>`: unlocks a specific IP.

### 2.6 Accessibility & Coding Standards
- Zero external dependencies.
- WordPress + VIP Coding Standards clean (`phpcs`).
- PHPStan Level 6 clean.
- PHPUnit unit tests covering rate limiter, settings sanitization, URL rewrites, and XML-RPC blocking.
- Playwright E2E tests covering login flow, custom URL access, lockout after threshold, rescue key bypass, WP-CLI reset, and axe-core a11y.
