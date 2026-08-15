<?php //11-08-2026
date_default_timezone_set('america/caracas');
$fecha =date('Y-m-d');
$hora = date('H:i:s'); // con segundos para el registro
//$dia=(int)date('d');
//$mes = (int)date('m'); // elimina el cero
//$anio=date('Y');
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
   $rssi=$_GET['rssi'];
}  else $rssi ='0';
if(isset($_GET['rssiLoRa'])) {
   $rssiLoRa=$_GET['rssiLoRa'];
}  else $rssiLoRa ='0';
if(isset($_GET['ssid'])) {
   $ssid=$_GET['ssid'];
} else $ssid ='No';
if(isset($_GET['macAP'])) {
   $macAP=$_GET['macAP'];
} else $macAP ='No';
if(isset($_GET['IpGT'])) {
   $IpGT=$_GET['IpGT'];
} else $IpGT ='No';
if(isset($_GET['IpESP'])) {
   $IpESP=$_GET['IpESP'];
} else $IpESP ='No';
if(isset($_GET['DNS1'])) {
   $DNS1=$_GET['DNS1'];
} else $DNS1 ='No';
if(isset($_GET['macESP'])) {
   $macESP=$_GET['macESP'];
} else $macESP ='No';
if(isset($_GET['name'])) {
   $name=$_GET['name'];
} else $name ='No';

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
//.......................
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
  if (mysqli_query($conexion, $sql)) {
       // echo "Datos guardados correctamente.";
 } else {
       echo "Error al guardar los datos: " . mysqli_error($conexion);
 }

//.......................


 $sql="INSERT INTO registros (fecha,hora,b1,b2,b3,b4,b5,b6,b7,mo1,mo2,mo3,mo4,mo5,mo6,mo7) VALUES ('$fecha','$hora','$b1','$b2','$b3','$b4','$b5','$b6','$b7','$mo1','$mo2','$mo3','$mo4','$mo5','$mo6','$mo7')";
  if ($conexion->query($sql) === TRUE) {
  } else {
    echo "Error: ". $sql. "<br>" . $conexion->error;
  }

/*
 // Obtener los valores de alarma desde la base de datos
$sql = "SELECT * FROM parametros WHERE Id= '0' "; 
$result = mysqli_query($conexion, $sql); 
$row = mysqli_fetch_assoc($result);
$nombre = $row['nombre'];
$alarmaMax = $row['alarm_max'];
$alarmaMin = $row['alarm_min'];
$bandera = $row['alarm_bandera'];
$alarm_notificada = $row['alarm_notificada'];
$reporte = $row['reporte'];
$reporte_notificado = $row['reporte_notificado'];
$hora_reporte = $row['hora_reporte'];
$mensajeWA = $row['mensajeWA'];
$mensajeTG = $row['mensajeTG'];
//............
$t_min = $row['tiempo_min'];
$t_max = $row['tiempo_max'];
$umbral= $row['umbral'];

  
$conexion->close();
echo '&t_min='.$t_min.'&t_max='.$t_max.'&umbral='.$umbral.'&fin'; 
*/


// ---------------- notificaciones -----WA y TG----------------------------------------------//
//*******************************************************************************************//

date_default_timezone_set('America/Caracas');

$archivoNotificaciones = __DIR__ . DIRECTORY_SEPARATOR . 'totificaciones.txt';

$mensaje = '';
$mensajeWA = '1';
$mensajeTG = '1';

if (!empty($cambiosDetectados)) {
   $mensaje = "Cambios detectados en variables:" . PHP_EOL . implode(PHP_EOL, $cambiosDetectados);
}

$envioWAExitoso = $mensajeWA != '1';
$envioTGExitoso = $mensajeTG != '1';
$errorWA = '';
$errorTG = '';
$estadoWA = $mensajeWA == '1' ? 'pendiente' : 'no habilitado';
$estadoTG = $mensajeTG == '1' ? 'pendiente' : 'no habilitado';

