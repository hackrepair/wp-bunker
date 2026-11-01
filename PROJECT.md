# The Hack Repair Guy's Bunker: project notes

**Working name:** The Hack Repair Guy's Bunker
**Tagline:** WordPress Utility Lockdown. Your static website faces the public; WordPress stays underground.
**Slug:** `thrg-bunker` (GitHub repository `hackrepair/wp-bunker`, release asset `thrg-bunker.X.Y.Z.zip`, tag `vX.Y.Z`)
**Status:** first build, version 0.1.0 (2026-11-01). All thirteen lockdowns, the settings page, first-run confirmation, safe mode, 404 page, review checks, updater and tests are written. Not committed, not released. Shared PHPCS (WordPress-Extra + PHPCompatibilityWP 7.4+, via the shared ruleset) is clean: 15 files, 0 errors, 0 warnings (2026-11-01).

**Name decided:** The Hack Repair Guy's Bunker (chosen over Stagehand, which collides with Browserbase's Stagehand, and Backstage, which collides with an existing WordPress plugin and Spotify's Backstage).

Other names considered: Stagehand, Backstage, Backroom, Utility Closet, Back Office Mode, Panic Room, Safe Room, Blast Door, Fallout Shelter.

The slug must not change after the first release, because changing it later breaks updates for installed copies.

## Who it is for

Site owners who no longer use WordPress as their website. The public site is static HTML (often built and updated by AI), and WordPress stays behind it as a utility: contact forms, mailing-list sign-ups, a chat backend, or a database that the static site reads from.

In that setup most of WordPress is unused, but it is still reachable, and bots still probe all of it. Bunker switches off everything a utility install does not need, while keeping the few doors the static site actually uses.

## The rule that shapes everything

A static site talks to WordPress through a small number of public entry points:

- `wp-admin/admin-post.php` (form posts, used by the Demo Contact Form plugin)
- `wp-admin/admin-ajax.php` (common for chat and live features)
- REST routes the owner chooses (for example a chat or data endpoint)

Every lockdown must leave these working. A lockdown that breaks the form is a bug.

## Scope

In scope: turning off and blocking WordPress features a utility install does not use, plus a small set of read-only checks and a recommendation panel.

Out of scope: a firewall, malware scanning, login-attempt limiting, two-factor and visitor logging. Those belong to HackGuard Security. Bunker detects HackGuard and shows a short note so both can run side by side without duplicate rules.

## Settings page

Location: **Settings > Bunker** (also linked from the Plugins screen).

### Behavior

1. Every lockdown is **on by default**, with two exceptions: item 13 ships **off** (see item 13), and item 8 shows as ticked but does not enforce until the owner has saved its list once (see item 8). Each one is a checkbox with a short title and a plain-language description of what it does.
2. **Each checkbox saves the moment it changes** (admin-ajax, `manage_options` capability, nonce). No Save button for the toggles.
3. When a box is **unchecked**, its description rotates (short flip or slide animation, about 300 ms) to a past-tense message describing what just happened. The card color shifts from green to amber, and an **Undo** link appears.
4. When the box is checked again, the text rotates back to the description and the card returns to green.
5. The state survives reloads: an unchecked option always shows its "what happened" text.
6. Respect `prefers-reduced-motion`: swap the text without the animation.
7. Screen readers get the new text through an `aria-live="polite"` region.

### First-run confirmation

Because the defaults are aggressive (for example, hiding the WordPress front end would blank out a normal WordPress website), nothing is enforced until the administrator confirms once:

> "Bunker is for WordPress running behind a static website. Is that how this site works?" → **Lock it down** / **Not yet**

All boxes are already ticked on this screen, so the defaults stay on. The owner can untick anything before confirming.

**If the owner picks "Not yet":** nothing is enforced. The settings page stays usable, but a persistent amber banner at the top says "Bunker is installed but not protecting this site yet" with a **Lock it down** button. The confirmation is offered again on every visit to the page. Toggles can still be set while waiting; they take effect when the owner confirms.

