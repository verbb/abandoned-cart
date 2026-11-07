# Emails and Email Templates

Each email in a sequence has two parts. The email itself holds its timing and wording: the subject, a heading, a short message, the button label and an optional discount code. Its email template decides how that content is laid out. Editors write and adjust emails on the sequence page, while email templates are set up once by an administrator or developer.

You don't need a template of your own to start. Abandoned Cart includes a responsive email design that shows the customer's cart, and every email uses it until you choose otherwise.

## Adding and Editing Emails

Open a sequence from **Abandoned Carts → Sequences**. Its emails appear in a timeline after **Cart abandoned**. Select an email to edit it in a panel beside the timeline, or choose **+ Add email** to add one at the end. **+ Add email here** between two emails inserts one at that point, and the emails after it keep their gaps.

A new email starts blank. **Start From** offers the emails from your [sequence presets](sequences.md#managing-presets), grouped by preset, if you'd like some starting wording. Choosing one fills in the fields, which you can then change.

A sequence can have up to five emails. Two or three suit most stores; more than that tends to annoy customers rather than bring them back.

Each email has these fields:

- **Send** sets when the email goes out, in minutes, hours or days. For the first email, it's counted from when the cart is abandoned. For later emails, it's counted from the email before.
- **Subject** is the email's subject line, and the only required field.
- **Preview Text** is the short line that most inboxes show after the subject.
- **Heading** is the large title at the top of the email.
- **Message** is the main text. Leave a blank line to start a new paragraph.
- **Button Label** is the text on the button that takes the customer back to their cart. It reads "Return to your cart" when left empty.
- **Discount Code** is a Commerce coupon code to apply when the customer returns through this email.
- **Email Template** chooses the design. **Default template** uses whichever template is currently the default.

The preview beside the fields updates as you type. Switch between **Desktop** and **Mobile** to check both widths, and use **Preview cart** to fill the preview with one of your store's real carts instead of the sample cart.

Changing an email's wording or discount code affects every copy of it that hasn't been sent yet, including emails already scheduled for carts in the sequence. Changing its delay only affects carts that enter the sequence afterwards. [Sequences](sequences.md#editing-a-sequence-in-use) explains why.

## Personalising With Tokens

Tokens are placeholders that Abandoned Cart replaces with details from each customer's cart. Write them in curly braces in the subject, preview text, heading, message or button label, or use the **Insert** buttons below the message to add one where your cursor is. For example, a subject of `{firstName}, your cart is saved` becomes "Amelia, your cart is saved".

| Token | Replaced With |
| --- | --- |
| `{firstName}` | The customer's first name, or "there" when it isn't known, so "Hi {firstName}" still reads naturally |
| `{lastName}` | The customer's last name |
| `{fullName}` | The customer's first and last names |
| `{email}` | The customer's email address |
| `{storeName}` | The name of the Commerce store the cart belongs to |
| `{siteName}` | The name of the site the cart belongs to |
| `{cartTotal}` | The cart's total, formatted in its currency |
| `{itemCount}` | The number of items in the cart, counting quantities |
| `{discountCode}` | This email's discount code |

Names come from the cart's billing address, then its shipping address, then the customer's user account.

### Twig in Subjects and Preview Text

A subject or preview text that contains `{{` or `{%` is also rendered as Twig, in a restricted sandbox, with the Commerce order available as `order`. Tokens are replaced first. This is mainly useful for translating a subject on a store that sells in several languages:

```twig
{{ 'Your cart is waiting' | t('site') }}
```

Emails render in the language of the customer's order, or the language of the cart's site when the order doesn't have one, so the subject above uses your site translations for that language. Developers can choose a different language with the [`resolveLanguage` event](../developers/events.md#the-resolvelanguage-event).

The heading, message and button label only support tokens, not Twig. The sandbox limits which filters, functions and object properties are available, and in previews and tests that use the sample cart, `order` is `null`. Tokens cover most needs and work everywhere, so prefer them for personal details and keep Twig for translation.

## Including a Discount

To offer a discount, first create one in Commerce with a coupon code, then choose the code in the email's **Discount Code** field, which lists your Commerce coupon codes with the name of their discount. You can also type a code that isn't listed, such as one from another plugin. When a customer returns through that email's link, the code is applied to their cart. The bundled design shows the code in a highlighted box, and `{discountCode}` lets you mention it in the wording.

Abandoned Cart applies the code but doesn't check whether it's valid for the cart: Commerce applies its own conditions, such as a minimum order value or an expiry date, when the customer reaches checkout. Test a discount with a real cart before relying on it. Discounts usually work best in the last email, so customers who were going to come back anyway don't receive one.

## Testing an Email

Choose **Send Test** in the email panel and enter an address. The test uses the cart chosen in **Preview cart**, or the sample cart if you haven't chosen one. Its subject starts with `[Test]`, and its recovery and unsubscribe links are placeholders that don't do anything.

To test every email in Everyone Else at once before you turn recovery on, use **Send Yourself a Test** on the Overview.

## Email Templates

Email templates are managed under **Abandoned Carts → Settings → Email Templates**, which only administrators can see. The list shows each template's name, whether it uses the bundled design or your own files, and how many emails use it.

A fresh install has one template, **Default**, that uses the bundled design. The bundled design shows the store name, the email's heading and message, the items in the cart with their quantities and prices, the discount code when there is one, the button back to the cart, and a footer with the store's address and an unsubscribe link. It also sends a plain text version.

### Creating a Template

Choose **New Email Template** and fill in:

- **Name** and **Handle**: what the template is called in the control panel, and how code refers to it.
- **HTML Template**: the path to a template in your site's templates folder, such as `_emails/abandoned-cart/html`. Leave it empty to use the bundled design.
- **Plain Text Template**: an optional path to a plain text version. When it's empty, the plain text version is made from the HTML.
- **Copy Templates**: copies the bundled design to the paths you entered, so you start from a working template. Existing files are never overwritten. This is only offered when you create a template.
- **Default Template**: makes this template the one used by emails that don't choose a template.

[Custom Email Templates](../template-guides/custom-email-templates.md) explains what your template files receive and walks through building one.

### The Default Template

There's always exactly one default template. Making another template the default switches it off for the previous one, and you'll need to make another template the default before you can delete the current one.

Deleting any other template is safe: the emails that used it fall back to the default.

### Templates Across Environments

Email templates are stored in Craft's project config, alongside your sections and fields, so you create and change them where admin changes are allowed and deploy them to other environments. The template files they point to belong in your site's templates folder, which you deploy with the rest of your templates. **System Status** warns you if a template's file can't be found.

Sequences and emails are stored in the database instead. Editors can adjust wording and timing on the live site without a deployment.
