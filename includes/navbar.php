<?php
// includes/navbar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/twintalk/index.php">
            <img src="/twintalk/assets/img/logo.png" alt="TwinTalk English" class="logo-navbar me-2">
            <span class="fw-bold text-primary">TwinTalk English</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
            aria-controls="mainNavbar" aria-expanded="false" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/twintalk/index.php">Inicio</a></li>
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item d-flex align-items-center me-2">
                        <?php
                        require_once __DIR__ . '/../config/db.php';
                        $uid = $_SESSION['usuario_id'];
                        $resAvatar = $mysqli->query("SELECT foto_perfil FROM usuarios WHERE id = $uid");
                        $avatarRow = $resAvatar ? $resAvatar->fetch_assoc() : null;
                        $avatar = $avatarRow && $avatarRow['foto_perfil']
                            ? $avatarRow['foto_perfil']
                            : '/twintalk/assets/img/avatars/avatar1.png';
                        ?>
                        <img src="<?= htmlspecialchars($avatar) ?>" class="avatar-sm me-1" alt="Avatar">
                    </li>
                    <?php if ($_SESSION['rol_id'] == 3): ?>
                        <li class="nav-item"><a class="nav-link" href="/twintalk/student/dashboard.php">Mi Panel</a></li>
                    <?php elseif ($_SESSION['rol_id'] == 2): ?>
                        <li class="nav-item"><a class="nav-link" href="/twintalk/docente/dashboard.php">Panel Docente</a></li>
                    <?php elseif ($_SESSION['rol_id'] == 1): ?>
                        <li class="nav-item"><a class="nav-link" href="/twintalk/admin/dashboard.php">Panel Admin</a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="btn btn-outline-danger ms-lg-2" href="/twintalk/logout.php">
                            <i class="fa-solid fa-right-from-bracket me-1"></i>Salir
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-lg-2" href="/twintalk/login.php">
                            Iniciar sesión
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>