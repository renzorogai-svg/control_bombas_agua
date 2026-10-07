<?php
/*22-08-2026 desde PC
*/
date_default_timezone_set('america/caracas');
$fecha = date('Y-m-d');
$hora = date('H:i:s'); // con segundos para el registro

$b1 = $_GET['b1'] ?? 2;
$b2 = $_GET['b2'] ?? 2;  // <-- Esto asegura que capture el 1 o el 0 sin dejarlo vacío
$b3 = $_GET['b3'] ?? 2;
$b4 = $_GET['b4'] ?? 2;
$b5 = $_GET['b5'] ?? 2;
$b6 = $_GET['b6'] ?? 2;
$b7 = $_GET['b7'] ?? 2;

$mo1 = $_GET['m1'] ?? 2;
$mo2 = $_GET['m2'] ?? 2;
$mo3 = $_GET['m3'] ?? 2;
$mo4 = $_GET['m4'] ?? 2;
$mo5 = $_GET['m5'] ?? 2;
$mo6 = $_GET['m6'] ?? 2;
$mo7 = $_GET['m7'] ?? 2;

if(isset($_GET['rssi'])) {
   $rssi = $_GET['rssi'];
} else $rssi = '0';

if(isset($_GET['rssiLoRa'])) {
   $rssiLoRa = $_GET['rssiLoRa'];
} else $rssiLoRa = '0';

if(isset($_GET['ssid'])) {
   $ssid = $_GET['ssid'];
} else $ssid = 'No';

if(isset($_GET['macAP'])) {
   $macAP = $_GET['macAP'];
} else $macAP = 'No';

if(isset($_GET['IpGT'])) {
   $IpGT = $_GET['IpGT'];
} else $IpGT = 'No';

if(isset($_GET['IpESP'])) {
   $IpESP = $_GET['IpESP'];
} else $IpESP = 'No';

if(isset($_GET['DNS1'])) {
   $DNS1 = $_GET['DNS1'];
} else $DNS1 = 'No';

if(isset($_GET['macESP'])) {
   $macESP = $_GET['macESP'];
} else $macESP = 'No';

if(isset($_GET['name'])) {
   $name = $_GET['name'];
} else $name = 'No';

$variablesActuales = [
   'b1' => (string)$b1,
   'b2' => (string)$b2,
   'b3' => (string)$b3,
   'b4' => (string)$b4,
   'b5' => (string)$b5,
   'b6' => (string)$b6,
   'b7' => (string)$b7,
   'mo1' => (string)$mo1,
   'mo2' => (string)$mo2,
   'mo3' => (string)$mo3,
   'mo4' => (string)$mo4,
   'mo5' => (string)$mo5,
   'mo6' => (string)$mo6,
   'mo7' => (string)$mo7,
];

$etiquetasVariables = [
   'b1' => 'boya 1',
   'b2' => 'boya 2',
   'b3' => 'boya 3',
   'b4' => 'boya 4',
   'b5' => 'boya 5',
   'b6' => 'boya 6',
   'b7' => 'boya 7',
   'mo1' => 'motor 1',
   'mo2' => 'motor 2',
   'mo3' => 'motor 3',
   'mo4' => 'motor 4',
   'mo5' => 'motor 5',
   'mo6' => 'motor 6',
   'mo7' => 'motor 7',
];

$etiquetasValores = [
   '0' => 'off',
   '1' => 'on',
   '2' => 'n/s',
];

include 'conexion.php'; 

$cambiosDetectados = [];
$sql = "SELECT b1, b2, b3, b4, b5, b6, b7, mo1, mo2, mo3, mo4, mo5, mo6, mo7 FROM registros ORDER BY Id DESC LIMIT 1";
$result = mysqli_query($conexion, $sql);

if ($result && ($registroAnterior = mysqli_fetch_assoc($result))) {
   foreach ($variablesActuales as $nombreVariable => $valorActual) {
      $valorAnterior = (string)($registroAnterior[$nombreVariable] ?? '');
      if ($valorAnterior !== $valorActual) {
         $textoAnterior = $etiquetasValores[$valorAnterior] ?? $valorAnterior;
         $textoActual = $etiquetasValores[$valorActual] ?? $valorActual;
         $cambiosDetectados[] = $etiquetasVariables[$nombreVariable] . ': ' . $textoAnterior . ' -> ' . $textoActual;
      }
   }
}

