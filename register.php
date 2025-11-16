<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/auth.php";

if (isset($_SESSION['usuario_id'])) {
    redirect_by_role();
}

$errores = [];
$exito = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($nombre === '' || $apellido === '' || $email === '' || $password ==='' || $telefono ===  '') {
        $errores[] = "Todos los campos marcados son obligatorios.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Correo electrónico inválido.";
    }
    if ($password !== $password2) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($errores)) {
        // Comprobar email único
        $check = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errores[] = "Ya existe una cuenta con ese correo.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $rol_estudiante = 3; // según tu dump

            $stmt = $mysqli->prepare("
                INSERT INTO usuarios (email, password_hash, rol_id, nombre, apellido, telefono, fecha_registro, activo)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->bind_param("ssisss", $email, $hash, $rol_estudiante, $nombre, $apellido, $telefono);

            if ($stmt->execute()) {
                $usuario_id = $stmt->insert_id;

                // Insertar en estudiantes
                $nivel_actual = "A1"; // por defecto, puedes cambiarlo luego
                $ins_est = $mysqli->prepare("
                    INSERT INTO estudiantes (id, nivel_actual, fecha_inscripcion)
                    VALUES (?, ?, CURDATE())
                ");
                $ins_est->bind_param("is", $usuario_id, $nivel_actual);
                $ins_est->execute();

                $exito = "¡Cuenta creada! Ahora puedes iniciar sesión.";
            } else {
                $errores[] = "Error al crear usuario: " . $mysqli->error;
            }
        }
    }
}

include __DIR__ . "/includes/header.php";
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-7 col-lg-6">
        <div class="card card-soft p-4">
            <h1 class="h4 fw-bold mb-3 text-center">Registro de estudiante</h1>

            <?php if ($exito): ?>
                <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
            <?php endif; ?>

            <?php if ($errores): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errores as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required
                               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellido *</label>
                        <input type="text" name="apellido" class="form-control" required
                               value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo electrónico *</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" required
                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirmar contraseña *</label>
                        <input type="password" name="password2" class="form-control" required>
                    </div>
                </div>
                <button class="btn btn-tt-primary w-100 mt-2">Crear cuenta</button>
                <p class="small text-center text-muted mt-2 mb-0">
                    Al registrarte, se creará tu perfil de <strong>estudiante</strong>.
                    Los roles de docente/admin solo los asigna el administrador.
                </p>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . "/includes/footer.php"; ?>
