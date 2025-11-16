<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); // solo admin

$mensaje = "";
$error = "";

// --- Crear / actualizar curso ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_curso     = trim($_POST['nombre_curso'] ?? '');
    $nivel_id         = (int)($_POST['nivel_id'] ?? 0);
    $duracion_horas   = (int)($_POST['duracion_horas'] ?? 0);
    $capacidad_maxima = (int)($_POST['capacidad_maxima'] ?? 0);
    $descripcion      = trim($_POST['descripcion'] ?? '');
    $curso_id         = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;

    if ($nombre_curso === '' || $nivel_id <= 0 || $duracion_horas <= 0 || $capacidad_maxima <= 0) {
        $error = "Completa todos los campos obligatorios.";
    } else {
        if ($curso_id > 0) {
            // actualizar curso
            $stmt = $mysqli->prepare("UPDATE cursos 
                                      SET nombre_curso = ?, nivel_id = ?, duracion_horas = ?, capacidad_maxima = ?, descripcion = ?
                                      WHERE id = ?");
            $stmt->bind_param("siiisi", $nombre_curso, $nivel_id, $duracion_horas, $capacidad_maxima, $descripcion, $curso_id);
            if ($stmt->execute()) {
                $mensaje = "Curso actualizado correctamente.";
            } else {
                $error = "Error al actualizar el curso.";
            }
        } else {
            // crear curso
            $stmt = $mysqli->prepare("INSERT INTO cursos (nombre_curso, nivel_id, duracion_horas, capacidad_maxima, descripcion, activo)
                                      VALUES (?,?,?,?,?,1)");
            $stmt->bind_param("siiis", $nombre_curso, $nivel_id, $duracion_horas, $capacidad_maxima, $descripcion);
            if ($stmt->execute()) {
                $mensaje = "Curso creado correctamente.";
            } else {
                $error = "Error al crear el curso.";
            }
        }
    }
}

// --- eliminar (desactivar) curso ---
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($id > 0) {
        $stmt = $mysqli->prepare("UPDATE cursos SET activo = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $mensaje = "Curso desactivado correctamente.";
        } else {
            $error = "No se pudo desactivar el curso.";
        }
    }
}

// --- cargar datos para edición ---
$curso_editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    if ($id > 0) {
        $stmt = $mysqli->prepare("SELECT * FROM cursos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $curso_editar = $res->fetch_assoc();
        }
    }
}

// --- niveles para el select ---
$niveles_data = [];
$res_niveles = $mysqli->query("SELECT id, codigo_nivel, nombre_nivel FROM niveles_academicos ORDER BY id");
if ($res_niveles) {
    while ($n = $res_niveles->fetch_assoc()) {
        $niveles_data[] = $n;
    }
}

// --- listado de cursos ---
$cursos = $mysqli->query("
    SELECT c.*, n.codigo_nivel, n.nombre_nivel
    FROM cursos c
    JOIN niveles_academicos n ON c.nivel_id = n.id
    ORDER BY c.fecha_creacion DESC
");

include __DIR__ . "/../includes/header.php";
?>

<div class="row mt-3">
    <div class="col-12 mb-3 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 fw-bold mb-0">Gestión de cursos</h1>
            <p class="text-muted small mb-0">Crea, edita y desactiva los cursos de la academia.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">← Volver al panel</a>
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
            <h2 class="h6 fw-bold mb-2">
                <?= $curso_editar ? 'Editar curso' : 'Nuevo curso'; ?>
            </h2>
            <form method="post">
                <?php if ($curso_editar): ?>
                    <input type="hidden" name="curso_id" value="<?= (int)$curso_editar['id'] ?>">
                <?php endif; ?>

                <div class="mb-2">
                    <label class="form-label">Nombre del curso *</label>
                    <input type="text" class="form-control" name="nombre_curso"
                           value="<?= htmlspecialchars($curso_editar['nombre_curso'] ?? '') ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Nivel académico *</label>
                    <select name="nivel_id" class="form-select" required>
                        <option value="">Seleccione un nivel</option>
                        <?php foreach ($niveles_data as $nivel): ?>
                            <option value="<?= (int)$nivel['id'] ?>"
                                <?= isset($curso_editar['nivel_id']) && $curso_editar['nivel_id'] == $nivel['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nivel['codigo_nivel'] . ' - ' . $nivel['nombre_nivel']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Duración en horas *</label>
                    <input type="number" class="form-control" name="duracion_horas" min="1"
                           value="<?= htmlspecialchars($curso_editar['duracion_horas'] ?? '') ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Capacidad máxima *</label>
                    <input type="number" class="form-control" name="capacidad_maxima" min="1"
                           value="<?= htmlspecialchars($curso_editar['capacidad_maxima'] ?? '') ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars($curso_editar['descripcion'] ?? '') ?></textarea>
                </div>

                <button class="btn btn-tt-primary" type="submit">
                    <?= $curso_editar ? 'Guardar cambios' : 'Crear curso'; ?>
                </button>
                <?php if ($curso_editar): ?>
                    <a href="cursos.php" class="btn btn-link btn-sm">Cancelar edición</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Listado de cursos</h2>
                <span class="small text-muted">Desde aquí puedes gestionar también los horarios.</span>
            </div>
            <div class="table-responsive table-rounded">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Curso</th>
                        <th>Nivel</th>
                        <th>Duración</th>
                        <th>Capacidad</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($cursos && $cursos->num_rows > 0): ?>
                        <?php while ($c = $cursos->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['nombre_curso']) ?></td>
                                <td><?= htmlspecialchars($c['codigo_nivel']) ?></td>
                                <td><?= (int)$c['duracion_horas'] ?> h</td>
                                <td><?= (int)$c['capacidad_maxima'] ?></td>
                                <td><?= $c['activo'] ? 'Sí' : 'No' ?></td>
                                <td>
                                    <a href="cursos.php?editar=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                                    <a href="horarios.php?curso_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">Horarios</a>
                                    <?php if ($c['activo']): ?>
                                        <a href="cursos.php?eliminar=<?= (int)$c['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('¿Desactivar este curso?');">
                                            Desactivar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-muted">Aún no hay cursos creados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
