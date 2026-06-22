'use strict';

const fs = require('fs');
const http = require('http');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');

const CONFIG_PATH = path.join(__dirname, 'config.json');
const LOG_PATH = path.join(__dirname, 'print-service.log');
const MAX_BODY_SIZE = 1024 * 1024;

function loadConfig() {
    if (!fs.existsSync(CONFIG_PATH)) {
        throw new Error(`No se encuentra el archivo de configuración: ${CONFIG_PATH}`);
    }

    const raw = fs.readFileSync(CONFIG_PATH, 'utf8').replace(/^\uFEFF/, '');
    const fileConfig = JSON.parse(raw);

    const config = {
        host: String(fileConfig.host || '127.0.0.1'),
        port: Number(fileConfig.port || 8787),
        token: String(fileConfig.token || ''),
        printerName: String(fileConfig.printerName || '').trim(),
        allowedOrigins: Array.isArray(fileConfig.allowedOrigins)
            ? fileConfig.allowedOrigins.map((value) => String(value).replace(/\/$/, ''))
            : [],
        keepTicketFiles: Boolean(fileConfig.keepTicketFiles),
        paperWidthMm: Number(fileConfig.paperWidthMm || 80),
        paperHeightMm: Number(fileConfig.paperHeightMm || 0),
        useCustomPaperSize: Boolean(fileConfig.useCustomPaperSize),
        fontFamily: String(fileConfig.fontFamily || 'Arial'),
    };

    if (!Number.isInteger(config.port) || config.port < 1 || config.port > 65535) {
        throw new Error('El campo "port" no es válido.');
    }

    if (config.token.length < 8) {
        throw new Error('El campo "token" debe tener al menos 8 caracteres.');
    }

    if (!Number.isFinite(config.paperWidthMm) || config.paperWidthMm < 30 || config.paperWidthMm > 120) {
        throw new Error('El campo "paperWidthMm" debe estar entre 30 y 120.');
    }

    return config;
}

const config = loadConfig();
let lastPrint = null;

function log(message, details = '') {
    const line = `[${new Date().toISOString()}] ${message}${details ? ` ${details}` : ''}`;
    console.log(line);
    try {
        fs.appendFileSync(LOG_PATH, `${line}${os.EOL}`, 'utf8');
    } catch {
        // El servicio no debe detenerse si no puede escribir el log.
    }
}

function normalizeOrigin(origin) {
    return String(origin || '').replace(/\/$/, '');
}

function isAllowedOrigin(origin) {
    if (!origin) {
        return true;
    }

    const normalized = normalizeOrigin(origin);
    if (config.allowedOrigins.includes(normalized)) {
        return true;
    }

    try {
        const parsed = new URL(normalized);
        return ['localhost', '127.0.0.1', '[::1]'].includes(parsed.hostname);
    } catch {
        return false;
    }
}

function corsHeaders(request) {
    const origin = normalizeOrigin(request.headers.origin || '');
    const headers = {
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Print-Token',
        'Access-Control-Max-Age': '86400',
        'Access-Control-Allow-Private-Network': 'true',
        'Cache-Control': 'no-store',
        Vary: 'Origin, Access-Control-Request-Private-Network',
    };

    if (origin && isAllowedOrigin(origin)) {
        headers['Access-Control-Allow-Origin'] = origin;
    }

    return headers;
}

function sendJson(request, response, statusCode, payload) {
    const body = JSON.stringify(payload);
    response.writeHead(statusCode, {
        ...corsHeaders(request),
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': Buffer.byteLength(body),
    });
    response.end(body);
}

function readJsonBody(request) {
    return new Promise((resolve, reject) => {
        let raw = '';

        request.setEncoding('utf8');
        request.on('data', (chunk) => {
            raw += chunk;
            if (Buffer.byteLength(raw, 'utf8') > MAX_BODY_SIZE) {
                reject(new Error('La petición supera el límite de 1 MB.'));
                request.destroy();
            }
        });
        request.on('end', () => {
            if (!raw.trim()) {
                resolve({});
                return;
            }

            try {
                resolve(JSON.parse(raw));
            } catch {
                reject(new Error('La petición no contiene JSON válido.'));
            }
        });
        request.on('error', reject);
    });
}

function getToken(request, body) {
    const authorization = String(request.headers.authorization || '');
    const bearer = authorization.match(/^Bearer\s+(.+)$/i);
    if (bearer) {
        return bearer[1];
    }

    const headerToken = request.headers['x-print-token'];
    if (typeof headerToken === 'string' && headerToken) {
        return headerToken;
    }

    return typeof body?.token === 'string' ? body.token : '';
}

