# Overview

The Overview is the first screen under **Abandoned Carts**. It shows what cart recovery is earning your store, how carts move from abandoned to recovered, and which sequences and emails are doing the work.

![The Overview for the last 30 days, with a Right Now strip of carts in recovery, emails scheduled, value in recovery and Needs Attention, Recovered Revenue with a daily chart against the previous 30 days, Recovery Rate, Recovered Orders and Revenue per Email Sent, the From Abandoned to Recovered funnel, the Sequence Performance table, Recent Recoveries and Why Carts Didn't Recover](../../screenshots/overview.png)

## Before Recovery Starts

On a new install, before any cart has entered a sequence and while no sequence is on, the Overview shows a setup checklist: check your sending details, review the default sequence's emails, send yourself a test and turn on recovery. Beside it, **Last 30 Days** shows the value left in incomplete carts over the past month, read from your Commerce orders, and how many of those carts had an email address. Those are the carts your emails could have reached. [Create Your First Sequence](../get-started/create-your-first-sequence.md) follows the checklist step by step.

Once a sequence is on, the Overview explains what happens next until the first cart enters a sequence. Results usually start appearing within a day or two.

## The Right Now Strip

A strip across the top shows the current state of recovery: how many carts are in recovery, how many emails are scheduled for the next 24 hours, the value of the carts in recovery, and how many emails failed to send in the last seven days under **Needs Attention**. Selecting a failure count opens System Status, which needs the **View System Status** permission.

## Results for a Period

Choose the last 7, 30 or 90 days. Each figure is compared with the period before it, so you can see whether a change to your emails made a difference. Carts are counted in the period in which they were abandoned, with two exceptions noted below.

- **Recovered Revenue** is the total of the orders that were recovered, with the number of orders and their average value. Its chart shows recovered revenue by the day each order was placed, against the previous period, so the chart doesn't always add up to the total above it.
- **Recovery Rate** is the share of carts that were sent at least one email and went on to be recovered.
- **Recovered Orders** counts recovered orders, and how many came from first-time customers.
- **Revenue per Email Sent** divides recovered revenue by the number of emails sent during the period, which helps you judge whether an extra email in a sequence is worth it.

**From Abandoned to Recovered** shows the funnel: carts abandoned, carts emailed, carts whose customer clicked through, and carts recovered. Under each stage is the value left in abandoned carts, the share of carts that could be emailed and how many were skipped, and the share of emailed carts that clicked through and that were recovered. The info icon beside the heading links to the skipped carts, so you can see why they weren't emailed.

Carts from a [Blocked Domain](../get-started/configuration.md#blockeddomains) are usually bots or test orders, so the Overview leaves them out of its totals, its funnel, **Why Carts Didn't Recover** and the frequent-abandoner count. The funnel's info icon says how many it left out. They're still listed under **Carts → Skipped**.

## Sequence Performance

The **Sequence Performance** table compares your sequences for the chosen period: carts entered, emails sent, click rate, recovery rate and revenue. Here the recovery rate is the share of carts that entered the sequence and were recovered, so it can differ from the headline **Recovery Rate**, which only counts carts that were sent an email. A bar beside each rate helps you compare sequences at a glance. Expand a sequence to compare its emails, which shows where customers come back and whether a later email earns its place.

**Recent Recoveries** lists the latest recovered carts, with the email the customer clicked before ordering, or a note that they bought without clicking an email, and any discount code they used.

**Why Carts Didn't Recover** groups the carts that left recovery without a credited order: the sequence finished without a purchase, the customer started a newer cart, they unsubscribed or were added to Do Not Email, the cart was emptied or deleted, they ordered without an email's help, staff stopped the emails, or an email failed to send. A large share for one reason is a prompt to review the matching setting. Carts that were skipped before any email are in the funnel instead. When customers have abandoned three or more carts in the last 30 days, a link underneath says how many and opens the Customers list.

## How Recoveries Are Credited

A cart counts as recovered when its customer completes the order within the **Attribution Window** after the last email the cart was sent, five days by default. The customer doesn't have to click. Someone who reads the reminder and comes back by typing your address still counts.

The recovery is credited to the last email the customer clicked. If they didn't click any, it's credited to the last email sent. The cart's page shows which applied, as **Clicked before ordering** or **Bought without clicking**.

A cart whose order was completed before any email was sent isn't counted as recovered, and nor is one completed after the attribution window closed. Both appear under **Not recovered**, with the reason, so they don't inflate the results.
