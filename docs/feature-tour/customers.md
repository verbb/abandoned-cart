# Customers

Abandoned Cart keeps a record for each email address that has abandoned a cart, linked to the customer's Craft user account when they have one. The record holds whether the customer can be emailed, and brings together their carts, the emails they've received and what they've spent. Open the list from **Abandoned Carts → Customers**.

## The Customers List

The list shows each customer's email status, how many carts they've abandoned, how many were recovered, their recovery rate and recovered revenue, and when they last abandoned a cart. Search by email address or name, and use the views along the top to show everyone, or only customers who **Can be emailed**, have **Unsubscribed** or are on **Do not email**.

Customers who abandoned three or more carts in the last 30 days are highlighted at the top. Someone who regularly fills a cart and leaves may be comparing prices, using the cart as a wishlist, or waiting for a discount, so they're worth a closer look before you send them more reminders.

## A Customer's Page

Select a customer to see their profile: carts abandoned in the last 30 days and all time, carts recovered and the revenue they brought in, emails sent and clicked, and their lifetime orders in Commerce. **What We've Noticed** points out patterns, such as a customer who often clicks but rarely buys, or one held back by the Contact Limit. Their carts are listed below, newest first, each linking to its [cart page](carts.md#the-cart-page).

When the customer was emailed recently, the page also says when they can next start a new sequence under the Contact Limit.

## Email Statuses

Every customer has one of three statuses:

- **Can be emailed**: the customer's carts can enter sequences, subject to your other settings.
- **Unsubscribed**: the customer used an unsubscribe link, or turned cart reminders off from their account on your site. Only the customer can reverse this, by opting in again at checkout or from their account. [Recovery Links and Unsubscribing](recovery-links-and-unsubscribing.md#unsubscribing) explains how.
- **Do not email**: staff have asked Abandoned Cart never to email this address about carts.

Unsubscribed and Do not email both stop any recovery that's under way straight away, and the customer's later carts are recorded as skipped.

## Do Not Email

Use Do Not Email when a customer asks you directly to stop, such as by phone, or for an address that should never receive reminders, such as a staff member who places test orders. You'll need the **Manage Do Not Email** permission.

On a customer's page, enter an optional note, such as "Asked by phone", and choose **Add to Do Not Email**. The note and the person who added it are shown on the page afterwards. To add several customers at once, select them in the list and choose **Add to Do Not Email**. To block an address that hasn't abandoned a cart yet, open the **Do not email** view and choose **Add Address**.

You can also switch off **Can be emailed** in a cart's sidebar.

Customers can't change a Do Not Email status themselves, even from their account. **Remove from Do Not Email** on the customer's page, or switching **Can be emailed** back on in a cart's sidebar, lets them be emailed again. Carts that were skipped while they were on the list aren't revisited.

Do Not Email records are kept permanently, whatever the **Keep History For** setting.

To stop emailing everyone at a domain, such as your own company's, add it to **Blocked Domains** under **Settings → General Settings** instead.

## Consent

If the law where you trade requires customers to opt in before receiving marketing emails, add a Lightswitch field for consent and choose it as the **Consent Field** under **Settings → General Settings**. Only customers who switched it on are emailed. Carts from everyone else are recorded as skipped, with the reason **Didn't opt in**.

The field is read from the order first, which suits a checkbox at checkout, and then from the customer's user account, which suits a preference in their account settings. A customer counts as not having agreed when neither the order nor their account has the field.

A consent field also gives unsubscribed customers a way back. When an unsubscribed customer completes an order with the consent field switched on, their status returns to **Can be emailed**.

Developers who store consent elsewhere, such as in a newsletter service, can decide it with the [`resolveConsent` event](../developers/events.md#the-resolveconsent-event).

## On User Accounts

Customers with a Craft user account have a tab named after the plugin on their account page in the control panel, under **Users**. It shows the same profile as their customer page, with a link to open it. The tab is visible to anyone with the **Access Abandoned Cart** permission.
