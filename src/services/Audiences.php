<?php
namespace verbb\abandonedcart\services;

use verbb\abandonedcart\events\MatchAudienceEvent;
use verbb\abandonedcart\events\RegisterAudiencesEvent;
use verbb\abandonedcart\models\Sequence;

use Craft;
use craft\base\Component;
use craft\elements\conditions\ElementConditionInterface;

use Throwable;

use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;

class Audiences extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_REGISTER_AUDIENCES = 'registerAudiences';
    public const EVENT_MATCH_AUDIENCE = 'matchAudience';


    // Properties
    // =========================================================================

    private ?array $_audiences = null;


    // Public Methods
    // =========================================================================

    /**
     * Audience types keyed by handle, each with a label and description for the sequence editor.
     */
    public function getAllAudiences(): array
    {
        if ($this->_audiences === null) {
            $event = new RegisterAudiencesEvent(['audiences' => [
                Sequence::AUDIENCE_ALL => ['label' => Craft::t('abandoned-cart', 'All Carts'), 'description' => Craft::t('abandoned-cart', 'Every abandoned cart with an email address')],
                Sequence::AUDIENCE_CART_VALUE => ['label' => Craft::t('abandoned-cart', 'Cart Value'), 'description' => Craft::t('abandoned-cart', 'Carts above or below an amount')],
                Sequence::AUDIENCE_RETURNING => ['label' => Craft::t('abandoned-cart', 'Returning Customers'), 'description' => Craft::t('abandoned-cart', 'Customers who’ve ordered before')],
                Sequence::AUDIENCE_FIRST_TIME => ['label' => Craft::t('abandoned-cart', 'First-Time Customers'), 'description' => Craft::t('abandoned-cart', 'No completed orders yet')],
                Sequence::AUDIENCE_PRODUCTS => ['label' => Craft::t('abandoned-cart', 'Products'), 'description' => Craft::t('abandoned-cart', 'Carts with chosen products')],
                Sequence::AUDIENCE_CUSTOM => ['label' => Craft::t('abandoned-cart', 'Custom Rules'), 'description' => Craft::t('abandoned-cart', 'Any Commerce order condition')],
            ]]);
            $this->trigger(self::EVENT_REGISTER_AUDIENCES, $event);
            $this->_audiences = $event->audiences;
        }

        return $this->_audiences;
    }

    public function describe(Sequence $sequence): string
    {
        $settings = $sequence->audienceSettings;
        $amount = static fn(mixed $value): string => $value === null || $value === '' ? '…' : Craft::$app->getFormatter()->asDecimal((float)$value, 2);

        return match ($sequence->audienceType) {
            Sequence::AUDIENCE_ALL => $sequence->isFallback ? Craft::t('abandoned-cart', 'Every cart that doesn’t match a sequence above') : Craft::t('abandoned-cart', 'Every abandoned cart'),
            Sequence::AUDIENCE_CART_VALUE => match ($settings['operator'] ?? 'atLeast') {
                'lessThan' => Craft::t('abandoned-cart', 'Cart total is less than {amount}', ['amount' => $amount($settings['amount'] ?? null)]),
                'between' => Craft::t('abandoned-cart', 'Cart total is between {amount} and {max}', ['amount' => $amount($settings['amount'] ?? null), 'max' => $amount($settings['maxAmount'] ?? null)]),
                default => Craft::t('abandoned-cart', 'Cart total is {amount} or more', ['amount' => $amount($settings['amount'] ?? null)]),
            },
            Sequence::AUDIENCE_RETURNING => Craft::t('abandoned-cart', '{count, plural, =1{Customer has ordered before} other{Customer has # or more orders}}', ['count' => max(1, (int)($settings['minOrders'] ?? 1))]),
            Sequence::AUDIENCE_FIRST_TIME => Craft::t('abandoned-cart', 'Customer hasn’t ordered yet'),
            Sequence::AUDIENCE_PRODUCTS => Craft::t('abandoned-cart', 'Cart contains chosen products'),
            Sequence::AUDIENCE_CUSTOM => Craft::t('abandoned-cart', 'Custom rules'),
            default => $this->getAllAudiences()[$sequence->audienceType]['label'] ?? $sequence->audienceType,
        };
    }

    public function matches(Sequence $sequence, Order $order): bool
    {
        if ($sequence->siteIds && !in_array((int)$order->orderSiteId, array_map('intval', $sequence->siteIds), true)) {
            return false;
        }

        $settings = $sequence->audienceSettings;

        $matches = match ($sequence->audienceType) {
            Sequence::AUDIENCE_ALL => true,
            Sequence::AUDIENCE_CART_VALUE => $this->_matchesCartValue($order, $settings),
            Sequence::AUDIENCE_RETURNING => $this->countPreviousOrders($order) >= max(1, (int)($settings['minOrders'] ?? 1)),
            Sequence::AUDIENCE_FIRST_TIME => $this->countPreviousOrders($order) === 0,
            Sequence::AUDIENCE_PRODUCTS => $this->_matchesProducts($order, $settings),
            Sequence::AUDIENCE_CUSTOM => $this->_matchesCondition($order, $settings['condition'] ?? []),
            default => null,
        };

        if ($this->hasEventHandlers(self::EVENT_MATCH_AUDIENCE)) {
            $event = new MatchAudienceEvent(['sequence' => $sequence, 'order' => $order, 'matches' => $matches]);
            $this->trigger(self::EVENT_MATCH_AUDIENCE, $event);
            $matches = $event->matches;
        }

        return (bool)$matches;
    }

    public function countPreviousOrders(Order $order): int
    {
        if (!$order->getEmail()) {
            return 0;
        }

        return (int)Order::find()
            ->email($order->getEmail())
            ->isCompleted(true)
            ->id(['not', $order->id])
            ->status(null)
            ->count();
    }

    public function createOrderCondition(array $config = []): OrderCondition
    {
        $config['class'] = OrderCondition::class;
        $condition = Craft::$app->getConditions()->createCondition($config);
        $condition->mainTag = 'div';
        $condition->name = 'audienceSettings[condition]';
        $condition->forProjectConfig = false;

        return $condition;
    }


    // Private Methods
    // =========================================================================

    private function _matchesCartValue(Order $order, array $settings): bool
    {
        $amount = isset($settings['amount']) && $settings['amount'] !== '' ? (float)$settings['amount'] : null;

        // A cart value rule without an amount yet can't match anything, so the sequence waits until it's set.
        if ($amount === null) {
            return false;
        }

        $total = (float)$order->getTotal();

        return match ($settings['operator'] ?? 'atLeast') {
            'lessThan' => $total < $amount,
            'between' => $total >= $amount && $total <= (float)($settings['maxAmount'] ?? PHP_FLOAT_MAX),
            default => $total >= $amount,
        };
    }

    private function _matchesProducts(Order $order, array $settings): bool
    {
        $productIds = array_map('intval', (array)($settings['productIds'] ?? []));

        if (!$productIds) {
            return false;
        }

        $cartProductIds = [];

        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();

            if ($purchasable instanceof Variant) {
                $cartProductIds[] = (int)$purchasable->primaryOwnerId;
            } elseif ($purchasable instanceof Product) {
                $cartProductIds[] = (int)$purchasable->id;
            }
        }

        $cartProductIds = array_values(array_unique(array_filter($cartProductIds)));

        if (($settings['match'] ?? 'any') === 'all') {
            return !array_diff($productIds, $cartProductIds);
        }

        return (bool)array_intersect($productIds, $cartProductIds);
    }

    private function _matchesCondition(Order $order, array $config): bool
    {
        try {
            $condition = $this->createOrderCondition($config);

            return !$condition->getConditionRules() || ($condition instanceof ElementConditionInterface && $condition->matchElement($order));
        } catch (Throwable $e) {
            Craft::warning('Abandoned Cart couldn’t evaluate a custom audience: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }
}