if($mensaje !== '' && $mensajeWA  == '1') {
//............................ Whats App ............................||
   $token = "4358035";
   $numero = "+584143459825";
   // URL de la API de CallMeBot.  
   $url = "https://api.callmebot.com/whatsapp.php?phone=$numero&text=" . urlencode($mensaje) . "&apikey=$token";
   // Inicializar cURL
   $ch = curl_init();
   // Configurar cURL
   curl_setopt($ch, CURLOPT_URL, $url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_TIMEOUT, 20);
   // Ejecutar la petici�n
   $response = curl_exec($ch);
   $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
   $curlError = curl_error($ch);
   if ($response === false) {
    $errorWA = 'WhatsApp no respondio: ' . $curlError;
      $estadoWA = 'fallo';
   } elseif ($httpCode !== 200) {
    $errorWA = 'WhatsApp devolvio HTTP ' . $httpCode . ': ' . trim((string)$response);
      $estadoWA = 'fallo';
   } elseif (stripos((string)$response, 'error') !== false || stripos((string)$response, 'activate') !== false) {
    $errorWA = 'WhatsApp rechazo el envio: ' . trim((string)$response);
      $estadoWA = 'fallo';
   } else {
    $envioWAExitoso = true;
      $estadoWA = 'enviado';
   }
   // Cerrar cURL
   curl_close($ch);
   //.................................................................||
   }
   if($mensaje !== '' && $mensajeTG  == '1') {
   // URL de la APU de  Telegram.
   $token = "8449294977:AAEfGzK9DscufRr8e8WxSG_gTMtGZ_rqu2w"; // Reemplaza con tu token
   $chat_id = "117482557"; // Reemplaza con tu ID de chat
   $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($mensaje);
   $response = file_get_contents($url);
   if ($response === false) {
    $errorTG = 'Telegram no respondio.';
      $estadoTG = 'fallo';
   } else {
    $envioTGExitoso = true;
      $estadoTG = 'enviado';
   }
  }

   // envio a WA por Twilio --------------------------------------------------------------
// Se integra como un canal adicional, pero nunca debe dispararse cuando no hay mensaje
// ni duplicar el envío que ya se intenta por CallMeBot / Telegram.
$twilioHabilitado = true;
if ($mensaje !== '' && $twilioHabilitado) {
    $accountSid = 'TWILIO_ACCOUNT_SID';      // oculto para subir a GitHub, reemplazar con tu SID de cuenta de Twilio
    $authToken  = 'TWILIO_AUTH_TOKEN';       // oculto para subir a GitHub, reemplazar con tu token de autenticación de Twilio   

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

    $telefonos = array(
        'whatsapp:+584143459825',  // minumero
        'whatsapp:+584127432683'   // Gustavo Valero
    );

    foreach ($telefonos as $numeroDestino) {
        $data = array(
            'From' => 'whatsapp:+14155238886',
            'To'   => $numeroDestino,
            'Body' => $mensaje
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$accountSid}:{$authToken}",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 20
        ));

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $errorWA .= 'Twilio fallo en ' . $numeroDestino . ': ' . $curlError . '; ';
            continue;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorWA .= 'Twilio ' . $numeroDestino . ' devolvio HTTP ' . $httpCode . ': ' . trim((string)$response) . '; ';
            continue;
        }

        echo "Enviado a <strong>{$numeroDestino}</strong> - Código HTTP: {$httpCode}<br>";
    }
}
// -------------------------------------------------------------------------------

   if ($mensaje !== '' && $envioWAExitoso && $envioTGExitoso && ($mensajeWA == '1' || $mensajeTG == '1')) {
  $fechaHora = date('Y-m-d H:i:s');
   $registro = $fechaHora . " | WA: " . $estadoWA . " | TG: " . $estadoTG . " | " . str_replace(PHP_EOL, ' ; ', $mensaje) . PHP_EOL;

  if (file_put_contents($archivoNotificaciones, $registro, FILE_APPEND | LOCK_EX) === false) {
    echo "Error al guardar el registro de la notificacion.";
  } else {
    echo "Se han enviado las notificaciones.";
  }
} else {
   if ($mensaje !== '') {
      $fechaHora = date('Y-m-d H:i:s');
      $detalleWA = $errorWA !== '' ? ' (' . $errorWA . ')' : '';
      $detalleTG = $errorTG !== '' ? ' (' . $errorTG . ')' : '';
      $registro = $fechaHora . " | WA: " . $estadoWA . $detalleWA . " | TG: " . $estadoTG . $detalleTG . " | " . str_replace(PHP_EOL, ' ; ', $mensaje) . PHP_EOL;
      if (file_put_contents($archivoNotificaciones, $registro, FILE_APPEND | LOCK_EX) === false) {
         echo "Error al guardar el registro de la notificacion.";
      }
   }
  if ($errorWA !== '') {
    echo $errorWA . PHP_EOL;
  }
  if ($errorTG !== '') {
    echo $errorTG . PHP_EOL;
  }
}
//*******************************************************************************************//

?>


