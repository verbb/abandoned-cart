<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\events\DefineEmailVariablesEvent;
use verbb\abandonedcart\events\ResolveLanguageEvent;
use verbb\abandonedcart\models\CartSummary;
use verbb\abandonedcart\models\Email;
use verbb\abandonedcart\models\RenderedEmail;

use Craft;
use craft\base\Component;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\helpers\Template;
use craft\mail\Message;
use craft\web\View;

use Throwable;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;

class EmailRenderer extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_DEFINE_VARIABLES = 'defineVariables';
    public const EVENT_RESOLVE_LANGUAGE = 'resolveLanguage';

    public const BUNDLED_HTML_TEMPLATE = 'abandoned-cart/_special/email/html';
    public const BUNDLED_TEXT_TEMPLATE = 'abandoned-cart/_special/email/text';


    // Public Methods
    // =========================================================================

    /**
     * Renders an email for a cart. `$urls` holds `recoverUrl` and `unsubscribeUrl`; previews and tests pass placeholders.
     */
    public function render(Email $email, CartSummary $cart, ?Order $order = null, array $urls = [], bool $isTest = false): RenderedEmail
    {
        $view = Craft::$app->getView();
        $originalMode = $view->getTemplateMode();
        $originalLanguage = Craft::$app->language;
        $language = $this->_resolveLanguage($cart, $order);
        $sites = Craft::$app->getSites();
        $originalSite = $sites->getCurrentSite();
        $cartSite = $cart->siteId ? $sites->getSiteById($cart->siteId) : null;

        try {
            Craft::$app->language = $language;

            // Templates see the cart's own site, so `siteUrl`, `currentSite` and site-specific content are right.
            if ($cartSite) {
                $sites->setCurrentSite($cartSite);
            }

            $tokens = $this->getTokens($email, $cart);
            $subject = $this->_renderText((string)$email->subject, $tokens, $order);
            $previewText = $this->_renderText((string)$email->previewText, $tokens, $order);
            $variables = $this->_getVariables($email, $cart, $order, $urls, $tokens, $isTest, $subject, $previewText);
            $template = $email->getEmailTemplate();

            if (!$template || $template->getUsesBundledDesign()) {
                $view->setTemplateMode(View::TEMPLATE_MODE_CP);
                $html = $view->renderTemplate(self::BUNDLED_HTML_TEMPLATE, $variables);
                $text = $view->renderTemplate(self::BUNDLED_TEXT_TEMPLATE, $variables);
            } else {
                $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
                $html = $view->renderTemplate($template->htmlTemplate, $variables);
                $text = $template->textTemplate ? $view->renderTemplate($template->textTemplate, $variables) : $this->htmlToText($html);
            }
        } finally {
            $view->setTemplateMode($originalMode);
            $sites->setCurrentSite($originalSite);
            Craft::$app->language = $originalLanguage;
        }

        return new RenderedEmail([
            'subject' => $subject,
            'previewText' => $previewText,
            'html' => $html,
            'text' => trim($text),
            'recoverUrl' => $urls['recoverUrl'] ?? '',
            'unsubscribeUrl' => $urls['unsubscribeUrl'] ?? '',
        ]);
    }

    /**
     * The values staff can put in a subject or message, written as `{firstName}`.
     */
    public function getTokens(Email $email, CartSummary $cart): array
    {
        $site = $cart->siteId ? Craft::$app->getSites()->getSiteById($cart->siteId) : Craft::$app->getSites()->getPrimarySite();

        return [
            'firstName' => $cart->firstName ?: Craft::t('abandoned-cart', 'there'),
            'lastName' => (string)$cart->lastName,
            'fullName' => $cart->getFullName(),
            'email' => (string)$cart->email,
            'storeName' => $this->getStoreName($cart),
            'siteName' => (string)$site?->getName(),
            'cartTotal' => $cart->formatMoney($cart->total),
            'itemCount' => (string)$cart->itemCount,
            'discountCode' => (string)$email->discountCode,
        ];
    }

    public static function tokenLabels(): array
    {
        return [
            'firstName' => Craft::t('abandoned-cart', 'First name'),
            'lastName' => Craft::t('abandoned-cart', 'Last name'),
            'fullName' => Craft::t('abandoned-cart', 'Full name'),
            'email' => Craft::t('app', 'Email'),
            'storeName' => Craft::t('abandoned-cart', 'Store name'),
            'siteName' => Craft::t('abandoned-cart', 'Site name'),
            'cartTotal' => Craft::t('abandoned-cart', 'Cart total'),
            'itemCount' => Craft::t('abandoned-cart', 'Item count'),
            'discountCode' => Craft::t('abandoned-cart', 'Discount code'),
        ];
    }

    public function getStoreName(CartSummary $cart): string
    {
        if ($cart->storeId && ($store = Commerce::getInstance()?->getStores()->getStoreById($cart->storeId))) {
            return (string)$store->getName();
        }

        $site = $cart->siteId ? Craft::$app->getSites()->getSiteById($cart->siteId) : Craft::$app->getSites()->getPrimarySite();

        return (string)$site?->getName();
    }

    public function getStoreAddress(CartSummary $cart): ?string
    {
        try {
            $storeId = $cart->storeId ?? Commerce::getInstance()?->getStores()->getPrimaryStore()?->id;
            $address = $storeId ? Commerce::getInstance()->getStoreSettings()->getStoreSettingsById($storeId)->getLocationAddress() : null;
        } catch (Throwable) {
            $address = null;
        }

        if (!$address) {
            return null;
        }

        $lines = array_filter([
            $address->organization,
            $address->addressLine1,
            $address->addressLine2,
            trim(implode(' ', array_filter([$address->locality, $address->administrativeArea, $address->postalCode]))),
            $address->countryCode,
        ]);

        return implode(', ', $lines) ?: null;
    }

    public function applyHeaders(Message $message, RenderedEmail $rendered): void
    {
        if ($rendered->unsubscribeUrl) {
            // Gmail and Yahoo unsubscribe in one click from the inbox, without opening the confirm page.
            $message->setHeader('List-Unsubscribe', '<' . $rendered->unsubscribeUrl . '>');
            $message->setHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
    }

    public function htmlToText(string $html): string
    {
        $html = preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace_callback('#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is', static function(array $match): string {
            $label = trim(strip_tags($match[3]));
            $url = html_entity_decode($match[2]);

            return $label && $label !== $url && !str_starts_with($url, '#') ? "{$label} ({$url})" : $label;
        }, $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|h[1-6]|tr|li|table)>#i', "\n\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }


    // Private Methods
    // =========================================================================

    private function _getVariables(Email $email, CartSummary $cart, ?Order $order, array $urls, array $tokens, bool $isTest, string $subject, string $previewText): array
    {
        $templates = AbandonedCart::$plugin->getTemplates();
        $message = $templates->renderTokens((string)$email->message, $tokens);

        // A message without markup is plain text from the editor or the 4.x upgrade, so keep its paragraphs.
        if ($message !== '' && $message === strip_tags($message)) {
            $message = implode('', array_map(static fn(string $paragraph): string => '<p>' . nl2br(Html::encode(trim($paragraph))) . '</p>', preg_split('/\n\s*\n/', trim($message)) ?: []));
        }

        $recoverUrl = $urls['recoverUrl'] ?? '#';
        $site = $cart->siteId ? Craft::$app->getSites()->getSiteById($cart->siteId) : Craft::$app->getSites()->getPrimarySite();

        $variables = [
            'email' => $email,
            'subject' => $subject,
            'previewText' => $previewText,
            'heading' => $templates->renderTokens((string)$email->heading, $tokens),
            'message' => Template::raw($message),
            'buttonLabel' => $templates->renderTokens((string)($email->buttonLabel ?: Craft::t('abandoned-cart', 'Return to your cart')), $tokens),
            'discountCode' => $email->discountCode,
            'cart' => $cart,
            'order' => $order,
            'site' => $site,
            'storeName' => $tokens['storeName'],
            'storeAddress' => $this->getStoreAddress($cart),
            'recoverUrl' => $recoverUrl,
            'unsubscribeUrl' => $urls['unsubscribeUrl'] ?? '#',
            'isTest' => $isTest,
        ];

        // Templates written for 4.x read these names, so upgraded stores keep working while they update.
        if (AbandonedCart::$plugin->getSettings()->compatibilityMode) {
            $variables['discount'] = $email->discountCode ?: false;
            // 4.x templates write `siteUrl ~ checkoutLink`, so the link is relative to the site URL.
            $siteUrl = UrlHelper::siteUrl();
            $variables['checkoutLink'] = str_starts_with($recoverUrl, $siteUrl) ? substr($recoverUrl, strlen($siteUrl)) : $recoverUrl;
            $variables['currentSite'] = $site;
        }

        if ($this->hasEventHandlers(self::EVENT_DEFINE_VARIABLES)) {
            $event = new DefineEmailVariablesEvent(['email' => $email, 'cart' => $cart, 'order' => $order, 'variables' => $variables]);
            $this->trigger(self::EVENT_DEFINE_VARIABLES, $event);
            $variables = $event->variables;
        }

        return $variables;
    }

    private function _renderText(string $text, array $tokens, ?Order $order): string
    {
        $text = AbandonedCart::$plugin->getTemplates()->renderTokens($text, $tokens);

        // Subjects carried over from 4.x can contain Twig, which renders sandboxed.
        if (str_contains($text, '{{') || str_contains($text, '{%')) {
            $text = AbandonedCart::$plugin->getTemplates()->renderSandboxedString($text, ['order' => $order]);
        }

        return trim(StringHelper::collapseWhitespace($text));
    }

    private function _resolveLanguage(CartSummary $cart, ?Order $order): string
    {
        $site = $cart->siteId ? Craft::$app->getSites()->getSiteById($cart->siteId) : null;
        $language = $order?->orderLanguage ?: $site?->language ?: Craft::$app->language;

        if ($this->hasEventHandlers(self::EVENT_RESOLVE_LANGUAGE)) {
            $event = new ResolveLanguageEvent(['cart' => $cart, 'order' => $order, 'language' => $language]);
            $this->trigger(self::EVENT_RESOLVE_LANGUAGE, $event);
            $language = $event->language;
        }

        return $language;
    }
}
