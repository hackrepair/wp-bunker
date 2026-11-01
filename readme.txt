=== The Hack Repair Guy's Bunker ===
Contributors: hackrepair
Tags: security, hardening, headless, static site, lockdown
Requires at least: 6.9.4
Requires PHP: 7.4
Tested up to: 7.1.3
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Support link: https://github.com/hackrepair/wp-bunker/discussions

WordPress Utility Lockdown. Your static website faces the public; WordPress stays underground.

== Description ==

If your public website is static HTML (often built and updated by AI) and WordPress only sits behind it for contact forms, mailing-list sign-ups, a chat backend or a database, most of WordPress is unused, yet bots still probe all of it. Bunker switches off everything a utility install does not need, and keeps the few doors your static site actually uses.

Open **Settings > Bunker**. Every lockdown is on by default, each with a plain-language description. Untick one and the description flips to tell you what just happened, with an Undo link. Each checkbox saves the moment you change it.

**The rule that shapes everything:** `admin-post.php`, `admin-ajax.php`, `wp-cron.php` and the REST routes you choose are never blocked. A lockdown that breaks your form is a bug.

**What Bunker locks down:**

* Hides the WordPress front end: visitors and bots get a plain 404 page
* Blocks XML-RPC
* Locks the REST API to logged-in users, except routes you allow
* Hides usernames: author scans, the users API, and login errors that give away real names
* Turns off comments, pingbacks and trackbacks
* Turns off public registration
* Turns off application passwords
* Locks the login page and dashboard to your IP addresses
* Turns off the built-in theme and plugin code editors
* Hides version files, logs and folder listings
* Blocks PHP in the uploads folder
* Watches WordPress's scheduled tasks and warns you when they stall
* Optional: only allows the form actions you list for logged-out visitors (off until you set it up)

**Nothing happens until you say so.** The first time you open the page, Bunker asks "Is that how this site works?" and enforces nothing until you choose **Lock it down**. The IP lock waits for a second step: it starts only after you have reviewed and saved your list of addresses.

**Your own 404 page.** Everything Bunker refuses gets a real 404. If your site folder has a `404.html`, Bunker uses it; otherwise a built-in page appears.

**Review checks.** Below the options, Bunker shows read-only checks: an account named admin, unused plugins and themes, debug mode, an error_log file in the web folder, and whether WordPress shares a folder with your static site's index.html. It never changes any of these for you.

**Even better: give WordPress its own address.** A panel explains moving WordPress to its own subdomain, or its own cPanel account, so a break-in cannot rewrite your homepage.

= What it is not =

Bunker is not a firewall, malware scanner, login-attempt limiter or two-factor plugin, and it does not log visitors. Those belong to HackGuard Security. Bunker notices when HackGuard is active and the two run side by side without duplicate rules.

= Safe by design =

* PHP enforcement for anything that could lock you out. Renaming the plugin folder in cPanel File Manager removes it at once.
* Add `define( 'THRG_BUNKER_SAFE_MODE', true );` to wp-config.php to pause every lockdown and remove the server rules until you take the line out.
* Server rules (items 10 and 11) sit between `# BEGIN THRG Bunker` and `# END THRG Bunker` in `.htaccess`. Bunker backs the file up, writes it atomically, checks your site still loads, and puts the old file back if it does not. Deactivating or deleting the plugin removes them. On Nginx, Bunker shows the rules to copy instead.
* Multisite networks are not supported yet; Bunker enforces nothing there.

= Privacy =

Bunker makes no external requests except update checks against GitHub releases. It stores only its own settings in your database. After a change to the server rules it requests your own home page and login page once to confirm they still load.

== Installation ==

1. Upload the zip in **Plugins > Add New > Upload Plugin**, then activate it.
2. Open **Settings > Bunker**.
3. Untick anything you want to keep open (for example, application passwords if your static site uses one, or XML-RPC if you use Jetpack).
4. Add any REST routes your static site calls under **Lock the REST API**.
5. Choose **Lock it down**.
6. Review the IP list and choose **Save list**. Add `203.0.113.0/24`-style ranges if your address changes.
7. Test your static site's form.

Later updates arrive on the Plugins screen.

== Frequently Asked Questions ==

= Will my contact form keep working? =

Yes. `admin-post.php` and `admin-ajax.php` are never blocked. If you also turn on the optional known-actions lockdown, list your form's action name first.

= I locked myself out of the login page. =

In cPanel File Manager, rename the `thrg-bunker` plugin folder. That removes every PHP lockdown at once. Or add `define( 'THRG_BUNKER_SAFE_MODE', true );` to wp-config.php.

= Why does WordPress Site Health show REST or loopback errors? =

Locking the REST API and hiding the front end blocks the requests Site Health makes to itself. That is expected.

= Does it work with Jetpack or the WordPress mobile app? =

Those use XML-RPC. Untick **Block XML-RPC** if you use either.

= Does it work on Nginx? =

PHP lockdowns work everywhere. For the two server-rule options Bunker shows the Nginx rules to copy instead of writing them.

== Changelog ==

= 0.1.0 =
* First development build: twelve lockdowns plus the optional known-actions lockdown, first-run confirmation, IP lock with review step, Bunker's own 404 page, review checks, safe mode, and GitHub release updates.

== Credits ==

Bunker bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") 5.7 by Janis Elsts, MIT licensed, unmodified, to provide GitHub release updates.
