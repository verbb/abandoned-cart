# Recover High-Value Carts with a Discount

A customer who leaves $400 in their cart is worth more effort than one who leaves a $15 pair of socks. In this guide, you'll set up a separate sequence for carts worth $200 or more. It sends three emails, and the last one includes a 10% discount code that's applied to the customer's cart when they come back. Carts under $200 carry on getting your default sequence, without a discount.

By the end, you'll have a discount in Commerce, a switched-on **High-Value Carts** sequence above your default sequence, and a way to check that it's picking up the right carts.

## What You'll Need

You'll need the default sequence reviewed and working, as described in [Create Your First Sequence](../get-started/create-your-first-sequence.md). You'll also need the **Manage sequences and send emails** permission in Abandoned Cart, and permission to create discounts in Commerce. Admins have both.

It helps to know two terms from [How Abandoned Cart Works](../get-started/how-abandoned-cart-works.md). A sequence's **audience** decides which carts it's for, and each cart gets the first switched-on sequence in the list whose audience it matches.

Sequences are stored in each environment's database, so set this up on the site where it'll run, usually your live site. The sequence starts switched off, so nothing is sent while you work on it.

## Create the Discount in Commerce

Abandoned Cart applies a discount code, but Commerce decides what the code does. So the discount comes first.

1. In the control panel, go to **Commerce → Store Management** and open **Discounts**.
2. Create a discount named "Come back 10", taking 10% off the order.
3. Under **Coupons**, add the code `COMEBACK10`.
4. Under **Match Order**, add a condition that the order's total is at least $200.
5. Set **Per Email Address Discount Limit** to `1`, so each customer can only use it once.
6. Save the discount.

Steps 4 and 5 matter because the code is the same for every customer. Without a minimum, someone who passes the code on lets anyone use it on a small order. With it, the code only works on the kind of cart this sequence is for. Commerce's own documentation covers the other discount options.

## Create the Sequence

Go to **Abandoned Carts → Sequences** and choose **New Sequence**. In the **Start From a Preset** dialog, choose **High-Value Carts**. It's a good starting point because it already has a **Cart Value** audience and two emails with a more personal tone.

The new sequence opens, switched off and placed just above the default sequence in the list. Under **Who Gets This Sequence**, **Cart Value** is chosen with **at least** and an empty amount. Enter `200`. Until you do, the sequence matches no carts at all.

The amount is compared with the cart's total, which includes shipping and tax once the customer has chosen them. Select **Save**.

## Add the Discount Email

The preset has two emails, sent straight away and a day later. Add a third for the discount:

1. Choose **+ Add email** at the end of the timeline. The new email starts with a delay of one day.
2. In **Start From**, choose "Last chance for a discount on your cart", under **Reminders, Then a Discount**. This fills in wording that tells the customer the code is applied when they return.
3. Change **Send** to `2` days, so the last email arrives two days after the second.
4. In **Discount Code**, choose `COMEBACK10`.
5. Select **Save**.

The preview beside the fields now shows the code in a highlighted box. If you'd like to mention it in the wording too, add `{discountCode}` to the message, and it's replaced with the code in each email.

The discount goes in the last email on purpose. Customers who were coming back anyway usually return after the first or second email, so they don't learn to wait for a code.

The three emails now go out about an hour, a day and three days after the customer last changed their cart. That fits comfortably within the default seven-day recovery period. If you add a later email, check that it still fits, as [Sequences](../feature-tour/sequences.md#timing) explains.

## Check the Order of Your Sequences

Go back to **Abandoned Carts → Sequences**. High-Value Carts should be above any sequence that would also take a $200 cart, such as one with the **All Carts** audience, because a cart gets the first sequence it matches. Drag it higher if it isn't. The default sequence always stays last.

## Try It with a Real Cart

On the sequence's page, find **Try It With a Real Cart** in the sidebar and choose one of your store's incomplete carts worth more than $200. You'll see one of these:

- "Matches: cart value at least …" means the cart would get this sequence.
- "Matches, but … is higher in the list and would take it first" means another sequence would take it. Move High-Value Carts above that one.
- "Doesn't match" means the cart is under the amount, or the amount isn't saved yet.

Then try a cart under $200 to check it doesn't match.

To see the emails as a customer would, open the third email, choose a real cart in **Previewing with**, then choose **Send Test** and enter your own address. The test's links are placeholders, so they don't restore a cart or apply the code.

## Switch It On

Switch on **Sequence Is On** and select **Save**. From now on, carts worth $200 or more that become abandoned get this sequence. Carts that were already abandoned before you switched it on carry on as they were.

## Check It's Working

After a few days, open **Abandoned Carts → Carts** and choose **High-Value Carts** in the sidebar to see only the carts in this sequence. A recovered cart's page shows the code the customer used, such as **Used COMEBACK10**, or **No discount used** if they ordered after an earlier email.

To test the whole path yourself, add more than $200 of products to a cart on your site with your own email address, and leave it. Once it has gone an hour without changes and the background run has picked it up, it appears on the Carts screen. Open it and use **Send Now** until the third email arrives. Following its button should take you to your cart with the discount applied.

If the code is on the cart but the total hasn't changed, the cart doesn't meet the discount's conditions in Commerce. Check its minimum and any other conditions, and whether your own test orders have already used up its limit for your email address.

Once the sequence has run for a few weeks, **Sequence Performance** on the Overview compares it with your default sequence. [Is It Working?](is-it-working.md) explains how to read it.
