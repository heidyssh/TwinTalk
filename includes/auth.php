<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requiere que el usuario esté logueado
function require_login() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: /twintalk/login.php");
        exit();
    }
}

// Requiere que el usuario tenga un rol específico
function require_role($roles_permitidos = []) {
    require_login();
    if (!in_array($_SESSION['rol_id'], $roles_permitidos)) {
        // si no tiene permiso, lo mandamos a su dashboard
        switch ($_SESSION['rol_id']) {
            case 1:
                header("Location: /twintalk/admin/dashboard.php");
                break;
            case 2:
                header("Location: /twintalk/docente/dashboard.php");
                break;
            case 3:
            default:
                header("Location: /twintalk/student/dashboard.php");
                break;
        }
        exit();
    }
}

// Redirección según rol después de login
function redirect_by_role() {
    switch ($_SESSION['rol_id']) {
        case 1:
            header("Location: /twintalk/admin/dashboard.php");
            break;
        case 2:
            header("Location: /twintalk/docente/dashboard.php");
            break;
        case 3:
        default:
            header("Location: /twintalk/student/dashboard.php");
            break;
    }
    exit();
}
