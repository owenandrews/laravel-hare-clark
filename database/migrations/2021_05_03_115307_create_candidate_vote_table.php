<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateVoteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('candidate_vote', function (Blueprint $table) {
            $table->foreignId('candidate_id')->constrained();
            $table->foreignId('vote_id')->constrained();
            $table->unsignedSmallInteger('preference');
            $table->timestamps();
        });
    }
}
