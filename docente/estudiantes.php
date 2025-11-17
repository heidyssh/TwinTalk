<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([2]); // solo docentes

$docente_id = $_SESSION['usuario_id'] ?? 0;
$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

if ($horario_id <= 0) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">Horario no válido.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// Verificar que el horario pertenece a este docente y traer info del curso
$sqlCurso = "
    SELECT 
        h.id AS horario_id,
        c.nombre_curso,
        n.codigo_nivel,
        n.nombre_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN niveles_academicos n ON c.nivel_id = n.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.id = ? AND h.docente_id = ?
    LIMIT 1
";
$stmtCurso = $mysqli->prepare($sqlCurso);
$stmtCurso->bind_param("ii", $horario_id, $docente_id);
$stmtCurso->execute();
$resCurso = $stmtCurso->get_result();
$curso = $resCurso->fetch_assoc();
$stmtCurso->close();

if (!$curso) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No tienes acceso a este horario o no existe.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// Estudiantes matriculados en este horario
$sqlEstudiantes = "
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email,
        u.telefono,
        e.nivel_actual,
        em.nombre_estado,
        m.fecha_matricula
    FROM matriculas m
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u ON e.id = u.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.horario_id = ?
    ORDER BY u.apellido, u.nombre
";
$stmtEst = $mysqli->prepare($sqlEstudiantes);
$stmtEst->bind_param("i", $horario_id);
$stmtEst->execute();
$resEst = $stmtEst->get_result();
$stmtEst->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">
    Estudiantes · <?= htmlspecialchars($curso['nombre_curso']) ?>
</h1>
<p class="text-muted mb-3">
    Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?> ·
    <?= htmlspecialchars($curso['nombre_nivel']) ?> ·
    <?= htmlspecialchars($curso['nombre_dia']) ?>,
    <?= substr($curso['hora_inicio'], 0, 5) ?> - <?= substr($curso['hora_fin'], 0, 5) ?> ·
    Aula <?= htmlspecialchars($curso['aula']) ?>
</p>

<div class="card card-soft p-3">
    <div class="table-responsive table-rounded">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Teléfono</th>
                <th>Nivel actual</th>
                <th>Estado</th>
                <th>Fecha matrícula</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($resEst->num_rows > 0): ?>
                <?php while ($row = $resEst->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre'] . " " . $row['apellido']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['telefono']) ?></td>
                        <td><?= htmlspecialchars($row['nivel_actual']) ?></td>
                        <td><?= htmlspecialchars($row['nombre_estado']) ?></td>
                        <td><?= htmlspecialchars($row['fecha_matricula']) ?></td>
                        <td>
                            <a href="estudiante_perfil.php?matricula_id=<?= (int)$row['matricula_id'] ?>"
                               class="btn btn-sm btn-outline-secondary">
                                Ver perfil
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-muted">No hay estudiantes matriculados en este horario.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<a href="cursos.php" class="btn btn-link px-0 mt-3">‹ Volver a mis cursos</a>

<?php include __DIR__ . "/../includes/footer.php"; ?>
