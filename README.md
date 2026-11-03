<p align="center">
  <img src=".github/branding/thrg-heart-animated.gif" alt="The Hack Repair Guy heart and wrench logo" width="144" height="144">
</p>

<h1 align="center">The Hack Repair Guy's Bunker</h1>

<p align="center"><strong>WordPress Utility Lockdown.</strong> Your static website faces the public. WordPress stays underground.</p>

## Sound familiar?

You built your website as plain HTML. It's fast, it's cheap to host, and there's not much on it to break.

Then the contact form needed somewhere to send its messages. So did the newsletter sign-up, or the chat widget. So WordPress went in quietly behind the scenes to handle them, and nobody ever looks at it.

The bots do. Every day they knock on the login page, poke at XML-RPC, ask the REST API for usernames and try to run PHP in your uploads folder. They don't care that nobody uses your WordPress. They only care that it answers.

**Bunker makes it stop answering, except for the few doors your site actually uses.**

It isn't about pretending WordPress isn't there. It's about closing the doors that get hit.

Current release: **1.2.12**. See [release notes](releases/v1.2.12.md).

<p align="center">
  <a href="https://github.com/hackrepair/wp-bunker/releases/latest" title="Download the latest Bunker plugin zip from GitHub"><img src="https://img.shields.io/badge/%E2%AC%87%20%20Download%20the%20latest%20release-b34700?style=for-the-badge&labelColor=b34700" alt="Download the latest release" height="46"></a>
  <br>
  <sub>Free. Upload the zip in WordPress, and later updates arrive on the Plugins screen.</sub>
</p>

<p align="center">
  <a href=".github/branding/bunker-settings.png"><img src=".github/branding/bunker-settings.png" alt="Bunker settings page showing the status switches and WordPress lockdown controls" width="100%"></a>
  <br><br>
  <a href="https://hackrepair.github.io/wp-bunker/" title="The Bunker project page">Visit the Bunker page</a>
</p>

## What gets locked down

Open **Settings → Bunker** and you'll find one checkbox per lockdown, each with a plain sentence about what it does. Changes save the moment you click. Untick one and the card turns amber and tells you exactly what you just opened back up.

Twelve are on from the start:

1. Hide the WordPress front end. Visitors and bots get a plain 404.
2. Block XML-RPC.
3. Lock the REST API to logged-in users, except routes you allow.
4. Hide usernames: author scans, the users API, and login errors that give real names away.
5. Turn off comments, pingbacks and trackbacks.
6. Turn off public registration.
7. Turn off application passwords.
8. Lock the login page and dashboard to your own IP addresses (IPv4, IPv6 and ranges, Cloudflare aware).
9. Turn off the built-in code editors.
10. Hide version files, logs and folder listings (`.htaccess` rules).
11. Block PHP in the uploads folder (`.htaccess` rules).
12. Keep an eye on WordPress's scheduled tasks, and warn you with the exact cron line to fix them if they stall.

Two more are there for when you want to go further. They stay off until you switch them on:

13. Only allow the form actions you list for logged-out visitors.
14. Answer 404 to everything WordPress-related.

### Your forms keep working

That's the whole point, so Bunker is careful here. The addresses your forms post to stay reachable from anywhere. Want it stricter? List the exact form action names your site uses, and everything else gets turned away. If your chat widget talks to the REST API, allow its route and the rest stays locked.

Either way, send yourself a test message after you change settings. It takes ten seconds.

Further down the page you'll find the **hatch link**, a choice of **404 page** (your own `404.html` if you have one), and a few read-only **review checks**: an account called `admin`, unused plugins and themes, debug mode left on, an `error_log` file in your web folder, and WordPress sharing a folder with your `index.html`.

## Nothing happens until you say so

The first time you open the page, Bunker shows you what it's about to do and asks *"Is that how this site works?"* Nothing is enforced until you press **Lock it down**.

The IP lock has one more step. It only starts after you've looked over your address list and saved it, so you can't lock yourself out by accident.

A light column beside the settings has **On | Off** switches for Bunker itself, the IP lock and the hatch link. Switch Bunker **Off** and every lockdown pauses and its server rules come out. Your settings stay put for when you switch it back on.

## Address changed? Use the hatch

The IP lock is how you get in day to day: only your addresses reach the login page. But home internet changes, you travel, your phone hops networks. The hatch link is your spare key.

Open it from anywhere and Bunker emails you. Nothing opens until you press **Approve** in that email, and then only the browser that asked gets in, for 1, 4, 12 or 24 hours. Someone who finds the link can't let themselves in.

- Bunker stores only a fingerprint (hash) of the link, and shows you the link once.
- Make a new link, or switch the hatch off, and every approved browser is signed out.
- One approval email a minute at most, five an hour. If the email can't be sent, the hatch stays shut.

