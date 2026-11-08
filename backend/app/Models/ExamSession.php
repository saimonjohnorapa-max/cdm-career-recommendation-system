<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExamSession extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['questions'];
    protected $casts = ['questions' => 'array', 'answers' => 'array', 'started_at' => 'datetime', 'deadline' => 'datetime', 'terminated_at' => 'datetime'];
}
