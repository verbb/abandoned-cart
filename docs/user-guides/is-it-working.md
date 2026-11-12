# Is It Working?

After a month of sending recovery emails, you'll want to know whether they're bringing customers back, which emails do the work, and whether anything needs changing. This guide walks through the Overview in the order that answers those questions, and explains what to change when a figure looks low.

You'll need recovery switched on for about a month, and the **Access Abandoned Cart** permission. Open **Abandoned Carts → Overview** and choose **Last 30 days** at the top of the page.

## Comparisons in Your First Month

Each figure on the Overview is compared with the period before it. In your first month, that earlier period has no recoveries, so most figures show no change, and **Recovery Rate** shows its whole value as a rise. Read the figures on their own this month. From the second month on, the comparison tells you whether a change you made helped.

## How Much Has Been Recovered

**Recovered Revenue** is the total of the orders that counted as recovered, with the number of orders and their average value. **Recovered Orders** gives the count, and how many came from first-time customers.

Before you judge the total, it helps to know what counts. An order is recovered when the customer completes it within five days of the last email they were sent, the **Attribution Window**, whether or not they clicked the email. That's fair, because a reminder often brings someone back even when they type your address in themselves. It also means some recovered orders came from customers who would have bought anyway.

To see how many customers came back through an email, look at **Recent Recoveries**. Each recovery shows either the email the customer clicked before ordering, such as "After “Still thinking it over?”", or "Bought without clicking an email". When most say "After …", your emails are bringing people back directly. When most say "Bought without clicking", the emails may still be reminding people, but treat the total with more caution.

Recovered revenue is the amount the customer paid, after any discount. Discounts given through your emails aren't shown as a cost anywhere on the Overview, so if your sequences use a discount code, check how often it's used. **Recent Recoveries** and each recovered cart's page show the code the customer used.

## Where Carts Drop Out

**From Abandoned to Recovered** shows how far carts got, in four stages. Read the share under each stage, because each gap points to a different fix.

**Carts Abandoned** to **Emailed** shows how many carts could be emailed. A low share means many carts were skipped. Select the info icon beside the heading, then **See why**, to list the skipped carts with each one's reason. If most were skipped as **Didn't opt in**, your consent field is probably shown too late, at checkout, as [Consent](../feature-tour/customers.md#consent) explains. If most were **Contacted recently**, your **Contact Limit** may be longer than you need.

**Emailed** to **Clicked Through** shows how many customers followed a link. A low share suggests the emails aren't persuading people to open them or come back. Try a clearer subject line, a shorter message, or a different first delay. Opens aren't tracked, so clicks are the measure.

**Clicked Through** to **Recovered** shows how many customers who came back went on to buy. If plenty click but few buy, the problem is more likely on your site than in the email. Check that the **Return Page** lands on your cart, that the cart shows the restored items, and that any discount code is applying. The [Restored Cart Notice](../template-guides/restored-cart-notice.md) guide shows how to display the code in the cart.

## Which Emails Do the Work

**Sequence Performance** lists each sequence with the carts that entered it, emails sent, click rate, recovery rate and revenue. Expand a sequence to see the same figures for each of its emails. A recovered order counts for the last email the customer clicked, or else the last one sent.

Use this to decide whether every email earns its place. If the third email in a sequence brings back very few customers, compared with the unsubscribes it might cause, you could drop it, or try a discount there instead. **Revenue per Email Sent**, near the top of the page, helps with the same question across your whole store.

If you have more than one sequence, compare them here too. Their recovery rates count only the carts that entered each one, so a sequence for high-value carts can be compared fairly with your default sequence.

## Why Carts Didn't Recover

**Why Carts Didn't Recover** groups the carts that left recovery without a credited order. Most stores see **Sequence finished without a purchase** at the top, which is normal, because most abandoned carts aren't recovered.

A few groups are worth a closer look. A large share of **Unsubscribed or on Do Not Email** suggests the emails come too often or too soon, so consider a longer first delay or one fewer email. **Ordered, but not from an email** counts customers who bought before the first email or long after the last, so a large share there may mean your first email waits too long. **Email failed to send** should be zero. If it isn't, open **Abandoned Carts → Settings → System Status**, or ask an admin to.

**Needs Attention**, in the strip at the top of the Overview, also counts failed emails. Anything above zero is worth checking with whoever looks after the site.

## Making a Change and Checking It

Change one thing at a time, so you know which change made the difference. Then wait a full period before comparing, and keep in mind that changes don't all apply straight away. A change to an email's wording or discount code reaches every email that hasn't been sent yet. A change to an email's delay, or a new email, only applies to carts that enter the sequence afterwards, so it can take a week or more before the results reflect it.

Next month, open the Overview again with **Last 30 days**. The figures now compare with your first month, and the arrows beside them show whether your change helped.
