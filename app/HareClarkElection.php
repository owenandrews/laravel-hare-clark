<?php
namespace App;

/* 

TODO:

- Verify ballots

*/


class HareClarkElection {
	
	protected $candidates = [];
	
	protected $ballots;
	
	protected $quota;
	
	protected $vacancies;

	public function __construct($vacancies, $candidates, $ballots)
	{
		if ($vacancies > count($candidates)) {
			throw new \Exception('Not enough candidates. There must be at least as many candidates as there are vacancies.');
		}

		$this->vacancies = $vacancies;

		$this->quota = (count($ballots) / ($vacancies + 1)) + 1;

		$this->ballots = $ballots;

		foreach ($candidates as $candidate) {
			$this->candidates[$candidate['id']] = [
    			'id' => $candidate['id'],
    			'name' => $candidate['name'],
    			'elected' => false,
    			'surplus_transferred' => false,
    			'excluded' => false,
    			'votes' => 0,
    			'parcels' => [],
    		];
		}
	}

	public function count()
	{
		$this->countFirstPreferences();

		while (count($this->electedCandidates()) < $this->vacancies) {
			ray($this->candidates, $this->quota);

			$this->sortCandidates();

			$this->processTransfers();

			$this->sortCandidates();

			$elected_candidates = $this->electedCandidates();
			$pending_transfers = $this->candidatesWithPendingTransfers();

			if (count($elected_candidates) < $this->vacancies && count($pending_transfers) == 0) {
				$continuing_candidates = $this->continuingCandidates();

				// If the number of continuing candidates equals the number of available vacancies, elect remaining candidates
		    	if (count($continuing_candidates) == $this->vacancies - count($elected_candidates)) {
		    		foreach ($continuing_candidates as $key => $candidate) {
		    			$this->candidates[$key]['elected'] = true;
		    		}

		    		break;
		    	}

		    	$this->excludeCandidate();
			}
		}

		return [
    		'quota' => $this->quota,
    		'candidates' => $this->candidates,
    	];
	}

	protected function countFirstPreferences()
	{
		$parcels = [];

    	foreach ($this->ballots as $ballot) {
    		$first_preference_candidate_id = $ballot[0];

    		if (!array_key_exists($first_preference_candidate_id, $parcels)) {
    			$parcels[$first_preference_candidate_id] = [
    				'transfer_value' => 1,
    				'ballots' => [],
    			];
    		}

    		$parcels[$first_preference_candidate_id]['ballots'][] = $ballot;
    	}

    	foreach ($parcels as $candidate_id => $parcel) {
    		$this->candidates[$candidate_id]['parcels'][] = $parcel;
    		$this->candidates[$candidate_id]['votes'] += count($parcel['ballots']) * $parcel['transfer_value'];

    		if ($this->candidates[$candidate_id]['votes'] >= $this->quota) {
    			$this->candidates[$candidate_id]['elected'] = true;

    			ray($this->candidates[$candidate_id]['name'].' elected on first preferences');
    		}
    	}
	}

	protected function processTransfers()
	{
		foreach ($this->candidates as &$candidate) {
    		if ($candidate['surplus_transferred'] === false && $candidate['votes'] >= $this->quota) {

    			$candidate['surplus_transferred'] = true;

    			// Skip transfer if exact vote
    			if ($candidate['votes'] == $this->quota) {
    				continue;
    			}
    			
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

		    	// Sort parcels in descending vote order
		    	uasort($transfer_parcels, function ($a, $b) {
		    		return (count($a['ballots']) * $a['transfer_value'] > count($b['ballots']) * $b['transfer_value']) ? -1 : 1;
		    	});

		    	foreach ($transfer_parcels as $key => $parcel) {
		    		$this->candidates[$key]['parcels'][] = $parcel;
		    		$this->candidates[$key]['votes'] += count($parcel['ballots']) * $parcel['transfer_value'];

		    		$votes = count($parcel['ballots']) * $parcel['transfer_value'];

		    		ray("Transfering {$votes} to {$this->candidates[$key]['name']} from {$candidate['name']}");

		    		if ($this->candidates[$key]['votes'] >= $this->quota) {
		    			$this->candidates[$key]['elected'] = true;

		    			ray($candidate['name'].' elected at transfer');

		    			// Exit early if all vacancies are filled
		    			if (count($this->electedCandidates()) == $this->vacancies) {
		    				return;
		    			}
		    		}
		    	}
    		}
	    }
	}

	protected function excludeCandidate()
	{
		$excluded_candidate_id = array_key_last($this->continuingCandidates());

		ray("Excluding candidate {$this->candidates[$excluded_candidate_id]['name']}");

    	$this->candidates[$excluded_candidate_id]['excluded'] = true;

    	$parcels = $this->candidates[$excluded_candidate_id]['parcels'];

    	// Sort parcels in highest transfer value order (descending order)
    	uasort($parcels, function ($a, $b) {
    		return ($a['transfer_value'] > $b['transfer_value']) ? -1 : 1;
    	});

    	foreach ($parcels as $parcel) {

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
	    	
	    		if ($this->candidates[$key]['votes'] >= $this->quota) {
	    			$this->candidates[$key]['elected'] = true;

	    			ray($this->candidates[$key]['name'].' elected at exclusion');
	    		}
	    	}
    	}
	}

	protected function candidatesWithPendingTransfers()
	{
		$pending_transfers = [];

    	foreach ($this->candidates as $key => $candidate) {
    		if ($candidate['surplus_transferred'] === false && $candidate['votes'] >= $this->quota) {
    			$pending_transfers[$key] = &$this->candidates[$key];
    		}
    	}

		return $pending_transfers;
	}

	protected function continuingCandidates()
	{
		$continuing_candidates = [];

    	foreach ($this->candidates as $key => $candidate) {
    		if ($candidate['elected'] === false && $candidate['excluded'] === false) {
    			$continuing_candidates[$key] = &$this->candidates[$key];
    		}
    	}

		return $continuing_candidates;
	}

	protected function electedCandidates()
	{
		$elected_candidates = [];

    	foreach ($this->candidates as $key => $candidate) {
    		if ($candidate['elected'] === true) {
    			$elected_candidates[$key] = &$this->candidates[$key];
    		}
    	}

		return $elected_candidates;
	}

	protected function sortCandidates()
	{
		uasort($this->candidates, function ($a, $b) {
			// Deal with exact equal votes
			if ($a['votes'] == $b['votes']) {
				return $this->findCandidateWithLeastVotes($a, $b)['id'] == $a['id'] ? 1 : -1;
			}

    		return ($a['votes'] > $b['votes']) ? -1 : 1;
    	});
	}

	protected function findCandidateWithLeastVotes($a, $b)
	{
		$a_parcels = array_reverse($a['parcels']);
		$b_parcels = array_reverse($b['parcels']);

		// Find last transfer where candidates votes were not equal and return the candidate with the lowest votes at that transfer
		for ($i = 0; $i < min(count($a_parcels), count($b_parcels)); $i++) {
		    if (count($a_parcels[$i]['ballots']) * $a_parcels[$i]['transfer_value'] != count($b_parcels[$i]['ballots']) * $b_parcels[$i]['transfer_value']) {
		    	return (count($a_parcels[$i]['ballots']) * $a_parcels[$i]['transfer_value'] < count($b_parcels[$i]['ballots']) * $b_parcels[$i]['transfer_value']) ? $a : $b;
		    }
		}

		// If the candidates votes have been equal at every transfer, return a random candidate
		return mt_rand(0, 1) ? $a : $b;
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
