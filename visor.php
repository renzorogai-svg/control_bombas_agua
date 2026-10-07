<?php
/*  visor.php desde PC
    07-10-2026
*/
?>
<!-- 21-06-2024:
 Creación del panel de control en tiempo real para monitoreo de boyas y motores, con actualización automática cada 2 segundos.
-->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo de Boyas y Motores</title>
    <style>
        :root {
            --bg-color: white;
            --card-bg: #ffffff;
            --text-color: #f01010;
            --active-color: #32e91a;
            --inactive-color: #e74c3c;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

   
        h3 {
            font-size: 16px;
            color: #6f09c2;
            position: fixed;
            top: 6px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10003;
            margin: 0;
            padding: 4px 8px;
            white-space: nowrap;
            max-width: 90vw;
            background: rgba(255,255,255,0);
        }

        .timestamp {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 25px;
        }

        .rssi-box {
            font-size: 14px;
            color: #2c3e50;
            background: rgba(255,255,255,0.9);
            padding: 1px 15px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 1px;
            box-shadow: 0 1px 1px black;
            margin-bottom: 5px;
        }

        .rssi-box span {
            font-weight: 700;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 80px 10px 10px 10px; /* Empuja el contenido hacia abajo para dejar espacio al h3 fijo */
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            overflow-y: auto;
            width: 100%;
        }

        @media (max-width: 768px) {
            html, body {
                overflow: hidden;
                height: 100%;
                overscroll-behavior: none;
                touch-action: none;
            }
        }

        .container {
            max-width: 1200px;
            width: 100%;
            z-index: 2;
            position: relative;
        }

        .photo-box {
            position: relative;
            width: 100%;
            max-width: 1200px;
            margin: 1px auto;
            box-sizing: border-box;
            overflow: hidden;
            z-index: 0;
            background-image: url('bombas.png');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 300px;
        }

        .photo-box img.background-photo {
            display: none;
        }

        .dots-layer {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 9999;
        }

        .condition-image {
            position: absolute;
            width: auto;
            height: 100%;
            max-height: 100%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            display: none;
            z-index: 1;
        }

        .status-marker {
            position: absolute;
            transform: translate(-50%, -50%);
            pointer-events: none;
            text-align: center;
            white-space: nowrap;
            z-index: 2;
        }

        .status-dot {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: var(--inactive-color);
            border: 3px solid rgba(255,255,255,0.95);
            box-shadow: 0 0 14px rgba(0,0,0,0.3);
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .boyas-status-box {
            position: absolute;
            left: 50%;
            bottom: 66px;
            transform: translateX(-50%);
            width: min(420px, calc(100% - 24px));
            color: #ffffff;
            padding: 0;
            font-size: 12px;
            line-height: 1.2;
            z-index: 5;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px 10px;
        }

        .boya-status-item {
            min-width: 0;
            text-align: left;
            color: #ecf0f1;
            font-size: 0.9rem;
            padding: 2px 4px;
            margin: 0;
        }

        .boya-status-item span {
            font-weight: 700;
            margin-left: 4px;
        }

        .boya-status-item span.alto {
            color: #e74c3c;
        }

        .boya-status-item span.bajo {
            color: #2ecc71;
        }

        .boya-status-item span.unknown {
            color: #95a5a6;
        }

        .warning-text {
            color: #e74c3c;
        }

        .status-dot.unknown {
            background-color: #95a5a6;
            box-shadow: 0 0 10px rgba(149, 165, 166, 0.6);
            border-color: rgba(255,255,255,0.6);
        }

        .status-text.unknown {
            color: #95a5a6;
        }

        .status-text {
            font-size: 0.7rem;
            color: #ffffff;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .status-label {
            display: block;
            margin-top: 6px;
            font-size: 0.75rem;
            color: #ffffff;
            text-shadow: 0 0 4px rgba(0,0,0,0.8);
        }

        .status-dot.on {
            background-color: var(--active-color);
            box-shadow: 0 0 14px rgba(50, 233, 26, 0.75);
        }

        .status-dot.unknown {
            background-color: #95a5a6;
            box-shadow: 0 0 10px rgba(149,165,166,0.6);
            border-color: rgba(255,255,255,0.6);
        }

        .link-icon {
            width: 18px;
            height: 18px;
            display: block;
            margin-top: 6px;
        }

        .link-icon.hidden {
            display: none;
        }

        .status-text.unknown {
            color: #95a5a6;
        }

        @media (max-width: 768px) {
            body {
                background-size: 95% auto;
                background-attachment: scroll;
            }

            .status-dot {
                width: 22px;
                height: 22px;
            }
        }

        /* En modo dispositivo evitar que la pantalla se mueva */
        @media (max-width: 768px) {
            body {
                position: fixed;
                width: 100vw;
                height: 100vh;
                overflow: hidden;
            }
        }

        /* Icono global: tamaño 20% del ancho de la pantalla y siempre centrado */
        #link-global-img {
            position: absolute;
            left: 50%;
            top: -50px;
            transform: translateX(-50%);
            width: 20vw;
            height: auto;
            z-index: 10002;
            max-width: 280px;
        }
        .history-link {
            display: inline-block;
            padding: 12px 24px;
            border: 2px solid #0d47a1;
            border-radius: 8px;
            background: #1565c0;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
        }

        .history-link:hover,
        .history-link:focus-visible {
            background: #0d47a1;
        }

        .history-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 10001;
        }

        @media (max-width: 768px) {
            .history-link {
                padding: 7px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h3>Visualización remota sala bombas.</h3>
        <img src="" id="link-global-img" class="link-icon hidden" alt="enlace global">
        <div class="rssi-box">Potencia de recepción remota: <span id="rssi-value">-</span> mdb</div>
        <div class="rssi-box">Nombre de red WiFi (SSID): <span id="ssid-value">-</span></div>
        <div class="rssi-box">Señal red WiFi: <span id="wifi-rssi-value">-</span> mdb</div>
        <div class="rssi-box" id="hora-box">Hora última actualización: <span id="hora-valor">-</span></div>
        <div class="timestamp" id="time-box">Hora actual: <span id="time-string">-</span> (ID: <span id="last-id">-</span>)</div>
    </div>

    <div class="photo-box">
        <div class="history-actions">
            <a href="ver_historial.php" id="history-link" class="history-link">Historial</a>
        </div>
        <img src="bombaVerde1.png" id="m1-image" class="condition-image" style="left: 8%; top: 50%;" alt="Motor 1">
        <img src="bombaVerde1.png" id="m2-image" class="condition-image" style="left: 24%; top: 50%;" alt="Motor 2">
        <img src="bombaVerde7.png" id="m3-image" class="condition-image" style="left: 39%; top: 50%;" alt="Motor 3">
        <img src="bombaVerde7.png" id="m4-image" class="condition-image" style="left: 53%; top: 50%;" alt="Motor 4">
        <img src="bombaVerde7.png" id="m5-image" class="condition-image" style="left: 67%; top: 50%;" alt="Motor 5">
        <img src="bombaVerde7.png" id="m6-image" class="condition-image" style="left: 80%; top: 50%;" alt="Motor 6">
        <img src="bombaVerde7.png" id="m7-image" class="condition-image" style="left: 93%; top: 50%;" alt="Motor 7">
        <div class="dots-layer" id="dots-layer"></div>
        <div class="boyas-status-box" id="boyas-status-box"></div>
    </div>

    <script>
        const historyLink = document.getElementById('history-link');
        historyLink.addEventListener('click', () => {
            historyLink.href = `ver_historial.php?timestamp=${Date.now()}`;
        });

        const photoBox = document.querySelector('.photo-box');
        const backgroundImage = new Image();
        backgroundImage.src = 'bombas.png';

        function adjustPhotoBoxHeight() {
            if (backgroundImage.naturalWidth && backgroundImage.naturalHeight) {
                photoBox.style.height = `${photoBox.offsetWidth * backgroundImage.naturalHeight / backgroundImage.naturalWidth}px`;
            }
        }

        backgroundImage.onload = adjustPhotoBoxHeight;
        window.addEventListener('resize', adjustPhotoBoxHeight);

        // Coordenadas independientes para cada boya y cada motor.
        // Ajusta los valores x/y en porcentaje para colocarlos sobre la imagen de fondo.
        const boyasPositions = [
            { x: 8, y: 16 },
            { x: 24, y: 18 },
            { x: 39, y: 20 },
            { x: 53, y: 23 },
            { x: 67, y: 26 },
            { x: 80, y: 28 },
            { x: 93, y: 28 }
        ];

        const motoresPositions = [
            { x: 8, y: 59 },
            { x: 24, y: 59 },
            { x: 39, y: 59 },
            { x: 53, y: 59 },
            { x: 67, y: 59 },
            { x: 80, y: 59 },
            { x: 93, y: 59 }
        ];

        const dotsLayer = document.getElementById('dots-layer');
        const boyaStatusBox = document.getElementById('boyas-status-box');
        const horaBox = document.getElementById('hora-box');
        const timeBox = document.getElementById('time-box');
        const boyaPercentages = ['50%', '45%', '33%', '25%', '20%', '15%', '15%'];

        function parseHoraActualizacion(horaString) {
            if (!horaString) return null;
            const trimmed = horaString.trim();
            const now = new Date();
            let parsed;
            if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(trimmed)) {
                const parts = trimmed.split(':');
                const hours = Number(parts[0]);
                const minutes = Number(parts[1]);
                const seconds = Number(parts[2] || 0);

                // Interpretar la hora del registro como hora local del navegador
                // y elegir entre ayer/hoy/mañana la fecha más cercana en valor absoluto.
                const candidates = [-1, 0, 1].map(dayShift => new Date(now.getFullYear(), now.getMonth(), now.getDate() + dayShift, hours, minutes, seconds));
                let best = candidates[0];
                let bestDiff = Math.abs(candidates[0].getTime() - now.getTime());
                for (let i = 1; i < candidates.length; i++) {
                    const diff = Math.abs(candidates[i].getTime() - now.getTime());
                    if (diff < bestDiff) {
                        best = candidates[i];
                        bestDiff = diff;
                    }
                }
                parsed = best;
            } else {
                parsed = new Date(trimmed);
            }
            return parsed && !isNaN(parsed.getTime()) ? parsed : null;
        }

        function renderBoyasStatus(datos) {
            const items = [];
            for (let i = 1; i <= 7; i++) {
                const percentage = boyaPercentages[i - 1];
                const stateValue = datos?.[`b${i}`];
                const isAlto = stateValue === 1 || stateValue === '1';
                const isBajo = stateValue === 0 || stateValue === '0';
                const isUnknown = stateValue === 2 || stateValue === '2';
                const status = isAlto ? 'ALTO' : isBajo ? 'BAJO' : isUnknown ? 'N/S' : '-';
                const className = isAlto ? 'alto' : isBajo ? 'bajo' : isUnknown ? 'unknown' : '';
                items.push(`
                    <div class="boya-status-item">
                        Boya ${i} (${percentage}): <span class="${className}">${status}</span>
                    </div>
                `);
            }
            boyaStatusBox.innerHTML = items.join('');
        }

        for (let i = 1; i <= 7; i++) {
            const boyaPos = boyasPositions[i - 1];
            dotsLayer.innerHTML += `
                <div class="status-marker" id="marker-b${i}" style="left: ${boyaPos.x}%; top: ${boyaPos.y}%;">
                    <span class="status-dot" id="b${i}"></span>
                    <span class="status-label">${boyaPercentages[i - 1]}</span>
                </div>
            `;

            const motorPos = motoresPositions[i - 1];
            dotsLayer.innerHTML += `
                <div class="status-marker" id="marker-m${i}" style="left: ${motorPos.x}%; top: ${motorPos.y}%;">
                    <span class="status-dot" id="m${i}"><span class="status-text" id="m${i}-text">off</span></span>
                </div>
            `;
        }

        // Función Fetch para actualizar los datos automáticamente
        async function actualizarPanel() {
            try {
                const respuesta = await fetch(`obtener_datos.php?_=${Date.now()}`);
                const resultado = await respuesta.json();

                if (resultado.status === 'success') {
                    const datos = resultado.data;

                    // Actualizar ID y hora en pantalla
                    document.getElementById('last-id').innerText = datos.Id;
                    document.getElementById('time-string').innerText = new Date().toLocaleTimeString();
                    document.getElementById('rssi-value').innerText = datos.rssiLoRa ?? '-';
                    document.getElementById('ssid-value').innerText = datos.ssid ?? '-';
                    document.getElementById('wifi-rssi-value').innerText = datos.rssi ?? '-';
                    const horaTexto = datos.hora ?? '-';
                    document.getElementById('hora-valor').innerText = horaTexto;
                    const horaActualizacion = parseHoraActualizacion(horaTexto);
                    // Comparar la hora mostrada en pantalla (time-string) con la hora del registro
                    const displayedText = document.getElementById('time-string')?.innerText ?? new Date().toLocaleTimeString();
                    const displayedDate = parseHoraActualizacion(displayedText) || new Date();
                    const diffMs = horaActualizacion ? Math.abs(displayedDate.getTime() - horaActualizacion.getTime()) : null;
                    const isLate = diffMs !== null && diffMs >= 6 * 60 * 1000;
                    horaBox.classList.toggle('warning-text', Boolean(isLate));
                    timeBox.classList.toggle('warning-text', Boolean(isLate));

                    // (debug removido)

                    // Recorrer del 1 al 7 para actualizar los estados visuales
                    for (let i = 1; i <= 7; i++) {
                            const elBoya = document.getElementById(`b${i}`);
                        const boyaValue = datos[`b${i}`];
                        const isBoyaAlto = boyaValue == 1;
                        const isBoyaBajo = boyaValue == 0;
                        const isBoyaUnknown = boyaValue == 2 || boyaValue == '2';
                        if (elBoya) {
                            elBoya.classList.toggle('on', isBoyaAlto);
                            elBoya.classList.toggle('unknown', isBoyaUnknown);
                            if (!isBoyaAlto && !isBoyaUnknown) {
                                elBoya.classList.remove('unknown');
                            }
                        }

                        const elMotor = document.getElementById(`m${i}`);
                        const elMotorText = document.getElementById(`m${i}-text`);
                        const motorValue = datos[`mo${i}`] ?? datos[`m${i}`];
                        if (elMotor) {
                            if (isBoyaUnknown) {
                                elMotor.classList.remove('on');
                                elMotor.classList.add('unknown');
                                if (elMotorText) elMotorText.innerText = 'N/S';
                            } else if (motorValue == 1) {
                                elMotor.classList.add('on');
                                elMotor.classList.remove('unknown');
                                if (elMotorText) elMotorText.innerText = 'on';
                            } else {
                                elMotor.classList.remove('on');
                                elMotor.classList.remove('unknown');
                                if (elMotorText) elMotorText.innerText = 'off';
                            }
                        }

                        const imageId = `m${i}-image`;
                        const motorImage = document.getElementById(imageId);
                        if (motorImage) {
                            motorImage.style.display = motorValue == 1 ? 'block' : 'none';
                        }

                        // (antes: iconos por motor) ahora se usa un icono global fuera del box
                    }

                    // actualizar ícono global de enlace según estados de boyas y lateness
                    const globalLinkImg = document.getElementById('link-global-img');
                    if (globalLinkImg) {
                        // Si la hora de actualización está atrasada, mostrar sin enlace
                        if (isLate) {
                            globalLinkImg.src = 'sinEnlace.png';
                            globalLinkImg.classList.remove('hidden');
                        } else {
                            let anyKnown = false;
                            for (let j = 1; j <= 7; j++) {
                                const v = datos[`b${j}`];
                                if (v == 0 || v == '0' || v == 1 || v == '1') { anyKnown = true; break; }
                            }
                            if (anyKnown) {
                                globalLinkImg.src = 'enlace.png';
                                globalLinkImg.classList.remove('hidden');
                            } else {
                                globalLinkImg.src = 'sinEnlace.png';
                                globalLinkImg.classList.remove('hidden');
                            }
                        }
                    }

                    renderBoyasStatus(datos);
                }
            } catch (error) {
                console.error("Error obteniendo datos de la base de datos:", error);
            }
        }

        // Ejecutar la función inmediatamente al cargar la página
        actualizarPanel();

        // Configurar para que se ejecute automáticamente cada 2 segundos (2000 milisegundos)
        setInterval(actualizarPanel, 2000);
    </script>
    <script>
        // Bloquear desplazamiento táctil y con rueda en modo dispositivo (ancho <= 768px)
        (function() {
            if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
                // Asegurar estilos
                document.documentElement.style.overflow = 'hidden';
                document.body.style.overflow = 'hidden';
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
                document.body.style.height = '100vh';

                // Evitar scroll táctil
                function preventTouch(e) { e.preventDefault(); }
                window.addEventListener('touchmove', preventTouch, { passive: false });

                // Evitar scroll por rueda (por si acaso)
                function preventWheel(e) { if (Math.abs(e.deltaY) > 0) e.preventDefault(); }
                window.addEventListener('wheel', preventWheel, { passive: false });
            }
        })();
    </script>
</body>
</html>