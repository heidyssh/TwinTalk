<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

// Para estudiante usa [3], para docente [2]
require_role([2,3]);

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$mensaje = "";
$error   = "";

// Enviar mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensaje'])) {
    $email_dest = trim($_POST['email_dest'] ?? '');
    $asunto     = trim($_POST['asunto'] ?? '');
    $contenido  = trim($_POST['contenido'] ?? '');

    if ($email_dest === '' || $contenido === '') {
        $error = "Debes indicar al menos el destinatario y el contenido.";
    } else {
        // Buscar destinatario por email
        $stmt = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email_dest);
        $stmt->execute();
        $res = $stmt->get_result();
        $dest = $res->fetch_assoc();
        $stmt->close();

        if (!$dest) {
            $error = "No se encontró un usuario con ese correo.";
        } else {
            $destinatario_id = (int)$dest['id'];

            $stmt = $mysqli->prepare("
                INSERT INTO mensajes (remitente_id, destinatario_id, asunto, contenido, leido, fecha_envio)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->bind_param("iiss", $usuario_id, $destinatario_id, $asunto, $contenido);
            if ($stmt->execute()) {
                $mensaje = "Mensaje enviado correctamente.";
            } else {
                $error = "No se pudo enviar el mensaje.";
            }
            $stmt->close();
        }
    }
}

// Bandeja de entrada
$stmt = $mysqli->prepare("
    SELECT m.id, u.nombre, u.apellido, u.email, m.asunto, m.contenido, m.leido, m.fecha_envio
    FROM mensajes m
    INNER JOIN usuarios u ON m.remitente_id = u.id
    WHERE m.destinatario_id = ?
    ORDER BY m.fecha_envio DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$inbox = $stmt->get_result();
$stmt->close();

// Enviados
$stmt = $mysqli->prepare("
    SELECT m.id, u.nombre, u.apellido, u.email, m.asunto, m.contenido, m.leido, m.fecha_envio
    FROM mensajes m
    INNER JOIN usuarios u ON m.destinatario_id = u.id
    WHERE m.remitente_id = ?
    ORDER BY m.fecha_envio DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$sent = $stmt->get_result();
$stmt->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Mensajes internos</h1>

<?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3 mt-2">
    <div class="col-lg-4">
        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-3">Nuevo mensaje</h2>
            <form method="post">
                <div class="mb-2">
                    <label class="form-label">Para (correo)</label>
                    <input type="email" name="email_dest" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Asunto</label>
                    <input type="text" name="asunto" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">Mensaje</label>
                    <textarea name="contenido" class="form-control" rows="4" required></textarea>
                </div>
                <button class="btn btn-tt-primary btn-sm" name="enviar_mensaje">Enviar</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <ul class="nav nav-tabs" id="tabMensajes" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#inbox" type="button">
                    Recibidos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sent" type="button">
                    Enviados
                </button>
            </li>
        </ul>
        <div class="tab-content border border-top-0 rounded-bottom p-3">
            <div class="tab-pane fade show active" id="inbox">
                <?php if ($inbox->num_rows > 0): ?>
                    <?php while ($m = $inbox->fetch_assoc()): ?>
                        <div class="mb-3 p-2 border rounded-3 <?= $m['leido'] ? 'bg-light' : '' ?>">
                            <div class="small text-muted">
                                De: <?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?>
                                (<?= htmlspecialchars($m['email']) ?>) •
                                <?= htmlspecialchars($m['fecha_envio']) ?>
                            </div>
                            <?php if ($m['asunto']): ?>
                                <div class="fw-bold"><?= htmlspecialchars($m['asunto']) ?></div>
                            <?php endif; ?>
                            <div class="small"><?= nl2br(htmlspecialchars($m['contenido'])) ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0">No tienes mensajes recibidos.</p>
                <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="sent">
                <?php if ($sent->num_rows > 0): ?>
                    <?php while ($m = $sent->fetch_assoc()): ?>
                        <div class="mb-3 p-2 border rounded-3">
                            <div class="small text-muted">
                                Para: <?= htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?>
                                (<?= htmlspecialchars($m['email']) ?>) •
                                <?= htmlspecialchars($m['fecha_envio']) ?>
                            </div>
                            <?php if ($m['asunto']): ?>
                                <div class="fw-bold"><?= htmlspecialchars($m['asunto']) ?></div>
                            <?php endif; ?>
                            <div class="small"><?= nl2br(htmlspecialchars($m['contenido'])) ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0">No has enviado mensajes.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
