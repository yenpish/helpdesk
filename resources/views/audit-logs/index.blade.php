@extends('layouts.app')

@section('title', 'Audit Log')
@section('section', 'Administration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Audit log</h1>
                <p>Historical record of changes made across the system.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('home') }}">Dashboard</a>
        </header>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if($auditLogs->count())
            <div class="table-responsive">
                <table class="table audit-table">
                    <thead>
                    <tr>
                        <th>Date / time</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Record</th>
                        <th>Description</th>
                        <th>Changes</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($auditLogs as $log)
                        <tr>
                            <td class="nowrap">{{ $log->created_at?->format('d M Y, h:i A') }}</td>
                            <td>{{ $log->user?->name ?? 'System / Unknown' }}</td>
                            <td><span class="audit-action">{{ strtoupper($log->action) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::headline(class_basename($log->auditable_type)) }}</td>
                            <td>#{{ $log->auditable_id }}</td>
                            <td>{{ $log->description ?? '—' }}</td>
                            <td>
                                @if(count($log->display_changes))
                                    <details class="audit-details">
                                        <summary>View changes</summary>
                                        <div class="audit-values table-responsive">
                                            <table class="audit-changes-table">
                                                <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                                                <tbody>
                                                @foreach($log->display_changes as $change)
                                                    <tr>
                                                        <th scope="row">{{ $change['field'] }}</th>
                                                        <td>{{ $change['before'] }}</td>
                                                        <td>{{ $change['after'] }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $auditLogs->links() }}</div>
        @else
            <div class="empty-state">No audit records have been recorded yet.</div>
        @endif
    </div>
@endsection
