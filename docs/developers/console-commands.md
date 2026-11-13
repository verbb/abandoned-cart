# Console Commands

Abandoned Cart includes console commands you can run from your Craft project’s directory.

## Reminders

::: reference
### `abandoned-cart/reminders/schedule-emails`

Finds carts that have become abandoned since the last run, removes expired abandoned-cart records and schedules any reminder emails that are due. Run it regularly from a cron job, as described in [Setup](docs:feature-tour/usage#setup). The reminders are sent by Craft’s queue, so make sure the queue is also running. This is the controller’s default action, so `abandoned-cart/reminders` runs it too.

| Option | Description | Default |
| --- | --- | --- |
| `--silent` | Whether to hide the command’s output, which is useful in a cron job. | `false` |

```shell
php craft abandoned-cart/reminders/schedule-emails
```

```shell
php craft abandoned-cart/reminders/schedule-emails --silent
```
:::
