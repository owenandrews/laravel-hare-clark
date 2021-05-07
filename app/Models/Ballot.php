<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Candidate;

class Ballot extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function preferences()
    {
        return $this->belongsToMany(Candidate::class, 'preferences')
			->withPivot('preference')
        	->withTimestamps();
    }
}
