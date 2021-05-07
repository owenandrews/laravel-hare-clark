<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Election;

class ElectionsController extends Controller
{
    public function index()
    {
    	$elections = Election::all();

    	return view('elections.index', [
    		'elections' => $elections,
    	]);
    }

    public function create()
    {
    	return view('elections.create');
    }

    public function store(Request $request)
    {
    	$request->validate([
    		'name' => 'required',
    		'vacancies' => 'required|int|min:1',
    	]);

    	Election::create($request->only(['name', 'vacancies']));

    	return redirect('/');
    }

    public function show(Election $election)
    {
    	return view('elections.show', [
    		'election' => $election,
    		'result' => $election->countResult(),
    	]);
    }
}
