<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ebook extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'name',
        'file_path',
        'question_category_id',
    ];

    /**
     * Get the question category that this ebook belongs to.
     */
    public function category()
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }
}
