# Overview

The Overview is the first screen under **Abandoned Carts**. It shows what cart recovery is earning your store, how carts move from abandoned to recovered, and which sequences and emails are doing the work.

## Before Recovery Starts

Until a sequence is switched on, the Overview shows a setup checklist: check your sending details, review the Everyone Else emails, send yourself a test and turn on recovery. Beside it, **Last 30 Days** shows the value left in incomplete carts over the past month, read from your Commerce orders, and how many of those carts had an email address. Those are the carts your emails could have reached. [Setting Up Your First Sequence](../get-started/setting-up-your-first-sequence.md) follows the checklist step by step.

Once a sequence is on, the Overview explains what happens next until the first carts have been through recovery. Results usually start appearing within a day or two.

## Right Now

A strip across the top shows the current state of recovery: how many carts are in recovery, how many emails are scheduled for the next 24 hours, the value of the carts in recovery, and anything that **Needs Attention**, such as emails that failed to send. Selecting a failure count opens System Status.

## Results for a Period

Choose the last 7, 30 or 90 days. Each figure is compared with the period before it, so you can see whether a change to your emails made a difference. Carts are counted in the period in which they were abandoned.

- **Recovered Revenue** is the total of the orders that were recovered, with the number of orders and their average value. A chart shows it day by day against the previous period.
- **Recovery Rate** is the share of carts that were sent at least one email and went on to be recovered.
- **Recovered Orders** counts recovered orders, and how many came from first-time customers.
- **Revenue per Email Sent** divides recovered revenue by the number of emails sent, which helps you judge whether an extra email in a sequence is worth it.

**From Abandoned to Recovered** shows the funnel: carts abandoned, carts emailed, carts whose customer clicked through, and carts recovered, with the value left in abandoned carts and the number skipped before any email. **See why** opens the skipped carts.

Carts from a [Blocked Domain](../get-started/configuration.md#blockeddomains) are usually bots or test orders, so the Overview leaves them out of its totals, its funnel, **Why Carts Didn't Recover** and the frequent-abandoner count, and notes how many it left out. They're still listed under **Carts → Skipped**.

**Why Carts Didn't Recover** ranks the most common reasons carts left recovery without an order, along with the reasons carts were skipped. A large share for one reason, such as **Contacted recently**, is a prompt to review the matching setting.

## Sequence Performance

The **Sequence Performance** table compares your sequences for the chosen period: carts entered, emails sent, click rate, carts recovered and revenue. Expand a sequence to compare its emails, which shows where customers come back and whether a later email earns its place.

**Recent Recoveries** lists the latest recovered carts and the email that brought each one back. A link at the bottom points out customers who abandoned three or more carts in the last 30 days.

## How Recoveries Are Credited

A cart counts as recovered when its customer completes the order within the **Attribution Window** after the last email the cart was sent, five days by default. The customer doesn't have to click: someone who reads the reminder and comes back by typing your address still counts.

The recovery is credited to the last email the customer clicked. If they didn't click any, it's credited to the last email sent. The cart's page shows which applied, as **Clicked before ordering** or **Bought without clicking**.

A cart whose order was completed before any email was sent isn't counted as recovered, and nor is one completed after the attribution window closed. Both appear under **Not recovered**, with the reason, so they don't inflate the results.
