<?php
session_start();
include 'conexion.php';

$ano = 2026;
$inicio = $ano . '-06-01';
$fin = $ano . '-07-01';
$error = null;
$mensaje = $_SESSION['borra_junio_mensaje'] ?? null;
unset($_SESSION['borra_junio_mensaje']);

if (empty($_SESSION['borra_junio_token'])) {
    $_SESSION['borra_junio_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['borra_junio_token'];

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (!$conexion) {
    $error = 'No se pudo conectar con la base de datos.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = $_POST['token'] ?? '';
    $confirmado = isset($_POST['confirmar']) && $_POST['confirmar'] === 'si';

    if (!is_string($tokenEnviado) || !hash_equals($token, $tokenEnviado)) {
        $error = 'La confirmación venció o no es válida. Recarga la página e inténtalo de nuevo.';
    } elseif (!$confirmado) {
        $error = 'Debes confirmar que deseas borrar los registros.';
    } else {
        try {
            $conexion->begin_transaction();
            $stmt = $conexion->prepare('DELETE FROM registros WHERE fecha >= ? AND fecha < ?');
            if (!$stmt) {
                throw new RuntimeException('No se pudo preparar la eliminación: ' . $conexion->error);
            }

            $stmt->bind_param('ss', $inicio, $fin);
            if (!$stmt->execute()) {
                throw new RuntimeException('No se pudieron borrar los registros: ' . $stmt->error);
            }

            $eliminados = $stmt->affected_rows;
            $stmt->close();
            $conexion->commit();
            $_SESSION['borra_junio_mensaje'] = $eliminados . ' registro(s) de junio de ' . $ano . ' eliminado(s).';
            $_SESSION['borra_junio_token'] = bin2hex(random_bytes(32));
            $conexion->close();
            header('Location: borra_junio.php');
            exit;
        } catch (Throwable $e) {
            $conexion->rollback();
            error_log('Error al borrar registros de junio: ' . $e->getMessage());
            $error = 'No se pudieron borrar los registros. No se confirmó ningún cambio; revisa el registro de errores del servidor.';
        }
    }
}

$cantidadRegistros = null;
if ($conexion && $error === null) {
    $stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM registros WHERE fecha >= ? AND fecha < ?');
    if (!$stmt) {
        $error = 'No se pudo consultar la cantidad de registros: ' . $conexion->error;
    } else {
        $stmt->bind_param('ss', $inicio, $fin);
        if (!$stmt->execute()) {
            $error = 'No se pudo consultar la cantidad de registros: ' . $stmt->error;
        } else {
            $resultado = $stmt->get_result()->fetch_assoc();
            $cantidadRegistros = (int) ($resultado['total'] ?? 0);
        }
        $stmt->close();
    }
}

if ($conexion) {
    $conexion->close();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Borrar registros de junio</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #183333;
            --muted: #637775;
            --line: #d6e2dc;
            --surface: #fff;
            --danger: #a23434;
            --danger-dark: #812828;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            background: linear-gradient(135deg, #edf4ef 0%, #f8faf7 58%, #eaf3f0 100%);
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
        }

        main {
            width: min(100% - 36px, 680px);
            margin: 0 auto;
            padding: 42px 0;
        }

        .card {
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--surface);
            box-shadow: 0 8px 24px #1833330d;
        }

        h1 { margin: 0 0 10px; font-family: Georgia, serif; font-size: 30px; font-weight: 500; }
        p { line-height: 1.55; }
        .warning { color: var(--danger); font-weight: 700; }
        .count { font-size: 18px; }
        .error, .success { padding: 12px 14px; border-radius: 4px; }
        .error { border: 1px solid #e7b6b6; color: var(--danger); background: #fff5f5; }
        .success { border: 1px solid #b9dfca; color: #246b45; background: #f0faf4; }

        .confirm {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin: 20px 0;
            color: var(--ink);
        }

        .confirm input { width: 18px; height: 18px; margin: 2px 0 0; accent-color: var(--danger); }
        button, .back-link {
            display: inline-flex;
            align-items: center;
            min-height: 42px;
            padding: 8px 15px;
            border: 1px solid var(--danger);
            border-radius: 4px;
            color: #fff;
            background: var(--danger);
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        button { cursor: pointer; }
        button:hover { background: var(--danger-dark); }
        .back-link { margin-left: 8px; border-color: var(--line); color: var(--ink); background: #fff; }
        @media (max-width: 560px) {
            main { width: min(100% - 24px, 680px); padding-top: 24px; }
            .card { padding: 18px; }
            h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <main>
        <section class="card">
            <h1>Borrar registros de junio de <?= escapar($ano) ?></h1>
            <p>Esta acción eliminará de la tabla <strong>registros</strong> todos los datos entre el 1 y el 30 de junio de <?= escapar($ano) ?>. No se puede deshacer.</p>

            <?php if ($mensaje !== null): ?>
                <p class="success"><?= escapar($mensaje) ?></p>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <p class="error"><?= escapar($error) ?></p>
            <?php elseif ($cantidadRegistros === 0): ?>
                <p class="count">No hay registros de junio de <?= escapar($ano) ?> para borrar.</p>
            <?php else: ?>
                <p class="count">Se encontraron <strong><?= escapar($cantidadRegistros) ?></strong> registro(s).</p>
                <p class="warning">Confirma solo si deseas borrar permanentemente estos datos.</p>
                <form method="post" action="borra_junio.php">
                    <input type="hidden" name="token" value="<?= escapar($token) ?>">
                    <label class="confirm">
                        <input type="checkbox" name="confirmar" value="si" required>
                        <span>Confirmo que deseo eliminar los registros de junio de <?= escapar($ano) ?>.</span>
                    </label>
                    <button type="submit">Borrar registros de junio</button>
                    <a class="back-link" href="ver_historial.php">Cancelar</a>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>