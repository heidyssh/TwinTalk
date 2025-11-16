<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); // solo admin

$mensaje = "";
$error = "";

// Curso filtrado opcional
$curso_id_filtro = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

// Cursos activos
$cursos_data = [];
$res_cursos = $mysqli->query("SELECT id, nombre_curso FROM cursos WHERE activo = 1 ORDER BY nombre_curso");
if ($res_cursos) {
    while ($c = $res_cursos->fetch_assoc()) {
        $cursos_data[] = $c;
    }
}

// Docentes activos
$docentes_data = [];
$res_docentes = $mysqli->query("
    SELECT d.id, u.nombre, u.apellido
    FROM docentes d
    JOIN usuarios u ON d.id = u.id
    WHERE d.activo = 1
    ORDER BY u.nombre, u.apellido
");
if ($res_docentes) {
    while ($d = $res_docentes->fetch_assoc()) {
        $docentes_data[] = $d;
    }
}

// Días de la semana
$dias_data = [];
$res_dias = $mysqli->query("SELECT id, nombre_dia FROM dias_semana ORDER BY numero_dia");
if ($res_dias) {
    while ($d = $res_dias->fetch_assoc()) {
        $dias_data[] = $d;
    }
}

// Crear horario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curso_id        = (int)($_POST['curso_id'] ?? 0);
    $docente_id      = (int)($_POST['docente_id'] ?? 0);
    $dia_semana_id   = (int)($_POST['dia_semana_id'] ?? 0);
    $hora_inicio     = $_POST['hora_inicio'] ?? '';
    $hora_fin        = $_POST['hora_fin'] ?? '';
    $aula            = trim($_POST['aula'] ?? '');
    $fecha_inicio    = $_POST['fecha_inicio'] ?? '';
    $fecha_fin       = $_POST['fecha_fin'] ?? '';
    $cupos           = (int)($_POST['cupos_disponibles'] ?? 0);

    if ($curso_id <= 0 || $docente_id <= 0 || $dia_semana_id <= 0 ||
        $hora_inicio === '' || $hora_fin === '' || $fecha_inicio === '' ||
        $fecha_fin === '' || $cupos <= 0) {
        $error = "Completa todos los campos obligatorios del horario.";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO horarios 
            (curso_id, docente_id, dia_semana_id, hora_inicio, hora_fin, aula, fecha_inicio, fecha_fin, cupos_disponibles, activo)
            VALUES (?,?,?,?,?,?,?,?,?,1)");
        $stmt->bind_param("iiisssssi", $curso_id, $docente_id, $dia_semana_id, $hora_inicio, $hora_fin, $aula, $fecha_inicio, $fecha_fin, $cupos);
        if ($stmt->execute()) {
            $mensaje = "Horario creado correctamente.";
            if ($curso_id_filtro === 0) {
                $curso_id_filtro = $curso_id;
            }
        } else {
            $error = "Error al crear el horario.";
        }
    }
}

// Desactivar horario
if (isset($_GET['desactivar'])) {
    $id_h = (int)$_GET['desactivar'];
    if ($id_h > 0) {
        $stmt = $mysqli->prepare("UPDATE horarios SET activo = 0 WHERE id = ?");
        $stmt->bind_param("i", $id_h);
        if ($stmt->execute()) {
            $mensaje = "Horario desactivado correctamente.";
        } else {
            $error = "No se pudo desactivar el horario.";
        }
    }
}

// Listado de horarios
$query_horarios = "
    SELECT h.*, c.nombre_curso, d.nombre_dia, u.nombre AS docente_nombre, u.apellido AS docente_apellido
    FROM horarios h
    JOIN cursos c ON h.curso_id = c.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    JOIN docentes dc ON h.docente_id = dc.id
    JOIN usuarios u ON dc.id = u.id
";
$params = [];
$types  = "";

if ($curso_id_filtro > 0) {
    $query_horarios .= " WHERE h.curso_id = ? ";
    $types .= "i";
    $params[] = $curso_id_filtro;
}

$query_horarios .= " ORDER BY c.nombre_curso, h.hora_inicio";

$stmt_h = $mysqli->prepare($query_horarios);
if (!empty($params)) {
    $stmt_h->bind_param($types, ...$params);
}
$stmt_h->execute();
$res_horarios = $stmt_h->get_result();

include __DIR__ . "/../includes/header.php";
?>

<div class="row mt-3">
    <div class="col-12 mb-3 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 fw-bold mb-0">Gestión de horarios</h1>
            <p class="text-muted small mb-0">Asigna docentes, días y horarios a los cursos.</p>
        </div>
        <a href="cursos.php" class="btn btn-outline-secondary btn-sm">← Volver a cursos</a>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-2">Nuevo horario</h2>
            <form method="post">
                <div class="mb-2">
                    <label class="form-label">Curso *</label>
                    <select name="curso_id" class="form-select" required>
                        <option value="">Seleccione un curso</option>
                        <?php foreach ($cursos_data as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= $curso_id_filtro === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre_curso']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Docente *</label>
                    <select name="docente_id" class="form-select" required>
                        <option value="">Seleccione un docente</option>
                        <?php foreach ($docentes_data as $d): ?>
                            <option value="<?= (int)$d['id'] ?>">
                                <?= htmlspecialchars($d['nombre'] . " " . $d['apellido']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Día de la semana *</label>
                    <select name="dia_semana_id" class="form-select" required>
                        <option value="">Seleccione un día</option>
                        <?php foreach ($dias_data as $d): ?>
                            <option value="<?= (int)$d['id'] ?>">
                                <?= htmlspecialchars($d['nombre_dia']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Hora inicio *</label>
                        <input type="time" name="hora_inicio" class="form-control" required>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Hora fin *</label>
                        <input type="time" name="hora_fin" class="form-control" required>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Aula</label>
                    <input type="text" name="aula" class="form-control" placeholder="Ej: Aula 1">
                </div>

                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Fecha inicio *</label>
                        <input type="date" name="fecha_inicio" class="form-control" required>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Fecha fin *</label>
                        <input type="date" name="fecha_fin" class="form-control" required>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Cupos disponibles *</label>
                    <input type="number" name="cupos_disponibles" class="form-control" min="1" required>
                </div>

                <button class="btn btn-tt-primary" type="submit">Crear horario</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Horarios registrados</h2>
                <?php if ($curso_id_filtro > 0): ?>
                    <span class="small text-muted">Filtrando por curso ID <?= (int)$curso_id_filtro ?></span>
                <?php endif; ?>
            </div>

            <div class="table-responsive table-rounded">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Curso</th>
                        <th>Docente</th>
                        <th>Día</th>
                        <th>Hora</th>
                        <th>Cupos</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($res_horarios && $res_horarios->num_rows > 0): ?>
                        <?php while ($h = $res_horarios->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($h['nombre_curso']) ?></td>
                                <td><?= htmlspecialchars($h['docente_nombre'] . " " . $h['docente_apellido']) ?></td>
                                <td><?= htmlspecialchars($h['nombre_dia']) ?></td>
                                <td><?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?></td>
                                <td><?= (int)$h['cupos_disponibles'] ?></td>
                                <td><?= $h['activo'] ? 'Sí' : 'No' ?></td>
                                <td>
                                    <?php if ($h['activo']): ?>
                                        <a href="horarios.php?desactivar=<?= (int)$h['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('¿Desactivar este horario?');">
                                            Desactivar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-muted">Aún no hay horarios registrados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
