<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\HelpdeskConversion;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpdeskConversionController extends Controller
{
    /**
     * List all helpdesk conversions.
     *
     * @authenticated
     * @response view="null"
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new conversion.
     *
     * @authenticated
     * @response view="null"
     */
    public function create()
    {
        //
    }

    /**
     * Store a new reply/conversion on a helpdesk ticket.
     *
     * @authenticated
     * @urlParam ticket_id int required Ticket ID. Example: 5
     * @bodyParam reply_description string required Reply content. Example: Issue has been resolved.
     * @bodyParam reply_attachments file nullable Attachment files.
     * @response view="helpdesk_ticket.show" (redirect back)
     */
    public function store(Request $request,$ticket_id)
    {
        $ticket = HelpdeskTicket::find($ticket_id);

        if(Auth::check())
        {
            $user        = User::where('id', Auth::user()->id)->first();
        }
        else
        {
            $user        = User::where('id', $ticket->created_by)->first();
        }

        if ($ticket) {
            $validation = ['reply_description' => ['required']];
            $validator = \Validator::make(
                $request->all(),
                $validation
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->withInput()->with('error', $messages->first());
            }
            $post = [];
            $post['sender'] = ($user) ? $user->id : 'user';
            $post['ticket_id'] = $ticket->id;
            $post['description'] = $request->reply_description;
            $data = [];
            if ($request->hasfile('reply_attachments')) {
                foreach ($request->file('reply_attachments') as $file) {
                    $name = $file->getClientOriginalName();
                    $data[] = [
                        'name' => $name,
                        'path' => 'uploads/helpdesk/' . $ticket->ticket_id . '/' . $name
                    ];
                   $path = multi_upload_file($file, 'reply_attachments', $name, 'helpdesk/' . $ticket->ticket_id);
                   if($path['flag'] == '0')
                   {
                        return redirect()->back()->with('error',$path['msg']);
                   }

                }
            }
            $post['attachments'] = json_encode($data);
            HelpdeskConversion::create($post);
            $user        = User::where('id', $ticket->created_by)->first();
            if(!empty(admin_setting('New Helpdesk Ticket Reply')) && admin_setting('New Helpdesk Ticket Reply')  == true)
                {
                    $uArr = [
                        'ticket_name' => $ticket->name,
                        'ticket_id' => $ticket->ticket_id,
                        'email' => $ticket->email,
                        'reply_description' => $request->reply_description,
                        'company_name' => company_setting('company_name',$ticket->user_id)
                    ];
                    try
                    {
                        if(Auth::check()){
                            if(Auth::user()->type == 'super admin')
                            {
                                $error_msg = EmailTemplate::sendEmailTemplate('New Helpdesk Ticket Reply', [$ticket->email], $uArr,$user->id);
                            }else
                            {
                                if($user->type != 'super admin'){
                                    $user        = User::where('type', 'super admin')->first();
                                }
                                $error_msg = EmailTemplate::sendEmailTemplate('New Helpdesk Ticket Reply', [$user->email], $uArr,$user->id);
                            }
                        }else
                        {
                            if($user->type != 'super admin'){
                                $user        = User::where('type', 'super admin')->first();
                            }
                            $error_msg = EmailTemplate::sendEmailTemplate('New Helpdesk Ticket Reply', [$user->email], $uArr,$user->id);
                        }
                    }
                    catch(\Exception $e)
                    {
                        $resp['error'] = $e->getMessage();
                    }
                }
            // $error_msg = EmailTemplate::sendEmailTemplate('New Helpdesk Ticket Reply', [$ticket->email], $uArr);
            return redirect()->back()->with('success', __('Reply added successfully') . ((isset($error_msg['error'])) ? '<br> <span class="text-danger">' . $error_msg['error'] . '</span>' : ''));
        } else {
            return view('403');
        }
    }

    /**
     * Display a specific conversion.
     *
     * @authenticated
     * @urlParam helpdeskConversion int required Conversion ID. Example: 1
     * @response view="null"
     */
    public function show(HelpdeskConversion $helpdeskConversion)
    {
        //
    }

    /**
     * Show the form for editing a conversion.
     *
     * @authenticated
     * @urlParam helpdeskConversion int required Conversion ID. Example: 1
     * @response view="null"
     */
    public function edit(HelpdeskConversion $helpdeskConversion)
    {
        //
    }

    /**
     * Update a conversion.
     *
     * @authenticated
     * @urlParam helpdeskConversion int required Conversion ID. Example: 1
     * @bodyParam description string required Conversion description. Example: Updated reply.
     * @response view="null"
     */
    public function update(Request $request, HelpdeskConversion $helpdeskConversion)
    {
        //
    }

    /**
     * Delete a conversion.
     *
     * @authenticated
     * @urlParam helpdeskConversion int required Conversion ID. Example: 1
     * @response view="null"
     */
    public function destroy(HelpdeskConversion $helpdeskConversion)
    {
        //
    }
}
