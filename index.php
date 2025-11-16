<?php
// Redirigir a dashboards si ya está logueado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['rol_id'] == 1) {
        header("Location: /twintalk/admin/dashboard.php");
        exit;
    } elseif ($_SESSION['rol_id'] == 2) {
        header("Location: /twintalk/docente/dashboard.php");
        exit;
    } else {
        header("Location: /twintalk/student/dashboard.php");
        exit;
    }
}

require_once __DIR__ . "/config/db.php";
include __DIR__ . "/includes/header.php";

// Cursos activos que se mostrarán como programas
$cursos = $mysqli->query("
    SELECT c.id, c.nombre_curso, c.descripcion,
           n.codigo_nivel, n.nombre_nivel
    FROM cursos c
    JOIN niveles_academicos n ON c.nivel_id = n.id
    WHERE c.activo = 1
    ORDER BY c.fecha_creacion DESC
    LIMIT 6
");
?>

<!-- HERO -->
<section id="inicio" class="section-hero">
    <div class="row align-items-center gy-4 hero-card p-4 p-lg-5">
        <div class="col-lg-6">
            <span class="hero-pill mb-2">
                Academia de inglés · La Ceiba, Atlántida
            </span>
            <h1 class="hero-title display-5 mb-3">
                TwinTalk English<br>
                <span class="text-primary">aprende inglés con confianza ✨</span>
            </h1>
            <p class="lead text-muted">
                Academia de inglés en La Ceiba, Atlántida, para niños, jóvenes y adultos
                que desean mejorar su inglés para la escuela, la universidad y el trabajo.
            </p>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <a href="#programas" class="btn btn-tt-primary">
                    Ver programas
                </a>
                <a href="#contacto" class="btn btn-outline-secondary rounded-pill">
                    Contáctanos
                </a>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3 small text-muted">
                <div><i class="fa-solid fa-child-reaching me-1 text-primary"></i> Inglés para niños</div>
                <div><i class="fa-solid fa-user-graduate me-1 text-success"></i> Jóvenes y universitarios</div>
                <div><i class="fa-solid fa-briefcase me-1 text-warning"></i> Inglés para profesionales</div>
            </div>
        </div>
        <div class="col-lg-6 text-center">
            <img src="/twintalk/assets/img/logo.png" alt="TwinTalk English" class="img-fluid" style="max-height:260px;">
        </div>
    </div>
</section>

<!-- SEPARADOR SUAVE -->
<hr class="section-divider">

<!-- SOBRE NOSOTROS -->
<section id="sobre" class="section-padding">
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-6">
            <h2 class="section-title">Sobre TwinTalk English</h2>
            <p class="text-muted">
                TwinTalk English es una academia de inglés ubicada en La Ceiba, Atlántida. Nuestro enfoque
                está en la comunicación real: que nuestros estudiantes se sientan capaces de hablar, escuchar,
                leer y escribir en inglés con seguridad.
            </p>
            <p class="text-muted mb-0">
                Trabajamos con grupos pequeños, material actualizado y docentes comprometidos para lograr que
                cada estudiante avance a su ritmo, pero sin perder la disciplina académica.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="card card-soft h-100 p-3">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-book-open-reader me-2 text-primary"></i>Niños</h5>
                        <p class="small text-muted mb-0">Programas diseñados para que los más pequeños
                            aprendan jugando y pierdan el miedo al inglés desde temprana edad.</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card card-soft h-100 p-3">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-user-graduate me-2 text-success"></i>Jóvenes</h5>
                        <p class="small text-muted mb-0">Apoyo para colegio, universidad, exámenes y
                            preparación para oportunidades académicas internacionales.</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card card-soft h-100 p-3">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-briefcase me-2 text-warning"></i>Profesionales</h5>
                        <p class="small text-muted mb-0">Clases enfocadas en inglés laboral, entrevistas,
                            reuniones y comunicación empresarial.</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card card-soft h-100 p-3">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-earth-americas me-2 text-danger"></i>Internacional</h5>
                        <p class="small text-muted mb-0">Niveles basados en el Marco Común Europeo (A1–C2),
                            lo que permite comparar tu nivel con estándares internacionales.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SEPARADOR -->
<hr class="section-divider">

<!-- PROGRAMAS / CURSOS -->
<section id="programas" class="section-padding">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Programas de la academia</h2>
        <span class="small text-muted">Los cursos pueden variar según el período académico.</span>
    </div>

    <div class="row g-3">
        <?php if ($cursos && $cursos->num_rows > 0): ?>
            <?php while ($curso = $cursos->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft h-100 p-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge-level">
                                Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?>
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($curso['nombre_curso']) ?></h5>
                        <p class="small text-muted mb-3">
                            <?= nl2br(htmlspecialchars(substr($curso['descripcion'], 0, 140))) ?>...
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                                <?= htmlspecialchars($curso['nombre_nivel']) ?>
                            </span>
                            <a href="#contacto" class="btn btn-sm btn-tt-primary">
                                Pedir información
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted">
                Próximamente publicaremos los programas del nuevo período.
                El administrador puede crearlos desde el panel administrativo.
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- SEPARADOR -->
<hr class="section-divider">

<!-- MISIÓN / VISIÓN -->
<section id="misionvision" class="section-padding">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card card-soft p-3 h-100">
                <h3 class="h5 fw-bold text-secondary mb-2">Nuestra misión</h3>
                <p class="small text-muted mb-0">
                    Formar estudiantes seguros y competentes en el idioma inglés, desarrollando habilidades
                    comunicativas a través de clases creativas, prácticas y cercanas a su realidad en La Ceiba
                    y la región.
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-soft p-3 h-100">
                <h3 class="h5 fw-bold text-secondary mb-2">Nuestra visión</h3>
                <p class="small text-muted mb-0">
                    Ser la academia de inglés de referencia en La Ceiba, Atlántida, reconocida por su calidad,
                    acompañamiento humano y resultados claros en el aprendizaje de nuestros estudiantes.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- SEPARADOR -->
<hr class="section-divider">

<!-- CONTACTO -->
<section id="contacto" class="section-padding mb-4">
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="section-title">Contáctanos</h2>
            <p class="text-muted small">
                Si deseas información sobre horarios, mensualidades, inscripciones o clases de prueba,
                puedes escribirnos o visitarnos.
            </p>
            <ul class="list-unstyled small text-muted">
                <li><i class="fa-solid fa-location-dot me-2 text-primary"></i> La Ceiba, Atlántida, Honduras</li>
                <li><i class="fa-solid fa-phone me-2 text-primary"></i> +504 0000-0000</li>
                <li><i class="fa-solid fa-envelope me-2 text-primary"></i> info@twintalkenglish.com</li>
                <li><i class="fa-solid fa-clock me-2 text-primary"></i> Lunes a viernes, 8:00 a.m. – 6:00 p.m.</li>
            </ul>
        </div>
        <div class="col-lg-6">
            <div class="card card-soft p-3">
                <h3 class="h6 fw-bold mb-2">Escríbenos un mensaje</h3>
                <form>
                    <div class="mb-2">
                        <label class="form-label small">Nombre completo</label>
                        <input type="text" class="form-control" placeholder="Tu nombre">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Correo electrónico</label>
                        <input type="email" class="form-control" placeholder="tucorreo@example.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Mensaje</label>
                        <textarea class="form-control" rows="3" placeholder="Cuéntanos qué información necesitas..."></textarea>
                    </div>
                    <button type="button" class="btn btn-tt-primary btn-sm w-100" disabled>
                        Enviar (demo para el proyecto)
                    </button>
                    <p class="small text-muted mt-2 mb-0">
                        Este formulario es solo demostrativo para el proyecto académico.
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
