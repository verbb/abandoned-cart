<?php
namespace craft\base {
    class MemoizableArray
    {
    }

    class Model
    {
        public function validate(): bool
        {
            return true;
        }
    }
}

namespace craft\db {
    class FixtureColumn
    {
        public ?string $after = null;

        public function after(string $column): self
        {
            $this->after = $column;

            return $this;
        }
    }

    class Migration
    {
        public array $operations = [];

        public function dateTime(): FixtureColumn
        {
            return new FixtureColumn();
        }

        public function addColumn(string $table, string $column, FixtureColumn $type): void
        {
            $this->operations[] = ['addColumn', $table, $column, $type];
        }

        public function update(
            string $table,
            array $columns,
            string|array $condition = '',
            array $params = [],
            bool $updateTimestamp = true,
        ): void {
            $this->operations[] = ['update', $table, $columns, $condition, $params, $updateTimestamp];
        }

        public function dropColumn(string $table, string $column): void
        {
            $this->operations[] = ['dropColumn', $table, $column];
        }
    }
}

namespace yii\db {
    class Expression
    {
        public function __construct(public string $expression)
        {
        }
    }
}

namespace yii\base {
    class Component
    {
        public function hasEventHandlers(string $name): bool
        {
            return false;
        }

        public function trigger(string $name, mixed $event): void
        {
        }
    }
}

namespace verbb\abandonedcart {
    class AbandonedCart
    {
        public static mixed $plugin = null;
    }
}

namespace verbb\abandonedcart\records {
    class Cart
    {
        public static ?self $savedRecord = null;
        public static bool $saveResult = true;

        public ?int $id = null;
        public mixed $orderId = null;
        public mixed $email = null;
        public mixed $clicked = null;
        public mixed $isScheduled = null;
        public mixed $firstReminder = null;
        public mixed $secondReminder = null;
        public mixed $isRecovered = null;
        public mixed $isSent = null;
        public mixed $dateLastSent = null;

        public static function findOne(array $condition): ?self
        {
            return self::$savedRecord?->id === ($condition['id'] ?? null) ? self::$savedRecord : null;
        }

        public function save(bool $runValidation): bool
        {
            if (!self::$saveResult) {
                return false;
            }

            $this->id ??= 1;
            self::$savedRecord = $this;

            return true;
        }
    }
}

namespace {
    require __DIR__ . '/../../src/models/Cart.php';
    require __DIR__ . '/../../src/migrations/m261003_000000_recovery_expiry_anchor.php';
    require __DIR__ . '/../../src/services/Carts.php';

    use verbb\abandonedcart\AbandonedCart;
    use verbb\abandonedcart\migrations\m261003_000000_recovery_expiry_anchor;
    use verbb\abandonedcart\models\Cart;
    use verbb\abandonedcart\records\Cart as CartRecord;
    use verbb\abandonedcart\services\Carts;
    use yii\db\Expression;

    class Craft
    {
        public static function info(string $message, string $category): void
        {
        }

        public static function t(string $category, string $message, array $params = []): string
        {
            return $message;
        }
    }

    class FixtureSettings
    {
        public int $restoreExpiryHours = 48;

        public function getRestoreExpiryHours(): int
        {
            return $this->restoreExpiryHours;
        }
    }

    class FixturePlugin
    {
        public function __construct(public FixtureSettings $settings)
        {
        }

        public function getSettings(): FixtureSettings
        {
            return $this->settings;
        }
    }

    function check(string $label, bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException("Failed: {$label}");
        }

