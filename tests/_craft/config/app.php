<?php

return [
    'components' => [
        'projectConfig' => function() {
            $config = craft\helpers\App::projectConfigConfig();
            $config['writeYamlAutomatically'] = false;

            return Craft::createObject($config);
        },
        // Sends are written to storage/runtime/mail instead of a transport, so tests can count real deliveries.
        'mailer' => function() {
            $config = craft\helpers\App::mailerConfig();
            $config['useFileTransport'] = true;

            return Craft::createObject($config);
        },
    ],
];
