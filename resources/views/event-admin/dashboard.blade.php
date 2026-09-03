

<h1>Event Attendance Dashboard</h1>

<a href="{{ route('event-admin.event.edit') }}">
    Edit Event Title
</a>

<h2>Sesi Semakan Prototype & Dokumen URS dan SFS</h2>

<table border="1" cellpadding="10">
    <thead>
        <tr>
            <th>Name</th>
            <th>Position</th>
            <th>Unit / Division</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Submitted At</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
        <tr>
            <td>Behind</td>
            <td>Staff</td>
            <td>IT Division</td>
            <td>0123456789</td>
            <td>that@example.com</td>
            <td>9:04 AM</td>
            <td>
                <a href="{{ route('event-admin.attendance.edit') }}">Edit</a>

                <button onclick="confirm('Delete this attendance?')">
                    Delete
                </button>
            </td>
        </tr>
    </tbody>
</table>