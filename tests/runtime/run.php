<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$package = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$config = json_decode(file_get_contents(__DIR__ . '/suite.json'), true, 512, JSON_THROW_ON_ERROR);
$project = getenv('DDEV_SITENAME');

if (getenv('IS_DDEV_PROJECT') !== 'true' || $project !== $config['project']) {
    throw new RuntimeException('Refusing to reset outside this plugin’s dedicated DDEV test project.');
}

$runtime = $root . '/.cache/verbb-tests';

foreach (['.cache', '.cache/verbb-tests', '.cache/verbb-tests/app'] as $relative) {
    $path = $root . '/' . $relative;

    if (is_link($path)) {
        throw new RuntimeException('Refusing symlinked runtime path: ' . $relative);
    }

    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create runtime directory.');
    }
}

$lock = fopen($runtime . '/run.lock', 'c');

if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Another test run owns this environment.');
}

$owner = ['project' => $project, 'source' => realpath($root)];
$ownerFile = $runtime . '/owner.json';

if (is_file($ownerFile) && json_decode(file_get_contents($ownerFile), true) !== $owner) {
    throw new RuntimeException('Runtime ownership mismatch.');
}

file_put_contents($ownerFile, json_encode($owner, JSON_PRETTY_PRINT));
file_put_contents($runtime . '/result.json', json_encode(['status' => 'running', 'startedAt' => gmdate(DATE_ATOM)]));
@unlink($runtime . '/junit.xml');

