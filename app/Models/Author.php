<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function publications()
    {
        return $this->hasMany(Publication::class);
    }
}
