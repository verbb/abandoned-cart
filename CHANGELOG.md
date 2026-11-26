# Changelog

## Unreleased

### Fixed
- Fixed a fresh `craft install` failing when project config lists Abandoned Cart before Commerce, as it does by default. The Commerce order foreign keys are now added once Commerce is installed. ([#104](https://github.com/verbb/abandoned-cart/issues/104))

## 5.0.1 - 2026-11-24

### Changed
- The Overview’s setup checklist now shows only on new installs, and stays until the first cart enters recovery, in place of the separate Recovery Is On page. Sites that already had Abandoned Cart go straight to the Overview’s results.
- The setup checklist now uses Plugin Kit’s shared checklist, without the progress bar or the Last 30 Days card.
- The setup checklist’s Review your emails step now ticks once the default sequence has been opened, not only once it’s been changed.
- Sequence Presets now shows an empty state when there are no presets, including straight after the last one is deleted.
- The Overview’s Recent Recoveries and Why Carts Didn’t Recover cards now show empty states when there’s nothing to list.
- The Carts and Customers screens’ empty-state headings no longer end in a full stop.

### Fixed
- Fixed the setup checklist marking Check your sending details as done when Craft’s sender address was missing, sending was paused or the consent field no longer existed.

## 5.0.0 - 2026-11-18

> {warning} Before upgrading to Abandoned Cart 5, review the [Upgrading from v4](https://verbb.io/craft-plugins/abandoned-cart/docs/v5/get-started/upgrading-from-v4) guide for breaking changes and required migration steps.

### Added
- Added sequences of up to five recovery emails, each with its own audience (cart value, returning customers, first-time customers, products or custom order rules), sites, timing and optional discount code. A default sequence, All Other Carts, catches every cart that no other sequence matches.
- Added sequence presets, managed under Settings → Sequence Presets and kept in project config, and the `Presets::EVENT_REGISTER_PRESETS` event for adding, changing, reordering or removing them in code.
- Added an email editor with tokens, a live preview using a sample cart in the store’s currency or a real cart, and test sends.
- Added email templates, stored in project config, with a responsive bundled design that needs no template files and an option to copy it to your own templates.
- Added the Overview, with recovered revenue, recovery rate, a recovery funnel, sequence and email performance, and a setup checklist for new installs. Carts from blocked domains are left out of its figures.
- Added the Carts screen and cart pages, with each cart’s journey and history, skipped carts with their reasons, Send Now, Stop Emails and Retry actions, and a Can be emailed switch.
- Added the Customers screen and customer pages, with Can be emailed, Unsubscribed and Do Not Email statuses, and an Abandoned Cart tab on Craft user accounts. Removing a customer from Do Not Email restores the status they had before, so an unsubscribed customer stays unsubscribed.
- Added a Delete Customer action, which removes a customer and their cart history for erasure requests.
- Added unsubscribe links and one-click `List-Unsubscribe` headers to every recovery email, with a confirmation page for customers.
- Added `craft.abandonedCart.getCurrentCustomer()` and the `abandoned-cart/preferences/save` action, so signed-in customers can turn cart reminders off or on from their account.
- Added the Consent Field, Blocked Domains, Only Returning Customers, Contact Limit, Attribution Window, Recovery Ends After and Pause All Sending settings, with per-sequence overrides for recovery timing and the return page.
- Added a Page Template setting so expired-link and unsubscribe pages can render inside the site's layout.
- Added System Status, which shows how the scheduler is being started, its last run and the last 24 hours of runs, the cron job to add, health checks for sending, recent failures, email templates and consent, and a Run Now action. It works where admin changes aren't allowed.
- Added the Manage sequences and send emails, Manage Do Not Email, Delete customers and View System Status permissions.
- Added events for sequences, emails, email templates, audiences, enrolment, consent, recovery links, sending, rendering and customers.
- Added the `compatibilityMode` setting, on by default, which keeps the 4.x email template variables, the `beforeMailSend` event, the 4.x console command and the 4.x scheduler URL working while sites update. The event, command and URL log deprecation warnings.

### Changed
- Upgrading from 4.x runs a one-time migration that converts 4.x settings, reminders, templates and the blocklist into the default sequence, email templates, Do Not Email statuses and the new settings, and imports 4.x reminder history.
- The scheduler command is now `abandoned-cart/recoveries/run`, and sends due emails itself rather than queueing a job for each email. When cron isn't available, web requests queue a run while a sequence is on.
- Recovery links now restore carts that belong to an account only for that customer, after signing in, and show a page explaining expired or invalid links.
- Recovery links now apply the email’s discount code to the restored cart, and the bundled email says so. Applying the code doesn’t count as a cart change, so the remaining emails keep their times.
- When a customer abandons a newer cart, it now takes over the recovery of their older one, keeping its sequence and the times of its remaining emails.
- Any order a customer completes now ends their recovery, even when it’s from a different cart, and counts as recovered when it comes within the attribution window after an email, with or without a click.
- Failed emails are now retried automatically, up to three tries.
- Abandoned Cart now requires Craft CMS 5.6 or later.

### Deprecated
- Deprecated the `checkoutLink`, `discount` and `currentSite` email template variables. Use `recoverUrl`, `discountCode` and `site` instead.
- Deprecated `Carts::EVENT_BEFORE_MAIL_SEND`. Use `Attempts::EVENT_BEFORE_SEND` instead.
- Deprecated the `abandoned-cart/reminders/schedule-emails` console command. Use `abandoned-cart/recoveries/run` instead.
- Deprecated the `abandoned-cart/carts/find-carts` scheduler URL and the `passKey` setting. Use `abandoned-cart/recoveries/run` from cron instead.

### Removed
- Removed or replaced the `firstReminderDelay`, `secondReminderDelay`, `firstReminderSubject`, `secondReminderSubject`, `firstReminderTemplate`, `secondReminderTemplate`, `discountCode`, `disableSecondReminder`, `restoreExpiryHours`, `recipientCooldownHours`, `staleRecordRetentionDays`, `recoveryUrl`, `previousOrderRequired`, `blacklist` and `includeBlacklisted` settings. The upgrade guide maps each one to its replacement.
- Removed the `Carts::EVENT_BEFORE_SAVE_CART` and `Carts::EVENT_AFTER_SAVE_CART` events, the `CartEvent` class and the 4.x cart model and record.
- Removed the 4.x `getCarts()` and `getService()` plugin services and their methods.
- Removed the bundled `abandoned-cart/emails/first` and `abandoned-cart/emails/second` email templates.

## 4.0.20 - 2026-11-01

### Fixed
- Fixed changes to the message in the `beforeMailSend` event not being used when sending abandoned cart emails.
- Fixed the app language and template mode not being restored after an abandoned cart email was sent or cancelled by the `beforeMailSend` event, which could affect later queue jobs.
- Fixed the log message for an abandoned cart email that couldn’t be sent not including the recipient’s email.
- Fixed the dashboard showing “undefined” links for guest customers and for carts whose order no longer exists.

## 4.0.19 - 2026-10-15

### Changed
- Updated Craft API usage to avoid deprecated calls.

### Fixed
- Fixed an error when loading the cart list and dashboard after an abandoned cart has been recorded. ([#103](https://github.com/verbb/abandoned-cart/issues/103))

## 4.0.18 - 2026-10-07

### Changed
- Require Verbb Base 3.0.20 or later so shared control-panel layouts use the current asset bundle namespace. ([verbb-base#3](https://github.com/verbb/verbb-base/issues/3))

## 4.0.17 - 2026-10-05

### Fixed
- Fixed a moderate-severity improper control of interaction frequency vulnerability.
- Fixed a moderate-severity race condition vulnerability.
- Fixed a moderate-severity credential exposure vulnerability.
- Fixed a low-severity capability expiration vulnerability.
- Fixed a low-severity race condition vulnerability.
- Fixed an error when finding abandoned carts on MySQL databases with mixed collations.

## 4.0.16 - 2026-10-02

### Changed
- Updated the required version of `verbb/base` to 3.0.19.

### Fixed
- Fixed a low-severity improper authentication vulnerability.

## 4.0.15 - 2026-09-30

### Changed
- Route plugin settings through the plugin’s authorized settings controller.

### Fixed
- Fixed a high-severity database query vulnerability.
- Fixed a moderate-severity authorization bypass vulnerability.

## 4.0.14 - 2026-09-23

### Changed
- Render reminder email subjects and template-path expressions in Twig sandbox.

## 4.0.13 - 2026-09-20

### Fixed
- Fix discount codes in the bundled reminder email templates for Craft Commerce 5.

## 4.0.12 - 2026-09-14

### Changed
- Updated documentation for clearer, more consistent guidance.
- Clarified optional PHP configuration with focused examples and linkable setting details.

## 4.0.11 - 2026-09-13

### Changed
- Normalize plugin settings.

## 4.0.10 - 2026-01-15

### Added
- Add `silent` option to `reminders/schedule-emails` console command.

## 4.0.9 - 2025-11-29

### Changed
- The “Expired” status now shows even if a recovery email has been sent.

## 4.0.8 - 2025-08-13

### Fixed
- Fix lack of translation support in emails.

## 4.0.7 - 2025-07-18

### Changed
- Update English translations.

### Fixed
- Fix an error when searching carts.

## 4.0.6 - 2025-02-20

### Added
- Add `isSent` to carts for a more deterministic way of knowing it has been sent.
- Add `includeBlacklisted` plugin setting.
- Add the ability to use domains as blacklisted items.

### Fixed
- Fix new abandoned carts not being updated when viewing the dashboard.
- Fix an error when trying to recover an expired cart.
- Fixed an error for Postgres. (thanks @boboldehampsink).
- Update deprecated `log()` function.

## 4.0.5 - 2025-02-03

### Fixed
- Fix some validation constraints for reminder delays.
- Fix an error with `AbandonedCart::info`.

## 4.0.4 - 2025-01-14

### Added
- Add “Previous Order Required” plugin setting.
- Allow reminder delays to be 0 (mainly for testing).

### Changed
- Abandoned carts order-matching now uses Commerce’s `activeCartDuration`.
- Abandoned Carts are now classified between 1-24 hours ago.

### Fixed
- Fix order-matching and timezone issues.

## 4.0.3 - 2024-11-21

### Fixed
- Fix an error when restoring carts.

## 4.0.2 - 2024-11-20

### Fixed
- Fix an error when restoring carts.

## 4.0.1 - 2024-11-15

### Added
- Carts are now sortable.
- Carts can be searched.
- Carts are now lazy-loaded for performance.

### Changed
- Carts are now paginated.
- Carts are now listed newest first by default.

### Fixed
- Fix PHP type issues for plugin settings, not working well with `.env` variables.

## 4.0.0 - 2024-10-25

### Changed
- Now requires Craft 5.0+.
- Now requires Craft Commerce 5.0+.

## 3.0.10 - 2026-01-15

### Added
- Add `silent` option to `reminders/schedule-emails` console command.

## 3.0.9 - 2025-11-29

### Changed
- The “Expired” status now shows even if a recovery email has been sent.

## 3.0.8 - 2025-08-12

### Fixed
- Fix lack of translation support in emails.

## 3.0.7 - 2025-07-18

### Changed
- Update English translations.

### Fixed
- Fix an error when searching carts.

## 3.0.6 - 2025-02-20

### Added
- Add `isSent` to carts for a more deterministic way of knowing it has been sent.
- Add `includeBlacklisted` plugin setting.
- Add the ability to use domains as blacklisted items.

### Fixed
- Fix new abandoned carts not being updated when viewing the dashboard.
- Fix an error when trying to recover an expired cart.
- Fixed an error for Postgres. (thanks @boboldehampsink).

## 3.0.5 - 2025-02-03

### Fixed
- Fix some validation constraints for reminder delays.
- Fix an error with `AbandonedCart::info`.

## 3.0.4 - 2025-01-14

### Added
- Add “Previous Order Required” plugin setting.
- Allow reminder delays to be 0 (mainly for testing).

### Changed
- Abandoned carts order-matching now uses Commerce’s `activeCartDuration`.
- Abandoned Carts are now classified between 1-24 hours ago.

### Fixed
- Fix order-matching and timezone issues.

## 3.0.3 - 2024-11-21

### Fixed
- Fix an error when restoring carts.

## 3.0.2 - 2024-11-20

### Fixed
- Fix an error when restoring carts.

## 3.0.1 - 2024-11-15

### Added
- Carts are now sortable.
- Carts can be searched.
- Carts are now lazy-loaded for performance.

### Changed
- Carts are now paginated.
- Carts are now listed newest first by default.

### Fixed
- Fix PHP type issues for plugin settings, not working well with `.env` variables.

## 3.0.0 - 2024-10-25

> {note} The plugin’s package name has changed to `verbb/abandoned-cart`. Abandoned Cart will need be updated to 3.0 from a terminal, by running `composer require verbb/abandoned-cart && composer remove mediabeastnz/craft-commerce-abandoned-cart`.

### Changed
- Migration to `verbb/abandoned-cart`.
- Now requires Craft 4.0+.
- Now requires Craft Commerce 4.0+.
- Changed `abandoned-cart/base/find-carts` action to `abandoned-cart/carts/find-carts`.
- Changed `abandoned-cart/base/restore-cart` action to `abandoned-cart/carts/restore-cart`.

### Removed
- Removed "Abandoned Carts Total" widget.
- Removed stats from Abandoned Carts Dashboard.

## 2.0.6 - 2024-07-18

### Fixed
- SQL issue when aggregating total from orders recovered

## 2.0.5 - 2024-01-05

### Added
- Added an event to allow for customisation or cancellation of emails

### Fixed
- Installation issues + PostgreSQL support

## 2.0.4 - 2023-08-31

### Fixed
- Merged PR that resolves empty carts sending emails

## 2.0.3 - 2023-02-22

### Fixed
- Ading missing translation on cart error
- Fixed export for missing purchasables

## 2.0.2 - 2023-01-11

### Fixed
- CP Widget error on Commerce 4.2 (thanks @jerome2710)

## 2.0.1 - 2022-07-11

### Fixed
- Find carts button is now only visible when in Test mode
- replaced `new Message()` with `mailer->compose()` to support custom mailers
- blacklist is now utilized better

## 2.0.0 - 2022-07-11

### Added
- Support for Craft & Commerce 4

## 1.6.5 - 2022-07-11

### Fixed
- Cart restore message can now be translated / supports multi-sites

## 1.6.4 - 2021-10-19

### Added
- Added multi-site to email templates (Thanks @billmn for the PR).
- `find-carts` route can be triggered without being logged in (still requires passKey)

## 1.6.3 - 2020-12-02

### Added
- Added user permissions for settings area. Ensure any "non admins" have the appropiate access after updating.

## 1.6.2 - 2020-10-02

### Fixed
- Merged pull request that fixes a dashboard widget error

## 1.6.1 - 2020-08-07

### Fixed
- Merged pull request to resolve spelling mistakes

## 1.6.0 - 2020-08-06

### Added
- Added support for environmental settings.

## 1.5.4 - 2020-08-06

### Fixed
- Fixed an issue relating to blacklisting emails
- Fixed an issues relating to timezones
- Merged a few simple pull requests

## 1.5.3 - 2020-04-08

### Added
- Emails can now be blacklisted. Simply a comma seperated list of emails to be ingored.
- Minor fix for empty dashboard state

## 1.5.2 - 2020-03-10

### Changed
- Tweaks to composer requirements

## 1.5.1 - 2020-03-09

### Added
- Minor tweaks to dashboard stats and layout.

## 1.5.0 - 2020-03-07

### Changed
- New widget using Commerce 3 stats. Allows you to view recovered total based on date range.
- Plugin now required Commerce 3.0+ which also means Craft CMS 3.4+ is required.

## 1.4.1 - 2020-03-07

### Fixed
- Fixed an issue relating to disabling 2nd reminder from `1.4.0`

## 1.4.0 - 2020-02-15

### Added
- The 2nd reminder can now be disabled so that only one reminder is sent.
- Added the simple ability to view all abandoned carts as json. This includes basic order and product details.
- Now tested with Craft 3.4+ and Commerce 3.0+

## 1.3.6 - 2019-04-10

### Fixed
- Minor fix from @billythekid for reocvery url not being set for incomplete orders.

## 1.3.5 - 2019-08-08

### Changed
- The from email and name that get pulled from systems settings are now parsed through Crafts environment function for people that were using environment variables in settings.

## 1.3.4 - 2019-08-05

### Fixed
- Removed cart variable from default example templates. Users using the default templates were experiencing issues with sessions

## 1.3.3 - 2019-08-05

### Changed
- Updated dashboard to show last updated date from the order and not the abandoned cart record
- Added order reference to dashboard table

## 1.3.2 - 2019-07-10

### Fixed
- Fixed an issue where test mode was not set on the dashboard when no orders have yet been abandoned

## 1.3.1 - 2019-07-04

### Fixed
- Fixed an issue on the dashboard where the order wasn't being found and displayed

### Changed
- Dashboard now displays if Test Mode is enabled or not
- If cart recovery fails (expired) and a custom recovery url is set, that will be used

## 1.3.0 - 2019-06-08

### Added
- Jobs can be triggered via a URL now using a secret key. This means people without access to cron jobs can use this plugin
- Added setting for a configurable secret key which is used to verify job requests

## 1.2.0 - 2019-06-08

### Added
- Test Mode 🎉 Test mode allows the queue to be bypassed and emails to instantly be sent allowing for quicker and easier testing
- Default email templates now include `{% set cart = craft.commerce.carts.cart %}` so that commerce helper functions like `|currency` can be used

## 1.1.6 - 2019-06-04

### Added
- Added support for `allowAdminChanges`, settings will be disabled by default

## 1.1.5 - 2019-05-30

### Changed
- New plugin icon 🎉

### Fixed
- Fixed the issue [#14] which involved abandoned carts with no email addresses. I've submitted a pull request to Craft Commerce to change how emails are stored when an order is first created

[#14]: https://github.com/mediabeastnz/craft-commerce-abandoned-cart/issues/14

## 1.1.4 - 2019-05-19

### Fixed
- Resolved a date deprecation warning

## 1.1.3 - 2019-05-10

### Fixed
- Abandoned carts still display if original Order is missing

## 1.1.2.1 - 2019-04-10

### Fixed
- Patch for last version, fixes missing folder

## 1.1.2 - 2019-04-10

### Fixed issue [#4]
- Fixed a bug that was introduced in version 1.1.1

[#4]: https://github.com/mediabeastnz/craft-commerce-abandoned-cart/issues/4

### Added
- Added the ability to change recovery url to soemthing other than shop/cart

## 1.1.1 - 2019-03-23

### Added
- Added pagination to the dashboard to handle sites with large amounts of orders

## 1.1.0 - 2019-03-19

### Added
- You can now manually trigger the job that searches for abandoned carts! Useful if you don't have access to cron jobs
- Added additional documention/instructions to help with setup

## 1.0.1 - 2019-02-20

### Fixed
- Breadcrumb link

## 1.0.0 - 2019-02-16

### Added
- Initial release
