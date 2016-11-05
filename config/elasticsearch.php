<?php

return [
    'hosts' => [
        env('ES_HOST', 'localhost') . ':' . env('ES_PORT', 9200),
    ],
];
