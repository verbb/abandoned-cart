# Sending and Scheduling

Abandoned Cart does its work in a short background run. Each run ends recoveries whose time is up, finds carts that have gone quiet and starts their sequences, then sends the emails that are due. This page explains how to schedule that run, which carts it picks up, and how to check that sending is healthy.

## Scheduling the Run

Run the command every five minutes from cron. Replace both paths with the PHP executable and Craft project for your server:

```shell
*/5 * * * * /path/to/php /path/to/project/craft abandoned-cart/recoveries/run
```

The command sends emails itself, so it doesn't depend on Craft's queue. Each run prints a one-line summary, such as `Enrolled 3, skipped 1, sent 5, failed 0, not sent 0, ended 2.`, which is useful in cron logs.

If you can't add a cron job, Abandoned Cart falls back to Craft's queue. While at least one sequence is on, any site or control panel request queues a run (shown as **Sending abandoned cart emails** in the queue) when there hasn't been one for five minutes. Craft's queue must be running for those jobs to be processed, which it is by default. On a quiet site, though, requests can be hours apart, and emails go out late. Cron keeps the timing regular.

Only one run happens at a time. If a run starts while another is still working, it waits a few seconds, then stops and reports that another run is already in progress.

## Which Carts Are Picked Up

A cart is picked up when it's an incomplete Commerce order with at least one item and an email address, and it has gone without changes for the **Abandoned After** time. Carts without an email address can't be emailed, so Abandoned Cart ignores them. Each run picks up at most 100 carts, and the next run continues where it left off; the [`maxEnrollmentsPerRun`](../get-started/configuration.md#maxenrollmentsperrun) setting changes that limit.

Before a cart starts a sequence, Abandoned Cart checks, in order, whether its customer is on Do Not Email or has unsubscribed, whether the address is at a blocked domain, whether the customer has given consent, and whether the store only emails returning customers. Then it finds the first sequence the cart matches and checks the contact limit. Finally, plugins and modules can exclude a cart with the [`beforeEnroll` event](../developers/events.md#the-beforeenroll-event). A cart that fails a check is recorded as skipped, with the reason, so you can see on the Carts screen why it wasn't emailed. [Carts](carts.md#skipped-carts) lists the reasons.

Each customer follows one cart at a time. If a customer abandons a newer cart while an older one is still in recovery, the older one stops, with the reason **Started a newer cart**, and the newer one takes its place.

## Recovery Windows

Three settings decide how long Abandoned Cart keeps working on a cart and how it counts the outcome. Each has a default under **Settings → General Settings**, and each sequence can override them.

**Recovery Ends After** (seven days by default) is how long a cart stays in recovery after it's abandoned. Once it's up, any emails that haven't been sent are cancelled, and the cart's recovery links show an expired page instead of restoring the cart.

**Attribution Window** (five days by default) is how long after an email a completed order still counts as recovered, even if the customer didn't click. A customer who reads your email, then types your store's address into their browser and buys, counts. An order more than five days after the last email doesn't.

**Contact Limit** (seven days by default) stops customers who abandon often from receiving a stream of reminders. A new cart doesn't start a sequence if the same customer was sent a recovery email for another cart within this time. Only emails that were actually sent count.

## System Status

**Abandoned Carts → Settings → System Status** checks the things that most often stop emails from going out:

- **Sequences**: whether any sequence is switched on.
- **Scheduler**: when the background run last happened. It needs attention if the run hasn't happened for more than 30 minutes.
- **Sending**: whether sending is paused, and the address emails come from.
- **Recent Failures**: how many emails couldn't be sent in the last seven days.
- **Email Templates**: whether every email template's files can be found.
- **Consent**: whether a consent field is in use, and whether it exists.

The page also summarises the last run. **Run Now** starts a run straight away, which is handy after changing a setting or while testing. It needs the **Manage sequences and send emails** permission.

## Pausing All Sending

**Pause All Sending** in **Settings → General Settings** holds every recovery email from every sequence. It's meant for emergencies, such as discovering a mistake in an email that's already going out. Carts keep entering sequences while sending is paused, and their emails wait. When you switch it off, emails that are due go out on the next run, except those whose cart has reached the end of its recovery period in the meantime.

Turning off a single sequence is different: it also stops new carts from entering that sequence, while other sequences carry on as normal.

You can also pause sending from your configuration file, for example on development and staging environments that hold a copy of your customers' addresses. [Configuration](../get-started/configuration.md#environment-specific-settings) shows how.

## Failures and Retries

Emails go out through Craft's mailer, using the sender in **Settings → Email**. If the mailer reports a failure, Abandoned Cart tries again 15 minutes later, then 30 minutes after that. After three tries, the email is marked **Failed**, and the cart's history shows the mailer's reason. Staff with the **Manage sequences and send emails** permission can choose **Retry** beside a failed email on the cart's page to send it again straight away.

If a run is interrupted while sending, such as by a server restart, nobody can tell whether the email left the mail server. Abandoned Cart marks it failed rather than risk sending it twice, and the history says so.

The Overview's **Needs Attention** figure and System Status both count recent failures, so they're the first place to look when emails seem to be missing.

## History Retention

**Keep History For** (365 days by default) controls how long finished and skipped carts stay in the history. Older records are removed during Craft's garbage collection, and they leave the Overview's totals at the same time. Carts still in recovery are never removed, and neither are customers or their Do Not Email status. The minimum is 30 days.
