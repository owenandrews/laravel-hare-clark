<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Election;
use App\Models\Ballot;

class Candidate extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function election()
    {
    	return $this->belongsTo(Election::class);
    }

    public function ballots()
    {
        return $this->belongsToMany(Ballot::class, 'preferences')
			->withPivot('preference')
        	->withTimestamps();
    }
}
