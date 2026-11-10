# One-Time Admin Promoter

A single-site WordPress plugin that promotes one existing user to Administrator, copies that user's current email to **Settings > General > Administration Email Address**, and deactivates itself.

Activate the plugin, then open **Tools > Admin Handover**, enter an existing username, and confirm the handover. Activation alone does not change any user or email setting. No FTP, configuration edit, or password is needed when installing through WordPress.

## Requirements

- WordPress 6.0 or later and PHP 7.2 or later.
- A single-site installation; Multisite and network activation are refused.
- A signed-in operator with permission to activate plugins, promote users, manage site settings, and edit and promote the target user.
- An existing target account with a valid email address.

## Installation and use

1. Back up the site/database. Confirm the intended account exists under **Users** and has the correct email address.
2. Sign in as an administrator with permission to install/activate plugins, promote users, and manage site settings. If replacing an older version, deactivate and delete it first.
3. ZIP the `one-time-admin-promoter` folder and install it through **Plugins > Add New Plugin > Upload Plugin**, or place the folder in `wp-content/plugins/`. Activate **One-Time Admin Promoter**.
4. Open **Tools > Admin Handover**. Enter the exact, case-sensitive existing username—not a display name or email address. Check the confirmation box and click **Complete handover and deactivate plugin**.
5. Verify the target's Administrator role under **Users**, the administration email under **Settings > General**, and the plugin's inactive state under **Plugins**.
6. Delete the inactive plugin. This does not reverse the handover or remove the attempt record.

This plugin cannot bypass permissions imposed by a host or agency. It does not create accounts, request passwords, change passwords, or log in as the target.

No `wp-config.php` edit is required. If the legacy `OTAP_TARGET_USERNAME` constant is already defined as a string, it only prefills the username field. The operator must still submit the confirmed form. The constant can be removed when file access is available. A prior attempt recorded by an older version continues to block another handover.

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

The plugin checks operator capabilities, an exact existing username, the target's email address, and the availability of the Administrator role before claiming an attempt. The handover handler requires an authenticated POST request, an action-specific WordPress nonce, and explicit confirmation. No unauthenticated handover handler is registered. Direct file execution is blocked.

There is no bundled automated test suite. PHP syntax checking does not verify a live handover; verify the resulting role, site email, and inactive plugin state on the intended installation.

## License

GPL-2.0-or-later, as declared in the plugin header.
