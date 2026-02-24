<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UserAnswer Model
 *
 * Model ini merepresentasikan tabel user_answers dalam database
 * Digunakan untuk menyimpan jawaban user untuk setiap soal
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property int $user_exam_id
 * @property int $question_id
 * @property int|null $question_option_id
 * @property string|null $answer
 * @property bool $is_correct
 * @property int $score
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class UserAnswer extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_exam_id',
        'question_id',
        'question_option_id',
        'answer',
        'is_correct',
        'score',
        'time_spent',
        'question_order',
        'question_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'score' => 'integer',
        ];
    }

    /**
     * Get the user exam that owns the user answer
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function userExam()
    {
        return $this->belongsTo(UserExam::class);
    }

    /**
     * Get the question that owns the user answer
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Get the question option that owns the user answer
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function questionOption()
    {
        return $this->belongsTo(QuestionOption::class);
    }
}
