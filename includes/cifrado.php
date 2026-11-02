<?php

const CIFRADO_PREFIJO = 'v1:';

function cifrado_clave(): string
{
    $clave = defined('APP_KEY') ? base64_decode((string) APP_KEY, true) : false;
    if ($clave === false || strlen($clave) !== 32) {
        throw new RuntimeException('APP_KEY no definida o inválida (se esperan 32 bytes en base64).');
    }
    return $clave;
}

function cifrar(string $texto): string
{
    $clave = cifrado_clave();
    $iv    = random_bytes(12);
    $tag   = '';
    $cifrado = openssl_encrypt($texto, 'aes-256-gcm', $clave, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cifrado === false) {
        throw new RuntimeException('No se pudo cifrar.');
    }
    return CIFRADO_PREFIJO . base64_encode($iv . $tag . $cifrado);
}

function descifrar(string $valor): string
{
    $clave = cifrado_clave();
    $raw   = str_starts_with($valor, CIFRADO_PREFIJO) ? base64_decode(substr($valor, strlen(CIFRADO_PREFIJO)), true) : false;
    if ($raw === false || strlen($raw) < 28) {
        throw new RuntimeException('Dato cifrado corrupto.');
    }
    $texto = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $clave, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($texto === false) {
        throw new RuntimeException('Dato cifrado corrupto o clave incorrecta.');
    }
    return $texto;
}
