# One-Time Admin Promoter

A single-site WordPress plugin that promotes one existing user to Administrator, copies that user's current email to **Settings > General > Administration Email Address**, and deactivates itself.

The handover runs on plugin activation. This version has no handover form or settings screen.

## Requirements

- WordPress 6.0 or later and PHP 7.2 or later.
- A single-site installation; Multisite and network activation are refused.
- A signed-in operator with permission to activate plugins, promote users, manage site settings, and edit and promote the target user.
- An existing target account with a valid email address.

## Installation and use

1. Back up the site/database. Confirm the intended account exists under **Users** and has the correct email address.
2. Before activating the plugin, add the following to `wp-config.php`, before the stop-editing line. Replace `existing_login` with the exact, case-sensitive username—not a display name or email address:

   ```php
   define( 'OTAP_TARGET_USERNAME', 'existing_login' );
   ```

3. Place this folder in `wp-content/plugins/`, or ZIP the `one-time-admin-promoter` folder and install it through **Plugins > Add New Plugin > Upload Plugin**.
4. Sign in as an authorized administrator and activate **One-Time Admin Promoter** through the normal WordPress Plugins screen. Activation immediately performs the handover; there is no separate confirmation form.
5. Verify the target's Administrator role under **Users**, the administration email under **Settings > General**, and the plugin's inactive state under **Plugins**.
6. Delete the inactive plugin and remove the `OTAP_TARGET_USERNAME` definition when convenient. Neither action reverses the handover or removes the attempt record.

This plugin cannot bypass permissions imposed by a host or agency. It requires configuration-file access and does not create accounts, request passwords, change passwords, or log in as the target.

## What changes

- The target user's existing roles are replaced with Administrator.
- The site administration email changes immediately, without an email confirmation link. Older pending administration-email changes are cleared.
- Other administrators keep their access. WordPress has no primary or main administrator role.
- Other accounts, content ownership, and plugin-specific notification settings are unchanged.

WordPress may notify the old address about the administration-email change. The new address is a one-time copy: later profile email changes do not synchronize automatically. Contact forms, commerce, security, and other plugins may use separate recipients. This plugin does not test mail delivery.

## One-time execution and recovery

The non-autoloaded option `otap_completed_v1` stores the status, target user ID, operator ID, and UTC timestamp. The plugin claims this record before changing the role to prevent concurrent or repeated attempts. It deliberately retains the record after plugin deletion; reinstalling or changing the configured username does not enable another run.

An interrupted or failed attempt after the claim also blocks retries. Role and email updates are not a database transaction, so a failure can leave a partial handover. Inspect both settings manually and restore the prior role/email if necessary. No reset control is supplied.

The plugin schedules self-deactivation at shutdown after a claimed attempt, with a fallback on a later authorized admin request. Validation failures before the claim do not consume the one-time operation.

## Security and validation

The plugin checks operator capabilities, an exact existing username, the target's email address, and the availability of the Administrator role before claiming an attempt. The normal WordPress Plugins activation flow supplies the activation nonce check. Direct file execution is blocked.

There is no bundled automated test suite. PHP syntax checking does not verify a live handover; verify the resulting role, site email, and inactive plugin state on the intended installation.

## License

GPL-2.0-or-later, as declared in the plugin header.
