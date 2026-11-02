<?php
require_once __DIR__ . '/../includes/auditoria.php';

function igual(mixed $obtenido, mixed $esperado, string $caso): void
{
    assert($obtenido === $esperado, $caso . ': obtenido ' . var_export($obtenido, true));
}

foreach ([
    '203.0.113.9'               => '203.0.113.9',
    '2001:db8:1:2:aaaa:bbbb::1' => '2001:db8:1:2::',
    '2001:db8:1:2::ffff'        => '2001:db8:1:2::',
    '::ffff:198.51.100.7'       => '198.51.100.7',
] as $entrada => $esperado) {
    $_SERVER['REMOTE_ADDR'] = $entrada;
    igual(ip_cliente(), $esperado, 'ip_cliente ' . $entrada);
}

echo "php-checks: ok\n";
