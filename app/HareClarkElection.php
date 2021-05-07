<?php
namespace App;

/* 

TODO:

- Verify ballots
- Implement exact match sorting rules
- Implement logic for when not enough candidates make it to the quota

*/


class HareClarkElection {
	
	protected $candidates = [];
	
	protected $ballots;
	
	protected $quota;
	
	protected $vacancies;

	protected $surplus_transfer_queue = [];

	public function __construct($vacancies, $candidates, $ballots)
	{
		$this->vacancies = $vacancies;

		$this->quota = (count($ballots) / ($vacancies + 1)) + 1;

		$this->ballots = $ballots;

		foreach ($candidates as $candidate) {
			$this->candidates[$candidate['id']] = [
    			'id' => $candidate['id'],
    			'name' => $candidate['name'],
    			'elected' => false,
    			'excluded' => false,
    			'votes' => 0,
    			'parcels' => [],
    		];
		}
	}

	public function count()
	{
		$this->countFirstPreferences();
		$this->processTransfers();

		return [
    		'quota' => $this->quota,
    		'candidates' => $this->candidates,
    	];
	}

	protected function countFirstPreferences()
	{
		$parcels = [];

    	foreach ($this->ballots as $ballot) {
    		if (!array_key_exists($ballot[0], $parcels)) {
    			$parcels[$ballot[0]] = [
    				'transfer_value' => 1,
    				'ballots' => [],
    			];
    		}

    		$parcels[$ballot[0]]['ballots'][] = $ballot;
    	}

    	foreach ($parcels as $key => $parcel) {
    		$this->candidates[$key]['parcels'][] = $parcel;
    		$this->candidates[$key]['votes'] += count($parcel['ballots']) * $parcel['transfer_value'];
    	}

		$this->findElectedCandidates();
	}

	protected function processTransfers()
	{
		while (count($this->surplus_transfer_queue)) {
    		$candidate = array_shift($this->surplus_transfer_queue);

			$last_parcel = $candidate['parcels'][array_key_last($candidate['parcels'])];
			$total_ballots = count($last_parcel['ballots']);
			$surplus = $candidate['votes'] - $this->quota;
			$transfer_value = $surplus / $total_ballots;

			$transfer_parcels = [];

			foreach ($last_parcel['ballots'] as $transfer_ballot) {
				$transfer_candidate_id = $this->findNextPreference(count($candidate['parcels']), $transfer_ballot);

				if ($transfer_candidate_id) {
					if (!array_key_exists($transfer_candidate_id, $transfer_parcels)) {
						$transfer_parcels[$transfer_candidate_id] = [
							'transfer_value' => $transfer_value,
							'ballots' => []
						]; 
					}
		    		
		    		$transfer_parcels[$transfer_candidate_id]['ballots'][] = $transfer_ballot;
				}
	    	}

	    	foreach ($transfer_parcels as $key => $parcel) {
	    		$this->candidates[$key]['parcels'][] = $parcel;
	    		$this->candidates[$key]['votes'] += count($parcel['ballots']) * $parcel['transfer_value'];

	    		$votes = count($parcel['ballots']) * $parcel['transfer_value'];

	    		ray("Transfering {$votes} to {$this->candidates[$key]['name']} from {$candidate['name']}");
	    	}

	    	$this->findElectedCandidates();

	    	$this->excludeCandidates();
	    }
	}

	protected function excludeCandidates()
	{
    	while (count($this->surplus_transfer_queue) === 0) {
    		$elected_candidates = array_filter($this->candidates, function ($candidate) {
	    		return $candidate['elected'] === true;
	    	});

	    	if (count($elected_candidates) >= $this->vacancies) {
	    		break;
	    	}

	    	$continuing_candidates = array_filter($this->candidates, function ($candidate) {
	    		return $candidate['elected'] === false && $candidate['excluded'] === false;
	    	});

	    	if (count($continuing_candidates) == 0) {
	    		break;
	    	}

	    	$excluded_candidate_id = array_key_last($continuing_candidates);

	    	ray("Excluding candidate {$this->candidates[$excluded_candidate_id]['name']}");

	    	$this->candidates[$excluded_candidate_id]['excluded'] = true;

	    	$parcels = $this->candidates[$excluded_candidate_id]['parcels'];

	    	// Sort parcels in highest transfer value order (descending order)
	    	uasort($parcels, function ($a, $b) {
	    		return ($a['transfer_value'] > $a['transfer_value']) ? -1 : 1;
	    	});

	    	foreach ($parcels as $key => $parcel) {

	    		$transfer_parcels = [];

	    		foreach ($parcel['ballots'] as $ballot) {
    				$transfer_candidate_id = $this->findNextPreference(count($parcels), $ballot);

    				if ($transfer_candidate_id) {
    					if (!array_key_exists($transfer_candidate_id, $transfer_parcels)) {
    						$transfer_parcels[$transfer_candidate_id] = [
    							'transfer_value' => $parcel['transfer_value'],
    							'ballots' => []
    						]; 
    					}
			    		
			    		$transfer_parcels[$transfer_candidate_id]['ballots'][] = $ballot;
    				}
		    	}

		    	foreach ($transfer_parcels as $key => $transfer_parcel) {
		    		$this->candidates[$key]['parcels'][] = $transfer_parcel;
		    		$this->candidates[$key]['votes'] += count($transfer_parcel['ballots']) * $transfer_parcel['transfer_value'];

		    		$votes = count($transfer_parcel['ballots']) * $transfer_parcel['transfer_value'];

		    		ray("Transfering excluded {$votes} to {$this->candidates[$key]['name']} from {$this->candidates[$excluded_candidate_id]['name']}");
		    	}
	    	}

	    	$this->findElectedCandidates();
    	}
	}

	protected function findElectedCandidates()
	{
		$this->sortCandidates();

    	// Find elected candidates and add them to the transfer queue
    	foreach ($this->candidates as $key => $candidate) {
    		if ($candidate['votes'] >= $this->quota && $candidate['elected'] === false) {
    			$this->candidates[$key]['elected'] = true;
    			
    			if ($candidate['votes'] > $this->quota) $this->surplus_transfer_queue[] = &$this->candidates[$key];

    			ray($candidate['name'].' elected');
    		}
    	}
	}

	protected function sortCandidates()
	{
		uasort($this->candidates, function ($a, $b) {
    		return ($a['votes'] > $b['votes']) ? -1 : 1;
    	});
	}

	protected function findNextPreference($minimum_preference, $ballot)
    {
    	foreach ($ballot as $preference => $candidate_id) {
    		if ($preference < $minimum_preference) continue;
    		if ($this->candidates[$candidate_id]['elected'] === true || $this->candidates[$candidate_id]['excluded'] === true) continue;
    		return $candidate_id;
    	}

    	return null;
    }
}
