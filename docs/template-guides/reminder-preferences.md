# Reminder Preferences

Customers can always stop cart reminders with the unsubscribe link in an email, but some look for the setting in their account instead, alongside their addresses and order history. This guide adds an "Email me about items I leave in my cart" checkbox to an account page on your site. Signed-in customers can turn reminders off, and turn them back on later, without waiting for an email.

The form needs a page that only signed-in customers can reach, such as the account area most Commerce stores already have. The example assumes a template at `templates/account/preferences.twig`, shown at `/account/preferences`.

## Read the Customer's Current Preference

`craft.abandonedCart.getCurrentCustomer()` returns Abandoned Cart's record for the signed-in customer, matched by their account or their email address. It returns `null` when nobody is signed in, or when the customer has never abandoned a cart and so has no record yet. A customer without a record can be emailed, so treat `null` as reminders being on.

The record's `status` is one of:

- `active`: cart reminders are on.
- `unsubscribed`: the customer turned them off, from an email or their account.
- `doNotEmail`: your staff have added the customer to Do Not Email. Customers can't change this themselves.

## Add the Form

Put this in your account template. It shows the current preference, saves changes through Abandoned Cart's `abandoned-cart/preferences/save` action, and returns the customer to the same page:

```twig
{% requireLogin %}

{% set customer = craft.abandonedCart.getCurrentCustomer() %}
{% set status = customer.status ?? 'active' %}

{% set success = craft.app.session.getFlash('success') %}
{% set error = craft.app.session.getFlash('error') %}

{% if success %}
    <p class="notice" role="status">{{ success }}</p>
{% endif %}

{% if error %}
    <p class="error" role="alert">{{ error }}</p>
{% endif %}

<h2>Cart Reminders</h2>

{% if status == 'doNotEmail' %}
    <p>We don't send you reminders about items left in your cart. Contact us if you'd like them.</p>
{% else %}
    <form method="post">
        {{ csrfInput() }}
        {{ actionInput('abandoned-cart/preferences/save') }}
        {{ redirectInput('account/preferences') }}
        {{ hiddenInput('emails', '0') }}

        <label>
            <input type="checkbox" name="emails" value="1" {{ status == 'active' ? 'checked' }}>
            Email me about items I leave in my cart
        </label>

        <button type="submit">Save</button>
    </form>
{% endif %}
```

The form posts an `emails` value of `1` to turn reminders on or `0` to turn them off. A cleared checkbox isn't sent at all, so the hidden input sends `0` in its place. When the checkbox is ticked, its `1` comes after the hidden input and replaces it.

The `requireLogin` tag sends signed-out visitors to your login page first. The action itself also requires a signed-in customer, so it can only change the preference of the person using the form.

## Check the Result

Sign in to your site as a customer, open the page, clear the checkbox and save. The page shows "We won't email you about carts you leave behind." In the control panel, the customer's status under **Abandoned Carts → Customers** changes to **Unsubscribed**, and any cart of theirs that was in recovery stops receiving emails.

Tick the checkbox and save again. The page shows "We'll remind you about carts you leave behind.", and the customer's status returns to **Can be emailed**. Carts they abandon from now on can enter a sequence again.

If staff have added the customer to Do Not Email, the form isn't shown. Should the action receive a request for such a customer anyway, it refuses with "Your cart reminder preference can't be changed here. Please contact us."

## Saving with JavaScript

The action also answers JSON requests, which suits an account page that saves each preference as soon as it changes. Send the same values with an `Accept: application/json` header, and the response includes the outcome message.

In the template above, replace the `<form>` inside the `{% else %}` branch with this. It keeps the `requireLogin` tag and reuses the `status` variable set at the top:

```twig
<label>
    <input type="checkbox" id="cart-reminders" {{ status == 'active' ? 'checked' }}>
    Email me about items I leave in my cart
</label>

<script>
document.getElementById('cart-reminders').addEventListener('change', async (event) => {
    const body = new FormData();
    body.append('{{ craft.app.config.general.csrfTokenName }}', '{{ craft.app.request.csrfToken }}');
    body.append('action', 'abandoned-cart/preferences/save');
    body.append('emails', event.target.checked ? '1' : '0');

    const response = await fetch('/', {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body,
    });
    const data = await response.json();

    alert(data.message);
});
</script>
```

Replace the `alert()` with however your site shows confirmation messages. A failed request, such as one for a customer on Do Not Email, returns an error status with the reason in `data.message`.
