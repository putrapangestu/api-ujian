<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * StudentGroup Model
 *
 * Model ini merepresentasikan tabel student_groups dalam database
 * Digunakan untuk management grup siswa
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property string $name
 * @property string $description
 * @property int $max_students
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class StudentGroup extends Model
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
        'max_students',
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
            'max_students' => 'integer',
        ];
    }

    /**
     * Get students in this group
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'group_id');
    }

    /**
     * Get exams assigned to this group
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'group_exams')
                    ->withPivot('assigned_date', 'due_date')
                    ->withTimestamps();
    }

    /**
     * Check if group is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get current student count
     *
     * @return int
     */
    public function getCurrentStudentCount(): int
    {
        return $this->students()->count();
    }

    /**
     * Check if group has available slots
     *
     * @return bool
     */
    public function hasAvailableSlots(): bool
    {
        return $this->getCurrentStudentCount() < $this->max_students;
    }

    /**
     * Get available slots
     *
     * @return int
     */
    public function getAvailableSlots(): int
    {
        return $this->max_students - $this->getCurrentStudentCount();
    }
}
