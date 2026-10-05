<?php
/*
 - 05-10-2026 desde laptop
 - archivo: estadisticas.php
 - Estadísticas de registros de boyas y bombas.
 */
session_start();
date_default_timezone_set('America/Caracas');
include 'conexion.php';

$elementos = [
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
$unidades = [
    'horas' => 'horas',
    'dias' => 'días',
    'meses' => 'meses',
    'anos' => 'años',
];
$unidadesSingular = [
    'horas' => 'hora',
    'dias' => 'día',
    'meses' => 'mes',
    'anos' => 'año',
];
$unidadesDateTime = [
    'horas' => 'hours',
    'dias' => 'days',
    'meses' => 'months',
    'anos' => 'years',
];
$cantidad = $_GET['cantidad'] ?? '1';
$unidad = $_GET['unidad'] ?? 'dias';
$zonaHoraria = new DateTimeZone('America/Caracas');
$periodoSeleccionado = $_GET['periodo'] ?? (new DateTimeImmutable('today', $zonaHoraria))->format('Y-m-d');
$modoRangoFechas = isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']);
$fechaInicioRango = $_GET['fecha_inicio'] ?? '';
$fechaFinRango = $_GET['fecha_fin'] ?? '';
$error = null;
$estadisticas = [];
$duraciones = [];
$correlaciones = [];
$registrosAnalizados = 0;
$inicioPeriodo = null;
$finPeriodo = null;
$periodoEtiqueta = '';

if (!is_string($cantidad) || !preg_match('/^\d+$/', $cantidad) || (int) $cantidad < 1 || (int) $cantidad > 1000) {
    $error = 'La cantidad debe ser un número entre 1 y 1000.';
    $cantidad = '1';
}
if (!is_string($unidad) || !isset($unidades[$unidad])) {
    $error = 'Selecciona una unidad de tiempo válida.';
    $unidad = 'dias';
}

if (!is_string($periodoSeleccionado)) {
    $error = 'Selecciona un periodo válido.';
    $periodoSeleccionado = '';
}

if (!$error && $modoRangoFechas) {
    if (
        !is_string($fechaInicioRango) || !is_string($fechaFinRango)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicioRango)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinRango)
    ) {
        $error = 'Selecciona un rango de fechas válido.';
    } else {
        $inicioRango = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaInicioRango, $zonaHoraria);
        $finRango = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaFinRango, $zonaHoraria);
        if (
            !$inicioRango || $inicioRango->format('Y-m-d') !== $fechaInicioRango
            || !$finRango || $finRango->format('Y-m-d') !== $fechaFinRango
            || $inicioRango > $finRango
        ) {
            $error = 'Selecciona un rango de fechas válido.';
        } else {
            $inicioPeriodo = $inicioRango->setTime(0, 0, 0);
            $finPeriodo = $finRango->setTime(23, 59, 59);
            $periodoEtiqueta = $fechaInicioRango === $fechaFinRango
                ? $inicioRango->format('d/m/Y')
                : $inicioRango->format('d/m/Y') . ' — ' . $finRango->format('d/m/Y');
        }
    }
} elseif (!$error) {
    if ($unidad === 'meses') {
        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $periodoSeleccionado, $partesPeriodo)) {
            $error = 'Selecciona un mes válido.';
        } else {
            $anoSeleccionado = (int) $partesPeriodo[1];
            $mesSeleccionado = (int) $partesPeriodo[2];
            $primerDiaSeleccionado = DateTimeImmutable::createFromFormat('!Y-n-j', $anoSeleccionado . '-' . $mesSeleccionado . '-1', $zonaHoraria);
            $mesAbsoluto = ($anoSeleccionado * 12) + $mesSeleccionado - 1 - ((int) $cantidad - 1);
            $anoInicio = intdiv($mesAbsoluto, 12);
            $mesInicio = ($mesAbsoluto % 12) + 1;
            if ($anoInicio < 1000 || !$primerDiaSeleccionado) {
                $error = 'El rango seleccionado está fuera de las fechas disponibles.';
            } else {
                $inicioPeriodo = DateTimeImmutable::createFromFormat('!Y-n-j', $anoInicio . '-' . $mesInicio . '-1', $zonaHoraria);
                $finPeriodo = $primerDiaSeleccionado->modify('last day of this month')->setTime(23, 59, 59);
                $periodoEtiqueta = $primerDiaSeleccionado->format('m/Y');
            }
        }
    } elseif ($unidad === 'anos') {
        if (!preg_match('/^\d{4}$/', $periodoSeleccionado) || (int) $periodoSeleccionado < 1000) {
            $error = 'Selecciona un año válido.';
        } else {
            $anoSeleccionado = (int) $periodoSeleccionado;
            $anoInicio = $anoSeleccionado - ((int) $cantidad - 1);
            if ($anoInicio < 1000) {
                $error = 'El rango seleccionado está fuera de las fechas disponibles.';
            } else {
                $inicioPeriodo = DateTimeImmutable::createFromFormat('!Y-n-j', $anoInicio . '-1-1', $zonaHoraria);
                $finPeriodo = DateTimeImmutable::createFromFormat('!Y-n-j', $anoSeleccionado . '-12-31', $zonaHoraria)->setTime(23, 59, 59);
                $periodoEtiqueta = (string) $anoSeleccionado;
            }
        }
    } else {
        $fechaSeleccionada = DateTimeImmutable::createFromFormat('!Y-m-d', $periodoSeleccionado, $zonaHoraria);
        if (!$fechaSeleccionada || $fechaSeleccionada->format('Y-m-d') !== $periodoSeleccionado) {
            $error = 'Selecciona una fecha válida.';
        } else {
            $finPeriodo = $fechaSeleccionada->setTime(23, 59, 59);
            if ($unidad === 'dias') {
                $inicioPeriodo = $fechaSeleccionada->modify('-' . ((int) $cantidad - 1) . ' days')->setTime(0, 0, 0);
                $periodoEtiqueta = $fechaSeleccionada->format('d/m/Y');
            } else {
                $inicioPeriodo = $finPeriodo->modify('-' . (int) $cantidad . ' hours');
                $periodoEtiqueta = $fechaSeleccionada->format('d/m/Y');
            }
        }
    }
    if (!$error && !$inicioPeriodo) {
        $error = 'No se pudo calcular el periodo seleccionado.';
    }
}

