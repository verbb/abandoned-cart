# Restored Cart Notice

When a customer follows a recovery link, Abandoned Cart restores their cart and sends them to your cart page with a short notice: "Welcome back. Your cart is just as you left it." A visible welcome reassures the customer that they've arrived in the right place. The notice doesn't mention discounts, so if the email carried a discount code, you can also show the code that's been applied. Both go in your cart template.

## Check the Return Page

The customer lands on the **Return Page** set in **Abandoned Carts → Settings → General Settings**, which is `shop/cart` by default. Set it to the path of your cart page, such as `cart`. A sequence can override it under **Override Recovery Defaults**, for example to send customers from a particular product range to a different page.

## Display the Notice

Abandoned Cart stores the notice as a Craft flash message under the `notice` key. A flash message is kept in the customer's session until the request that reads it has finished, so it appears on the page they land on and is gone when they move on.

Some layouts already print flash messages, often by looping over `craft.app.session.getAllFlashes()`. A loop like that shows the notice too. Layouts that only print the `success` and `error` keys, which Craft and Commerce use for their own messages, won't show it. If yours doesn't show the `notice` key, add this to your cart template where you'd like the notice to appear, usually above the line items:

```twig
{% set notice = craft.app.session.getFlash('notice') %}

{% if notice %}
    <p class="notice" role="status">{{ notice }}</p>
{% endif %}
```

Reading the message with `getFlash()` doesn't remove it until the next request, so every place that reads it during the same page shows it. Add the snippet only when your layout doesn't already show the `notice` key, or the customer sees the notice twice.

## Show the Applied Discount

When the email the customer followed has a discount code, Abandoned Cart applies it to their cart as a coupon code before redirecting. Show it in the cart so the customer knows it's there:

```twig
{% set cart = craft.commerce.carts.cart %}

{% if cart.couponCode %}
    <p>{{ 'Discount code {code} has been applied.' | t({ code: cart.couponCode }) }}</p>
{% endif %}
```

Commerce decides whether the discount actually applies, using the discount's own conditions. If the cart doesn't qualify, such as when it's below the discount's minimum order value, the code is on the cart but the total is unchanged. To show the customer what the code changed, list the cart's discount adjustments:

```twig
{% for adjustment in cart.getAdjustmentsByType('discount') %}
    <p>{{ adjustment.name }}: {{ adjustment.amountAsCurrency }}</p>
{% endfor %}
```

When the loop shows nothing, the code is on the cart without a discount applying to it.

## Test the Notice

Turn on a sequence on a development site, then add something to a cart and enter your own email address at checkout without completing the order. Abandoned Cart only picks the cart up once it's gone without changes for the **Abandoned After** time (an hour by default) and the scheduled run has processed it, as described in [Sending & Scheduling](../feature-tour/sending-scheduling.md#scheduling-the-run). The cart then appears under **Abandoned Carts → Carts**. Wait for its first email, or open the cart and use **Send Now** to send it straight away.

Follow the link in the email. If you abandoned the cart while signed in to an account, follow the link while signed in to that account, because a cart that belongs to an account only reopens for that account and anyone else is sent to the login page first. Your cart page should show the notice and, if the email has a discount code, the applied code.

## Change the Notice Wording

The notice comes from Abandoned Cart's translations. To change it, create a site translation file for your language, such as `translations/en/abandoned-cart.php`, and return the original text with your replacement:

```php
<?php

return [
    'Welcome back. Your cart is just as you left it.' => 'Welcome back. We kept your cart for you.',
];
```
