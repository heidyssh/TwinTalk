<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]);

$mensaje = "";
$error = "";

// Leer niveles en un array para evitar problemas con data_seek
$niveles_data = [];
$res_niveles = $mysqli->query("SELECT id, codigo_nivel, nombre_nivel FROM niveles_academicos ORDER BY id");
if ($res_niveles) {
    while ($n = $res_niveles->fetch_assoc()) {
        $niveles_data[] = $n;
    }
}

// Crear curso rápido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_curso'])) {
    $nombre = trim($_POST['nombre_curso'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nivel_id = (int)($_POST['nivel_id'] ?? 0);
    $duracion = (int)($_POST['duracion_horas'] ?? 0);
    $capacidad = (int)($_POST['capacidad_maxima'] ?? 0);

    if ($nombre && $nivel_id && $duracion > 0 && $capacidad > 0) {
        $stmt = $mysqli->prepare("
            INSERT INTO cursos (nombre_curso, descripcion, nivel_id, duracion_horas, capacidad_maxima, activo)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("ssiii", $nombre, $descripcion, $nivel_id, $duracion, $capacidad);
        if ($stmt->execute()) {
            $mensaje = "Curso creado correctamente.";
        } else {
            $error = "Error al crear curso: " . $mysqli->error;
        }
    } else {
        $error = "Debes rellenar todos los campos obligatorios.";
    }
}

$cursos = $mysqli->query("
    SELECT c.id, c.nombre_curso, n.codigo_nivel, c.capacidad_maxima, c.activo
    FROM cursos c
    JOIN niveles_academicos n ON c.nivel_id = n.id
    ORDER BY c.fecha_creacion DESC
");

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Gestión de cursos</h1>

<?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3 mt-2">
    <div class="col-lg-5">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6 fw-bold mb-2">Crear nuevo curso</h2>

            <?php if (empty($niveles_data)): ?>
                <div class="alert alert-warning small">
                    No hay niveles académicos registrados en la tabla <strong>niveles_academicos</strong>.
                    Debes insertarlos desde phpMyAdmin (por ejemplo: A1, A2, B1, etc.) para poder asociar cursos.
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-2">
                    <label class="form-label">Nombre del curso *</label>
                    <input class="form-control" name="nombre_curso" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nivel académico *</label>
                    <select class="form-select" name="nivel_id" required <?= empty($niveles_data) ? 'disabled' : '' ?>>
                        <option value="">Selecciona nivel</option>
                        <?php foreach ($niveles_data as $n): ?>
                            <option value="<?= (int)$n['id'] ?>">
                                <?= htmlspecialchars($n['codigo_nivel'] . " - " . $n['nombre_nivel']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Duración en horas *</label>
                    <input type="number" class="form-control" name="duracion_horas" min="1" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Capacidad máxima *</label>
                    <input type="number" class="form-control" name="capacidad_maxima" min="1" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"></textarea>
                </div>
                <button class="btn btn-tt-primary" name="crear_curso" <?= empty($niveles_data) ? 'disabled' : '' ?>>
                    Crear curso
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6 fw-bold mb-2">Listado de cursos</h2>
            <div class="table-responsive table-rounded">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Curso</th>
                        <th>Nivel</th>
                        <th>Capacidad</th>
                        <th>Activo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($cursos && $cursos->num_rows > 0): ?>
                        <?php while ($c = $cursos->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['nombre_curso']) ?></td>
                                <td><?= htmlspecialchars($c['codigo_nivel']) ?></td>
                                <td><?= (int)$c['capacidad_maxima'] ?></td>
                                <td><?= $c['activo'] ? 'Sí' : 'No' ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-muted">Aún no hay cursos creados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
