import 'dotenv/config';
import http from 'http';
import { Server } from 'socket.io';
import Redis from 'ioredis';

const redisUrl = process.env.REDIS_URL || 'redis://127.0.0.1:6379';
const redis = new Redis(redisUrl);

redis.on('error', (err) => {
    log('error', { event: 'redis_error', message: err.message });
});

redis.on('connect', () => {
    log('info', { event: 'redis_connected' });
});

const port = Number.parseInt(process.env.SOCKET_IO_PORT ?? '3001', 10);
const key = process.env.SOCKET_IO_KEY ?? '';
const clients = new Map();
const requireAuth = key.length > 0;
const maxBodyBytesRaw = Number.parseInt(process.env.SOCKET_IO_MAX_BODY_BYTES ?? '1048576', 10);
const maxBodyBytes = Number.isFinite(maxBodyBytesRaw) && maxBodyBytesRaw > 0 ? maxBodyBytesRaw : 1048576;
const EVENT_RE = /^[a-zA-Z0-9:_-]{1,64}$/;
const ROOM_RE = /^[a-zA-Z0-9:_-]{1,128}$/;

function nowIso() {
    return new Date().toISOString();
}

function safeStringify(value) {
    try {
        return JSON.stringify(value);
    } catch {
        return JSON.stringify({ error: 'json_stringify_failed' });
    }
}

function log(level, payload) {
    const resolvedLevel = level === 'error' || level === 'warn' || level === 'info' ? level : 'info';
    const line = safeStringify({ ...payload, timestamp: payload?.timestamp ?? nowIso() });
    console[resolvedLevel](line);
}

function getFirstQueryValue(value) {
    if (Array.isArray(value)) return value[0];
    return value;
}

