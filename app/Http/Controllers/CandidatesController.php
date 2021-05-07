<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Election;
use App\Models\Candidate;

class CandidatesController extends Controller
{
    public function create(Election $election)
    {
    	return view('elections.candidates.create', [
    		'election' => $election,
    	]);
    }

    public function store(Request $request, Election $election)
    {
    	$request->validate([
    		'name' => 'required',
    	]);

    	$election->candidates()->create($request->only('name'));

    	return redirect()->route('elections.show', $election);
    }

    public function show(Election $election, Candidate $candidate)
    {
    	return view('elections.candidates.show', [
    		'candidate' => $candidate,
    	]);
    }
}
