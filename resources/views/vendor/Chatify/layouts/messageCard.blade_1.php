{{-- -------------------- The default card (white) -------------------- --}}
@if($viewType == 'default')
    @if($from_id != $to_id)
        @php
            // In group chats ($to_id == 0), check if this is the current user's message
            $isCurrentUserMessage = ($to_id == 0 && $from_id == auth()->id());
        @endphp
        <div class="message-card {{ $isCurrentUserMessage ? 'mc-sender' : '' }}" data-id="{{ $id }}">
            @if($to_id == 0)
                @php $sender = App\Models\User::find($from_id); @endphp
                @if($sender)
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        @if(!empty($sender->avatar))
                            <div class="avatar av-m" style="width: 36px; height: 36px; background-image: url('{{ get_file($sender->avatar) }}');">
                                <span class="activeStatus"></span>
                            </div>
                        @else
                            <div class="avatar av-m" style="width: 36px; height: 36px; background-image: url('{{ get_file('uploads/users-avatar/avatar.png') }}');">
                                <span class="activeStatus"></span>
                            </div>
                        @endif
                    </div>
                @endif
            @endif
            @if($to_id == 0)
                <p><strong>{{ $sender->name ?? '' }}</strong><br>{!! ($message == null && $attachment != null && @$attachment[2] != 'file') ? $attachment[1] : nl2br($message) !!}
                    <sub title="{{ $fullTime }}">{{ $time }}</sub>
            @else
                <p>{!! ($message == null && $attachment != null && @$attachment[2] != 'file') ? $attachment[1] : nl2br($message) !!}
                    <sub title="{{ $fullTime }}">{{ $time }}</sub>
            @endif
                {{-- If attachment is a file --}}
                @if(@$attachment[2] == 'file')
                    <a href="{{ route(config('chatify.attachments.download_route_name'),['fileName'=>$attachment[0]]) }}" style="color: #595959;" class="file-download">
                        <span class="ti ti-file"></span> {{$attachment[1]}}</a>
                @endif
            </p>
        </div>
        {{-- If attachment is an image --}}
        @if(@$attachment[2] == 'image')
            <div>
                <div class="message-card">
                    <div class="image-file chat-image" style="width: 250px; height: 150px;background-image: url('{{ get_file('/uploads/attachments/' . $attachment[0]) }}')">
                    </div>
                </div>
            </div>
        @endif
    @endif
@endif

{{-- -------------------- Sender card (owner) -------------------- --}}
@if($viewType == 'sender')
    <div class="message-card mc-sender" data-id="{{ $id }}">
        {{-- Show sender info in group chats --}}
        @if($to_id == 0)
            @php $sender = App\Models\User::find($from_id); @endphp
            @if($sender)
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    @if(!empty($sender->avatar))
                        <div class="avatar av-m" style="width: 36px; height: 36px; background-image: url('{{ get_file($sender->avatar) }}');">
                            <span class="activeStatus"></span>
                        </div>
                    @else
                        <div class="avatar av-m" style="width: 36px; height: 36px; background-image: url('{{ get_file('uploads/users-avatar/avatar.png') }}');">
                            <span class="activeStatus"></span>
                        </div>
                    @endif
                </div>
            @endif
        @endif
        @if($to_id == 0)
            <p><strong>{{ $sender->name ?? '' }}</strong><br>{!! ($message == null && $attachment != null && @$attachment[2] != 'file') ? $attachment[1] : nl2br($message) !!}
                <sub title="{{ $fullTime }}" class="message-time">
        @else
            <p>{!! ($message == null && $attachment != null && @$attachment[2] != 'file') ? $attachment[1] : nl2br($message) !!}
                <sub title="{{ $fullTime }}" class="message-time">
        @endif
                <span class="ti ti-{{ $seen > 0 ? 'check-double' : 'check' }} seen"></span> {{ $time }}</sub>
            {{-- If attachment is a file --}}
            @if(@$attachment[2] == 'file')
                <a href="{{ route(config('chatify.attachments.download_route_name'),['fileName'=>$attachment[0]]) }}" class="file-download">
                    <span class="ti ti-file"></span> {{$attachment[1]}}</a>
            @endif
        </p>
    </div>
    {{-- If attachment is an image --}}
    @if(@$attachment[2] == 'image')
        <div>
            <div class="message-card mc-sender">
                <div class="image-file chat-image" style="width: 250px; height: 150px;background-image: url('{{ get_file('/uploads/attachments/' . $attachment[0])}}')">
                </div>
            </div>
        </div>
    @endif
@endif
