@extends('layouts.main')

@section('content')
<div class="container-fluid">
    <div class="page-title">
        <div class="row">
            <div class="col-6">
                <h3>{{ __('Numbering Audit Log') }}</h3>
            </div>
            <div class="col-6 text-end">
                <a href="{{ route('settings.numbering.index') }}" class="btn btn-secondary">
                    <i class="ti ti-arrow-left"></i> {{ __('Back to Configuration') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Audit Log') }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Module') }}</th>
                                <th>{{ __('Scope Type') }}</th>
                                <th>{{ __('Scope ID') }}</th>
                                <th>{{ __('Action') }}</th>
                                <th>{{ __('Changed By') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $logs = \DB::table('numbering_config_logs')
                                    ->orderBy('created_at', 'desc')
                                    ->limit(100)
                                    ->get();
                            @endphp
                            @if($logs->count() > 0)
                                @foreach($logs as $log)
                                <tr>
                                    <td>{{ $log->created_at }}</td>
                                    <td>{{ strtoupper($log->module) }}</td>
                                    <td>{{ $log->scope_type }}</td>
                                    <td>{{ $log->scope_id ?? 'Global' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $log->action_type == 'create' ? 'success' : ($log->action_type == 'update' ? 'info' : 'danger') }}">
                                            {{ ucfirst($log->action_type) }}
                                        </span>
                                        @if($log->is_rollback)
                                            <span class="badge badge-warning">{{ __('Rollback') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->changed_by }}</td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center">{{ __('No audit logs found.') }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
