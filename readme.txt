=== The Hack Repair Guy's Bunker ===
Contributors: hackrepair
Tags: security, hardening, headless, static site, lockdown
Requires at least: 6.9.4
Requires PHP: 7.4
Tested up to: 7.1.3
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Support link: https://github.com/hackrepair/wp-bunker/discussions

WordPress Utility Lockdown. Your static website faces the public; WordPress stays underground.

== Description ==

If your public website is static HTML (often built and updated by AI) and WordPress only sits behind it for contact forms, mailing-list sign-ups, a chat backend or a database, most of WordPress is unused, yet bots still probe all of it. Bunker switches off everything a utility install does not need, and keeps the few doors your static site actually uses.

Development build 1.2.2 is unreleased.

Open **Settings > Bunker**. Most lockdowns are on by default, each with a plain-language description. Untick one and the description flips to tell you what just happened. Each checkbox saves the moment you change it.

**Forms and API access:** The IP gate exempts form endpoints. The optional known-actions gate restricts logged-out actions to the exact names you allow. REST routes require authentication or an allowlisted prefix.

**What Bunker locks down:**

* Hides the WordPress front end: visitors and bots get a plain 404 page
* Blocks XML-RPC
* Locks the REST API to logged-in users, except routes you allow
* Hides usernames: author scans, the users API, and login errors that give away real names
* Turns off comments, pingbacks and trackbacks
* Turns off public registration
* Turns off application passwords
* Locks the login page and dashboard to your IP addresses, with an optional hatch link that only opens after you approve it by email
* Optional: answers 404 to everything WordPress-related (off by default)
* Turns off the built-in theme and plugin code editors
* Hides version files, logs and folder listings
* Blocks PHP in the uploads folder
* Watches WordPress's scheduled tasks and warns you when they stall
* Optional: only allows the form actions you list for logged-out visitors (off until you set it up)

**Nothing happens until you say so.** The first time you open the page, Bunker asks "Is that how this site works?" and enforces nothing until you choose **Lock it down**. The IP lock waits for a second step: it starts only after you have reviewed and saved your list of addresses.

**Left column and switches.** A light column beside the settings shows the status at a glance, with On | Off switches for Bunker itself, the IP lock and the hatch link, plus jump links and a pinned Save button. Switching Bunker Off pauses every lockdown and removes Bunker's rules from .htaccess, and keeps your settings.

**Hatch link (spare key).** If your IP address changes, the hatch link is a way back to the login page. Opening it emails the administrator, and only a press of Approve in that email lets the browser that asked through, for 1 to 24 hours. Making a new link or switching the hatch off signs everyone out. Requests are limited to one email a minute and five an hour. A pending request remains assigned to its browser through completion or expiry. Creation locks require a shared host filesystem.

**HTTPS redirect on Save changes.** Recommended and initially checked. Saving settings verifies the HTTPS homepage certificate before adding a 308 redirect for static pages and WordPress on direct Apache/LiteSpeed hosting. Paths, queries and methods are preserved. Unchecking persists your choice. Configure proxy/Cloudflare and Nginx redirects through hosting controls. Pausing or deactivating Bunker removes its redirect.

**Your own 404 page.** Everything Bunker refuses gets a real 404. If your site folder has a `404.html`, Bunker uses it; otherwise a built-in page appears.

**Review checks.** Below the options, Bunker shows read-only checks: an account named admin, unused plugins and themes, debug mode, an error_log file in the web folder, and whether WordPress shares a directory with your static site's index.html. It never changes any of these for you.

**Sharing your static web page in the same directory as WordPress is not recommended.** A panel explains moving WordPress to its own subdomain, or its own hosting account, so a break-in cannot rewrite your homepage. The red Bad word and the warning card in the left column appear only when an index.html is found next to WordPress.

= What it is not =

Bunker is not a firewall, malware scanner, login-attempt limiter or two-factor plugin, and it does not log visitors.

= Safe by design =

* Renaming the plugin folder stops PHP enforcement. Remove retained Bunker server-rule blocks manually through hosting access if necessary.
* Add `define( 'THRG_BUNKER_SAFE_MODE', true );` to wp-config.php to pause every lockdown and remove the server rules until you take the line out.
* Server rules (items 10 and 11) sit between `# BEGIN THRG Bunker` and `# END THRG Bunker` in `.htaccess`. Bunker verifies a backup, locks the update and writes atomically. It checks home, login and affected uploads content. Inconclusive checks and failed rollback retain recovery material for review. A rollback failure is reported for manual recovery. Deactivating or deleting the plugin removes them. On Nginx, Bunker shows the rules to copy instead.
* Deactivating Bunker switches everything off: the rules are removed, the hatch link is killed and Bunker is left paused. Your settings are kept. After you activate it again, switch Bunker On when you are ready. Settings are deleted only if you tick "Delete all plugin data" and then delete the plugin.
* Multisite networks are not supported yet; Bunker enforces nothing there.

= Privacy =

