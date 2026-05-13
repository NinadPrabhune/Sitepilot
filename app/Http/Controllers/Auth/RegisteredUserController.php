<?php

namespace App\Http\Controllers\Auth;

use App\Events\DefaultData;
use App\Events\GivePermissionToRole;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSpace;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Workdo\GoogleCaptcha\Events\VerifyReCaptchaToken;
use Illuminate\Support\Facades\DB;




class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public $admin_settings;

    public function setting(){
        $this->admin_settings = getAdminAllSetting();

    }
    public function __construct()
    {
        $this->setting();

        if(!file_exists(storage_path() . "/installed"))
        {
            header('location:install');
            die;
        }
        if(module_is_active('GoogleCaptcha') && (isset($this->admin_settings['google_recaptcha_is_on']) ? $this->admin_settings['google_recaptcha_is_on'] : 'off') == 'on' )
        {
            config(['captcha.secret' => isset($this->admin_settings['google_recaptcha_secret']) ? $this->admin_settings['google_recaptcha_secret'] : '']);
            config(['captcha.sitekey' => isset($this->admin_settings['google_recaptcha_key']) ? $this->admin_settings['google_recaptcha_key'] : '']);
        }
        // $this->middleware('guest')->except('logout');
    }
    public function create(Request $request,$lang = '')
    {
        if (empty( $this->admin_settings['signup']) ||  (isset($this->admin_settings['signup']) ? $this->admin_settings['signup'] : 'off') == "on")
        {
            if($lang == '')
            {
                $lang = getActiveLanguage();
            }
            else
            {
                $lang = array_key_exists($lang, languages()) ? $lang : 'en';
            }
            \App::setLocale($lang);

            $ref = $request->ref_id ?? 0 ;
            $refCode = User::where('referral_code' , '=', $ref)->first();
            if(isset($refCode) && $refCode->referral_code != $ref)
            {
                return redirect()->route('register');
            }

            return view('auth.register',compact('lang','ref'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'workspace' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'password_confirmation' => 'required'

        ]);
        if (module_is_active('GoogleCaptcha') && admin_setting('google_recaptcha_is_on') == 'on') {
            if (admin_setting('google_recaptcha_version') == 'v2-checkbox') {
                $request->validate([
                    'g-recaptcha-response' => 'required|captcha',
                ]);
            } else {
                $result = event(new VerifyReCaptchaToken($request));
                if (!isset($result[0]['status']) || $result[0]['status'] != true) {
                    $key = 'g-recaptcha-response';
                    $request->merge([$key => null]);
                    $request->validate([
                        'g-recaptcha-response' => 'required|captcha',
                    ]);
                }
            }
        }

        do {
            $code = rand(100000, 999999);
        } while (User::where('referral_code', $code)->exists());
        $user = User::create([
            'name' => $this->toCamelCase($request->name),
            'email' => $request->email,
            'referral_code' => $code,
            'used_referral_code'=> !empty($request->ref_code)?$request->ref_code:'0',
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        $role_r = Role::where('name','company')->first();
        if(!empty($user))
        {
            $user->addRole($role_r);
            // WorkSpace slug create on WorkSpace Model
            $workspace = new WorkSpace();
            $workspace->name = $request->workspace;
            $workspace->created_by = $user->id;
            $workspace->save();

            $user_work = User::find($user->id);
            $user_work->active_workspace = $workspace->id;
            $user_work->workspace_id = $workspace->id;
            $user_work->save();
            
            // ? Insert default document types for this workspace
                $defaultDocs = [
                    'Employee Provident Fund Upload',
                    'ESIC CARD Upload',
                    'Bank Details Upload',
                    'Aadhar Card Upload',
                ];

                foreach ($defaultDocs as $docName) {
                    \DB::table('document_types')->insert([
                        'name'        => $docName,
                        'is_required' => 1,
                        'workspace'   => $workspace->id,   // link to the newly created workspace
                        'site_id'     => null,
                        'created_by'  => $user->id,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

          

                    // ? Insert default leave types for this workspace
                    $defaultLeaveTypes = [
                       
                        ['title' => 'Casual', 'days' => 10],
                    ];

                    foreach ($defaultLeaveTypes as $leave) {
                        \DB::table('leave_types')->insert([
                            'title'      => $leave['title'],
                            'days'       => $leave['days'],
                            'created_by' => $user->id,
                            'workspace'  => $workspace->id,   // link to new workspace
                            'site_id'    => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    
                    

                    // ? Insert default branches for this workspace and capture IDs
                    $branchIds = [];
                    $defaultBranches = [
                        'Head Office (Corporate)',
                        'Project Site Office',
                    ];

                    foreach ($defaultBranches as $branchName) {
                        $branchIds[$branchName] = \DB::table('branches')->insertGetId([
                            'name'       => $branchName,
                            'workspace'  => $workspace->id,
                            'created_by' => $user->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // ? Insert default departments for this workspace and capture IDs
                    $departmentIds = [];
                    $defaultDepartments = [
                        ['branch' => 'Head Office (Corporate)', 'name' => 'Administration & HR'],
                        ['branch' => 'Head Office (Corporate)', 'name' => 'Finance & Accounts'],
                        ['branch' => 'Project Site Office',     'name' => 'Project Management'],
                    ];

                    foreach ($defaultDepartments as $dept) {
                        $branchId = $branchIds[$dept['branch']];
                        $departmentIds[$dept['name']] = \DB::table('departments')->insertGetId([
                            'branch_id'  => $branchId,
                            'name'       => $dept['name'],
                            'workspace'  => $workspace->id,
                            'created_by' => $user->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // ? Insert default designations for this workspace using relational IDs
                    $defaultDesignations = [
                        ['branch' => 'Head Office (Corporate)', 'department' => 'Administration & HR', 'name' => 'Staff Manager'],
                        ['branch' => 'Head Office (Corporate)', 'department' => 'Finance & Accounts',  'name' => 'Account Manager'],
                        ['branch' => 'Project Site Office',     'department' => 'Project Management',  'name' => 'Project Manager'],
                    ];

                    foreach ($defaultDesignations as $designation) {
                        $branchId     = $branchIds[$designation['branch']];
                        $departmentId = $departmentIds[$designation['department']];

                        \DB::table('designations')->insert([
                            'branch_id'    => $branchId,
                            'department_id'=> $departmentId,
                            'name'         => $designation['name'],
                            'workspace'    => $workspace->id,
                            'created_by'   => $user->id,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                    }
            

            User::CompanySetting($user->id);

            $user->MakeRole();

            if(!empty($request->type) && $request->type != "pricing" && $request->type != "plan" && $request->type != "trial")
            {
                $plan = Plan::where('is_free_plan',1)->first();
                if($plan)
                {
                    $user->assignPlan($plan->id,'Month',$plan->modules,0,$user->id);
                }
            }

            if($request->type == "trial")
            {
                try {
                    $id       = \Crypt::decrypt($request->plan);
                } catch (\Throwable $th) {
                    return redirect()->back()->with('error', __('Plan Not Found.'));
                }
                $plan = Plan::find($id);

                $user->assignPlan($plan->id, 'Trial', $plan->modules, 0, $user->id);
                $user->is_trial_done = 1;
                $user->save();
            }

            if ( admin_setting('email_verification') == 'on')
            {
                try
                {
                    $uArr = [
                        'email'=> $request->email,
                        'password'=> $request->password,
                        'company_name'=>$request->name,
                    ];

                    $admin_user = User::where('type','super admin')->first();
                    SetConfigEmail(!empty($admin_user->id) ? $admin_user->id : null);
                    $resp = EmailTemplate::sendEmailTemplate('New User', [$user->email], $uArr,$admin_user->id);
                    $user->sendEmailVerificationNotification();
                    // event(new Registered($user));
                }
                catch(\Exception $e)
                {
                    $smtp_error = __('E-Mail has been not sent due to SMTP configuration');
                }
            }
            else
            {
                $user_work = User::find($user->id);
                $user_work->email_verified_at = date('Y-m-d h:i:s');
                $user_work->save();
            }

        }
        if($request->type == "plan")
        {

            return redirect()->route('plan.buy',$request->plan);
        }
        elseif($request->type == "pricing")
        {
            return redirect('plans');
        }

        return redirect(RouteServiceProvider::HOME);
    }

    /**
     * Convert string to camel case (proper capitalization)
     * 
     * @param string $string
     * @return string
     */
    private function toCamelCase($string)
    {
        // Convert to title case (first letter of each word uppercase, rest lowercase)
        return ucwords(strtolower(trim($string)));
    }
}
