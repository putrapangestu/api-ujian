# API Ujian CPNS - Laravel

## Deskripsi
API Ujian CPNS adalah RESTful API yang dibangun dengan Laravel untuk aplikasi ujian CPNS. API ini menyediakan fitur authentication, manajemen ujian, sistem penilaian, dan admin panel.

## Fitur Utama

### 🔐 Authentication
- Register user baru
- Login dengan JWT token
- Profile management
- Logout

### 📋 Manajemen Ujian
- CRUD operations untuk ujian
- Start ujian dengan timer
- Sistem penilaian otomatis
- History ujian user

### 👨‍💼 Admin Panel
- Manajemen ujian (Create, Read, Update, Delete)
- User management
- Report dan analytics

### 🔧 Teknologi
- **Framework**: Laravel 11.x
- **Database**: MySQL
- **Authentication**: Laravel Sanctum (JWT)
- **Documentation**: Swagger/OpenAPI
- **Pattern**: Repository Pattern

## Struktur Project

```
api-ujian/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   └── ExamController.php
│   │   ├── Requests/
│   │   │   ├── BaseRequest.php
│   │   │   └── Auth/
│   │   │       ├── LoginRequest.php
│   │   │       └── RegisterRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Exam.php
│   │   ├── Question.php
│   │   ├── UserExam.php
│   │   └── UserAnswer.php
│   ├── Services/
│   │   ├── BaseService.php
│   │   ├── AuthService.php
│   │   └── ExamService.php
│   ├── Repositories/
│   │   ├── BaseRepository.php
│   │   ├── BaseRepositoryInterface.php
│   │   ├── UserRepository.php
│   │   ├── UserRepositoryInterface.php
│   │   ├── ExamRepository.php
│   │   └── ExamRepositoryInterface.php
│   ├── Helpers/
│   │   ├── ResponseHelper.php
│   │   └── StringHelper.php
│   └── Middleware/
│       └── AdminMiddleware.php
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_exams_table.php
│       ├── 2024_01_01_000002_create_questions_table.php
│       ├── 2024_01_01_000003_create_user_exams_table.php
│       └── 2024_01_01_000004_create_user_answers_table.php
├── routes/
│   └── api.php
└── documentation/
    ├── DOCUMENTASI_DESIGN.md
    ├── API_DOCUMENTATION.md
    └── SwaggerConfig.php
```

## Instalasi

### Persyaratan
- PHP 8.1 atau lebih tinggi
- Composer
- MySQL
- Laravel 11.x

### Langkah-langkah Instalasi

1. **Clone Repository**
```bash
git clone [repository-url]
cd api-ujian
```

2. **Install Dependencies**
```bash
composer install
```

3. **Copy Environment File**
```bash
cp .env.example .env
```

4. **Generate Application Key**
```bash
php artisan key:generate
```

5. **Konfigurasi Database**
Edit file `.env` dan sesuaikan konfigurasi database:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ujian_cpns
DB_USERNAME=root
DB_PASSWORD=
```

6. **Run Migrations**
```bash
php artisan migrate
```

7. **Install Laravel Sanctum**
```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

8. **Start Development Server**
```bash
php artisan serve
```

## API Endpoints

### Authentication Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register user baru |
| POST | `/api/auth/login` | Login user |
| POST | `/api/auth/logout` | Logout user |
| GET | `/api/auth/profile` | Get user profile |
| PUT | `/api/auth/profile` | Update user profile |

### Exam Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/exams` | Get all exams |
| GET | `/api/exams/{id}` | Get exam by ID |
| POST | `/api/exams/{id}/start` | Start exam for user |

### Admin Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/admin/exams` | Create new exam |
| PUT | `/api/admin/exams/{id}` | Update exam |
| DELETE | `/api/admin/exams/{id}` | Delete exam |

## Cara Penggunaan

### 1. Register User
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 2. Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### 3. Get Profile (with token)
```bash
curl -X GET http://localhost:8000/api/auth/profile \
  -H "Authorization: Bearer {your_token}"
```

### 4. Create Exam (Admin only)
```bash
curl -X POST http://localhost:8000/api/admin/exams \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Ujian CPNS Tahun 2024",
    "description": "Ujian seleksi CPNS untuk formasi tahun 2024",
    "duration": 120,
    "passing_score": 70
  }'
```

## Struktur Response

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {...}
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {...}
}
```

## Database Structure

### Users Table
- `id` - Primary key
- `name` - User name
- `email` - User email (unique)
- `password` - Encrypted password
- `role` - User role (admin/user)
- `avatar` - Profile avatar (nullable)
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

### Exams Table
- `id` - Primary key
- `title` - Exam title
- `description` - Exam description
- `duration` - Duration in minutes
- `total_questions` - Total number of questions
- `passing_score` - Minimum score to pass
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

### Questions Table
- `id` - Primary key
- `exam_id` - Foreign key to exams
- `question_text` - Question content
- `question_type` - Type (multiple_choice/essay/true_false/single_choice)
- `options` - JSON array of options
- `correct_answer` - Correct answer
- `score` - Question score value
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

### UserExams Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `exam_id` - Foreign key to exams
- `start_time` - Exam start time
- `end_time` - Exam end time
- `score` - User score
- `status` - Exam status (pending/in_progress/completed/expired)
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

### UserAnswers Table
- `id` - Primary key
- `user_exam_id` - Foreign key to user_exams
- `question_id` - Foreign key to questions
- `answer` - User answer
- `is_correct` - Whether answer is correct
- `score` - Score for this answer
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

## Testing

### Run Tests
```bash
php artisan test
```

### Run Specific Test
```bash
php artisan test --filter=AuthTest
```

## Security

### Authentication
- Menggunakan Laravel Sanctum untuk JWT authentication
- Token berlaku selama 1 hari (dapat dikonfigurasi)
- Refresh token tersedia

### Authorization
- Middleware untuk memastikan user adalah admin sebelum mengakses admin endpoints
- Role-based access control (RBAC)

### Validation
- Input validation di setiap request
- SQL injection prevention
- XSS protection

## Best Practices

### Code Structure
- Menggunakan Repository Pattern untuk separation of concerns
- Service layer untuk business logic
- Request validation terpisah
- Response helper untuk konsistensi

### Documentation
- Comment block di setiap file dan method
- Parameter dan return value yang jelas
- API documentation yang komprehensif

### Error Handling
- Standardized error responses
- Proper HTTP status codes
- Detailed error messages untuk debugging

## Contributing

1. Fork repository
2. Buat branch untuk fitur baru
3. Commit perubahan
4. Push ke branch
5. Buat Pull Request

## License

This project is licensed under the MIT License.

## Support

Untuk pertanyaan dan support, silakan hubungi:
- Email: admin@ujian-cpns.com
- Issue Tracker: [GitHub Issues](https://github.com/your-repo/issues)

## Changelog

### v1.0.0 (2024-01-01)
- Initial release
- Authentication system
- Exam management
- Admin panel
- API documentation
- Repository pattern implementation
