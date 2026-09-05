=== Wizjo Monitor ===
Contributors: wizjo
Tags: monitoring, health, uptime, diagnostics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Read-only health endpoint for Wizjo Tools monitoring. Checks database, storage, scheduled tasks, updates and PHP.

== Description ==

Wizjo Monitor exposes one token-protected REST endpoint used by Wizjo Tools.
It helps detect problems that are invisible to a normal uptime check, including
failed writes, overdue scheduled tasks and pending security updates.

The plugin checks:

* database connectivity,
* free filesystem space,
* an actual write and read in the uploads directory,
* overdue WordPress cron events,
* pending WordPress, plugin and theme updates,
* production debug mode,
* unsupported PHP versions.

The plugin does not initiate external connections. It only responds when the
monitoring service calls its endpoint with the token configured by the site
administrator. It does not return post contents, user data or database rows.

== Installation ==

1. Install and activate Wizjo Monitor.
2. Open Settings > Wizjo Monitor.
3. Paste the token generated for this site in Wizjo Tools and save it.
4. Copy the displayed endpoint URL into the corresponding monitoring check.
5. Use the test action in Wizjo Tools before enabling the check.

== Frequently Asked Questions ==

= Is the diagnostic endpoint public? =

The URL is public, but every successful request requires the token in the
X-Wizjo-Token header. A missing or incorrect token returns HTTP 403. If the
plugin has not been configured yet, it returns HTTP 503.

= Does the plugin send data by itself? =

No. It does not initiate requests to Wizjo Tools or any other service. It only
answers authenticated requests received by the WordPress REST API.

= Why can filesystem space differ from the hosting panel? =

On shared hosting PHP may report the whole server partition instead of the
account quota. A low percentage is therefore reported as a warning. A failed
real write to uploads is reported as an error.

== Upgrade Notice ==

= 1.1.0 =

Improves shared-hosting disk checks, verifies real uploads writes and reports
the name of the oldest overdue cron hook.

== Changelog ==

= 1.1.0 =

* Avoid false disk failures caused by shared-hosting partition percentages.
* Include the amount of free space in the diagnostic message.
* Verify a real write, read and cleanup operation in the uploads directory.
* Include the oldest overdue cron hook in diagnostic results and metrics.
* Stop accepting the monitoring token in the query string.

= 1.0.0 =

* Initial release.
