<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // solo estudiantes

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

// Mes y año a mostrar (por defecto, mes actual)
$hoy = new DateTime('today');
$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)$hoy->format('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)$hoy->format('Y');

if ($mes < 1 || $mes > 12) {
    $mes = (int)$hoy->format('n');
}
if ($anio < 2000 || $anio > 2100) {
    $anio = (int)$hoy->format('Y');
}

// Primer día del mes y datos auxiliares
$primerDiaMes   = new DateTime(sprintf('%04d-%02d-01', $anio, $mes));
$nombreMes      = $primerDiaMes->format('F');
$numeroDiasMes  = (int)$primerDiaMes->format('t');
$diaSemanaInicio = (int)$primerDiaMes->format('N'); // 1=lunes...7=domingo

// Mes anterior / siguiente para navegación
$anterior = clone $primerDiaMes;
$anterior->modify('-1 month');
$siguiente = clone $primerDiaMes;
$siguiente->modify('+1 month');

$mesAnt  = (int)$anterior->format('n');
$anioAnt = (int)$anterior->format('Y');
$mesSig  = (int)$siguiente->format('n');
$anioSig = (int)$siguiente->format('Y');

// 1) Consultar tareas de TODOS los cursos activos del estudiante para este mes
$sqlTareas = "
    SELECT 
        t.id AS tarea_id,
        t.titulo AS titulo_tarea,
        t.fecha_entrega,
        c.nombre_curso,
        h.id AS horario_id,
        te.id AS entrega_id
    FROM tareas t
    INNER JOIN horarios h           ON t.horario_id = h.id
    INNER JOIN matriculas m         ON m.horario_id = h.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    INNER JOIN cursos c             ON c.id = h.curso_id
    LEFT JOIN tareas_entregas te 
        ON te.tarea_id = t.id 
       AND te.matricula_id = m.id
    WHERE m.estudiante_id = ?
      AND em.nombre_estado = 'Activa'
      AND t.activo = 1
      AND t.fecha_entrega IS NOT NULL
      AND MONTH(t.fecha_entrega) = ?
      AND YEAR(t.fecha_entrega) = ?
    ORDER BY t.fecha_entrega, c.nombre_curso, t.titulo
";

$stmtT = $mysqli->prepare($sqlTareas);
$stmtT->bind_param("iii", $usuario_id, $mes, $anio);
$stmtT->execute();
$resT = $stmtT->get_result();
$stmtT->close();

// Agrupar tareas por día
$tareasPorDia = [];
while ($row = $resT->fetch_assoc()) {
    $dia = (int)date('j', strtotime($row['fecha_entrega']));
    if (!isset($tareasPorDia[$dia])) {
        $tareasPorDia[$dia] = [];
    }
    $tareasPorDia[$dia][] = $row;
}

// Hoy para resaltar fecha actual
$hoyDia   = (int)$hoy->format('j');
$hoyMes   = (int)$hoy->format('n');
$hoyAnio  = (int)$hoy->format('Y');

include __DIR__ . "/../includes/header.php";
?>

<div class="row mt-3">
    <div class="col-12 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h4 fw-bold mb-1">
                Calendario de asignaciones 📅
            </h1>
            <p class="text-muted mb-0 small">
                Ve todas tus tareas por fecha de entrega. 
                Las que ya enviaste se muestran <span class="text-decoration-line-through">tachadas</span>.
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-gauge-high me-1"></i> Volver al dashboard
        </a>
    </div>
</div>

<div class="card card-soft">
    <div class="card-body">

        <!-- Navegación de meses -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="calendario.php?mes=<?= $mesAnt ?>&anio=<?= $anioAnt ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <div class="text-center">
                <h2 class="h5 mb-0">
                    <?php
                    // Mes en español simple
                    setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');
                    $nombreMesEsp = strftime('%B', $primerDiaMes->getTimestamp());
                    echo ucfirst($nombreMesEsp) . " " . $anio;
                    ?>
                </h2>
            </div>
            <a href="calendario.php?mes=<?= $mesSig ?>&anio=<?= $anioSig ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 calendar-table">
                <thead class="table-light">
                    <tr class="text-center small">
                        <th class="fw-semibold">Lun</th>
                        <th class="fw-semibold">Mar</th>
                        <th class="fw-semibold">Mié</th>
                        <th class="fw-semibold">Jue</th>
                        <th class="fw-semibold">Vie</th>
                        <th class="fw-semibold">Sáb</th>
                        <th class="fw-semibold">Dom</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dia = 1;
                    $columna = 1;

                    echo "<tr>";

                    // Celdas vacías antes del primer día
                    for ($i = 1; $i < $diaSemanaInicio; $i++) {
                        echo '<td class="bg-light-subtle" style="height:110px;"></td>';
                        $columna++;
                    }

                    while ($dia <= $numeroDiasMes) {
                        // Si empezamos una nueva fila
                        if ($columna > 7) {
                            echo "</tr><tr>";
                            $columna = 1;
                        }

                        $esHoy = ($dia === $hoyDia && $mes === $hoyMes && $anio === $hoyAnio);
                        $claseHoy = $esHoy ? 'border-primary border-2' : '';

                        echo '<td class="align-top ' . $claseHoy . '" style="height:110px; font-size:0.85rem;">';

                        // Número de día
                        echo '<div class="d-flex justify-content-between align-items-center mb-1">';
                        echo '<span class="fw-semibold">' . $dia . '</span>';
                        if ($esHoy) {
                            echo '<span class="badge bg-primary-subtle text-primary border border-primary-subtle small">Hoy</span>';
                        }
                        echo '</div>';

                        // Listado de tareas para este día
                        if (!empty($tareasPorDia[$dia])) {
                            echo '<ul class="list-unstyled mb-0">';
                            foreach ($tareasPorDia[$dia] as $t) {
                                $completada = !empty($t['entrega_id']);

                                echo '<li class="mb-1">';
                                echo '<a href="curso_detalle.php?horario_id=' . (int)$t['horario_id'] . '" class="text-decoration-none small d-block">';

                                if ($completada) {
                                    echo '<span class="text-decoration-line-through text-muted">';
                                } else {
                                    echo '<span>';
                                }

                                echo htmlspecialchars($t['titulo_tarea']);

                                echo '</span>';

                                echo '<br><span class="text-muted small">';
                                echo htmlspecialchars($t['nombre_curso']);
                                if ($completada) {
                                    echo ' · <span class="text-success">Enviada ✅</span>';
                                } else {
                                    echo ' · <span class="text-danger">Pendiente</span>';
                                }
                                echo '</span>';

                                echo '</a>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<span class="text-muted small">Sin tareas</span>';
                        }

                        echo '</td>';

                        $dia++;
                        $columna++;
                    }

                    // Rellenar celdas vacías al final
                    while ($columna <= 7) {
                        echo '<td class="bg-light-subtle" style="height:110px;"></td>';
                        $columna++;
                    }

                    echo "</tr>";
                    ?>
                </tbody>
            </table>
        </div>

        <p class="small text-muted mt-2 mb-0">
            * Solo se muestran las tareas de tus cursos activos que tienen fecha de entrega.
        </p>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