**Multisite:** not supported in 1.0. On a multisite network, Bunker shows a notice and enforces nothing.

### The lockdowns

Each entry lists the on text, the off text shown the moment it is unticked, and how it is enforced.

**1. Hide the WordPress front end**
- On: Visitors and bots who find your WordPress pages, posts, feeds or author archives get a plain "not found." Your static website is the only thing they see.
- Off: WordPress pages are public again. Anyone can browse your posts, feeds, sitemaps and author archives, which reveal usernames and what's installed.
- How: `template_redirect` at priority 0 returns a 404 for every theme request. Login, admin, admin-post, admin-ajax, cron and allowed REST routes are not affected. Logged-in administrators can preview the front end.
- Refused requests are answered by Bunker's own 404 page (see **404 page** below), so visitors never see WordPress's 404 or theme.

**2. Block XML-RPC**
- On: Closes xmlrpc.php, an old remote-publishing door that bots use to try thousands of passwords in a single request.
- Off: xmlrpc.php is open again. Bots can resume password guessing and pingback attacks through it.
- How: refuse with 403 on `plugins_loaded` when `XMLRPC_REQUEST` is defined, plus `xmlrpc_enabled` and `xmlrpc_methods` filters.
- Note: Jetpack and the WordPress mobile app use XML-RPC. The description says to untick this if either is in use.

**3. Lock the REST API to logged-in users**
- On: Anonymous visitors can't use the WordPress API, except the routes you allow below (like a chat). This stops username lookups and plugin fingerprinting.
- Off: The REST API is public again. Anyone can list your usernames at /wp-json/wp/v2/users and see which features are installed.
- How: `rest_authentication_errors`, with an editable allowlist of route prefixes shown under the toggle.
- Note: with this and item 1 on, Site Health may report REST API or loopback errors. Show a short explainer under the toggle and in the review checks so owners do not mistake it for a fault.

**4. Hide usernames**
- On: Hides usernames from author-ID scans and makes login errors identical for a wrong username or a wrong password.
- Off: Usernames can be discovered again, and the login page now tells attackers when they've guessed a real username.
- How: block `?author=` requests, remove the users endpoints for anonymous visitors, and use a generic `login_errors` message.

**5. Turn off comments, pingbacks and trackbacks**
- On: Closes the comment and trackback endpoints, so bots can't fill your database with spam comments you'll never use.
- Off: Comment and trackback endpoints are open again. Expect spam comments to start arriving in your moderation queue.
- How: `comments_open` and `pings_open` return false, `pre_comment_on_post` refuses, and the comment menus are removed. Existing comments are left alone.

**6. Turn off public registration**
- On: No one can create an account on this site, even if "Anyone can register" gets switched on by mistake or by an attacker.
- Off: Bunker no longer guards registration. If "Anyone can register" is turned on, strangers can create accounts.
- How: `pre_option_users_can_register` returns 0. Refuse `wp-signup.php` and `wp-activate.php`.

**7. Turn off application passwords**
- On: Removes application passwords, a second way to log in that skips your normal login page and two-factor.
- Off: Application passwords are available again. Any user can create one and sign in through the API without your login page.
- How: `wp_is_application_passwords_available` returns false. The description notes that this must be unticked if the static site pulls data with an application password.

