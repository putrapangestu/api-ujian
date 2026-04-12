# WebSocket Realtime Exam Monitoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan sistem pengumpulan jawaban, kalkulasi skor, monitoring waktu, dan deteksi kecurangan secara realtime via WebSocket (Socket.IO) untuk sistem ujian CPNS.

**Architecture:** 
Node.js (Socket.IO) akan bertindak sebagai *realtime state manager* dan *message broker* yang menyimpan jawaban sementara di Redis. Node.js akan melakukan *bulk-sync* ke Laravel API secara berkala (batching). Laravel akan mengkalkulasi skor (`current_score`) dan mengembalikannya ke Node.js untuk di-broadcast ke Admin *dashboard*. Deteksi kecurangan dilakukan di frontend dan ditangani melalui *warning* atau intervensi admin (Force Submit/Unblock).

**Tech Stack:** Node.js, Socket.IO, Redis, Laravel 10+, MySQL.

---

### Task 1: Persiapan Database Laravel & Redis Connection Node.js

**Tujuan:** Menambahkan kolom `current_score` ke tabel `user_exams` untuk menyimpan nilai realtime, serta menyiapkan koneksi Redis di Node.js.

**Files:**
- Create: `database/migrations/2024_04_13_000000_add_current_score_to_user_exams_table.php`
- Modify: `app/Models/UserExam.php`
- Modify: `socket-server.js`

- [ ] **Step 1: Buat file migration untuk `current_score`**
```bash
php artisan make:migration add_current_score_to_user_exams_table --table=user_exams
```

- [ ] **Step 2: Isi file migration**
Buka file migration yang baru dibuat dan tambahkan kode berikut:
```php
public function up(): void
{
    Schema::table('user_exams', function (Blueprint $table) {
        $table->integer('current_score')->default(0)->after('score')->comment('Skor sementara realtime');
    });
}

public function down(): void
{
    Schema::table('user_exams', function (Blueprint $table) {
        $table->dropColumn('current_score');
    });
}
```

- [ ] **Step 3: Tambahkan `current_score` ke `fillable` di Model**
Buka `app/Models/UserExam.php` dan tambahkan `current_score` ke dalam array `$fillable`:
```php
// app/Models/UserExam.php
// Tambahkan di dalam properti $fillable
'current_score',
```

- [ ] **Step 4: Setup Redis di Node.js (`socket-server.js`)**
Buka `socket-server.js` dan tambahkan koneksi Redis menggunakan *ioredis* atau redis client bawaan.
```javascript
// Di bagian atas socket-server.js
import Redis from 'ioredis';

const redis = new Redis(process.env.REDIS_URL || 'redis://127.0.0.1:6379');

redis.on('error', (err) => {
    log('error', { event: 'redis_error', message: err.message });
});

redis.on('connect', () => {
    log('info', { event: 'redis_connected' });
});
```

- [ ] **Step 5: Commit Perubahan**
```bash
git add database/migrations/ app/Models/UserExam.php socket-server.js
git commit -m "feat: setup current_score column and redis connection"
```

---

### Task 2: Implementasi Endpoint Bulk Sync di Laravel

**Tujuan:** Membuat endpoint API internal yang akan menerima *batch* jawaban dan log monitoring dari Node.js, lalu menyimpan/menghitungnya secara efisien di MySQL.

**Files:**
- Modify: `routes/api.php`
- Create: `app/Http/Controllers/Api/Internal/ExamSyncController.php`
- Modify: `app/Services/ExamMonitoringService.php`

- [ ] **Step 1: Tambahkan Route Internal**
Buka `routes/api.php` dan tambahkan route khusus (tanpa Sanctum auth biasa, lindungi dengan secret key).
```php
// routes/api.php
Route::prefix('internal')->middleware('internal.api.key')->group(function () {
    Route::post('/sync-answers', [\App\Http\Controllers\Api\Internal\ExamSyncController::class, 'syncAnswers']);
});
```

- [ ] **Step 2: Buat Middleware InternalApiKey (Opsional/Sederhana)**
Buat file `app/Http/Middleware/InternalApiKeyMiddleware.php`:
```php
// app/Http/Middleware/InternalApiKeyMiddleware.php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class InternalApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = config('services.socket_io.key');
        if ($request->header('x-internal-key') !== $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
```
Daftarkan middleware di `app/Http/Kernel.php` pada array `$routeMiddleware` sebagai `'internal.api.key' => \App\Http\Middleware\InternalApiKeyMiddleware::class`.

