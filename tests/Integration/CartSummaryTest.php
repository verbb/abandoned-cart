<?php

use verbb\abandonedcart\models\CartSummary;

use craft\elements\Asset;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Assets as AssetsField;
use craft\fs\Local;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayoutTab;
use craft\models\Volume;

use craft\commerce\Plugin as Commerce;

uses(verbb\abandonedcart\tests\Integration\TestCase::class);

it('shows the product thumbnail for a cart item, and none when the product has no thumbnail field', function() {
    $variant = $this->createVariant();
    $cart = CartSummary::fromOrder($this->createCart('thumbless@example.test', $variant));

    expect($cart->items[0]['purchasableId'])->toBe($variant->id)
        ->and($cart->getItemThumbHtml($cart->items[0]))->toBeNull();

    // A local volume with one image, and an Assets field used for product thumbnails.
    $suffix = StringHelper::randomString(6);
    $path = sys_get_temp_dir() . '/ac-thumb-test-' . $suffix;
    FileHelper::createDirectory($path);
    $fs = new Local(['name' => 'Thumbs ' . $suffix, 'handle' => 'thumbs' . $suffix, 'path' => $path, 'hasUrls' => true, 'url' => '/thumbs']);
    expect(Craft::$app->getFs()->saveFilesystem($fs))->toBeTrue(json_encode($fs->getErrors()));
    $volume = new Volume(['name' => 'Thumbs ' . $suffix, 'handle' => 'thumbs' . $suffix, 'fsHandle' => $fs->handle]);
    expect(Craft::$app->getVolumes()->saveVolume($volume))->toBeTrue();

    $field = new AssetsField(['name' => 'Product Image', 'handle' => 'productImage' . $suffix, 'sources' => ['volume:' . $volume->uid]]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

    $product = $variant->getOwner();
    $type = $product->getType();
    $layout = $type->getFieldLayout();
    $element = new CustomField($field, ['uid' => StringHelper::UUID()]);
    $layout->setTabs([new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'elements' => [$element]])]);
    $layout->thumbFieldKey = $element->key();
    expect(Commerce::getInstance()->getProductTypes()->saveProductType($type))->toBeTrue();

    $image = $path . '/source.png';
    file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    $asset = new Asset(['tempFilePath' => $image, 'filename' => 'lamp.png', 'newFolderId' => Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id, 'volumeId' => $volume->id, 'avoidFilenameConflicts' => true]);
    $asset->setScenario(Asset::SCENARIO_CREATE);
    expect(Craft::$app->getElements()->saveElement($asset))->toBeTrue();

    $product = Craft::$app->getElements()->getElementById($product->id, null, $product->siteId);
    $product->setFieldValue($field->handle, [$asset->id]);
    expect(Craft::$app->getElements()->saveElement($product))->toBeTrue();

    // The variant has no thumbnail field of its own, so the product's is used.
    $html = $cart->getItemThumbHtml($cart->items[0]);

    expect($html)->toContain('class="thumb')
        ->and($html)->toContain('data-srcset');

    FileHelper::removeDirectory($path);
});

it('gives sample cart items the same keys as real ones', function() {
    $real = CartSummary::fromOrder($this->createCart('keys@example.test'))->items[0];
    $sample = CartSummary::sample()->items[0];

    expect(array_keys($sample))->toEqualCanonicalizing(array_keys($real))
        ->and($sample['purchasableId'])->toBeNull();
});

it('prices the sample cart in the store’s currency', function() {
    $site = Craft::$app->getSites()->getPrimarySite();
    $store = Commerce::getInstance()->getStores()->getPrimaryStore();
    $original = $store->getCurrency()->getCode();

    // The stores service keeps one instance of each store, so this stands in for an Australian store.
    $store->setCurrency('AUD');

    try {
        expect(CartSummary::sample($site->id)->currency)->toBe('AUD')
            ->and(CartSummary::sample()->currency)->toBe('AUD')
            ->and(CartSummary::sample($site->id)->formatMoney(238))->toBe(Craft::$app->getFormatter()->asCurrency(238, 'AUD'))
            ->and(CartSummary::sample($site->id)->formatMoney(238))->not->toBe(Craft::$app->getFormatter()->asCurrency(238, 'USD'));
    } finally {
        $store->setCurrency($original);
    }
});