if (!$error && !$conexion) {
    $error = 'No se pudo conectar con la base de datos: ' . mysqli_connect_error();
}

if (!$error) {
    $sql = 'SELECT COUNT(*) AS total FROM registros'
        . ' WHERE TIMESTAMP(fecha, hora) >= ? AND TIMESTAMP(fecha, hora) <= ?';
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        $error = 'No se pudo preparar la consulta de estadísticas: ' . $conexion->error;
    } else {
        $desde = $inicioPeriodo->format('Y-m-d H:i:s');
        $hasta = $finPeriodo->format('Y-m-d H:i:s');
        $stmt->bind_param('ss', $desde, $hasta);
        if (!$stmt->execute()) {
            $error = 'No se pudieron consultar las estadísticas: ' . $stmt->error;
        } else {
            $resultado = $stmt->get_result()->fetch_assoc();
            $registrosAnalizados = (int) ($resultado['total'] ?? 0);
        }
        $stmt->close();
    }

    if (!$error) {
        $columnas = implode(', ', array_map(static function ($columna) {
            return '`' . $columna . '`';
        }, array_values($elementos)));
        $sqlDuraciones = 'SELECT fecha, hora, ' . $columnas
            . ' FROM registros WHERE TIMESTAMP(fecha, hora) >= ? AND TIMESTAMP(fecha, hora) <= ?'
            . ' ORDER BY fecha ASC, hora ASC, Id ASC';
        $stmtDuraciones = $conexion->prepare($sqlDuraciones);
        if (!$stmtDuraciones) {
            $error = 'No se pudieron preparar las duraciones ON: ' . $conexion->error;
        } else {
            $stmtDuraciones->bind_param('ss', $desde, $hasta);
            if (!$stmtDuraciones->execute()) {
                $error = 'No se pudieron consultar las duraciones ON: ' . $stmtDuraciones->error;
            } else {
                $resultadoDuraciones = $stmtDuraciones->get_result();
                $estadoAnterior = array_fill_keys(array_values($elementos), null);
                $inicioConexion = array_fill_keys(array_values($elementos), null);
                $duraciones = array_fill_keys(array_values($elementos), []);
                $intervalos = array_fill_keys(array_values($elementos), []);
                $boyas = array_slice($elementos, 0, 7, true);
                $bombas = array_slice($elementos, 7, null, true);
                $conteosPares = [];
                foreach ($boyas as $nombreBoya => $columnaBoya) {
                    foreach ($bombas as $nombreBomba => $columnaBomba) {
                        $conteosPares[$nombreBoya][$nombreBomba] = [0, 0, 0, 0];
                    }
                }

                while ($registro = $resultadoDuraciones->fetch_assoc()) {
                    $marcaTiempo = strtotime($registro['fecha'] . ' ' . $registro['hora']);
                    if ($marcaTiempo === false) {
                        foreach ($elementos as $columna) {
                            $estadoAnterior[$columna] = null;
                            $inicioConexion[$columna] = null;
                        }
                        continue;
                    }

                    foreach ($elementos as $columna) {
                        $estado = (int) $registro[$columna];
                        if ($estado !== 0 && $estado !== 1) {
                            $estadoAnterior[$columna] = null;
                            $inicioConexion[$columna] = null;
                            continue;
                        }

                        if ($estado === 1 && $estadoAnterior[$columna] === 0) {
                            $inicioConexion[$columna] = $marcaTiempo;
                        } elseif ($estado === 0 && $inicioConexion[$columna] !== null) {
                            $marcaInicio = $inicioConexion[$columna];
                            $duraciones[$columna][] = ($marcaTiempo - $marcaInicio) / 60;
                            $intervalos[$columna][] = [
                                'inicio' => $marcaInicio,
                                'fin' => $marcaTiempo,
                                'minutos' => ($marcaTiempo - $marcaInicio) / 60,
                            ];
                            $inicioConexion[$columna] = null;
                        }

                        $estadoAnterior[$columna] = $estado;
                    }

                    foreach ($boyas as $nombreBoya => $columnaBoya) {
                        $estadoBoya = (int) $registro[$columnaBoya];
                        if ($estadoBoya !== 0 && $estadoBoya !== 1) {
                            continue;
                        }
                        foreach ($bombas as $nombreBomba => $columnaBomba) {
                            $estadoBomba = (int) $registro[$columnaBomba];
                            if ($estadoBomba !== 0 && $estadoBomba !== 1) {
                                continue;
                            }
                            $indicePar = ($estadoBoya * 2) + $estadoBomba;
                            $conteosPares[$nombreBoya][$nombreBomba][$indicePar]++;
                        }
                    }
                }

                foreach ($elementos as $nombre => $columna) {
                    $estadisticas[$nombre] = count($duraciones[$columna]);
                }

                foreach ($conteosPares as $nombreBoya => $paresBomba) {
                    foreach ($paresBomba as $nombreBomba => $tabla) {
                        [$n00, $n01, $n10, $n11] = $tabla;
                        $denominador = sqrt(($n10 + $n11) * ($n00 + $n01) * ($n01 + $n11) * ($n00 + $n10));
                        $correlaciones[$nombreBoya][$nombreBomba] = [
                            'muestras' => array_sum($tabla),
                            'phi' => $denominador > 0 ? (($n11 * $n00) - ($n10 * $n01)) / $denominador : null,
                        ];
                    }
                }
            }
            $stmtDuraciones->close();
        }
    }
}

