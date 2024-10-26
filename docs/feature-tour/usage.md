# Usage
Abandoned Carts will send a maximum of two emails, these emails can be configured to be sent after a certain amount of hours.

A responsive email template is included but can be overwritten with your own if preferred.

The email the customer receives includes a link that restores their cart. The plugin also uses this to detect clicks. Knowing if customers are opening/clicking emails is a great way to increase conversion.

Discounts can also be included in emails. Simply create a discount code in Craft Commerce and enter that code in
Abandoned Carts settings.

All abandoned cart emails are created as jobs and placed in Craft's queue.

## Abandoned Logic
Any cart will be marked as abandoned 1 hour after no activity. This is important to remember when adjusting the delay settings for the reminder emails. For example by default the 1st email will be sent 2 hours after the cart was last interacted with. Just remember to allow for that 1 hour delay upfront.

## Setup
The first step is to monitor carts when they turn into an abandoned state, and alert the owner of that cart about it.

To do this, you will either need to manually trigger this monitoring, or schedule it via a cron job.

### Manual Trigger
To manually trigger the process of detecting abandoned carts, you can use the following URL.

```
https://[www.website.com]/actions/abandoned-cart/carts/find-carts&passkey={{passKey}}
```

When visiting this endpoint, any carts that are deemed as abandoned will be added to the queue and processed (emails sent to the customer).

### Cron Job
An alternative is to use a Cron Job to automate this process from the command line.

```shell
*/5 * * * * php craft abandoned-cart/reminders/schedule-emails
```
