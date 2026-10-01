@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
    <div class="app-box" style="max-width: 1200px;">

        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:24px;">
            <div>
                <h1 style="margin:0 0 6px;">Audit Log</h1>
                <p style="margin:0; color:#666;">
                    Historical record of changes made across the system.
                </p>
            </div>

            <a href="{{ url()->previous() }}"
               style="padding:10px 14px; background:#eee; color:#222; text-decoration:none; border-radius:4px;">
                Back
            </a>
        </div>

        @if(session('success'))
            <div style="padding:12px 14px; margin-bottom:20px; background:#e8f5e9; border:1px solid #c8e6c9; color:#2e7d32;">
                {{ session('success') }}
            </div>
        @endif

        @if($auditLogs->count())
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; background:white;">
                    <thead>
                    <tr style="background:#f1f1f1; text-align:left;">
                        <th style="padding:12px; border-bottom:1px solid #ddd;">Date / Time</th>
                        <th style="padding:12px; border-bottom:1px solid #ddd;">Actor</th>
                        <th style="padding:12px; border-bottom:1px solid #ddd;">Action</th>
                        <th style="padding:12px; border-bottom:1px solid #ddd;">Module</th>
                        <th style="padding:12px; border-bottom:1px solid #ddd;">Record</th>
                        <th style="padding:12px; border-bottom:1px solid #ddd;">Description</th>
                        <th style="padding:12px; border-bottom:1px solid #ddd;">Details</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($auditLogs as $log)
                        <tr>
                            <td style="padding:12px; border-bottom:1px solid #eee; white-space:nowrap;">
                                {{ $log->created_at?->format('d M Y, h:i A') }}
                            </td>

                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                {{ $log->user?->name ?? 'System / Unknown' }}
                            </td>

                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <strong>{{ strtoupper($log->action) }}</strong>
                            </td>

                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                {{ class_basename($log->auditable_type) }}
                            </td>

                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                #{{ $log->auditable_id }}
                            </td>

                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                {{ $log->description ?? '—' }}
                            </td>

                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                @if($log->old_values || $log->new_values)
                                    <details>
                                        <summary style="cursor:pointer;">
                                            View changes
                                        </summary>

                                        <div style="margin-top:10px; min-width:300px;">
                                            @if($log->old_values)
                                                <strong>Before</strong>

                                                <pre style="white-space:pre-wrap; font-size:12px; background:#f7f7f7; padding:10px; margin-top:6px;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @endif

                                            @if($log->new_values)
                                                <strong>After</strong>

                                                <pre style="white-space:pre-wrap; font-size:12px; background:#f7f7f7; padding:10px; margin-top:6px;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @endif
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

            <div style="margin-top:20px;">
                {{ $auditLogs->links() }}
            </div>
        @else
            <div style="padding:30px; text-align:center; background:#f8f8f8; border:1px solid #ddd;">
                No audit records have been recorded yet.
            </div>
        @endif

    </div>
@endsection