function psQuote(value) {
    return String(value ?? '').replace(/'/g, "''");
}

function runPowerShell(script, timeoutMs = 30000) {
    return new Promise((resolve, reject) => {
        if (process.platform !== 'win32') {
            reject(new Error('Este servicio solo puede imprimir en Windows.'));
            return;
        }

        const tempDir = path.join(os.tmpdir(), 'easyappointments-local-print');
        fs.mkdirSync(tempDir, { recursive: true });
        const scriptPath = path.join(tempDir, `command-${Date.now()}-${Math.random().toString(16).slice(2)}.ps1`);

        // BOM para que Windows PowerShell 5.1 interprete correctamente los acentos.
        fs.writeFileSync(scriptPath, `\uFEFF${script}`, 'utf8');

        const child = spawn(
            'powershell.exe',
            ['-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', scriptPath],
            { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] },
        );

        let stdout = '';
        let stderr = '';
        let settled = false;

        const timer = setTimeout(() => {
            if (settled) return;
            settled = true;
            child.kill();
            reject(new Error('PowerShell agotó el tiempo máximo de ejecución.'));
        }, timeoutMs);

        child.stdout.on('data', (chunk) => { stdout += chunk.toString(); });
        child.stderr.on('data', (chunk) => { stderr += chunk.toString(); });

        child.on('error', (error) => {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            reject(new Error(`No se pudo iniciar PowerShell: ${error.message}`));
        });

        child.on('close', (code) => {
            if (!config.keepTicketFiles) {
                fs.rm(scriptPath, { force: true }, () => {});
            }

            if (settled) return;
            settled = true;
            clearTimeout(timer);

            if (code !== 0) {
                reject(new Error((stderr || stdout || `PowerShell terminó con el código ${code}.`).trim()));
                return;
            }

            resolve({ stdout: stdout.trim(), stderr: stderr.trim(), scriptPath });
        });
    });
}

async function getPrinters() {
    const result = await runPowerShell(`
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$defaultName = ''
try {
    $defaultName = (Get-CimInstance Win32_Printer -Filter "Default=True" | Select-Object -First 1 -ExpandProperty Name)
} catch {}
$names = @([System.Drawing.Printing.PrinterSettings]::InstalledPrinters | ForEach-Object { [string]$_ })
@($names | ForEach-Object {
    [PSCustomObject]@{
        name = $_
        isDefault = ($_ -eq $defaultName)
    }
}) | ConvertTo-Json -Compress
`);

    if (!result.stdout) return [];
    const parsed = JSON.parse(result.stdout);
    return Array.isArray(parsed) ? parsed : [parsed];
}

function ticketValue(ticket, keys, fallback = '') {
    for (const key of keys) {
        const value = ticket?.[key];
        if (value !== undefined && value !== null && String(value).trim()) {
            return String(value).trim();
        }
    }
    return fallback;
}

function normalizeTicket(body) {
    const ticket = body?.ticket && typeof body.ticket === 'object' ? body.ticket : body;
    const date = ticketValue(ticket, ['date', 'appointmentDate', 'appointment_date', 'fecha']);
    const time = ticketValue(ticket, ['time', 'appointmentTime', 'appointment_time', 'hora']);

    return {
        number: ticketValue(ticket, [
            'queue_number', 'queueNumber', 'number', 'ticketNumber', 'ticket_number',
            'appointmentNumber', 'appointment_number', 'numero', 'número',
        ], '---'),
        department: ticketValue(ticket, [
            'department', 'departmentName', 'department_name', 'departamento',
        ], 'Sin departamento'),
        service: ticketValue(ticket, [
            'service', 'serviceName', 'service_name', 'servicio',
        ], 'Sin servicio'),
        datetime: ticketValue(ticket, [
            'datetime', 'dateTime', 'start_datetime', 'appointmentDateTime',
            'appointment_datetime', 'fechaHora', 'fecha_hora',
        ], [date, time].filter(Boolean).join(' ')),
    };
}

