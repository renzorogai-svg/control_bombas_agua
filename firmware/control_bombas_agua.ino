/**
 * control_bombas_agua.ino
 *
 * Monitorea el nivel del tanque de agua y el estado de las bombas.
 * Envía los datos al servidor en la nube mediante HTTP (JSON) para
 * visualización desde dispositivos móviles y generación de alertas.
 *
 * Hardware:
 *   - ESP32 (o ESP8266 con ajustes menores)
 *   - Sensor ultrasónico HC-SR04 para nivel de agua
 *   - Relés para controlar/detectar el estado de cada bomba
 *   - LEDs indicadores opcionales
 *
 * Pines (ajustar según hardware):
 *   TRIG_PIN  → GPIO 5   (sensor ultrasónico)
 *   ECHO_PIN  → GPIO 18  (sensor ultrasónico)
 *   PUMP1_PIN → GPIO 23  (entrada digital: HIGH = bomba encendida)
 *   PUMP2_PIN → GPIO 22  (entrada digital: HIGH = bomba encendida)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ──────────────────────────────────────────────
// Configuración de red y servidor – AJUSTAR
// ──────────────────────────────────────────────
const char* WIFI_SSID     = "TU_SSID";
const char* WIFI_PASSWORD = "TU_PASSWORD";

// URL del endpoint del servidor (ver server/index.js)
const char* SERVER_URL = "http://TU_SERVIDOR:3000/api/data";

// API key compartida con el servidor para autenticación básica
const char* API_KEY = "cambia_esta_clave_secreta";

// ──────────────────────────────────────────────
// Pines
// ──────────────────────────────────────────────
#define TRIG_PIN   5
#define ECHO_PIN   18
#define PUMP1_PIN  23
#define PUMP2_PIN  22

// ──────────────────────────────────────────────
// Parámetros del tanque
// ──────────────────────────────────────────────
// Distancia (cm) desde el sensor hasta el fondo del tanque (tanque vacío)
const float TANK_HEIGHT_CM = 100.0f;

// Nivel mínimo aceptable (% capacidad). Genera alerta si baja de este valor.
const float ALERT_LEVEL_PCT = 20.0f;

// Intervalo entre envíos al servidor (ms)
const unsigned long SEND_INTERVAL_MS = 30000UL; // 30 segundos

// ──────────────────────────────────────────────
// Variables globales
// ──────────────────────────────────────────────
unsigned long lastSendTime = 0;

// ──────────────────────────────────────────────
// Funciones auxiliares
// ──────────────────────────────────────────────

/**
 * Mide la distancia en centímetros usando el sensor HC-SR04.
 * Devuelve -1 en caso de error (sin eco).
 */
float measureDistanceCm() {
    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG_PIN, LOW);

    long duration = pulseIn(ECHO_PIN, HIGH, 30000); // timeout 30 ms
    if (duration == 0) {
        return -1.0f;
    }
    return (duration * 0.0343f) / 2.0f;
}

/**
 * Calcula el porcentaje de nivel del tanque a partir de la distancia medida.
 * 100 % → sensor al ras del agua (tanque lleno)
 *   0 % → agua al fondo (tanque vacío)
 */
float calculateLevelPct(float distanceCm) {
    if (distanceCm < 0) return -1.0f;
    float waterHeight = TANK_HEIGHT_CM - distanceCm;
    if (waterHeight < 0) waterHeight = 0;
    float pct = (waterHeight / TANK_HEIGHT_CM) * 100.0f;
    if (pct > 100.0f) pct = 100.0f;
    return pct;
}

/**
 * Conecta o reconecta a la red Wi-Fi.
 */
void connectWiFi() {
    if (WiFi.status() == WL_CONNECTED) return;

    Serial.print("[WiFi] Conectando a ");
    Serial.print(WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    unsigned long start = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - start < 20000UL) {
        delay(500);
        Serial.print(".");
    }
    Serial.println();

    if (WiFi.status() == WL_CONNECTED) {
        Serial.print("[WiFi] Conectado. IP: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("[WiFi] Error: no se pudo conectar.");
    }
}

/**
 * Envía los datos de sensores al servidor como JSON por HTTP POST.
 */
void sendData(float levelPct, bool pump1On, bool pump2On, bool alert) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[HTTP] Sin conexión WiFi, omitiendo envío.");
        return;
    }

    StaticJsonDocument<256> doc;
    doc["level_pct"]  = levelPct;
    doc["pump1"]      = pump1On;
    doc["pump2"]      = pump2On;
    doc["alert"]      = alert;
    doc["api_key"]    = API_KEY;
    // NOTA DE SEGURIDAD: usar HTTPS en SERVER_URL para proteger la clave en tránsito.

    String payload;
    serializeJson(doc, payload);

    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");

    int httpCode = http.POST(payload);

    if (httpCode > 0) {
        Serial.printf("[HTTP] Respuesta: %d\n", httpCode);
    } else {
        Serial.printf("[HTTP] Error: %s\n", http.errorToString(httpCode).c_str());
    }
    http.end();
}

// ──────────────────────────────────────────────
// Setup & Loop
// ──────────────────────────────────────────────

void setup() {
    Serial.begin(115200);
    delay(100);

    pinMode(TRIG_PIN,  OUTPUT);
    pinMode(ECHO_PIN,  INPUT);
    pinMode(PUMP1_PIN, INPUT_PULLDOWN);
    pinMode(PUMP2_PIN, INPUT_PULLDOWN);

    connectWiFi();
}

void loop() {
    connectWiFi();

    unsigned long now = millis();
    if (now - lastSendTime >= SEND_INTERVAL_MS) {
        lastSendTime = now;

        float distanceCm = measureDistanceCm();
        float levelPct   = calculateLevelPct(distanceCm);
        bool  pump1On    = (digitalRead(PUMP1_PIN) == HIGH);
        bool  pump2On    = (digitalRead(PUMP2_PIN) == HIGH);
        bool  alert      = (levelPct >= 0 && levelPct < ALERT_LEVEL_PCT);

        Serial.printf("[Sensor] Distancia: %.1f cm | Nivel: %.1f%% | Bomba1: %s | Bomba2: %s | Alerta: %s\n",
                      distanceCm, levelPct,
                      pump1On ? "ON" : "OFF",
                      pump2On ? "ON" : "OFF",
                      alert   ? "SI" : "NO");

        if (levelPct < 0) {
            Serial.println("[Sensor] Error: lectura de distancia inválida, omitiendo envío.");
        } else {
            sendData(levelPct, pump1On, pump2On, alert);
        }
    }

    delay(100);
}
