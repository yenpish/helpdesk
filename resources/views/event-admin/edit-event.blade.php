
<h1>Edit Event Form Title</h1>

<form method="GET" action="{{ route('event-admin.dashboard') }}">
    <label>Event Title</label>

    <input
        type="text"
        value="Sesi Semakan Prototype & Dokumen URS dan SFS Permit-Permit KKM Bahagian Kawalan Penyakit (BKP) 26 - 27 Ogos 2026"
        required
    >

    <label>Location</label>

    <input
        type="text"
        value="Bilik Anjung"
        required
    >

    <button type="submit">Save Changes</button>
</form>