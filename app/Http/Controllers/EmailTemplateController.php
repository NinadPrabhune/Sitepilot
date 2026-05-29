<?php

namespace App\Http\Controllers;

use App\DataTables\EmailTemplateDataTable;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailTemplateController extends Controller
{

    /**
     * List all email templates.
     *
     * @authenticated
     * @response view="email_templates.index"
     */
    public function index(EmailTemplateDataTable $dataTable)
    {
        if(Auth::user()->isAbleTo('email template manage'))
        {
            return $dataTable->render('email_templates.index');
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new email template.
     *
     * @authenticated
     * @response view="email_templates.show"
     */
    public function create()
    {
        //
        return view('email_templates.show');
    }

    /**
     * Store a newly created email template.
     *
     * @authenticated
     * @bodyParam name string required Template name. Example: welcome-email
     * @bodyParam content string required Template content. Example: <h1>Welcome</h1>
     * @response view="null"
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified email template with language variant.
     *
     * @authenticated
     * @urlParam id int required Template ID. Example: 1
     * @urlParam lang string required Language code. Example: en
     * @response view="email_templates.show"
     */
    public function show($id ,$lang = 'en')
    {
        if(Auth::user()->isAbleTo('email template manage'))
        {
            $languages         = languages();
            $emailTemplate     = EmailTemplate::where('id', '=', $id)->first();
            $currEmailTempLang = EmailTemplateLang::where('parent_id', '=', $id)->where('lang', $lang)->first();
            if(!isset($currEmailTempLang) || empty($currEmailTempLang))
            {
                $currEmailTempLang       = EmailTemplateLang::where('parent_id', '=', $id)->where('lang', 'en')->first();
                $currEmailTempLang       = EmailTemplateLang::where('parent_id', '=', $id)->where('lang', 'en')->first();
                if(!empty($currEmailTempLang)){
                    $currEmailTempLang->lang = $lang;
                }else{
                    return redirect()->back()->with('error', __('Template Not Found.'));
                }
            }

            return view('email_templates.show', compact('emailTemplate', 'languages', 'currEmailTempLang'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for editing the specified email template.
     *
     * @authenticated
     * @urlParam id int required Template ID. Example: 1
     * @response view="null"
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified email template.
     *
     * @authenticated
     * @urlParam id int required Template ID. Example: 1
     * @bodyParam from string required Sender email address. Example: no-reply@example.com
     * @response view="email_templates.index" (redirect back)
     */
    public function update(Request $request, $id)
    {
        $emailTemplate = EmailTemplate::find($id);
        $emailTemplate->from = $request->from;
        $emailTemplate->save();
        return redirect()->back()->with('success', __('The email template details are updated successfully'));
    }

    /**
     * Remove the specified email template.
     *
     * @authenticated
     * @urlParam id int required Template ID. Example: 1
     * @response view="null"
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Store email template content for a specific language.
     *
     * @authenticated
     * @urlParam id int required Template ID. Example: 1
     * @bodyParam subject string required Email subject. Example: Welcome to SitePilot
     * @bodyParam content string required Email content. Example: <p>Dear User, welcome!</p>
     * @bodyParam lang string required Language code. Example: en
     * @response view="email_templates.show" (redirect)
     */
    public function storeEmailLang(Request $request, $id)
    {
        $validator = \Validator::make(
            $request->all(), [
                                'subject' => 'required',
                                'content' => 'required',
                            ]
        );

        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }


        $emailLangTemplate = EmailTemplateLang::where('parent_id', '=', $id)->where('lang', '=', $request->lang)->first();

        // if record not found then create new record else update it.
        if(empty($emailLangTemplate))
        {
            $emailLangTemplate            = new EmailTemplateLang();
            $emailLangTemplate->parent_id = $id;
            $emailLangTemplate->lang      = $request['lang'];
            $emailLangTemplate->subject   = $request['subject'];
            $emailLangTemplate->content   = $request['content'];
            $emailLangTemplate->save();
        }
        else
        {
            $emailLangTemplate->subject = $request['subject'];
            $emailLangTemplate->content = $request['content'];
            $emailLangTemplate->save();
        }

        return redirect()->route(
            'manage.email.language', [
                                        $id,
                                        $request->lang,
                                    ]
        )->with('success', __('The email template details are updated successfully'));

    }
}