if ($conexion) {
    $conexion->close();
}

$maximo = $estadisticas ? max($estadisticas) : 0;
$tipoCampoPeriodo = [
    'horas' => 'date',
    'dias' => 'date',
    'meses' => 'month',
    'anos' => 'number',
][$unidad];
$etiquetaCampoPeriodo = [
    'horas' => 'Día de referencia',
    'dias' => 'Día a contabilizar',
    'meses' => 'Mes a contabilizar',
    'anos' => 'Año a contabilizar',
][$unidad];
if ($unidad === 'meses' && preg_match('/^\d{4}-\d{2}$/', $periodoSeleccionado)) {
    $valorCampoPeriodo = $periodoSeleccionado;
} elseif ($unidad === 'anos' && preg_match('/^\d{4}$/', $periodoSeleccionado)) {
    $valorCampoPeriodo = $periodoSeleccionado;
} elseif (($unidad === 'horas' || $unidad === 'dias') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodoSeleccionado)) {
    $valorCampoPeriodo = $periodoSeleccionado;
} else {
    $hoy = new DateTimeImmutable('today', $zonaHoraria);
    $valorCampoPeriodo = $unidad === 'meses'
        ? $hoy->format('Y-m')
        : ($unidad === 'anos' ? $hoy->format('Y') : $hoy->format('Y-m-d'));
}
$urlHistorial = 'ver_historial.php';
if ($inicioPeriodo && $finPeriodo) {
    $urlHistorial .= '?' . http_build_query([
        'fecha_inicio' => $inicioPeriodo->format('Y-m-d'),
        'fecha_fin' => $finPeriodo->format('Y-m-d'),
    ]);
}

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatearDuracion(float $minutos): string
{
    if ($minutos <= 60) {
        return number_format($minutos, 1, ',', '.') . ' min';
    }

    $decimasTotales = (int) round($minutos * 10);
    $horas = intdiv($decimasTotales, 600);
    $minutosRestantes = ($decimasTotales % 600) / 10;
    return $horas . ' h ' . number_format($minutosRestantes, 1, ',', '.') . ' min';
}

