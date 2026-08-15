# control_bombas_agua

Sistema de monitoreo de nivel de tanque de agua y estado de bombas.
Envía la información a un servidor en la nube para visualizarse desde dispositivos móviles y generar alertas automáticas.

---

## Estructura del proyecto

```
firmware/                  – Sketch de Arduino / ESP32
  control_bombas_agua.ino

server/                    – Backend Node.js + dashboard web
  index.js                 – Servidor Express (API REST)
  public/index.html        – Dashboard responsive (HTML/CSS/JS puro)
  test/server.test.js      – Tests del servidor
  package.json
```

---

## Hardware requerido

| Componente | Cantidad |
|---|---|
| ESP32 (o ESP8266) | 1 |
| Sensor ultrasónico HC-SR04 | 1 |
| Módulo de relé (para detectar estado de bombas) | 1 por bomba |

### Conexiones (ESP32)

| Señal | GPIO |
|---|---|
| HC-SR04 TRIG | 5 |
| HC-SR04 ECHO | 18 |
| Bomba 1 (entrada digital) | 23 |
| Bomba 2 (entrada digital) | 22 |

---

## Firmware (Arduino IDE / PlatformIO)

### Dependencias de librerías
- `WiFi.h` (incluida en el core de ESP32)
- `HTTPClient.h` (incluida en el core de ESP32)
- **ArduinoJson** ≥ 6.x (instalar desde el gestor de librerías)

### Configuración

Editar las constantes en `firmware/control_bombas_agua.ino`:

```cpp
const char* WIFI_SSID     = "TU_SSID";
const char* WIFI_PASSWORD = "TU_PASSWORD";
const char* SERVER_URL    = "http://TU_SERVIDOR:3000/api/data";
const char* API_KEY       = "cambia_esta_clave_secreta";
const float TANK_HEIGHT_CM = 100.0f;
const float ALERT_LEVEL_PCT = 20.0f;
```

El firmware envía un JSON por HTTP POST cada 30 segundos.

---

## Servidor backend

### Requisitos
- Node.js ≥ 18

### Instalación y arranque

```bash
cd server
npm install
export PORT=3000
export API_KEY=cambia_esta_clave_secreta
npm start
```

El dashboard estará disponible en **http://localhost:3000**.

### API REST

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/data` | Recibe lectura del ESP32 |
| `GET` | `/api/latest` | Última lectura almacenada |
| `GET` | `/api/history?n=50` | Historial de las últimas N lecturas |

### Ejecutar tests

```bash
cd server
npm test
```

---

## Dashboard web

- Muestra el nivel del tanque en % con barra visual.
- Indica el estado (ON/OFF) de cada bomba.
- Muestra aviso de **NIVEL BAJO** cuando se activa la alerta.
- Historial de nivel en gráfica de línea.
- Se actualiza automáticamente cada 10 segundos.
- Responsive, funciona en móviles.
