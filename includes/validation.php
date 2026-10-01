<?php
// includes/validation.php — single source of truth for field rules AND
// their UI hints, so a hint can never drift from the rule it describes.
// Client-side `pattern` is a convenience only; this file is authoritative.

const REGLAS_PACIENTE = [
    'nombre'     => ['label' => 'Nombre',     'pattern' => '/^\p{L}[\p{L}\s\'-]{1,59}$/u',              'hint' => 'Solo letras y espacios, 2 a 60 caracteres.'],
    'apellido'   => ['label' => 'Apellido',   'pattern' => '/^\p{L}[\p{L}\s\'-]{1,59}$/u',              'hint' => 'Solo letras y espacios, 2 a 60 caracteres.'],
    'ci'         => ['label' => 'Cedula',     'pattern' => '/^\d{7,8}$/',                               'hint' => '7 u 8 digitos, sin puntos ni guion.'],
    'direccion'  => ['label' => 'Direccion',  'pattern' => '/^.{5,160}$/u',                             'hint' => 'Calle y numero, 5 a 160 caracteres.'],
    'numero'     => ['label' => 'Telefono',   'pattern' => '/^\+?\d[\d\s-]{7,24}$/',                    'hint' => '8 a 25 digitos; se permite + inicial.'],
    'email'      => ['label' => 'Correo',     'filter'  => FILTER_VALIDATE_EMAIL,                       'hint' => 'Formato nombre@dominio.com.'],
    'contrasena' => ['label' => 'Contrasena', 'pattern' => '/^(?=.*\p{L})(?=.*\d).{8,72}$/u',           'hint' => 'Minimo 8 caracteres, con al menos una letra y un numero.'],
];

// Reuses the same nombre/apellido/email/contrasena rules as REGLAS_PACIENTE
// (they were already generic, not paciente-specific) for staff accounts
// (medico/profesional/administrador) created from manejar-usuarios.php.
const REGLAS_STAFF = [
    'nombre'     => REGLAS_PACIENTE['nombre'],
    'apellido'   => REGLAS_PACIENTE['apellido'],
    'email'      => REGLAS_PACIENTE['email'],
    'contrasena' => REGLAS_PACIENTE['contrasena'],
];

/**
 * Validates $datos against $reglas. Returns an associative array of
 * field => error message for every field that failed; an empty array
 * means every field passed.
 *
 * @param array<string,array{label:string,pattern?:string,filter?:int,hint:string}> $reglas
 * @param array<string,mixed> $datos
 * @return array<string,string>
 */
function validar(array $reglas, array $datos): array
{
    $errores = [];
    foreach ($reglas as $campo => $regla) {
        $valor = isset($datos[$campo]) ? trim((string) $datos[$campo]) : '';

        if ($valor === '') {
            $errores[$campo] = $regla['label'] . ' es obligatorio.';
            continue;
        }

        if (isset($regla['filter'])) {
            if (filter_var($valor, $regla['filter']) === false) {
                $errores[$campo] = 'Formato de ' . $regla['label'] . ' invalido. ' . $regla['hint'];
            }
            continue;
        }

        if (isset($regla['pattern']) && !preg_match($regla['pattern'], $valor)) {
            $errores[$campo] = 'Formato de ' . $regla['label'] . ' invalido. ' . $regla['hint'];
        }
    }
    return $errores;
}
