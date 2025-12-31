<?php

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php'; // QUESTA RIGA DEVE ESSERE QUI

// La riga per i trusted proxies va dopo, se presente
// Esempio: Symfony\Component\HttpFoundation\Request::setTrustedProxies(...)

return function (array $context) {
    return new Kernel('dev', true);
};
