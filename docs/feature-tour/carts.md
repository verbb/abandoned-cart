# Carts

The Carts screen lists every abandoned cart Abandoned Cart has dealt with, so you can see what happened to a particular customer's cart and step in when you need to. Open it from **Abandoned Carts → Carts**.

## The Carts List

Each row is one abandoned cart, showing the customer, the cart's value, the sequence it entered, its progress through that sequence's emails, its status and when it was abandoned. Search by email address or name. The sidebar lists the statuses, each with its number of carts, and then your sequences, so you can show just the carts that entered one sequence:

- **In recovery**: the cart is in a sequence and has emails still to come. The **Detail** column says when the next one is due.
- **Recovered**: the customer completed the order after an email, within the attribution window.
- **Not recovered**: the cart's recovery ended without a recovered order. The **Detail** column gives the reason.
- **Skipped**: the cart was never emailed. The **Detail** column gives the reason.

Carts without an email address aren't listed, because there's no way to email the customer.

## The Cart Page

Select a cart to open its page. The heading shows where the cart is up to: when the next email goes out, how much was recovered and from which order, or why recovery ended.

For a recovered cart, the page shows which email the order was credited to, whether the customer clicked before ordering or bought without clicking, whether it was their first order, and the discount code they used, if any. [Overview](overview.md#how-recoveries-are-credited) explains how credit is decided.

**Journey** shows each step: when the cart was abandoned, each email with its status, any clicks, and the order. **Full History** lists everything Abandoned Cart did with the cart, including emails that couldn't be sent and why, and changes to the cart that moved its emails later.

**Cart Contents** lists the items with their thumbnails. Each uses the variant's thumbnail, or else its product's: the Assets field set to **Use for element thumbnails** in the variant or product field layout, which is the same image Commerce shows in its product index. Items without one show their first letter instead.

The sidebar starts with **Can be emailed**. Staff with the **Manage Do Not Email** permission can switch it off to add the customer to [Do Not Email](customers.md#do-not-email), which stops their emails, or back on to remove them. It can't be switched on for a customer who unsubscribed. Below it are the cart's status, the customer with a link to their [customer page](customers.md), whether they have an account and how many orders they've completed before, the sequence and its audience, the cart's value, when it was abandoned and last changed, and a link to the order in Commerce.

## Stepping In

Staff with the **Manage sequences and send emails** permission can act on a cart that's in recovery:

- **Send Now** sends the next scheduled email straight away. The emails after it move earlier by the same amount, so they keep their gaps instead of arriving in a burst. It works while the cart's sequence is off, but not while all sending is paused.
- **Stop Emails** ends the cart's recovery, so it receives no more emails. Its reason is recorded as **Stopped by staff**.
- **Retry** appears beside an email that failed to send, in the cart's **Journey**. It sends that email again straight away, with a fresh set of automatic retries if it fails again. It isn't available once the cart has left recovery, or while all sending is paused.

To stop every email to a customer, rather than for one cart, add them to Do Not Email from their customer page.

## Why Recovery Ends

When a cart leaves recovery without being recovered, its page and the **Not recovered** view show one of these reasons:

| Reason | What Happened |
| --- | --- |
| Emails finished | Every email in the sequence was sent, and the recovery period ended without an order. |
| Recovery period ended | The recovery period ended before every email could be sent. |
| Started a newer cart | The customer abandoned another cart, which took over. |
| Unsubscribed | The customer unsubscribed from cart reminders. |
| Added to Do Not Email | Staff added the customer to Do Not Email. |
| Ordered before any email | The customer completed the order before the first email was sent. |
| Ordered after the attribution window | The customer completed the order, but too long after the last email to count. |
| Cart emptied | All the items were removed from the cart. |
| Cart deleted | The Commerce order was deleted. |
| Stopped by staff | Someone chose **Stop Emails**. |

## Skipped Carts

A skipped cart was abandoned but never entered a sequence. Its page explains the reason in full:

| Reason | What It Means |
| --- | --- |
| Didn't opt in | A consent field is in use, and it wasn't switched on for this customer. |
| On Do Not Email | The customer is on Do Not Email. |
| Unsubscribed | The customer has unsubscribed from cart reminders. |
| Blocked domain | The email address is at one of the Blocked Domains. |
| Not a returning customer | Only Returning Customers is switched on, and the customer hasn't ordered before. |
| Contacted recently | The customer was sent an email about another cart within the Contact Limit. |
| No sequence was on | No switched-on sequence matched the cart, usually because Everyone Else is off. |
| Excluded by a plugin | A plugin or module excluded the cart, sometimes with its own explanation. |

Skipped carts appear in the Overview too, so you can see how many carts your rules hold back. If a large share are skipped for one reason, review the matching setting under **Settings → General Settings**.
