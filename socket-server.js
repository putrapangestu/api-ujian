import 'dotenv/config';
import http from 'http';
import { Server } from 'socket.io';

const port = Number.parseInt(process.env.SOCKET_IO_PORT ?? '3001', 10);
const key = process.env.SOCKET_IO_KEY ?? '';
const clients = new Map();

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url ?? '/', `http://${req.headers.host ?? 'localhost'}`);

    if (req.method === 'GET' && url.pathname === '/health') {
        res.writeHead(200, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', timestamp: new Date().toISOString() }));
        return;
    }

    if (req.method === 'GET' && url.pathname === '/clients') {
        const list = Array.from(clients.values());
        res.writeHead(200, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ count: list.length, clients: list, timestamp: new Date().toISOString() }));
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

io.engine.on('connection_error', (err) => {
    console.warn(JSON.stringify({
        event: 'engine_connection_error',
        message: err?.message ?? String(err),
        code: err?.code ?? null,
        context: err?.context ?? null,
        timestamp: new Date().toISOString(),
    }));
});

io.use((socket, next) => {
    const clientKey =
        socket.handshake.auth?.key ??
        socket.handshake.query?.key ??
        socket.handshake.headers?.['x-socket-key'];
    if (clientKey === key) {
        console.log(JSON.stringify({
            event: 'handshake_auth_ok',
            key_present: !!clientKey,
            address: socket.handshake.address ?? null,
            exam_id: socket.handshake.query?.exam_id ?? null,
            student_id: socket.handshake.query?.student_id ?? null,
            timestamp: new Date().toISOString(),
        }));
        next();
    } else {
        console.warn(JSON.stringify({
            event: 'handshake_auth_fail',
            key_present: !!clientKey,
            address: socket.handshake.address ?? null,
            exam_id: socket.handshake.query?.exam_id ?? null,
            student_id: socket.handshake.query?.student_id ?? null,
            timestamp: new Date().toISOString(),
        }));
        next(new Error('unauthorized'));
    }
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

    clients.set(socket.id, {
        id: socket.id,
        exam_id: examId ?? null,
        student_id: studentId ?? null,
        rooms: Array.from(socket.rooms),
        connected_at: new Date().toISOString(),
    });

    if (examId) {
        socket.to(`exam:${examId}`).emit('user_connected', {
            id: socket.id,
            exam_id: examId ?? null,
            student_id: studentId ?? null,
            timestamp: new Date().toISOString(),
        });
    }

    socket.emit('connected', {
        id: socket.id,
        exam_id: examId ?? null,
        student_id: studentId ?? null,
        rooms: Array.from(socket.rooms),
        timestamp: new Date().toISOString(),
    });

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

    socket.on('answer_sync', (payload, callback) => {
        const body = (payload?.data ?? payload) ?? {};
        const room = examId ? `exam:${examId}` : '';
        const base = {
            exam_id: body.exam_id ?? examId ?? null,
            student_id: body.student_id ?? studentId ?? null,
            action: body.action ?? 'ANSWER',
        };
        const items = Array.isArray(body.answers)
            ? body.answers.map((a) => ({ ...base, ...a }))
            : [{ ...base, ...body }];

        console.log(JSON.stringify({
            event: 'answer_sync_received',
            id: socket.id,
            count: items.length,
            exam_id: base.exam_id,
            student_id: base.student_id,
            timestamp: new Date().toISOString(),
        }));

        for (const item of items) {
            const data = {
                id: item.id ?? null,
                user_exam_id: item.user_exam_id ?? null,
                student_id: item.student_id,
                exam_id: item.exam_id,
                action: item.action,
                question_id: item.question_id,
                answer: item.answer,
                time_spent: item.time_spent,
                created_at: item.created_at ?? new Date().toISOString(),
            };
            if (room) {
                io.to(room).emit('exam_action', { channel: 'exam-monitoring', data });
            } else {
                io.emit('exam_action', { channel: 'exam-monitoring', data });
            }
        }

        const ack = {
            status: 'ok',
            received: items.length,
            timestamp: new Date().toISOString(),
        };
        if (typeof callback === 'function') {
            callback(ack);
        } else {
            socket.emit('answer_sync_ack', ack);
        }
    });

    socket.on('status_update', (payload) => {
        const body = (payload?.data ?? payload) ?? {};
        const room = examId ? `exam:${examId}` : '';
        
        const data = {
            student_id: body.student_id ?? studentId ?? null,
            exam_id: body.exam_id ?? examId ?? null,
            is_focused: body.is_focused ?? true,
            current_question_id: body.current_question_id ?? null,
            answered_count: body.answered_count ?? 0,
            timestamp: new Date().toISOString(),
        };

        if (clients.has(socket.id)) {
            const clientData = clients.get(socket.id);
            clients.set(socket.id, { ...clientData, last_status: data });
        }

        if (room) {
            socket.to(room).emit('student_status', { channel: 'exam-monitoring', data });
        } else {
            socket.broadcast.emit('student_status', { channel: 'exam-monitoring', data });
        }
    });

    socket.on('request_active_students', (payload, callback) => {
        const targetExamId = payload?.exam_id ?? examId;
        if (!targetExamId) {
            if (typeof callback === 'function') callback({ error: 'exam_id_required' });
            return;
        }
        
        const activeStudents = Array.from(clients.values())
            .filter(c => c.exam_id === targetExamId && c.student_id != null)
            .map(c => ({
                socket_id: c.id,
                student_id: c.student_id,
                connected_at: c.connected_at,
                last_status: c.last_status ?? null
            }));
            
        if (typeof callback === 'function') {
            callback({ status: 'ok', data: activeStudents });
        } else {
            socket.emit('active_students_list', { channel: 'exam-monitoring', exam_id: targetExamId, data: activeStudents });
        }
    });

    socket.on('disconnect', (reason) => {
        console.log(JSON.stringify({
            event: 'socket_disconnected',
            id: socket.id,
            reason,
            timestamp: new Date().toISOString(),
        }));
        if (examId) {
            socket.to(`exam:${examId}`).emit('user_disconnected', {
                id: socket.id,
                exam_id: examId ?? null,
                student_id: studentId ?? null,
                reason,
                timestamp: new Date().toISOString(),
            });
        }
        clients.delete(socket.id);
    });
});

server.listen(port, '0.0.0.0');
server.on('listening', () => {
    console.log(JSON.stringify({
        event: 'server_listening',
        port,
        host: '0.0.0.0',
        timestamp: new Date().toISOString(),
    }));
});
server.on('error', (err) => {
    console.error(JSON.stringify({
        event: 'server_error',
        message: err?.message ?? String(err),
        code: err?.code ?? null,
        timestamp: new Date().toISOString(),
    }));
});
