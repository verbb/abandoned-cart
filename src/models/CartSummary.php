<?php
namespace verbb\abandonedcart\models;

use Craft;
use craft\base\Model;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;

/**
 * The cart as email templates see it: plain values, so a custom template works the same for a real cart, a preview
 * and a test send. The Commerce order is also available to templates as `order` when there is one.
 */
class CartSummary extends Model
{
    // Properties
    // =========================================================================

    public ?string $email = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $number = null;
    public ?int $siteId = null;
    public ?int $storeId = null;
    public ?string $currency = null;
    public float $total = 0;
    public float $subtotal = 0;
    public int $itemCount = 0;

    /** @var array<int, array{title: string, description: ?string, qty: int, price: float, subtotal: float, url: ?string}> */
    public array $items = [];

    public bool $isSample = false;


    // Public Methods
    // =========================================================================

    public static function fromOrder(Order $order): self
    {
        $customer = $order->getCustomer();
        $address = $order->getBillingAddress() ?? $order->getShippingAddress();

        return new self([
            'email' => $order->getEmail(),
            'firstName' => $address?->firstName ?: $customer?->firstName,
            'lastName' => $address?->lastName ?: $customer?->lastName,
            'number' => $order->number,
            'siteId' => $order->orderSiteId,
            'storeId' => $order->storeId,
            'currency' => $order->currency,
            'total' => (float)$order->getTotal(),
            'subtotal' => (float)$order->getItemSubtotal(),
            'itemCount' => (int)$order->getTotalQty(),
            'items' => array_map(static fn(LineItem $item): array => [
                'title' => (string)$item->getDescription(),
                'description' => self::_describeOptions($item->getOptions()),
                'qty' => (int)$item->qty,
                'price' => (float)$item->getSalePrice(),
                'subtotal' => (float)$item->getSubtotal(),
                'url' => $item->getPurchasable()?->getUrl(),
            ], $order->getLineItems()),
        ]);
    }

    public static function sample(?int $siteId = null): self
    {
        return new self([
            'email' => 'amelia.hart@example.test',
            'firstName' => 'Amelia',
            'lastName' => 'Hart',
            'number' => 'sample',
            'siteId' => $siteId,
            'currency' => 'USD',
            'total' => 238,
            'subtotal' => 238,
            'itemCount' => 3,
            'items' => [
                ['title' => Craft::t('abandoned-cart', 'Leather Card Wallet'), 'description' => Craft::t('abandoned-cart', 'Tan'), 'qty' => 2, 'price' => 69, 'subtotal' => 138, 'url' => null],
                ['title' => Craft::t('abandoned-cart', 'Canvas Weekender'), 'description' => Craft::t('abandoned-cart', 'Slate'), 'qty' => 1, 'price' => 100, 'subtotal' => 100, 'url' => null],
            ],
            'isSample' => true,
        ]);
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function formatMoney(float $amount): string
    {
        return Craft::$app->getFormatter()->asCurrency($amount, $this->currency ?: 'USD');
    }


    // Private Methods
    // =========================================================================

    private static function _describeOptions(array $options): ?string
    {
        $values = array_map('strval', array_filter($options, 'is_scalar'));

        return $values ? implode(' · ', $values) : null;
    }
}
