<section>
	<form method="POST" action="{{ route('elections.votes.store', $election) }}">
		@foreach ($candidates as $candidate)
			<h2>{{ $candidate->name }}</h2>
			<input type="hidden" name="preferences[{{ $loop->index }}][candidate_id]" value="{{ $candidate->id }}">
			<input type="number" name="preferences[{{ $loop->index }}][preference]" placeholder="Preference">
		@endforeach

		@csrf
		<button>Vote</button>
	</form>
</section>