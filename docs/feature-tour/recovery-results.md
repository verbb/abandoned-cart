# Recovery Results

Abandoned Cart reports what cart recovery is earning your store, how carts move from abandoned to recovered, and which sequences and emails are doing the work. The results are on **Abandoned Carts → Overview**, the first screen in the plugin's section, and anyone with the **Access Abandoned Cart** permission can see them. [Is It Working?](../user-guides/is-it-working.md) walks through reading them after your first month and what to change when a figure looks low.

![The Overview for the last 30 days, with a Right Now strip of carts in recovery, emails scheduled, value in recovery and Needs Attention, Recovered Revenue with a daily chart against the previous 30 days, Recovery Rate, Recovered Orders and Revenue per Email Sent, the From Abandoned to Recovered funnel, the Sequence Performance table, Recent Recoveries and Why Carts Didn't Recover](../../screenshots/overview.png)

## How Recoveries Are Credited

Every figure on this page depends on which orders count as recovered, so it helps to know the rule first. A cart counts as recovered when its customer completes an order within the **Attribution Window** after the last email the cart was sent, five days by default. The customer doesn't have to click, and the order doesn't have to come from the same cart. Someone who reads the reminder, comes back by typing your address and buys from a fresh cart still counts. Orders are matched to the customer by email address, or by their user account.

The recovery is credited to the last email whose link restored the customer's cart. A click that didn't restore it, such as on an expired link or one that asked the customer to sign in first, doesn't count. If no link restored the cart, the recovery is credited to the last email sent. The cart's page shows which applied, as **Clicked before ordering** or **Bought without clicking**, and when the order came from a different cart, it says so and links to that order, as [Recovered Carts](carts.md#recovered-carts) shows.

A cart whose customer completed an order before any email was sent isn't counted as recovered, and nor is one whose order was completed after the attribution window closed. Both appear under **Not recovered** on the Carts screen, with the reason, so they don't inflate the results.

## Choosing a Period to Compare

Choose the last 7, 30 or 90 days from the menu at the top of the Overview. Each figure is compared with the period before it, so you can see whether a change to your emails made a difference. In your first period there's nothing to compare with, so most figures show no change, and **Recovery Rate** shows its whole value as a rise.

Carts are counted in the period in which they were abandoned, so a cart left on the last day of the period is included even if it's recovered a few days later. The revenue chart and **Revenue per Email Sent** are the two exceptions, described below.

## Recovered Revenue and the Recovery Rate

The four headline figures answer how much recovery has brought in and how efficiently.

- **Recovered Revenue** is the total of the orders that were recovered, with the number of orders and their average value. Its chart shows recovered revenue by the day each order was placed, against the previous period, so the chart doesn't always add up to the total above it.
- **Recovery Rate** is the share of carts that were sent at least one email and went on to be recovered.
- **Recovered Orders** counts recovered orders, and how many came from first-time customers.
- **Revenue per Email Sent** divides recovered revenue by the number of emails sent during the period, which helps you judge whether an extra email in a sequence is worth it.

Recovered revenue is what the customer paid, after any discount. Discounts given through your emails aren't counted as a cost, so if a sequence offers a code, check how often it's used under **Recent Recoveries** or on each recovered cart's page.

## Where Carts Drop Out

**From Abandoned to Recovered** shows the funnel: carts abandoned, carts emailed, carts whose customer clicked through, and carts recovered. Under each stage is the value left in abandoned carts, the share of carts that could be emailed and how many were skipped, and the share of emailed carts that clicked through and that were recovered. Opens aren't tracked, so clicks are the measure of interest.

The info icon beside the heading says how many carts were skipped before any email, with a **See why** link to those carts, so you can see each one's reason. [Skipped Carts](carts.md#skipped-carts) explains the reasons.

## Comparing Sequences and Their Emails

The **Sequence Performance** table compares your sequences for the chosen period: carts entered, emails sent, click rate, recovery rate and revenue. Here the recovery rate is the share of carts that entered the sequence and were recovered, so it can differ from the headline **Recovery Rate**, which only counts carts that were sent an email. Counting this way means a sequence for high-value carts can be compared fairly with your default sequence. A bar beside each rate helps you compare sequences at a glance.

Expand a sequence to compare its emails, with each one's sends, click rate, recoveries credited to it and revenue. This shows where customers come back and whether a later email earns its place. A sequence that's switched off is still listed, with an **Off** label, so its earlier results stay visible.

## Recent Recoveries

**Recent Recoveries** lists the latest recovered carts, newest first. Each one shows the email the customer clicked before ordering, or a note that they bought without clicking an email, any discount code they used, and a **First order** label for a customer who hadn't ordered before. Select one to open its cart.

When most recoveries name an email, your emails are bringing people back directly. When most say the customer bought without clicking, the reminders may still be helping, but some of those customers would probably have bought anyway.

## Why Carts Didn't Recover

**Why Carts Didn't Recover** groups the carts that left recovery without a credited order: the sequence finished without a purchase, the customer started a newer cart, they unsubscribed or were added to Do Not Email, the cart was emptied or deleted, they ordered without an email's help, staff stopped the emails, or an email failed to send. Carts that were skipped before any email are in the funnel instead. [Why Recovery Ends](carts.md#why-recovery-ends) explains each reason a cart can leave recovery.

Most stores see **Sequence finished without a purchase** at the top, because most abandoned carts aren't recovered. A large share for another reason is a prompt to review the matching setting, such as the timing of your first email when many customers unsubscribe.

When customers have abandoned three or more carts in the last 30 days, a link underneath says how many and opens the Customers list, where [Customers Who Abandon Often](customers.md#customers-who-abandon-often) explains what to look for.

## Carts and Emails in Recovery Now

A strip across the top of the Overview shows the current state of recovery, whatever period you've chosen. It counts the carts in recovery and their total value, the emails scheduled for the next 24 hours, and, under **Needs Attention**, the emails that failed to send in the last seven days. Selecting a failure count opens System Status, which needs the **View System Status** permission. [Failures and Retries](sending-scheduling.md#failures-and-retries) explains what to do about them.

## Stores That Sell in Several Currencies

On a store that sells in more than one currency, the Overview adds totals from every currency together without converting them, and shows the sum in your primary store's currency. The same applies to the figures on the Sequences screen and customer pages. Read the money figures as a rough guide on these stores, and use each cart's page, which shows its own currency, for exact values.

## Carts from Blocked Domains

Carts from a [Blocked Domain](../get-started/configuration.md#blockeddomains) are usually bots or test orders, so the results leave them out of the totals, the funnel, **Why Carts Didn't Recover** and the count of customers who abandon often. The funnel's info icon says how many it left out. They're still listed under **Skipped** on the Carts screen.

## Before Recovery Starts

On a new install, the Overview shows a setup checklist in place of results until the first recovery email reaches a customer. Its steps are checking your sending details, reviewing the default sequence's emails, sending yourself a test, turning on recovery and emailing your first abandoned cart. Each step ticks itself off once Abandoned Cart sees it done, and the first stays unticked if something would stop emails going out, such as a missing sender address, **Pause All Sending** or a consent field that no longer exists. Once recovery is on, the last step says when the first email goes out, how many carts are in recovery and whether any email failed to send. [Create Your First Sequence](../get-started/create-your-first-sequence.md) follows the checklist step by step. The **Send yourself a test** and **Turn on recovery** steps only show their buttons to people with the **Manage sequences and send emails** permission. Everyone else sees the steps without the buttons.
