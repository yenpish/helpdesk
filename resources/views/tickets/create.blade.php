<h1>Create Ticket</h1>

<form method="POST" action="/tickets">
    @csrf
    <label>Title:</label>
    <input type="text" name="title">

    <br>

    <label>Description:</label>
    <textarea name="description"></textarea>

    <br>

    <label>Category:</label>

    <select name="category_id">

        @foreach($categories as $category)

            <option value="{{ $category->id }}">
                {{ $category->name }}
            </option>

        @endforeach

    </select>

    <label>Status:</label>
    <input type="text" name="status">

    <br>

    <label>Priority:</label>
    <input type="text" name="priority">

    <button>
        Submit
    </button>
</form>
