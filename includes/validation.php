<?php

const REGLAS_PACIENTE = [
    'nombre'     => ['label' => 'Nombre',     'pattern' => '/^\p{L}[\p{L}\s\'-]{1,59}\z/u',              'hint' => 'Solo letras y espacios, 2 a 60 caracteres.'],
    'apellido'   => ['label' => 'Apellido',   'pattern' => '/^\p{L}[\p{L}\s\'-]{1,59}\z/u',              'hint' => 'Solo letras y espacios, 2 a 60 caracteres.'],
    'ci'         => ['label' => 'Cédula',     'pattern' => '/^\d{7,8}\z/',                               'hint' => '7 u 8 dígitos, sin puntos ni guion.'],
    'direccion'  => ['label' => 'Dirección',  'pattern' => '/^(?!.*[<>]).{5,160}\z/u',                   'hint' => 'Calle y número, 5 a 160 caracteres, sin < ni >.'],
    'numero'     => ['label' => 'Teléfono',   'pattern' => '/^\+?\d[\d\s-]{7,24}\z/',                    'hint' => '8 a 25 dígitos; se permite + inicial.'],
    'email'      => ['label' => 'Correo',     'filter'  => FILTER_VALIDATE_EMAIL,                       'hint' => 'Formato nombre@dominio.com.'],
    'contrasena' => ['label' => 'Contraseña', 'pattern' => '/^(?=.*\p{L})(?=.*\d)(?=.*[^\p{L}\d\s]).{8,72}\z/u', 'hint' => 'Mínimo 8 caracteres, con al menos una letra, un número y un carácter especial.', 'maxbytes' => 72],
];

const REGLAS_STAFF = [
    'nombre'     => REGLAS_PACIENTE['nombre'],
    'apellido'   => REGLAS_PACIENTE['apellido'],
    'ci'         => REGLAS_PACIENTE['ci'],
    'email'      => REGLAS_PACIENTE['email'],
    'contrasena' => REGLAS_PACIENTE['contrasena'],
];

function texto_post(string $campo): string
{
    return isset($_POST[$campo]) && is_scalar($_POST[$campo]) ? trim((string) $_POST[$campo]) : '';
}

function contiene_html(string $valor): bool
{
    return str_contains($valor, '<') || str_contains($valor, '>');
}

function validar(array $reglas, array $datos): array
{
    $errores = [];
    foreach ($reglas as $campo => $regla) {
        if (isset($datos[$campo]) && !is_scalar($datos[$campo])) {
            $errores[$campo] = 'Formato de ' . $regla['label'] . ' inválido. ' . $regla['hint'];
            continue;
        }
        $valor = isset($datos[$campo]) ? trim((string) $datos[$campo]) : '';

        if ($valor === '') {
            $errores[$campo] = $regla['label'] . ' es obligatorio.';
            continue;
        }

        if (isset($regla['filter'])) {
            if (filter_var($valor, $regla['filter']) === false) {
                $errores[$campo] = 'Formato de ' . $regla['label'] . ' inválido. ' . $regla['hint'];
            }
            continue;
        }

        if (isset($regla['maxbytes']) && strlen($valor) > $regla['maxbytes']) {
            $errores[$campo] = 'Formato de ' . $regla['label'] . ' inválido. ' . $regla['hint'] . ' Máximo ' . $regla['maxbytes'] . ' bytes.';
            continue;
        }

        if (isset($regla['pattern']) && !preg_match($regla['pattern'], $valor)) {
            $errores[$campo] = 'Formato de ' . $regla['label'] . ' inválido. ' . $regla['hint'];
        }
    }
    return $errores;
}
