<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // solo estudiantes

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

if ($horario_id <= 0) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">Horario no válido.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// 1) Verificar que el estudiante esté matriculado en ese horario
$check = $mysqli->prepare("
    SELECT m.id AS matricula_id, em.nombre_estado
    FROM matriculas m
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.estudiante_id = ? AND m.horario_id = ?
    LIMIT 1
");
$check->bind_param("ii", $usuario_id, $horario_id);
$check->execute();
$resCheck = $check->get_result();
$matricula = $resCheck->fetch_assoc();
$check->close();

if (!$matricula) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No estás matriculado en este curso.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// 2) Datos del curso, horario y docente
$infoSql = "
    SELECT
        h.id AS horario_id,
        c.nombre_curso,
        c.descripcion,
        n.codigo_nivel,
        n.nombre_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula,
        u.nombre AS docente_nombre,
        u.apellido AS docente_apellido,
        u.email AS docente_email,
        u.foto_perfil,
        ip.pais,
        ip.ciudad
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN niveles_academicos n ON c.nivel_id = n.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    INNER JOIN docentes dc ON h.docente_id = dc.id
    INNER JOIN usuarios u ON dc.id = u.id
    LEFT JOIN informacion_personal ip ON ip.usuario_id = u.id
    WHERE h.id = ?
    LIMIT 1
";
$stmtInfo = $mysqli->prepare($infoSql);
$stmtInfo->bind_param("i", $horario_id);
$stmtInfo->execute();
$resInfo = $stmtInfo->get_result();
$curso = $resInfo->fetch_assoc();
$stmtInfo->close();

if (!$curso) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No se encontró la información del curso.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// 3) Compañeros de clase (matrículas activas)
$companerosSql = "
    SELECT 
        u.nombre,
        u.apellido,
        u.email
    FROM matriculas m
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u ON e.id = u.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.horario_id = ? AND em.nombre_estado = 'Activa'
    ORDER BY u.nombre, u.apellido
";
$stmtComp = $mysqli->prepare($companerosSql);
$stmtComp->bind_param("i", $horario_id);
$stmtComp->execute();
$companeros = $stmtComp->get_result();
$stmtComp->close();

// 4) Materiales del curso
$matSql = "
    SELECT 
        m.titulo,
        m.descripcion,
        m.archivo_url,
        ta.tipo_archivo,
        m.fecha_subida
    FROM materiales m
    INNER JOIN tipos_archivo ta ON m.tipo_archivo_id = ta.id
    WHERE m.horario_id = ? AND m.activo = 1
    ORDER BY m.fecha_subida DESC
";
$stmtMat = $mysqli->prepare($matSql);
$stmtMat->bind_param("i", $horario_id);
$stmtMat->execute();
$materiales = $stmtMat->get_result();
$stmtMat->close();

// 5) Anuncios específicos de este curso
$anSql = "
    SELECT 
        a.titulo,
        a.contenido,
        a.fecha_publicacion,
        a.importante,
        ta.tipo_anuncio
    FROM anuncios a
    INNER JOIN tipos_anuncio ta ON a.tipo_anuncio_id = ta.id
    WHERE a.horario_id = ?
      AND (a.fecha_expiracion IS NULL OR a.fecha_expiracion >= CURDATE())
    ORDER BY a.importante DESC, a.fecha_publicacion DESC
";
$stmtAn = $mysqli->prepare($anSql);
$stmtAn->bind_param("i", $horario_id);
$stmtAn->execute();
$anuncios = $stmtAn->get_result();
$stmtAn->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">
    Detalle del curso: <?= htmlspecialchars($curso['nombre_curso']) ?>
</h1>
<p class="text-muted mb-3">
    Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?> · 
    <?= htmlspecialchars($curso['nombre_nivel']) ?> ·
    <?= htmlspecialchars($curso['nombre_dia']) ?>,
    <?= substr($curso['hora_inicio'], 0, 5) ?> - <?= substr($curso['hora_fin'], 0, 5) ?> ·
    Aula <?= htmlspecialchars($curso['aula']) ?>
</p>

<div class="row g-3 mb-4">
    <!-- Columna izquierda: info curso, anuncios, materiales -->
    <div class="col-lg-8">
        <div class="card card-soft mb-3 p-3">
            <h2 class="h6 fw-bold mb-2">Descripción del curso</h2>
            <p class="mb-0">
                <?= nl2br(htmlspecialchars($curso['descripcion'] ?: 'Sin descripción registrada.')) ?>
            </p>
        </div>

        <div class="card card-soft mb-3 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Anuncios de este curso</h2>
            </div>

            <?php if ($anuncios->num_rows === 0): ?>
                <p class="small text-muted mb-0">Aún no hay anuncios específicos para este curso.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php while ($a = $anuncios->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <?php if ($a['importante']): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                Importante
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge bg-light text-muted border">
                                            <?= htmlspecialchars($a['tipo_anuncio']) ?>
                                        </span>
                                    </div>
                                    <strong class="d-block small mb-1">
                                        <?= htmlspecialchars($a['titulo']) ?>
                                    </strong>
                                    <p class="mb-0 small">
                                        <?= nl2br(htmlspecialchars($a['contenido'])) ?>
                                    </p>
                                </div>
                                <small class="text-muted ms-3">
                                    <?= date('d/m/Y H:i', strtotime($a['fecha_publicacion'])) ?>
                                </small>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Materiales del curso</h2>
            </div>

            <?php if ($materiales->num_rows === 0): ?>
                <p class="small text-muted mb-0">Aún no hay materiales subidos para este curso.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php while ($m = $materiales->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-light text-muted border small mb-1">
                                        <?= htmlspecialchars($m['tipo_archivo']) ?>
                                    </span>
                                    <strong class="d-block">
                                        <a href="<?= htmlspecialchars($m['archivo_url']) ?>" target="_blank">
                                            <?= htmlspecialchars($m['titulo']) ?>
                                        </a>
                                    </strong>
                                    <?php if (!empty($m['descripcion'])): ?>
                                        <p class="small mb-1">
                                            <?= nl2br(htmlspecialchars($m['descripcion'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted ms-3">
                                    <?= date('d/m/Y', strtotime($m['fecha_subida'])) ?>
                                </small>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Columna derecha: docente y compañeros -->
    <div class="col-lg-4">
        <div class="card card-soft mb-3 p-3">
            <h2 class="h6 fw-bold mb-2">Docente del curso</h2>
            <div class="d-flex align-items-center">
                <?php if (!empty($curso['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($curso['foto_perfil']) ?>"
                         alt="Foto docente"
                         class="rounded-circle me-2"
                         style="width:48px;height:48px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center me-2"
                         style="width:48px;height:48px;">
                        <span class="fw-bold">
                            <?= strtoupper(substr($curso['docente_nombre'],0,1) . substr($curso['docente_apellido'],0,1)) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($curso['docente_nombre'] . " " . $curso['docente_apellido']) ?>
                    </div>
                    <div class="small text-muted">
                        <?= htmlspecialchars($curso['docente_email']) ?>
                    </div>
                    <?php if ($curso['pais'] || $curso['ciudad']): ?>
                        <div class="small text-muted">
                            <?= htmlspecialchars(trim($curso['ciudad'] . ", " . $curso['pais']), ", ") ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-2">Compañeros de clase</h2>
            <?php if ($companeros->num_rows === 0): ?>
                <p class="small text-muted mb-0">Aún no hay otros estudiantes activos en esta clase.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 small">
                    <?php while ($c = $companeros->fetch_assoc()): ?>
                        <li class="mb-1">
                            <strong><?= htmlspecialchars($c['nombre'] . " " . $c['apellido']) ?></strong><br>
                            <span class="text-muted"><?= htmlspecialchars($c['email']) ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<a href="dashboard.php" class="btn btn-link px-0">
    ‹ Volver a mis cursos
</a>

<?php include __DIR__ . "/../includes/footer.php"; ?>
