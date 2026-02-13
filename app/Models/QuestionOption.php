<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * QuestionOption Model
 * 
 * Model ini merepresentasikan tabel question_options dalam database
 * Digunakan untuk menyimpan opsi jawaban dari soal
 * 
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 * 
 * @property int $id
 * @property int $question_id
 * @property string $option_text
 * @property boolean $is_correct
 * @property int $score
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class QuestionOption extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'question_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
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
            'is_correct' => 'boolean',
            'score' => 'integer',
        ];
    }

    /**
     * Get the question that owns the option.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Check if this option is correct.
     * 
     * @return bool
     */
    public function isCorrect(): bool
    {
        return $this->is_correct === true;
    }

    /**
     * Get option label (A, B, C, D, etc.)
     * 
     * @return string
     */
    public function getLabel(): string
    {
        return $this->option_label ?: chr(65 + ($this->id - 1)); // A, B, C, D
    }
}