Bunker makes no external requests except update checks against GitHub releases. It stores settings, hashed recovery-link material, temporary approval state and email-throttle timestamps in your database. Hatch approval state includes the requesting IP address and user agent, which appear in the approval email. It sends only three kinds of email, all through WordPress's own mail: a one-time welcome to the administrator who activates it, the hatch approval email, and an optional warning when scheduled tasks stall. Subjects look like "[example.com] [Bunker] Welcome". Health checks request your own homepage, login page and a temporary uploads resource. Saving HTTPS preferences also probes the HTTPS homepage with certificate validation.

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

The IP gate keeps the form endpoints available. If you enable known-actions lockdown, list the exact action name, including case, and test your form.

= I locked myself out of the login page. =

In File Manager, rename the `thrg-bunker` plugin folder. That removes every PHP lockdown at once. Or add `define( 'THRG_BUNKER_SAFE_MODE', true );` to wp-config.php.

= What is the hatch link for? =

It is a spare key. If your IP address changes and you cannot reach the login page, open your hatch link from the new address. Bunker emails the administrator, and the login page opens for that browser only after the email's Approve button is pressed. Opening the link from an address already on your list just takes you to the login page.

= What happens when I deactivate Bunker? =

Everything is switched off and the hatch link stops working, but your settings are kept. After you activate it again, press On in the left column when you are ready.

= Why does WordPress Site Health show REST or loopback errors? =

Locking the REST API and hiding the front end blocks the requests Site Health makes to itself. That is expected.

= Does it work with Jetpack or the WordPress mobile app? =

Those use XML-RPC. Untick **Block XML-RPC** if you use either.

= Does it work on Nginx? =

PHP lockdowns work everywhere. Bunker shows manual equivalents for its file restrictions. Configure HTTPS redirects through your Nginx hosting controls.

== Changelog ==

= 1.2.2 =
* Recommend an HTTP-to-HTTPS redirect when saving settings, with certificate validation and direct-host checks.
* Apply the redirect to static pages through the managed server rules and remove it when Bunker is paused.

= 1.2.1 =
* Preserve adjacent Apache directives and retain recovery backups on failed rollback.
* Serialize rule updates, verify uploads health, and preserve pending hatch approvals.
* Match public actions exactly and apply REST restrictions after cookie authentication.
* Remove the plugin asset path from the built-in 404 page.

= 1.2.0 =
* New: hatch link, a spare key for the IP-locked login page. Opening it emails the administrator, and only a press of Approve in that email lets the requesting browser through, for 1 to 24 hours. Requests are limited to one email a minute and five an hour. A pending request remains assigned to its browser through completion or expiry. Creation locks require a shared host filesystem.
* New, off by default: "Answer 404 to everything WordPress-related". Visitors who are not allowed get a plain 404 instead of the "Access limited" page, core files, plugin and theme readmes, version files and WordPress folders answer 404, and XML-RPC answers 404. It hides WordPress; it does not protect it.
* New left column on the settings page: status at a glance, On | Off switches for Bunker, the IP lock and the hatch link, jump links, a pinned Save button, and a warning card about sharing a directory with WordPress (shown only when it applies). It stacks above the page on narrow screens.
* Switching Bunker Off pauses every lockdown and removes its rules from .htaccess, and keeps your settings. Switching the hatch link Off kills the link and signs out every approved browser.
* New: a friendly one-time welcome email goes to the administrator who activates Bunker.
* Deactivating Bunker now switches everything off: .htaccess rules are removed, the hatch link is killed and Bunker is left paused. Settings are kept unless "Delete all plugin data" is ticked and the plugin is deleted.
* Bunker's emails use the THRG standard subject, for example "[example.com] [Bunker] Welcome", and name the site in the body.
* Review checks show the words Good and Bad instead of coloured dots.
* The Hatch link section sits right under the lockdowns.
* Removed the Undo link under unticked options; the checkbox does the same.
* Removed the HackGuard Security line from the review checks.
* Pressing the side of an On | Off switch that is already current now changes nothing and says so. Before, pressing On on the hatch link made a new link and signed everyone out.
* Both Save buttons now save everything on the page, so editing the REST routes and then pressing Save list no longer loses the routes. A rejected IP list never stops the other fields from saving.
* Making a new hatch link or switching the hatch off resets the wrong-link count and the email warning from the old link. The approval email states the real expiry time.
* The hatch note under the IP lock now names the real reason (Bunker off, IP lock off, or list not saved).
* The login page replaces only the wrong-username and wrong-password messages with the generic one; other login messages are left alone.
* With "Answer 404 to everything WordPress-related" on, refused comments and refused form actions answer 404 too.
* Bunker removes its .htaccess backup once the site answers, puts its rules back if something removes them, and settings load faster on every request.

= 0.1.0 =
* First development build: twelve lockdowns plus the optional known-actions lockdown, first-run confirmation, IP lock with review step, Bunker's own 404 page, review checks, safe mode, and GitHub release updates.

== Credits ==

Bunker bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") 5.7 by Janis Elsts, MIT licensed, unmodified, to provide GitHub release updates.