function responderJson(int $estado, array $contenido): void
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_GET['analisis_local'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $entrada = json_decode(file_get_contents('php://input'), true);
    $tokenEnviado = is_array($entrada) ? ($entrada['csrf'] ?? '') : '';
    if (!is_string($tokenEnviado) || empty($_SESSION['estadisticas_local_csrf'])
        || !hash_equals($_SESSION['estadisticas_local_csrf'], $tokenEnviado)) {
        responderJson(403, ['error' => 'La sesión venció. Recarga la página e inténtalo de nuevo.']);
    }
    session_write_close();

    if ($error !== null) {
        responderJson(422, ['error' => $error]);
    }

    $lineasAnalisis = [
        'Periodo: ' . $inicioPeriodo->format('d/m/Y H:i') . ' a ' . $finPeriodo->format('d/m/Y H:i')
            . '; ' . $registrosAnalizados . ' registro(s).',
        '',
        'Intervalos completos ON→OFF:',
    ];
    $elementosConIntervalos = [];
    foreach ($elementos as $nombre => $columna) {
        $tiempos = $duraciones[$columna] ?? [];
        if ($tiempos) {
            $elementosConIntervalos[] = [
                'nombre' => $nombre,
                'cantidad' => count($tiempos),
                'minimo' => min($tiempos),
                'maximo' => max($tiempos),
            ];
        }
    }
    usort($elementosConIntervalos, static function ($a, $b) {
        return $b['cantidad'] <=> $a['cantidad'];
    });
    if (!$elementosConIntervalos) {
        $lineasAnalisis[] = 'No se detectaron intervalos completos en el periodo.';
    } else {
        foreach ($elementosConIntervalos as $elemento) {
            $lineasAnalisis[] = sprintf(
                '- %s: %d ON; duración observada entre (%s) y (%s).',
                $elemento['nombre'],
                $elemento['cantidad'],
                formatearDuracion($elemento['minimo']),
                formatearDuracion($elemento['maximo'])
            );
        }
    }

    $lineasAnalisis[] = '';
    $lineasAnalisis[] = 'Duraciones de activación de las bombas:';
    $bombasConIntervalos = array_filter($elementosConIntervalos, static function ($elemento) {
        return strpos($elemento['nombre'], 'Bomba ') === 0;
    });
    if (!$bombasConIntervalos) {
        $lineasAnalisis[] = 'No se detectaron activaciones completas de bombas en el periodo.';
    } else {
        foreach ($bombasConIntervalos as $bomba) {
            $lineasAnalisis[] = sprintf(
                '- %s: mínimo %s y máximo %s.',
                $bomba['nombre'],
                formatearDuracion($bomba['minimo']),
                formatearDuracion($bomba['maximo'])
            );
            if ($bomba['maximo'] > 60) {
                $lineasAnalisis[] = '  Advertencia: el máximo supera 60 minutos; es un encendido prolongado que puede aumentar el desgaste y afectar la vida útil de la bomba. Verifica las especificaciones del fabricante; este dato por sí solo no confirma un daño.';
            } else {
                $lineasAnalisis[] = '  El máximo observado no supera 60 minutos.';
            }
        }
    }

    $paresCorrelacion = [];
    foreach ($correlaciones as $nombreBoya => $bombasCorrelacion) {
        foreach ($bombasCorrelacion as $nombreBomba => $correlacion) {
            if ($correlacion['phi'] !== null) {
                $paresCorrelacion[] = [
                    'boya' => $nombreBoya,
                    'bomba' => $nombreBomba,
                    'phi' => $correlacion['phi'],
                    'muestras' => $correlacion['muestras'],
                ];
            }
        }
    }
    usort($paresCorrelacion, static function ($a, $b) {
        return abs($b['phi']) <=> abs($a['phi']);
    });

    $lineasAnalisis[] = '';
    $lineasAnalisis[] = 'Asociación entre estados simultáneos de boyas y bombas (coeficiente phi):';
    if (!$paresCorrelacion) {
        $lineasAnalisis[] = 'No hay suficientes muestras binarias variables para calcular correlaciones.';
    } else {
        foreach (array_slice($paresCorrelacion, 0, 5) as $par) {
            $fuerza = abs($par['phi']) >= 0.7 ? 'fuerte' : (abs($par['phi']) >= 0.4 ? 'moderada' : 'débil');
            $direccion = $par['phi'] >= 0 ? 'positiva (tienden a coincidir)' : 'negativa (tienden a estados opuestos)';
            $advertenciaMuestra = $par['muestras'] < 30 ? ' [muestra pequeña]' : '';
            $lineasAnalisis[] = sprintf(
                '- %s y %s: asociación %s, %s (phi = %+.2f; %d muestras válidas).%s',
                $par['boya'],
                $par['bomba'],
                $fuerza,
                $direccion,
                $par['phi'],
                $par['muestras'],
                $advertenciaMuestra
            );
        }
    }

    $lineasAnalisis[] = '';
    $lineasAnalisis[] = 'Interpretación: phi va de -1 a +1; cerca de cero indica poca asociación lineal entre estados binarios simultáneos. Los valores no binarios se excluyeron. Correlación no implica causalidad; los intervalos ON→OFF se cuentan aparte.';
    responderJson(200, ['lineas' => $lineasAnalisis]);
}

