<p align="center">
  <img src=".github/branding/thrg-heart-animated.gif" alt="The Hack Repair Guy heart and wrench logo" width="144" height="144">
</p>

<h1 align="center">The Hack Repair Guy's Bunker</h1>

<p align="center"><strong>WordPress Utility Lockdown.</strong> Your static website faces the public; WordPress stays underground.</p>

If your public website is static HTML (often built and updated by AI) and WordPress only sits behind it for contact forms, mailing-list sign-ups, a chat backend or a database, most of WordPress is unused, yet bots still probe all of it. Bunker switches off everything a utility install does not need, and keeps the few doors your static site actually uses.

Current version: **0.1.0** (development build; no release yet).

## The rule that shapes everything

`wp-admin/admin-post.php`, `wp-admin/admin-ajax.php`, `wp-cron.php` and the REST routes you choose are never blocked. A lockdown that breaks your form is a bug.

## What it does

**Settings → Bunker** has one checkbox per lockdown. Each saves the moment you change it. Untick one and its description flips to say what just happened, the card turns amber, and an Undo link appears. Every lockdown is on by default, except the optional one at the end.

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
12. Watch WordPress's scheduled tasks and warn when they stall, with the cPanel cron line that fixes it
13. Optional, off by default: only allow the form actions you list for logged-out visitors

Below the options: read-only **review checks** (an `admin` account, unused plugins and themes, debug mode, an `error_log` in the web folder, WordPress sharing a folder with `index.html`, HackGuard Security detected) and an **Even better: give WordPress its own address** panel.

## Nothing happens until you say so

The first time the page opens, Bunker asks *"Is that how this site works?"* and enforces nothing until you choose **Lock it down**. The IP lock has a second step: it starts only after you have reviewed and saved your list. Bunker's own **404 page** uses your site's `404.html` when there is one.

## Safe and recoverable

- Anything that could lock you out is enforced in PHP. Renaming the plugin folder in cPanel File Manager removes it at once.
- `define( 'THRG_BUNKER_SAFE_MODE', true );` in `wp-config.php` pauses every lockdown and removes the server rules until you take the line out.
- Server rules sit between `# BEGIN THRG Bunker` and `# END THRG Bunker`. They are written atomically after a backup, checked with a loopback request, and rolled back if the site starts returning server errors. Deactivating or deleting the plugin removes them. On Nginx the rules are shown to copy.
- Multisite is not supported in 1.0.

## Not in scope

A firewall, malware scanning, login-attempt limiting, two-factor and visitor logging belong to HackGuard Security. Bunker detects HackGuard and the two run side by side without duplicate rules.

## Development

- PHP 7.4 or newer. Avoid PHP 8-only functions.
- Tests are standalone PHP scripts in `tests/`; run each with `php tests/<name>.php`.
- Run the shared PHPCS before committing PHP changes: `/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"`.
- `python3 scripts/build-release.py` builds `dist/thrg-bunker.X.Y.Z.zip` with a single top-level `thrg-bunker/` folder. `PROJECT.md`, `README.md`, `tests/` and `.gitignore` are kept out of the zip.

Bundled library: [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") 5.7 by Janis Elsts, MIT, unmodified, for GitHub release updates.

Released under the [GPL v2 or later](LICENSE "GNU General Public License, version 2").
