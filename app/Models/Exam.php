<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Exam Model
 *
 * Model ini merepresentasikan tabel exams dalam database
 * Digunakan untuk management ujian/tes
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property string $title
 * @property string $description
 * @property int $duration Durasi dalam menit
 * @property int $total_questions
 * @property int $passing_score Nilai kelulusan
 * @property \Carbon\Carbon|null $start_date Tanggal mulai ujian
 * @property \Carbon\Carbon|null $end_date Tanggal berakhir ujian
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Exam extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'duration',
        'total_questions',
        'passing_score',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'total_questions' => 'integer',
            'passing_score' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /**
     * Get questions for the exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Get user exams for this exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userExams()
    {
        return $this->hasMany(UserExam::class);
    }

    /**
     * Get users who have taken this exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function questionExams()
    {
        return $this->hasMany(QuestionExam::class);
    }

    /**
     * Get question groups for this exam
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function questionGroups()
    {
        return $this->belongsToMany(QuestionGroup::class, 'exam_question_groups')
                    ->withPivot('random_selection')
                    ->withTimestamps();
    }

    /**
     * Check if exam has questions
     *
     * @return bool
     */
    public function hasQuestions(): bool
    {
        return $this->questions()->exists();
    }

    /**
     * Check if exam is available (within date range)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $now = Carbon::now();

        // Check start date
        if ($this->start_date && $now < $this->start_date) {
            return false;
        }

        // Check end date
        if ($this->end_date && $now > $this->end_date) {
            return false;
        }

        return true;
    }

    /**
     * Check if exam has started
     *
     * @return bool
     */
    public function hasStarted(): bool
    {
        if (!$this->start_date) {
            return true;
        }

        return Carbon::now() >= $this->start_date;
    }

    /**
     * Check if exam has ended
     *
     * @return bool
     */
    public function hasEnded(): bool
    {
        if (!$this->end_date) {
            return false;
        }

        return Carbon::now() > $this->end_date;
    }

    /**
     * Get formatted duration
     *
     * @return string
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0) {
            return $hours . ' jam ' . $minutes . ' menit';
        }

        return $minutes . ' menit';
    }
}
