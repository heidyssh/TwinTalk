<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]);

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";
$error = "";

// Helper para obtener id de estado por nombre
function obtenerEstadoId($mysqli, $nombre)
{
    $stmt = $mysqli->prepare("SELECT id FROM estados_matricula WHERE nombre_estado = ? LIMIT 1");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return (int) $row['id'];
    }
    return null;
}

// Procesar retiro de matrícula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirar_matricula'])) {
    $matricula_id = (int) ($_POST['matricula_id'] ?? 0);

    // Verificar que la matrícula pertenece al estudiante y está Activa
    $stmt = $mysqli->prepare("
        SELECT m.id, m.horario_id, em.nombre_estado
        FROM matriculas m
        INNER JOIN estados_matricula em ON m.estado_id = em.id
        WHERE m.id = ? AND m.estudiante_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $matricula_id, $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $mat = $res->fetch_assoc();
    $stmt->close();

    if (!$mat) {
        $error = "Matrícula no válida.";
    } elseif ($mat['nombre_estado'] !== 'Activa') {
        $error = "Solo puedes retirarte de matrículas activas.";
    } else {
        $estado_cancelada = obtenerEstadoId($mysqli, 'Cancelada');
        if ($estado_cancelada === null) {
            $error = "No se encontró el estado 'Cancelada' en la tabla estados_matricula.";
        } else {
            // Cambiar estado a Cancelada
            $upd = $mysqli->prepare("UPDATE matriculas SET estado_id = ? WHERE id = ?");
            $upd->bind_param("ii", $estado_cancelada, $matricula_id);
            if ($upd->execute()) {
                // Liberar cupo del horario
                $upd2 = $mysqli->prepare("UPDATE horarios SET cupos_disponibles = cupos_disponibles + 1 WHERE id = ?");
                $upd2->bind_param("i", $mat['horario_id']);
                $upd2->execute();
                $upd2->close();

                $mensaje = "Te has retirado de la clase correctamente.";
            } else {
                $error = "No se pudo actualizar la matrícula.";
            }
            $upd->close();
        }
    }
}

// Consulta de historial con nota promedio (calificaciones.publicado = 1)
$stmt = $mysqli->prepare("
   SELECT 
        m.id AS matricula_id,
        h.id AS horario_id,
        c.nombre_curso,
        n.codigo_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        em.nombre_estado,
        m.fecha_matricula,
        m.monto_pagado,
        mp.nombre_metodo,
        cal.promedio
    FROM matriculas m
    JOIN horarios h ON m.horario_id = h.id
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    JOIN estados_matricula em ON m.estado_id = em.id
    LEFT JOIN metodos_pago mp ON m.metodo_pago_id = mp.id
    LEFT JOIN (
        SELECT matricula_id, AVG(puntaje) AS promedio
        FROM calificaciones
        WHERE publicado = 1
        GROUP BY matricula_id
    ) cal ON cal.matricula_id = m.id
    WHERE m.estudiante_id = ?
    ORDER BY m.fecha_matricula DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Mis matrículas e historial académico</h1>
<p class="text-muted mb-3">
    Aquí puedes ver tus cursos, estados de matrícula y tu nota promedio publicada.
</p>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-soft p-3">
    <div class="table-responsive table-rounded">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Curso</th>
                    <th>Nivel</th>
                    <th>Día</th>
                    <th>Hora</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Nota promedio</th>
                    <th>Fecha matrícula</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nombre_curso']) ?></td>
                            <td><?= htmlspecialchars($row['codigo_nivel']) ?></td>
                            <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                            <td><?= substr($row['hora_inicio'], 0, 5) ?> - <?= substr($row['hora_fin'], 0, 5) ?></td>
                            <td><?= htmlspecialchars($row['nombre_estado']) ?></td>
                            <td>
                                <?php if ($row['monto_pagado'] !== null): ?>
                                    L <?= number_format($row['monto_pagado'], 2) ?>
                                    <?php if (!empty($row['nombre_metodo'])): ?>
                                        <span class="text-muted small">(<?= htmlspecialchars($row['nombre_metodo']) ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Pendiente de pago</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['promedio'] !== null): ?>
                                    <?= number_format($row['promedio'], 2) ?>

                                    <span class="text-muted small">Sin notas</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['fecha_matricula']) ?></td>
                            <td>
                                <?php if ($row['nombre_estado'] === 'Activa'): ?>
                                    <form method="post" class="d-inline"
                                        onsubmit="return confirm('¿Seguro que deseas retirarte de esta clase?');">
                                        <input type="hidden" name="matricula_id" value="<?= (int) $row['matricula_id'] ?>">
                                        <button type="submit" name="retirar_matricula" class="btn btn-sm btn-outline-danger me-1">
                                            Retirarme
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="curso_detalle.php?horario_id=<?= (int) $row['horario_id'] ?>"
                                    class="btn btn-sm btn-outline-primary mb-1">
                                    Ver curso
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-muted">Aún no tienes matrículas registradas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>