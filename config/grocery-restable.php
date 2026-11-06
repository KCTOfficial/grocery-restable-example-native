<?php

// Bootstrap configuration of Grocery Restable (no framework).
// Everything else is configured in the admin GUI and stored in SQLite (writable/).
return [
    'framework' => 'standalone',
    'storagePath' => __DIR__ . '/../writable/grocery-restable',   // outside public/
    'apiUrl' => '/api',
    'adminUrl' => '/grocery-restable',
    'debug' => true,                                               // demo only
    'encryptionKey' => 'base64:mqnrW30iVg9/+ql/vN3xNSjes3EfEha96tZVNuELWug=',
];
