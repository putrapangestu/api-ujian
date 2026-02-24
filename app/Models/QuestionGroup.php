<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * QuestionGroup Model
 *
 * Model ini merepresentasikan tabel question_groups dalam database
 * Digunakan untuk management grup soal/bank soal
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $code
 * @property int $total_questions
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class QuestionGroup extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * Status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DRAFT = 'draft';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'question_groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'code',
        'total_questions',
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
            'total_questions' => 'integer',
        ];
    }

    /**
     * Get the questions for the question group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Get the exams that use this question group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_question_groups')
                    ->withPivot('random_selection')
                    ->withTimestamps();
    }

    /**
     * Check if the question group is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the question group is draft.
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Update total questions count.
     *
     * @return void
     */
    public function updateTotalQuestions(): void
    {
        $this->total_questions = $this->questions()->count();
        $this->save();
    }
}
