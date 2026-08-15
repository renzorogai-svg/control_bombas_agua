<?php
// 09-08-2026
// 

date_default_timezone_set('America/Caracas');

$archivoNotificaciones = __DIR__ . DIRECTORY_SEPARATOR . 'totificaciones.txt';

$mensajeWA = 1; 
$mensajeTG = 1;
$mensaje = "Alerta alarma !";

$envioWAExitoso = $mensajeWA != '1';
$envioTGExitoso = $mensajeTG != '1';
$errorWA = '';
$errorTG = '';

if($mensajeWA  == '1') {
//............................ Whats App ,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,||
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
   } elseif ($httpCode !== 200) {
    $errorWA = 'WhatsApp devolvio HTTP ' . $httpCode . ': ' . trim((string)$response);
   } elseif (stripos((string)$response, 'error') !== false || stripos((string)$response, 'activate') !== false) {
    $errorWA = 'WhatsApp rechazo el envio: ' . trim((string)$response);
   } else {
    $envioWAExitoso = true;
   }
   // Cerrar cURL
   curl_close($ch);
   //........................................................................................||
   }
   if($mensajeTG  == '1') {
   // URL de la APU de  Telegram.
   $token = "8449294977:AAEfGzK9DscufRr8e8WxSG_gTMtGZ_rqu2w"; // Reemplaza con tu token
   $chat_id = "117482557"; // Reemplaza con tu ID de chat
   $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($mensaje);
   $response = file_get_contents($url);
   if ($response === false) {
    $errorTG = 'Telegram no respondio.';
   } else {
    $envioTGExitoso = true;
   }
  }

  if ($envioWAExitoso && $envioTGExitoso && ($mensajeWA == '1' || $mensajeTG == '1')) {
  $fechaHora = date('Y-m-d H:i:s');
  $registro = $fechaHora . " | " . $mensaje . PHP_EOL;

  if (file_put_contents($archivoNotificaciones, $registro, FILE_APPEND | LOCK_EX) === false) {
    echo "Error al guardar el registro de la notificacion.";
  } else {
    echo "Se han enviado las notificaciones.";
  }
} else {
  if ($errorWA !== '') {
    echo $errorWA . PHP_EOL;
  }
  if ($errorTG !== '') {
    echo $errorTG . PHP_EOL;
  }
}
