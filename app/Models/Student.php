<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Student Model
 *
 * Model ini merepresentasikan tabel students dalam database
 * Digunakan untuk management data siswa
 *
 * @author Development Team
 *
 * @version 1.0.0
 *
 * @property string $id
 * @property string $nisn Nomor Induk Siswa Nasional
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property string $group_id
 * @property int|null $user_id
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Student extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Status constants
     */
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_GRADUATED = 'graduated';

    const STATUS_DROPPED = 'dropped';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nisn',
        'name',
        'phone',
        'address',
        'group_id',
        'user_id',
        'status',
        'birth_date',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['email'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:d-m-Y',
        ];
    }

    /**
     * Get the group that owns the student
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(StudentGroup::class, 'group_id');
    }

    /**
     * Get the user that owns the student
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get user exams for this student
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userExams()
    {
        return $this->hasMany(UserExam::class, 'student_id');
    }

    /**
     * Get the user's email.
     *
     * @return string|null
     */
    public function getEmailAttribute()
    {
        return $this->user ? $this->user->email : null;
    }

    /**
     * Check if student is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if student can take exam
     */
    public function canTakeExam(): bool
    {
        return $this->isActive() && $this->group && $this->group->isActive();
    }

    /**
     * Get exam history
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExamHistory()
    {
        return $this->userExams()->with(['exam'])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get average score
     */
    public function getAverageScore(): ?float
    {
        $completedExams = $this->userExams()->where('status', UserExam::STATUS_COMPLETED)->get();

        if ($completedExams->isEmpty()) {
            return null;
        }

        return $completedExams->avg('score');
    }
}