## Know when something changes (new in 1.2.10)

Blocking bots is half the job. The other half is noticing when something changes that shouldn't have. Under **Email alerts** there are three optional alerts, all off until you tick them:

- an account becomes an administrator (a new account, a promotion, or the role added to an existing account)
- a new plugin is installed through WordPress (upload, plugin directory or WP-CLI)
- a new theme is installed through WordPress

Tick what you want and press **Save changes**. Alerts go to the site's admin email, or to any address you type into **Send alert emails to**. Each one tells you the site, the time, what changed and who did it, when WordPress knows. Routine stuff stays quiet: updates, reinstalls, activating a plugin and failed installs send nothing.

A few honest notes:

- Alerts work even before you press **Lock it down**, so they're useful on any site. Pausing Bunker, safe mode and multisite switch them off.
- If WordPress can't send an alert, Settings shows a notice. It clears after the next alert goes out, or when you save your settings. Nothing is retried.
- They only see changes made through WordPress. Files copied in over SFTP or File Manager, custom admin-like roles and direct database edits get past them.
- They tell you; they don't stop it. Someone who already has an administrator account could switch alerts off, so treat an alert as a nudge to go and look.

## What lands in your inbox

Bunker only sends mail through WordPress itself, with subjects like `[example.com] [Bunker] Welcome`:

- **Welcome**, once, to the administrator who first activates it.
- **Approve or deny a hatch request**, whenever someone opens the hatch link from outside your IP list.
- **Scheduled tasks stalled**, optional and off by default, at most once a week.
- **Security alerts**, optional and off by default: administrator access added, new plugin installed, new theme installed.

## If something goes wrong

- Can't reach the dashboard? Add `define( 'THRG_BUNKER_SAFE_MODE', true );` to `wp-config.php`. Every lockdown pauses and the server rules come out until you remove the line.
- Server rules live between `# BEGIN THRG Bunker` and `# END THRG Bunker` in `.htaccess`, so they're easy to spot. Before changing that file, Bunker backs it up, then checks your home page, login page and uploads afterward. If your server answers with an error, the old file goes straight back. On Nginx, Bunker shows you the rules to copy instead.
- If a check can't tell either way, Bunker keeps its backup and shows a short notice with a button to set it aside once you've looked at your site. Otherwise you won't see anything.
- **Deactivating** switches everything off: rules removed, hatch link cancelled, Bunker left paused. Your settings are deleted only if you delete the plugin with **Delete all plugin data** ticked.
- Renaming the plugin folder in File Manager also stops it. The server rules stay until you remove those lines yourself.
- Multisite isn't supported yet. Bunker does nothing there.

## Privacy, and what Bunker isn't

Bunker doesn't phone home. The only outside request it makes is checking this repository for updates. Its health checks only ask your own site for its home page, login page and a temporary file in uploads. Settings, the hatch fingerprint and short-lived approval details stay in your WordPress database. A hatch request records the asking IP address and browser so you can see them in the approval email, and security alerts name the account or plugin/theme and who made the change.

It's not a firewall, malware scanner, login limiter or two-factor plugin, and it doesn't log your visitors. Certificates, HTTPS redirects and HSTS belong with your hosting provider.

If you think your site has already been hacked, [The Hack Repair Guy](https://hackrepair.com/ "The Hack Repair Guy: WordPress security and hack repair") can help.

## Install

1. Download the latest zip from the button above.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, upload it and activate it.
3. Open **Settings → Bunker**, untick anything your site still needs, and press **Lock it down**.
4. Save your IP list, then send yourself a test message through your form.

Requires WordPress 6.9.4 or newer and PHP 7.4 or newer. Updates arrive on your Plugins screen from this repository's [GitHub releases](https://github.com/hackrepair/wp-bunker/releases "All Bunker releases and download files").

## Author and support

**[Jim Walker](https://hackrepair.com/)** — The Hack Repair Guy — author and maintainer.

[Questions, ideas and community support](https://github.com/hackrepair/wp-bunker/discussions) · [Report a bug](https://github.com/hackrepair/wp-bunker/issues) · [Security reporting](SECURITY.md) · [Buy Jim a coffee](https://hackrepair.com/donations/buy-jim-a-coffee)

## License and credits

[GPLv2 or later](LICENSE "GNU General Public License, version 2").

Update checks use [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") 5.7 by Jānis Elsts (MIT license), included unmodified.

Made by [The Hack Repair Guy](https://hackrepair.com/ "The Hack Repair Guy: WordPress security and hack repair").

## Changelog

See the **Changelog** section of [`readme.txt`](readme.txt).