if (empty($_SESSION['estadisticas_local_csrf'])) {
    $_SESSION['estadisticas_local_csrf'] = bin2hex(random_bytes(32));
}
$tokenAnalisisIA = $_SESSION['estadisticas_local_csrf'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estadísticas de estados ON</title>
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
            width: min(100% - 36px, 980px);
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

        .subtitle, .period-summary, .empty-state { color: var(--muted); }
        .subtitle { margin: 8px 0 0; }

        form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 12px;
            padding: 22px 0;
            border-bottom: 1px solid var(--line);
        }

        .date-range-form {
            flex-wrap: nowrap;
        }

        .date-range-form > label {
            flex: 1 1 0;
            min-width: 0;
        }

        .date-range-form input[type="date"] {
            width: 100%;
            min-width: 0;
        }

        .date-range-form > button {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        label {
            display: grid;
            gap: 7px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        input, select, button, .back-link {
            min-height: 42px;
            border: 1px solid #bdcec5;
            border-radius: 4px;
            font: inherit;
        }

        input, select {
            padding: 8px 10px;
            color: var(--ink);
            background: var(--surface);
        }

        input { width: 120px; }
        input[type="date"], input[type="month"] { width: 190px; }

        button, .back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-color: var(--accent);
            color: #fff;
            background: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        button { cursor: pointer; }
        button:hover, .back-link:hover { background: var(--accent-dark); }
        button:disabled { opacity: .65; cursor: wait; }

        .ai-actions { margin: 20px 0 12px; }
        .ai-button { background: #4055a5; border-color: #4055a5; }
        .ai-button:hover { background: #304386; }
        .ai-note { margin: 7px 0 0; color: var(--muted); font-size: 12px; }
        .ai-analysis {
            margin: 14px 0 20px;
            padding: 16px;
            border: 1px solid #cbd4f0;
            border-radius: 5px;
            background: #f7f8fe;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .ai-analysis[hidden] { display: none; }
        .ai-analysis.error { border-color: #e7b6b6; color: #a23434; background: #fff5f5; }
        .analysis-warning { color: #b42318; font-weight: 700; }

        .period-summary { margin: 20px 0 12px; font-size: 14px; }
        .error { padding: 14px; border: 1px solid #e7b6b6; border-radius: 4px; color: #a23434; background: #fff5f5; }
        .empty-state { padding: 18px 0; }

        .stats-list {
            display: grid;
            gap: 12px;
        }

        .stat-row {
            display: grid;
            grid-template-columns: 110px minmax(80px, 1fr) 74px;
            align-items: center;
            gap: 14px;
            padding: 13px 16px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: var(--surface);
        }

        .stat-name { font-weight: 700; }

        .duration-summary {
            grid-column: 1 / -1;
            margin-top: -5px;
            color: var(--muted);
            font-size: 12px;
        }

        .activation-list {
            grid-column: 1 / -1;
            display: grid;
            gap: 5px;
            margin: -5px 0 0;
            padding: 0;
            color: var(--muted);
            font-size: 13px;
            list-style: none;
        }

        .activation-list li {
            padding: 7px 9px;
            border-radius: 4px;
            background: #f3f7f4;
        }

        .activation-list .activation-date {
            padding: 4px 0 0;
            color: var(--ink);
            background: transparent;
            font-weight: 700;
        }

        .duration-minimum {
            color: #b42318;
            font-weight: 700;
        }

        .bar-track {
            height: 14px;
            overflow: hidden;
            border-radius: 999px;
            background: #e9f0ec;
        }

        .bar-fill {
            height: 100%;
            min-width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #147a64, #62bb8e);
        }

        .stat-value { text-align: right; white-space: nowrap; }
        .footer { margin-top: 22px; }

        @media (max-width: 560px) {
            main { width: min(100% - 24px, 980px); padding-top: 26px; }
            h1 { font-size: 26px; }
            form > label, form > button { width: 100%; }
            input, select { width: 100%; }
            .date-range-form { gap: 6px; }
            .date-range-form > label { font-size: 11px; }
            .date-range-form input[type="date"] { padding: 6px 2px; font-size: 12px; }
            .date-range-form > button { width: auto; padding: 8px; font-size: 12px; }
            .stat-row { grid-template-columns: 82px minmax(50px, 1fr) 66px; gap: 9px; padding: 12px 10px; }
        }
    </style>
</head>
<body>
    <main>
        <header>
            <h1>Estadísticas</h1>
            <p class="subtitle">Cantidad de intervalos completos: una secuencia ON que luego llega a OFF.</p>
        </header>

        <form method="get" action="estadisticas.php" class="<?= $modoRangoFechas ? 'date-range-form' : '' ?>">
            <?php if ($modoRangoFechas): ?>
                <label for="fecha_inicio">
                    Día de inicio
                    <input id="fecha_inicio" name="fecha_inicio" type="date" value="<?= escapar($fechaInicioRango) ?>" required>
                </label>
                <label for="fecha_fin">
                    Día de fin
                    <input id="fecha_fin" name="fecha_fin" type="date" value="<?= escapar($fechaFinRango) ?>" required>
                </label>
            <?php else: ?>
                <label for="cantidad">
                    Periodo
                    <input id="cantidad" name="cantidad" type="number" min="1" max="1000" value="<?= escapar($cantidad) ?>" required>
            </label>
            <label for="unidad">
                Unidad de tiempo
                <select id="unidad" name="unidad">
                    <?php foreach ($unidades as $valor => $etiqueta): ?>
                        <option value="<?= escapar($valor) ?>" <?= $unidad === $valor ? 'selected' : '' ?>><?= escapar(ucfirst($etiqueta)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label id="periodo-label" for="periodo">
                <?= escapar($etiquetaCampoPeriodo) ?>
                <input id="periodo" name="periodo" type="<?= escapar($tipoCampoPeriodo) ?>" value="<?= escapar($valorCampoPeriodo) ?>" <?= $unidad === 'anos' ? 'min="1000" max="9999"' : '' ?> required>
            </label>
            <?php endif; ?>
            <button type="submit"><?= $modoRangoFechas ? 'Mostrar' : 'Mostrar estadísticas' ?></button>
        </form>

        <?php if ($error !== null): ?>
            <p class="error"><?= escapar($error) ?></p>
        <?php else: ?>
            <p class="period-summary">
                <?php if ($modoRangoFechas): ?>
                    Rango seleccionado (<?= escapar($periodoEtiqueta) ?>):
                <?php else: ?>
                    Periodo de <?= escapar($cantidad) ?> <?= escapar((int) $cantidad === 1 ? $unidadesSingular[$unidad] : $unidades[$unidad]) ?> seleccionado (<?= escapar($periodoEtiqueta) ?>):
                <?php endif; ?>
                <?= escapar($inicioPeriodo->format('d/m/Y H:i')) ?> — <?= escapar($finPeriodo->format('d/m/Y H:i')) ?>.
                <?= escapar($registrosAnalizados) ?> registro(s) analizado(s).
            </p>
            <?php if ($registrosAnalizados > 0): ?>
                <div class="ai-actions">
                    <button type="button" class="ai-button" id="analizar-local" data-csrf="<?= escapar($tokenAnalisisIA) ?>">Análisis de correlación</button>
                </div>
                <section class="ai-analysis" id="resultado-analisis" aria-live="polite" hidden></section>
            <?php endif; ?>
            <?php if ($registrosAnalizados === 0): ?>
                <p class="empty-state">No hay registros dentro del periodo seleccionado.</p>
            <?php elseif (!array_filter($estadisticas, static function ($conteo) { return $conteo > 0; })): ?>
                <p class="empty-state">No hay elementos con activaciones completas ON a OFF en este periodo.</p>
            <?php else: ?>
                <section class="stats-list" aria-label="Conteo de intervalos completos ON a OFF">
                    <?php foreach ($estadisticas as $nombre => $conteo):
                        if ($conteo === 0) {
                            continue;
                        }
                        $ancho = $maximo > 0 ? ($conteo / $maximo * 100) : 0;
                    ?>
                        <div class="stat-row">
                            <span class="stat-name"><?= escapar($nombre) ?></span>
                            <div class="bar-track" role="img" aria-label="<?= escapar($nombre) ?>: <?= escapar($conteo) ?> activación(es) completa(s) ON a OFF">
                                <div class="bar-fill" style="width: <?= escapar(number_format($ancho, 2, '.', '')) ?>%"></div>
                            </div>
                            <strong class="stat-value"><?= escapar($conteo) ?> ON</strong>
                            <?php
                                $intervalosElemento = $intervalos[$elementos[$nombre]] ?? [];
                                if ($intervalosElemento):
                                    $fechaIntervaloAnterior = null;
                            ?>
                                <ul class="activation-list" aria-label="Periodos de activación de <?= escapar($nombre) ?>">
                                    <?php foreach ($intervalosElemento as $indiceIntervalo => $intervalo): ?>
                                        <?php $fechaIntervalo = date('Y-m-d', $intervalo['inicio']); ?>
                                        <?php if ($fechaIntervalo !== $fechaIntervaloAnterior): ?>
                                            <li class="activation-date"><time datetime="<?= escapar($fechaIntervalo) ?>"><?= escapar(date('d/m/Y', $intervalo['inicio'])) ?></time></li>
                                            <?php $fechaIntervaloAnterior = $fechaIntervalo; ?>
                                        <?php endif; ?>
                                        <li>
                                            ON<?= escapar($indiceIntervalo + 1) ?> <?= escapar(date('G:i', $intervalo['inicio'])) ?>
                                            OFF<?= escapar($indiceIntervalo + 1) ?> <?= escapar(date('G:i', $intervalo['fin'])) ?>
                                            duración <?= escapar(formatearDuracion($intervalo['minutos'])) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <small class="duration-summary">Sin intervalos completos ON→OFF en este periodo.</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <p class="footer"><a class="back-link" href="<?= escapar($urlHistorial) ?>">Volver al historial</a></p>
    </main>
    <script>
        const botonAnalisisLocal = document.getElementById('analizar-local');
        if (botonAnalisisLocal) {
            botonAnalisisLocal.addEventListener('click', async () => {
                const resultadoAnalisis = document.getElementById('resultado-analisis');
                botonAnalisisLocal.disabled = true;
                botonAnalisisLocal.textContent = 'Analizando...';
                resultadoAnalisis.hidden = false;
                resultadoAnalisis.classList.remove('error');
                resultadoAnalisis.textContent = 'Calculando el resumen estadístico...';

                try {
                    const parametros = new URLSearchParams(window.location.search);
                    parametros.set('analisis_local', '1');
                    const respuesta = await fetch(`${window.location.pathname}?${parametros.toString()}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ csrf: botonAnalisisLocal.dataset.csrf })
                    });
                    const datos = await respuesta.json();
                    if (!respuesta.ok) {
                        throw new Error(datos.error || 'No se pudo generar el análisis.');
                    }
                    if (!Array.isArray(datos.lineas)) {
                        throw new Error('El análisis devuelto no tiene un formato válido.');
                    }
                    resultadoAnalisis.replaceChildren();
                    datos.lineas.forEach((linea, indice) => {
                        const textoLinea = linea.trimStart();
                        if (textoLinea.endsWith(':')) {
                            const titulo = document.createElement('strong');
                            titulo.textContent = linea;
                            resultadoAnalisis.appendChild(titulo);
                        } else if (/^\s*-\s*[^:]+:/.test(linea)) {
                            const coincidencia = linea.match(/^(\s*-\s*)([^:]+:)(.*)$/);
                            resultadoAnalisis.appendChild(document.createTextNode(coincidencia[1]));
                            const nombreElemento = document.createElement('strong');
                            nombreElemento.textContent = coincidencia[2];
                            resultadoAnalisis.appendChild(nombreElemento);
                            resultadoAnalisis.appendChild(document.createTextNode(coincidencia[3]));
                        } else if (textoLinea.startsWith('Advertencia:')) {
                            const advertencia = document.createElement('span');
                            advertencia.className = 'analysis-warning';
                            advertencia.textContent = linea;
                            resultadoAnalisis.appendChild(advertencia);
                        } else if (textoLinea.startsWith('Interpretación:')) {
                            const etiquetaInterpretacion = document.createElement('strong');
                            etiquetaInterpretacion.textContent = 'Interpretación:';
                            resultadoAnalisis.appendChild(etiquetaInterpretacion);
                            resultadoAnalisis.appendChild(document.createTextNode(textoLinea.slice('Interpretación:'.length)));
                        } else {
                            resultadoAnalisis.appendChild(document.createTextNode(linea));
                        }
                        if (indice < datos.lineas.length - 1) {
                            resultadoAnalisis.appendChild(document.createElement('br'));
                        }
                    });
                } catch (error) {
                    resultadoAnalisis.classList.add('error');
                    resultadoAnalisis.textContent = error instanceof Error
                        ? error.message
                        : 'No se pudo generar el análisis.';
                } finally {
                    botonAnalisisLocal.disabled = false;
                    botonAnalisisLocal.textContent = 'Análisis de correlación';
                }
            });
        }

        const unidadPeriodo = document.getElementById('unidad');
        const campoPeriodo = document.getElementById('periodo');
        const etiquetaPeriodo = document.querySelector('label[for="periodo"]');
        const etiquetasPeriodo = {
            horas: ['date', 'Día de referencia'],
            dias: ['date', 'Día a contabilizar'],
            meses: ['month', 'Mes a contabilizar'],
            anos: ['number', 'Año a contabilizar']
        };

        if (unidadPeriodo && campoPeriodo && etiquetaPeriodo) {
            unidadPeriodo.addEventListener('change', () => {
                const valorAnterior = campoPeriodo.value;
                const tipoAnterior = campoPeriodo.type;
                const [tipoNuevo, etiquetaNueva] = etiquetasPeriodo[unidadPeriodo.value];
                let valorNuevo = valorAnterior;

                if (tipoNuevo !== tipoAnterior) {
                    if (tipoNuevo === 'month') {
                        valorNuevo = tipoAnterior === 'number'
                            ? valorAnterior + '-01'
                            : valorAnterior.slice(0, 7);
                    } else if (tipoNuevo === 'number') {
                        valorNuevo = valorAnterior.slice(0, 4);
                    } else if (tipoAnterior === 'month') {
                        valorNuevo = valorAnterior + '-01';
                    } else if (tipoAnterior === 'number') {
                        valorNuevo = valorAnterior + '-01-01';
                    }
                    campoPeriodo.value = '';
                    campoPeriodo.type = tipoNuevo;
                }

                etiquetaPeriodo.firstChild.textContent = etiquetaNueva;
                campoPeriodo.min = tipoNuevo === 'number' ? '1000' : '';
                campoPeriodo.max = tipoNuevo === 'number' ? '9999' : '';
                campoPeriodo.value = valorNuevo;
            });
        }
    </script>
</body>
</html>