try {
    $arguments = array_slice($argv, 1);
    $updateLock = false;
    $database = 'mysql';

    foreach ($arguments as $index => $argument) {
        if (str_starts_with($argument, '--database=')) {
            $database = substr($argument, 11);
            unset($arguments[$index]);
        }

        if ($argument === '--update-lock') {
            $updateLock = true;
            unset($arguments[$index]);
        }

        if ($argument === '--parallel' || str_starts_with($argument, '--processes')) {
            throw new RuntimeException('Parallel workers require independent runtime state and are not enabled.');
        }
    }

    if (!in_array($database, ['mysql', 'pgsql'], true)) {
        throw new RuntimeException('Unknown test database.');
    }

    // Both choices name fixed services owned by this dedicated test project.
    // Never accept an arbitrary database DSN or credentials from the environment.
    $databaseConfig = [
        'driver' => $database,
        'server' => $database === 'pgsql' ? 'matrix-pgsql' : 'db',
        'port' => $database === 'pgsql' ? '5432' : '3306',
        'database' => 'db',
        'user' => 'db',
        'password' => 'db',
    ];
    file_put_contents($runtime . '/database.json', json_encode($databaseConfig));

    $log = fopen($runtime . '/latest.log', 'w');
    $run = static function(array $command, ?string $cwd = null) use ($root, $log): void {
        $process = proc_open($command, [
            0 => STDIN,
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ], $pipes, $cwd ?? $root);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start ' . $command[0]);
        }

        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            echo $chunk;
            fwrite($log, $chunk);
        }

        fclose($pipes[1]);
        $code = proc_close($process);

        if ($code !== 0) {
            throw new RuntimeException($command[0] . ' failed with exit code ' . $code);
        }
    };
    $remove = static function(string $path) use (&$remove): void {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (new FilesystemIterator($path) as $item) {
            $remove($item->getPathname());
        }

        rmdir($path);
    };
    $app = $runtime . '/app';
    $requirements = array_merge($package['require-dev'] ?? [], [$package['name'] => '*']);
    $manifest = [
        'name' => 'verbb/abandoned-cart-test-app',
        'type' => 'project',
        'require' => $requirements,
        'repositories' => [[
            'type' => 'path',
            'url' => '../../..',
            'options' => ['symlink' => true, 'reference' => 'config'],
        ]],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
        'autoload-dev' => [
            'psr-4' => [
                'verbb\\abandonedcart\\tests\\' => '../../../tests/',
            ],
        ],
        'config' => [
            'allow-plugins' => [
                'craftcms/plugin-installer' => true,
                'pestphp/pest-plugin' => true,
                'yiisoft/yii2-composer' => true,
            ],
        ],
    ];

    file_put_contents($app . '/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    if (is_file(__DIR__ . '/composer.lock')) {
        copy(__DIR__ . '/composer.lock', $app . '/composer.lock');
    }

    $run(['composer', $updateLock ? 'update' : 'install', '--no-interaction', '--prefer-dist', '--no-progress'], $app);

    if ($updateLock) {
        copy($app . '/composer.lock', __DIR__ . '/composer.lock');
    }

    foreach (['config', 'storage', 'web'] as $name) {
        $remove($app . '/' . $name);
        mkdir($app . '/' . $name, 0775, true);
    }

    foreach (glob($root . '/tests/_craft/config/*.php') as $source) {
        copy($source, $app . '/config/' . basename($source));
    }

    $originalAppConfig = file_get_contents($app . '/config/app.php');
    file_put_contents($app . '/config/plugin-tests.php', $originalAppConfig);
    file_put_contents($app . '/config/app.php', <<<'PHP'
<?php
$config = require __DIR__ . '/plugin-tests.php';
$config['baseApiUrl'] = 'http://127.0.0.1:9/';

return $config;
PHP);
    file_put_contents($app . '/config/db.php', "<?php\nreturn " . var_export($databaseConfig, true) . ";\n");
    file_put_contents($app . '/config/general.php', "<?php\nreturn \\craft\\config\\GeneralConfig::create()->devMode(true)->allowAdminChanges(true)->timezone('UTC');\n");

    $db = new PDO($database . ':host=' . $databaseConfig['server'] . ';port=' . $databaseConfig['port'] . ';dbname=db', 'db', 'db', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($database === 'pgsql') {
        $db->exec('DROP SCHEMA public CASCADE');
        $db->exec('CREATE SCHEMA public');
    } else {
        $db->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $db->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }

        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    $run(['php', 'tests/runtime/craft.php', 'install/craft', '--interactive=0', '--username=admin', '--email=admin@example.test', '--password=testing-only-password', '--siteName=Abandoned Cart Tests', '--siteUrl=https://' . $project . '.ddev.site', '--language=en-US']);
    $run(['php', 'tests/runtime/craft.php', 'plugin/install', 'commerce', '--interactive=0']);
    $run(['php', 'tests/runtime/craft.php', 'plugin/install', $package['extra']['handle'], '--interactive=0']);
    $run(['php', 'tests/runtime/verify.php']);
    $run([
        'php',
        '-d',
        'memory_limit=1G',
        $app . '/vendor/pestphp/pest/bin/pest',
        '--fail-on-empty-test-suite',
        '--fail-on-risky',
        '--enforce-time-limit',
        '--default-time-limit=60',
        '--configuration',
        'phpunit.craft.xml',
        '--log-junit',
        $runtime . '/junit.xml',
        ...array_values($arguments),
    ]);

    $report = is_file($runtime . '/junit.xml') ? simplexml_load_file($runtime . '/junit.xml') : false;

    if (!$report || (int)$report->testsuite['tests'] < 1) {
        throw new RuntimeException('Pest exited without a completed, non-empty JUnit report.');
    }

    $counts = [];

    foreach (['tests', 'assertions', 'errors', 'failures', 'skipped'] as $key) {
        $counts[$key] = (int)$report->testsuite[$key];
    }

    file_put_contents($runtime . '/result.json', json_encode([
        'status' => 'passed',
        'counts' => $counts,
        'database' => $database,
        'databaseVersion' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
        'arguments' => array_values($arguments),
        'finishedAt' => gmdate(DATE_ATOM),
    ], JSON_PRETTY_PRINT));
    echo 'Completed ' . $counts['tests'] . ' tests; ' . $counts['assertions'] . " assertions.\n";
} catch (Throwable $error) {
    file_put_contents($runtime . '/result.json', json_encode([
        'status' => 'failed',
        'database' => $database ?? null,
        'message' => $error->getMessage(),
        'finishedAt' => gmdate(DATE_ATOM),
    ], JSON_PRETTY_PRINT));
    fwrite(STDERR, $error->getMessage() . "\nSee .cache/verbb-tests/latest.log\n");
    exit(1);
}
