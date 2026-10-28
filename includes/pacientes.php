<?php
require_once __DIR__ . '/db.php';

/**
 * Links to the newly created patient the citas and orders made by a doctor when only their email ($email) was known.
 * Runs inside the registration transaction.
 */
function vincular_citas_pendientes(PDO $db, int $pacienteId, string $email): void
{
    $stmt = $db->prepare('UPDATE citas SET paciente_id = ?, email_pendiente = NULL, nombre_pendiente = NULL, apellido_pendiente = NULL WHERE email_pendiente = ? AND paciente_id IS NULL');
    $stmt->execute([$pacienteId, $email]);

    $stmt = $db->prepare(
        'UPDATE ordenes o JOIN citas c ON c.id = o.cita_id
            SET o.paciente_id = ?
          WHERE c.paciente_id = ? AND o.paciente_id IS NULL'
    );
    $stmt->execute([$pacienteId, $pacienteId]);
}

/**
 * Frees CIs squatted through self-registration: deletes patient accounts left unverified for 48h (verification
 * tokens cascade). The pacientes row goes too unless it has history (citas, resultados, ordenes, notas_clinicas,
 * medico_paciente; every FK to pacientes is ON DELETE CASCADE, so deleting it would erase the history). In that case it is
 * kept with email_anterior = the deleted account's email, i.e. the "account deleted" state register.php already
 * handles: the legitimate owner of that email can sign up again and get the history back.
 * Staff-created accounts are verified at creation, so only self-registrations are affected. Returns accounts removed.
 */
function limpiar_cuentas_sin_verificar(PDO $db): int
{
    $filas = $db->query(
        "SELECT id, email, ref_id FROM cuentas
          WHERE rol = 'paciente' AND verificado = 0 AND creado_en < NOW() - INTERVAL 48 HOUR"
    )->fetchAll();

    $conHistoria = 'SELECT 1 FROM citas WHERE paciente_id = :p1 UNION ALL SELECT 1 FROM resultados WHERE paciente_id = :p2
                    UNION ALL SELECT 1 FROM ordenes WHERE paciente_id = :p3 UNION ALL SELECT 1 FROM notas_clinicas WHERE paciente_id = :p4
                    UNION ALL SELECT 1 FROM medico_paciente WHERE paciente_id = :p5 LIMIT 1';
    $borradas = 0;
    $liberados = 0;
    foreach ($filas as $fila) {
        try {
            $db->beginTransaction();
            // Re-check verificado inside the transaction: the user may have verified since the SELECT.
            $del = $db->prepare("DELETE FROM cuentas WHERE id = ? AND verificado = 0 AND rol = 'paciente'");
            $del->execute([$fila['id']]);
            if ($del->rowCount() === 1) {
                $p = (int) $fila['ref_id'];
                $stmt = $db->prepare($conHistoria);
                $stmt->execute(['p1' => $p, 'p2' => $p, 'p3' => $p, 'p4' => $p, 'p5' => $p]);
                if ($stmt->fetchColumn()) {
                    $db->prepare('UPDATE pacientes SET email_anterior = ? WHERE id = ?')->execute([$fila['email'], $p]);
                } else {
                    $db->prepare('DELETE FROM pacientes WHERE id = ?')->execute([$p]);
                    $liberados++;
                }
                $borradas++;
            }
            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('limpiar_cuentas_sin_verificar: ' . $e->getMessage());
        }
    }
    if ($borradas > 0) {
        registrar_evento('limpieza_sin_verificar', "cuentas=$borradas pacientes=$liberados");
    }
    return $borradas;
}
