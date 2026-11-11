# Restored Cart Notice

When a customer follows a recovery link, Abandoned Cart restores their cart and sends them to your cart page with a short notice: "Welcome back. Your cart is just as you left it." A visible welcome reassures the customer that they've arrived in the right place, and confirms that any discount from the email has been applied. This guide shows how to display that notice, and the discount, in your cart template.

## Check the Return Page

The customer lands on the **Return Page** set in **Abandoned Carts → Settings → General Settings**, which is `shop/cart` by default. Set it to the path of your cart page, such as `cart`. A sequence can override it under **Override Recovery Defaults**, for example to send customers from a particular product range to a different page.

## Display the Notice

Abandoned Cart sets the notice as a Craft flash message, the same mechanism Commerce and Craft use for their own success messages. A flash message is stored in the customer's session and removed once it's read, so it appears once.

Many sites already display flash messages in their layout. If yours doesn't, add this to your cart template where you'd like the notice to appear, usually above the line items:

```twig
{% set notice = craft.app.session.getFlash('notice') %}

{% if notice %}
    <p class="notice" role="status">{{ notice }}</p>
{% endif %}
```

If your layout already shows flash messages, leave this out, because reading the message removes it, so only the first place that reads it shows it.

## Show the Applied Discount

When the email the customer followed has a discount code, Abandoned Cart applies it to their cart as a coupon code before redirecting. Show it in the cart so the customer knows it's there:

```twig
{% set cart = craft.commerce.carts.cart %}

{% if cart.couponCode %}
    <p>{{ 'Discount code {code} has been applied.' | t({ code: cart.couponCode }) }}</p>
{% endif %}
```

Commerce decides whether the discount actually applies, using the discount's own conditions. If the cart doesn't qualify, such as when it's below the discount's minimum order value, the code is on the cart but the total is unchanged. Commerce's own discount notices and the cart's adjustments are the best way to show the customer what changed.

## Test It

Turn on a sequence on a development site, then abandon a cart with your own email address and wait for its first email, or use **Send Now** on the cart's page. Follow the link in the email. Your cart page should show the notice and, if the email has a discount code, the applied code.

## Change the Notice Wording

The notice comes from Abandoned Cart's translations. To change it, create a site translation file for your language, such as `translations/en/abandoned-cart.php`, and return the original text with your replacement:

```php
<?php

return [
    'Welcome back. Your cart is just as you left it.' => 'Welcome back! We saved your cart for you.',
];
```
