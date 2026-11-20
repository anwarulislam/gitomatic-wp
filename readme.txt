=== Gitomatic WP ===
Contributors: alex91ckua, freemius

Tags: git, deployment, automator, updater

Requires at least: 4.6

Tested up to: 7.1

Stable tag: 1.3.0

License: GPL-3.0

Requires PHP: 7.0

License URI: http://www.gnu.org/licenses/gpl-3.0.html

Install and update plugins (and themes) hosted on your repo hosted on GitHub, Bitbucket, GitLab, or Gitea in a single click.

== Description ==
Deploy WordPress plugins and themes from GitHub, Bitbucket, GitLab, or Gitea. In Deployments, choose Add deployment, enter the repository, select a branch, and review the destination folder.

Use Add another branch to deploy into the same installation or choose a separate folder for an inactive copy. When branches share a folder, the most recent successful deployment supplies its files. Each saved branch has its own webhook URL. Configure your CI job to call that URL when its branch is pushed.

== Screenshots ==
1. Dashboard
2. Install Theme Page
3. Settings page

== Frequently Asked Questions ==
= Do I need Git installed on the server? =

No. Gitomatic WP does not require Git to be installed on the server.

= I have shared/cheap hosting, will it work on my server? =

Yes, as long as your server runs WordPress, the plugin will work fine.

= What git repositories are supported by Gitomatic WP? =

The plugin supports Bitbucket, GitHub, Gitea, and GitLab repositories.

= Is there any way to clear website cache after each deploy? =

Yes, it's possible. Plugin supports WP Rocket, WP-Optimize W3 Total Cache, LiteSpeed Cache, WP Super Cache, WP Fastest Cache and Autoptimize.

= How does Gitomatic WP improve website management? =

By automating the deployment process. Once set up, the plugin automatically updates your WordPress instance after each commit of the desired branch to your git repository, eliminating the need for manual file transfers via FTP.

= Can I use Gitomatic WP for multiple websites? =

Yes, the plugin can be used for multiple websites. Each website will require its own setup to connect to the respective git repository.

= Is there a cost associated with Gitomatic WP? =

Public and private repository deployments are free.

= Does it support WordPress Multisite? =

Yes, it does.

= Is there any risk of data loss when using Gitomatic WP? =

The plugin is designed to safely update your website with the latest changes from your git repository. However, as with any deployment tool, it\'s always recommended to have regular backups of your site.

= Does it support WP-CLI? =

Use wp gitomatic-wp list to see deployments. Run wp help gitomatic-wp for installation and update commands.

== Changelog ==
= 1.3.0 =
* Added a branch selector and a single deployment setup screen.
* Deploy multiple branches into one installation or keep separate plugin and theme copies.
* Added branch-specific webhook handling and removed the paid plan integration.

= 1.2.0 =
* Improved the admin deployment warning
* Revised Logs page now includes searchable, filterable deployment activity view
* Fixed package-type validation to prevent themes from being installed as plugins and plugins as themes
* Compatibility updates

= 1.0.13 =
* Added the ability to change the git branch without unlinking a package
* UI/UX updates
* Compatibility updates

= 1.0.12 =
* Added WP-CLI support
* Added WordPress 7 support
* UI/UX updates
* Bug fixes

= 1.0.11 =
* Added ability to unlink plugin or theme
* Flush cache: Breeze support added
* UI/UX updates

= 1.0.10 =
* Added ability to edit package credentials
* Compatibility updates

= 1.0.9 =
* Bitbucket "App Passwords" support added
* Small UI/UX updates
* Compatibility updates

= 1.0.8 =
* Compatibility updates

= 1.0.7 =
* GitHub "Fine-grained personal access tokens" support added

= 1.0.6 =
* Compatibility updates

= 1.0.5 =
* Support for self-hosted Gitea repositories

= 1.0.4 =
* GitLab groups support added

= 1.0.3 =
* Experimental support for self-hosted GitLab repositories

= 1.0.2 =
* Multisite support added

= 1.0.1 =
* Minor interface improvements
* Plugin is localizable now
* Ukrainian translation added

= 1.0 =
* Initial release

== Upgrade Notice ==

= Updating =
Automatic updates should work smoothly; however, always make sure to backup your site as a precaution.