**8. Lock the login page and dashboard to your IP addresses**
- On: Only the IP addresses listed below can reach the login page and dashboard. Your form keeps working for everyone.
- Off: The login page and dashboard are open to the whole internet again, so password guessing can reach them.
- How: enforced in PHP (not .htaccess), so renaming the plugin folder in cPanel always removes it. admin-post.php, admin-ajax.php and cron are exempt. The list is pre-filled with the administrator's IP at first-run confirmation and shown with a warning that a changing IP address will lock them out, plus the recovery steps.
- Enforcement waits for review: after first-run confirmation the card is ticked but shows amber, "Waiting for you to review your IP list," and **nothing is blocked**. The lock starts enforcing only after the owner clicks **Save list** once. Editing and saving the list later takes effect immediately.
- Proxy and CDN handling: use `REMOTE_ADDR` by default. Only trust a forwarded header (`CF-Connecting-IP`, `X-Forwarded-For`) when the owner picks it in a "My site is behind Cloudflare or a proxy" setting, and when `REMOTE_ADDR` is in that provider's known range. Show the detected address and which source it came from before pre-filling, so a wrong address is visible at once.
- The list accepts IPv4, IPv6 and CIDR ranges, validated on save. Refuse to save a list that does not include the current administrator's address unless they confirm.

**9. Turn off the built-in code editors**
- On: Removes the theme and plugin file editors, so a stolen admin login can't be used to plant code from the dashboard.
- Off: The file editors are back. Anyone with an admin login can now edit PHP files from inside WordPress.
- How: define `DISALLOW_FILE_EDIT` early if it is not already defined. `DISALLOW_FILE_MODS` is deliberately not offered, because it also stops plugin and core updates.

**10. Hide version files, logs and folder listings**
- On: Blocks readme.html, license.txt, wp-config-sample.php and error logs, and stops folders from listing their files.
- Off: Version and log files are reachable again, and folders without an index file will list their contents.
- How: a marked block in the site's .htaccess (Apache and LiteSpeed). These rules are harmless if left behind, but they are removed on deactivation. On Nginx, show the rules to copy instead of writing them.
- Safety check: `Options -Indexes` can cause a 500 error where `AllowOverride` does not permit it. After writing the block, request the site's home page and `wp-login.php` with a loopback request. If either returns a 5xx, restore the backed-up `.htaccess` automatically and show a notice saying which rule failed.

**11. Block PHP in the uploads folder**
- On: Stops PHP files in wp-content/uploads from running. That's where most uploaded backdoors try to hide.
- Off: PHP can run in the uploads folder again. A malicious file uploaded there would execute.
- How: a marked block in `wp-content/uploads/.htaccess`. Same Nginx fallback and the same post-write check as item 10.
- Note: a few plugins (some caching and page-builder tools) legitimately run PHP from uploads. The description says to untick this if something stops working after turning it on.

**12. Watch WordPress's scheduled tasks**
- On: Your visitors load the static page, not WordPress, so WordPress's scheduled tasks, including automatic updates, may rarely run. Bunker warns you when they stall and shows the cPanel cron command that fixes it.
- Off: Bunker has stopped watching scheduled tasks. If they stall, automatic updates can quietly stop and you won't be told.
- How: record the last WP-Cron run. If it is more than 24 hours old, show an admin notice and an optional email. Show a ready-to-paste cPanel cron line and the `DISABLE_WP_CRON` instruction.
- This is a monitor, not enforcement.

**13. Only allow known form actions (logged-out visitors)**
- On: Logged-out visitors can only run the form and chat actions you list below. Every other request to admin-post.php and admin-ajax.php from a stranger is refused.
- Off: admin-post.php and admin-ajax.php accept any action from anyone again, including actions that installed plugins registered for logged-out visitors.
- How: on `admin_post_nopriv_*` and `wp_ajax_nopriv_*`, refuse any `action` not in the allowlist when the visitor is not logged in. Logged-in users are not affected.
- Default: **off.** The owner ticks it after adding the actions the static site uses. It cannot be turned on while the allowlist is empty, because that would break the static site's form. The settings page offers a "Detect actions" helper that lists the registered `nopriv` actions so the owner can tick the ones the site uses. This is the only lockdown that closes a door the rule above deliberately leaves open.

### 404 page

Bunker answers every request the front-end lockdown (item 1) refuses with its own 404 page, so visitors never see WordPress's 404 or theme.

