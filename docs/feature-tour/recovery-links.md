# Recovery Links

Every recovery email carries two links for the customer: a recovery link, behind the email's button, that brings their cart back, and an unsubscribe link that stops cart reminders. This page describes what happens when a customer follows each one.

## Restoring a Cart

When a customer follows a recovery link, Abandoned Cart puts their cart back into their browser session, so the items are waiting exactly as they left them. If the email has a discount code, the code is applied to the cart at the same time. The customer then lands on the **Return Page**, usually your cart or checkout page, with a notice that reads "Welcome back. Your cart is just as you left it."

The click is recorded against the email the customer followed. You'll see it in the cart's history, and if they go on to complete the order, the recovery is credited to that email. [Restored Cart Notice](../template-guides/restored-cart-notice.md) explains how to display the notice in your cart template.

The Return Page comes from **Settings → General Settings**, unless the cart's sequence overrides it. A relative path such as `shop/cart` is resolved against the site the cart belongs to.

### Carts That Belong to an Account

Emails are sometimes forwarded, and recovery links shouldn't reveal one customer's cart to someone else. When a cart belongs to a customer with a password, the link only restores it for that customer. Anyone else, including the customer when they're signed out, is sent to your site's login page first and returns to the link once signed in. Carts from guest checkouts restore for whoever follows the link.

### When a Link Can't Restore the Cart

A recovery link works until the cart's recovery period ends, seven days after it was abandoned by default. In a few situations, the link shows a short page instead of restoring the cart:

| Page | When It Appears |
| --- | --- |
| This link has expired | The cart's recovery period has ended. |
| This order is already complete | The customer has already completed the order. |
| This cart is empty | The items have been removed from the cart since the email was sent. |
| This link isn't valid | The link is incomplete or altered, or the cart no longer exists. |

Each page has a button to continue shopping. Abandoned Cart shows these pages on their own by default; you can show them inside your site's layout instead by choosing a **Page Template** under **Settings → General Settings → Customer Pages**. [Styling Customer Pages](../template-guides/styling-customer-pages.md) explains how.

## Unsubscribing

Every email includes an unsubscribe link. The bundled design puts it in the footer, and your own templates should include it too. Following it shows a page asking the customer to confirm that they want to stop cart reminders. Only confirming unsubscribes them, so spam filters and link scanners that open every link in an email can't unsubscribe anyone by accident.

Abandoned Cart also adds `List-Unsubscribe` and `List-Unsubscribe-Post` headers to each email. Mail apps such as Gmail and Yahoo use them to show an unsubscribe option next to the sender, which unsubscribes the customer in one click without opening a page. Large mailbox providers expect these headers from senders of marketing email.

Unsubscribing stops every cart reminder to that email address, across all sequences, and ends any recovery that's under way. It doesn't affect Commerce's order confirmations or other emails your store sends. The customer's status changes to **Unsubscribed** on the [Customers](customers.md) screen.

Staff can't undo an unsubscribe. A customer can, in two ways. When a consent field is in use and the customer switches it on for an order they complete, their status returns to **Can be emailed**. Customers with an account can also turn cart reminders off and on again from an account page on your site, if you add the form described in [Reminder Preferences](../template-guides/reminder-preferences.md).
