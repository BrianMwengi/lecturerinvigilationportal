<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invigilator extends Model
{
    protected $fillable = ['name'];

    public function invigilationDuties(): HasMany
    {
        return $this->hasMany(InvigilationDuty::class);
    }
}