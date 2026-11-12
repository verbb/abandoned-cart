<?php
namespace verbb\abandonedcart\controllers;

use verbb\abandonedcart\AbandonedCart;
use verbb\abandonedcart\db\Table;
use verbb\abandonedcart\models\Customer;
use verbb\abandonedcart\models\Recovery;
use verbb\abandonedcart\models\SkippedCart;

use Craft;
use craft\helpers\AdminTable;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;

use DateTime;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use craft\commerce\elements\Order;

class CustomersController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        return $this->renderTemplate('abandoned-cart/customers/index', [
            'counts' => AbandonedCart::$plugin->getReports()->getCustomerCounts(),
            'frequent' => AbandonedCart::$plugin->getReports()->countFrequentAbandoners(new DateTime('-30 days')),
        ]);
    }

    public function actionList(): Response
    {
        $this->requireAcceptsJson();

        $page = max(1, (int)$this->request->getParam('page', 1));
        $pageSize = min(100, max(10, (int)$this->request->getParam('per_page', 50)));
        $sort = $this->request->getParam('sort')[0] ?? [];
        $result = AbandonedCart::$plugin->getReports()->getCustomerList([
            'status' => $this->request->getParam('status', 'all'),
            'search' => $this->request->getParam('search'),
        ], $page, $pageSize, (string)($sort['sortField'] ?? 'carts'), (string)($sort['direction'] ?? 'desc'));

        $formatter = Craft::$app->getFormatter();
        $currency = AbandonedCart::$plugin->getReports()->getCurrency();

        $rows = array_map(static function(array $row) use ($formatter, $currency): array {
            $carts = (int)$row['carts'];
            $recovered = (int)$row['recovered'];

            return [
                'id' => (int)$row['id'],
                'url' => UrlHelper::cpUrl('abandoned-cart/customers/' . $row['id']),
                'customer' => $row['name'] ?: null,
                'email' => $row['email'],
                'status' => $row['status'],
                'statusLabel' => self::statusLabels()[$row['status']] ?? $row['status'],
                'detail' => self::statusDetail($row),
                'carts' => $carts,
                'recovered' => $recovered,
                'rate' => $carts ? $formatter->asPercent($recovered / $carts, 0) : '—',
                'revenue' => (float)$row['revenue'],
                'revenueLabel' => (float)$row['revenue'] ? $formatter->asCurrency($row['revenue'], $currency) : '—',
                'lastAbandoned' => $row['lastAbandoned'] ? $formatter->asDate(DateTimeHelper::toDateTime($row['lastAbandoned']), 'short') : '—',
            ];
        }, $result['rows']);

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $result['total'], $pageSize),
            'data' => $rows,
        ]);
    }

    public function actionView(int $customerId): Response
    {
        $plugin = AbandonedCart::$plugin;
        $customer = $plugin->getCustomers()->getCustomerById($customerId);

        if (!$customer) {
            throw new NotFoundHttpException('Customer not found');
        }

        return $this->renderTemplate('abandoned-cart/customers/_view', self::profileVariables($customer));
    }

    /**
     * The variables the customer profile templates share, used by the profile page and the user edit screen.
     */
    public static function profileVariables(Customer $customer): array
    {
        $plugin = AbandonedCart::$plugin;
        $history = $plugin->getReports()->getCustomerHistory($customer->id);
        $recoveryIds = array_map(static fn(array $row): int => (int)$row['id'], array_filter($history, static fn(array $row): bool => $row['kind'] === 'recovery'));
        $progress = $plugin->getReports()->getEmailProgress($recoveryIds);
        $since = new DateTime('-30 days');
        $recent = array_filter($history, static fn(array $row): bool => DateTimeHelper::toDateTime($row['abandonedAt']) >= $since);
        $sent = 0;
        $clicked = 0;

        foreach ($progress as $steps) {
            foreach ($steps as $step) {
                $sent += in_array($step['status'], ['sent', 'clicked'], true) ? 1 : 0;
                $clicked += $step['status'] === 'clicked' ? 1 : 0;
            }
        }

        $recoveredRows = array_filter($history, static fn(array $row): bool => $row['status'] === Recovery::STATUS_RECOVERED);
        $contactSkips = count(array_filter($recent, static fn(array $row): bool => $row['kind'] === 'skipped' && $row['reason'] === SkippedCart::REASON_CONTACT_LIMIT));
        $user = $customer->getUser();
        $lifetime = Order::find()->email($customer->email)->isCompleted(true)->status(null);

        return [
            'customer' => $customer,
            'user' => $user,
            'history' => $history,
            'progress' => $progress,
            'sequences' => array_column(array_map(static fn($s): array => ['id' => $s->id, 'name' => $s->name], $plugin->getSequences()->getAllSequences()), 'name', 'id'),
            'stats' => [
                'recentCarts' => count($recent),
                'allCarts' => count($history),
                'recovered' => count($recoveredRows),
                'revenue' => array_sum(array_map(static fn(array $row): float => (float)$row['orderTotal'], $recoveredRows)),
                'sent' => $sent,
                'clicked' => $clicked,
                'lifetimeOrders' => (int)$lifetime->count(),
                'lifetimeValue' => (float)$lifetime->sum('[[commerce_orders.totalPrice]]'),
            ],
            'noticed' => array_values(array_filter([
                $contactSkips ? Craft::t('abandoned-cart', '**{count} of {total} carts weren’t emailed** because of the Contact Limit.', ['count' => $contactSkips, 'total' => count($recent)]) : null,
                $clicked && $clicked > count($recoveredRows) ? Craft::t('abandoned-cart', '**Clicks but rarely buys.** Clicked {clicked} of {sent} emails and completed {recovered} of those carts.', ['clicked' => $clicked, 'sent' => $sent, 'recovered' => count($recoveredRows)]) : null,
                count($recent) >= 3 && !$recoveredRows ? Craft::t('abandoned-cart', '**Abandons often.** {count} carts in the last 30 days, none recovered.', ['count' => count($recent)]) : null,
            ])),
            'nextSequenceAt' => self::_nextSequenceAt($customer),
            'statusLabels' => self::statusLabels(),
            'changedBy' => $customer->statusChangedById ? Craft::$app->getUsers()->getUserById($customer->statusChangedById) : null,
        ];
    }

    public function actionDoNotEmail(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_DO_NOT_EMAIL);

        $customers = AbandonedCart::$plugin->getCustomers();
        $ids = (array)$this->request->getBodyParam('ids', []);
        $note = trim((string)$this->request->getBodyParam('note')) ?: null;
        $changed = 0;

        foreach ($ids as $id) {
            $customer = $customers->getCustomerById((int)$id);

            if ($customer && $customer->status !== Customer::STATUS_DO_NOT_EMAIL && $customers->changeStatus($customer, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF, $note)) {
                $changed++;
            }
        }

        return $this->asSuccess(Craft::t('abandoned-cart', '{count, plural, =1{1 customer} other{# customers}} added to Do Not Email.', ['count' => $changed]));
    }

    public function actionAddAddress(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_DO_NOT_EMAIL);

        $email = trim((string)$this->request->getRequiredBodyParam('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Enter a valid email address.'));
        }

        $customers = AbandonedCart::$plugin->getCustomers();
        $customer = $customers->getOrCreateCustomer($email);
        $customers->changeStatus($customer, Customer::STATUS_DO_NOT_EMAIL, Customer::SOURCE_STAFF, trim((string)$this->request->getBodyParam('note')) ?: null);

        return $this->asSuccess(Craft::t('abandoned-cart', '{email} won’t be emailed.', ['email' => $customer->email]));
    }

    public function actionRemoveDoNotEmail(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_MANAGE_DO_NOT_EMAIL);

        $customers = AbandonedCart::$plugin->getCustomers();
        $customer = $customers->getCustomerById((int)$this->request->getRequiredBodyParam('id'));

        if (!$customer || $customer->status !== Customer::STATUS_DO_NOT_EMAIL) {
            return $this->asFailure(Craft::t('abandoned-cart', 'This customer isn’t on Do Not Email.'));
        }

        if (!$customers->removeDoNotEmail($customer)) {
            return $this->asFailure($customer->getFirstError('status') ?? Craft::t('abandoned-cart', 'Couldn’t remove {email} from Do Not Email.', ['email' => $customer->email]));
        }

        if ($customer->status === Customer::STATUS_UNSUBSCRIBED) {
            return $this->asSuccess(Craft::t('abandoned-cart', '{email} removed from Do Not Email. They’re still unsubscribed.', ['email' => $customer->email]));
        }

        return $this->asSuccess(Craft::t('abandoned-cart', '{email} can be emailed again.', ['email' => $customer->email]));
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(AbandonedCart::PERMISSION_DELETE_CUSTOMERS);

        $customers = AbandonedCart::$plugin->getCustomers();
        $customer = $customers->getCustomerById((int)$this->request->getRequiredBodyParam('id'));

        if (!$customer) {
            throw new NotFoundHttpException('Customer not found');
        }

        if (!$customers->deleteCustomer($customer)) {
            return $this->asFailure(Craft::t('abandoned-cart', 'Couldn’t delete customer.'));
        }

        return $this->asSuccess(Craft::t('abandoned-cart', 'Customer deleted.'), [], 'abandoned-cart/customers');
    }

    public static function statusLabels(): array
    {
        return [
            Customer::STATUS_ACTIVE => Craft::t('abandoned-cart', 'Can be emailed'),
            Customer::STATUS_UNSUBSCRIBED => Craft::t('abandoned-cart', 'Unsubscribed'),
            Customer::STATUS_DO_NOT_EMAIL => Craft::t('abandoned-cart', 'Do not email'),
        ];
    }

    public static function statusDetail(array $row): ?string
    {
        $date = $row['statusChangedAt'] ? Craft::$app->getFormatter()->asDate(DateTimeHelper::toDateTime($row['statusChangedAt']), 'short') : null;

        return match ($row['status']) {
            Customer::STATUS_UNSUBSCRIBED => $date ? Craft::t('abandoned-cart', 'Unsubscribed {date}', ['date' => $date]) : null,
            Customer::STATUS_DO_NOT_EMAIL => trim(implode(' · ', array_filter([$row['statusNote'] ?? null, $date]))) ?: null,
            default => (int)($row['active'] ?? 0) ? Craft::t('abandoned-cart', 'In recovery now') : null,
        };
    }


    // Private Methods
    // =========================================================================

    private static function _nextSequenceAt(Customer $customer): ?DateTime
    {
        $lastSent = (new \craft\db\Query())
            ->select(['MAX([[a.sentAt]])'])
            ->from(['a' => Table::ATTEMPTS])
            ->innerJoin(['r' => Table::RECOVERIES], '[[r.id]] = [[a.recoveryId]]')
            ->where(['r.customerId' => $customer->id, 'a.status' => 'sent'])
            ->scalar();

        if (!$lastSent) {
            return null;
        }

        $next = DateTimeHelper::toDateTime($lastSent)->modify('+' . AbandonedCart::$plugin->getSettings()->contactLimitMinutes . ' minutes');

        return $next > new DateTime() ? $next : null;
    }
}
