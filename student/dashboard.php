<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // solo estudiantes

$usuario_id = $_SESSION['usuario_id'];

// Cursos donde YA está matriculado
$mis_cursos = $mysqli->prepare("
    SELECT m.id AS matricula_id, c.nombre_curso, n.codigo_nivel,
           h.hora_inicio, h.hora_fin, d.nombre_dia,
           u.nombre AS docente_nombre, u.apellido AS docente_apellido
    FROM matriculas m
    JOIN horarios h ON m.horario_id = h.id
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    JOIN docentes dc ON h.docente_id = dc.id
    JOIN usuarios u ON dc.id = u.id
    WHERE m.estudiante_id = ?
    ORDER BY h.fecha_inicio DESC
");
$mis_cursos->bind_param("i", $usuario_id);
$mis_cursos->execute();
$res_mis_cursos = $mis_cursos->get_result();

// Algunos horarios disponibles (no matriculado)
$disponibles = $mysqli->prepare("
    SELECT h.id AS horario_id, c.nombre_curso, n.codigo_nivel,
           d.nombre_dia, h.hora_inicio, h.hora_fin, h.cupos_disponibles
    FROM horarios h
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.activo = 1
      AND h.cupos_disponibles > 0
      AND NOT EXISTS (
        SELECT 1 FROM matriculas m
        WHERE m.horario_id = h.id AND m.estudiante_id = ?
      )
    ORDER BY h.fecha_inicio ASC
    LIMIT 5
");
$disponibles->bind_param("i", $usuario_id);
$disponibles->execute();
$res_disponibles = $disponibles->get_result();

include __DIR__ . "/../includes/header.php";
?>

<div class="row mt-3">
    <div class="col-12 mb-3">
        <h1 class="h4 fw-bold">
            Hola, <?= htmlspecialchars($_SESSION['nombre']) ?> 👋
        </h1>
        <p class="text-muted mb-0">Bienvenido a tu dashboard de estudiante.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Mis clases actuales</h2>
                <a href="mis_matriculas.php" class="small">Ver historial ›</a>
            </div>
            <?php if ($res_mis_cursos->num_rows > 0): ?>
                <div class="table-responsive table-rounded">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Curso</th>
                            <th>Docente</th>
                            <th>Día</th>
                            <th>Hora</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $res_mis_cursos->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['nombre_curso']) ?></strong><br>
                                    <span class="badge-level">Nivel <?= htmlspecialchars($row['codigo_nivel']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['docente_nombre'] . " " . $row['docente_apellido']) ?></td>
                                <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                                <td><?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Aún no estás matriculado en ningún curso.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">Cursos disponibles para matricular</h2>
            <?php if ($res_disponibles->num_rows > 0): ?>
                <?php while ($row = $res_disponibles->fetch_assoc()): ?>
                    <div class="border rounded-3 p-2 mb-2 bg-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?= htmlspecialchars($row['nombre_curso']) ?></strong><br>
                                <span class="badge-level">Nivel <?= htmlspecialchars($row['codigo_nivel']) ?></span>
                                <span class="small d-block text-muted">
                                    <?= htmlspecialchars($row['nombre_dia']) ?> ·
                                    <?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?>
                                </span>
                            </div>
                            <div class="text-end">
                                <span class="small text-muted d-block mb-1">
                                    Cupos: <?= (int)$row['cupos_disponibles'] ?>
                                </span>
                                <a href="cursos_disponibles.php?matricular=<?= (int)$row['horario_id'] ?>"
                                   class="btn btn-sm btn-tt-primary">
                                    Matricular
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted mb-0">No hay cursos disponibles para ti en este momento.</p>
            <?php endif; ?>
        </div>

        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-2">Configuración rápida</h2>
            <p class="small text-muted mb-2">
                Actualiza tus datos personales y cambia tu contraseña.
            </p>
            <a href="perfil.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                Ir a mi perfil
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
