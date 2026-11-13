# Console Commands

Abandoned Cart includes console commands you can run from your Craft project’s directory.

## Recoveries

::: reference
### `abandoned-cart/recoveries/run`

Runs Abandoned Cart’s background run once. It ends recoveries whose time is up, starts sequences for carts that have gone quiet, then sends the emails that are due, and prints a one-line summary such as `Enrolled 3, skipped 1, sent 5, failed 0, not sent 0, ended 2.` Schedule it every five minutes from cron, as [Sending and Scheduling](../feature-tour/sending-and-scheduling.md#scheduling-the-run) explains. When another run is already in progress, it says so and exits without doing anything.

```shell
php craft abandoned-cart/recoveries/run
```
:::

## Deprecated Commands

::: reference
### `abandoned-cart/reminders/schedule-emails`

The 4.x command, deprecated in 5.0. While the [`compatibilityMode`](../get-started/configuration.md#compatibilitymode) setting is on, it logs a deprecation warning and runs `abandoned-cart/recoveries/run`, so existing cron jobs keep working. With the setting off, it fails with an error. Replace it in cron with `abandoned-cart/recoveries/run`, as [Upgrading from v4](../get-started/upgrading-from-v4.md#console-command) describes.

```shell
php craft abandoned-cart/reminders/schedule-emails
```
:::