- **Source, in order:** (1) the owner's own static 404 page, auto-detected as `404.html` next to the site's `index.html`, if present; (2) a built-in plain page with the THRG heart mascot and a short "Page not found" message. A setting lets the owner force the built-in page.
- Always sent with HTTP status 404, `X-Robots-Tag: noindex`, and no WordPress version, generator tag or theme output.
- The detected file is read as plain text and sent as-is. It is never executed, even if it contains PHP, and only a file named `404.html` in the site root is eligible.
- Relative links inside the owner's 404.html (images, CSS) can break when the page is served at a deeper URL. The settings page notes that root-relative links (starting with `/`) work everywhere.

### Review checks (read-only, below the toggles)

These are not toggles. Each shows a status and what to do:

- An account named `admin` exists (the first username bots try).
- Inactive plugins and themes are installed (delete them; utility installs need very few).
- `WP_DEBUG` or `WP_DEBUG_DISPLAY` is on.
- An `error_log` file is in the web root.
- WordPress is in the same folder as the static site's `index.html` (links to the recommendation below).
- HackGuard Security is active (informational: both can run together).

### Recommendation panel (bottom of the page)

Title: **Even better: give WordPress its own address**

Text (draft): Right now WordPress shares a folder with your website. If WordPress were ever broken into, the attacker could rewrite your homepage too. Moving WordPress to its own subdomain, such as forms.yoursite.com, keeps it apart. Putting it in a separate cPanel account is stronger still, because a break-in there can't touch your website's files at all. Your form keeps working: you only change its action address to the new location.

The panel shows whether the current install shares a folder with an `index.html`, and links to the hosting provider's help.

## Safety and recovery

- PHP enforcement for anything that can lock someone out (items 1–9 and 13). Item 12 is a monitor only. Renaming the plugin folder through cPanel File Manager removes the PHP enforcement immediately.
- Server rules only for items 10 and 11. They are low risk but not zero: item 10 can trigger a server error on restrictive hosts (see its safety check) and item 11 can stop plugins that run PHP from uploads. They sit between `# BEGIN THRG Bunker` / `# END THRG Bunker` markers. Write them atomically, back up .htaccess first, and remove them on deactivation.
- Safe mode: `define( 'THRG_BUNKER_SAFE_MODE', true );` in wp-config.php pauses all PHP enforcement, removes the `.htaccess` marker blocks for items 10 and 11 (and restores them when safe mode is turned off), and shows a notice.
- Renaming the plugin folder runs no deactivation code, so the `.htaccess` blocks for items 10 and 11 stay in place. The recovery notes say so and explain how to delete the marked blocks by hand.
- Never block admin-post.php, admin-ajax.php, wp-cron.php or the allowed REST routes.

## Architecture (planned)

- `index.php`: plugin header, constants (`THRG_BUNKER_VERSION`), bootstrap, Plugins-screen links and View details.
- `includes/class-thrg-bunker-options.php`: registry of lockdowns (id, title, on text, off text, default, enforcement callback). The page, the AJAX handler and the tests all read from this one list.
- `includes/class-thrg-bunker-lockdown.php`: PHP-level enforcement hooks.
- `includes/class-thrg-bunker-server-rules.php`: .htaccess marker blocks, server detection, Nginx fallback text.
- `includes/class-thrg-bunker-checks.php`: review checks.
- `includes/view-settings.php`, `assets/settings.css`, `assets/settings.js`: the page, instant save and text rotation.
- `includes/class-thrg-bunker-updater.php` and `plugin-update-checker/`: GitHub release updates.
- `includes/companion-plugins.php`: the **Other THRG Plugins** tab in View details.
- `uninstall.php`: remove options and server-rule blocks. Delete all plugin data only when the owner ticks that option.

## Hack Repair plugin standard

