<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name_en',
        'name_kh',
        'code',
        'address',
        'phone',
        'is_active',
    ];

    public const ORDERED_CODES = ['CHV', 'KB', 'NR3', 'KSR'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope query to order campuses in standard order: CHV, KB, NR3, KSR.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw("CASE code WHEN 'CHV' THEN 1 WHEN 'KB' THEN 2 WHEN 'NR3' THEN 3 WHEN 'KSR' THEN 4 ELSE 99 END");
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class, 'campus_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'campus_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'campus_id');
    }
}
