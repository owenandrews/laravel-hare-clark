<section>
	<h1>{{ $election->name }}</h1>
	<p>Quota: {{ $result['quota'] }}</p>
	<p>Vacancies: {{ $election->vacancies }}</p>
	<h2>Candidates</h2>
	<table style="border: 1px solid;">
		@foreach ($result['candidates'] as $candidate)
			<tr>
				<td>
					<a href="{{ route('elections.candidates.show', ['election' => $election->id, 'candidate' => $candidate['id']]) }}">{{ $candidate['name'] }}</a>
				</td>
				<td>
					Votes: {{ $candidate['votes'] }}<br>
					Quotas: {{ $candidate['votes'] / $result['quota'] }}
				</td>
			</tr>
		@endforeach
	</table>
	<a href="{{ route('elections.candidates.create', $election) }}">Create Candidate</a>
	<a href="{{ route('elections.votes.create', $election) }}">Vote!</a>
</section>