- [ ] **Step 3: Buat Method `processBulkSync` di `ExamMonitoringService`**
```php
// app/Services/ExamMonitoringService.php
public function processBulkSync(array $answers, array $monitorings, array $cheatCounters): array
{
    return DB::transaction(function () use ($answers, $monitorings, $cheatCounters) {
        try {
            // 1. Bulk Insert Monitorings
            if (!empty($monitorings)) {
                ExamMonitoring::insert($monitorings);
            }

            // 2. Process Answers (Upsert)
            $userExamIds = [];
            if (!empty($answers)) {
                // Gunakan upsert untuk update jawaban yang ada atau insert baru
                \App\Models\UserAnswer::upsert(
                    $answers,
                    ['user_exam_id', 'question_id'], // unique keys
                    ['answer', 'question_option_id', 'is_correct', 'score', 'updated_at'] // update columns
                );
                $userExamIds = array_unique(array_column($answers, 'user_exam_id'));
            }

            // 3. Recalculate current_score
            $updatedScores = [];
            foreach ($userExamIds as $userExamId) {
                $totalScore = (int) \App\Models\UserAnswer::where('user_exam_id', $userExamId)->sum('score');
                UserExam::where('id', $userExamId)->update(['current_score' => $totalScore]);
                $updatedScores[$userExamId] = $totalScore;
            }

            // 4. (Optional) Process Cheat Counters if needed to DB

            // 5. Emit back to Node.js
            foreach ($updatedScores as $userExamId => $score) {
                $userExam = UserExam::find($userExamId);
                if ($userExam) {
                    $this->socketIOService->broadcastExamStatistics($userExam->exam_id, [
                        'user_exam_id' => $userExamId,
                        'student_id' => $userExam->student_id,
                        'current_score' => $score
                    ]);
                }
            }

            return ['success' => true];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Bulk Sync Error: ' . $e->getMessage());
            throw $e;
        }
    });
}
```

- [ ] **Step 4: Buat Controller `ExamSyncController`**
```php
// app/Http/Controllers/Api/Internal/ExamSyncController.php
namespace App\Http\Controllers\Api\Internal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ExamMonitoringService;

class ExamSyncController extends Controller
{
    public function syncAnswers(Request $request, ExamMonitoringService $service)
    {
        $data = $request->validate([
            'answers' => 'array',
            'monitorings' => 'array',
            'cheat_counters' => 'array',
        ]);

        try {
            $service->processBulkSync($data['answers'] ?? [], $data['monitorings'] ?? [], $data['cheat_counters'] ?? []);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

- [ ] **Step 5: Commit Perubahan**
```bash
git add app/Http/ routes/api.php app/Services/ExamMonitoringService.php
git commit -m "feat: implement internal bulk sync endpoint for nodejs"
```

---

### Task 3: Penerimaan Jawaban & Heartbeat di Node.js (Socket.IO)

**Tujuan:** Menambahkan *listener* event di Node.js untuk menerima jawaban peserta dan menyimpannya di Redis.

**Files:**
- Modify: `socket-server.js`

- [ ] **Step 1: Tambahkan Listener `submit_answer`**
Di dalam `io.on('connection', (socket) => { ... })`, tambahkan penanganan untuk pengiriman jawaban.
```javascript
// socket-server.js
socket.on('submit_answer', async (payload) => {
    try {
        const { exam_id, student_id, user_exam_id, question_id, answer, time_spent } = payload;
        if (!exam_id || !user_exam_id || !question_id) return;

        const answerData = {
            user_exam_id,
            student_id,
            exam_id,
            question_id,
            answer: answer || null,
            time_spent: time_spent || 0,
            created_at: nowIso(),
            updated_at: nowIso()
        };

        // Simpan jawaban di Redis Hash (field: question_id, value: JSON string)
        await redis.hset(`exam:${exam_id}:answers:${user_exam_id}`, question_id, JSON.stringify(answerData));

        // Simpan log monitoring
        const monitoringData = {
            user_exam_id, student_id, exam_id,
            action: 'answer', question_id, answer, time_spent: time_spent || 0,
            created_at: nowIso()
        };
        await redis.lpush(`exam:monitorings_queue`, JSON.stringify(monitoringData));

        // Beri tahu klien bahwa jawaban telah diterima
        socket.emit('answer_received', { question_id, status: 'saved' });

    } catch (error) {
        log('error', { event: 'submit_answer_error', error: error.message });
    }
});
```

- [ ] **Step 2: Tambahkan Listener `heartbeat`**
Menyimpan sisa waktu ujian peserta.
```javascript
// socket-server.js
socket.on('heartbeat', async (payload) => {
    const { exam_id, user_exam_id, time_remaining } = payload;
    if (!exam_id || !user_exam_id) return;

    // Update state peserta
    const state = {
        time_remaining,
        last_seen: nowIso(),
        socket_id: socket.id
    };
    await redis.hset(`exam:${exam_id}:state`, user_exam_id, JSON.stringify(state));

    // Broadcast ke admin room secara opsional (bisa di-throttle)
    io.to(`exam:${exam_id}`).emit('student_state_update', { user_exam_id, time_remaining, status: 'online' });
});
```

- [ ] **Step 3: Commit Perubahan**
```bash
git add socket-server.js
git commit -m "feat: add submit_answer and heartbeat socket listeners"
```

---

### Task 4: Node.js Batch Sync Processor

**Tujuan:** Membuat fungsi *cron/interval* di Node.js yang mengambil data dari Redis dan mengirimkannya ke Laravel setiap 30 detik.

**Files:**
- Modify: `socket-server.js`

- [ ] **Step 1: Buat Fungsi Sync**
```javascript
// socket-server.js
const LARAVEL_API_URL = process.env.LARAVEL_API_URL || 'http://localhost:8000/api/internal/sync-answers';
const INTERNAL_KEY = key; // gunakan SOCKET_IO_KEY yang sudah ada

