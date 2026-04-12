# Spesifikasi Integrasi WebSocket untuk Monitoring Ujian Realtime

## 1. Pendahuluan
Dokumen ini mendefinisikan arsitektur dan spesifikasi untuk integrasi WebSocket (menggunakan Socket.IO) ke dalam sistem ujian CBT yang ada. Tujuannya adalah untuk meningkatkan performa pengumpulan jawaban dari peserta (menghindari HTTP bottleneck), memungkinkan monitoring ujian secara realtime oleh admin, dan mendeteksi serta menindak kecurangan peserta secara instan.

## 2. Arsitektur & Alur Data

### 2.1. Pengumpulan Jawaban (Direct WebSocket dengan Batch Sync)
- **Tujuan:** Menghindari beban *database write* dan *HTTP overhead* saat ribuan peserta mengklik jawaban secara bersamaan.
- **Mekanisme:**
  1. Frontend peserta mengirim jawaban secara *direct* via WebSocket ke Node.js server.
  2. Node.js menyimpan jawaban di memori/Redis (menggunakan *Hash/Map* agar jawaban terbaru untuk soal yang sama selalu menimpa yang lama).
  3. Node.js mengirim *acknowledgment* kembali ke peserta.
  4. Node.js memiliki *Batch Processor* yang berjalan setiap 30 detik (atau jika antrean mencapai 100 jawaban). Prosesor ini melakukan HTTP POST *bulk-sync* ke endpoint internal Laravel.
  5. Laravel memproses *bulk data* tersebut, mencocokkan dengan kunci jawaban, menyimpan ke tabel `user_answers` dan `exam_monitorings`, lalu mengembalikan respon OK.
  6. Endpoint `/exams/{id}/finish` tetap dipertahankan sebagai *failsafe* terakhir untuk menampung jawaban yang belum tersinkronisasi saat peserta menyelesaikan ujian.

### 2.2. Kalkulasi Nilai & Monitoring Waktu Realtime
- **Tujuan:** Admin dapat melihat *leaderboard* dan sisa waktu peserta tanpa perlu me-refresh halaman.
- **Mekanisme:**
  1. Frontend peserta rutin mengirimkan *heartbeat* (misal setiap 10 detik) via WebSocket ke Node.js yang berisi `time_remaining`.
  2. Node.js menyimpan *state* waktu dan status koneksi (online/offline) peserta di Redis.
  3. Saat Laravel memproses *bulk-sync* jawaban (dari Poin 2.1), Laravel menghitung ulang `current_score` dan mengirimkan data skor terbaru ini kembali ke Node.js via endpoint `/emit`.
  4. Node.js menggabungkan data skor dari Laravel dengan data *realtime state* (waktu & status) di Redis, lalu me-nge-broadcast data gabungan ini ke *room* admin `exam:{exam_id}`.

### 2.3. Deteksi Kecurangan & Kontrol Admin
- **Tujuan:** Mendeteksi peserta yang berpindah tab/aplikasi dan memberikan kontrol kepada admin untuk menindaklanjuti.
- **Mekanisme:**
  1. Frontend mendeteksi event `visibilitychange` atau *loss of focus* dan mengirim event `cheat_warning` ke Node.js.
  2. Node.js mencatat *counter* pelanggaran di Redis dan me-nge-broadcast peringatan `cheat_alert` ke *room* admin.
  3. Layar peserta akan diblokir sementara dengan pesan: "Anda terdeteksi pindah tab. Menunggu persetujuan Admin."
  4. Di dashboard, Admin dapat memilih dua aksi:
     - **Beri Peringatan:** Melanjutkan ujian peserta (mengirim event `unblock` via Laravel -> Node.js -> Klien).
     - **Force Submit:** Menghentikan ujian peserta (memanggil endpoint Laravel untuk menyelesaikan ujian -> Laravel mengirim event `force_submit` ke Node.js -> Klien dialihkan ke halaman selesai).

## 3. Komponen yang Terlibat

### 3.1. Node.js (Socket.IO Server)
- Mengelola koneksi klien dan otentikasi (JWT).
- Mengelola state di Redis (`answers`, `monitoring_state`, `cheat_counters`).
- Menjalankan *cron job/interval* untuk *bulk-sync* ke Laravel.
- Me-nge-broadcast event ke klien dan admin.

### 3.2. Laravel API
- Menyediakan endpoint internal `/api/internal/sync-answers` (dilindungi token statis/API key khusus) untuk menerima *bulk data* dari Node.js.
- Memperbarui tabel `user_exams` (menambahkan kolom `current_score` jika belum ada), `user_answers`, dan `exam_monitorings`.
- Mengirim event *broadcast* ke Node.js via HTTP POST `/emit` (sudah terimplementasi di `SocketIOService`).

### 3.3. Database (MySQL)
- Perlu menambahkan kolom `current_score` (integer) pada tabel `user_exams` untuk menyimpan nilai sementara tanpa perlu men-sum seluruh `user_answers` setiap saat.
- Memastikan index tabel optimal untuk *bulk insert/update*.

## 4. Endpoint & Event Definisi

### 4.1. Event Socket.IO (Klien -> Node.js)
- `submit_answer`: Payload `{ exam_id, question_id, answer, time_spent }`
- `heartbeat`: Payload `{ exam_id, time_remaining }`
- `cheat_warning`: Payload `{ exam_id, type: 'tab_switch' }`

### 4.2. Event Socket.IO (Node.js -> Klien)
- `answer_received`: Acknowledgment bahwa jawaban diterima server.
- `cheat_alert`: (Ke Admin) Notifikasi kecurangan.
- `dashboard_update`: (Ke Admin) Update *leaderboard* dan waktu.
- `admin_action`: (Ke Peserta) Berisi instruksi `unblock` atau `force_submit`.

### 4.3. API Internal Laravel
- `POST /api/internal/sync-answers`: Menerima JSON array dari Node.js berisi data jawaban dan *cheat counters* untuk diproses.

## 5. Keamanan & Failsafe
- Komunikasi Node.js ke Laravel menggunakan token rahasia (internal API key).
- Jawaban di-cache di *local storage* browser peserta dan dikirim ulang via REST API saat tombol *Selesai* ditekan (jika masih ada yang *pending* di frontend).
- Redis digunakan sebagai *backing store* untuk Node.js agar *state* tidak hilang jika *instance* Node.js *restart*.