// Actualización de parámetros
$sql = "UPDATE parametros 
        SET fecha = '$fecha',
            hora = '$hora', 
            ssid = '$ssid', 
            IpGT = '$IpGT', 
            IpESP = '$IpESP',
            macAP = '$macAP', 
            rssi = '$rssi',
            rssiLoRa = '$rssiLoRa', 
            DNS1 = '$DNS1', 
            macESP = '$macESP', 
            name = '$name'";  
if (!mysqli_query($conexion, $sql)) {
    echo "Error al guardar los datos: " . mysqli_error($conexion);
}

// Inserción de registro histórico
$sql = "INSERT INTO registros (fecha,hora,b1,b2,b3,b4,b5,b6,b7,mo1,mo2,mo3,mo4,mo5,mo6,mo7) VALUES ('$fecha','$hora','$b1','$b2','$b3','$b4','$b5','$b6','$b7','$mo1','$mo2','$mo3','$mo4','$mo5','$mo6','$mo7')";
if ($conexion->query($sql) !== TRUE) {
    echo "Error: ". $sql. "<br>" . $conexion->error;
}

// ---------------- Notificacion Telegram ---------------- //

if (!empty($cambiosDetectados)) {
   $mensaje = "Alerta de bombas (" . $fecha . " " . $hora . "):\n" . implode("\n", $cambiosDetectados);
   $envioTGExitoso = false;
   $estadoTG = 'fallo';

   // URL de la API de Telegram.
   $token = "8449294977:AAEfGzK9DscufRr8e8WxSG_gTMtGZ_rqu2w";
   $chat_id = "117482557";
   $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($mensaje);
   $response = file_get_contents($url);
   if ($response === false) {
      $errorTG = 'Telegram no respondio.';
      $estadoTG = 'fallo';
      error_log($errorTG);
   } else {
      $envioTGExitoso = true;
      $estadoTG = 'enviado';
   }
}

// ---------------- Notificaciones WhatsApp (Twilio Content API) ---------------- //

$accountSid = getenv('TWILIO_ACCOUNT_SID');
$authToken = getenv('TWILIO_AUTH_TOKEN');
$contentSid = getenv('TWILIO_CONTENT_SID');
$twilioHabilitado = $accountSid !== false && $accountSid !== ''
    && $authToken !== false && $authToken !== ''
    && $contentSid !== false && $contentSid !== '';

if (!empty($cambiosDetectados) && $twilioHabilitado) {

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

    $telefonos = array(
        'whatsapp:+584143459825',
        'whatsapp:+584127432683'
    );

    // Preparar el mapeo de variables dinámicas (1 a 14) según la plantilla
    $contentVariables = [
        "1"  => $etiquetasValores[(string)$b1]  ?? 'n/s',
        "2"  => $etiquetasValores[(string)$b2]  ?? 'n/s',
        "3"  => $etiquetasValores[(string)$b3]  ?? 'n/s',
        "4"  => $etiquetasValores[(string)$b4]  ?? 'n/s',
        "5"  => $etiquetasValores[(string)$b5]  ?? 'n/s',
        "6"  => $etiquetasValores[(string)$b6]  ?? 'n/s',
        "7"  => $etiquetasValores[(string)$b7]  ?? 'n/s',
        "8"  => $etiquetasValores[(string)$mo1] ?? 'n/s',
        "9"  => $etiquetasValores[(string)$mo2] ?? 'n/s',
        "10" => $etiquetasValores[(string)$mo3] ?? 'n/s',
        "11" => $etiquetasValores[(string)$mo4] ?? 'n/s',
        "12" => $etiquetasValores[(string)$mo5] ?? 'n/s',
        "13" => $etiquetasValores[(string)$mo6] ?? 'n/s',
        "14" => $etiquetasValores[(string)$mo7] ?? 'n/s'
    ];

    foreach ($telefonos as $numeroDestino) {
        $data = array(
            'From'             => 'whatsapp:+14155238886', // Tu número de Twilio WhatsApp
            'To'               => $numeroDestino,
            'ContentSid'       => $contentSid,
            'ContentVariables' => json_encode($contentVariables)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$accountSid}:{$authToken}",
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 20
        ));

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
} elseif (!empty($cambiosDetectados)) {
    error_log('Twilio notifications skipped: required environment variables are not configured.');
}
?>