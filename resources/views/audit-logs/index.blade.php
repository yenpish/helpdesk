@extends('layouts.app')

@section('title', 'Audit Log')
@section('section', 'Administration')

@section('content')
    <style>
        .table-responsive.audit-table-wrap {
            overflow-x: visible;
        }

        .audit-table {
            width: 100%;
            table-layout: fixed;
        }

        .audit-table th,
        .audit-table td {
            overflow-wrap: anywhere;
            vertical-align: top;
        }

        .audit-table th:nth-child(1) { width: 15%; }
        .audit-table th:nth-child(2) { width: 13%; }
        .audit-table th:nth-child(3) { width: 10%; }
        .audit-table th:nth-child(4) { width: 19%; }
        .audit-table th:nth-child(5) { width: 43%; }

        .audit-timestamp span {
            display: block;
        }

        .audit-details {
            margin-top: 6px;
        }

        .audit-details summary {
            color: var(--blue);
            cursor: pointer;
        }

        .audit-change-list {
            display: grid;
            gap: 8px;
            margin: 10px 0 0;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 5px;
            background: var(--surface);
        }

        .audit-change {
            display: grid;
            grid-template-columns: minmax(90px, 0.35fr) minmax(0, 1fr);
            gap: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--line);
        }

        .audit-change:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .audit-change dt {
            font-weight: 600;
        }

        .audit-change dd {
            min-width: 0;
            margin: 0;
        }

        .audit-change-values {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            align-items: start;
            gap: 8px;
        }

        .audit-change-value {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .audit-change-value small {
            display: block;
            margin-bottom: 2px;
            color: var(--muted);
        }

        .audit-change-value.after {
            font-weight: 600;
        }

        .audit-change-arrow {
            color: var(--muted);
        }

        @media (max-width: 760px) {
            .table-responsive.audit-table-wrap {
                overflow-x: auto;
            }

            .audit-table {
                min-width: 720px;
            }
        }
    </style>
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
            <div class="table-responsive audit-table-wrap">
                <table class="table audit-table">
                    <thead>
                    <tr>
                        <th>Date / time</th>
                        <th>By</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>What happened</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($auditLogs as $log)
                        @php
                            $recordType = class_basename($log->auditable_type ?? '');
                            $recordLabel = \Illuminate\Support\Str::headline($recordType);
                            $changeFields = collect($log->display_changes)->pluck('field')->all();
                            $description = trim($log->description ?? '');
                            $genericDescription = preg_match(
                                '/^(event|session|pre-registration status) updated\.?$|^session rescheduled with the event\.?$/i',
                                $description
                            ) === 1;
                            $whatHappened = $description ?: \Illuminate\Support\Str::headline($log->action) . ' ' . strtolower($recordLabel);

                            if ($genericDescription && count($changeFields)) {
                                $changedSchedule = count(array_intersect($changeFields, [
                                    'Starts At', 'Ends At', 'Attendance Opens At', 'Attendance Closes At',
                                ])) > 0;

                                if ($recordType === 'EventSession' && $changedSchedule) {
                                    $whatHappened = 'Session schedule changed';
                                } elseif ($recordType === 'Event' && $changedSchedule) {
                                    $whatHappened = 'Event schedule changed';
                                } elseif ($recordType === 'Event' && in_array('Status', $changeFields, true)) {
                                    $whatHappened = 'Event status changed';
                                } elseif ($recordType === 'Registration' && in_array('Status', $changeFields, true)) {
                                    $whatHappened = 'Pre-registration status changed';
                                } else {
                                    $whatHappened = 'Updated: ' . implode(', ', $changeFields);
                                }
                            }
                        @endphp
                        <tr>
                            <td class="audit-timestamp">
                                <span>{{ $log->created_at?->format('d M Y') }}</span>
                                <span>{{ $log->created_at?->format('h:i A') }}</span>
                            </td>
                            <td>{{ $log->user?->name ?? 'System / Unknown' }}</td>
                            <td><span class="audit-action">{{ strtoupper($log->action) }}</span></td>
                            <td>{{ $recordLabel }} #{{ $log->auditable_id }}</td>
                            <td>
                                <div>{{ $whatHappened }}</div>
                                @if(count($log->display_changes))
                                    <details class="audit-details">
                                        <summary>View changes</summary>
                                        <dl class="audit-change-list">
                                            @foreach($log->display_changes as $change)
                                                <div class="audit-change">
                                                    <dt>{{ $change['field'] }}</dt>
                                                    <dd class="audit-change-values">
                                                        <span class="audit-change-value before">
                                                            <small>Before</small>
                                                            {{ $change['before'] }}
                                                        </span>
                                                        <span class="audit-change-arrow" aria-hidden="true">→</span>
                                                        <span class="audit-change-value after">
                                                            <small>After</small>
                                                            {{ $change['after'] }}
                                                        </span>
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('components.pagination-row', ['paginator' => $auditLogs, 'ariaLabel' => 'Audit log pages'])
        @else
            <div class="empty-state">No audit records have been recorded yet.</div>
        @endif
    </div>
@endsection