async function processBatchSync() {
    try {
        // Ambil semua monitoring log
        const monitoringsRaw = await redis.lrange('exam:monitorings_queue', 0, -1);
        await redis.del('exam:monitorings_queue');
        const monitorings = monitoringsRaw.map(r => JSON.parse(r));

        // Ambil kunci-kunci jawaban yang ada
        const answerKeys = await redis.keys('exam:*:answers:*');
        let allAnswers = [];

        for (const aKey of answerKeys) {
            const answersHash = await redis.hgetall(aKey);
            for (const qId in answersHash) {
                allAnswers.push(JSON.parse(answersHash[qId]));
            }
            // Hapus dari redis setelah diambil (opsional, atau biarkan sampai ujian selesai)
            // Jika mau aman, hapus field yang sudah diambil, tapi karena ini Hash, bisa pakai del.
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
            throw new Error(`Laravel returned ${response.status}`);
        }

        log('info', { event: 'batch_sync_success' });

    } catch (error) {
        log('error', { event: 'batch_sync_failed', message: error.message });
        // NOTE: Di implementasi nyata yang robust, data yang gagal dikirim harus dikembalikan ke Redis (requeue).
    }
}

// Jalankan setiap 30 detik
setInterval(processBatchSync, 30000);
```

- [ ] **Step 2: Commit Perubahan**
```bash
git add socket-server.js
git commit -m "feat: implement batch sync processor in nodejs"
```

---

### Task 5: Deteksi Kecurangan (Cheating) & Admin Actions

**Tujuan:** Menangani *event* kecurangan dari peserta, membroadcast peringatan ke admin, dan menangani perintah admin (*force submit*/*unblock*).

**Files:**
- Modify: `socket-server.js`

- [ ] **Step 1: Listener `cheat_warning` dari Peserta**
```javascript
// socket-server.js
socket.on('cheat_warning', async (payload) => {
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
    socket.emit('cheat_blocked', { count: cheatCount, message: 'Anda terdeteksi berpindah tab. Menunggu persetujuan admin.' });
});
```

- [ ] **Step 2: Listener `admin_action` dari Admin/Laravel**
Meneruskan perintah dari Laravel (melalui `/emit`) ke peserta. *Note: Endpoint `/emit` sudah mendukung pengiriman event ke room `student:{student_id}`.*

```javascript
// Tidak perlu mengubah Node.js untuk ini karena endpoint POST /emit di socket-server.js
// sudah bisa menerima payload: { event: 'force_submit', room: 'student:123', data: {} }
```

- [ ] **Step 3: Tambahkan method `forceSubmit` di Laravel (Opsional jika belum ada)**
Buka `app/Http/Controllers/Api/ExamMonitoringController.php` (atau buat controller baru untuk Admin Actions).
```php
// app/Http/Controllers/Api/ExamMonitoringController.php
public function forceSubmit(Request $request, \App\Services\ExamService $examService)
{
    $request->validate([
        'user_exam_id' => 'required|exists:user_exams,id'
    ]);

    $userExam = \App\Models\UserExam::find($request->user_exam_id);
    
    // Panggil method finishExam dari ExamService
    $result = $examService->finishExam($userExam, []);
    
    // Broadcast force submit ke student via socket
    app(\App\Services\SocketIOService::class)->emitEvent(
        'admin-action', 
        'force_submit', 
        ['message' => 'Ujian Anda dihentikan paksa oleh Admin.'], 
        "student:{$userExam->student_id}"
    );

    return response()->json(['status' => 'success', 'message' => 'Peserta dihentikan paksa.']);
}

public function unblockStudent(Request $request)
{
    $request->validate([
        'user_exam_id' => 'required|exists:user_exams,id'
    ]);
    $userExam = \App\Models\UserExam::find($request->user_exam_id);
    
    app(\App\Services\SocketIOService::class)->emitEvent(
        'admin-action', 
        'unblock', 
        ['message' => 'Silakan lanjutkan ujian.'], 
        "student:{$userExam->student_id}"
    );
    return response()->json(['status' => 'success', 'message' => 'Blokir dibuka.']);
}
```

- [ ] **Step 4: Commit Perubahan**
```bash
git add socket-server.js app/Http/Controllers/Api/ExamMonitoringController.php
git commit -m "feat: implement cheat detection and admin actions"
```
