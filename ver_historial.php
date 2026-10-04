<?php
/*
 - 03-10-2026
 - archivo: estadisticas.php
 - Estadísticas de registros de boyas y bombas.
 */
include 'conexion.php';

$mostrarJson = ($_GET['json'] ?? '') === '1';
$fecha = trim($_GET['fecha'] ?? ($mostrarJson ? '' : date('Y-m-d')));
$datosDisponibles = [
    'Boya 1' => 'b1',
    'Boya 2' => 'b2',
    'Boya 3' => 'b3',
    'Boya 4' => 'b4',
    'Boya 5' => 'b5',
    'Boya 6' => 'b6',
    'Boya 7' => 'b7',
    'Bomba 1' => 'mo1',
    'Bomba 2' => 'mo2',
    'Bomba 3' => 'mo3',
    'Bomba 4' => 'mo4',
    'Bomba 5' => 'mo5',
    'Bomba 6' => 'mo6',
    'Bomba 7' => 'mo7',
];
$selectorEnviado = isset($_GET['selector']);
$datosSolicitados = $_GET['datos'] ?? [];
if (!is_array($datosSolicitados)) {
    $datosSolicitados = [];
}
$datosSolicitados = array_filter($datosSolicitados, 'is_string');
$datosSeleccionados = $selectorEnviado
    ? array_values(array_intersect(array_keys($datosDisponibles), $datosSolicitados))
    : array_keys($datosDisponibles);
$registros = [];
$error = null;

