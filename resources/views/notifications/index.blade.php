@extends('layouts.main')

@section('page-title')
{{ __('Notifications') }}
@endsection

@section('page-breadcrumb')
{{ __('Notifications') }}
@endsection

@section('page-action')
<div class="d-flex">
    <form action="{{ route('notifications.markAllAsRead') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-sm btn-secondary">
            <i class="ti ti-check"></i> {{ __('Mark All as Read') }}
        </button>
    </form>
</div>
@endsection
@php use Illuminate\Support\Str; @endphp
@section('content')
<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Sr. No.') }}</th>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Message') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Created At') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications as $nu)
                            @php








                            $isRead = !is_null($nu->read_at);

                            @endphp
                            <tr class="{{ $isRead ? '' : 'table-primary' }}">
                                {{-- Pagination-aware row number --}}
                                <td>{{ $loop->iteration + ($notifications->currentPage() - 1) * $notifications->perPage() }}</td>

                                <td><a href="{{ $nu->notification->full_action_url ?? '#' }}" onclick="event.stopPropagation();">{{ $nu->notification->title }}</a></td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;">
                                        {!! Str::limit(strip_tags($nu->notification->message), 50) !!}
                                    </span>

                                    <button type="button"
                                            class="btn btn-sm btn-link"
                                            data-bs-toggle="modal"
                                            data-bs-target="#notifModal{{ $nu->id }}">
                                        {{ __('View Details') }}
                                    </button>

                                    {{-- Modal --}}
                                    <div class="modal fade" id="notifModal{{ $nu->id }}" tabindex="-1" aria-labelledby="notifModalLabel{{ $nu->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="notifModalLabel{{ $nu->id }}">
                                                        {{ $nu->notification->title }}
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                                                </div>
                                                <div class="modal-body">
                                                    {!! $nu->notification->message !!}
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        {{ __('Close') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                               <td>
                                   

                                    <span class="badge bg-primary">
                                        {{ Str::of($nu->notification->type ?? '')->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td>{{ $nu->notification->created_at->format('d M Y, h:i A') }}</td>
                                <td>
                                    @if($isRead)
                                    <span class="badge bg-success">{{ __('Read') }}</span>
                                    @else
                                    <span class="badge bg-warning">{{ __('Unread') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @unless($isRead)
                                    <form action="{{ route('notifications.markAsRead', $nu->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            {{ __('Mark as Read') }}
                                        </button>
                                    </form>
                                    @endunless
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center">{{ __('No notifications found.') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $notifications->links('pagination::bootstrap-5') }}
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
