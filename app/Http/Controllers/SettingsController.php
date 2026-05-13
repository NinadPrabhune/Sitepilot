<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\EmailTemplate;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('settings.index');
    }
    public function getSettingSection($module, $method = 'index')
    {
        $folder = 'Company';
        if (auth()->user()->type == 'super admin') {
            $settings = getAdminAllSetting();

            $folder = 'SuperAdmin';
        } else {
            $settings = getCompanyAllSetting();
        }
        
        if (!empty($module) && $module != 'Base') {
            $controllerClass = "Workdo\\" . $module . "\\Http\\Controllers\\" . $folder . "\\SettingsController";
           
            if (class_exists($controllerClass)) {
                $controller = \App::make($controllerClass);
                if (method_exists($controller, $method)) {
                    $output =  $controller->{$method}($settings);
                   
                    $return = [
                        'status' => 200,
                        'html' => $output->toHtml(),
                    ];
                    return  response()->json($return);
                }
            }
        } else {
            $method = 'index';
            $html = '';
            $controllerClass = "App\\Http\\Controllers\\" . $folder . "\\SettingsController";
            if (class_exists($controllerClass)) {
                $controller = \App::make($controllerClass);
                if (method_exists($controller, $method)) {
                    $output =  $controller->{$method}($settings);
                    if ($output !== null) {
                        $html .= $output->toHtml();
                    }
                }
            }
            $method = 'emailSettingGet';
            $controllerClass = "App\\Http\\Controllers\\SettingsController";

            if (class_exists($controllerClass)) {
                $controller = \App::make($controllerClass);
                if (method_exists($controller, $method)) {
                    $output =  $controller->{$method}($settings);
                    if ($output !== null) {
                        $html .= $output->toHtml();
                    }
                }
            }

            $method = 'settingGet';
            $controllerClass = "App\\Http\\Controllers\\BanktransferController";

            if (class_exists($controllerClass)) {
                $controller = \App::make($controllerClass);
                if (method_exists($controller, $method)) {
                    $output =  $controller->{$method}($settings);
                    if ($output !== null) {
                        $html .= $output->toHtml();
                    }
                }
            }

            $return = [
                'status' => 200,
                'html' => $html,
            ];
            return response()->json($return);
        }
    }


    public function emailSettingGet($settings)
    {
        $activatedModules = ActivatedModule();
        $email_notification_modules = Notification::where('type','mail')->whereIn('module', $activatedModules)->orwhere('module','General')->pluck('module')->toArray();

        $email_notification_modules = array_unique($email_notification_modules);

        $email_notify = Notification::where('type', 'mail')->whereIn('module', $email_notification_modules)->get(['module', 'action', 'permissions']);
        $email_setting = EmailTemplate::$email_settings;
        return view('email.index', compact('settings', 'email_notification_modules', 'email_notify','email_setting'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()->back();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        return redirect()->back();
    }

    public function getfields(Request $request)
    {
        if (auth()->user()->type == 'super admin') {
            $settings = getAdminAllSetting();

            $folder = 'SuperAdmin';
        } else {
            $settings = getCompanyAllSetting();
        }
       $email_setting = $request->emailsetting;

       $returnHTML = view('email.input', compact('email_setting','settings'))->render();
       $response = [
           'is_success' => true,
           'message' => '',
           'html' => $returnHTML,
       ];

       return response()->json($response);
    }
    public function mailStore(Request $request)
    {

        if (Auth::user()->isAbleTo('setting manage')) {

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'mail_driver' => 'required|string|max:255',
                        'mail_host' => 'required|string|max:255',
                        'mail_port' => 'required|string|max:255',
                        'mail_username' => 'required|string|max:255',
                        'mail_password' => 'required|string|max:255',
                        'mail_encryption' => 'required|string|max:255',
                        'mail_from_address' => 'required|string|max:255',
                        'mail_from_name' => 'required|string|max:255',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();
                    return redirect()->back()->with('error', $messages->first());
                }

                $post = [
                    'email_setting' => $request->email_setting,
                    'mail_driver' => $request->mail_driver,
                    'mail_host' => $request->mail_host,
                    'mail_port' => $request->mail_port,
                    'mail_username' => $request->mail_username,
                    'mail_password' => $request->mail_password,
                    'mail_encryption' => $request->mail_encryption,
                    'mail_from_address' => $request->mail_from_address,
                    'mail_from_name' => $request->mail_from_name,

                ];

            unset($post['_token'], $post['_method'], $post['mail_noti']);
            foreach ($post as $key => $value) {
                // Define the data to be updated or inserted
                $data = [
                    'key' => $key,
                    'workspace' => getActiveWorkSpace(),
                    'created_by' => creatorId(),
                ];
                // Check if the record exists, and update or insert accordingly
                Setting::updateOrInsert($data, ['value' => $value]);
            }
            // Settings Cache forget
            AdminSettingCacheForget();
            comapnySettingCacheForget();
            return redirect()->back()->with('success', 'Mail Setting save sucessfully.');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function testMail(Request $request)
    {
        $data                    = [];
        $data['mail_driver']     = $request->mail_driver;
        $data['mail_host']       = $request->mail_host;
        $data['mail_port']       = $request->mail_port;
        $data['mail_username']   = $request->mail_username;
        $data['mail_password']   = $request->mail_password;
        $data['mail_from_address']   = $request->mail_from_address;
        $data['mail_encryption'] = $request->mail_encryption;
        $data['route'] = route('test.mail.send');
        return view('settings.test_mail', compact('data'));
    }

    public function sendTestMail(Request $request)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'email' => 'required|email',
                               'mail_driver' => 'required',
                               'mail_host' => 'required',
                               'mail_port' => 'required',
                               'mail_username' => 'required',
                               'mail_password' => 'required',
                               'mail_from_address' => 'required',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return error_res($messages->first());
        }
        try
        {
            config(
                [
                    'mail.driver' => $request->mail_driver,
                    'mail.host' => $request->mail_host,
                    'mail.port' => $request->mail_port,
                    'mail.encryption' => $request->mail_encryption,
                    'mail.username' => $request->mail_username,
                    'mail.password' => $request->mail_password,
                    'mail.from.address' => $request->mail_from_address,
                    'mail.from.name' => config('name'),
                ]
            );

             Mail::to($request->email)->send(new TestMail());

            return success_res(__('Email send Successfully'));

        }
        catch(\Exception $e)
        {
            return error_res($e->getMessage());
        }
    }

    public function mailNotificationStore(Request $request)
    {
        // mail notification save
        if ($request->has('mail_noti')) {
            foreach ($request->mail_noti as $key => $notification) {
                // Define the data to be updated or inserted
                $data = [
                    'key' => $key,
                    'workspace' => getActiveWorkSpace(),
                    'created_by' => creatorId(),
                ];

                // Check if the record exists, and update or insert accordingly
                Setting::updateOrInsert($data, ['value' => $notification]);
            }
        }
        // Settings Cache forget
        AdminSettingCacheForget();
        comapnySettingCacheForget();
        return redirect()->back()->with('success', 'Mail Notification Setting save sucessfully.');
    }

    /**
     * Numbering Configuration Index
     */
    public function numberingIndex()
    {
        \Log::info('Numbering Index accessed', ['user_id' => auth()->id()]);
        
        $workspaces = \App\Models\WorkSpace::pluck('name', 'id');
        $sites = \Workdo\Taskly\Entities\Project::pluck('name', 'id');
        
        return view('settings.numbering.index', compact('workspaces', 'sites'));
    }

    /**
     * Update Numbering Configuration
     */
    public function updateNumberingConfig(Request $request)
    {
        
        $request->validate([
            'module' => 'required|string',
            'scope_type' => 'required|in:site,workspace',
            'scope_id' => 'nullable|integer',
            'prefix' => 'required|string|max:20',
            'starting_number' => 'required|integer|min:1',
            'padding_length' => 'required|integer|min:1|max:10',
        ]);
        
        // CRITICAL: Server-side scope validation
        if ($request->module === 'po' && $request->scope_type !== 'workspace') {
            return back()->with('error', 'PO must use workspace scope');
        }
        
        $tableMap = [
            'po' => 'purchase_orders',
            'indent' => 'indents',
            'grn' => 'grns',
            'invoice' => 'purchase_invoices',
            'payment' => 'payments_module',
        ];
        
        $columnMap = [
            'po' => 'po_number',
            'indent' => 'indent_number',
            'grn' => 'grn_number',
            'invoice' => 'invoice_number',
            'payment' => 'payment_number',
        ];
        
        $scopeColumn = $request->scope_type === 'workspace' ? 'workspace_id' : 'site_id';
        $table = $tableMap[$request->module] ?? null;
        $column = $columnMap[$request->module] ?? null;
        
        // CRITICAL: Validate against existing data
        if ($table && $request->scope_id) {
            $exists = \DB::table($table)
                ->where($scopeColumn, $request->scope_id)
                ->exists();
            
            if ($exists && $request->starting_number == 1) {
                return back()->with('error', 'Cannot reset starting number to 1 when records already exist for this scope.');
            }
            
            // CRITICAL: Prevent prefix duplication
            if ($column) {
                $prefixExists = \DB::table($table)
                    ->where($scopeColumn, $request->scope_id)
                    ->where($column, 'like', strtoupper($request->prefix) . '%')
                    ->exists();
                
                if ($prefixExists) {
                    return back()->with('error', 'This prefix already exists in records. Choose a different prefix or continue sequence.');
                }
                
                // CRITICAL: Warn if normalized prefix is similar (PO vs PO-)
                $oldConfig = \DB::table('numbering_configs')
                    ->where('module', $request->module)
                    ->where('scope_type', $request->scope_type)
                    ->where('scope_id', $request->scope_id)
                    ->first();
                
                if ($oldConfig) {
                    $oldNormalized = $this->normalizePrefix($oldConfig->prefix);
                    $newNormalized = $this->normalizePrefix($request->prefix);
                    
                    if ($oldNormalized === $newNormalized) {
                        return back()->with('error', 'New prefix is too similar to existing prefix. This will create visual confusion (e.g., PO00001 vs PO-00001). Choose a distinctly different prefix.');
                    }
                }
            }
        }
        
        // Get old config for audit
        $oldConfig = \DB::table('numbering_configs')
            ->where('module', $request->module)
            ->where('scope_type', $request->scope_type)
            ->where('scope_id', $request->scope_id)
            ->first();
        
        // CRITICAL: Wrap in transaction to prevent race conditions
        \DB::transaction(function () use ($request, $oldConfig) {
            // Lock the config row
            \DB::table('numbering_configs')
                ->where('module', $request->module)
                ->where('scope_type', $request->scope_type)
                ->where('scope_id', $request->scope_id)
                ->lockForUpdate();
            
            // Update or insert config
            \DB::table('numbering_configs')->updateOrInsert(
                [
                    'module' => $request->module,
                    'scope_type' => $request->scope_type,
                    'scope_id' => $request->scope_id,
                ],
                [
                    'prefix' => strtoupper($request->prefix),
                    'starting_number' => $request->starting_number,
                    'padding_length' => $request->padding_length,
                    'updated_at' => now(),
                ]
            );
            
            // Log audit trail with full snapshot
            \DB::table('numbering_config_logs')->insert([
                'module' => $request->module,
                'scope_type' => $request->scope_type,
                'scope_id' => $request->scope_id,
                'action_type' => $oldConfig ? 'update' : 'create',
                'is_rollback' => false,
                'old_value' => json_encode($oldConfig),
                'new_value' => json_encode([
                    'prefix' => strtoupper($request->prefix),
                    'starting_number' => $request->starting_number,
                    'padding_length' => $request->padding_length,
                ]),
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });
        
        // CRITICAL: Cache invalidation
        app(\App\Services\NumberGeneratorService::class)->invalidateConfigCache(
            $request->module,
            $request->scope_type,
            $request->scope_id
        );
        
        return back()->with('success', 'Numbering configuration updated successfully');
    }

    /**
     * Get Effective Configuration
     */
    public function getEffectiveConfig(Request $request)
    {
        $module = $request->query('module');
        $scopeType = $request->query('scope_type');
        $scopeId = $request->query('scope_id');
        
        $config = \DB::table('numbering_configs')
            ->where('module', $module)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();
        
        if (!$config) {
            $config = \DB::table('numbering_configs')
                ->where('module', $module)
                ->where('scope_type', $scopeType)
                ->whereNull('scope_id')
                ->first();
        }
        
        return response()->json($config);
    }

    /**
     * Test Next Number (Dry Run)
     */
    public function testNextNumber(Request $request)
    {
        $module = $request->query('module');
        $scopeId = $request->query('scope_id');
        
        if (!$scopeId) {
            return response()->json(['error' => 'scope_id required'], 400);
        }
        
        $nextNumber = app(\App\Services\NumberGeneratorService::class)->generate($module, $scopeId);
        
        // Get last issued number for debugging
        $lastNumber = $this->getLastIssuedNumber($module, $scopeId);
        
        return response()->json([
            'next_number' => $nextNumber,
            'last_number' => $lastNumber,
            'is_preview' => true,
        ]);
    }

    private function getLastIssuedNumber(string $module, int $scopeId): ?string
    {
        $tableMap = [
            'po' => 'purchase_orders',
            'indent' => 'indents',
            'grn' => 'grns',
            'invoice' => 'purchase_invoices',
            'payment' => 'payments_module',
        ];
        
        $columnMap = [
            'po' => 'po_number',
            'indent' => 'indent_number',
            'grn' => 'grn_number',
            'invoice' => 'invoice_number',
            'payment' => 'payment_number',
        ];
        
        $scopeColumn = config("numbering.scopes.{$module}") === 'workspace' ? 'workspace_id' : 'site_id';
        
        $table = $tableMap[$module] ?? null;
        $column = $columnMap[$module] ?? null;
        
        if (!$table || !$column) {
            return null;
        }
        
        return \DB::table($table)
            ->where($scopeColumn, $scopeId)
            ->orderBy('id', 'desc')
            ->value($column);
    }

    /**
     * Audit Log Viewer with Filters
     */
    public function auditLog(Request $request)
    {
        $query = \DB::table('numbering_config_logs')
            ->leftJoin('users', 'numbering_config_logs.changed_by', '=', 'users.id')
            ->select('numbering_config_logs.*', 'users.name as changed_by_name');
        
        // Apply filters
        if ($request->module) {
            $query->where('numbering_config_logs.module', $request->module);
        }
        
        if ($request->scope_type) {
            $query->where('numbering_config_logs.scope_type', $request->scope_type);
        }
        
        if ($request->date_from) {
            $query->where('numbering_config_logs.created_at', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->where('numbering_config_logs.created_at', '<=', $request->date_to);
        }
        
        if ($request->user_id) {
            $query->where('numbering_config_logs.changed_by', $request->user_id);
        }
        
        $logs = $query->orderBy('numbering_config_logs.created_at', 'desc')
            ->paginate(50);
        
        $users = \DB::table('users')->pluck('name', 'id');
        
        return view('settings.numbering.audit', compact('logs', 'users'));
    }

    /**
     * Force Reset Sequence
     */
    public function forceResetSequence(Request $request)
    {
        $request->validate([
            'module' => 'required|string',
            'scope_id' => 'required|integer',
            'reset_to' => 'required|integer|min:1',
        ]);
        
        // Update starting_number in config
        \DB::table('numbering_configs')
            ->where('module', $request->module)
            ->where('scope_id', $request->scope_id)
            ->update(['starting_number' => $request->reset_to]);
        
        // Log audit
        \DB::table('numbering_config_logs')->insert([
            'module' => $request->module,
            'scope_type' => config("numbering.scopes.{$request->module}"),
            'scope_id' => $request->scope_id,
            'action_type' => 'update',
            'is_rollback' => false,
            'old_value' => json_encode(['reset' => 'forced']),
            'new_value' => json_encode(['starting_number' => $request->reset_to]),
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);
        
        // Invalidate cache
        app(\App\Services\NumberGeneratorService::class)->invalidateConfigCache(
            $request->module,
            config("numbering.scopes.{$request->module}"),
            $request->scope_id
        );
        
        return response()->json(['success' => true]);
    }

    /**
     * Normalize prefix for comparison
     */
    private function normalizePrefix(string $prefix): string
    {
        return rtrim($prefix, '-');
    }

    /**
     * Get Numbering Configurations API
     */
    public function getNumberingConfigsApi()
    {
        $configs = \DB::table('numbering_configs')
            ->orderBy('module')
            ->orderBy('scope_type')
            ->orderBy('scope_id')
            ->get();
        
        return response()->json($configs);
    }
}
