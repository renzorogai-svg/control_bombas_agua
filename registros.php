<?php
/* registros.php
06-08-2026
- genera un JSON con todos los registros de la tabla "registros" agrupados por fecha, mostrando la hora y el estado de cada boya y motor en formato "On"/"Off".
- filtra por fecha si se proporciona el parámetro 'fecha' en la URL.
- filtra por cantidad de registros si se proporciona el parámetro 'cantidad' en la URL.
- filtra por nombre de dato si se proporciona el parámetro 'tag' (ST-S01..ST-S07, ST-B01..ST-B07).
*/

header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization"); 
include 'conexion.php';

$fechaFiltrada = isset($_GET['fecha']) ? $_GET['fecha'] : null;
$cantidad = isset($_GET['cantidad']) ? $_GET['cantidad'] : null;
$tagFiltrado = isset($_GET['tag']) ? trim($_GET['tag']) : null;

$datosSolicitados = null;
if ($tagFiltrado !== null && $tagFiltrado !== '') {
    $tokens = array_filter(array_map('trim', explode(',', strtoupper($tagFiltrado))));
    $permitidos = [];

    for ($i = 1; $i <= 7; $i++) {
        $permitidos[] = sprintf('ST-S%02d', $i);
        $permitidos[] = sprintf('ST-B%02d', $i);
    }

    $datosSolicitados = array_values(array_intersect($tokens, $permitidos));

    if (empty($datosSolicitados)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => "El parámetro 'tag' no es válido. Usa ST-S01..ST-S07 o ST-B01..ST-B07."
        ]);
        $conexion->close();
        exit;
    }
}


try {
    // Tomar todos los registros de la tabla ordenados por fecha y hora
    $sql = "SELECT * FROM registros";

    if ($fechaFiltrada) {
        $fechaFiltrada = $conexion->real_escape_string($fechaFiltrada);
        $sql .= " WHERE fecha = '$fechaFiltrada'";
    }

    $sql .= " ORDER BY fecha DESC, hora DESC";

    if ($cantidad !== null && $cantidad !== '') {
        $cantidad = (int) $cantidad;
        if ($cantidad > 0) {
            $sql .= " LIMIT $cantidad";
        }
    }

    $result = $conexion->query($sql);

    if ($result && $result->num_rows > 0) {
        $data = [];
        $indexByDate = [];

        while ($row = $result->fetch_assoc()) {
            $fecha = $row['fecha'] ?? null;
            $item = [
                'hora' => $row['hora'] ?? null,
            ];

            for ($i = 1; $i <= 7; $i++) {
                $bKey = "b{$i}";     // Clave para la boya
                $mKey = "mo{$i}";    // Clave para el motor
                $stS = sprintf('ST-S%02d', $i);
                $stB = sprintf('ST-B%02d', $i);

                if ($datosSolicitados === null || in_array($stS, $datosSolicitados, true)) {
                    $item[$stS] = isset($row[$bKey]) ? (string)$row[$bKey] : null; // Estado de la boya
                }

                if ($datosSolicitados === null || in_array($stB, $datosSolicitados, true)) {
                    $item[$stB] = isset($row[$mKey]) ? (string)$row[$mKey] : null; // Estado del motor
                }
            }

            if (!isset($indexByDate[$fecha])) {
                $indexByDate[$fecha] = count($data);
                $data[] = [
                    'fecha' => $fecha,
                    'dato' => []
                ];
            }

            $data[$indexByDate[$fecha]]['dato'][] = $item;
        }

        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conexion->close();
?>
