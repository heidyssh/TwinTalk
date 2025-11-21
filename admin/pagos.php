<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([1]); // solo admin

$mensaje = "";
$error   = "";

// -----------------------------------------------------
// Helper: obtener id de un estado por nombre
// -----------------------------------------------------
function obtenerEstadoIdPorNombre($mysqli, $nombre) {
    $stmt = $mysqli->prepare("SELECT id FROM estados_matricula WHERE nombre_estado = ? LIMIT 1");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? (int)$res['id'] : null;
}

// -----------------------------------------------------
// Helper: obtener precio vigente del curso
// -----------------------------------------------------
function obtenerPrecioCursoActual($mysqli, $curso_id) {
    $stmt = $mysqli->prepare("
        SELECT precio
        FROM precios_cursos
        WHERE curso_id = ?
          AND activo = 1
          AND fecha_inicio_vigencia <= CURDATE()
          AND (fecha_fin_vigencia IS NULL OR fecha_fin_vigencia >= CURDATE())
        ORDER BY fecha_inicio_vigencia DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $curso_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? (float)$res['precio'] : null;
}

// -----------------------------------------------------
// Helper: enviar correo de confirmación de pago
// (Versión sencilla con mail(); si usas PHPMailer, aquí lo cambias)
// -----------------------------------------------------
function enviarCorreoConfirmacionPago($mysqli, $matricula_id) {
    // Obtener datos: alumno, curso, monto, método
    $sql = "
        SELECT 
            u.email,
            u.nombre,
            u.apellido,
            c.nombre_curso,
            n.codigo_nivel,
            m.monto_pagado,
            mp.nombre_metodo
        FROM matriculas m
        JOIN estudiantes e   ON m.estudiante_id = e.id
        JOIN usuarios u      ON e.id = u.id
        JOIN horarios h      ON m.horario_id = h.id
        JOIN cursos c        ON h.curso_id = c.id
        JOIN niveles_academicos n ON c.nivel_id = n.id
        LEFT JOIN metodos_pago mp ON m.metodo_pago_id = mp.id
        WHERE m.id = ?
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $matricula_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) return;

    $para    = $res['email'];
    $nombre  = $res['nombre'] . " " . $res['apellido'];
    $curso   = $res['nombre_curso'] . " (" . $res['codigo_nivel'] . ")";
    $monto   = number_format($res['monto_pagado'], 2);
    $metodo  = $res['nombre_metodo'] ?: 'Método de pago';

    $asunto  = "Confirmación de pago de matrícula - TwinTalk English";
    $mensaje = "Hola {$nombre},\n\n"
             . "Hemos registrado tu pago de matrícula para el curso: {$curso}.\n"
             . "Monto pagado: L {$monto}\n"
             . "Método de pago: {$metodo}\n\n"
             . "¡Gracias por estar al día con tu pago!\n"
             . "TwinTalk English";

    // Versión básica (requiere que mail() esté bien configurado)
    @mail($para, $asunto, $mensaje, "From: twintalk39@gmail.com\r\n");
}

// -----------------------------------------------------
// Registrar pago (POST)
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $matricula_id   = (int)($_POST['matricula_id'] ?? 0);
    $metodo_pago_id = (int)($_POST['metodo_pago_id'] ?? 0);
    $monto_pagado   = (float)($_POST['monto_pagado'] ?? 0);

    if ($matricula_id <= 0 || $metodo_pago_id <= 0 || $monto_pagado <= 0) {
        $error = "Completa todos los datos del pago.";
    } else {
        $estado_activa = obtenerEstadoIdPorNombre($mysqli, 'Activa');
        if (!$estado_activa) {
            $error = "No se encontró el estado 'Activa' en la tabla estados_matricula.";
        } else {
            // Definir fecha de vencimiento (por ejemplo, 30 días después del pago)
            $fecha_vencimiento = date('Y-m-d', strtotime('+30 days'));

            $stmt = $mysqli->prepare("
                UPDATE matriculas
                SET metodo_pago_id = ?, 
                    monto_pagado   = ?, 
                    fecha_vencimiento = ?,
                    estado_id = ?
                WHERE id = ?
            ");
            $stmt->bind_param("idsii", $metodo_pago_id, $monto_pagado, $fecha_vencimiento, $estado_activa, $matricula_id);
            if ($stmt->execute()) {
                $mensaje = "Pago registrado correctamente.";
                enviarCorreoConfirmacionPago($mysqli, $matricula_id);
            } else {
                $error = "No se pudo registrar el pago.";
            }
            $stmt->close();
        }
    }
}

