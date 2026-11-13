# Console Commands

Abandoned Cart includes console commands you can run from your Craft project’s directory.

## Recoveries

::: reference
### `abandoned-cart/recoveries/run`

Runs Abandoned Cart’s background run once. It ends recoveries whose time is up, starts sequences for carts that have gone quiet, then sends the emails that are due, and prints a one-line summary such as `Enrolled 3, skipped 1, sent 5, failed 0, not sent 0, ended 2.` Schedule it every five minutes from cron, as [Sending & Scheduling](../feature-tour/sending-scheduling.md#scheduling-the-run) explains. When another run is already in progress, it says so and exits without doing anything.

```shell
php craft abandoned-cart/recoveries/run
```
:::
