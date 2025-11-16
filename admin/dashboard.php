<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]);

// Contadores para vista general
$total_usuarios   = $mysqli->query("SELECT COUNT(*) AS c FROM usuarios")->fetch_assoc()['c'] ?? 0;
$total_estudiantes= $mysqli->query("SELECT COUNT(*) AS c FROM estudiantes")->fetch_assoc()['c'] ?? 0;
$total_docentes   = $mysqli->query("SELECT COUNT(*) AS c FROM docentes")->fetch_assoc()['c'] ?? 0;
$total_cursos     = $mysqli->query("SELECT COUNT(*) AS c FROM cursos")->fetch_assoc()['c'] ?? 0;
$total_matriculas = $mysqli->query("SELECT COUNT(*) AS c FROM matriculas")->fetch_assoc()['c'] ?? 0;

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Panel de administración</h1>
<p class="text-muted mb-4">
    Control total de usuarios, cursos y matrículas de TwinTalk English.
</p>

<div class="row g-3">
    <div class="col-md-4 col-lg-3">
        <div class="card card-soft p-3 text-center">
            <span class="small text-muted">Usuarios totales</span>
            <h2 class="display-6 fw-bold text-primary mb-0"><?= $total_usuarios ?></h2>
        </div>
    </div>
    <div class="col-md-4 col-lg-3">
        <div class="card card-soft p-3 text-center">
            <span class="small text-muted">Estudiantes</span>
            <h2 class="display-6 fw-bold text-success mb-0"><?= $total_estudiantes ?></h2>
        </div>
    </div>
    <div class="col-md-4 col-lg-3">
        <div class="card card-soft p-3 text-center">
            <span class="small text-muted">Docentes</span>
            <h2 class="display-6 fw-bold text-info mb-0"><?= $total_docentes ?></h2>
        </div>
    </div>
    <div class="col-md-4 col-lg-3">
        <div class="card card-soft p-3 text-center">
            <span class="small text-muted">Cursos</span>
            <h2 class="display-6 fw-bold text-warning mb-0"><?= $total_cursos ?></h2>
        </div>
    </div>
</div>

<div class="row g-3 mt-2">
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6 fw-bold mb-2">Gestión rápida</h2>
            <p class="small text-muted mb-3">
                Desde aquí puedes administrar usuarios y cursos. Recuerda que solo el admin
                puede cambiar el rol de un usuario (por defecto todos son estudiantes).
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="usuarios.php" class="btn btn-tt-primary btn-sm">
                    Gestionar usuarios
                </a>
                <a href="cursos.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    Gestionar cursos y horarios
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6 fw-bold mb-2">Resumen de matrículas</h2>
            <p class="display-6 fw-bold text-secondary mb-0"><?= $total_matriculas ?></p>
            <p class="small text-muted">
                Total de matrículas registradas. Para detalles avanzados puedes hacer consultas
                desde phpMyAdmin (no se incluye pago en línea, solo registro manual de pagos).
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
