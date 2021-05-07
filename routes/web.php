<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'ElectionsController@index');
Route::get('/elections/create', 'ElectionsController@create')->name('elections.create');
Route::post('/elections', 'ElectionsController@store')->name('elections.store');
Route::get('/elections/{election}', 'ElectionsController@show')->name('elections.show');

Route::get('/elections/{election}/candidates/create', 'CandidatesController@create')->name('elections.candidates.create');
Route::post('/elections/{election}/candidates/store', 'CandidatesController@store')->name('elections.candidates.store');
Route::get('/elections/{election}/candidates/{candidate}', 'CandidatesController@show')->name('elections.candidates.show');

Route::get('/elections/{election}/vote', 'VotesController@create')->name('elections.votes.create');
Route::post('/elections/{election}/vote', 'VotesController@store')->name('elections.votes.store');