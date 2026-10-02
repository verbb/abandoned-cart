<?php
namespace yii\web {
    class BadRequestHttpException extends \RuntimeException
    {
    }

    class ForbiddenHttpException extends \RuntimeException
    {
    }

    class MethodNotAllowedHttpException extends \RuntimeException
    {
    }

    class NotFoundHttpException extends \RuntimeException
    {
    }

    class Response
    {
        public ?string $location = null;
        public int $statusCode = 302;
    }
}

namespace craft\web {
    class Controller
    {
        public bool $enableCsrfValidation = true;
        public array $guardCalls = [];
        public mixed $request = null;

        public function beforeAction($action): bool
        {
            $this->guardCalls[] = 'parent';

            return true;
        }

        public function requirePostRequest(): void
        {
            $this->guardCalls[] = 'post';

            if (!$this->request->isPost) {
                throw new \yii\web\MethodNotAllowedHttpException('POST required.');
            }
        }

        public function requireCpRequest(): void
        {
        }

        public function requirePermission(string $permission): void
        {
        }

        public function requireAcceptsJson(): void
        {
        }
    }
}

namespace craft\helpers {
    class UrlHelper
    {
        public static function cpUrl(string $path): string
        {
            return "/admin/{$path}";
        }
    }
}

namespace verbb\abandonedcart {
    class AbandonedCart
    {
        public static mixed $plugin = null;
    }
}

namespace {
    require __DIR__ . '/../../src/controllers/CartsController.php';

    use verbb\abandonedcart\AbandonedCart;
    use verbb\abandonedcart\controllers\CartsController;
    use yii\web\BadRequestHttpException;
    use yii\web\ForbiddenHttpException;
    use yii\web\MethodNotAllowedHttpException;
    use yii\web\Response;

    class Craft
    {
        public static mixed $app = null;

        public static function t(string $category, string $message, array $params = []): string
        {
            return strtr($message, ['{num}' => (string)($params['num'] ?? '')]);
        }
    }

    class FixtureAction
    {
        public function __construct(public string $id)
        {
        }
    }

    class FixtureHeaders
    {
        public function __construct(private array $authorization = [])
        {
        }

        public function get(string $name, mixed $default = null, bool $first = true): mixed
        {
            if (strcasecmp($name, 'Authorization') !== 0 || $this->authorization === []) {
                return $default;
            }

            return $first ? $this->authorization[0] : $this->authorization;
        }
    }

    class FixtureRequest
    {
        public function __construct(
            public string $rawMethod = 'POST',
            public bool $isPost = true,
            private array $query = [],
            private array $body = [],
            private array $authorization = [],
        ) {
        }

        public function getQueryParams(): array
        {
            return $this->query;
        }

        public function getBodyParams(): array
        {
            return $this->body;
        }

        public function getHeaders(): FixtureHeaders
        {
            return new FixtureHeaders($this->authorization);
        }
    }

    class FixtureSettings
    {
        public function __construct(private mixed $passKey)
        {
        }

        public function getPassKey(): mixed
        {
            return $this->passKey;
        }
    }

    class FixtureCarts
    {
        public int $schedulerCalls = 0;

        public function getEmailsToSend(): int
        {
            $this->schedulerCalls++;

            return 0;
        }
    }

    class FixturePlugin
    {
        public function __construct(
            private FixtureSettings $settings,
            private FixtureCarts $carts,
        ) {
        }

        public function getSettings(): FixtureSettings
        {
            return $this->settings;
        }

        public function getCarts(): FixtureCarts
        {
            return $this->carts;
        }
    }

    class FixtureSession
    {
        public array $notices = [];

        public function setNotice(string $message): void
        {
            $this->notices[] = $message;
        }
    }

    class FixtureAppController
    {
        public function redirect(string $location): Response
        {
            $response = new Response();
            $response->location = $location;

            return $response;
        }
    }

    class FixtureApp
    {
        public FixtureAppController $controller;

        public function __construct(private FixtureSession $session)
        {
            $this->controller = new FixtureAppController();
        }

        public function getSession(): FixtureSession
        {
            return $this->session;
        }
    }

