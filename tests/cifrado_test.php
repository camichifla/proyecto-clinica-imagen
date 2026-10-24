<?php
// Run: php tests/cifrado_test.php — throwaway key, no DB or config.php needed.
// Own check(): php.ini may ship zend.assertions=-1, which would turn assert() into a no-op.
function check(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

define('APP_KEY', base64_encode(random_bytes(32)));
require __DIR__ . '/../includes/cifrado.php';

$texto = "Paciente con dolor molar \u{00e1}\u{00f1}";
$c = cifrar($texto);
check(str_starts_with($c, 'v1:') && $c !== $texto, 'prefix and not plaintext');
check(descifrar($c) === $texto, 'round-trip');
check(cifrar($texto) !== $c, 'random IV');
check(descifrar('nota vieja sin cifrar') === 'nota vieja sin cifrar', 'legacy passthrough'); // legacy passthrough

// Tamper detection: flipping one ciphertext byte must THROW (GCM tag mismatch).
$raw = base64_decode(substr($c, 3));
$raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";
try {
    descifrar('v1:' . base64_encode($raw));
    check(false, 'tamper not detected');
} catch (RuntimeException $e) {
}
try {
    descifrar('v1:abc');
    check(false, 'truncated not detected');
} catch (RuntimeException $e) {
}
echo "cifrado_test OK\n";
