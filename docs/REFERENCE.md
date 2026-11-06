# Reference

## Actions & Filters

### Actions

#### `pratiks_login_shield_ip_locked`
* **Type**: Action
* **Arguments**: `(string $ip, string $username, int $locked_until)`
* **Description**: Fires immediately when an IP address exceeds the maximum failed login threshold and is locked out.

```php
add_action( 'pratiks_login_shield_ip_locked', function( $ip, $username, $locked_until ) {
    error_log( "IP {$ip} locked until " . date( 'Y-m-d H:i:s', $locked_until ) . " targeting user {$username}" );
}, 10, 3 );
```

---

## PHP Constants

#### `PRATIKS_LOGIN_SHIELD_DISABLE`
* **Type**: Boolean
* **Default**: `false`
* **Description**: Place `define( 'PRATIKS_LOGIN_SHIELD_DISABLE', true );` in `wp-config.php` to immediately disable all custom login slug rewriting and 404 redirection without altering your database settings.

---

## WP-CLI Commands

```bash
# Check current custom login URL, rate limiting settings, and active locked IPs
wp pratiks-login-shield status

# Unlock a specific IP address immediately
wp pratiks-login-shield unlock 192.168.1.100

# Emergency reset: clear custom login slug, restore default /wp-login.php, and remove all IP lockouts
wp pratiks-login-shield reset
```

---

## Abilities API (WordPress 6.9+ / Autonomous AI Agents)

Category: `security`

#### `pratiks-login-shield/get-status`
* **Type**: Read-only
* **Permission**: Requires `manage_options`
* **Description**: Returns current plugin settings, custom URL configuration, and array of currently locked IP addresses.

#### `pratiks-login-shield/unlock-ip`
* **Type**: Destructive
* **Permission**: Requires `manage_options`
* **Description**: Unlocks a specific IP address by removing it from the active lockouts list.
