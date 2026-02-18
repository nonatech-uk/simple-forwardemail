=== Simple ForwardEmail ===
Contributors: nonatech
Tags: smtp, email, forward email, healthchecks, monitoring
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight SMTP plugin for Forward Email with email logging and Healthchecks.io monitoring.

== Description ==

Simple ForwardEmail configures WordPress to send email via Forward Email's SMTP service. It includes built-in email logging and Healthchecks.io integration for independent delivery monitoring.

**Features:**

* SMTP sending via Forward Email (or any SMTP provider)
* Email logging with success/failure tracking
* Healthchecks.io heartbeat - proves the plugin and cron are alive
* Healthchecks.io failure pings - immediate alert when an email fails
* Dashboard with send/fail stats (24h, 7d, 30d)
* Encrypted credential storage (AES-256)
* wp-config.php constant support for credentials
* Test email from the settings page
* Automatic log cleanup (configurable retention)
* No external dependencies, no CDN, no tracking

**Why not use WP Mail SMTP / FluentSMTP?**

Those plugins support 10+ email providers you don't need. Their monitoring and reporting features are paywalled. This plugin does one thing well: Forward Email SMTP with visibility.

== Installation ==

1. Upload the `simple-forwardemail` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to ForwardEmail > Settings
4. Enter your Forward Email SMTP credentials
5. Optionally add your Healthchecks.io ping URL
6. Send a test email to verify

**Recommended:** Define credentials in wp-config.php instead of the database:

    define( 'SFE_SMTP_USER', 'you@yourdomain.com' );
    define( 'SFE_SMTP_PASS', 'your-generated-password' );
    define( 'SFE_HEALTHCHECKS_URL', 'https://hc-ping.com/your-uuid' );

== Frequently Asked Questions ==

= What SMTP settings do I need for Forward Email? =

* Host: smtp.forwardemail.net
* Port: 465 (SSL) or 587 (TLS)
* Username: Your alias email address
* Password: Generated in Forward Email dashboard under My Account > Domains > Aliases > Generate Password

= How does the Healthchecks.io integration work? =

Two signals:

1. **Heartbeat** - A scheduled ping (every 12h or 24h) that proves the plugin is active and WordPress cron is running. If the heartbeat stops, Healthchecks alerts you.
2. **Failure ping** - When an email fails to send, the plugin immediately pings the /fail endpoint so you know right away.

This means you get alerted if: the site goes down, the plugin is deactivated, cron breaks, or an email actually fails.

= Why encrypt the Healthchecks URL? =

Your Healthchecks ping URL is a secret - anyone with it could send false pings. Encrypting it at rest in the database prevents exposure if the database is compromised.

= Can I use this with providers other than Forward Email? =

Yes. It works with any SMTP provider. Just change the host, port, and credentials.

== Changelog ==

= 1.0.0 =
* Initial release
