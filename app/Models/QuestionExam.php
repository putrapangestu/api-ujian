<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * QuestionExam Model
 *
 * Model ini merepresentasikan tabel question_exam dalam database
 * Digunakan untuk menyimpan soal yang dipilih dari bank soal untuk ujian
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property int $exam_id
 * @property int $question_id
 * @property int $question_group_id
 * @property int $question_number Nomor urut soal (1, 2, 3, dst)
 * @property int $question_order Urutan random untuk setiap student
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property Exam $exam
 * @property Question $question
 * @property QuestionGroup $questionGroup
 */
class QuestionExam extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'question_exam';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'exam_id',
        'question_id',
        'question_group_id',
        'question_number',
        'question_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the exam that owns the question.
     */
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the question.
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Get the question group.
     */
    public function questionGroup()
    {
        return $this->belongsTo(QuestionGroup::class);
    }
}
