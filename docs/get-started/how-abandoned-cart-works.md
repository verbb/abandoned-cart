# How Abandoned Cart Works

Before you review emails and switch recovery on, it helps to know what happens to a cart from the moment a customer leaves it. This page follows one cart from start to finish and explains the terms the rest of these docs use. Each step links to the page that covers it in detail.

## A Customer Leaves a Cart

Amelia adds a wallet and a weekend bag to her cart, enters her email address at checkout, then closes the tab without paying. In Commerce, her cart is an incomplete order.

Abandoned Cart only looks at incomplete orders that have at least one item and an email address. Customers usually give their email address at checkout, and a customer who's signed in to their account has it already. A cart without an email address can't be emailed, so it's never picked up and doesn't appear anywhere in Abandoned Cart.

## The Cart Counts as Abandoned

A cart counts as **abandoned** once it has gone the **Abandoned After** time without changes, an hour by default. Any change to the order, such as adding an item or entering an address, starts that wait again. The **Abandoned** date shown on a cart's page is when the customer last changed the cart, which is where the wait began.

## The Background Run Picks It Up

Abandoned Cart does its work in the **background run**, a short task that should happen every five minutes. Your developer usually schedules it with cron, a scheduled task on your web server. Each run ends recoveries that are over, picks up carts that have counted as abandoned, and sends the emails that are due. [Sending and Scheduling](../feature-tour/sending-and-scheduling.md) explains how to schedule it and check that it's happening.

Nothing is picked up while every sequence is switched off. A sequence also only takes carts that became abandoned after it was switched on, so turning recovery on never sends reminders about carts that were left weeks ago.

## Checks Before Any Email

Before Amelia's cart gets any email, Abandoned Cart checks that she can be emailed. A cart is **skipped** if her address is on Do Not Email, she has unsubscribed, her email domain is blocked, she hasn't given consent where a consent field is in use, or the store only emails returning customers and she hasn't ordered before. A skipped cart is listed on the Carts screen with the reason, and it isn't emailed. [Carts](../feature-tour/carts.md#skipped-carts) lists every reason.

## The Cart Gets a Sequence

A **sequence** is a series of up to five emails, and each sequence has an **audience**, which decides the carts it's for, such as carts worth $200 or more or customers who have ordered before. Abandoned Cart checks the cart against your switched-on sequences from the top of the list down, and the cart gets the first one whose audience it matches. The **default sequence** is always last and takes every cart that reaches it. A fresh install creates it as **All Other Carts**, with three emails.

One more check happens here. The **Contact Limit** (seven days by default) stops a customer who abandons often from starting a new sequence too soon after their last recovery email. A cart held back by it is skipped too.

Amelia's $238 cart matches no other sequence, so it gets the default sequence. [Sequences](../feature-tour/sequences.md#how-carts-choose-a-sequence) explains the order and audiences in full.

## The Cart Is in Recovery

The time a cart spends in its sequence is its **recovery**, and the cart is shown as **In recovery**. Its emails go out on schedule, with email 1 timed from when the cart counted as abandoned and each later email timed from the one before. Each email's button is a **recovery link**, which puts the cart back in the customer's browser and takes them to your cart page. [Recovery Links](../feature-tour/recovery-links.md) explains what a link does and when it can't restore a cart.

If Amelia changes her cart while it's in recovery, her remaining emails move later, because she's still shopping. If she leaves a newer cart while this one is in recovery, the newer cart takes over the recovery. It keeps the same sequence and email times, so she isn't emailed more often, and the emails still to come show what's in the newer cart.

A recovery lasts until one of these happens:

- the **recovery period** ends, seven days after the cart was abandoned by default (the **Recovery Ends After** setting), which cancels any emails not sent yet
- the customer completes any order, from this cart or another one
- the customer unsubscribes, or staff add them to Do Not Email
- staff stop the emails, the cart is emptied or deleted, or a newer cart takes over

[Carts](../feature-tour/carts.md#why-recovery-ends) lists every reason a recovery ends.

## The Order Is Credited

Two days later, Amelia reads email 2, follows its link and pays. Her cart is now **Recovered**.

An order counts as recovered when the customer completes it within the **Attribution Window** (five days by default) after the last email they were sent. It can be the same cart or a different one, such as a cart on another device, and the customer doesn't have to click. The recovery is credited to the last email whose link brought the customer back to their cart, or to the last email sent when they didn't use a link. An order completed before any email was sent, or after the attribution window, ends the recovery without counting as recovered. [Overview](../feature-tour/overview.md#how-recoveries-are-credited) explains how credit shows in your results.

## Where Everything Is Kept

Your settings, email templates and sequence presets are saved with your site's code and deployed from your development site, along with the rest of your project. Sequences, their emails, customers, Do Not Email and every cart's history live in each environment's database instead, so they're edited on each site separately. [What Moves Between Environments](configuration.md#what-moves-between-environments) has the full list and what it means for staging and production.

Next, follow [Create Your First Sequence](create-your-first-sequence.md) to review the default sequence and switch it on.