try {
    if ($fecha !== '') {
        $fechaValidada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException('Selecciona una fecha válida.');
        }

        $ordenRegistros = $mostrarJson ? 'DESC, Id DESC' : 'ASC, Id ASC';
        $stmt = $conexion->prepare('SELECT * FROM registros WHERE fecha = ? ORDER BY hora ' . $ordenRegistros);
        $stmt->bind_param('s', $fecha);
        $stmt->execute();
        $result = $stmt->get_result();
        $registros = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif ($mostrarJson) {
        $result = $conexion->query('SELECT * FROM registros ORDER BY Id DESC');
        $registros = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$conexion->close();

if ($mostrarJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo $error ? json_encode(['error' => $error]) : json_encode($registros);
    exit;
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$coloresGrafico = [
    '#147a64', '#d05a38', '#3976b8', '#9a62a8', '#c28a16',
    '#268c9b', '#ba4964', '#6a7f32', '#7054c7', '#bf6c24',
    '#397f5b', '#536d91', '#a34c8b',
];
$series = [];
foreach ($datosSeleccionados as $indice => $dato) {
    $serie = [
        'nombre' => $dato,
        'color' => $coloresGrafico[$indice % count($coloresGrafico)],
        'puntos' => [],
        'segmentos' => [],
        'encendidos' => 0,
    ];
    $segmento = [];
    $estadoAnterior = null;

    foreach ($registros as $registro) {
        $hora = (string) ($registro['hora'] ?? '');
        $valor = $registro[$datosDisponibles[$dato]] ?? null;
        $horaValida = preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?(?:\.\d+)?$/', $hora, $coincidencias);
        $valorValido = is_numeric($valor) && (float) $valor >= 0 && (float) $valor <= 1;

        if (!$horaValida || !$valorValido) {
            if (count($segmento) > 1) {
                $serie['segmentos'][] = $segmento;
            }
            $segmento = [];
            $estadoAnterior = null;
            continue;
        }

        $estadoActual = (float) $valor;
        if ($estadoActual === 1.0 && $estadoAnterior === 0.0) {
            $serie['encendidos']++;
        }
        $estadoAnterior = $estadoActual;

        $segundos = ((int) $coincidencias[1] * 3600)
            + ((int) $coincidencias[2] * 60)
            + (isset($coincidencias[3]) ? (int) $coincidencias[3] : 0);
        $punto = [
            'x' => 64 + ($segundos / 86400 * 912),
            'y' => 24 + (1 - (float) $valor) * 402,
            'hora' => $hora,
            'valor' => (float) $valor,
        ];
        $serie['puntos'][] = $punto;
        $segmento[] = $punto;
    }

    if (count($segmento) > 1) {
        $serie['segmentos'][] = $segmento;
    }
    $series[] = $serie;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial de registros</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #183333;
            --muted: #637775;
            --line: #d6e2dc;
            --accent: #147a64;
            --accent-dark: #0d604f;
            --surface: #fff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            background: linear-gradient(135deg, #edf4ef 0%, #f8faf7 58%, #eaf3f0 100%);
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
        }

        main {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
            padding: 38px 0 56px;
        }

        header {
            padding-bottom: 22px;
            border-bottom: 1px solid var(--line);
        }

        h1 {
            margin: 0;
            font-family: Georgia, serif;
            font-size: 30px;
            font-weight: 500;
        }

        .subtitle, .result-count, .empty-state, .error {
            color: var(--muted);
        }

        .subtitle { margin: 8px 0 0; }

        form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 12px;
            padding: 22px 0;
            border-bottom: 1px solid var(--line);
        }

        .data-selector {
            flex: 1 1 100%;
            min-width: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .data-selector legend {
            margin-bottom: 9px;
            padding: 0;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
            gap: 8px;
            max-width: 800px;
        }

        .check-option {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 6px 9px;
            border: 1px solid var(--line);
            border-radius: 4px;
            color: var(--ink);
            background: var(--surface);
            font-size: 13px;
            font-weight: 400;
            cursor: pointer;
        }

        .check-option input {
            width: 16px;
            min-width: 16px;
            min-height: 16px;
            margin: 0;
            padding: 0;
            accent-color: var(--accent);
        }

        label {
            display: grid;
            gap: 7px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        input, button, .clear-link, .button-link {
            min-height: 42px;
            border: 1px solid #bdcec5;
            border-radius: 4px;
            font: inherit;
        }

        input {
            min-width: 210px;
            padding: 8px 10px;
            color: var(--ink);
            background: var(--surface);
        }

        button, .button-link {
            padding: 8px 16px;
            border-color: var(--accent);
            color: #fff;
            background: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .button-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .button-link svg {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.8;
        }

        .button-link .chart-bars { stroke: #ffd166; }
        .button-link .chart-trend { stroke: #7ee2c3; }

        button {
            cursor: pointer;
        }

        button:hover, .button-link:hover { background: var(--accent-dark); }

        .clear-link {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            color: var(--ink);
            background: var(--surface);
            text-decoration: none;
        }

        .result-count { margin: 20px 0 10px; font-size: 14px; }

        .chart-card {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: var(--surface);
        }

        .chart-wrap {
            width: 100%;
            padding: 12px 16px 0;
        }

        .chart {
            display: block;
            width: 100%;
            height: auto;
        }

        .grid-line { stroke: #e5ece8; stroke-width: 1; }
        .axis-line { stroke: #8ba09a; stroke-width: 1.2; }
        .axis-label { fill: #526864; font: 12px "Trebuchet MS", "Segoe UI", sans-serif; }
        .axis-title { fill: #31504b; font: 700 13px "Trebuchet MS", "Segoe UI", sans-serif; }
        .data-line { fill: none; stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
        .data-point { stroke: #fff; stroke-width: 1.5; }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            padding: 12px 18px 16px;
            border-top: 1px solid #e5ece8;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--ink);
            font-size: 13px;
        }

        .legend-swatch {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .on-counts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin: 14px 0;
        }

        .on-count {
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: var(--surface);
        }

        .on-count-name {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .on-count-value {
            display: block;
            margin-top: 5px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
        }

        .empty-state, .error { padding: 18px 0; }
        .error { color: #a23434; }

        @media (max-width: 560px) {
            main { width: min(100% - 24px, 1180px); padding-top: 26px; }
            h1 { font-size: 26px; }
            form { align-items: stretch; }
            .date-field, form > button, .clear-link, .button-link { width: 100%; }
            .date-field input { width: 100%; }
            .checkbox-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <main>
        <header>
            <h1>Historial de registros</h1>
            <p class="subtitle">Selecciona una fecha para consultar las mediciones guardadas.</p>
        </header>

        <form method="get" action="ver_historial.php">
            <label class="date-field" for="fecha">
                Fecha
                <input id="fecha" name="fecha" type="date" value="<?= escapar($fecha) ?>" required>
            </label>
            <fieldset class="data-selector">
                <legend>Datos que se mostrarán</legend>
                <input type="hidden" name="selector" value="1">
                <div class="checkbox-grid">
                    <?php foreach ($datosDisponibles as $dato => $columna): ?>
                        <label class="check-option">
                            <input type="checkbox" name="datos[]" value="<?= escapar($dato) ?>" <?= in_array($dato, $datosSeleccionados, true) ? 'checked' : '' ?>>
                            <?= escapar($dato) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button type="submit">Mostrar datos</button>
            <a class="button-link" href="estadisticas.php">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path class="chart-bars" d="M4 19.5h16M6.5 16v-4M11.5 16V7M16.5 16v-6"></path>
                    <path class="chart-trend" d="m5 9 5-4 5 2 4-4"></path>
                </svg>
                Estadisticas
            </a>
            <?php if ($fecha !== ''): ?>
                <a class="clear-link" href="ver_historial.php">Limpiar</a>
            <?php endif; ?>
        </form>

        <?php if ($error !== null): ?>
            <p class="error"><?= escapar($error) ?></p>
        <?php elseif ($fecha === ''): ?>
            <p class="empty-state">Elige un día para ver sus registros.</p>
        <?php elseif (empty($registros)): ?>
            <p class="empty-state">No hay registros para el <?= escapar($fecha) ?>.</p>
        <?php elseif (empty($datosSeleccionados)): ?>
            <p class="empty-state">Selecciona al menos un dato para mostrar el gráfico.</p>
        <?php else: ?>
            <p class="result-count"><?= count($registros) ?> registro(s) para el <?= escapar($fecha) ?></p>
            <div class="on-counts" aria-label="Veces que cada elemento pasó de OFF a ON">
                <?php foreach ($series as $serie): ?>
                    <div class="on-count">
                        <span class="on-count-name">
                            <span class="legend-swatch" style="background-color: <?= escapar($serie['color']) ?>"></span>
                            <?= escapar($serie['nombre']) ?>
                        </span>
                        <strong class="on-count-value"><?= escapar($serie['encendidos']) ?> ON</strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <section class="chart-card" aria-label="Gráfico de valores del día">
                <div class="chart-wrap">
                    <svg class="chart" viewBox="0 0 1000 500" role="img" aria-labelledby="chart-title chart-description">
                        <title id="chart-title">Valores del <?= escapar($fecha) ?></title>
                        <desc id="chart-description">Gráfico de los datos seleccionados durante las 24 horas del día. El eje vertical va de 0 a 1.</desc>
                        <?php foreach ([0, 1] as $tick):
                            $valorEje = $tick;
                            $y = 24 + (1 - $valorEje) * 402;
                        ?>
                            <line class="grid-line" x1="64" y1="<?= escapar($y) ?>" x2="976" y2="<?= escapar($y) ?>"></line>
                            <text class="axis-label" x="52" y="<?= escapar($y + 4) ?>" text-anchor="end"><?= $tick === 0 ? 'OFF' : 'ON' ?></text>
                        <?php endforeach; ?>
                        <?php for ($horaEje = 0; $horaEje <= 24; $horaEje += 2):
                            $x = 64 + ($horaEje / 24 * 912);
                        ?>
                            <line class="grid-line" x1="<?= escapar($x) ?>" y1="24" x2="<?= escapar($x) ?>" y2="426"></line>
                            <text class="axis-label" x="<?= escapar($x) ?>" y="448" text-anchor="middle"><?= escapar(sprintf('%02d:00', $horaEje)) ?></text>
                        <?php endfor; ?>
                        <line class="axis-line" x1="64" y1="426" x2="976" y2="426"></line>
                        <line class="axis-line" x1="64" y1="24" x2="64" y2="426"></line>
                        <text class="axis-title" x="520" y="482" text-anchor="middle">Hora del día</text>
                        <text class="axis-title" x="16" y="225" text-anchor="middle" transform="rotate(-90 16 225)">Estado</text>
                        <?php foreach ($series as $serie): ?>
                            <?php foreach ($serie['segmentos'] as $segmento): ?>
                                <polyline class="data-line" stroke="<?= escapar($serie['color']) ?>" points="<?php
                                    $coordenadas = [];
                                    foreach ($segmento as $punto) {
                                        $coordenadas[] = number_format($punto['x'], 2, '.', '') . ',' . number_format($punto['y'], 2, '.', '');
                                    }
                                    echo escapar(implode(' ', $coordenadas));
                                ?>"></polyline>
                            <?php endforeach; ?>
                            <?php foreach ($serie['puntos'] as $punto): ?>
                                <circle class="data-point" cx="<?= escapar(number_format($punto['x'], 2, '.', '')) ?>" cy="<?= escapar(number_format($punto['y'], 2, '.', '')) ?>" r="4" fill="<?= escapar($serie['color']) ?>">
                                    <title><?= escapar($serie['nombre']) ?> — <?= escapar($punto['hora']) ?>: <?= escapar(number_format($punto['valor'], 2, ',', '')) ?></title>
                                </circle>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </svg>
                </div>
                <div class="chart-legend" aria-label="Leyenda del gráfico">
                    <?php foreach ($series as $serie): ?>
                        <span class="legend-item">
                            <span class="legend-swatch" style="background-color: <?= escapar($serie['color']) ?>"></span>
                            <?= escapar($serie['nombre']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php if (!array_filter($series, static function ($serie) { return !empty($serie['puntos']); })): ?>
                <p class="empty-state">No hay valores entre 0 y 1 para mostrar en el gráfico.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>