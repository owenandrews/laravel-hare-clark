<section>
	<form method="POST" action="{{ route('elections.candidates.store', $election) }}">
		<input type="text" name="name" placeholder="Name">
		@csrf
		<button>Create</button>
	</form>
</section>