// -----------------------------------------------------
// Listar matrículas pendientes de pago
// -----------------------------------------------------
$sqlPend = "
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email,
        c.nombre_curso,
        n.codigo_nivel,
        h.hora_inicio,
        h.hora_fin,
        m.fecha_matricula,
        m.monto_pagado,
        em.nombre_estado,
        c.id AS curso_id
    FROM matriculas m
    JOIN estudiantes e        ON m.estudiante_id = e.id
    JOIN usuarios u           ON e.id = u.id
    JOIN horarios h           ON m.horario_id = h.id
    JOIN cursos c             ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN estados_matricula em ON m.estado_id = em.id
    WHERE 
        em.nombre_estado = 'Pendiente'
        OR (
            em.nombre_estado = 'Activa'
            AND (m.metodo_pago_id IS NULL OR m.monto_pagado IS NULL OR m.monto_pagado = 0)
        )
    ORDER BY m.fecha_matricula DESC
";

$pendientes = $mysqli->query($sqlPend);

// Métodos de pago (bancos)
$metodos = $mysqli->query("SELECT id, nombre_metodo FROM metodos_pago ORDER BY nombre_metodo ASC");

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Gestión de pagos de matrícula</h1>

<?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card card-soft mt-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Matrículas pendientes de pago</h2>

        <?php if ($pendientes && $pendientes->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table align-middle table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Alumno</th>
                            <th>Curso</th>
                            <th>Horario</th>
                            <th>Fecha matrícula</th>
                            <th>Monto sugerido</th>
                            <th>Registrar pago</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($m = $pendientes->fetch_assoc()): ?>
                        <?php
                            $precio_sugerido = obtenerPrecioCursoActual($mysqli, (int)$m['curso_id']);
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($m['email']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($m['nombre_curso']) ?>
                                <span class="badge bg-soft-primary ms-1">
                                    <?= htmlspecialchars($m['codigo_nivel']) ?>
                                </span>
                            </td>
                            <td><?= substr($m['hora_inicio'],0,5) ?> - <?= substr($m['hora_fin'],0,5) ?></td>
                            <td><?= htmlspecialchars($m['fecha_matricula']) ?></td>
                            <td>
                                <?php if ($precio_sugerido !== null): ?>
                                    L <?= number_format($precio_sugerido, 2) ?>
                                <?php else: ?>
                                    <span class="text-muted small">Sin precio configurado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="row g-1 align-items-center">
                                    <input type="hidden" name="matricula_id" value="<?= (int)$m['matricula_id'] ?>">
                                    <div class="col-5">
                                        <select name="metodo_pago_id" class="form-select form-select-sm" required>
                                            <option value="">Banco...</option>
                                            <?php if ($metodos && $metodos->num_rows > 0): ?>
                                                <?php mysqli_data_seek($metodos, 0); ?>
                                                <?php while ($mp = $metodos->fetch_assoc()): ?>
                                                    <option value="<?= (int)$mp['id'] ?>">
                                                        <?= htmlspecialchars($mp['nombre_metodo']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <input type="number" name="monto_pagado" step="0.01" min="0" class="form-control form-control-sm"
                                               value="<?= $precio_sugerido !== null ? htmlspecialchars($precio_sugerido) : '' ?>"
                                               placeholder="Monto">
                                    </div>
                                    <div class="col-3 d-grid">
                                        <button class="btn btn-sm btn-tt-primary" name="registrar_pago">
                                            OK
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0">No hay matrículas pendientes de pago.</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