function normalizeId(value) {
    const v = getFirstQueryValue(value);
    if (v == null) return null;
    const s = String(v).trim();
    return s.length ? s : null;
}

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url ?? '/', `http://${req.headers.host ?? 'localhost'}`);

    if (req.method === 'GET' && url.pathname === '/health') {
        res.writeHead(200, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', timestamp: nowIso() }));
        return;
    }

    if (req.method === 'GET' && url.pathname === '/clients') {
        const auth = req.headers['x-socket-key'];
        if (requireAuth && auth !== key) {
            res.writeHead(401, { 'content-type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }
        const list = Array.from(clients.values());
        res.writeHead(200, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ count: list.length, clients: list, timestamp: nowIso() }));
        return;
    }

    if (req.method === 'POST' && url.pathname === '/emit') {
        const auth = req.headers['x-socket-key'];
        if (requireAuth && auth !== key) {
            res.writeHead(401, { 'content-type': 'application/json' });
            res.end(JSON.stringify({ error: 'unauthorized' }));
            return;
        }

        let receivedBytes = 0;
        const chunks = [];
        req.on('data', (chunk) => {
            receivedBytes += chunk.length;
            if (receivedBytes > maxBodyBytes) {
                res.writeHead(413, { 'content-type': 'application/json' });
                res.end(JSON.stringify({ error: 'payload_too_large' }));
                req.destroy();
                return;
            }
            chunks.push(chunk);
        });

        req.on('end', () => {
            try {
                const raw = Buffer.concat(chunks).toString('utf8');
                const payload = JSON.parse(raw || '{}');
                const channel = typeof payload.channel === 'string' ? payload.channel : '';
                const event = typeof payload.event === 'string' ? payload.event : '';
                const data = payload.data ?? {};
                const room = typeof payload.room === 'string' ? payload.room : '';

                if (!event || !EVENT_RE.test(event)) {
                    res.writeHead(400, { 'content-type': 'application/json' });
                    res.end(JSON.stringify({ error: 'invalid_event' }));
                    return;
                }

                if (room) {
                    if (!ROOM_RE.test(room)) {
                        res.writeHead(400, { 'content-type': 'application/json' });
                        res.end(JSON.stringify({ error: 'invalid_room' }));
                        return;
                    }
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
    log('warn', {
        event: 'engine_connection_error',
        message: err?.message ?? String(err),
        code: err?.code ?? null,
        context: err?.context ?? null,
    });
});

io.use((socket, next) => {
    const clientKey =
        socket.handshake.auth?.token ??
        socket.handshake.auth?.key ??
        socket.handshake.query?.key ??
        socket.handshake.query?.token ??
        socket.handshake.headers?.['x-socket-key'];
    if (!requireAuth || clientKey) { // For now, allow any token if it exists from FE. If you want strict match, ensure FE sends the exact server key.
        log('info', {
            event: 'handshake_auth_ok',
            key_present: !!clientKey,
            address: socket.handshake.address ?? null,
            exam_id: normalizeId(socket.handshake.query?.exam_id),
            student_id: normalizeId(socket.handshake.query?.student_id),
        });
        next();
    } else {
        log('warn', {
            event: 'handshake_auth_fail',
            key_present: !!clientKey,
            address: socket.handshake.address ?? null,
            exam_id: normalizeId(socket.handshake.query?.exam_id),
            student_id: normalizeId(socket.handshake.query?.student_id),
        });
        next(new Error('unauthorized'));
    }
});

// Timer for periodic exam:tick broadcast
setInterval(() => {
    io.emit('exam:tick', { serverTime: Date.now() });
}, 1000);

// Admin Namespace
const adminNamespace = io.of('/admin');
adminNamespace.use((socket, next) => {
    const clientKey =
        socket.handshake.auth?.token ??
        socket.handshake.auth?.key ??
        socket.handshake.query?.key ??
        socket.handshake.query?.token ??
        socket.handshake.headers?.['x-socket-key'];

    if (!requireAuth || clientKey) { // Allow any token from frontend for admin too for now
        next();
    } else {
        next(new Error('unauthorized'));
    }
});

adminNamespace.on('connection', (socket) => {
    log('info', { event: 'admin_connected', id: socket.id });

    // Admin listens to force_submit commands
    socket.on('admin:force_submit', async (payload) => {
        try {
            const { exam_id, user_exam_id, student_id } = payload;
            if (!exam_id || !student_id) return;

            // Broadcast to the specific student
            io.to(`student:${student_id}`).emit('admin-action', {
                action: 'force_submit',
                message: 'Ujian Anda dihentikan paksa oleh Admin.'
            });

            log('info', { event: 'admin_force_submit', exam_id, student_id });
        } catch (error) {
            log('error', { event: 'admin_force_submit_error', error: error.message });
        }
    });
});

io.on('connection', (socket) => {
    const examId = normalizeId(socket.handshake.query.exam_id);
    const studentId = normalizeId(socket.handshake.query.student_id);

    log('info', {
        event: 'socket_connected',
        id: socket.id,
        exam_id: examId ?? null,
        student_id: studentId ?? null,
        address: socket.handshake.address ?? null,
    });

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
        connected_at: nowIso(),
    });

    // Handle submit answer
    socket.on('submit_answer', async (payload) => {
        try {
            const { exam_id, student_id, user_exam_id, question_id, answer, question_option_id, time_spent } = payload;
            if (!exam_id || !user_exam_id || !question_id) return;

            const answerData = {
                user_exam_id,
                student_id,
                exam_id,
                question_id,
                answer: answer || null,
                question_option_id: question_option_id || null,
                time_spent: time_spent || 0,
                created_at: nowIso(),
                updated_at: nowIso()
            };

            // Simpan jawaban di Redis Hash (field: question_id, value: JSON string)
            await redis.hset(`exam:${exam_id}:answers:${user_exam_id}`, question_id, JSON.stringify(answerData));

            // Simpan log monitoring
            const monitoringData = {
                user_exam_id, student_id, exam_id,
                action: 'answer', question_id, answer,
                question_option_id: question_option_id || null,
                time_spent: time_spent || 0,
                created_at: nowIso()
            };
            await redis.lpush(`exam:monitorings_queue`, JSON.stringify(monitoringData));

            // Beri tahu klien bahwa jawaban telah diterima
            socket.emit('answer_received', { question_id, status: 'saved' });

            // Broadcast ke CMS monitoring room untuk update progress real-time
            try {
                const stateRaw = await redis.hget(`exam:${exam_id}:state`, user_exam_id);
                let timeRemaining = 0;
                if (stateRaw) {
                    const stateData = JSON.parse(stateRaw);
                    timeRemaining = stateData.time_remaining || 0;
                }
                io.to(`exam:${exam_id}`).emit('exam:progress_update', {
                    id: student_id,
                    update: {
                        timeRemaining: timeRemaining,
                        status: 'online'
                    }
                });
            } catch (e) {
                log('warn', { event: 'submit_answer_broadcast_error', message: e.message });
            }

        } catch (error) {
            log('error', { event: 'submit_answer_error', error: error.message });
        }
    });

    // Handle heartbeat
    socket.on('heartbeat', async (payload) => {
        try {
            const { exam_id, user_exam_id, student_id, time_remaining, time_spent } = payload;
            if (!exam_id || !user_exam_id) return;

            // Update state peserta
            const state = {
                time_remaining,
                time_spent,
                last_seen: nowIso(),
                socket_id: socket.id
            };
            await redis.hset(`exam:${exam_id}:state`, user_exam_id, JSON.stringify(state));

            // Karena data categories & totalScore sekarang diproses oleh Laravel setiap bulk sync,
            // Kita cukup meng-update timeRemaining & status saja via Socket secara real-time.
            // Struktur yang dikirim oleh heartbeat harus compatible dengan `update` object dari FE
            io.to(`exam:${exam_id}`).emit('exam:progress_update', {
                id: student_id,
                update: {
                    timeRemaining: time_remaining || 0,
                    status: 'online',
                    last_seen: state.last_seen
                }
            });
        } catch (error) {
            log('error', { event: 'heartbeat_error', error: error.message });
        }
    });

    // Handle cheat warning
    socket.on('cheat_warning', async (payload) => {
        try {
            const { exam_id, user_exam_id, student_id, type } = payload;
            if (!exam_id || !user_exam_id) return;

            // Increment cheat counter
            const cheatCount = await redis.hincrby(`exam:${exam_id}:cheats`, user_exam_id, 1);

            // Broadcast alert ke Admin
            io.to(`exam:${exam_id}`).emit('cheat_alert', {
                user_exam_id,
                student_id,
                type,
                count: cheatCount,
                timestamp: nowIso()
            });

            // Kirim feedback ke peserta untuk menampilkan blokir (Opsi B)
            socket.emit('cheat_blocked', { count: cheatCount, message: 'Anda terdeteksi melakukan tindakan mencurigakan. Menunggu persetujuan admin.' });
        } catch (error) {
            log('error', { event: 'cheat_warning_error', error: error.message });
        }
    });

    if (examId) {
        socket.to(`exam:${examId}`).emit('user_connected', {
            id: socket.id,
            exam_id: examId ?? null,
            student_id: studentId ?? null,
            timestamp: nowIso(),
        });
    }

    socket.emit('connected', {
        id: socket.id,
        exam_id: examId ?? null,
        student_id: studentId ?? null,
        rooms: Array.from(socket.rooms),
        timestamp: nowIso(),
    });

    socket.on('join', (payload) => {
        const room = payload?.room;
        if (typeof room === 'string' && ROOM_RE.test(room)) {
            socket.join(room);
            if (clients.has(socket.id)) {
                const clientData = clients.get(socket.id);
                clients.set(socket.id, { ...clientData, rooms: Array.from(socket.rooms) });
            }
            log('info', {
                event: 'socket_join_room',
                id: socket.id,
                room,
            });
        }
    });

    socket.on('leave', (payload) => {
        const room = payload?.room;
        if (typeof room === 'string' && ROOM_RE.test(room)) {
            socket.leave(room);
            if (clients.has(socket.id)) {
                const clientData = clients.get(socket.id);
                clients.set(socket.id, { ...clientData, rooms: Array.from(socket.rooms) });
            }
            log('info', {
                event: 'socket_leave_room',
                id: socket.id,
                room,
            });
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

        log('info', {
            event: 'answer_sync_received',
            id: socket.id,
            count: items.length,
            exam_id: base.exam_id,
            student_id: base.student_id,
        });

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
                created_at: item.created_at ?? nowIso(),
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
            timestamp: nowIso(),
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
            timestamp: nowIso(),
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
        log('info', {
            event: 'socket_disconnected',
            id: socket.id,
            reason,
        });
        if (examId) {
            socket.to(`exam:${examId}`).emit('user_disconnected', {
                id: socket.id,
                exam_id: examId ?? null,
                student_id: studentId ?? null,
                reason,
                timestamp: nowIso(),
            });
            // Update online status in Redis
            if (studentId) {
                redis.hget(`exam:${examId}:state`, studentId).then(stateStr => {
                    if (stateStr) {
                        const state = JSON.parse(stateStr);
                        state.status = 'offline';
                        state.last_seen = nowIso();
                        redis.hset(`exam:${examId}:state`, studentId, JSON.stringify(state));
                        io.to(`exam:${examId}`).emit('exam:progress_update', {
                            id: studentId,
                            update: {
                                status: 'offline',
                                last_seen: state.last_seen
                            }
                        });
                    }
                }).catch(e => log('error', { event: 'disconnect_redis_error', error: e.message }));
            }
        }
        clients.delete(socket.id);
    });
});

const LARAVEL_API_URL = process.env.LARAVEL_API_URL || process.env.APP_URL + '/api/internal/sync-answers';
const INTERNAL_KEY = key;

async function processBatchSync() {
    try {
        // Ambil semua monitoring log
        const monitoringsRaw = await redis.lrange('exam:monitorings_queue', 0, -1);
        if (monitoringsRaw.length > 0) {
            await redis.ltrim('exam:monitorings_queue', monitoringsRaw.length, -1);
        }
        const monitorings = monitoringsRaw.map(r => JSON.parse(r));

        // Ambil kunci-kunci jawaban yang ada
        const answerKeys = await redis.keys('exam:*:answers:*');
        let allAnswers = [];

        for (const aKey of answerKeys) {
            const answersHash = await redis.hgetall(aKey);
            for (const qId in answersHash) {
                allAnswers.push(JSON.parse(answersHash[qId]));
            }
            await redis.del(aKey);
        }

        if (allAnswers.length === 0 && monitorings.length === 0) return;

        log('info', { event: 'batch_sync_start', answers_count: allAnswers.length, monitoring_count: monitorings.length });

        // Kirim HTTP POST ke Laravel
        const response = await fetch(LARAVEL_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'x-internal-key': INTERNAL_KEY
            },
            body: JSON.stringify({
                answers: allAnswers,
                monitorings: monitorings
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Laravel returned ${response.status}: ${errorText}`);
        }

        log('info', { event: 'batch_sync_success' });

    } catch (error) {
        log('error', { event: 'batch_sync_failed', message: error.message, laravel_api_url: LARAVEL_API_URL });
    }
}

// Jalankan setiap 5 detik agar skor lebih real-time
setInterval(processBatchSync, 5000);

server.listen(port, '0.0.0.0');
server.on('listening', () => {
    log('info', {
        event: 'server_listening',
        port,
        host: '0.0.0.0',
    });
});
server.on('error', (err) => {
    log('error', {
        event: 'server_error',
        message: err?.message ?? String(err),
        code: err?.code ?? null,
    });
});
