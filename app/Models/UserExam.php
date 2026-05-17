<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UserExam Model
 *
 * Model ini merepresentasikan tabel user_exams dalam database
 * Digunakan untuk menyimpan data ujian yang diikuti oleh user
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property string $user_id
 * @property string $exam_id
 * @property \Carbon\Carbon|null $start_time
 * @property \Carbon\Carbon|null $end_time
 * @property int|null $score
 * @property string $status pending, in_progress, completed, expired
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class UserExam extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'exam_id',
        'student_id',
        'start_time',
        'end_time',
        'score',
        'current_score',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'score' => 'integer',
        ];
    }

    /**
     * Get the user that owns the user exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the user that owns the user exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function userOnly()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the exam that owns the user exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id')->withTrashed();
    }

    /**
     * Get user answers for this user exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userAnswers()
    {
        return $this->hasMany(UserAnswer::class);
    }

    /**
     * Check if exam is in progress
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if exam is completed
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if exam is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Get duration in minutes
     *
     * @return int
     */
    public function getDurationAttribute(): int
    {
        if (!$this->start_time || !$this->end_time) {
            return 0;
        }

        return $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Check if user passed the exam
     *
     * @return bool
     */
    public function isPassed(): bool
    {
        if (!$this->score || !$this->exam) {
            return false;
        }

        return $this->score >= $this->exam->passing_score;
    }
}
