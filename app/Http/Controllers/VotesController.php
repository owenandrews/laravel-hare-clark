<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Election;

class VotesController extends Controller
{
    public function create(Election $election)
    {
    	return view('elections.votes.create', [
    		'election' => $election,
    		'candidates' => $election->candidates,
    	]);
    }

    public function store(Request $request, Election $election)
    {
    	$request->validate([
    		'preferences.*.candidate_id' => 'required',
    		'preferences.*.preference' => 'required|min:1',
    	]);

    	$vote = $election->ballots()->create();

    	$preferences = collect($request->input('preferences'));
    	$preferences->each(function ($preference) use ($vote) {
    		$vote->preferences()->attach($preference['candidate_id'], ['preference' => $preference['preference']]);
    	});

    	return redirect()->route('elections.show', $election);
    }
}
