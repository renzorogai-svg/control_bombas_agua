<?php
/*
 - 07-10-2026
 - archivo: estadisticas.php
 - Estadísticas de registros de boyas y bombas.
 */
include 'conexion.php';

$mostrarJson = ($_GET['json'] ?? '') === '1';
$fecha = trim($_GET['fecha'] ?? '');
$fechaInicio = trim($_GET['fecha_inicio'] ?? ($mostrarJson ? $fecha : ($fecha !== '' ? $fecha : date('Y-m-d'))));
$fechaFin = trim($_GET['fecha_fin'] ?? ($mostrarJson ? $fecha : ($fecha !== '' ? $fecha : $fechaInicio)));
if (!$mostrarJson && $fechaFin === '') {
    $fechaFin = $fechaInicio;
}
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
    if ($mostrarJson && $fecha === '') {
        $result = $conexion->query('SELECT * FROM registros ORDER BY Id DESC');
        $registros = $result->fetch_all(MYSQLI_ASSOC);
    } elseif ($mostrarJson || ($fechaInicio !== '' && $fechaFin !== '')) {
        $valorFechaInicio = $mostrarJson ? $fecha : $fechaInicio;
        $valorFechaFin = $mostrarJson ? $fecha : $fechaFin;
        $fechaInicioValidada = DateTimeImmutable::createFromFormat('!Y-m-d', $valorFechaInicio);
        $fechaFinValidada = DateTimeImmutable::createFromFormat('!Y-m-d', $valorFechaFin);
        if (
            !$fechaInicioValidada || $fechaInicioValidada->format('Y-m-d') !== $valorFechaInicio
            || !$fechaFinValidada || $fechaFinValidada->format('Y-m-d') !== $valorFechaFin
        ) {
            throw new InvalidArgumentException('Selecciona un periodo válido.');
        }
        if ($fechaInicioValidada > $fechaFinValidada) {
            throw new InvalidArgumentException('El día de inicio debe ser anterior o igual al día de fin.');
        }

        $diasPeriodo = (int) $fechaInicioValidada->diff($fechaFinValidada)->format('%a') + 1;
        $duracionPeriodoSegundos = $diasPeriodo * 86400;
        $sql = $mostrarJson
            ? 'SELECT * FROM registros WHERE fecha = ? ORDER BY hora DESC, Id DESC'
            : 'SELECT * FROM registros WHERE fecha BETWEEN ? AND ? ORDER BY fecha ASC, hora ASC, Id ASC';
        $stmt = $conexion->prepare($sql);
        if ($mostrarJson) {
            $stmt->bind_param('s', $valorFechaInicio);
        } else {
            $stmt->bind_param('ss', $valorFechaInicio, $valorFechaFin);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $registros = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
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
$fechaInicioValidada = $fechaInicioValidada ?? null;
$duracionPeriodoSegundos = $duracionPeriodoSegundos ?? 86400;
$graficoYInicio = 12;
$graficoAlturaY = 201 * 1.5;
$graficoYFin = $graficoYInicio + $graficoAlturaY;
$textoPeriodo = $fechaInicio === $fechaFin ? $fechaInicio : $fechaInicio . ' al ' . $fechaFin;
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
        $fechaRegistro = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($registro['fecha'] ?? ''));
        if (!$fechaRegistro || !$fechaInicioValidada) {
            continue;
        }
        $diasDesdeInicio = (int) $fechaInicioValidada->diff($fechaRegistro)->format('%r%a');
        $segundosPeriodo = ($diasDesdeInicio * 86400) + $segundos;
        $punto = [
            'x' => 64 + ($segundosPeriodo / $duracionPeriodoSegundos * 912),
            'y' => $graficoYInicio + (1 - (float) $valor) * $graficoAlturaY,
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
$seriesConEncendidos = array_filter($series, static function ($serie) {
    return $serie['encendidos'] > 0;
});
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
            display: flex;
            flex-direction: column;
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
            padding: 38px 0 56px;
        }

        main > header { order: 0; }
        main > form { order: 2; }

        .history-results {
            order: 1;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 22px;
            border-bottom: 1px solid var(--line);
        }

        h1 {
            margin: 0;
            font-family: Georgia, serif;
            font-size: 30px;
            font-weight: 500;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            min-height: 42px;
            padding: 8px 14px;
            border: 1px solid var(--accent);
            border-radius: 4px;
            color: #fff;
            background: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .back-link:hover { background: var(--accent-dark); }

        .result-count, .empty-state, .error {
            color: var(--muted);
        }

        form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 12px;
            padding: 22px 0;
            border-bottom: 1px solid var(--line);
        }

        .date-fields {
            display: flex;
            flex: 0 1 360px;
            gap: 8px;
        }

        .date-fields .date-field {
            flex: 1 1 0;
            min-width: 0;
            gap: 4px;
            font-size: 12px;
        }

        .date-fields input {
            width: 100%;
            min-width: 0;
            min-height: 36px;
            padding: 5px 7px;
            font-size: 13px;
        }

        .selector-actions {
            display: flex;
            flex: 0 1 auto;
            align-items: stretch;
            gap: 8px;
            min-width: 0;
        }

        .selector-actions .data-selector {
            flex: 1 1 auto;
            width: auto;
        }

        .selector-actions .selector-trigger {
            width: 100%;
        }

        .selector-actions > button {
            flex: 0 0 auto;
        }

        .data-selector {
            position: relative;
            flex: 0 0 auto;
            min-width: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .selector-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 42px;
            padding: 8px 12px;
            border: 1px solid #bdcec5;
            border-radius: 4px;
            color: var(--ink);
            background: var(--surface);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            list-style: none;
        }

        .selector-trigger::-webkit-details-marker { display: none; }
        .selector-trigger::after { content: "+"; color: var(--accent); font-size: 20px; line-height: 1; }
        .data-selector[open] .selector-trigger::after { content: "−"; }

        .selector-popup {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            z-index: 10;
            display: none;
            width: min(360px, calc(100vw - 36px));
            max-height: min(360px, 55vh);
            overflow-y: auto;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 8px 24px rgba(24, 51, 51, 0.2);
        }

        .data-selector[open] .selector-popup { display: block; }

        .data-selector-options {
            min-width: 0;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .selector-popup legend {
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

        .chart-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 16px 12px;
            color: var(--muted);
            font-size: 12px;
        }

        .chart-controls p { margin: 0; }

        .chart-reset {
            min-height: 32px;
            padding: 5px 10px;
            font-size: 12px;
        }

        .chart {
            display: block;
            width: 100%;
            height: auto;
            touch-action: none;
            user-select: none;
            cursor: grab;
        }

        .chart:active { cursor: grabbing; }

        .grid-line { stroke: #e5ece8; stroke-width: 1; }
        .axis-line { stroke: #8ba09a; stroke-width: 1.2; }
        .axis-label { fill: #526864; font: 12px "Trebuchet MS", "Segoe UI", sans-serif; }
        .axis-x-tick { fill: #183333; font-size: 16px; font-weight: 700; }
        .axis-title { fill: #31504b; font: 700 13px "Trebuchet MS", "Segoe UI", sans-serif; }
        .data-line { fill: none; stroke-width: 1.2; stroke-linejoin: round; stroke-linecap: round; }
        .data-point { stroke: #fff; stroke-width: 1.5; }

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
            main {
                width: calc(100% - 24px);
                padding: 22px 0 36px;
            }

            header { padding-bottom: 16px; }
            h1 { font-size: 26px; }
            form {
                align-items: stretch;
                gap: 14px;
                padding: 18px 0;
            }

            .date-fields {
                flex: 1 1 100%;
                width: 100%;
                gap: 10px;
            }

            .date-fields .date-field {
                min-width: 0;
                font-size: 12px;
            }

            .date-fields input {
                min-height: 40px;
                font-size: 14px;
            }

            .selector-actions {
                flex: 1 1 100%;
                width: 100%;
            }

            form > button,
            .clear-link,
            .button-link {
                width: 100%;
            }

            .selector-actions .selector-trigger {
                min-height: 48px;
                padding: 8px;
                gap: 8px;
                font-size: 14px;
            }

            .selector-actions > button {
                width: auto;
                min-height: 48px;
                padding: 8px 10px;
                font-size: 13px;
            }

            .selector-popup {
                width: 100%;
                max-height: 50vh;
            }

            .checkbox-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .check-option {
                min-height: 44px;
                padding: 8px;
            }

            .check-option input {
                width: 18px;
                min-width: 18px;
                min-height: 18px;
            }

            form > button,
            .clear-link,
            .button-link {
                justify-content: center;
                min-height: 48px;
            }

            .result-count { line-height: 1.45; }

            .on-counts {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 5px;
                margin: 8px 0;
            }

            .on-count {
                min-width: 0;
                padding: 5px 6px;
            }

            .on-count-name {
                gap: 4px;
                font-size: 10px;
                white-space: nowrap;
            }

            .on-count-name .legend-swatch {
                width: 8px;
                height: 8px;
                flex: 0 0 8px;
            }

            .on-count-value {
                margin-top: 1px;
                font-size: 9px;
            }

            .chart-wrap {
                overflow: hidden;
                padding: 8px 8px 0;
            }

            .chart-controls { padding: 8px; }

            .chart { min-width: 0; }

        }
    </style>
</head>
<body>
    <main>
        <header>
            <h1>Historial de registros</h1>
            <a class="back-link" href="visor.php">Volver</a>
        </header>

        <div class="history-results">
        <?php if ($error !== null): ?>
            <p class="error"><?= escapar($error) ?></p>
        <?php elseif ($fechaInicio === '' || $fechaFin === ''): ?>
            <p class="empty-state">Elige un periodo para ver sus registros.</p>
        <?php elseif (empty($registros)): ?>
            <p class="empty-state">No hay registros entre el <?= escapar($textoPeriodo) ?>.</p>
        <?php elseif (empty($datosSeleccionados)): ?>
            <p class="empty-state">Selecciona al menos un dato para mostrar el gráfico.</p>
        <?php else: ?>
            <p class="result-count"><?= count($registros) ?> registro(s) entre el <?= escapar($textoPeriodo) ?></p>
            <section class="chart-card" aria-label="Gráfico de valores del periodo">
                <div class="chart-wrap">
                    <svg class="chart" viewBox="0 0 1000 350" data-start-date="<?= escapar($fechaInicio) ?>" data-duration-seconds="<?= escapar($duracionPeriodoSegundos) ?>" role="img" aria-labelledby="chart-title chart-description">
                        <title id="chart-title">Valores del <?= escapar($textoPeriodo) ?></title>
                        <desc id="chart-description">Gráfico de los datos seleccionados entre las fechas indicadas. El eje vertical va de 0 a 1.</desc>
                        <defs>
                            <clipPath id="chart-plot-clip">
                                <rect x="64" y="<?= escapar($graficoYInicio) ?>" width="912" height="<?= escapar($graficoAlturaY) ?>"></rect>
                            </clipPath>
                        </defs>
                        <?php foreach ([0, 1] as $tick):
                            $valorEje = $tick;
                            $y = $graficoYInicio + (1 - $valorEje) * $graficoAlturaY;
                        ?>
                            <line class="grid-line" x1="64" y1="<?= escapar($y) ?>" x2="976" y2="<?= escapar($y) ?>"></line>
                            <text class="axis-label" x="52" y="<?= escapar($y + 4) ?>" text-anchor="end"><?= $tick === 0 ? 'OFF' : 'ON' ?></text>
                        <?php endforeach; ?>
                        <g id="chart-x-ticks"></g>
                        <line class="axis-line" x1="64" y1="<?= escapar($graficoYFin) ?>" x2="976" y2="<?= escapar($graficoYFin) ?>"></line>
                        <line class="axis-line" x1="64" y1="<?= escapar($graficoYInicio) ?>" x2="64" y2="<?= escapar($graficoYFin) ?>"></line>
                        <text class="axis-title" id="chart-x-title" x="520" y="<?= escapar($graficoYFin + 28) ?>" text-anchor="middle"><?= $fechaInicio === $fechaFin ? 'Hora del día' : 'Fecha' ?></text>
                        <text class="axis-title" x="16" y="<?= escapar(($graficoYInicio + $graficoYFin) / 2) ?>" text-anchor="middle" transform="rotate(-90 16 <?= escapar(($graficoYInicio + $graficoYFin) / 2) ?>)">Estado</text>
                        <g clip-path="url(#chart-plot-clip)">
                            <g id="chart-data">
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
                            </g>
                        </g>
                    </svg>
                </div>
                <div class="chart-controls">
                    <p>Pellizca para ampliar el rango de tiempo; arrastra para explorar.</p>
                    <button class="chart-reset" id="chart-reset" type="button">Restablecer</button>
                </div>
            </section>
            <?php if ($seriesConEncendidos): ?>
                <div class="on-counts" aria-label="Veces que cada elemento pasó de OFF a ON">
                    <?php foreach ($seriesConEncendidos as $serie): ?>
                        <div class="on-count">
                            <span class="on-count-name">
                                <span class="legend-swatch" style="background-color: <?= escapar($serie['color']) ?>"></span>
                                <?= escapar($serie['nombre']) ?>
                            </span>
                            <strong class="on-count-value"><?= escapar($serie['encendidos']) ?> ON</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!array_filter($series, static function ($serie) { return !empty($serie['puntos']); })): ?>
                <p class="empty-state">No hay valores entre 0 y 1 para mostrar en el gráfico.</p>
            <?php endif; ?>
        <?php endif; ?>
        </div>

        <form method="get" action="ver_historial.php">
            <div class="date-fields">
                <label class="date-field" for="fecha_inicio">
                    Día de inicio
                    <input id="fecha_inicio" name="fecha_inicio" type="date" value="<?= escapar($fechaInicio) ?>" required>
                </label>
                <label class="date-field" for="fecha_fin">
                    Día de fin
                    <input id="fecha_fin" name="fecha_fin" type="date" value="<?= escapar($fechaFin) ?>" required>
                </label>
            </div>
            <div class="selector-actions">
                <details class="data-selector">
                    <summary class="selector-trigger">Elementos a mostrar (<?= count($datosSeleccionados) ?>)</summary>
                    <div class="selector-popup">
                        <fieldset class="data-selector-options">
                            <legend>Elige boyas y bombas</legend>
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
                    </div>
                </details>
                <button type="submit">Mostrar datos</button>
            </div>
            <a id="statistics-link" class="button-link" href="estadisticas.php?fecha_inicio=<?= escapar($fechaInicio) ?>&amp;fecha_fin=<?= escapar($fechaFin) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path class="chart-bars" d="M4 19.5h16M6.5 16v-4M11.5 16V7M16.5 16v-6"></path>
                    <path class="chart-trend" d="m5 9 5-4 5 2 4-4"></path>
                </svg>
                Estadisticas
            </a>
            <?php if ($fechaInicio !== ''): ?>
                <a class="clear-link" href="ver_historial.php">Limpiar</a>
            <?php endif; ?>
        </form>
    </main>
    <script>
        const statisticsLink = document.getElementById('statistics-link');
        statisticsLink.addEventListener('click', function () {
            const url = new URL(statisticsLink.href, window.location.href);
            url.searchParams.set('timestamp', Date.now());
            statisticsLink.href = url.toString();
        });

        const chart = document.querySelector('.chart');
        const resetChartButton = document.getElementById('chart-reset');
        if (chart && resetChartButton) {
            const plotStartX = 64;
            const plotWidth = 912;
            const totalDuration = Number(chart.dataset.durationSeconds);
            const origin = new Date(`${chart.dataset.startDate}T00:00:00Z`);
            const originTimestamp = origin.getTime();
            const tickGroup = document.getElementById('chart-x-ticks');
            const dataGroup = document.getElementById('chart-data');
            const axisTitle = document.getElementById('chart-x-title');
            let timeWindow = { start: 0, duration: totalDuration };
            let gesture = null;

            function chooseTickStep(windowDuration) {
                const labelWidth = totalDuration > 86400 && windowDuration < 86400 ? 110 : 75;
                const targetCount = Math.max(2, Math.floor(chart.clientWidth / labelWidth));
                const rawStep = windowDuration / targetCount;
                const magnitude = Math.pow(10, Math.floor(Math.log10(rawStep)));
                const normalized = rawStep / magnitude;
                const multiplier = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
                return multiplier * magnitude;
            }

            function formatTick(elapsedSeconds, step) {
                const date = new Date(originTimestamp + elapsedSeconds * 1000);
                const day = String(date.getUTCDate()).padStart(2, '0');
                const month = String(date.getUTCMonth() + 1).padStart(2, '0');
                if (step >= 86400) {
                    return `${day}/${month}`;
                }

                const hour = String(date.getUTCHours()).padStart(2, '0');
                const minute = String(date.getUTCMinutes()).padStart(2, '0');
                return totalDuration > 86400 ? `${day}/${month} ${hour}:${minute}` : `${hour}:${minute}`;
            }

            function fitChartText() {
                const bounds = chart.getBoundingClientRect();
                if (!bounds.width) {
                    return;
                }

                const userUnitsPerPixel = 1000 / bounds.width;
                const tickFontSize = 13 * userUnitsPerPixel;
                const titleFontSize = 14 * userUnitsPerPixel;
                chart.querySelectorAll('.axis-label').forEach(function (label) {
                    label.style.fontSize = `${tickFontSize}px`;
                });
                chart.querySelectorAll('.axis-title').forEach(function (title) {
                    title.style.fontSize = `${titleFontSize}px`;
                });
                const tickLabelY = 313.5 + tickFontSize * 1.15;
                tickGroup.querySelectorAll('.axis-x-tick').forEach(function (label) {
                    label.setAttribute('y', tickLabelY);
                });
                const axisTitleY = tickLabelY + tickFontSize * 1.15 + titleFontSize * 0.35;
                axisTitle.setAttribute('y', axisTitleY);
                chart.setAttribute('viewBox', `0 0 1000 ${Math.max(350, axisTitleY + titleFontSize * 0.7)}`);
            }

            function renderTimeAxis() {
                const windowEnd = timeWindow.start + timeWindow.duration;
                const step = chooseTickStep(timeWindow.duration);
                const firstTick = Math.ceil((timeWindow.start - 1e-7) / step) * step;
                const namespace = 'http://www.w3.org/2000/svg';
                axisTitle.textContent = totalDuration > 86400
                    ? (timeWindow.duration < 86400 ? 'Fecha y hora' : 'Fecha')
                    : 'Hora del día';
                tickGroup.replaceChildren();

                for (let elapsed = firstTick; elapsed <= windowEnd && tickGroup.childElementCount < 12; elapsed += step) {
                    const x = plotStartX + ((elapsed - timeWindow.start) / timeWindow.duration) * plotWidth;
                    const line = document.createElementNS(namespace, 'line');
                    line.setAttribute('class', 'grid-line');
                    line.setAttribute('x1', x);
                    line.setAttribute('y1', '12');
                    line.setAttribute('x2', x);
                    line.setAttribute('y2', '313.5');
                    tickGroup.appendChild(line);

                    const label = document.createElementNS(namespace, 'text');
                    label.setAttribute('class', 'axis-label axis-x-tick');
                    label.setAttribute('x', x);
                    label.setAttribute('text-anchor', 'middle');
                    label.textContent = formatTick(elapsed, step);
                    tickGroup.appendChild(label);
                }
                fitChartText();
            }

            function applyTimeWindow(start, duration) {
                timeWindow = {
                    start: Math.min(totalDuration - duration, Math.max(0, start)),
                    duration: duration
                };
                const scale = totalDuration / timeWindow.duration;
                const translateX = plotStartX - scale * plotStartX - (timeWindow.start / timeWindow.duration) * plotWidth;
                dataGroup.setAttribute('transform', `matrix(${scale} 0 0 1 ${translateX} 0)`);
                renderTimeAxis();
            }

            function chartPlotPosition(clientX) {
                const bounds = chart.getBoundingClientRect();
                return (((clientX - bounds.left) / bounds.width) * 1000 - plotStartX) / plotWidth;
            }

            function startPinch(touches) {
                const first = touches[0];
                const second = touches[1];
                const centerX = (first.clientX + second.clientX) / 2;
                const pinchDistance = Math.abs(second.clientX - first.clientX);
                const plotPosition = chartPlotPosition(centerX);
                gesture = {
                    type: 'pinch',
                    start: timeWindow.start,
                    duration: timeWindow.duration,
                    distance: pinchDistance,
                    anchor: timeWindow.start + plotPosition * timeWindow.duration
                };
            }

            function startPan(touch) {
                gesture = {
                    type: 'pan',
                    start: timeWindow.start,
                    duration: timeWindow.duration,
                    clientX: touch.clientX
                };
            }

            function updatePinch(touches) {
                const first = touches[0];
                const second = touches[1];
                const distance = Math.abs(second.clientX - first.clientX);
                if (!gesture || gesture.type !== 'pinch' || gesture.distance === 0) {
                    startPinch(touches);
                    return;
                }

                const duration = Math.min(
                    totalDuration,
                    Math.max(totalDuration / 8, gesture.duration * gesture.distance / distance)
                );
                const centerPosition = chartPlotPosition((first.clientX + second.clientX) / 2);
                applyTimeWindow(gesture.anchor - centerPosition * duration, duration);
            }

            function updatePan(touch) {
                if (!gesture || gesture.type !== 'pan') {
                    startPan(touch);
                    return;
                }
                const bounds = chart.getBoundingClientRect();
                const plotPixelWidth = bounds.width * plotWidth / 1000;
                const elapsedDelta = ((touch.clientX - gesture.clientX) / plotPixelWidth) * gesture.duration;
                applyTimeWindow(gesture.start - elapsedDelta, gesture.duration);
            }

            chart.addEventListener('touchstart', function (event) {
                if (event.touches.length >= 2) {
                    event.preventDefault();
                    startPinch(event.touches);
                } else if (timeWindow.duration < totalDuration) {
                    event.preventDefault();
                    startPan(event.touches[0]);
                }
            }, { passive: false });

            chart.addEventListener('touchmove', function (event) {
                if (event.touches.length >= 2) {
                    event.preventDefault();
                    updatePinch(event.touches);
                } else if (event.touches.length === 1 && timeWindow.duration < totalDuration) {
                    event.preventDefault();
                    updatePan(event.touches[0]);
                }
            }, { passive: false });

            chart.addEventListener('touchend', function (event) {
                if (event.touches.length === 1 && timeWindow.duration < totalDuration) {
                    startPan(event.touches[0]);
                } else if (event.touches.length === 0) {
                    gesture = null;
                }
            });

            chart.addEventListener('touchcancel', function () {
                gesture = null;
            });

            resetChartButton.addEventListener('click', function () {
                applyTimeWindow(0, totalDuration);
                gesture = null;
            });

            window.addEventListener('resize', renderTimeAxis);
            applyTimeWindow(0, totalDuration);
        }

        const fechaInicio = document.getElementById('fecha_inicio');
        const fechaFin = document.getElementById('fecha_fin');
        if (!fechaFin.value) {
            fechaFin.value = fechaInicio.value;
        }

        document.querySelectorAll('#fecha_inicio, #fecha_fin').forEach(function (selectorFecha) {
            selectorFecha.addEventListener('change', function () {
                if (this === fechaInicio) {
                    fechaFin.value = fechaInicio.value;
                } else if (!fechaFin.value) {
                    fechaFin.value = fechaInicio.value;
                }
                this.form.requestSubmit();
            });
        });
    </script>
</body>
</html>