- Bundle the unmodified upstream `plugin-update-checker/` (currently 5.7, Jānis Elsts, MIT). Declare it in README.md, readme.txt and this file.
- Release asset `thrg-bunker.X.Y.Z.zip` with a single top-level `thrg-bunker/` folder, tag `vX.Y.Z`. Latest-release strategy, release assets required, tag must equal the asset version.
- PHP 7.4 or newer. Avoid PHP 8-only functions such as `str_starts_with`.
- Run the shared PHPCS before committing PHP changes: `/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"` with a local `phpcs.xml.dist` (WordPress Extra and PHPCompatibilityWP 7.4+).
- Links in docs carry tooltip text: [text](url "tooltip").
- Use the 2026 THRG heart mascot artwork on the settings page, matching Site Inspector and Plugin Detective.
- Keep `PROJECT.md`, `README.md`, `tests/` and `.gitignore` out of the release zip.

## Tests (planned)

- Option registry: every lockdown has a title, on text, off text and default, and every default is on.
- Each lockdown blocks what it should and leaves admin-post.php, admin-ajax.php, wp-cron.php and allowed REST routes working.
- IP lock: allowed IP passes, other IP is refused, exempt endpoints still work, safe mode disables it.
- Server rules: marker blocks are added once, updated in place, and removed cleanly; existing .htaccess content is preserved.
- AJAX toggle: capability and nonce required; the response returns the new state and the matching text.
- Uninstall behavior.
- First-run "Not yet": nothing enforced, banner shown, confirmation offered again, toggles saved but inactive.
- Proxy and CDN: `REMOTE_ADDR` used by default; forwarded header trusted only when selected and the source is a known range; IPv6 and CIDR entries validated; saving a list without the admin's own address needs confirmation.
- Safe mode with server rules: marker blocks are removed on entering safe mode and restored on leaving it.
- Item 10 post-write check: a simulated 5xx restores the backed-up `.htaccess`.
- Undo link: restores the on state and the description text.
- No-JavaScript fallback: toggles still work through a normal form post.
- Multisite: shows the notice and enforces nothing.
- Item 8 waiting state: ticked but not enforced until the list is saved once; enforced immediately after; later edits apply at once.
- 404 page: detected `404.html` is served with status 404 and noindex; PHP inside it is not executed; built-in page used when none exists or when forced; no WordPress version or theme output.
- Item 13: cannot be turned on with an empty allowlist; listed actions pass for logged-out visitors, other actions are refused, logged-in users are unaffected.
- End-to-end on the demo site (resellertest1.tvsecure.net): the static page's contact form still submits and saves with every lockdown on.

## Decisions

Decided:

- Name: The Hack Repair Guy's Bunker, slug `thrg-bunker`.
- First-run confirmation before enforcement (no enforce-on-activation mode).
- IP lock (item 8) waits for the owner to review and save the list once before it enforces.
- Item 13 (known form actions only) ships off by default and cannot be turned on with an empty list.
- Missing pages get Bunker's own 404 page (the owner's `404.html` if present, otherwise a built-in page).
- Multisite is unsupported in 1.0.

Still to do before the first release:

1. Confirm the GitHub repository `hackrepair/wp-bunker` is set up (its name differs from the slug on purpose; the slug and zip name stay `thrg-bunker`).
2. Update the settings-page artwork and copy to the bunker theme (heart mascot, blast-door motif).

## Build order

1. **Foundation:** options registry, settings page, instant save, text rotation, first-run confirmation, safe mode, updater, tests for the registry and the AJAX toggle.
2. **Low-risk lockdowns:** items 2, 5, 6, 7, 9 and the monitor in item 12, with their tests.
3. **Review checks and the recommendation panel.**
4. **Higher-risk lockdowns:** items 3, 4, 1 and 13, then the IP lock (item 8) once proxy handling is tested.
5. **Server rules:** items 10 and 11 with the post-write check, then the demo-site end-to-end test with every lockdown on.
