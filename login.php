<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/auth.php";

if (isset($_SESSION['usuario_id'])) {
    redirect_by_role();
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores[] = "Debe ingresar correo y contraseña.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, password_hash, rol_id, nombre, apellido,foto_perfil FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['usuario_id'] = $row['id'];
                $_SESSION['rol_id'] = $row['rol_id'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['apellido'] = $row['apellido'];
                $_SESSION['foto_perfil'] = $row['foto_perfil']; // ← IMPORTANTE

                // Actualizar ultimo_login
                $upd = $mysqli->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
                $upd->bind_param("i", $row['id']);
                $upd->execute();

                redirect_by_role();
            } else {
                $errores[] = "Credenciales incorrectas.";
            }
        } else {
            $errores[] = "Usuario no encontrado o inactivo.";
        }
    }
}

include __DIR__ . "/includes/header.php";
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card card-soft p-4">
            <h1 class="h4 fw-bold mb-3 text-center">Iniciar sesión</h1>

            <?php if ($errores): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errores as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-tt-primary w-100 mb-2">Entrar</button>
                <p class="small text-center text-muted mb-0">
                    ¿No tienes cuenta? <a href="register.php">Regístrate como estudiante</a>
                </p>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
