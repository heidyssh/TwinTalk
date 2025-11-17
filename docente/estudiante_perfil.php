<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([2]); // docente

$docente_id   = $_SESSION['usuario_id'] ?? 0;
$matricula_id = isset($_GET['matricula_id']) ? (int)$_GET['matricula_id'] : 0;

if ($matricula_id <= 0) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">Matrícula no válida.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// 1) Traer info de la matrícula y verificar que el horario es del docente
$sqlMat = "
    SELECT
        m.id AS matricula_id,
        m.estudiante_id,
        m.horario_id,
        m.fecha_matricula,
        em.nombre_estado,
        c.nombre_curso,
        n.codigo_nivel,
        n.nombre_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula
    FROM matriculas m
    INNER JOIN horarios h ON m.horario_id = h.id
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN niveles_academicos n ON c.nivel_id = n.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.id = ? AND h.docente_id = ?
    LIMIT 1
";
$stmtMat = $mysqli->prepare($sqlMat);
$stmtMat->bind_param("ii", $matricula_id, $docente_id);
$stmtMat->execute();
$resMat = $stmtMat->get_result();
$matricula = $resMat->fetch_assoc();
$stmtMat->close();

if (!$matricula) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No tienes permiso para ver esta matrícula.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

$estudiante_id = (int)$matricula['estudiante_id'];

// 2) Datos del estudiante (usuarios + estudiantes + informacion_personal + tipos_documento)
$sqlEst = "
    SELECT
        u.nombre,
        u.apellido,
        u.email,
        u.telefono,
        u.foto_perfil,
        e.nivel_actual,
        e.fecha_inscripcion,
        td.tipo_documento,
        ip.numero_documento,
        ip.fecha_nacimiento,
        ip.direccion,
        ip.ciudad,
        ip.pais
    FROM estudiantes e
    INNER JOIN usuarios u ON e.id = u.id
    LEFT JOIN informacion_personal ip ON ip.usuario_id = u.id
    LEFT JOIN tipos_documento td ON ip.tipo_documento_id = td.id
    WHERE e.id = ?
    LIMIT 1
";
$stmtEst = $mysqli->prepare($sqlEst);
$stmtEst->bind_param("i", $estudiante_id);
$stmtEst->execute();
$resEst = $stmtEst->get_result();
$est = $resEst->fetch_assoc();
$stmtEst->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">
    Perfil de estudiante
</h1>
<p class="text-muted mb-3">
    Información detallada del estudiante en este curso.
</p>

<div class="row g-3">
    <!-- Columna izquierda: datos del estudiante -->
    <div class="col-lg-5">
        <div class="card card-soft p-3">
            <div class="d-flex align-items-center mb-3">
                <?php if (!empty($est['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($est['foto_perfil']) ?>"
                         alt="Foto estudiante"
                         class="rounded-circle me-3"
                         style="width:64px;height:64px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center me-3"
                         style="width:64px;height:64px;">
                        <span class="fw-bold">
                            <?= strtoupper(substr($est['nombre'],0,1) . substr($est['apellido'],0,1)) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($est['nombre'] . " " . $est['apellido']) ?>
                    </div>
                    <div class="small text-muted">
                        <?= htmlspecialchars($est['email']) ?>
                    </div>
                    <div class="small text-muted">
                        <?= htmlspecialchars($est['telefono']) ?>
                    </div>
                </div>
            </div>

            <h2 class="h6 fw-bold mb-2">Información académica</h2>
            <p class="small mb-1">
                <strong>Nivel actual:</strong>
                <?= htmlspecialchars($est['nivel_actual']) ?>
            </p>
            <p class="small mb-1">
                <strong>Fecha de inscripción:</strong>
                <?= htmlspecialchars($est['fecha_inscripcion']) ?>
            </p>

            <h2 class="h6 fw-bold mt-3 mb-2">Datos adicionales</h2>
            <p class="small mb-1">
                <strong>Tipo de documento:</strong>
                <?= htmlspecialchars($est['tipo_documento'] ?: 'No registrado') ?>
            </p>
            <p class="small mb-1">
                <strong>Número de documento:</strong>
                <?= htmlspecialchars($est['numero_documento'] ?: 'No registrado') ?>
            </p>
            <p class="small mb-1">
                <strong>Fecha de nacimiento:</strong>
                <?= htmlspecialchars($est['fecha_nacimiento'] ?: 'No registrada') ?>
            </p>
            <p class="small mb-1">
                <strong>Dirección:</strong>
                <?= htmlspecialchars($est['direccion'] ?: 'No registrada') ?>
            </p>
            <p class="small mb-1">
                <strong>Ciudad:</strong>
                <?= htmlspecialchars($est['ciudad'] ?: 'No registrada') ?>
            </p>
            <p class="small mb-1">
                <strong>País:</strong>
                <?= htmlspecialchars($est['pais'] ?: 'No registrado') ?>
            </p>
        </div>
    </div>

    <!-- Columna derecha: info del curso / matrícula -->
    <div class="col-lg-7">
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">Curso y horario</h2>
            <p class="small mb-1">
                <strong>Curso:</strong>
                <?= htmlspecialchars($matricula['nombre_curso']) ?>
            </p>
            <p class="small mb-1">
                <strong>Nivel:</strong>
                <?= htmlspecialchars($matricula['codigo_nivel']) ?> ·
                <?= htmlspecialchars($matricula['nombre_nivel']) ?>
            </p>
            <p class="small mb-1">
                <strong>Día:</strong>
                <?= htmlspecialchars($matricula['nombre_dia']) ?>
            </p>
            <p class="small mb-1">
                <strong>Hora:</strong>
                <?= substr($matricula['hora_inicio'], 0, 5) ?> - <?= substr($matricula['hora_fin'], 0, 5) ?>
            </p>
            <p class="small mb-1">
                <strong>Aula:</strong>
                <?= htmlspecialchars($matricula['aula']) ?>
            </p>
            <p class="small mb-1">
                <strong>Estado de matrícula:</strong>
                <?= htmlspecialchars($matricula['nombre_estado']) ?>
            </p>
            <p class="small mb-0">
                <strong>Fecha de matrícula:</strong>
                <?= htmlspecialchars($matricula['fecha_matricula']) ?>
            </p>
        </div>

        <div class="alert alert-info small mb-0">
            Si deseas ver o registrar calificaciones y asistencia de este estudiante,
            utiliza las secciones <strong>Calificaciones</strong> y <strong>Asistencia</strong> del menú de docente.
        </div>
    </div>
</div>

<a href="estudiantes.php?horario_id=<?= (int)$matricula['horario_id'] ?>" class="btn btn-link px-0 mt-3">
    ‹ Volver a la lista de estudiantes
</a>

<?php include __DIR__ . "/../includes/footer.php"; ?>
