
{{-- -------------------- Saved Messages -------------------- --}}
@if($get == 'saved')
    <table class="messenger-list-item m-li-divider @if('user_'.Auth::user()->id == $id && $id != "0") m-list-active @endif">
        <tr data-action="0">
            {{-- Avatar side --}}
            <td>
                <div class="avatar av-m" style="background-color: #D9EFFF; text-align: center;">
                    <span class="ti ti-bookmark" style="font-size: 22px; color: #6FD943;"></span>
                </div>
            </td>
            {{-- center side --}}
            <td>
                <p data-id="{{ 'user_'.Auth::user()->id }}">{{__('Saved Messages')}} <span>{{__('You')}}</span></p>
                <span>{{__('Save messages secretly')}}</span>
            </td>
        </tr>
    </table>
@endif

@if(!empty($user))
    {{-- -------------------- All users/group list -------------------- --}}
    @if($get == 'users')
        <table class="messenger-list-item @if($user->id == $id && $id != "0") m-list-active @endif" data-contact="{{ $type == 'group' ? 'group_'.$user->id : (!empty($user)?$user->id:0) }}">
            <tr data-action="0">
                {{-- Avatar side --}}
                <td style="position: relative">
                    @if($type == 'group')
                         <div class="avatar av-m" style="display: flex; align-items: center; justify-content: center; background-color: #6fd943; color: white;">
                            <span>{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                        </div>
                    @else
                        @if($user->active_status)
                            <span class="activeStatus"></span>
                        @endif
                        @if(!empty($user->avatar))
                            <div class="avatar av-m"
                                 style="background-image: url('{{ get_file($user->avatar)}}');">
                            </div>
                        @else
                            <div class="avatar av-m"
                                 style="background-image: url('{{ get_file('uploads/users-avatar/avatar.png') }}');">
                            </div>
                        @endif
                    @endif
                </td>
                {{-- center side --}}
                <td>
                    <p data-id="{{ $type.'_'.$user->id }}">
                        {{ strlen($user->name) > 35 ? trim(substr($user->name,0,12)).'..' : $user->name }}
                        <span class="chat-time">{{ !empty($lastMessage)?$lastMessage->created_at->diffForHumans():'' }}</span>
                    </p>
                    <span class="chat-msg">
                        @if(!empty($lastMessage))
                            {{-- Last Message use indicator --}}
                            {!!
                                $lastMessage->from_id == Auth::user()->id
                                ? '<span class="lastMessageIndicator">You :</span>'
                                : ''
                            !!}
                            {{-- Last message body --}}
                            @if($lastMessage->attachment == null)
                                {{
                                    strlen($lastMessage->body) > 30
                                    ? trim(substr($lastMessage->body, 0, 30)).'..'
                                    : $lastMessage->body
                                }}
                            @else
                                <span class="ti ti-file"></span> Attachment
                            @endif
                        @else
                            <span style="font-style: italic; color: #aaa;">Start a conversation</span>
                        @endif
                    </span>
                    {{-- New messages counter --}}
                    {!! $unseenCounter > 0 ? "<b>".$unseenCounter."</b>" : '' !!}
                </td>
            </tr>
        </table>
    @endif

    {{-- -------------------- Search Item -------------------- --}}
    @if($get == 'search_item')
        <table class="messenger-list-item" data-contact="{{ $user->id }}">
            <tr data-action="0">
                {{-- Avatar side --}}
                <td style="position: relative">
                    @if($user->active_status)
                        <span class="activeStatus"></span>
                    @endif
                    @if(!empty($user->avatar))
                        <div class="avatar av-m"
                            style="background-image: url('{{ get_file($user->avatar)}}');">
                        </div>
                    @else
                        <div class="avatar av-m"
                            style="background-image: url('{{ get_file('uploads/users-avatar/avatar.png') }}');">
                        </div>
                    @endif
                </td>
                {{-- center side --}}
                <td>
                    <p data-id="{{ $type.'_'.$user->id }}">
                    {{ strlen($user->name) > 35 ? trim(substr($user->name,0,12)).'..' : $user->name }}
                </td>

            </tr>
        </table>
    @endif


    {{-- -------------------- Get All Members -------------------- --}}

    @if($get == 'all_members')
        <table class="messenger-list-item" data-contact="{{ $user->id }}">
            <tr data-action="0">
                {{-- Avatar side --}}
                <td style="position: relative">
                    @if($user->active_status)
                        <span class="activeStatus"></span>
                    @endif
                    @if(!empty($user->avatar))
                        <div class="avatar av-m"
                             style="background-image: url('{{ get_file($user->avatar)}}');">
                        </div>
                    @else
                        <div class="avatar av-m"
                             style="background-image: url('{{ get_file('uploads/users-avatar/avatar.png') }}');">
                        </div>
                    @endif
                </td>
                {{-- center side --}}
                <td>
                    <p data-id="{{ $type.'_'.$user->id }}">
                    {{ strlen($user->name) > 35 ? trim(substr($user->name,0,12)).'..' : $user->name }}
                </td>

            </tr>
        </table>
    @endif

@endif


{{-- -------------------- Shared photos Item -------------------- --}}
@if($get == 'sharedPhoto')
    <div class="shared-photo chat-image" style="background-image: url('{{ $image }}')"></div>
@endif
