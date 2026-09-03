
<h1>Edit Attendance Record</h1>

<form method="GET" action="{{ route('event-admin.dashboard') }}">
    <label>Full Name</label>
    <input type="text" value="Behind" required>

    <label>Position</label>
    <input type="text" value="Staff" required>

    <label>Unit / Division</label>
    <input type="text" value="IT Division" required>

    <label>Phone</label>
    <input type="text" value="0123456789" required>

    <label>Email</label>
    <input type="email" value="that@example.com" required>

    <button type="submit">Update Attendance</button>
</form>