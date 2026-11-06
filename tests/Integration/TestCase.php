<?php
namespace verbb\abandonedcart\tests\Integration;

use verbb\abandonedcart\AbandonedCart;

use Craft;

use PHPUnit\Framework\TestCase as BaseTestCase;
use yii\db\Transaction;

use craft\commerce\Plugin as Commerce;

abstract class TestCase extends BaseTestCase
{
    // Properties
    // =========================================================================

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
        $this->_transaction = Craft::$app->getDb()->beginTransaction();
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
}
