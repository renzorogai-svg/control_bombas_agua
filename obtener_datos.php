<?php
/* obtener_datos.php\
21-06-2024
datos:

-hora: ultima actualizacion de base de datos
- ssid: nombre de red wifi a la que esta conectado el dispositivo
- rssi: potencia de la señal wifi



*/
include 'conexion.php';



try {
    // Buscamos el último registro insertado
    $sql = "SELECT * FROM registros ORDER BY Id DESC LIMIT 1";
    $result = $conexion->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Obtener hora, rssiLoRa, rssi y ssid desde la tabla parametros
        $paramSql = "SELECT hora, rssiLoRa, rssi, ssid FROM parametros LIMIT 1";
        $paramResult = $conexion->query($paramSql);
        if ($paramResult && $paramResult->num_rows > 0) {
            $paramRow = $paramResult->fetch_assoc();
            $row['hora'] = $paramRow['hora'] ?? null;
            $row['rssiLoRa'] = $paramRow['rssiLoRa'] ?? null;
            $row['rssi'] = $paramRow['rssi'] ?? null;
            $row['ssid'] = $paramRow['ssid'] ?? null;
        } else {
            $row['hora'] = null;
            $row['rssiLoRa'] = null;
            $row['rssi'] = null;
            $row['ssid'] = null;
        }

        echo json_encode(["status" => "success", "data" => $row]);
    } else {
        echo json_encode(["status" => "empty", "message" => "No se encontraron registros."]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conexion->close();
?>