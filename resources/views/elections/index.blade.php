<section>
	<h1>Elections</h1>
	<table>
		@foreach ($elections as $election)
			<tr>
				<td>
					<a href="{{ route('elections.show', $election->id) }}">{{ $election->name }}</a>
				</td>
			</tr>
		@endforeach
	</table>
	<a href="{{ route('elections.create') }}">Create Election</a>
</section>