function buildPrintScript(ticketPath) {
    const printerName = psQuote(config.printerName);
    const fontFamily = psQuote(config.fontFamily);
    const useCustomPaper = config.useCustomPaperSize && config.paperHeightMm > 0;

    return `
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$ticket = Get-Content -LiteralPath '${psQuote(ticketPath)}' -Raw -Encoding UTF8 | ConvertFrom-Json
$printerName = '${printerName}'
$fontFamily = '${fontFamily}'

$doc = [System.Drawing.Printing.PrintDocument]::new()
$doc.DocumentName = 'EasyAppointments Ticket'
$doc.PrintController = [System.Drawing.Printing.StandardPrintController]::new()

if ($printerName) {
    $doc.PrinterSettings.PrinterName = $printerName
}

if (-not $doc.PrinterSettings.IsValid) {
    throw "La impresora configurada no es válida para Windows: '$printerName'"
}

$doc.DefaultPageSettings.Margins = [System.Drawing.Printing.Margins]::new(0, 0, 0, 0)
$doc.OriginAtMargins = $false

${useCustomPaper ? `
function MmToHundredthsInch([double]$mm) {
    return [int][Math]::Round(($mm / 25.4) * 100)
}
$paper = [System.Drawing.Printing.PaperSize]::new(
    'EasyAppointments Ticket',
    (MmToHundredthsInch ${config.paperWidthMm}),
    (MmToHundredthsInch ${config.paperHeightMm})
)
$doc.DefaultPageSettings.PaperSize = $paper
` : ''}

$handler = [System.Drawing.Printing.PrintPageEventHandler]{
    param($sender, $e)

    $graphics = $e.Graphics
    $graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::SingleBitPerPixelGridFit

    $pageWidth = [float]$e.PageBounds.Width
    if ($pageWidth -le 0) { $pageWidth = 300 }

    $left = 8.0
    $width = [Math]::Max(100.0, $pageWidth - 16.0)
    $y = 6.0

    $center = [System.Drawing.StringFormat]::new()
    $center.Alignment = [System.Drawing.StringAlignment]::Center
    $center.LineAlignment = [System.Drawing.StringAlignment]::Near

    $fontHeader = [System.Drawing.Font]::new($fontFamily, 11, [System.Drawing.FontStyle]::Bold)
    $fontTitle = [System.Drawing.Font]::new($fontFamily, 12, [System.Drawing.FontStyle]::Bold)
    $fontNumber = [System.Drawing.Font]::new($fontFamily, 46, [System.Drawing.FontStyle]::Bold)
    $fontLabel = [System.Drawing.Font]::new($fontFamily, 8, [System.Drawing.FontStyle]::Bold)
    $fontText = [System.Drawing.Font]::new($fontFamily, 9, [System.Drawing.FontStyle]::Regular)
    $pen = [System.Drawing.Pen]::new([System.Drawing.Color]::Black, 1)

    try {
        $rect = [System.Drawing.RectangleF]::new($left, $y, $width, 20)
        $graphics.DrawString('AYUNTAMIENTO DE', $fontHeader, [System.Drawing.Brushes]::Black, $rect, $center)
        $y += 18

        $rect = [System.Drawing.RectangleF]::new($left, $y, $width, 20)
        $graphics.DrawString('ARANDA DE DUERO', $fontHeader, [System.Drawing.Brushes]::Black, $rect, $center)
        $y += 24

        $graphics.DrawLine($pen, $left + 8, $y, $left + $width - 8, $y)
        $y += 8

        $rect = [System.Drawing.RectangleF]::new($left, $y, $width, 20)
        $graphics.DrawString('SU TURNO', $fontTitle, [System.Drawing.Brushes]::Black, $rect, $center)
        $y += 22

        $rect = [System.Drawing.RectangleF]::new($left, $y, $width, 70)
        $graphics.DrawString([string]$ticket.number, $fontNumber, [System.Drawing.Brushes]::Black, $rect, $center)
        $y += 74

        foreach ($item in @(
            @('DEPARTAMENTO', [string]$ticket.department),
            @('SERVICIO', [string]$ticket.service),
            @('FECHA / HORA', [string]$ticket.datetime)
        )) {
            if ([string]::IsNullOrWhiteSpace($item[1])) { continue }

            $rect = [System.Drawing.RectangleF]::new($left, $y, $width, 15)
            $graphics.DrawString($item[0], $fontLabel, [System.Drawing.Brushes]::Black, $rect, $center)
            $y += 15

            $rect = [System.Drawing.RectangleF]::new($left, $y, $width, 34)
            $graphics.DrawString($item[1], $fontText, [System.Drawing.Brushes]::Black, $rect, $center)
            $y += 36
        }

        $rect = [System.Drawing.RectangleF]::new($left, $y, $width, 32)
        $graphics.DrawString('Espere a que aparezca su turno en pantalla', $fontText, [System.Drawing.Brushes]::Black, $rect, $center)

        $y += 30

        $e.HasMorePages = $false
    }
    finally {
        $center.Dispose()
        $fontHeader.Dispose()
        $fontTitle.Dispose()
        $fontNumber.Dispose()
        $fontLabel.Dispose()
        $fontText.Dispose()
        $pen.Dispose()
    }
}

$doc.add_PrintPage($handler)
try {
    $doc.Print()
    Write-Output ('PRINT_OK|' + $doc.PrinterSettings.PrinterName)
}
finally {
    $doc.remove_PrintPage($handler)
    $doc.Dispose()
}
`;
}

