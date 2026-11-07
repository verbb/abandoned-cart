# Setting Up Your First Sequence

A sequence is the series of emails a customer receives after leaving a cart. Abandoned Cart installs with one ready-made sequence, the default sequence, called **All Other Carts**. It sends three friendly reminders: one hour after the cart is left, then a day later, then two days after that. This walkthrough takes you from a fresh install to that sequence emailing real carts, and shows you how to check it's working.

You'll need Abandoned Cart installed on a site running Craft Commerce, Craft's email settings configured so the site can send mail, and either an admin account or the **Manage sequences and send emails** permission. Do the final check on a development or staging site where you can create carts of your own.

## Check Your Sending Details

Open **Abandoned Carts → Overview**. Until a sequence is switched on, the Overview shows a short setup checklist, and its first step summarises how emails will be sent.

Recovery emails go out through Craft's mailer, using the sender name and address from **Settings → Email**. If the address shown isn't the one customers should see, change it there.

The checklist also tells you whether a consent field is in use. Without one, every abandoned cart with an email address can be emailed. If the law where you trade requires customers to opt in to marketing emails, add a Lightswitch field to your checkout (on the order or the customer's account) and choose it under **Settings → General Settings → Consent Field**. Only customers who switched it on are emailed. [Customers](../feature-tour/customers.md#consent) explains how the field is read.

## Schedule the Background Run

Abandoned Cart works through carts in a short background run. It finds carts that have gone quiet, starts their sequences and sends any emails that are due. Run it every five minutes from cron, using the PHP executable and Craft project path for your server:

```shell
*/5 * * * * /path/to/php /path/to/project/craft abandoned-cart/recoveries/run
```

If you can't add a cron job, Abandoned Cart queues a run from ordinary site and control panel traffic instead, as long as Craft's queue is running. Cron keeps the timing regular on quiet sites, so prefer it where you can. [Sending and Scheduling](../feature-tour/sending-and-scheduling.md) covers both options.

## Review the Emails

Choose **Review the Emails** in the checklist, or go to **Abandoned Carts → Sequences** and select **All Other Carts**. The sequence page shows a timeline. The cart is abandoned after an hour without changes, email 1 goes out straight away, email 2 a day later and email 3 two days after that.

Select an email to open it. You can change its subject, preview text, heading, message and button label, and insert tokens such as `{firstName}` to personalise them. The preview beside the fields updates as you type, using a sample cart. Save each email you change; once you've changed one, the checklist marks this step done. [Emails and Email Templates](../feature-tour/emails-and-email-templates.md) explains every field.

If you'd like the last email to include a discount, create a discount with a coupon code in Commerce first, then enter that code in the email's **Discount Code** field. Customers who return through that email have the code applied to their cart.

## Send Yourself a Test

Back on the Overview, enter your own address under **Send Yourself a Test** and send the test emails. You'll receive every email in the sequence, each with `[Test]` at the start of its subject and filled in with a sample cart. Links in test emails are placeholders, so they don't restore anything.

Check that the emails arrive, that the sender looks right, and that the wording reads well on your phone as well as your desktop. If nothing arrives, use the **Test** button under **Settings → Email** and check your mail provider's logs before going further.

## Turn On Recovery

Choose **Turn On** in the checklist, or switch on **Sequence Is On** on the sequence's page. From that moment, carts that become abandoned on your store are emailed. Carts that had already been abandoned before you turned it on are left alone, so long-forgotten carts don't suddenly receive a reminder.

The Overview changes to show that recovery is on. Once customers start coming back, it fills with results.

## Check It Works

With recovery turned on, go to your development or staging site, add a product to a cart and enter an email address you can read at checkout, then leave the order incomplete. Don't touch the cart again, because a cart counts as abandoned once it has gone an hour without changes. To try this without waiting, temporarily set **Abandoned After** to a minute or two in **Settings → General Settings**.

When the time has passed, run the background run yourself from your Craft project directory:

```shell
php craft abandoned-cart/recoveries/run
```

The command prints a summary such as `Enrolled 1, skipped 0, sent 1, failed 0, not sent 0, ended 0.` Because email 1 is set to go out straight away, it's sent in the same run. Open **Abandoned Carts → Carts** and you'll see the cart **In recovery**, with its next email scheduled.

Open the email and follow its button. Your browser lands on your cart page with the same items, and the cart's history in the control panel records the click. Complete the order, and the cart moves to **Recovered**, credited to the email you followed.

If the run reports a cart as skipped, open it from the **Skipped** view on the Carts screen. It shows the reason, such as a missing consent or a recent email to the same customer. If nothing is enrolled at all, check that the cart has an email address and items, that it's still incomplete, and that enough time has passed since it last changed.

Remember to restore **Abandoned After** if you changed it. From here, you can add sequences for particular customers or carts, such as a separate series for high-value carts. [Sequences](../feature-tour/sequences.md) explains how carts are matched to them.
