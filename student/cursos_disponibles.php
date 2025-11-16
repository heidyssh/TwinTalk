<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]);

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";
$error = "";

// Obtener id de estado "Activa" para la matrícula
function obtenerEstadoActiva($mysqli) {
    $res = $mysqli->query("SELECT id FROM estados_matricula WHERE nombre_estado = 'Activa' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return null;
}

if (isset($_GET['matricular'])) {
    $horario_id = (int) $_GET['matricular'];

    $estado_activa_id = obtenerEstadoActiva($mysqli);
    if (!$estado_activa_id) {
        $error = "No se encontró el estado de matrícula 'Activa'. Pídale al admin que lo cree en la tabla estados_matricula.";
    } else {
        // ¿ya está matriculado?
        $check = $mysqli->prepare("
            SELECT id FROM matriculas
            WHERE estudiante_id = ? AND horario_id = ? AND estado_id = ?
        ");
        $check->bind_param("iii", $usuario_id, $horario_id, $estado_activa_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Ya estás matriculado en este horario.";
        } else {
            // Insertar matrícula
            $ins = $mysqli->prepare("
                INSERT INTO matriculas (estudiante_id, horario_id, fecha_matricula, estado_id)
                VALUES (?, ?, NOW(), ?)
            ");
            $ins->bind_param("iii", $usuario_id, $horario_id, $estado_activa_id);
            if ($ins->execute()) {
                // reducir cupos
                $mysqli->query("UPDATE horarios SET cupos_disponibles = cupos_disponibles - 1 WHERE id = $horario_id AND cupos_disponibles > 0");
                $mensaje = "¡Matrícula realizada con éxito!";
            } else {
                $error = "Error al matricularte: " . $mysqli->error;
            }
        }
    }
}

// Listado general de horarios
$cursos = $mysqli->query("
    SELECT h.id AS horario_id, c.nombre_curso, n.codigo_nivel,
           d.nombre_dia, h.hora_inicio, h.hora_fin, h.cupos_disponibles
    FROM horarios h
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.activo = 1 AND h.cupos_disponibles > 0
    ORDER BY h.fecha_inicio ASC
");

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Cursos disponibles</h1>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-responsive table-rounded mt-3">
    <table class="table align-middle">
        <thead class="table-light">
        <tr>
            <th>Curso</th>
            <th>Nivel</th>
            <th>Día</th>
            <th>Hora</th>
            <th>Cupos</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($cursos && $cursos->num_rows > 0): ?>
            <?php while ($row = $cursos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nombre_curso']) ?></td>
                    <td><?= htmlspecialchars($row['codigo_nivel']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                    <td><?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?></td>
                    <td><?= (int)$row['cupos_disponibles'] ?></td>
                    <td>
                        <a href="?matricular=<?= (int)$row['horario_id'] ?>"
                           class="btn btn-sm btn-tt-primary">Matricular</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" class="text-muted">No hay horarios disponibles.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