        echo "PASS: {$label}\n";
    }

    function dateAt(string $time): DateTime
    {
        return new DateTime($time, new DateTimeZone('UTC'));
    }

    $settings = new FixtureSettings();
    AbandonedCart::$plugin = new FixturePlugin($settings);

    $cart = new Cart();
    $cart->isSent = true;
    $cart->dateLastSent = dateAt('2026-10-01 00:00:00');
    $cart->dateUpdated = dateAt('2026-10-01 00:00:00');

    check('recovery is available immediately before its deadline', $cart->getIsRecoveryAvailable(dateAt('2026-10-02 23:59:59')));
    check('recovery expires at the exact deadline', !$cart->getIsRecoveryAvailable(dateAt('2026-10-03 00:00:00')));

    $originalIssuedAt = $cart->dateLastSent->format(DATE_ATOM);
    $expiresAt = $cart->getRecoveryExpiresAt();
    check('expiry calculation does not mutate the stored send anchor', $cart->dateLastSent->format(DATE_ATOM) === $originalIssuedAt);
    check('expiry is derived from the successful send time', $expiresAt?->format('Y-m-d H:i:s') === '2026-10-03 00:00:00');

    $cart->clicked = true;
    $cart->dateUpdated = dateAt('2026-10-02 23:59:59');
    check('a successful restoration save cannot extend the deadline', !$cart->getIsRecoveryAvailable(dateAt('2026-10-03 00:00:00')));

    $cart->dateLastSent = dateAt('2000-01-01 00:00:00');
    $cart->dateUpdated = dateAt('2099-01-01 00:00:00');
    check('dashboard expiry ignores later general updates once a link was sent', $cart->getStatus() === Cart::STATUS_EXPIRED);

    $cart->dateLastSent = dateAt('2026-10-02 12:00:00');
    check('a later successful reminder deliberately issues a new deadline', $cart->getRecoveryExpiresAt()?->format('Y-m-d H:i:s') === '2026-10-04 12:00:00');

    $cart->dateLastSent = null;
    $cart->dateUpdated = dateAt('2026-10-03 00:00:00');
    check('a cart without a successful reminder send cannot be restored', !$cart->getIsRecoveryAvailable(dateAt('2026-10-03 00:00:01')));

    $cart->dateLastSent = dateAt('2026-10-01 00:00:00');
    $settings->restoreExpiryHours = 24;
    check('expiry setting changes retain their existing dynamic behavior', $cart->getRecoveryExpiresAt()?->format('Y-m-d H:i:s') === '2026-10-02 00:00:00');

    $service = new Carts();
    CartRecord::$saveResult = false;
    $failedCart = new Cart();
    $markRecoveryLinkAsIssued = new ReflectionMethod(Carts::class, '_markRecoveryLinkAsIssued');

    try {
        $markRecoveryLinkAsIssued->invoke($service, $failedCart);
        $anchorFailure = null;
    } catch (Throwable $exception) {
        $anchorFailure = $exception;
    }

    check('delivery cannot start when the expiry anchor is not persisted', $anchorFailure instanceof Exception && CartRecord::$savedRecord === null);

    CartRecord::$saveResult = true;
    $sentCart = new Cart();
    $markRecoveryLinkAsIssued->invoke($service, $sentCart);
    check('a send attempt persists its expiry anchor before delivery', $sentCart->dateLastSent instanceof DateTime && CartRecord::$savedRecord?->dateLastSent === $sentCart->dateLastSent);

    $sendAnchor = $sentCart->dateLastSent->format(DATE_ATOM);
    $sentCart->clicked = true;
    $sentCart->dateUpdated = dateAt('2099-01-01 00:00:00');
    check('ordinary cart saves retain the successful-send anchor', $service->saveCart($sentCart, false) && $sentCart->dateLastSent->format(DATE_ATOM) === $sendAnchor && CartRecord::$savedRecord?->dateLastSent->format(DATE_ATOM) === $sendAnchor);

    $migration = new m261003_000000_recovery_expiry_anchor();
    check('migration applies successfully', $migration->safeUp());

    [$operation, $table, $column, $type] = $migration->operations[0];
    check('migration adds the nullable send anchor after isSent', $operation === 'addColumn' && $table === '{{%abandonedcart_carts}}' && $column === 'dateLastSent' && $type->after === 'isSent');

    [$operation, $table, $columns, $condition, , $updateTimestamp] = $migration->operations[1];
    check(
        'migration preserves issued-link deadlines without refreshing dateUpdated',
        $operation === 'update' &&
        $table === '{{%abandonedcart_carts}}' &&
        $columns['dateLastSent'] instanceof Expression &&
        $columns['dateLastSent']->expression === '[[dateUpdated]]' &&
        $condition === [
            'or',
            ['isSent' => true],
            ['firstReminder' => true],
            ['secondReminder' => true],
        ] &&
        $updateTimestamp === false,
    );

    check('migration rollback applies successfully', $migration->safeDown());
    check('migration rollback removes the send anchor', $migration->operations[2] === ['dropColumn', '{{%abandonedcart_carts}}', 'dateLastSent']);
}
