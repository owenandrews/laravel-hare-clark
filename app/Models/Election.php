<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Candidate;
use App\Models\Ballot;

class Election extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function candidates()
    {
    	return $this->hasMany(Candidate::class);
    }

    public function ballots()
    {
    	return $this->hasMany(Ballot::class);
    }

    public function getQuotaAttribute()
    {
    	return ($this->ballots->count() / ($this->vacancies + 1)) + 1;
    }

    public function countResult()
    {
    	$vacancies = $this->vacancies;

    	$ballots = $this->ballots()->with('preferences')->get()->map(function ($ballot) {
    		// Sort prefences in ascending order
    		$preferences = $ballot->preferences->sort(function($a, $b) {
    			return ($a->pivot->preference < $b->pivot->preference) ? -1 : 1;
    		})->values()->map(function ($preference) {
    			return $preference->id;
    		})->all();

    		return $preferences;
    	})->all();

    	$quota = (count($ballots) / ($vacancies + 1)) + 1;

    	$candidates = $this->candidates->mapWithKeys(function ($candidate) {
    		return [strval($candidate->id) => [
    			'id' => $candidate->id,
    			'name' => $candidate->name,
    			'elected' => false,
    			'elected_at_transfer' => false,
    			'surplus_transferred' => false,
    			'excluded' => false,
    			'parcels' => [
    				['transfer_value' => 1, 'ballots' => []]
    			],
    			'votes' => 0,
    			//'original_model' => $candidate,
    		]];
    	})->all();

    	// Count first preferences
    	foreach ($ballots as $ballot) {
    		$candidates[$ballot[0]]['parcels'][0]['ballots'][] = $ballot;
    		$candidates[$ballot[0]]['votes'] += 1;
    	}

    	$elected = [];

    	for ($i = 0; $i < 4; $i++) {
    		ray($i);

    		uasort($candidates, function ($a, $b) {
	    		return ($a['votes'] > $b['votes']) ? -1 : 1;
	    	});

	    	// Find elected candidates
	    	foreach ($candidates as &$candidate) {
	    		if (($candidate['elected'] === false || $candidate['elected_at_transfer'] === true)  && $candidate['votes'] >= $quota) {
	    			$candidate['elected'] = true;
	    			$candidate['surplus_transferred'] = $candidate['votes'] == $quota;

	    			ray($candidate['name'].' elected');
	    		}
	    	}

	    	foreach ($candidates as &$candidate) {
	    		if ($candidate['elected'] === true && $candidate['surplus_transferred'] === false) {
	    			$candidate['surplus_transferred'] = true;

	    			$last_parcel = $candidate['parcels'][array_key_last($candidate['parcels'])];
	    			$total_ballots = count($last_parcel['ballots']);
	    			$surplus = $candidate['votes'] - $quota;
	    			$transfer_value = $surplus / $total_ballots;

	    			$parcels = [];

	    			foreach ($last_parcel['ballots'] as $transfer_ballot) {
	    				$transfer_candidate_id = $this->findNextPreference(count($candidate['parcels']), $transfer_ballot, $candidates);

	    				if ($transfer_candidate_id) {
	    					$transfer_candidate = $candidates[$transfer_candidate_id];

	    					if (!array_key_exists($transfer_candidate['id'], $parcels)) {
	    						$parcels[$transfer_candidate['id']] = [
	    							'transfer_value' => $transfer_value,
	    							'ballots' => []
	    						]; 
	    					}
				    		
				    		$parcels[$transfer_candidate['id']]['ballots'][] = $transfer_ballot;
				    		$candidates[$transfer_candidate['id']]['votes'] += $transfer_value;
	    				}
			    	}

			    	foreach ($parcels as $key => $parcel) {
			    		$candidates[$key]['parcels'][] = $parcel;

			    		// Check if transfer has elected a candidate
			    		if ($candidates[$key]['votes'] >= $quota) {
			    			$candidates[$key]['elected_at_transfer'] = true;
			    		}
			    	}
	    		}
	    	}



	    	// TODO: Exclude candidates
	    	// Don't transfer votes to already elected candidates
	    	// Don't transfer votes to excluded candidates
	    	// Handle cases where vote equality is equal when ordering	
    	}

    	$final = [
    		'quota' => $quota,
    		'candidates' => $candidates,
    	];

    	//dd($final);

    	return $final;
    }

    protected function findNextPreference($min, &$ballot, &$candidates)
    {
    	foreach ($ballot as $key => $candidate_id) {
    		if ($key < $min) continue;
    		if ($candidates[$candidate_id]['elected'] === true || $candidates[$candidate_id]['elected_at_transfer'] === true || $candidates[$candidate_id]['excluded'] === true) continue;
    		return $candidate_id;
    	}

    	return null;
    }
}
