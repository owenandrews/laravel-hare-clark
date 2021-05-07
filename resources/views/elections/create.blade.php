<section>
	<form method="POST" action="{{ route('elections.store') }}">
		<input type="text" name="name" placeholder="Name">
		<input type="number" name="vacancies" placeholder="Vacancies">
		@csrf
		<button>Create</button>
	</form>
</section>