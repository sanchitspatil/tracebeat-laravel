<?php

return [
    /*
    | Base URL of your TraceBeat server, for example https://tracebeat.example.com
    | No trailing slash. The agent authenticates against {url}/api/agent-auth.
    */
    'url' => env('TRACEBEAT_URL', env('NIGHTWATCH_BASE_URL')),

    /*
    | The environment token created in TraceBeat (Application > Environments).
    */
    'token' => env('TRACEBEAT_TOKEN', env('NIGHTWATCH_TOKEN')),
];
