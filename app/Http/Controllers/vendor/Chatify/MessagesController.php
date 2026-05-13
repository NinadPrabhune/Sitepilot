<?php

namespace App\Http\Controllers\vendor\Chatify;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use App\Models\ChMessage as Message;
use App\Models\ChFavorite as Favorite;
use Chatify\Facades\ChatifyMessenger as Chatify;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Support\Str;
use App\Events\UserNotificationEvent;
use App\Notifications\ChatMessageNotification;
use Illuminate\Support\Facades\Validator;
use App\Services\FCMService;
use Workdo\Taskly\Entities\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessagesController extends Controller {

    /**
     * Authinticate the connection for pusher
     *
     * @param Request $request
     *
     * @return void
     */
    public function __construct() {
        $admin_settings = getAdminAllSetting();

        // Only override if admin settings have valid values, otherwise use .env defaults
        config(['chatify.pusher.key' => !empty($admin_settings['PUSHER_APP_KEY']) ? $admin_settings['PUSHER_APP_KEY'] : env('PUSHER_APP_KEY')]);
        config(['chatify.pusher.secret' => !empty($admin_settings['PUSHER_APP_SECRET']) ? $admin_settings['PUSHER_APP_SECRET'] : env('PUSHER_APP_SECRET')]);
        config(['chatify.pusher.app_id' => !empty($admin_settings['PUSHER_APP_ID']) ? $admin_settings['PUSHER_APP_ID'] : env('PUSHER_APP_ID')]);
        config(['chatify.pusher.options.cluster' => !empty($admin_settings['PUSHER_APP_CLUSTER']) ? $admin_settings['PUSHER_APP_CLUSTER'] : env('PUSHER_APP_CLUSTER', 'ap2')]);
    }

    public function pusherAuth(Request $request) {
        try {
            $user = Auth::user();

            // If no user is authenticated
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Build channel data
            $authData = json_encode([
                'user_id' => $user->id,
                'user_info' => [
                    'name' => $user->name,
                ],
            ]);

            // Return Pusher auth response
            return Chatify::pusherAuth(
                $request['channel_name'],
                $request['socket_id'],
                $authData
            );
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    }

    /**
     * Get unseen message count for authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnseenCount(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $unseenCount = Message::where('to_id', $user->id)
            ->where('seen', 0)
            ->count();

        return response()->json([
            'success' => true,
            'unseen_count' => $unseenCount,
        ]);
    }

    /**
     * Returning the view of the app with the required data.
     *
     * @param int $id
     *
     * @return void
     */
    public function index($id = null) {
        if (Auth::check()) {
            // get current route
            $routeName = FacadesRequest::route()->getName();
            $route = (in_array(
                            $routeName, ['user', config('chatify.routes.prefix')]
                    )) ? 'user' : $routeName;

            // prepare id
            return view(
                    'Chatify::pages.app', [
                'id' => ($id == null) ? 0 : $route . '_' . $id,
                'type' => ($id == null) ? 'user' : $route,
                'route' => $route,
                'messengerColor' => Auth::user()->messenger_color,
                'dark_mode' => (company_setting('cust_darklayout') == 'on') ? 'dark' : 'light',
                    ]
            );
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Fetch data by id for (user/group)
     *
     * @param Request $request
     *
     * @return collection
     */
    public function idFetchData(Request $request) {
        // Favorite
        $favorite = Chatify::inFavorite($request['id']);

        // Initialize $fetch
        $fetch = null;

        // User data
        if ($request['type'] === 'user') {
            $fetch = User::where('id', $request['id'])->first();
            if ($fetch && !empty($fetch->avatar)) {
                $avatar = get_file($fetch->avatar);
            } else {
                $avatar = get_file('uploads/users-avatar/avatar.png');
            }
        } else if ($request['type'] === 'group') {
            $fetch = Project::find($request['id']);
            $fetch->name = $fetch->name; // Ensure name property exists
            $avatar = null; // Groups handled in view
        }

        // send the response
        return response()->json([
                    'favorite' => $favorite,
                    'fetch' => $fetch,
                    'user_avatar' => $avatar,
        ]);
    }

    /**
     * This method to make a links for the attachments
     * to be downloadable.
     *
     * @param string $fileName
     *
     * @return void
     */
    public function download($fileName) {
        // storage_path() . '/' . config('chatify.attachments.folder') . '/' . $fileName
        $path = get_file('/uploads/attachments/' . $fileName);
        if (file_exists($path)) {
            return Response::download($path, $fileName);
        } else {
            return abort(404, "Sorry, File does not exist in our server or may have been deleted!");
        }
    }

    /**
     * Send a message to database
     *
     * @param Request $request
     *
     * @return JSON response
     */
    public function send(Request $request) {

        $validationRules = [
            'type' => 'required|string',
            'message' => 'nullable|string',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,gif,doc,docx,pdf,zip,txt,xlsx,ppt,pptx|max:153600', // 10MB max
        ];

        if ($request->input('type') == 'group') {
            $validationRules['id'] = 'required|exists:projects,id';
        } else {
            $validationRules['id'] = 'required|exists:users,id';
        }

        $request->validate($validationRules);

            // Determine the type of authentication
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $userId = $user->id;
        $userName = $user->name;

        // ✅ Check permission for group messages
        if ($request['type'] == 'group') {
            $projectId = $request['id'];
            $userInGroup = DB::table('user_projects')
                ->where('user_id', $userId)
                ->where('project_id', $projectId)
                ->exists();

            if (!$userInGroup) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to send messages in this group.'
                ], 403);
            }
        }

//        dd($request->all());
        // default variables
        $error = (object) [
                    'status' => 0,
                    'message' => null
        ];
        $attachment = null;
        $attachment_title = null;

        // if there is attachment [file]
        if ($request->hasFile('file')) {
            // allowed extensions
            $allowed_images = Chatify::getAllowedImages();
            $allowed_files = Chatify::getAllowedFiles();
            $allowed = array_merge($allowed_images, $allowed_files);

            $file = $request->file('file');
            // if size less than 150MB
            if ($file->getSize() < 150000000) {
                if (in_array($file->getClientOriginalExtension(), $allowed)) {
                    // get attachment name
                    $attachment_title = $file->getClientOriginalName();
                    // upload attachment and store the new name

                    $dir = '/attachments/';
                    $attachment = Str::uuid() . "." . $file->getClientOriginalExtension();
                    $path = upload_file($request, 'file', $attachment, $dir);
                    if ($path['flag'] == 1) {
                        $url = $path['url'];
                    } else {
                        $error->message = "File extension not allowed!";
                    }

//                    $file->storeAs("/" . config('chatify.attachments.folder'), $attachment);
                } else {
                    $error->status = 1;
                    $error->message = "File extension not allowed!";
                }
            } else {
                $error->status = 1;
                $error->message = "File extension not allowed!";
            }
        }
        if (!$error->status) {
            // send to database
            // Use current timestamp as unique message ID (non-zero, monotonic)
            $messageID = (int)(microtime(true) * 10000000); // milliseconds since epoch * 10000

            $messageText = trim($request->input('message', ''));
            $body = $messageText !== '' ? htmlentities($messageText, ENT_QUOTES, 'UTF-8') : null;

            $attachmentData = $attachment ? json_encode((object) [
                                'new_name' => $attachment,
                                'old_name' => htmlentities(trim($attachment_title), ENT_QUOTES, 'UTF-8'),
                            ]) : null;

            // ✅ Ensure at least one of body or attachment is not null
            if (is_null($body) && is_null($attachmentData)) {
                return response()->json([
                            'status' => false,
                            'message' => 'Message body and attachment cannot both be empty.',
                                ], 422);
            }

            if ($request['type'] == 'group') {
                $msgData = Message::create([
                    'id' => $messageID,
                    'type' => $request['type'],
                    'from_id' => $userId,
                    'to_id' => 0,
                    'project_id' => $request['id'],
                    'body' => $body,
                    'attachment' => $attachmentData,
                ]);

                // Refetch to ensure it's in DB and ready for rendering
                $messageData = Chatify::fetchMessage($msgData->id);
                // if fetchMessage returns empty, fallback to building from created model
                if (empty($messageData)) {
                    $messageData = [
                        'id' => $msgData->id,
                        'from_id' => $msgData->from_id,
                        'to_id' => $msgData->to_id,
                        'message' => $msgData->body,
                        'attachment' => null,
                        'time' => $msgData->created_at->diffForHumans(),
                        'fullTime' => $msgData->created_at,
                        'viewType' => 'sender',
                        'seen' => 0,
                    ];
                    // handle attachment if present
                    if ($msgData->attachment) {
                        $attachmentOBJ = json_decode($msgData->attachment);
                        $attachment = $attachmentOBJ->new_name;
                        $attachment_title = htmlentities(trim($attachmentOBJ->old_name), ENT_QUOTES, 'UTF-8');
                        $ext = pathinfo($attachment, PATHINFO_EXTENSION);
                        $attachment_type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file';
                        $messageData['attachment'] = [$attachment, $attachment_title, $attachment_type];
                    }
                }

                // Send to all project members
                $project = Project::find($request['id']);
                if($project) {
                    $members = $project->users;
                    foreach($members as $member) {
                        if($member->id != $userId) {
                            $renderedMessage = Chatify::messageCard($messageData, 'default');
                            // only push if message rendered successfully (not empty)
                            if (!empty(trim($renderedMessage))) {
                                $projectName = $project->name ?? 'Group';
                                Chatify::push(
                                    'private-chatify', 'messaging', [
                                        'from_id' => $userId,
                                        'to_id' => $member->id,
                                        'message' => $renderedMessage,
                                        'type' => 'group',
                                        'id' => $request['id'],
                                        'sender_name' => $userName,
                                        'group_name' => $projectName,
                                    ]
                                );
                            }
                        }
                    }
                }
            } else {
                Chatify::newMessage([
                    'id' => $messageID,
                    'type' => $request['type'],
                    'from_id' => $userId,
                    'to_id' => $request['id'],
                    'body' => $body,
                    'attachment' => $attachmentData,
                ]);

                // fetch message to send it with the response
                $messageData = Chatify::fetchMessage($messageID);

                // send to user using pusher
                Chatify::push(
                        'private-chatify', 'messaging', [
                    'from_id' => $userId,
                    'to_id' => $request['id'],
                    'message' => Chatify::messageCard($messageData, 'default'),
                    'type' => 'user',
                    'id' => $userId,
                    'sender_name' => $userName,
                        ]
                );
            }

            $notificationText = $messageText !== '' ? strip_tags($messageText) : '📎 Sent an attachment';

            $notification = [
                'title' => 'New Message',
                'body'  => $userName . ': ' . $notificationText,
            ];

            $data = [
                'type' => 'chat',
                'message_id' => $messageID,
                'sender_id' => $userId,
                'sender_name' => $userName,
            ];

            $fcm = app(FCMService::class);

            if ($request['type'] == 'group') {
                $project = Project::find($request['id']);
                if ($project) {
                    $members = $project->users;
                    foreach ($members as $member) {
                        if ($member->id != $userId) {
                            $fcm->sendToUser($member, $notification, $data);
                        }
                    }
                }
            } else {
                $receiver = User::find($request['id']);
                if ($receiver) {
                    $fcm->sendToUser($receiver, $notification, $data);
                }
            }


//            $notificationData = [
//                'type' => 'chat',
//                'title' => 'New Message',
//                'sender_id' => $userId,
//                'sender_name' => $userName,
//                'message' => trim($request['message']) ?: 'Sent a file',
//                'message_id' => $messageID,
//                'attachment' => $attachment ? [
//            'new_name' => $attachment,
//            'old_name' => $attachment_title
//                ] : null,
//            ];
//
//            // Send notification
//            $receiver->notify(new ChatMessageNotification($notificationData));
//            // Real-time notification
//            broadcast(new UserNotificationEvent(
//                            $receiver->id,
//                            [
//                        'type' => 'chat',
//                        'title' => 'New Message',
//                        'body' => $userName . ': ' . strip_tags($request['message']),
//                        'message_id' => $messageID,
//                            ]
//            ));
        }


        if ($request->route()->middleware() && in_array('api', $request->route()->middleware())) {

            // API response: plain JSON with only the message
            return response()->json([
                        'status' => 200,
                        'error' => $error->status ? 1 : 0,
                        'error_msg' => $error->message,
                        'message' => $messageData ? $messageData : null,
                        'tempID' => $request['temporaryMsgId'],
                        'res' => 'if',
            ]);
        } else {

            // Web response: full payload
            return response()->json([
                        'status' => 200,
                        'error' => $error->status ? 1 : 0,
                        'error_msg' => $error->message,
                        'message' => Chatify::messageCard(@$messageData),
                        'tempID' => $request['temporaryMsgId'],
                        'res' => 'else',
            ]);
        }

//        // send the response
//        if ($request->wantsJson()) {
//            // API response: plain JSON with only the message
//            return response()->json([
//                'status'    => 200,
//                'error'     => $error->status ? 1 : 0,
//                'error_msg' => $error->message,
//                'message' => $messageData ? $messageData : null,
//                'tempID'    => $request['temporaryMsgId'],
//                'res'    => 'if',
//            ]);
//        } else {
//            // Web response: full payload
//            return response()->json([
//                'status'    => 200,
//                'error'     => $error->status ? 1 : 0,
//                'error_msg' => $error->message,
//                'message'   => Chatify::messageCard(@$messageData),
//                'tempID'    => $request['temporaryMsgId'],
//                'res'    => 'else',
//            ]);
//        }
//        // send the response
//        return Response::json(
//                        [
//                            'status' => '200',
//                            'error' => $error->status ? 1 : 0,
//                            'error_msg' => $error->message,
//                            'message' => Chatify::messageCard(@$messageData),
//                            'tempID' => $request['temporaryMsgId'],
//                        ]
//                );
    }

    /**
     * fetch [user/group] messages from database
     *
     * @param Request $request
     *
     * @return JSON response
     */
//    public function fetch(Request $request) {
////        dd($request->all());
//        // messages variable
//        $allMessages = null;
//
//        if ($request['type'] == 'group') {
//             // ✅ Verify user is part of this group
//             $projectId = $request['id'];
//             $userId = Auth::check() ? Auth::user()->id : null;
//
//             if (!$userId) {
//                 return response()->json(['error' => 'Unauthorized'], 401);
//             }
//
//             $userInGroup = DB::table('user_projects')
//                 ->where('user_id', $userId)
//                 ->where('project_id', $projectId)
//                 ->exists();
//
//             if (!$userInGroup) {
//                 return response()->json(['error' => 'You do not have access to this group'], 403);
//             }
//
//             $messages = Message::where('project_id', $projectId)->orderBy('created_at', 'asc')->get();
//        } else {
//             // fetch messages
//             $query = Chatify::fetchMessagesQuery($request['id'])->orderBy('created_at', 'asc');
//             $messages = $query->get();
//        }
//
//        // if there is a messages
//        if ($messages->count() > 0) {
//            foreach ($messages as $message) {
//                $allMessages .= Chatify::messageCard(
//                        Chatify::fetchMessage($message->id)
//                );
//            }
//
//            // send the response
//            return Response::json(
//                            [
//                                'count' => $messages->count(),
//                                'messages' => $allMessages,
//                            ]
//                    );
//        }
//
//        // send the response
//        return Response::json(
//                        [
//                            'count' => $messages->count(),
//                            'messages' => '<p class="message-hint"><span>Say \'hi\' and start messaging</span></p>',
//                        ]
//                );
//    }
    
    public function fetch(Request $request)
{
    try {
        $allMessagesHtml = '';
        $messagesJson    = [];

        if ($request['type'] === 'group') {
            // ✅ Verify user is part of this group
            $projectId = $request['id'];
            $userId    = Auth::id();

            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $userInGroup = DB::table('user_projects')
                ->where('user_id', $userId)
                ->where('project_id', $projectId)
                ->exists();

            if (!$userInGroup) {
                return response()->json(['error' => 'You do not have access to this group'], 403);
            }

            $messages = Message::where('project_id', $projectId)
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            // ✅ Direct user chat
            $messages = Chatify::fetchMessagesQuery($request['id'])
                ->orderBy('created_at', 'asc')
                ->get();
        }

        if ($messages->count() > 0) {
            foreach ($messages as $message) {
                // Web rendering
                $allMessagesHtml .= Chatify::messageCard(
                    Chatify::fetchMessage($message->id)
                );

                // API/mobile JSON
                $messagesJson[] = [
                    'id'        => $message->id,
                    'from_id'   => $message->from_id,
                    'to_id'     => $message->to_id,
                    'body'      => $message->body,
                    'seen'      => $message->seen,
                    'created_at'=> $message->created_at,
                ];
            }

            return response()->json([
                'count'    => $messages->count(),
                'messages' => $allMessagesHtml,
                'messages_json' => $messagesJson,
            ], 200);
        }

        return response()->json([
            'count'    => 0,
            'messages' => '<p class="message-hint"><span>Say \'hi\' and start messaging</span></p>',
            'messages_json' => [],
        ], 200);

    } catch (\Exception $e) {
        \Log::error('[Chatify] fetch error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
    }
}


//    public function fetchMobile(Request $request) {
//        try {
//            // Validate required fields
//            $validator = Validator::make($request->all(), [
//                'from_id' => 'required|integer',
//                'to_id' => 'required|integer',
//            ]);
//
//            if ($validator->fails()) {
//                return response()->json([
//                            'status' => 'error',
//                            'message' => $validator->messages()->first(),
//                            'errors' => $validator->errors(),
//                                ], 422);
//            }
//
//            $fromId = $request->from_id;
//            $toId = $request->to_id;
//
//            // Fetch messages between two users
//            $messages = Message::where(function ($q) use ($fromId, $toId) {
//                        $q->where('from_id', $fromId)
//                                ->where('to_id', $toId);
//                    })
//                    ->orWhere(function ($q) use ($fromId, $toId) {
//                        $q->where('from_id', $toId)
//                                ->where('to_id', $fromId);
//                    })
//                    ->orderBy('created_at', 'asc')
//                    ->get();
//
//            // Format JSON response
//            $jsonMessages = [];
//
//            foreach ($messages as $msg) {
//                $jsonMessages[] = [
//                    'id' => $msg->id,
//                    'from_id' => $msg->from_id,
//                    'to_id' => $msg->to_id,
//                    'message' => $msg->body,
//                    'attachment' => $msg->attachment,
//                    'type' => $msg->type,
//                    'seen' => $msg->seen,
//                    'created_at' => $msg->created_at,
//                ];
//            }
//
//            return response()->json([
//                        'status' => 'success',
//                        'count' => $messages->count(),
//                        'messages' => $jsonMessages,
//                            ], 200);
//        } catch (\Exception $e) {
//            return response()->json([
//                        'status' => 'error',
//                        'message' => $e->getMessage(),
//                        'line' => $e->getLine(),
//                        'file' => $e->getFile(),
//                            ], 500);
//        }
//    }



public function fetchMobile(Request $request)
{
    try {
        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'type'   => 'required|string|in:user,group',
            'from_id'=> 'required|integer',
            'to_id'  => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->messages()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $type   = $request->type;
        $fromId = $request->from_id;
        $toId   = $request->to_id;

        $messages = collect();

        if ($type === 'group') {
            // ✅ Verify user is part of this group/project
            $userInGroup = DB::table('user_projects')
                ->where('user_id', $fromId)
                ->where('project_id', $toId)
                ->exists();

            if (!$userInGroup) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You do not have access to this group',
                ], 403);
            }

            // ✅ Fetch group messages
            $messages = Message::where('project_id', $toId)
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            // ✅ Fetch direct user-to-user messages
            $messages = Message::where(function ($q) use ($fromId, $toId) {
                    $q->where('from_id', $fromId)->where('to_id', $toId);
                })
                ->orWhere(function ($q) use ($fromId, $toId) {
                    $q->where('from_id', $toId)->where('to_id', $fromId);
                })
                ->orderBy('created_at', 'asc')
                ->get();
        }

        // ✅ Format JSON response
        $jsonMessages = $messages->map(function ($msg) {
            return [
                'id'         => $msg->id,
                'from_id'    => $msg->from_id,
                'to_id'      => $msg->to_id,
                'message'    => $msg->body,
                'attachment' => $msg->attachment,
                'type'       => $msg->type,
                'seen'       => $msg->seen,
                'created_at' => $msg->created_at,
            ];
        })->values();

        return response()->json([
            'status'   => 'success',
            'count'    => $messages->count(),
            'messages' => $jsonMessages,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('[Chatify] fetchMobile error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ], 500);
    }
}

    /**
     * Make messages as seen
     *
     * @param Request $request
     *
     * @return void
     */
    public function seen(Request $request) {
        // make as seen
        $seenmessage = Message::Where('from_id', $request['id'])->where('to_id', Auth::user()->id)->where('seen', 0)->count();
        $messageCount = Message::where('to_id', Auth::user()->id)->where('seen', 0)->count();

        $seen = Chatify::makeSeen($request['id']);

        if ($seen) {
            $messageCount = $messageCount - $seenmessage;
        }
        // send the response
        return Response::json(
                        [
                            'status' => $seen,
                            'messengerCount' => $messageCount
                        ], 200
                );
    }

    /**
     * Get contacts list
     *
     * @param Request $request
     *
     * @return JSON response
     */
//    public function getContacts(Request $request) {
//        try {
//            $authUser = Auth::user();
//            \Log::debug('[Chatify] getContacts', [
//                'user_id' => $authUser->id,
//                'user_email' => $authUser->email,
//                'workspace' => getActiveWorkSpace(),
//            ]);
//
//            // get all users that received/sent message from/to [Auth user]
//            $users = Message::join(
//                        'users', function ($join) {
//                            $join->on('ch_messages.from_id', '=', 'users.id')->orOn('ch_messages.to_id', '=', 'users.id');
//                        }
//                )->where('ch_messages.from_id', Auth::user()->id)->orWhere('ch_messages.to_id', Auth::user()->id)->orderBy('ch_messages.created_at', 'desc')->get()->unique('id');
//
//        $contacts = '';
//        $active_type = $request['messenger_type'] ?? 'user';
//
//        if ($users->count() > 0) {
//            // fetch contacts
//            foreach ($users as $user) {
//                if ($user->id != Auth::user()->id) {
//                    // Get user data
//                    $userCollection = User::where('id', $user->id)->where('workspace_id', getActiveWorkSpace())->first();
//                    if($userCollection) {
//                        // If active type is group, we don't want to highlight user items with same ID
//                        // Chatify::getContactItem likely uses $request['messenger_id'] internally if we don't pass it?
//                        // Actually, Chatify::getContactItem implementation:
//                        // public function getContactItem($messenger_id, $user)
//                        // So we can pass 0 if active_type is group
//                        $activeId = ($active_type == 'user') ? $request['messenger_id'] : 0;
//                        $contacts .= Chatify::getContactItem($activeId, $userCollection);
//                    }
//                }
//            }
//        }
//
//        if (module_is_active('Taskly')) {
//             // Get all projects assigned to current user
//             $projects = DB::table('user_projects')
//                ->join('projects', 'projects.id', '=', 'user_projects.project_id')
//                ->where('user_projects.user_id', '=', Auth::user()->id)
//                ->select('projects.*')
//                ->orderBy('projects.created_at', 'desc')
//                ->get();
//
//            \Log::debug('[Chatify] Projects found', ['count' => count($projects), 'projects' => $projects->pluck('id')->toArray()]);
//
//            if ($projects && count($projects) > 0) {
//                foreach($projects as $project) {
//                     try {
//                         // Convert to Project model for view compatibility
//                         $projectModel = Project::find($project->id);
//                         if ($projectModel) {
//                             $lastMessage = Message::where('project_id', $projectModel->id)->latest()->first();
//                             $unseenCounter = 0;
//
//                             $renderedGroup = view('Chatify::layouts.listItem', [
//                                'get' => 'users',
//                                'user' => $projectModel,
//                                'lastMessage' => $lastMessage,
//                                'unseenCounter' => $unseenCounter,
//                                'type' => 'group',
//                                'id' => ($active_type == 'group') ? $request['messenger_id'] : 0,
//                             ])->render();
//
//                             $contacts .= $renderedGroup;
//                             \Log::debug('[Chatify] Rendered group', ['project_id' => $projectModel->id, 'project_name' => $projectModel->name]);
//                         }
//                     } catch (\Exception $e) {
//                         \Log::error('[Chatify] Error rendering group', ['project_id' => $project->id, 'error' => $e->getMessage()]);
//                     }
//                }
//            }
//        }
//
//        // Get All Members from active project
//        $objUser = Auth::user();
//        $getRecords = '';
//        $members = collect(); // Initialize empty collection
//
//        $activeProject = getActiveProject();
//        if ($activeProject) {
//            // Get all users assigned to the active project
//            $userIds = DB::table('user_projects')
//                ->where('project_id', $activeProject)
//                ->pluck('user_id')
//                ->toArray();
//
//            if (count($userIds) > 0) {
//                $members = User::whereIn('id', $userIds)
//                    //->where('workspace_id', getActiveWorkSpace())
//                    ->where('id', '!=', $objUser->id)
//                    ->get();
//            }
//        }
//
//        \Log::debug('[Chatify] Members found', ['count' => $members->count(), 'active_project' => $activeProject, 'members' => $members->pluck('name', 'id')->toArray()]);
//
//        foreach ($members as $record) {
//            $getRecords .= view(
//                    'Chatify::layouts.listItem', [
//                'get' => 'all_members',
//                'type' => 'user',
//                'user' => $record,
//                    ]
//                    )->render();
//        }
//
//        // send the response
//        \Log::debug('[Chatify] getContacts response', [
//            'contacts_rendered' => !empty($contacts),
//            'contacts_length' => strlen($contacts),
//            'members_count' => $members->count(),
//            'allUsers_rendered' => !empty($getRecords),
//            'allUsers_length' => strlen($getRecords),
//        ]);
//
//        return Response::json(
//                        [
//                            'contacts' => !empty($contacts) ? $contacts : '<br><p class="message-hint"><span>' . __('Your contact list is empty') . '</span></p>',
//                            'allUsers' => !empty($getRecords) ? $getRecords : '<br><p class="message-hint"><span>' . __('Your member list is empty') . '</span></p>',
//                        ], 200
//                );
//        } catch (\Exception $e) {
//            \Log::error('[Chatify] getContacts error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
//            return Response::json(['error' => $e->getMessage()], 500);
//        }
//    }
    
    
    public function getContacts(Request $request)
    {
        try {
            $authUser = Auth::user();
            Log::debug('[Chatify] getContacts', [
                'user_id'    => $authUser->id,
                'user_email' => $authUser->email,
                'workspace'  => getActiveWorkSpace(),
            ]);

            // ✅ Collect distinct user IDs from messages
            $userIds = Message::where(function ($q) use ($authUser) {
                    $q->where('from_id', $authUser->id)
                      ->orWhere('to_id', $authUser->id);
                })
                ->selectRaw('DISTINCT from_id')
                ->pluck('from_id')
                ->merge(
                    Message::where(function ($q) use ($authUser) {
                        $q->where('from_id', $authUser->id)
                          ->orWhere('to_id', $authUser->id);
                    })
                    ->selectRaw('DISTINCT to_id')
                    ->pluck('to_id')
                )
                ->unique()
                ->reject(fn ($id) => $id == $authUser->id);

            // ✅ Fetch all contacts in one query
            $contactsUsers = User::whereIn('id', $userIds)
                ->where('workspace_id', getActiveWorkSpace())
                ->get();

            $contactsHtml = '';
            $contactsJson = [];
            $activeType   = $request['messenger_type'] ?? 'user';

            foreach ($contactsUsers as $user) {
                // Last message between auth user and contact
                $lastMessage = Message::where(function ($q) use ($authUser, $user) {
                        $q->where('from_id', $authUser->id)->where('to_id', $user->id)
                          ->orWhere('from_id', $user->id)->where('to_id', $authUser->id);
                    })
                    ->latest()
                    ->first();

                // Unseen counter
                $unseenCounter = Message::where('from_id', $user->id)
                    ->where('to_id', $authUser->id)
                    ->where('seen', 0)
                    ->count();

                // Web rendering
                $activeId = ($activeType == 'user') ? $request['messenger_id'] : 0;
                $contactsHtml .= Chatify::getContactItem($activeId, $user);

                // API/mobile JSON
                $contactsJson[] = [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'lastMessage'   => $lastMessage?->body,
                    'lastMessageAt' => $lastMessage?->created_at,
                    'unseenCount'   => $unseenCounter,
                ];
            }

            // ✅ Groups/Projects (Taskly integration)
            if (module_is_active('Taskly')) {
                $projects = DB::table('user_projects')
                    ->join('projects', 'projects.id', '=', 'user_projects.project_id')
                    ->where('user_projects.user_id', '=', $authUser->id)
                    ->select('projects.*')
                    ->orderBy('projects.created_at', 'desc')
                    ->get();

                foreach ($projects as $project) {
                    $projectModel = Project::find($project->id);
                    if ($projectModel) {
                        $lastMessage = Message::where('project_id', $projectModel->id)->latest()->first();
                        $unseenCounter = Message::where('project_id', $projectModel->id)
                            ->where('to_id', $authUser->id)
                            ->where('seen', 0)
                            ->count();

                        // Web rendering
                        $renderedGroup = view('Chatify::layouts.listItem', [
                            'get'           => 'users',
                            'user'          => $projectModel,
                            'lastMessage'   => $lastMessage,
                            'unseenCounter' => $unseenCounter,
                            'type'          => 'group',
                            'id'            => ($activeType == 'group') ? $request['messenger_id'] : 0,
                        ])->render();

                        $contactsHtml .= $renderedGroup;

                        // API/mobile JSON
                        $contactsJson[] = [
                            'id'            => $projectModel->id,
                            'name'          => $projectModel->name,
                            'type'          => 'group',
                            'lastMessage'   => $lastMessage?->body,
                            'lastMessageAt' => $lastMessage?->created_at,
                            'unseenCount'   => $unseenCounter,
                        ];
                    }
                }
            }

            // ✅ Active project members
            $membersHtml = '';
            $membersJson = [];
            $activeProject = getActiveProject();

            if ($activeProject) {
                $userIds = DB::table('user_projects')
                    ->where('project_id', $activeProject)
                    ->pluck('user_id')
                    ->toArray();

                $members = User::whereIn('id', $userIds)
                    ->where('id', '!=', $authUser->id)
                    ->get();

                foreach ($members as $member) {
                    // Web rendering
                    $membersHtml .= view('Chatify::layouts.listItem', [
                        'get'  => 'all_members',
                        'type' => 'user',
                        'user' => $member,
                    ])->render();

                    // API/mobile JSON
                    $membersJson[] = [
                        'id'    => $member->id,
                        'name'  => $member->name,
                        'email' => $member->email,
                    ];
                }
            }

            // ✅ Response
            return Response::json([
                'contacts' => $contactsHtml ?: '<p class="message-hint"><span>Your contact list is empty</span></p>',
                'contacts_json' => $contactsJson,
                'allUsers'  => $membersHtml ?: '<p class="message-hint"><span>Your member list is empty</span></p>',
                'members_json'  => $membersJson,
            ], 200);

        } catch (\Exception $e) {
            Log::error('[Chatify] getContacts error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

//    public function getContactsMobile(Request $request) {
//        // Validation
//        $validated = $request->validate([
//            'workspace_id' => 'required|integer|exists:work_spaces,id',
//            'site_id' => 'nullable|integer',
//            'user_id' => 'required|integer|exists:users,id',
//        ]);
//
//        try {
//            $workspaceId = $validated['workspace_id'];
//            $authId = $validated['user_id'];
//            $user_id = $validated['user_id'];
//
//            // Get all users who have chatted with the authenticated user
//            $users = Message::join('users', function ($join) {
//                        $join->on('ch_messages.from_id', '=', 'users.id')
//                                ->orOn('ch_messages.to_id', '=', 'users.id');
//                    })
//                    ->where(function ($query) use ($authId) {
//                        $query->where('ch_messages.from_id', $authId)
//                                ->orWhere('ch_messages.to_id', $authId);
//                    })
//                    ->orderBy('ch_messages.created_at', 'desc')
//                    ->get()
//                    ->unique('id');
//
//            $userIds = $users->pluck('id')->filter(fn($id) => $id != $authId);
//
//            $userDataList = User::whereIn('id', $userIds)
//                    ->where('workspace_id', $workspaceId)
//                    ->get();
//
//            $contacts = $userDataList->map(function ($user) {
//                        return [
//                            'id' => $user->id,
//                            'name' => $user->name,
//                            'email' => $user->email,
//                            'mobile_no' => $user->mobile_no,
//                            'avatar' => $user->avatar ?? null,
//                            'is_active' => optional($user->last_seen)->gt(now()->subMinutes(2)),
//                        ];
//                    })->values();
//
//            // Get workspace members
//            $objUser = User::findOrFail($authId);
//
//            if ($objUser->type === 'company') {
//                $members = User::where('created_by', $objUser->id)
//                        ->where('workspace_id', $workspaceId)
//                        ->get();
//            } else {
//                $members = User::where('workspace_id', $workspaceId)
//                        ->where('type', '!=', 'client')
//                        ->where('type', '!=', 'vendor')
//                        ->where(function ($query) use ($objUser) {
//                            $query->where('id', '!=', $objUser->id)
//                                    ->orWhere('id', $objUser->created_by);
//                        })
//                        ->get();
//            }
//
//            $allUsers = $members->map(function ($record) {
//                        return [
//                            'id' => $record->id,
//                            'name' => $record->name,
//                            'email' => $record->email,
//                            'mobile_no' => $record->mobile_no,
//                            'avatar' => $record->avatar ?? null,
//                            'is_active' => optional($record->last_seen)->gt(now()->subMinutes(2)),
//                        ];
//                    })->values();
//
//            return response()->json([
//                        'contacts' => $contacts,
//                        'allUsers' => $allUsers,
//                            ], 200);
//        } catch (\Exception $e) {
//            return response()->json([
//                        'error' => true,
//                        'message' => 'Something went wrong: ' . $e->getMessage(),
//                            ], 500);
//        }
//    }

    
    public function getContactsMobile(Request $request)
{
    // ✅ Validation
    $validated = $request->validate([
        'workspace_id' => 'required|integer|exists:work_spaces,id',
        'site_id'      => 'nullable|integer',
        'user_id'      => 'required|integer|exists:users,id',
    ]);

    try {
        $workspaceId = $validated['workspace_id'];
        $authId      = $validated['user_id'];

        // ✅ Collect distinct user IDs from messages
        $userIds = Message::where(function ($q) use ($authId) {
                $q->where('from_id', $authId)
                  ->orWhere('to_id', $authId);
            })
            ->selectRaw('DISTINCT from_id')
            ->pluck('from_id')
            ->merge(
                Message::where(function ($q) use ($authId) {
                    $q->where('from_id', $authId)
                      ->orWhere('to_id', $authId);
                })
                ->selectRaw('DISTINCT to_id')
                ->pluck('to_id')
            )
            ->unique()
            ->reject(fn ($id) => $id == $authId);

        // ✅ Fetch all contacts in one query
        $userDataList = User::whereIn('id', $userIds)
            ->where('workspace_id', $workspaceId)
            ->get();

        // ✅ Build contacts with unseen message counts
        $contacts = $userDataList->map(function ($user) use ($authId) {
            $lastMessage = Message::where(function ($q) use ($authId, $user) {
                    $q->where('from_id', $authId)->where('to_id', $user->id)
                      ->orWhere('from_id', $user->id)->where('to_id', $authId);
                })
                ->latest()
                ->first();

            $unseenCount = Message::where('from_id', $user->id)
                ->where('to_id', $authId)
                ->where('seen', 0)
                ->count();

            return [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'mobile_no'     => $user->mobile_no,
                'avatar'        => $user->avatar ?? null,
                'is_active'     => optional($user->last_seen)->gt(now()->subMinutes(2)),
                'lastMessage'   => $lastMessage?->body,
                'lastMessageAt' => $lastMessage?->created_at,
                'unseenCount'   => $unseenCount,
            ];
        })->values();

        // ✅ Groups/Projects logic
        $projects = DB::table('user_projects')
            ->join('projects', 'projects.id', '=', 'user_projects.project_id')
            ->where('user_projects.user_id', '=', $authId)
            ->select('projects.id', 'projects.name', 'projects.created_at')
            ->orderBy('projects.created_at', 'desc')
            ->get();

        $groups = $projects->map(function ($project) use ($authId) {
            $lastMessage = Message::where('project_id', $project->id)->latest()->first();

            $unseenCount = Message::where('project_id', $project->id)
                ->where('to_id', $authId)
                ->where('seen', 0)
                ->count();

            return [
                'id'            => $project->id,
                'name'          => $project->name,
                'type'          => 'group',
                'lastMessage'   => $lastMessage?->body,
                'lastMessageAt' => $lastMessage?->created_at,
                'unseenCount'   => $unseenCount,
            ];
        })->values();

        // ✅ Workspace members
        $objUser = User::findOrFail($authId);
        if ($objUser->isAbleTo('user chat manage')) {
            $members = User::where('created_by', $objUser->id)
                ->where('workspace_id', $workspaceId)
                ->get();
        } else {
            $members = User::where('workspace_id', $workspaceId)
                ->whereNotIn('type', ['client', 'vendor'])
                ->where(function ($query) use ($objUser) {
                    $query->where('id', '!=', $objUser->id)
                          ->orWhere('id', $objUser->created_by);
                })
                ->get();
        }

        $allUsers = $members->map(function ($record) {
            return [
                'id'        => $record->id,
                'name'      => $record->name,
                'email'     => $record->email,
                'mobile_no' => $record->mobile_no,
                'avatar'    => $record->avatar ?? null,
                'is_active' => optional($record->last_seen)->gt(now()->subMinutes(2)),
            ];
        })->values();

        // ✅ Final response
        return response()->json([
            'contacts' => $contacts,
            'groups'   => $groups,
            'allUsers' => $allUsers,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('[Chatify] getContactsMobile error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'error'   => true,
            'message' => 'Something went wrong: ' . $e->getMessage(),
        ], 500);
    }
}

    
    
    /**
     * Update user's list item data
     *
     * @param Request $request
     *
     * @return JSON response
     */
    public function updateContactItem(Request $request) {
        // Get user data
        if ($request->has('type') && $request['type'] == 'group') {
             $project = Project::find($request['user_id']);
             if ($project) {
                 $lastMessage = Message::where('project_id', $project->id)->latest()->first();
                 $unseenCounter = 0; // Group seen logic not implemented

                 $active_type = $request['messenger_type'] ?? 'user';
                 $contactItem = view('Chatify::layouts.listItem', [
                    'get' => 'users',
                    'user' => $project,
                    'lastMessage' => $lastMessage,
                    'unseenCounter' => $unseenCounter,
                    'type' => 'group',
                    'id' => ($active_type == 'group') ? $request['messenger_id'] : 0,
                 ])->render();
             } else {
                $contactItem = '';
             }
        } else {
            $userCollection = User::where('id', $request['user_id'])->first();
            // Same here for User items.
            $active_type = $request['messenger_type'] ?? 'user';
            $activeId = ($active_type == 'user') ? $request['messenger_id'] : 0;
            $contactItem = Chatify::getContactItem($activeId, $userCollection);
        }

        $messageCount = Message::where('to_id', Auth::user()->id)->where('seen', 0)->count();
        // send the response
        return Response::json(
                        [
                            'contactItem' => $contactItem,
                            'messengerCount' => $messageCount
                        ], 200
                );
    }

    /**
     * Put a user in the favorites list
     *
     * @param Request $request
     *
     * @return void
     */
    public function favorite(Request $request) {
        // check action [star/unstar]
        if (Chatify::inFavorite($request['user_id'])) {
            // UnStar
            Chatify::makeInFavorite($request['user_id'], 0);
            $status = 0;
        } else {
            // Star
            Chatify::makeInFavorite($request['user_id'], 1);
            $status = 1;
        }

        // send the response
        return Response::json(
                        [
                            'status' => @$status,
                        ], 200
                );
    }

    /**
     * Get favorites list
     *
     * @param Request $request
     *
     * @return void
     */
    public function getFavorites(Request $request) {
        $favoritesList = null;
        $favorites = Favorite::where('user_id', Auth::user()->id);
        foreach ($favorites->get() as $favorite) {
            // get user data
            $user = User::where('id', $favorite->favorite_id)->where('workspace_id', getActiveWorkSpace())->first();
            if (!empty($user)) {
                $favoritesList .= view(
                        'Chatify::layouts.favorite', [
                    'user' => $user,
                        ]
                );
            }
        }

        // send the response
        return Response::json(
                        [
                            'count' => $favorites->count(),
                            'favorites' => $favorites->count() > 0 ? $favoritesList : '<p class="message-hint"><span>' . __("Your favorite list is empty") . '</span></p>',
                        ], 200
                );
    }

    /**
     * Search in messenger
     *
     * @param Request $request
     *
     * @return void
     */
    public function search(Request $request) {
        $getRecords = null;
        $input = trim(strip_tags($request['input']));

        $user = Auth::user();

        // If still no user, return unauthorized
        if (!$user) {
            return response()->json([
                        'status' => 0,
                        'message' => 'Unauthorized user'
                            ], 401);
        }

        // Determine company/creator ID
        if ($user->isAbleTo('user chat manage')) {
            $id = $user->id;
        } else {
            $id = $user->created_by;
        }

        // Fetch matching users
        $records = User::where('created_by', $id)
                ->where('type', '!=', 'client')
                ->where('workspace_id', getActiveWorkSpace())
                ->where('name', 'LIKE', "%{$input}%")
                ->get();

        // Build HTML list items
        foreach ($records as $record) {
            $getRecords .= view(
                    'Chatify::layouts.listItem',
                    [
                        'get' => 'search_item',
                        'type' => 'user',
                        'user' => $record,
                    ]
                    )->render();
        }

        // Return response
        return response()->json([
                    'records' => $records->count() > 0 ? $getRecords : '<p class="message-hint"><span>Nothing to show.</span></p>',
                    'addData' => 'html',
                        ], 200);
    }

    /**
     * Get shared photos
     *
     * @param Request $request
     *
     * @return void
     */
    public function sharedPhotos(Request $request) {
        $shared = Chatify::getSharedPhotos($request['user_id']);
        $sharedPhotos = null;

        // shared with its template
        for ($i = 0; $i < count($shared); $i++) {
            $sharedPhotos .= view(
                    'Chatify::layouts.listItem', [
                'get' => 'sharedPhoto',
                'image' => get_file('/uploads/attachments/' . $shared[$i])
                    ]
                    )->render();
        }

        // send the response
        return Response::json(
                        [
                            'shared' => count($shared) > 0 ? $sharedPhotos : '<p class="message-hint"><span>Nothing shared yet</span></p>',
                        ], 200
                );
    }

    /**
     * Delete conversation
     *
     * @param Request $request
     *
     * @return void
     */
    public function deleteConversation(Request $request) {
        // delete
        $delete = Chatify::deleteConversation($request['id']);

        // send the response
        return Response::json(
                        [
                            'deleted' => $delete ? 1 : 0,
                        ], 200
                );
    }

    public function updateSettings(Request $request) {
        $msg = null;
        $error = $success = 0;

        // dark mode
        if ($request['dark_mode']) {
            $request['dark_mode'] == "dark" ? User::where('id', Auth::user()->id)->update(['dark_mode' => 1])  // Make Dark
                            : User::where('id', Auth::user()->id)->update(['dark_mode' => 0]); // Make Light
        }

        // If messenger color selected
        if ($request['messengerColor']) {

            $messenger_color = explode('-', trim(strip_tags($request['messengerColor'])));
            $messenger_color = Chatify::getMessengerColors()[$messenger_color[1]];
            User::where('id', Auth::user()->id)->update(['messenger_color' => $messenger_color]);
        }
        // if there is a [file]
        if ($request->hasFile('avatar')) {
            // allowed extensions
            $allowed_images = Chatify::getAllowedImages();

            $file = $request->file('avatar');
            // if size less than 150MB
            if ($file->getSize() < 150000000) {
                if (in_array($file->getClientOriginalExtension(), $allowed_images)) {
                    // delete the older one
                    if (Auth::user()->avatar != config('chatify.user_avatar.default')) {
                        $path = storage_path(config('chatify.user_avatar.folder') . '/' . Auth::user()->avatar);
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                    // upload
                    $avatar = Str::uuid() . "." . $file->getClientOriginalExtension();
                    $update = User::where('id', Auth::user()->id)->update(['avatar' => $avatar]);
                    // $file->storeAs("public/" . config('chatify.user_avatar.folder'), $avatar);
                    // $file->storeAs(config('chatify.user_avatar.folder'), $avatar);
                    $file->storeAs('avatar', $avatar);
                    $success = $update ? 1 : 0;
                } else {
                    $msg = "File extension not allowed!";
                    $error = 1;
                }
            } else {
                $msg = "File extension not allowed!";
                $error = 1;
            }
        }

        // send the response
        return Response::json(
                        [
                            'status' => $success ? 1 : 0,
                            'error' => $error ? 1 : 0,
                            'message' => $error ? $msg : 0,
                        ], 200
                );
    }

    /**
     * Set user's active status
     *
     * @param Request $request
     *
     * @return void
     */
    public function setActiveStatus(Request $request) {
        $update = $request['status'] > 0 ? User::where('id', $request['user_id'])->update(['active_status' => 1]) : User::where('id', $request['user_id'])->update(['active_status' => 0]);

        // send the response
        return Response::json(
                        [
                            'status' => $update,
                        ], 200
                );
    }
}
