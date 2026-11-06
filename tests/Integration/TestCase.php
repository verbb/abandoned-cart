<?php
namespace verbb\abandonedcart\tests\Integration;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\models\Sequence;

use Craft;
use craft\enums\PropagationMethod;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\models\FieldLayout;

use DateInterval;
use DateTime;
use PHPUnit\Framework\TestCase as BaseTestCase;
use yii\db\Transaction;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\Plugin as Commerce;

abstract class TestCase extends BaseTestCase
{
    // Properties
    // =========================================================================

    // Tests that change the schema turn this off: MySQL commits schema changes immediately.
    protected bool $transactional = true;

    private ?Transaction $_transaction = null;
    private array $_settings = [];


    // Public Methods
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        if (!Craft::$app || !AbandonedCart::$plugin || !Commerce::getInstance()) {
            self::fail('Craft, Commerce and Abandoned Cart must be bootstrapped for integration tests.');
        }

        $this->_settings = AbandonedCart::$plugin->getSettings()->getAttributes();
        $this->_transaction = $this->transactional ? Craft::$app->getDb()->beginTransaction() : null;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->_transaction?->getIsActive()) {
                $this->_transaction->rollBack();
            }

            AbandonedCart::$plugin->getSettings()->setAttributes($this->_settings, false);
        } finally {
            parent::tearDown();
        }
    }


    // Protected Methods
    // =========================================================================

    protected function createVariant(float $price = 25): Variant
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $suffix = bin2hex(random_bytes(4));
        $type = new ProductType([
            'name' => 'Abandoned Cart Test ' . $suffix,
            'handle' => 'abandonedCartTest' . $suffix,
            'maxVariants' => 1,
            'hasProductTitleField' => true,
            'hasVariantTitleField' => true,
            'skuFormat' => null,
            'propagationMethod' => PropagationMethod::All,
        ]);
        $type->setSiteSettings([
            $site->id => new ProductTypeSite(['siteId' => $site->id, 'hasUrls' => false, 'enabledByDefault' => true]),
        ]);
        $type->getBehavior('productFieldLayout')->setFieldLayout(new FieldLayout(['type' => Product::class]));
        $type->getBehavior('variantFieldLayout')->setFieldLayout(new FieldLayout(['type' => Variant::class]));

        if (!Commerce::getInstance()->getProductTypes()->saveProductType($type)) {
            self::fail('Unable to save the test product type: ' . Json::encode($type->getErrors()));
        }

        $product = new Product(['typeId' => $type->id, 'siteId' => $site->id, 'title' => 'Canvas Weekender ' . $suffix, 'slug' => 'product-' . $suffix, 'postDate' => new DateTime(), 'enabled' => true]);

        if (!Craft::$app->getElements()->saveElement($product)) {
            self::fail('Unable to save the test product: ' . Json::encode($product->getErrors()));
        }

        $variant = new Variant(['title' => 'Slate', 'siteId' => $site->id, 'enabled' => true, 'isDefault' => true, 'inventoryTracked' => false, 'availableForPurchase' => true]);
        $variant->setSku('AC-' . strtoupper($suffix));
        $variant->setBasePrice($price);
        $variant->setOwnerId($product->id);
        $variant->setPrimaryOwnerId($product->id);

        if (!Craft::$app->getElements()->saveElement($variant)) {
            self::fail('Unable to save the test variant: ' . Json::encode($variant->getErrors()));
        }

        return Variant::find()->id($variant->id)->siteId($site->id)->status(null)->one();
    }

    /**
     * An incomplete cart with one line item, last changed `$idleMinutes` ago.
     */
    protected function createCart(string $email, ?Variant $variant = null, int $qty = 1, int $idleMinutes = 120): Order
    {
        $commerce = Commerce::getInstance();
        $variant ??= $this->createVariant();
        $order = new Order([
            'number' => $commerce->getCarts()->generateCartNumber(),
            'orderSiteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'storeId' => $commerce->getStores()->getPrimaryStore()->id,
        ]);
        $order->setCustomer(Craft::$app->getUsers()->ensureUserByEmail($email));
        $order->addLineItem($commerce->getLineItems()->create($order, ['purchasableId' => $variant->id, 'qty' => $qty]));

        if (!Craft::$app->getElements()->saveElement($order, false)) {
            self::fail('Unable to save the test cart: ' . Json::encode($order->getErrors()));
        }

        $this->backdateCart($order, $idleMinutes);

        return Order::find()->id($order->id)->status(null)->one();
    }

    protected function backdateCart(Order $order, int $idleMinutes): void
    {
        $date = Db::prepareDateForDb((new DateTime())->sub(new DateInterval('PT' . $idleMinutes . 'M')));
        Db::update('{{%elements}}', ['dateUpdated' => $date], ['id' => $order->id], [], false);
        Db::update('{{%commerce_orders}}', ['dateUpdated' => $date], ['id' => $order->id], [], false);
    }

    protected function completeOrder(Order $order): Order
    {
        $order = Order::find()->id($order->id)->status(null)->one();
        $order->markAsComplete();

        return Order::find()->id($order->id)->status(null)->one();
    }

    protected function turnOn(Sequence $sequence, string $startedAgo = '-1 day'): Sequence
    {
        $sequence->enabled = true;
        AbandonedCart::$plugin->getSequences()->saveSequence($sequence);

        // Pretend the sequence went on earlier, so carts abandoned in the test count.
        Db::update('{{%abandonedcart_sequences}}', ['enrollmentStartedAt' => Db::prepareDateForDb(new DateTime($startedAgo))], ['id' => $sequence->id], [], false);

        return AbandonedCart::$plugin->getSequences()->getSequenceById($sequence->id);
    }

    protected function sentMailCount(): int
    {
        return count(glob(Craft::$app->getPath()->getRuntimePath() . '/mail/*.eml') ?: []);
    }
}
