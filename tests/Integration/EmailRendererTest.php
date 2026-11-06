<?php

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\CartSummary;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\EmailTemplate;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

function renderEmail(array $attributes, ?CartSummary $cart = null): verbb\abandonedcart\models\RenderedEmail
{
    $email = new Email(array_merge([
        'sequenceId' => AbandonedCart::$plugin->getSequences()->getFallbackSequence()->id,
        'subject' => 'Your cart is saved, {firstName}',
        'previewText' => 'We kept {itemCount} items for you.',
        'heading' => 'You left something behind',
        'message' => "Hi {firstName},\n\nYour cart is still waiting.",
        'buttonLabel' => 'Return to your cart',
    ], $attributes));

    return AbandonedCart::$plugin->getEmailRenderer()->render($email, $cart ?? CartSummary::sample(), null, ['recoverUrl' => 'https://shop.test/recover/abc', 'unsubscribeUrl' => 'https://shop.test/unsubscribe/xyz']);
}

it('renders the bundled design with tokens, cart items and links', function() {
    $rendered = renderEmail(['discountCode' => 'SAVE10']);

    expect($rendered->subject)->toBe('Your cart is saved, Amelia')
        ->and($rendered->previewText)->toBe('We kept 3 items for you.')
        ->and($rendered->html)->toContain('You left something behind')
        ->and($rendered->html)->toContain('<p>Hi Amelia,</p><p>Your cart is still waiting.</p>')
        ->and($rendered->html)->toContain('Leather Card Wallet')
        ->and($rendered->html)->toContain('SAVE10')
        ->and($rendered->html)->toContain('https://shop.test/recover/abc')
        ->and($rendered->html)->toContain('https://shop.test/unsubscribe/xyz')
        ->and($rendered->text)->toContain('Return to your cart: https://shop.test/recover/abc');
});

it('renders a site template, with the variables 4.x templates expect', function() {
    $path = Craft::$app->getPath()->getSiteTemplatesPath() . '/_emails/legacy.twig';
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, '<p>{{ heading }}</p><a href="{{ checkoutLink }}">{{ discount ?: "none" }}</a>');

    $templates = AbandonedCart::$plugin->getEmailTemplates();
    $template = new EmailTemplate(['name' => 'Legacy', 'handle' => 'legacy', 'htmlTemplate' => '_emails/legacy']);
    $templates->saveTemplate($template);

    try {
        $rendered = renderEmail(['emailTemplateId' => $template->id, 'discountCode' => 'FREESHIP']);

        expect($rendered->html)->toBe('<p>You left something behind</p><a href="https://shop.test/recover/abc">FREESHIP</a>')
            ->and($rendered->text)->toBe('You left something behind' . "\n\n" . 'FREESHIP (https://shop.test/recover/abc)');
    } finally {
        $templates->deleteTemplate($templates->getTemplateByHandle('legacy'));
        @unlink($path);
    }
});

it('renders Twig carried over in 4.x subjects inside the sandbox', function() {
    $rendered = renderEmail(['subject' => 'Still deciding, {{ "friend" | upper }}?']);

    expect($rendered->subject)->toBe('Still deciding, FRIEND?');
});
