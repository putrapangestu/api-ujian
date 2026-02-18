<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Question Model
 *
 * Model ini merepresentasikan tabel questions dalam database
 * Digunakan untuk management soal ujian
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property int $id
 * @property int $exam_id
 * @property int $category_id
 * @property int|null $question_group_id
 * @property string $question_text
 * @property string $question_type multiple_choice, essay, true_false
 * @property array $options Pilihan jawaban untuk multiple choice
 * @property string|null $correct_answer
 * @property int $score Nilai soal
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Question extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'exam_id',
        'category_id',
        'question_group_id',
        'question_text',
        'question_type',
        'options',
        'correct_answer',
        'score',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'score' => 'integer',
        ];
    }

    /**
     * Get the exam that owns the question
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the category that owns the question
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    /**
     * Get the question group that owns the question
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function questionGroup()
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    /**
     * Get the options for the question
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }

    /**
     * Get user answers for this question
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userAnswers()
    {
        return $this->hasMany(UserAnswer::class);
    }

    /**
     * Check if question is multiple choice
     *
     * @return bool
     */
    public function isMultipleChoice(): bool
    {
        return $this->question_type === 'multiple_choice';
    }

    /**
     * Check if question is essay
     *
     * @return bool
     */
    public function isEssay(): bool
    {
        return $this->question_type === 'essay';
    }

    /**
     * Check if question is true/false
     *
     * @return bool
     */
    public function isTrueFalse(): bool
    {
        return $this->question_type === 'true_false';
    }

    /**
     * Check if given answer is correct
     *
     * @param string $answer
     * @return bool
     */
    public function isCorrect(string $answer): bool
    {
        if ($this->isMultipleChoice()) {
            // For multiple choice, check against options
            $correctOption = $this->options()->where('is_correct', true)->first();
            return $correctOption && $correctOption->option_text === $answer;
        }

        // For essay and true/false, use direct comparison
        return strtolower(trim($answer)) === strtolower(trim($this->correct_answer));
    }

    /**
     * Get the correct option for multiple choice questions
     *
     * @return QuestionOption|null
     */
    public function getCorrectOption(): ?QuestionOption
    {
        return $this->options()->where('is_correct', true)->first();
    }

    /**
     * Get total score for this question based on options
     *
     * @return int
     */
    public function getTotalScore(): int
    {
        return $this->options()->sum('score');
    }
}
