# Styling Customer Pages

Customers sometimes reach a page from Abandoned Cart instead of their cart: when a recovery link has expired, when the order is already complete, when the cart is empty or the link isn't valid, and when they unsubscribe. By default, these are standalone pages, with your site's name and a link back to the shop above a card that holds the message. You can display them inside your site's own layout instead, so they have your header, navigation and footer like the rest of the store.

## Prepare Your Layout

Abandoned Cart extends a template of your choosing and fills its `content` block. Most Craft sites already have a layout template that other templates extend. For this guide, suppose it's `templates/_layouts/site.twig`:

```twig
<!DOCTYPE html>
<html lang="{{ craft.app.language }}">
<head>
    <meta charset="utf-8">
    <title>{{ siteName }}</title>
    {# Your stylesheets #}
</head>
<body>
    {% include '_partials/header' %}

    <main>
        {% block content %}{% endblock %}
    </main>

    {% include '_partials/footer' %}
</body>
</html>
```

If your layout's main block has a different name, such as `main`, add a `content` block inside it, or create a small layout for Abandoned Cart that extends yours:

```twig
{# templates/_layouts/abandoned-cart.twig #}
{% extends '_layouts/site' %}

{% block main %}
    <div class="container">
        {% block content %}{% endblock %}
    </div>
{% endblock %}
```

Your layout is rendered without the variables a normal page has, such as `entry`. If the layout or its partials use one, give it a fallback, such as `{{ entry.title ?? siteName }}`, so the customer pages don't cause an error. Craft's global variables, such as `siteName`, `siteUrl` and `currentUser`, are available as usual.

## Choose the Template

Go to **Abandoned Carts → Settings → General Settings**, and under **Customer Pages**, enter `_layouts/site` (or `_layouts/abandoned-cart`) for **Page Template**. Save the settings. You can set the same path with the [`customerPageTemplate`](../get-started/configuration.md#customerpagetemplate) setting in `config/abandoned-cart.php` instead, which takes precedence over the field.

Abandoned Cart only uses the template if it exists. If the path is wrong, or the template hasn't been deployed to an environment, the standalone pages are shown instead.

## Check the Result

Links in test emails are placeholders, so the quickest page to check is the one for an invalid link, which doesn't need a real email. Visit a link Abandoned Cart can't recognise, such as `https://example.com/abandoned-cart/recover/test`, replacing `example.com` with your site's domain. You'll see the **This link isn't valid** page inside your layout, with a button to continue shopping.

## Style the Content

Each page's content is wrapped in an element you can target from your stylesheet:

```html
<div class="abandoned-cart-page" data-page="expired">
    <div aria-hidden="true"><svg>…</svg></div>
    <h1>This link has expired</h1>
    <p>…</p>
    <a href="…">Continue shopping</a>
</div>
```

The `data-page` attribute identifies the page: `notFound`, `expired`, `alreadyCompleted`, `cartEmpty`, `unsubscribe` or `unsubscribed`. The content is a white card with an icon and some inline spacing and button styles, so it looks reasonable in any layout. The button is either a link directly inside the card or, on the unsubscribe page, a `<button>` in a form. That page also has a plain "Keep getting reminders" text link inside a paragraph, which the child selector below leaves alone. To restyle the button to match your own, target it from your stylesheet:

```css
.abandoned-cart-page > a,
.abandoned-cart-page button {
    background: var(--brand-colour) !important;
    border-radius: 999px !important;
}
```

The `!important` is needed to override the inline styles.

## Change the Wording

The pages' text comes from Abandoned Cart's translations, so you can change it with a site translation file without touching a template. Create `translations/en/abandoned-cart.php` in your Craft project, using the language of your site, and return the original text and your replacement:

```php
<?php

return [
    'This link has expired' => 'Sorry, this cart link has expired',
    'Cart links only last a little while, to keep your details safe. Your items may still be in stock.' => 'Your cart is no longer saved, but the items may still be available in our shop.',
];
```

The same approach changes the text in the bundled email design, and the notice shown when a cart is restored.
