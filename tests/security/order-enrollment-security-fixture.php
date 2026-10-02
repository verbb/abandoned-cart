<?php
namespace craft\base {
    class MemoizableArray
    {
    }
}

namespace craft\db {
    class Query
    {
    }
}

namespace craft\elements\db {
    interface ElementQueryInterface
    {
    }
}

namespace craft\helpers {
    class Db
    {
        public static function prepareDateForDb(mixed $value): mixed
        {
            return $value;
        }
    }
}

namespace craft\mail {
    class Message
    {
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

namespace yii\db {
    class Expression
    {
        public function __construct(public string $expression)
        {
        }
    }

    class IntegrityException extends \RuntimeException
    {
        public function __construct(string $message, public array $errorInfo)
        {
            parent::__construct($message);
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
    use yii\db\IntegrityException;

    class FixtureQuery
    {
        private ?array $condition = null;

        public function where(array $condition): self
        {
            $this->condition = $condition;

            return $this;
        }

        public function andWhere(array $condition): self
        {
            return $this;
        }

        public function one(): ?object
        {
            return $this->exists() ? (object)[] : null;
        }

        public function exists(): bool
        {
            if (isset($this->condition['orderId'])) {
                return Cart::$orderExists;
            }

            if (isset($this->condition['recipientKey'])) {
                return Cart::$recipientExists;
            }

            return false;
        }
    }

    class Cart
    {
        public static bool $orderExists = false;
        public static bool $recipientExists = false;
        public static string $collision = '';

        public mixed $orderId = null;
        public mixed $email = null;
        public mixed $recipientKey = null;

        public static function find(): FixtureQuery
        {
            return new FixtureQuery();
        }

        public static function updateAll(array $attributes, array $condition): int
        {
            return 0;
        }

        public function save(bool $runValidation): bool
        {
            if (self::$collision === 'order') {
                self::$orderExists = true;

                throw new IntegrityException('duplicate order', ['23000', 1062]);
            }

            if (self::$collision === 'order-postgres') {
                self::$orderExists = true;

                throw new IntegrityException('duplicate order', ['23505', 7]);
            }

            if (self::$collision === 'recipient') {
                self::$recipientExists = true;

                throw new IntegrityException('duplicate recipient', ['23000', 1062]);
            }

            if (self::$collision === 'other') {
                self::$orderExists = true;

                throw new IntegrityException('unrelated constraint', ['23000', 1452]);
            }

            return true;
        }
    }
}

namespace {
    require __DIR__ . '/../../src/services/Carts.php';

    use verbb\abandonedcart\AbandonedCart;
    use verbb\abandonedcart\records\Cart as CartRecord;
    use verbb\abandonedcart\services\Carts;
    use yii\db\IntegrityException;

    class Craft
    {
    }

    class FixtureSettings
    {
        public bool $previousOrderRequired = false;

        public function getMaxEnrollmentsPerRun(): int
        {
            return 100;
        }

        public function getDisableSecondReminder(): bool
        {
            return false;
        }

        public function getRecipientCooldownHours(): int
        {
            return 24;
        }
    }

    class FixturePlugin
    {
        public function __construct(private FixtureSettings $settings)
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

    AbandonedCart::$plugin = new FixturePlugin(new FixtureSettings());
    $service = new Carts();
    $order = (object)['id' => 42, 'email' => ' Customer@Example.com '];

    CartRecord::$collision = 'order';
    CartRecord::$orderExists = false;
    CartRecord::$recipientExists = false;
    $service->createNewCarts([$order]);
    check('a concurrent insert for the same order is idempotent', CartRecord::$orderExists);

    CartRecord::$collision = 'order-postgres';
    CartRecord::$orderExists = false;
    CartRecord::$recipientExists = false;
    $service->createNewCarts([$order]);
    check('PostgreSQL duplicate-key collisions are idempotent', CartRecord::$orderExists);

    CartRecord::$collision = 'recipient';
    CartRecord::$orderExists = false;
    CartRecord::$recipientExists = false;
    $service->createNewCarts([$order]);
    check('the existing concurrent recipient guard remains idempotent', CartRecord::$recipientExists);

    CartRecord::$collision = 'other';
    CartRecord::$orderExists = false;
    CartRecord::$recipientExists = false;

    try {
        $service->createNewCarts([$order]);
        $unrelatedFailure = null;
    } catch (Throwable $exception) {
        $unrelatedFailure = $exception;
    }

    check('unrelated integrity failures are not swallowed', $unrelatedFailure instanceof IntegrityException);
}
