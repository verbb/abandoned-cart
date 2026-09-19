// craft-screenshots: sample-frontend
/** Seed real Commerce carts and render the plugin's bundled recovery email. */

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\Plugin as Commerce;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use verbb\abandonedcart\AbandonedCart;

$commerce = Commerce::getInstance();
$elements = Craft::$app->getElements();
$site = Craft::$app->getSites()->getPrimarySite();
$store = $commerce->getStores()->getPrimaryStore();
$productTypes = $commerce->getProductTypes();
$type = $productTypes->getProductTypeByHandle('screenshotGoods');

if (!$type) {
    $type = new ProductType([
        'name' => 'Screenshot Goods',
        'handle' => 'screenshotGoods',
        'hasProductTitleField' => true,
        'hasVariantTitleField' => true,
        'maxVariants' => 12,
    ]);
    $type->getBehavior('productFieldLayout')->setFieldLayout(new FieldLayout(['type' => Product::class]));
    $type->getBehavior('variantFieldLayout')->setFieldLayout(new FieldLayout(['type' => Variant::class]));
    $type->setSiteSettings([
        $site->id => new ProductTypeSite([
            'siteId' => $site->id,
            'hasUrls' => true,
            'uriFormat' => 'shop/{slug}',
            'template' => 'shop/_product',
            'enabledByDefault' => true,
        ]),
    ]);

    if (!$productTypes->saveProductType($type)) {
        throw new RuntimeException('Unable to save the Abandoned Cart screenshot product type: ' . Json::encode($type->getErrors()));
    }
}

$catalogue = [
    ['Harbour linen throw', 'harbour-linen-throw', 'HLT-OAT-L', 'Oatmeal / Large', 189],
    ['Stoneware serving bowl', 'stoneware-serving-bowl', 'SSB-SAND', 'Sand', 74],
];
$variants = [];

foreach ($catalogue as [$title, $slug, $sku, $variantTitle, $price]) {
    $product = Product::find()->typeId($type->id)->slug($slug)->siteId($site->id)->status(null)->one();

    if (!$product) {
        $product = new Product([
            'typeId' => $type->id,
            'siteId' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'postDate' => new DateTime('2026-09-01 09:00:00'),
            'enabled' => true,
        ]);

        if (!$elements->saveElement($product)) {
            throw new RuntimeException('Unable to save an Abandoned Cart screenshot product: ' . Json::encode($product->getErrors()));
        }
    }

    $variant = Variant::find()->sku($sku)->status(null)->one();

    if (!$variant) {
        $variant = new Variant([
            'title' => $variantTitle,
            'siteId' => $site->id,
            'enabled' => true,
            'availableForPurchase' => true,
            'inventoryTracked' => false,
            'isDefault' => true,
        ]);
        $variant->setSku($sku);
        $variant->setBasePrice($price);
        $variant->setOwnerId($product->id);
        $variant->setPrimaryOwnerId($product->id);

        if (!$elements->saveElement($variant)) {
            throw new RuntimeException('Unable to save an Abandoned Cart screenshot variant: ' . Json::encode($variant->getErrors()));
        }

        $product->setVariants([$variant]);
        $elements->saveElement($product, false);
    }

    $variants[$sku] = $variant;
}

$cartData = [
    ['maya.chen@example.com', 'HLT-OAT-L', 1, false, true, true, true, true, false, '2026-09-18 08:42:00'],
    ['oliver.grant@example.com', 'SSB-SAND', 2, false, true, true, false, true, false, '2026-09-18 07:20:00'],
    ['priya.nair@example.com', 'HLT-OAT-L', 1, true, true, true, true, true, true, '2026-09-17 16:08:00'],
    ['daniel.brooks@example.com', 'SSB-SAND', 1, false, true, false, false, false, false, '2026-09-17 12:27:00'],
    ['lena.ortiz@example.com', 'HLT-OAT-L', 2, false, true, true, false, true, false, '2026-09-16 18:16:00'],
    ['noah.patel@example.com', 'SSB-SAND', 1, false, true, false, false, false, false, '2026-09-16 09:40:00'],
];

