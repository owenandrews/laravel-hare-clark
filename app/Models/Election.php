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
    	ray()->clearScreen();
    	
    	$ballots = $this->ballots()->with('preferences')->get()->map(function ($ballot) {
    		// Sort prefences in ascending order
    		$preferences = $ballot->preferences->sort(function($a, $b) {
    			return ($a->pivot->preference < $b->pivot->preference) ? -1 : 1;
    		})->values()->map(function ($preference) {
    			return $preference->id;
    		})->all();

    		return $preferences;
    	})->all();

    	$candidates = $this->candidates->map(function ($candidate) {
    		return [
    			'id' => $candidate->id,
    			'name' => $candidate->name,
    		];
    	})->values()->all();

    	$hare_clark = new \App\HareClarkElection($this->vacancies, $candidates, $ballots);

    	return $hare_clark->count();
    }

    public function oldCount()
    {
    	ray()->clearScreen();

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

    	$candidates = [];

    	$this->candidates->each(function ($candidate) use (&$candidates) {
    		$candidates[strval($candidate->id)] = [
    			'id' => $candidate->id,
    			'name' => $candidate->name,
    			'elected' => false,
    			'excluded' => false,
    			'parcels' => [
    				['transfer_value' => 1, 'ballots' => []]
    			],
    			'votes' => 0,
    			//'original_model' => $candidate,
    		];
    	});

    	// Count first preferences
    	foreach ($ballots as $ballot) {
    		$candidates[$ballot[0]]['parcels'][0]['ballots'][] = $ballot;
    		$candidates[$ballot[0]]['votes'] += 1;
    	}

    	$elected = [];

    	//for ($i = 0; $i < 1; $i++) {
    		uasort($candidates, function ($a, $b) {
	    		return ($a['votes'] > $b['votes']) ? -1 : 1;
	    	});

	    	$surplus_transfer_queue = [];

	    	// Find elected candidates
	    	foreach ($candidates as $key => $candidate) {
	    		if ($candidate['votes'] >= $quota && $candidate['elected'] === false) {
	    			$candidate['elected'] = true;
	    			
	    			if ($candidate['votes'] > $quota) $surplus_transfer_queue[] = &$candidates[$key];

	    			ray($candidate['name'].' elected');
	    		}
	    	}

	    	while (count($surplus_transfer_queue)) {
	    		$candidate = array_shift($surplus_transfer_queue);

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

		    	// Sort parcels in highest vote order (descending order)
		    	uasort($parcels, function ($a, $b) {
		    		return (count($a['ballots']) > count($a['ballots'])) ? -1 : 1;
		    	});

		    	foreach ($parcels as $key => $parcel) {
		    		$candidates[$key]['parcels'][] = $parcel;

		    		// Check if transfer has elected a candidate
		    		if ($candidates[$key]['votes'] >= $quota) {
		    			$candidates[$key]['elected'] = true;

		    			if ($candidates[$key]['votes'] > $quota) $surplus_transfer_queue[] = &$candidates[$key];

		    			ray($candidates[$key]['name'].' elected');
		    		}
		    	}

		    	//
		    	//
		    	//

		    	$elected_candidates = array_filter($candidates, function ($candidate) {
		    		return $candidate['elected'] === true;
		    	});

		    	while (
		    		count($surplus_transfer_queue) === 0 &&
		    		count($elected_candidates) < $vacancies
		    	) {
		    		uasort($candidates, function ($a, $b) {
			    		return ($a['votes'] > $b['votes']) ? -1 : 1;
			    	});

			    	$continuing_candidates = array_filter($candidates, function ($candidate) {
			    		return $candidate['elected'] === false && $candidate['excluded'] === false;
			    	});

			    	if (count($continuing_candidates) == 0) {
			    		break;
			    	}

			    	$candidates[array_key_last($continuing_candidates)]['excluded'] = true;

			    	$excluded_parcels = $candidates[array_key_last($continuing_candidates)]['parcels'];

			    	// Sort parcels in highest transfer value order (descending order)
			    	uasort($excluded_parcels, function ($a, $b) {
			    		return ($a['transfer_value'] > $a['transfer_value']) ? -1 : 1;
			    	});

			    	foreach ($excluded_parcels as $key => $excluded_parcel) {

			    		$transfer_parcels = [];

			    		foreach ($excluded_parcel['ballots'] as $excluded_ballot) {
		    				$transfer_candidate_id = $this->findNextPreference(count($candidates[array_key_last($continuing_candidates)]['parcels']), $excluded_ballot, $candidates);

		    				if ($transfer_candidate_id) {
		    					$transfer_candidate = $candidates[$transfer_candidate_id];

		    					if (!array_key_exists($transfer_candidate['id'], $transfer_parcels)) {
		    						$transfer_parcels[$transfer_candidate['id']] = [
		    							'transfer_value' => $excluded_parcel['transfer_value'],
		    							'ballots' => []
		    						]; 
		    					}
					    		
					    		$transfer_parcels[$transfer_candidate['id']]['ballots'][] = $excluded_ballot;
					    		$candidates[$transfer_candidate['id']]['votes'] += $excluded_parcel['transfer_value'];
		    				}
				    	}

				    	// Sort parcels in highest vote order (descending order)
				    	uasort($transfer_parcels, function ($a, $b) {
				    		return (count($a['ballots']) > count($a['ballots'])) ? -1 : 1;
				    	});

				    	ray($transfer_parcels);

				    	foreach ($transfer_parcels as $key => $transfer_parcel) {
				    		$candidates[$key]['parcels'][] = $transfer_parcel;

				    		// Check if transfer has elected a candidate
				    		if ($candidates[$key]['votes'] >= $quota) {
				    			$candidates[$key]['elected'] = true;

				    			if ($candidates[$key]['votes'] > $quota) $surplus_transfer_queue[] = &$candidates[$key];

				    			ray($candidates[$key]['name'].' elected');
				    		}
				    	}
			    	}
		    	}
	    	}

	    	// TODO
	    	// Handle cases where vote equality is equal when ordering	
    	//}

    	$final = [
    		'quota' => $quota,
    		'candidates' => $candidates,
    	];

    	//dd($final);

    	return $final;
    }

    protected function findNextPreference($minimum_preference, &$ballot, &$candidates)
    {
    	foreach ($ballot as $preference => $candidate_id) {
    		if ($preference < $minimum_preference) continue;
    		if ($candidates[$candidate_id]['elected'] === true || $candidates[$candidate_id]['excluded'] === true) continue;
    		return $candidate_id;
    	}

    	return null;
    }
}
