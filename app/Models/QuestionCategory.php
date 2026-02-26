<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * QuestionCategory Model
 *
 * Model ini merepresentasikan tabel question_categories dalam database
 * Digunakan untuk management kategori soal
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property string $name
 * @property string $description
 * @property string $code
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class QuestionCategory extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * Status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'code',
        'status',
    ];

    /**
     * Get questions in this category
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questions()
    {
        return $this->hasMany(Question::class, 'category_id');
    }

    /**
     * Get exams that use this category
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_categories')
                    ->withPivot('weight', 'question_count')
                    ->withTimestamps();
    }

    /**
     * Check if category is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get question count for this category
     *
     * @return int
     */
    public function getQuestionCount(): int
    {
        return $this->questions()->count();
    }

    /**
     * Get active questions count
     *
     * @return int
     */
    public function getActiveQuestionCount(): int
    {
        return $this->questions()->whereHas('exam', function ($query) {
            $query->where('status', 'active');
        })->count();
    }

    /**
     * Get question groups in this category
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questionGroups()
    {
        return $this->hasMany(QuestionGroup::class, 'question_category_id');
    }
}
