<?php
namespace craft\base {
    class Model
    {
        public function __construct(array $config = [])
        {
            foreach ($config as $name => $value) {
                if (!property_exists($this, $name)) {
                    throw new \RuntimeException("Unknown property: {$name}");
                }

                $this->{$name} = $value;
            }
        }
    }
}

namespace {
    require __DIR__ . '/../src/models/Cart.php';

    use verbb\abandonedcart\models\Cart;

    $cart = new Cart([
        'id' => 1,
        'orderId' => 2,
        'email' => 'customer@example.com',
        'recipientKey' => 'customer@example.com',
        'clicked' => false,
        'isScheduled' => false,
        'firstReminder' => false,
        'secondReminder' => false,
        'isRecovered' => false,
        'isSent' => false,
        'dateLastSent' => null,
        'dateCreated' => new DateTime('2026-10-13 00:00:00'),
        'dateUpdated' => new DateTime('2026-10-13 00:00:00'),
        'uid' => 'fixture-uid',
    ]);

    if ($cart->recipientKey !== 'customer@example.com') {
        throw new RuntimeException('The recipient key was not hydrated.');
    }

    echo "PASS: cart rows with recipient keys can be hydrated\n";
}
