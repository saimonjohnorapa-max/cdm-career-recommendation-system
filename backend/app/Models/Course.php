<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'duration',
        'tuition_fee',
        'ideal_score_range',
        'subjects',
        'institute',
        'career_paths',
        'image_path',
        'image_data',
        'image_mime_type',
        'program_type',
        'is_active',
        'is_recommendable',
        'display_order',
        'recommendation_profile',
    ];

    protected $casts = [
        'subjects' => 'array',
        'career_paths' => 'array',
        'is_active' => 'boolean',
        'is_recommendable' => 'boolean',
        'display_order' => 'integer',
        'recommendation_profile' => 'array',
    ];

    protected $hidden = ['image_data'];

    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }
}
