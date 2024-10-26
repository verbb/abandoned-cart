# Changelog

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
