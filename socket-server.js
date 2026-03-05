import http from 'http';
import { Server } from 'socket.io';

const port = Number.parseInt(process.env.SOCKET_IO_PORT ?? '3001', 10);
const key = process.env.SOCKET_IO_KEY ?? '';

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url ?? '/', `http://${req.headers.host ?? 'localhost'}`);

    if (req.method === 'GET' && url.pathname === '/health') {
        res.writeHead(200, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', timestamp: new Date().toISOString() }));
        return;
    }

    if (req.method === 'POST' && url.pathname === '/emit') {
        const auth = req.headers['x-socket-key'];
        if (key && auth !== key) {
            res.writeHead(401, { 'content-type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }

        let body = '';
        req.on('data', (chunk) => {
            body += chunk;
        });

        req.on('end', () => {
            try {
                const payload = JSON.parse(body || '{}');
                const channel = payload.channel ?? '';
                const event = payload.event ?? '';
                const data = payload.data ?? {};
                const room = payload.room ?? '';

                if (!event) {
                    res.writeHead(400, { 'content-type': 'application/json' });
                    res.end(JSON.stringify({ error: 'event_required' }));
                    return;
                }

                if (room) {
                    io.to(room).emit(event, { channel, data });
                } else {
                    io.emit(event, { channel, data });
                }

                res.writeHead(200, { 'content-type': 'application/json' });
                res.end(JSON.stringify({ status: 'sent' }));
            } catch (error) {
                res.writeHead(400, { 'content-type': 'application/json' });
                res.end(JSON.stringify({ error: 'invalid_payload' }));
            }
        });
        return;
    }

    res.writeHead(404, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ error: 'not_found' }));
});

const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST'],
    },
});

io.on('connection', (socket) => {
    const examId = socket.handshake.query.exam_id;
    const studentId = socket.handshake.query.student_id;

    console.log(JSON.stringify({
        event: 'socket_connected',
        id: socket.id,
        exam_id: examId ?? null,
        student_id: studentId ?? null,
        address: socket.handshake.address ?? null,
        timestamp: new Date().toISOString(),
    }));

    if (examId) {
        socket.join(`exam:${examId}`);
    }

    if (studentId) {
        socket.join(`student:${studentId}`);
    }

    socket.on('join', (payload) => {
        const room = payload?.room;
        if (room) {
            socket.join(room);
            console.log(JSON.stringify({
                event: 'socket_join_room',
                id: socket.id,
                room,
                timestamp: new Date().toISOString(),
            }));
        }
    });

    socket.on('disconnect', (reason) => {
        console.log(JSON.stringify({
            event: 'socket_disconnected',
            id: socket.id,
            reason,
            timestamp: new Date().toISOString(),
        }));
    });
});

server.listen(port, '0.0.0.0');
