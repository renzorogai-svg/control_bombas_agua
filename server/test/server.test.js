/**
 * test/server.test.js
 *
 * Tests for the control_bombas_agua backend server.
 * Uses Node.js built-in test runner (node --test) – no extra framework needed.
 */

'use strict';

const test      = require('node:test');
const assert    = require('node:assert/strict');
const http      = require('node:http');

// Set API_KEY before requiring the app so the env var is picked up
process.env.API_KEY = 'test_key_123';

const app = require('../index.js');

let server;

// ── helpers ────────────────────────────────────────────────────────────────

function request(method, path, body) {
    return new Promise((resolve, reject) => {
        const data    = body ? JSON.stringify(body) : null;
        const options = {
            hostname: '127.0.0.1',
            port:     server.address().port,
            path,
            method,
            headers: {
                'Content-Type':   'application/json',
                'Content-Length': data ? Buffer.byteLength(data) : 0,
            },
        };
        const req = http.request(options, (res) => {
            let raw = '';
            res.on('data', (c) => (raw += c));
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, body: JSON.parse(raw) });
                } catch {
                    resolve({ status: res.statusCode, body: raw });
                }
            });
        });
        req.on('error', reject);
        if (data) req.write(data);
        req.end();
    });
}

// ── lifecycle ──────────────────────────────────────────────────────────────

test.before(() => {
    server = app.listen(0);            // random port
});

test.after(() => {
    server.close();
});

// ── test cases ─────────────────────────────────────────────────────────────

test('GET /api/latest returns null when no data', async () => {
    const res = await request('GET', '/api/latest');
    assert.equal(res.status, 200);
    assert.equal(res.body, null);
});

test('POST /api/data rejects wrong API key', async () => {
    const res = await request('POST', '/api/data', {
        level_pct: 55,
        pump1: false,
        pump2: false,
        alert: false,
        api_key: 'wrong_key',
    });
    assert.equal(res.status, 401);
});

test('POST /api/data rejects missing level_pct', async () => {
    const res = await request('POST', '/api/data', {
        pump1: false,
        pump2: false,
        alert: false,
        api_key: 'test_key_123',
    });
    assert.equal(res.status, 400);
});

test('POST /api/data accepts valid payload', async () => {
    const res = await request('POST', '/api/data', {
        level_pct: 72.5,
        pump1: true,
        pump2: false,
        alert: false,
        api_key: 'test_key_123',
    });
    assert.equal(res.status, 200);
    assert.deepEqual(res.body, { ok: true });
});

test('GET /api/latest returns the last inserted entry', async () => {
    const res = await request('GET', '/api/latest');
    assert.equal(res.status, 200);
    assert.equal(res.body.level_pct, 72.5);
    assert.equal(res.body.pump1, true);
    assert.equal(res.body.pump2, false);
    assert.equal(res.body.alert, false);
    assert.ok(res.body.timestamp);
});

test('GET /api/history returns array with the entry', async () => {
    const res = await request('GET', '/api/history?n=10');
    assert.equal(res.status, 200);
    assert.ok(Array.isArray(res.body));
    assert.ok(res.body.length >= 1);
    const last = res.body[res.body.length - 1];
    assert.equal(last.level_pct, 72.5);
});

test('POST /api/data sets alert=true when level is low', async () => {
    const res = await request('POST', '/api/data', {
        level_pct: 10,
        pump1: false,
        pump2: false,
        alert: true,
        api_key: 'test_key_123',
    });
    assert.equal(res.status, 200);

    const latest = await request('GET', '/api/latest');
    assert.equal(latest.body.alert, true);
    assert.equal(latest.body.level_pct, 10);
});
