<?php

return [
    'logger' => [
        'level' => 'DEBUG',
        'databaseHandler' => true,
        'databaseHandlerLevel' => 'DEBUG',
        'printTrace' => true,
        'handlers' => ['default', 'database'],
    ],
];

