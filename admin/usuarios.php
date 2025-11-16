<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]);

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'], $_POST['rol_id'])) {
    $usuario_id = (int)$_POST['usuario_id'];
    $rol_id     = (int)$_POST['rol_id'];

    // evitar que se cambie a sí mismo a no-admin por accidente (opcional)
    if ($usuario_id == $_SESSION['usuario_id'] && $rol_id != 1) {
        $mensaje = "No puedes quitarte tu propio rol de administrador.";
    } else {
        $stmt = $mysqli->prepare("UPDATE usuarios SET rol_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $rol_id, $usuario_id);
        if ($stmt->execute()) {
            $mensaje = "Rol actualizado correctamente.";
        } else {
            $mensaje = "Error al actualizar rol.";
        }
    }
}

$usuarios = $mysqli->query("
    SELECT u.id, u.nombre, u.apellido, u.email, u.rol_id, r.nombre_rol
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
    ORDER BY u.fecha_registro DESC
");

$roles = $mysqli->query("SELECT id, nombre_rol FROM roles ORDER BY id");

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Gestión de usuarios</h1>

<?php if ($mensaje): ?><div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

<div class="table-responsive table-rounded mt-3">
    <table class="table align-middle">
        <thead class="table-light">
        <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Rol actual</th>
            <th>Cambiar rol</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($u = $usuarios->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($u['nombre'] . " " . $u['apellido']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($u['nombre_rol']) ?></span></td>
                <td>
                    <form class="d-flex gap-1" method="post">
                        <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                        <select name="rol_id" class="form-select form-select-sm w-auto">
                            <?php while ($r = $roles->fetch_assoc()): ?>
                                <option value="<?= (int)$r['id'] ?>"
                                    <?= $r['id'] == $u['rol_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['nombre_rol']) ?>
                                </option>
                            <?php endwhile; ?>
                            <?php $roles->data_seek(0); ?>
                        </select>
                        <button class="btn btn-sm btn-tt-primary">Guardar</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
