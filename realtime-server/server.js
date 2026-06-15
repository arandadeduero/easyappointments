const http = require('http');
const { WebSocketServer } = require('ws');

const port = Number(process.env.REALTIME_PORT || 8081);
const secret = process.env.REALTIME_SECRET || 'change-this-secret';

const clients = new Set();

const server = http.createServer((req, res) => {
    if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true, clients: clients.size }));
        return;
    }

    if (req.method === 'POST' && req.url === '/publish') {
        const incomingSecret = req.headers['x-realtime-secret'];

        if (secret && incomingSecret !== secret) {
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Unauthorized' }));
            return;
        }

        let rawBody = '';

        req.on('data', (chunk) => {
            rawBody += chunk;

            if (rawBody.length > 1024 * 1024) {
                req.destroy();
            }
        });

        req.on('end', () => {
            try {
                const message = JSON.parse(rawBody || '{}');
                const payload = JSON.stringify({
                    event: message.event || 'calendar.unknown',
                    data: message.data || {},
                    sent_at: message.sent_at || new Date().toISOString(),
                });

                for (const client of clients) {
                    if (client.readyState === client.OPEN) {
                        client.send(payload);
                    }
                }

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ ok: true, delivered_to: clients.size }));
            } catch (error) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'Invalid JSON payload' }));
            }
        });

        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'Not found' }));
});

const wsServer = new WebSocketServer({ server });

wsServer.on('connection', (socket) => {
    clients.add(socket);

    socket.send(
        JSON.stringify({
            event: 'calendar.connected',
            data: { connected_clients: clients.size },
            sent_at: new Date().toISOString(),
        }),
    );

    socket.on('close', () => {
        clients.delete(socket);
    });

    socket.on('error', () => {
        clients.delete(socket);
    });
});

server.listen(port, '0.0.0.0', () => {
    console.log(`[realtime] websocket server running on port ${port}`);
});
