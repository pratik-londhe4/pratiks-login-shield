# Pratiks Login Shield

[![E2E Tests](https://github.com/pratik-londhe4/pratiks-login-shield/actions/workflows/e2e-tests.yml/badge.svg)](https://github.com/pratik-londhe4/pratiks-login-shield/actions)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D%206.6-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D%207.4-8892BF.svg)](https://php.net/)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE.md)

Protect WordPress from automated brute-force attacks with custom login URLs, IP rate-limiting lockouts, XML-RPC shielding, and bulletproof emergency anti-lockout recovery.

![Login Shield Walkthrough](.github/media/demo.gif)

---

## Try It in WordPress Playground

Test **Login Shield** instantly in your browser without installing anything locally:

[![Try in WordPress Playground](https://img.shields.io/badge/Playground-Test%20Live-2271b1?style=for-the-badge&logo=wordpress)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/pratik-londhe4/pratiks-login-shield/main/blueprint.json)

---

## Key Features

- **Custom Login URL**: Mask `/wp-login.php` behind any secret slug (e.g. `https://example.com/secret-portal/`). Direct requests to `/wp-login.php` return a standard `404 Not Found`.
- **Brute Force Rate Limiting**: Tracks failed authentication attempts by client IP. When failed attempts reach the threshold (default: 4), the IP is temporarily locked out (default: 20 minutes).
- **Anti-Lockout Emergency Key**: Unique rescue URL token generated per installation (`https://example.com/wp-login.php?shield_rescue=...`) to ensure administrators never get locked out if a slug is forgotten.
- **XML-RPC Protection**: Optional one-click toggle to disable XML-RPC methods and remove `X-Pingback` headers, stopping multicall amplification attacks.
- **Full WP-CLI Integration**:
  - `wp pratiks-login-shield status`
  - `wp pratiks-login-shield reset`
  - `wp pratiks-login-shield unlock <ip>`
- **Active Lockouts Dashboard**: View currently locked IPs, targeted usernames, and remaining lockout time with 1-click IP unlocking under **Settings > Login Shield**.
- **Privacy & GDPR Compliant**: Failed attempt logs and IP lockouts automatically expire and clear. Full integration with WordPress Core Personal Data Exporter and Eraser.

---

## Anti-Lockout Guarantees

Getting locked out of your own site is the #1 pain point of traditional login hiding plugins. Login Shield guarantees 3 distinct ways to regain access:

1. **Rescue URL**: Visit your secret emergency URL bookmark (`/wp-login.php?shield_rescue=<secret_key>`).
2. **WP-CLI Reset**: Run `wp pratiks-login-shield reset` from terminal to immediately disable custom login URLs and clear all lockouts.
3. **Environment Bypass**: Add `define( 'PRATIKS_LOGIN_SHIELD_DISABLE', true );` to `wp-config.php`.

---

## Requirements

- WordPress 6.6 or higher
- PHP 7.4 or higher
- Zero external PHP or JavaScript runtime dependencies

---

## Development & Testing

```bash
# Install dependencies
composer install
npm install

# Run PHPUnit unit tests
./vendor/bin/phpunit

# Run code style & static analysis
./vendor/bin/phpcs
./vendor/bin/phpstan analyse --memory-limit=512M

# Start local Docker environment
npm run env:start

# Run Playwright E2E & accessibility tests
npm run test:e2e

# Run multi-version test matrix (oldest supported & latest WP/PHP)
bin/e2e-legs.sh

# Run WordPress Plugin Check
bin/plugin-check.sh
```

---

## License

GPL-2.0-or-later. See [LICENSE.md](LICENSE.md) for full terms.