    function check(string $label, bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException("Failed: {$label}");
        }

        echo "PASS: {$label}\n";
    }

    function runScheduler(
        FixtureRequest $request,
        mixed $configuredPassKey = 'fixture-secret',
    ): array {
        $carts = new FixtureCarts();
        $session = new FixtureSession();
        AbandonedCart::$plugin = new FixturePlugin(new FixtureSettings($configuredPassKey), $carts);
        Craft::$app = new FixtureApp($session);

        $controller = new CartsController();
        $controller->request = $request;
        $previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = $request->rawMethod;

        $response = null;
        $exception = null;

        try {
            $response = $controller->actionFindCarts();
        } catch (Throwable $exception) {
        } finally {
            if ($previousRequestMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
            }
        }

        return [$response, $exception, $controller, $carts];
    }

    $controller = new CartsController();
    check('find-carts disables CSRF before dispatch', $controller->beforeAction(new FixtureAction('find-carts')) && !$controller->enableCsrfValidation);

    $controller = new CartsController();
    check('other actions retain CSRF protection', $controller->beforeAction(new FixtureAction('restore-cart')) && $controller->enableCsrfValidation);

    [$response, $exception, $controller, $carts] = runScheduler(new FixtureRequest(authorization: ['Bearer fixture-secret']));
    check('valid bearer credential runs the scheduler', $exception === null && $response instanceof Response && $response->location === '/admin/abandoned-cart' && $carts->schedulerCalls === 1);
    check('POST guard runs before scheduler authentication', $controller->guardCalls === ['post']);

    [$response, $exception, , $carts] = runScheduler(new FixtureRequest(authorization: ['bearer   fixture-secret']));
    check('bearer scheme matching is case-insensitive', $exception === null && $carts->schedulerCalls === 1);

    $invalidHeaders = [
        'missing header' => [],
        'wrong credential' => ['Bearer wrong'],
        'wrong scheme' => ['Basic fixture-secret'],
        'blank credential' => ['Bearer '],
        'trailing whitespace' => ["Bearer fixture-secret\t"],
        'combined credentials' => ['Bearer fixture-secret, Bearer fixture-secret'],
        'duplicate headers' => ['Bearer fixture-secret', 'Bearer fixture-secret'],
    ];

    foreach ($invalidHeaders as $label => $headers) {
        [, $exception, , $carts] = runScheduler(new FixtureRequest(authorization: $headers));
        check("{$label} is rejected before scheduling", $exception instanceof ForbiddenHttpException && $carts->schedulerCalls === 0);
    }

    [, $exception, , $carts] = runScheduler(new FixtureRequest(query: ['passkey' => 'fixture-secret'], authorization: ['Bearer fixture-secret']));
    check('query-string credential is rejected before scheduling', $exception instanceof BadRequestHttpException && $carts->schedulerCalls === 0);

    [, $exception, , $carts] = runScheduler(new FixtureRequest(body: ['passkey' => 'fixture-secret'], authorization: ['Bearer fixture-secret']));
    check('body credential is rejected before scheduling', $exception instanceof BadRequestHttpException && $carts->schedulerCalls === 0);

    [, $exception, , $carts] = runScheduler(new FixtureRequest(rawMethod: 'GET', isPost: true, authorization: ['Bearer fixture-secret']));
    check('method override cannot turn a raw GET into POST', $exception instanceof MethodNotAllowedHttpException && $carts->schedulerCalls === 0);

    [, $exception, , $carts] = runScheduler(new FixtureRequest(isPost: false, query: ['passkey' => 'fixture-secret']));
    check('effective non-POST request is rejected first', $exception instanceof MethodNotAllowedHttpException && $carts->schedulerCalls === 0);

    foreach ([null, '', '   '] as $configuredPassKey) {
        [, $exception, , $carts] = runScheduler(new FixtureRequest(authorization: ['Bearer fixture-secret']), $configuredPassKey);
        check('blank configured credential fails closed', $exception instanceof ForbiddenHttpException && $carts->schedulerCalls === 0);
    }
}
