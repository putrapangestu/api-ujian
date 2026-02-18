<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ExamMonitoring Model
 *
 * Model ini merepresentasikan tabel exam_monitoring dalam database
 * Digunakan untuk monitoring realtime pengerjaan ujian
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property int $id
 * @property int $user_exam_id
 * @property int $student_id
 * @property int $exam_id
 * @property string $action
 * @property string|null $question_id
 * @property string|null $answer
 * @property int $time_spent
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ExamMonitoring extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * Action constants
     */
    const ACTION_START = 'start';
    const ACTION_ANSWER = 'answer';
    const ACTION_NAVIGATE = 'navigate';
    const ACTION_SUBMIT = 'submit';
    const ACTION_PAUSE = 'pause';
    const ACTION_RESUME = 'resume';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_exam_id',
        'student_id',
        'exam_id',
        'action',
        'question_id',
        'answer',
        'time_spent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_spent' => 'integer',
        ];
    }

    /**
     * Get the user exam that owns the monitoring record
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function userExam()
    {
        return $this->belongsTo(UserExam::class);
    }

    /**
     * Get the student that owns the monitoring record
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the exam that owns the monitoring record
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the question that owns the monitoring record
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Check if action is start
     *
     * @return bool
     */
    public function isStart(): bool
    {
        return $this->action === self::ACTION_START;
    }

    /**
     * Check if action is answer
     *
     * @return bool
     */
    public function isAnswer(): bool
    {
        return $this->action === self::ACTION_ANSWER;
    }

    /**
     * Check if action is submit
     *
     * @return bool
     */
    public function isSubmit(): bool
    {
        return $this->action === self::ACTION_SUBMIT;
    }
}
