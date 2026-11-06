<?php
namespace verbb\abandonedcart\tests\Integration;

/**
 * Upgrade tests rebuild the plugin's tables, so they run outside a transaction and reinstall the plugin afterwards.
 */
abstract class UpgradeTestCase extends TestCase
{
    protected bool $transactional = false;
}
