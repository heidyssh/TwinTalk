<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]);

$usuario_id = $_SESSION['usuario_id'];

$stmt = $mysqli->prepare("
    SELECT m.id AS matricula_id,
           c.nombre_curso,
           n.codigo_nivel,
           d.nombre_dia,
           h.hora_inicio,
           h.hora_fin,
           em.nombre_estado,
           m.fecha_matricula
    FROM matriculas m
    JOIN horarios h ON m.horario_id = h.id
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.estudiante_id = ?
    ORDER BY m.fecha_matricula DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$matriculas = $stmt->get_result();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Mis matrículas</h1>
<p class="small text-muted mb-3">Historial de cursos y estados de matrícula.</p>

<div class="table-responsive table-rounded">
    <table class="table align-middle">
        <thead class="table-light">
        <tr>
            <th>Curso</th>
            <th>Nivel</th>
            <th>Día</th>
            <th>Horario</th>
            <th>Estado</th>
            <th>Fecha de matrícula</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($matriculas->num_rows > 0): ?>
            <?php while ($row = $matriculas->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nombre_curso']) ?></td>
                    <td><?= htmlspecialchars($row['codigo_nivel']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                    <td><?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?></td>
                    <td><?= htmlspecialchars($row['nombre_estado']) ?></td>
                    <td><?= htmlspecialchars($row['fecha_matricula']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" class="text-muted">Aún no tienes matrículas registradas.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
