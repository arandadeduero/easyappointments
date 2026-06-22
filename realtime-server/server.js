'use strict';

const http = require('http');
const { WebSocket, WebSocketServer } = require('ws');

const host = process.env.REALTIME_HOST || '127.0.0.1';
const port = Number(process.env.REALTIME_PORT || 8081);
const secret = (process.env.REALTIME_SECRET || '').trim();

if (!Number.isInteger(port) || port < 1 || port > 65535) {
    console.error('[realtime] REALTIME_PORT is invalid');
    process.exit(1);
}

if (!secret) {
    console.error('[realtime] REALTIME_SECRET is required');
    process.exit(1);
}

const clients = new Set();

const sendJson = (res, statusCode, payload) => {
    res.writeHead(statusCode, {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-store',
    });

    res.end(JSON.stringify(payload));
};

const server = http.createServer((req, res) => {
    if (req.method === 'GET' && req.url === '/health') {
        sendJson(res, 200, {
            ok: true,
            clients: clients.size,
        });

        return;
    }

    if (req.method === 'POST' && req.url === '/publish') {
        const incomingSecret = req.headers['x-realtime-secret'];

        if (incomingSecret !== secret) {
            sendJson(res, 401, {
                error: 'Unauthorized',
            });

            return;
        }

        let rawBody = '';
        let bodyTooLarge = false;

        req.on('data', (chunk) => {
            if (bodyTooLarge) {
                return;
            }

            rawBody += chunk;

            if (Buffer.byteLength(rawBody, 'utf8') > 1024 * 1024) {
                bodyTooLarge = true;

                sendJson(res, 413, {
                    error: 'Payload too large',
                });

                req.destroy();
            }
        });

        req.on('end', () => {
            if (bodyTooLarge || res.writableEnded) {
                return;
            }

            try {
                const message = JSON.parse(rawBody || '{}');

                const payload = JSON.stringify({
                    event: message.event || 'calendar.unknown',
                    data: message.data || {},
                    sent_at: message.sent_at || new Date().toISOString(),
                });

                let deliveredTo = 0;

                for (const client of clients) {
                    if (client.readyState === WebSocket.OPEN) {
                        client.send(payload);
                        deliveredTo += 1;
                    }
                }

                sendJson(res, 200, {
                    ok: true,
                    delivered_to: deliveredTo,
                });
            } catch (error) {
                sendJson(res, 400, {
                    error: 'Invalid JSON payload',
                });
            }
        });

        req.on('error', (error) => {
            console.error('[realtime] request error:', error.message);
        });

        return;
    }

    sendJson(res, 404, {
        error: 'Not found',
    });
});

const wsServer = new WebSocketServer({ server });

wsServer.on('connection', (socket) => {
    clients.add(socket);

    socket.send(
        JSON.stringify({
            event: 'calendar.connected',
            data: {
                connected_clients: clients.size,
            },
            sent_at: new Date().toISOString(),
        }),
    );

    socket.on('close', () => {
        clients.delete(socket);
    });

    socket.on('error', (error) => {
        console.error('[realtime] websocket error:', error.message);
        clients.delete(socket);
    });
});

const shutdown = (signal) => {
    console.log(`[realtime] received ${signal}, shutting down`);

    for (const client of clients) {
        if (
            client.readyState === WebSocket.OPEN ||
            client.readyState === WebSocket.CONNECTING
        ) {
            client.close(1001, 'Server shutting down');
        }
    }

    wsServer.close(() => {
        server.close(() => {
            console.log('[realtime] server stopped');
            process.exit(0);
        });
    });

    setTimeout(() => {
        console.error('[realtime] forced shutdown');
        process.exit(1);
    }, 10000).unref();
};

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

server.on('error', (error) => {
    console.error('[realtime] server error:', error);
    process.exit(1);
});

server.listen(port, host, () => {
    console.log(
        `[realtime] websocket server running on http://${host}:${port}`,
    );
});
