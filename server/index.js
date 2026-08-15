/**
 * index.js – Servidor backend para control_bombas_agua
 *
 * Recibe datos del ESP32 (nivel del tanque y estado de bombas),
 * los almacena en memoria (últimas N lecturas) y los sirve a través de
 * una API REST consumida por el dashboard web.
 *
 * Variables de entorno:
 *   PORT     – Puerto HTTP (default 3000)
 *   API_KEY  – Clave compartida con el firmware (default "cambia_esta_clave_secreta")
 */

'use strict';

const express = require('express');
const path    = require('path');

const app    = express();
const PORT   = process.env.PORT   || 3000;
const API_KEY = process.env.API_KEY || 'cambia_esta_clave_secreta';

// Historial en memoria: máximo 500 lecturas
const MAX_HISTORY = 500;
const history     = [];

app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

// ──────────────────────────────────────────────
// POST /api/data  – recibe datos del firmware
// ──────────────────────────────────────────────
app.post('/api/data', (req, res) => {
    const { level_pct, pump1, pump2, alert, api_key } = req.body;

    if (api_key !== API_KEY) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    if (typeof level_pct !== 'number') {
        return res.status(400).json({ error: 'level_pct must be a number' });
    }

    const entry = {
        timestamp: new Date().toISOString(),
        level_pct,
        pump1:  Boolean(pump1),
        pump2:  Boolean(pump2),
        alert:  Boolean(alert),
    };

    history.push(entry);
    if (history.length > MAX_HISTORY) {
        history.shift();
    }

    console.log(`[${entry.timestamp}] Nivel: ${level_pct.toFixed(1)}% | Bomba1: ${entry.pump1} | Bomba2: ${entry.pump2} | Alerta: ${entry.alert}`);

    return res.json({ ok: true });
});

// ──────────────────────────────────────────────
// GET /api/latest  – última lectura
// ──────────────────────────────────────────────
app.get('/api/latest', (req, res) => {
    if (history.length === 0) {
        return res.json(null);
    }
    res.json(history[history.length - 1]);
});

// ──────────────────────────────────────────────
// GET /api/history  – últimas N lecturas (default 50)
// ──────────────────────────────────────────────
app.get('/api/history', (req, res) => {
    const parsed = parseInt(req.query.n, 10);
    const n = Math.min(isNaN(parsed) ? 50 : Math.max(parsed, 0), MAX_HISTORY);
    res.json(history.slice(-n));
});

// ──────────────────────────────────────────────
// Start
// ──────────────────────────────────────────────
if (require.main === module) {
    app.listen(PORT, () => {
        console.log(`Servidor escuchando en http://0.0.0.0:${PORT}`);
    });
}

module.exports = app; // para tests
