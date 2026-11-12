# Custom Email Templates

Abandoned Cart's bundled email design works without any template files, and most stores can use it as it is. Write your own template when recovery emails need to match your other emails: your logo and colours, your fonts, product images, or a layout your designer has already built.

A custom template controls the layout. Editors still write each email's subject, heading, message and button label on the sequence page, and your template decides where those appear. That keeps the wording editable without touching code.

## Create the Template

Start from a copy of the bundled design, so you begin with something that already works. Go to **Abandoned Carts → Settings → Email Templates** and choose **New Email Template**. You'll need to be an administrator, on an environment where admin changes are allowed.

1. Enter `Branded` for **Name** and `branded` for **Handle**.
2. Enter `_emails/abandoned-cart/html` for **HTML Template**.
3. Enter `_emails/abandoned-cart/text` for **Plain Text Template**.
4. Switch on **Copy Templates**.
5. Save the template.

Abandoned Cart copies the bundled design to `templates/_emails/abandoned-cart/html.twig` and `templates/_emails/abandoned-cart/text.twig` in your Craft project. It never overwrites a file that already exists, so if you've created these files yourself, they're left alone.

To use the template, open an email on the sequence page and choose **Branded** for **Email Template**, or make **Branded** the default on the Email Templates screen so every email that doesn't choose a template uses it. The preview beside the email shows your template straight away. It doesn't watch the file, so after you edit `html.twig`, choose a different cart from **Previewing with** or reopen the email to see your change.

The plain text template is optional. If you leave **Plain Text Template** empty, Abandoned Cart makes the plain text version from your HTML, turning links into the link text followed by the address.

## A Minimal Template

The copied files are a complete, tested design. If you'd rather start from scratch, replace the contents of `templates/_emails/abandoned-cart/html.twig` with this, the smallest useful HTML template. It shows the editor's heading and message, the items in the cart, the discount code, the link back to the cart and the unsubscribe link:

```twig
<!DOCTYPE html>
<html lang="{{ craft.app.language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ subject }}</title>
</head>
<body>
    {% if previewText %}
        <div style="display:none;max-height:0;overflow:hidden;">{{ previewText }}</div>
    {% endif %}

    <h1>{{ heading }}</h1>

    {{ message }}

    <table role="presentation" width="100%">
        {% for item in cart.items %}
            <tr>
                <td>
                    {{ item.title }}{{ item.description ? ' (' ~ item.description ~ ')' }}
                    × {{ item.qty }}
                </td>
                <td align="right">{{ cart.formatMoney(item.subtotal) }}</td>
            </tr>
        {% endfor %}
        <tr>
            <td><strong>{{ 'Subtotal' | t }}</strong></td>
            <td align="right"><strong>{{ cart.formatMoney(cart.subtotal) }}</strong></td>
        </tr>
    </table>

    {% if discountCode %}
        <p>{{ 'This code is added to your cart when you return' | t }}: <strong>{{ discountCode }}</strong></p>
    {% endif %}

    <p><a href="{{ recoverUrl }}">{{ buttonLabel }}</a></p>

    <p>
        <small>
            {{ storeName }}{{ storeAddress ? ' · ' ~ storeAddress }}<br>
            <a href="{{ unsubscribeUrl }}">{{ 'Unsubscribe from cart reminders' | t }}</a>
        </small>
    </p>
</body>
</html>
```

`message` already contains HTML paragraphs, so output it directly rather than wrapping it in a `<p>`. The hidden `previewText` element at the top sets the line most inboxes show after the subject.

Always include `unsubscribeUrl`. Abandoned Cart adds unsubscribe headers that mail apps show beside the sender, but customers who read the email itself also need a visible way to stop reminders.

## Template Variables

Your template receives these variables, whether it's rendering a real email, a preview or a test:

| Variable | Contains |
| --- | --- |
| `subject` | The email's subject, with tokens replaced. |
| `previewText` | The email's preview text, with tokens replaced. |
| `heading` | The email's heading, with tokens replaced. |
| `message` | The email's message as HTML, with tokens replaced. Blank lines in the editor become paragraphs. |
| `buttonLabel` | The button label, or "Return to your cart" when the email doesn't set one. |
| `discountCode` | The email's discount code, or `null`. |
| `recoverUrl` | The link that restores the customer's cart. |
| `unsubscribeUrl` | The link that unsubscribes the customer from cart reminders. |
| `cart` | A summary of the cart, described below. |
| `order` | The Commerce order, or `null` when previewing or testing with the sample cart. |
| `site` | The Craft site the cart belongs to. |
| `storeName` | The name of the Commerce store the cart belongs to. |
| `storeAddress` | The store's location address from Commerce's store settings on one line, or `null`. |
| `email` | The email from the sequence, for settings the other variables don't cover, such as `email.position` (its place in the sequence, starting at 1). |
| `isTest` | Whether this is a preview or a test send, rather than a real email. |

The `cart` variable holds plain values, so your template works the same for real carts, previews and tests:

| Property | Contains |
| --- | --- |
| `cart.items` | The cart's items. Each has `title`, `description` (such as a variant's title or chosen options, or `null`), `qty`, `price`, `subtotal`, `url` (the product's page, or `null`) and `purchasableId` (the variant's ID, or `null` for the sample cart). |
| `cart.total` | The cart's total, as a number. |
| `cart.subtotal` | The total of the items before shipping, tax and discounts. |
| `cart.itemCount` | The number of items, counting quantities. |
| `cart.currency` | The cart's currency code. |
| `cart.firstName`, `cart.lastName`, `cart.fullName` | The customer's name, from the cart's addresses or their account. These can be empty. |
| `cart.email` | The customer's email address. |
| `cart.number` | The Commerce order number. |
| `cart.isSample` | Whether this is the sample cart used for previews and tests. |

Use `cart.formatMoney()` to format any amount in the cart's currency, such as `{{ cart.formatMoney(cart.total) }}`.

Emails render in the language of the customer's order, or the language of the cart's site when the order doesn't have one. Static text passed through Craft's `t` filter, such as `{{ 'Subtotal' | t }}`, appears in that language when you provide site translations. Developers can choose a different language, such as one stored on the customer's account, with the [`resolveLanguage` event](../developers/events.md#the-resolvelanguage-event).

## Show Product Images

The `cart` summary doesn't include images, because each store keeps them in a different field. For anything beyond the summary, use `order`, the Commerce order itself. This example assumes your products have an Assets field with the handle `productImage`. In `html.twig`, use it in place of the `{% for item in cart.items %}` loop in the items table, or wherever you want the items to appear:

```twig
{% if order %}
    {% for lineItem in order.lineItems %}
        {% set product = lineItem.purchasable.owner ?? null %}
        {% set image = product ? product.productImage.one() : null %}

        <p>
            {% if image %}
                <img src="{{ image.url }}" width="80" alt="{{ image.alt ?? product.title }}">
            {% endif %}
            {{ lineItem.description }}
        </p>
    {% endfor %}
{% else %}
    {% for item in cart.items %}
        <p>{{ item.title }}</p>
    {% endfor %}
{% endif %}
```

Previews and tests with the sample cart have no `order`, so the `else` branch shows the item titles instead. Email clients need absolute image URLs, so check that the asset volume's base URL includes your domain.

## The Plain Text Template

A plain text template receives the same variables. If you copied the bundled design, it's in `templates/_emails/abandoned-cart/text.twig`. To write your own, replace that file's contents with something like this. It turns off Twig's HTML escaping, because plain text has no markup to protect, and converts `message` from HTML to text by turning each paragraph end into a blank line before removing the tags:

```twig
{% autoescape false %}
{{ heading }}

{{ message | replace({'</p>': "\n\n"}) | striptags | trim }}

{% for item in cart.items %}
- {{ item.title }} × {{ item.qty }}: {{ cart.formatMoney(item.subtotal) }}
{% endfor %}

{{ buttonLabel }}: {{ recoverUrl }}

{{ 'Unsubscribe from cart reminders' | t }}: {{ unsubscribeUrl }}
{% endautoescape %}
```

## Test the Template

Open an email that uses your template, choose one of your store's carts from **Previewing with**, then choose **Send Test** and enter your address. The test uses that cart's real items and `order`, while its links are placeholders. Check the result in the mail apps your customers use, on both desktop and mobile.

If an email can't be rendered, the preview shows the Twig error. **Settings → System Status** warns you when a template's file can't be found, such as when the template exists in project config but the file hasn't been deployed.

To add variables of your own, such as recommended products, use the [`defineVariables` event](../developers/events.md#the-definevariables-event).
