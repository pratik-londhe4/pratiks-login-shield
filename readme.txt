=== Pratiks Login Shield ===
Contributors: pratiklondhe
Tags: login, hide login, custom login url, brute force, security
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect WordPress from brute force attacks with custom login URLs, rate limiting lockouts, and emergency anti-lockout recovery.

== Description ==

WordPress websites are targeted around the clock by automated bots probing `/wp-login.php` with credential stuffing and brute-force attacks. These scans exhaust server resources, clutter error logs, and put user accounts at risk.

**Login Shield** gives your site comprehensive, modern protection with zero bloat and zero dependencies:

* **Custom Login URL**: Hide `/wp-login.php` behind a private slug (e.g. `https://example.com/secret-portal/`). Direct requests to `/wp-login.php` return a 404 Not Found error.
* **Brute Force Rate Limiting**: Automatically track failed login attempts by IP address. Violating IPs are temporarily locked out after exceeding your chosen threshold (default: 4 attempts for 20 minutes).
* **Anti-Lockout Emergency Key**: Never worry about getting locked out. Every site receives a secret rescue URL that instantly bypasses the shield if you forget your custom slug.
* **XML-RPC Multicall Protection**: Easily disable XML-RPC authentication to block XML-RPC multicall brute-force amplification attacks.
* **WP-CLI Management**: Inspect lockout status, unlock specific IP addresses, or perform an instant emergency reset directly from the command line (`wp pratiks-login-shield reset`).
* **Active Lockout Dashboard**: Review locked IP addresses, failed attempt counts, targeted usernames, and unlock any IP in one click from **Settings > Login Shield**.
* **Privacy & GDPR Compliant**: Stored IP lockouts expire and are automatically purged. Personal data export and erasure tools fully supported.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pratiks-login-shield` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings > Login Shield** to configure your custom login slug and rate limiting rules.
4. Bookmark your secret **Emergency Rescue URL** for safe keeping.

== Frequently Asked Questions ==

= What happens if I forget my custom login slug? =
You will never be locked out! Login Shield provides an **Anti-Lockout Emergency Key** shown on your settings screen (`https://example.com/wp-login.php?shield_rescue=...`). Visiting that rescue link bypasses the shield and allows you to log in normally. Alternatively, if you have SSH access, simply run `wp pratiks-login-shield reset`, or add `define( 'PRATIKS_LOGIN_SHIELD_DISABLE', true );` to `wp-config.php`.

= Does this plugin conflict with password-protected posts? =
No. Password-protected posts use `wp-login.php?action=postpass`, which Login Shield explicitly preserves and allows.

= What happens when an attacker is locked out? =
When an IP address exceeds the maximum number of failed login attempts, authentication requests from that IP are blocked with a clear warning showing the remaining lockout duration. Locked IPs automatically regain access when the lockout window expires, or an administrator can unlock them immediately in **Settings > Login Shield** or via `wp pratiks-login-shield unlock <ip>`.

== Screenshots ==

1. Login Shield settings screen with custom login URL, rate limiting configuration, and active IP lockouts table.
2. Custom login screen with remaining failed attempts counter.
3. Temporary IP lockout notice after exceeding maximum attempts.

== Changelog ==

= 0.1.1 =
* Fix: improve color contrast of emergency key button

= 0.1.0 =
* Initial release: Custom login URL routing, brute force rate limiting, emergency rescue key, XML-RPC blocker, WP-CLI commands, and Abilities API support.