foreach ($cartData as $cartIndex => [$email, $sku, $qty, $recovered, $scheduled, $first, $second, $sent, $clicked, $updated]) {
    $existing = (new craft\db\Query())
        ->from('{{%abandonedcart_carts}}')
        ->where(['email' => $email])
        ->one();

    if ($existing) {
        Craft::$app->getDb()->createCommand()->update('{{%commerce_orders}}', [
            'number' => 'AC' . str_pad((string)(1048 + $cartIndex), 5, '0', STR_PAD_LEFT),
            'reference' => 'Cart ' . (1048 + $cartIndex),
        ], ['id' => $existing['orderId']])->execute();
        continue;
    }

    $order = new Order([
        'orderSiteId' => $site->id,
        'storeId' => $store->id,
        'isCompleted' => false,
        'currency' => $store->getCurrency(),
        'paymentCurrency' => $store->getCurrency(),
        'number' => 'AC' . str_pad((string)(1048 + $cartIndex), 5, '0', STR_PAD_LEFT),
        'reference' => 'Cart ' . (1048 + $cartIndex),
    ]);
    $order->setEmail($email);

    if (!$elements->saveElement($order, false)) {
        throw new RuntimeException('Unable to save an Abandoned Cart screenshot order: ' . Json::encode($order->getErrors()));
    }

    $lineItem = $commerce->getLineItems()->create($order, [
        'purchasable' => $variants[$sku],
        'qty' => $qty,
        'options' => [],
    ]);
    $order->addLineItem($lineItem);

    if (!$elements->saveElement($order, false)) {
        throw new RuntimeException('Unable to save Abandoned Cart screenshot order items: ' . Json::encode($order->getErrors()));
    }

    Craft::$app->getDb()->createCommand()->insert('{{%abandonedcart_carts}}', [
        'orderId' => $order->id,
        'email' => $email,
        'clicked' => $clicked,
        'isScheduled' => $scheduled,
        'firstReminder' => $first,
        'secondReminder' => $second,
        'isRecovered' => $recovered,
        'isSent' => $sent,
        'dateCreated' => $updated,
        'dateUpdated' => $updated,
        'uid' => StringHelper::UUID(),
    ])->execute();
}

$discount = $commerce->getDiscounts()->getDiscountByCode('WELCOME10', $store->id);

if (!$discount) {
    $discount = new Discount([
        'storeId' => $store->id,
        'name' => 'A little something for your cart',
        'description' => 'Take 10% off when you complete your order today.',
        'couponFormat' => 'WELCOME10',
        'requireCouponCode' => true,
        'percentDiscount' => -0.10,
        'allPurchasables' => true,
        'enabled' => true,
    ]);

    if (!$commerce->getDiscounts()->saveDiscount($discount)) {
        throw new RuntimeException('Unable to save the Abandoned Cart screenshot discount: ' . Json::encode($discount->getErrors()));
    }

    $coupon = new Coupon(['discountId' => $discount->id, 'code' => 'WELCOME10']);
    if (!$commerce->getCoupons()->saveCoupon($coupon)) {
        throw new RuntimeException('Unable to save the Abandoned Cart screenshot coupon: ' . Json::encode($coupon->getErrors()));
    }
}

Craft::$app->getPlugins()->savePluginSettings(AbandonedCart::$plugin, [
    'previousOrderRequired' => false,
    'disableSecondReminder' => false,
    'restoreExpiryHours' => 48,
    'firstReminderDelay' => 2,
    'secondReminderDelay' => 24,
    'firstReminderSubject' => 'You left something behind',
    'secondReminderSubject' => 'Your cart is still waiting',
    'discountCode' => 'WELCOME10',
    'recoveryUrl' => 'shop/cart',
]);

$featured = Order::find()->email('maya.chen@example.com')->isCompleted(false)->one();

if (!$featured || !$featured->hasLineItems()) {
    throw new RuntimeException('Unable to resolve the featured Abandoned Cart screenshot order.');
}

$templateDir = Craft::getAlias('@templates') . '/abandoned-cart-preview';
FileHelper::createDirectory($templateDir);
$emailTemplate = <<<'TWIG'
{# craft-screenshots: sample-frontend #}
{% set order = craft.orders().email('maya.chen@example.com').isCompleted(false).one() %}
{% include 'abandoned-cart-preview/_first' with {
    order: order,
    checkoutLink: 'actions/abandoned-cart/carts/restore-cart?number=' ~ order.number,
    discount: 'WELCOME10'
} only %}
TWIG;
file_put_contents($templateDir . '/email.twig', $emailTemplate);
$pluginRoot = dirname((new ReflectionClass(AbandonedCart::class))->getFileName());
file_put_contents($templateDir . '/_first.twig', file_get_contents($pluginRoot . '/templates/emails/first.html'));

echo Json::encode([
    'cartsRoute' => '/admin/abandoned-cart/dashboard',
    'settingsRoute' => '/admin/abandoned-cart/settings',
    'emailRoute' => '/abandoned-cart-preview/email',
    'cartCount' => (int)(new craft\db\Query())->from('{{%abandonedcart_carts}}')->count(),
], JSON_THROW_ON_ERROR);