async function printTicket(ticket) {
    const tempDir = path.join(os.tmpdir(), 'easyappointments-local-print');
    fs.mkdirSync(tempDir, { recursive: true });

    const ticketPath = path.join(tempDir, `ticket-${Date.now()}-${Math.random().toString(16).slice(2)}.json`);
    fs.writeFileSync(ticketPath, `\uFEFF${JSON.stringify(ticket)}`, 'utf8');

    try {
        const result = await runPowerShell(buildPrintScript(ticketPath), 45000);
        const printerName = result.stdout.startsWith('PRINT_OK|')
            ? result.stdout.slice('PRINT_OK|'.length)
            : (config.printerName || 'predeterminada de Windows');

        return { printerName, output: result.stdout };
    } finally {
        if (!config.keepTicketFiles) {
            fs.rm(ticketPath, { force: true }, () => {});
        }
    }
}

async function handlePrint(request, response, body) {
    if (getToken(request, body) !== config.token) {
        sendJson(request, response, 401, { ok: false, error: 'Token de impresión no válido.' });
        return;
    }

    const ticket = normalizeTicket(body);
    log('Petición de impresión recibida.', JSON.stringify({
        origin: request.headers.origin || '',
        printer: config.printerName || 'default',
        number: ticket.number,
    }));

    try {
        const result = await printTicket(ticket);
        lastPrint = {
            ok: true,
            at: new Date().toISOString(),
            printerName: result.printerName,
            number: ticket.number,
        };
        log('Trabajo enviado correctamente al spooler.', JSON.stringify(lastPrint));
        sendJson(request, response, 200, {
            ok: true,
            message: 'Trabajo enviado a la cola de impresión de Windows.',
            printerName: result.printerName,
        });
    } catch (error) {
        lastPrint = {
            ok: false,
            at: new Date().toISOString(),
            error: error instanceof Error ? error.message : String(error),
        };
        log('ERROR de impresión.', lastPrint.error);
        sendJson(request, response, 500, { ok: false, error: lastPrint.error });
    }
}

const server = http.createServer(async (request, response) => {
    const requestUrl = new URL(request.url, `http://${request.headers.host || '127.0.0.1'}`);
    const origin = request.headers.origin || '';

    if (origin && !isAllowedOrigin(origin)) {
        log('Origen CORS rechazado.', origin);
        sendJson(request, response, 403, {
            ok: false,
            error: `Origen no permitido: ${origin}. Añádelo a allowedOrigins en config.json.`,
        });
        return;
    }

    if (request.method === 'OPTIONS') {
        response.writeHead(204, corsHeaders(request));
        response.end();
        return;
    }

    if (request.method === 'GET' && requestUrl.pathname === '/health') {
        sendJson(request, response, 200, {
            ok: true,
            service: 'easyappointments-local-print',
            host: config.host,
            port: config.port,
            configuredPrinter: config.printerName || 'Windows default printer',
            lastPrint,
            logFile: LOG_PATH,
        });
        return;
    }

    if (request.method === 'GET' && requestUrl.pathname === '/printers') {
        try {
            const printers = await getPrinters();
            sendJson(request, response, 200, {
                ok: true,
                configuredPrinter: config.printerName || 'Windows default printer',
                printers,
            });
        } catch (error) {
            sendJson(request, response, 500, {
                ok: false,
                error: error instanceof Error ? error.message : String(error),
            });
        }
        return;
    }

    if (request.method === 'POST' && requestUrl.pathname === '/print') {
        try {
            const body = await readJsonBody(request);
            await handlePrint(request, response, body);
        } catch (error) {
            sendJson(request, response, 400, {
                ok: false,
                error: error instanceof Error ? error.message : String(error),
            });
        }
        return;
    }

    if (request.method === 'POST' && requestUrl.pathname === '/test-print') {
        try {
            const body = await readJsonBody(request);
            await handlePrint(request, response, {
                ...body,
                number: 'TEST',
                department: 'PRUEBA DE IMPRESIÓN',
                service: 'EasyAppointments',
                datetime: new Date().toLocaleString('es-ES'),
            });
        } catch (error) {
            sendJson(request, response, 400, {
                ok: false,
                error: error instanceof Error ? error.message : String(error),
            });
        }
        return;
    }

    sendJson(request, response, 404, { ok: false, error: 'Endpoint no encontrado.' });
});

server.on('clientError', (error, socket) => {
    log('Error de cliente.', error.message);
    socket.end('HTTP/1.1 400 Bad Request\r\n\r\n');
});

server.listen(config.port, config.host, () => {
    log('EasyAppointments Local Print Service iniciado.');
    log(`Escuchando en http://${config.host}:${config.port}`);
    log(`Impresora configurada: ${config.printerName || 'predeterminada de Windows'}`);
    log(`Diagnóstico: http://${config.host}:${config.port}/printers`);
});
