<p align="center">
  <img src=".github/branding/thrg-heart-animated.gif" alt="The Hack Repair Guy heart and wrench logo" width="144" height="144">
</p>

<h1 align="center">The Hack Repair Guy's Bunker</h1>

<p align="center"><strong>WordPress Utility Lockdown.</strong> Your static website faces the public; WordPress stays underground.</p>

If your public website is static HTML (often built and updated by AI) and WordPress only sits behind it for contact forms, mailing-list sign-ups, a chat backend or a database, most of WordPress is unused, yet bots still probe all of it. Bunker switches off everything a utility install does not need, and keeps the few doors your static site actually uses.

Development version: **1.2.3 (unreleased)**. Version 1.2.1 was installed directly on the authorized test site. See the validation status below before deployment.

## The rule that shapes everything

Bunker keeps the form endpoints available through the IP gate. The optional known-actions gate restricts their public actions to your exact allowlist. REST routes require authenticated requests or an allowed route prefix. Test the routes and actions used by your static site after changing these controls.

## What it does

**Settings → Bunker** has one checkbox per lockdown. Each saves the moment you change it. Untick one and its description flips to say what just happened, and the card turns amber. Every lockdown is on by default, except the two optional ones at the end.

1. Hide the WordPress front end (visitors and bots get a plain 404)
2. Block XML-RPC
3. Lock the REST API to logged-in users, except routes you allow
4. Hide usernames (author scans, the users API, revealing login errors)
5. Turn off comments, pingbacks and trackbacks
6. Turn off public registration
7. Turn off application passwords
8. Lock the login page and dashboard to your IP addresses (IPv4, IPv6 and ranges; Cloudflare aware)
9. Turn off the built-in code editors
10. Hide version files, logs and folder listings (`.htaccess` rules)
11. Block PHP in the uploads folder (`.htaccess` rules)
12. Watch WordPress's scheduled tasks and warn when they stall, with the cron line that fixes it
13. Optional, off by default: only allow the form actions you list for logged-out visitors
14. Optional, off by default: answer 404 to everything WordPress-related. It hides WordPress; it does not protect it

Below the options: the **Hatch link** (see below), your site's **404 page** setting, read-only **review checks** (an `admin` account, unused plugins and themes, debug mode, an `error_log` in the web folder, WordPress sharing a directory with `index.html`, and whether item 14 is on), and a "Sharing your static web page in the same directory as WordPress is not recommended" panel that explains moving WordPress to its own address.

### Hosting responsibility

Configure certificates, HTTPS redirects and HSTS through your hosting provider. Bunker has no HTTPS switch or automatic redirect and imposes no HTTPS activation requirement. Any legacy HTTPS preferences from 1.2.2 are ignored and dropped on the next state write. Normal managed-rule synchronization replaces Bunker's old block while preserving hosting rules outside its markers. A retained recovery backup can require owner review before a managed-block update proceeds.

### Left column

A light column beside the settings shows the status at a glance, with **On | Off** switches for Bunker itself, the IP lock and the hatch link. It also has jump links, a pinned **Save changes** button and, only when an `index.html` sits next to WordPress, one warning card. Switching Bunker **Off** pauses every lockdown and removes Bunker's rules from `.htaccess`; your settings are kept. It stacks above the page on narrow screens.

### Hatch link (spare key)

The IP lock is the normal way in. If your address changes, the hatch link is a way back: opening it emails the administrator, and nothing happens until someone presses **Approve** on the confirmation page behind that email link. Approval lets the browser that asked, not the one that clicked, through for 1, 4, 12 or 24 hours. Making a new link or switching the hatch off ends every approved browser at once. Only a hash of the link is stored, and it is shown once. At most one approval email is sent a minute and five an hour. If the email cannot be sent, the hatch stays shut. A pending request stays assigned to its original browser until completion or expiry. Creation uses a host-local filesystem lock, so multi-host installations require shared locking before relying on concurrent-request protection.

## Nothing happens until you say so

The first time the page opens, Bunker asks *"Is that how this site works?"* and enforces nothing until you choose **Lock it down**. The IP lock has a second step: it starts only after you have reviewed and saved your list. Bunker's own **404 page** uses your site's `404.html` when there is one.

## Emails

Bunker sends only three kinds of email, with subjects in the THRG format `[domain] [Bunker] Purpose`:

- **Welcome**, once, to the administrator who first activates the plugin. It says nothing is locked yet, what Bunker is for, the next step and how to recover from a lockout.
- **Approve or deny a hatch request**, each time a visitor outside your IP list opens the hatch link.
- **Scheduled tasks stalled**, optional and off by default, at most once a week.

## Safe and recoverable

- Renaming the plugin folder stops PHP enforcement. Server rules remain until removed manually. Keep hosting/File Manager access available for recovery, including any HTTPS redirect loop.
- `define( 'THRG_BUNKER_SAFE_MODE', true );` in `wp-config.php` pauses every lockdown and removes the server rules until you take the line out.
- Server rules sit between `# BEGIN THRG Bunker` and `# END THRG Bunker`. Writes use per-target locks and unique temporary files. A fresh backup is required before replacing an existing file. Home/login probes and an uploads static-content probe check the affected behavior. Inconclusive checks retain the backup. A failed rollback retains recovery material and reports the failure. Resolve an existing recovery backup before retrying an apply. On Nginx the rules are shown to copy.
- **Deactivating** Bunker switches everything off: the rules are removed, the hatch link is killed and Bunker is left paused. Your settings are kept, so after you activate it again you switch Bunker **On** when you are ready. Settings are deleted only when you delete the plugin with **Delete all plugin data** ticked.
- Multisite is not supported yet; Bunker enforces nothing there.

## Not in scope

Bunker is not a firewall, malware scanner, login-attempt limiter or two-factor plugin, and it does not log visitors.

## Validation status

Seventeen standalone suites run against WordPress stubs. PHP 8.4 syntax checks and JavaScript syntax checks pass. The test site verified the 1.2.0-to-1.2.1 replacement, REST nonce behavior, uploads toggle persistence, case-sensitive action rejection and neutral 404 output. HTTPS automation introduced in 1.2.2 was removed in 1.2.3. The rule-backup failure observed on the test site still requires owner-side filesystem review. Apache fault injection, true concurrent requests, minimum-PHP execution, disallowed-IP access and full email approval remain pending. Version 1.2.3 has no release or tag from this work.

## Development

- PHP 7.4 or newer. Avoid PHP 8-only functions.
- Tests are standalone PHP scripts in `tests/`; run each with `php tests/<name>.php`. `scripts/mailpit-preview.php` is not a test: it sends Bunker's three emails to a local Mailpit for a visual check.
- Run the shared PHPCS before committing PHP changes: `/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"`.
- `python3 scripts/build-release.py` builds `dist/thrg-bunker.X.Y.Z.zip` with a single top-level `thrg-bunker/` folder. `PROJECT.md`, `README.md`, `tests/` and `.gitignore` are kept out of the zip.

Bundled library: [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") 5.7 by Janis Elsts, MIT, unmodified, for GitHub release updates.

Released under the [GPL v2 or later](LICENSE "GNU General Public License, version 2").
