<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]);

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";
$error   = "";

// Obtener id de estado "Activa" para la matrícula
function obtenerEstadoActiva($mysqli) {
    $res = $mysqli->query("SELECT id FROM estados_matricula WHERE nombre_estado = 'Activa' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return null;
}

// Obtener el nivel máximo que el estudiante ha FINALIZADO (estado 'Finalizada')
function obtenerNivelMaximoFinalizado($mysqli, $estudiante_id) {
    $sql = "
        SELECT MAX(c.nivel_id) AS max_nivel
        FROM matriculas m
        INNER JOIN horarios h           ON m.horario_id = h.id
        INNER JOIN cursos c             ON h.curso_id = c.id
        INNER JOIN estados_matricula em ON m.estado_id = em.id
        WHERE m.estudiante_id = ?
          AND em.nombre_estado = 'Finalizada'
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $estudiante_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $res && $res['max_nivel'] !== null ? (int)$res['max_nivel'] : 0;
}

// Obtener el código (A1, A2, B1...) de un nivel por id
function obtenerCodigoNivel($mysqli, $nivel_id) {
    if ($nivel_id <= 0) return null;
    $stmt = $mysqli->prepare("SELECT codigo_nivel FROM niveles_academicos WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $nivel_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? $res['codigo_nivel'] : null;
}

// Obtener precio vigente para un curso
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
    $res = $stmt->get_result();
    $precio = null;
    if ($res && $row = $res->fetch_assoc()) {
        $precio = (float)$row['precio'];
    }
    $stmt->close();
    return $precio;
}

// Obtener un método de pago por defecto (el primero que exista)
function obtenerMetodoPagoDefecto($mysqli) {
    $res = $mysqli->query("SELECT id FROM metodos_pago ORDER BY id ASC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['id'];
    }
    return null;
}

// --------------------------------------------------------
// PROCESAR MATRICULACIÓN (backend protegido con requisito)
// --------------------------------------------------------
if (isset($_GET['matricular'])) {
    $horario_id = (int) $_GET['matricular'];

    $estado_activa_id = obtenerEstadoActiva($mysqli);
    if (!$estado_activa_id) {
        $error = "No se encontró el estado de matrícula 'Activa'. Pídale al admin que lo cree en la tabla estados_matricula.";
    } else {

        // 1) Obtener el nivel del curso al que se quiere matricular
        $stmtCurso = $mysqli->prepare("
            SELECT c.id AS curso_id, c.nivel_id, n.codigo_nivel
            FROM horarios h
            INNER JOIN cursos c ON h.curso_id = c.id
            INNER JOIN niveles_academicos n ON c.nivel_id = n.id
            WHERE h.id = ?
            LIMIT 1
        ");
        $stmtCurso->bind_param("i", $horario_id);
        $stmtCurso->execute();
        $cursoData = $stmtCurso->get_result()->fetch_assoc();
        $stmtCurso->close();

        if (!$cursoData) {
            $error = "El horario seleccionado no existe.";
        } else {
            $nivel_curso     = (int)$cursoData['nivel_id'];      // nivel del curso (1 = A1, 2 = A2, etc.)
            $codigo_nivel    = $cursoData['codigo_nivel'];       // A1, A2...
            $max_nivel_final = obtenerNivelMaximoFinalizado($mysqli, $usuario_id);

            $cumple_requisito = true;
            $mensaje_requisito = "";

            if ($nivel_curso > 1) {
                // Requiere haber completado al menos el nivel anterior
                $nivel_requerido_id = $nivel_curso - 1;
                $codigo_requerido   = obtenerCodigoNivel($mysqli, $nivel_requerido_id);

                if ($max_nivel_final < $nivel_requerido_id) {
                    $cumple_requisito = false;
                    if ($codigo_requerido) {
                        $mensaje_requisito = "Este curso es de nivel {$codigo_nivel}. Debes completar primero el nivel {$codigo_requerido}.";
                    } else {
                        $mensaje_requisito = "No cumples el requisito de nivel para este curso.";
                    }
                }
            }

            if (!$cumple_requisito) {
                $error = $mensaje_requisito;
            } else {
                // 2) ¿ya está matriculado en este horario?
                $check = $mysqli->prepare("
                    SELECT id FROM matriculas
                    WHERE estudiante_id = ? AND horario_id = ? AND estado_id = ?
                ");
                $check->bind_param("iii", $usuario_id, $horario_id, $estado_activa_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $error = "Ya estás matriculado en este horario.";
                } else {
                    // 3) Obtener precio y método de pago por defecto
                    $curso_id = (int)$cursoData['curso_id'];
                    $precio   = obtenerPrecioCursoActual($mysqli, $curso_id);
                    $metodo_default = obtenerMetodoPagoDefecto($mysqli);

                    // Definir fecha de vencimiento (por ejemplo, 30 días después)
                    $fecha_vencimiento = null;
                    if ($precio !== null) {
                        $fecha_vencimiento = date('Y-m-d', strtotime('+30 days'));
                    }

                    $ins = $mysqli->prepare("
                        INSERT INTO matriculas (
                            estudiante_id, horario_id, fecha_matricula,
                            estado_id, metodo_pago_id, monto_pagado, fecha_vencimiento
                        )
                        VALUES (?, ?, NOW(), ?, ?, ?, ?)
                    ");

                    // Si no hay precio o método, dejamos NULL
                    $metodo_pago_id = $metodo_default; // puede ser null
                    $monto_pagado   = $precio;         // puede ser null

                    // iiiids = int, int, int, int, double, string
                    $ins->bind_param(
                        "iiiids",
                        $usuario_id,
                        $horario_id,
                        $estado_activa_id,
                        $metodo_pago_id,
                        $monto_pagado,
                        $fecha_vencimiento
                    );

                    if ($ins->execute()) {
                        $mensaje = "Te has matriculado correctamente en el curso.";
                        // Actualizar cupos_disponibles (disminuir en 1)
                        $upd = $mysqli->prepare("UPDATE horarios SET cupos_disponibles = cupos_disponibles - 1 WHERE id = ?");
                        $upd->bind_param("i", $horario_id);
                        $upd->execute();
                        $upd->close();
                    } else {
                        $error = "Error al matricularte: " . $ins->error;
                    }
                    $ins->close();
                }
                $check->close();
            }
        }
    }
}

// --------------------------------------------------------
// OBTENER NIVEL MÁXIMO FINALIZADO PARA PINTAR LA TABLA
// --------------------------------------------------------
$nivel_max_finalizado = obtenerNivelMaximoFinalizado($mysqli, $usuario_id);
$codigo_nivel_maximo  = $nivel_max_finalizado > 0
    ? obtenerCodigoNivel($mysqli, $nivel_max_finalizado)
    : null;

// --------------------------------------------------------
// CONSULTAR CURSOS DISPONIBLES (horarios activos con cupos)
// --------------------------------------------------------
$cursos = $mysqli->query("
    SELECT 
        h.id AS horario_id,
        c.id AS curso_id,
        c.nombre_curso,
        c.nivel_id,
        n.codigo_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.cupos_disponibles
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

<?php if ($codigo_nivel_maximo): ?>
    <p class="text-muted small mb-1">
        Tu nivel completado más alto: <strong><?= htmlspecialchars($codigo_nivel_maximo) ?></strong>
    </p>
<?php else: ?>
    <p class="text-muted small mb-1">
        Aún no tienes niveles finalizados. Puedes comenzar desde el nivel inicial (A1).
    </p>
<?php endif; ?>

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
            <th>Precio</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($cursos && $cursos->num_rows > 0): ?>
            <?php while ($row = $cursos->fetch_assoc()): ?>
                <?php
                    $nivel_curso = (int)$row['nivel_id'];
                    $codigo_nivel = $row['codigo_nivel'];

                    $requiere_previo = $nivel_curso > 1;
                    $tiene_requisito = true;
                    $texto_requisito = "";

                    if ($requiere_previo) {
                        $nivel_requerido_id = $nivel_curso - 1;
                        $codigo_requerido = obtenerCodigoNivel($mysqli, $nivel_requerido_id);
                        if ($nivel_max_finalizado < $nivel_requerido_id) {
                            $tiene_requisito = false;
                            if ($codigo_requerido) {
                                $texto_requisito = "Debes completar el nivel {$codigo_requerido} para inscribirte en este curso.";
                            } else {
                                $texto_requisito = "No cumples el requisito de nivel para este curso.";
                            }
                        }
                    }

                    // Precio del curso (según precios_cursos)
                    $precio_row = obtenerPrecioCursoActual($mysqli, (int)$row['curso_id']);
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['nombre_curso']) ?></td>
                    <td><?= htmlspecialchars($row['codigo_nivel']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                    <td><?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?></td>
                    <td><?= (int)$row['cupos_disponibles'] ?></td>

                    <!-- Columna PRECIO -->
                    <td>
                        <?php
                            if ($precio_row !== null) {
                                echo "L " . number_format($precio_row, 2);
                            } else {
                                echo '<span class="text-muted small">Sin precio</span>';
                            }
                        ?>
                    </td>

                    <!-- Columna de acción (matricular / requisito) -->
                    <td>
                        <?php if ($tiene_requisito): ?>
                            <a href="?matricular=<?= (int)$row['horario_id'] ?>"
                               class="btn btn-sm btn-tt-primary">
                                Matricular
                            </a>
                        <?php else: ?>
                            <span class="text-danger small d-block">
                                <?= htmlspecialchars($texto_requisito) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-muted">No hay horarios disponibles.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
