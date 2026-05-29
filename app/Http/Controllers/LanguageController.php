<?php

namespace App\Http\Controllers;

use App\Models\AddOn;
use App\Models\EmailTemplateLang;
use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use App\Facades\ModuleFacade as Module;;
use Illuminate\Filesystem\Filesystem;
use ZipArchive;

class LanguageController extends Controller
{
    /**
     * Display language management page
     *
     * @authenticated
     * @urlParam lang string required Language code. Example: en
     * @urlParam module string required Module name. Example: general
     * @response view="lang.index"
     */
    public function index($lang = 'en',$module='general')
    {
        if($lang){
            $user = \Auth::user();
            if($user->isAbleTo('language manage') )
            {
                if($module == 'general' ){
                    $dir = base_path() . '/resources/lang/' . $lang;
                }else{
                    $module = AddOn::where('name',$module)->first();
                    if($module)
                    {
                        $module= $module->module;
                        $this_module = Module::find($module);
                        $path =   $this_module->getPath();

                        $dir = $path.'/src/Resources/lang/' . $lang;
                    }else{
                        return redirect()->back()->with('error', __('Please active this module.'));
                    }
                }
                try{
                    if(file_exists($dir . '.json'))
                    {
                        $arrLabel = json_decode(file_get_contents($dir . '.json'));
                    }else{
                        return redirect()->back()->with('error', __('Permission denied.'));
                    }

                    $arrFiles   = array_diff(
                        scandir($dir), array(
                                        '..',
                                        '.',
                                    )
                    );
                    $arrMessage = [];
                    foreach($arrFiles as $file)
                    {
                        $fileName = basename($file, ".php");
                        $fileData = $myArray = include $dir . "/" . $file;
                        if(is_array($fileData))
                        {
                            $arrMessage[$fileName] = $fileData;
                        }
                    }
                    $langs = Language::where('code',$lang)->first();
                    $languages = Language::get()->pluck('name','code')->toArray();
                }catch(Exception $e){

                    return redirect()->back()->with('error',str_replace( array( '\'', '"', '`','{',"\n"), ' ', $e->getMessage()));
                }
                return view('lang.index', compact('user', 'lang', 'arrLabel', 'arrMessage','module','langs','languages'));
            }
            else
            {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        }else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show create language form
     *
     * @authenticated
     * @response view="lang.create"
     */
    public function create()
    {
        if(\Auth::user()->isAbleTo('language create'))
        {
            return view('lang.create');
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    /**
     * Store a newly created language
     *
     * @authenticated
     * @bodyParam code string required Language code (e.g., fr). Example: fr
     * @bodyParam fullname string required Full language name. Example: French
     * @response redirect back to lang.index with success message
     */
    public function store(Request $request)
    {
        if(\Auth::user()->isAbleTo('language create'))
        {
            $languageExist = Language::where('code',$request->code)->orWhere('name',$request->fullname)->first();
            if(empty($languageExist)){
                $language = new Language();
                $language->code = strtolower($request->code);
                $language->name = ucfirst($request->fullname);
                $language->save();
            }else{

                return redirect()->route('lang.index', $request->code)->with('error', __('Language already Created!'));
            }
            try{

                $Filesystem = new Filesystem();
                $langCode   = strtolower($request->code);
                $langDir    = base_path() . '/resources/lang/';
                $dir        = $langDir;

                if(!is_dir($dir))
                {
                    mkdir($dir);
                    chmod($dir, 0777);
                }

                $dir      = $dir . '/' . $langCode;
                $jsonFile = $dir . ".json";
                if(file_exists($langDir . 'en.json'))
                {
                    \File::copy($langDir . 'en.json', $jsonFile);
                    chmod($jsonFile, 0777);
                }
                if(!is_dir($dir))
                {
                    mkdir($dir);
                    chmod($dir, 0777);
                }
                $Filesystem->copyDirectory($langDir . "en", $dir . "/");

                $modules = Module::allModules();
                if($modules){
                    foreach($modules as $module)
                    {
                        $Filesystem = new Filesystem();
                        $langCode   = strtolower($request->code);
                        $path       = $module->getDevPackagePath();
                        $langDir    = $path.'/src/Resources/lang/';
                        $dir        = $langDir;
                        if(!is_dir($dir))
                        {
                            mkdir($dir);
                            chmod($dir, 0777);
                        }
                        $dir      = $dir . $langCode;

                        $jsonFile = $dir . ".json";

                        if(file_exists($langDir . 'en.json'))
                        {
                            \File::copy($langDir . 'en.json', $jsonFile);
                            chmod($jsonFile, 0777);

                        }
                        if(!is_dir($dir))
                        {
                            mkdir($dir);
                            chmod($dir, 0777);
                        }

                        $Filesystem->copyDirectory($langDir . "en", $dir . "/");
                    }
                }
                // make entry in email_tempalte_lang table for email template content
                makeEmailLang($langCode);
            }catch(Exception $e)
            {
                return redirect()->back()->with('error',str_replace( array( '\'', '"', '`','{',"\n"), ' ', $e->getMessage()));
            }

            return redirect()->route('lang.index', $langCode)->with('success', __('The language has been created successfully'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

    }

    /**
     * Display language (not implemented)
     *
     * @authenticated
     * @urlParam id int required Language ID
     */
    public function show($id)
    {
        //
    }

    /**
     * Edit language (not implemented)
     *
     * @authenticated
     * @urlParam id int required Language ID
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update language (not implemented)
     *
     * @authenticated
     * @urlParam id int required Language ID
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Delete a language
     *
     * @authenticated
     * @urlParam lang string required Language code to delete. Example: fr
     * @response view="lang.index"
     */
    public function destroy($lang)
    {
        if(\Auth::user()->isAbleTo('language delete'))
        {
            $usr = \Auth::user();
            if($usr->type == 'super admin')
            {
                $default_lang = $usr->lang;

                // Remove Email Template Language
                EmailTemplateLang::where('lang', 'LIKE', $lang)->delete();

                $langDir = base_path() . '/resources/lang/';
                if(is_dir($langDir))
                {
                    // remove directory and file
                    delete_directory($langDir . $lang);
                    if(file_exists($langDir . $lang . '.json'))
                    {
                        unlink($langDir . $lang . '.json');
                    }
                }

                $modules = Module::allModules();
                if($modules){
                    foreach($modules as $module)
                    {
                        $path       = $module->getPath();
                        $langDir    = $path.'/src/Resources/lang/';
                        if(is_dir($langDir))
                        {
                            // remove directory and file
                            delete_directory($langDir . $lang);
                            if(file_exists($langDir . $lang . '.json'))
                            {
                                unlink($langDir . $lang . '.json');
                            }
                        }
                    }
                }
                // update user that has assign deleted language.
                User::where('lang', 'LIKE', $lang)->update(['lang' => $default_lang]);
                Language::where('code',$lang)->first()->delete();

                return redirect()->route('lang.index', $default_lang)->with('success', __('The language has been deleted'));
            }
            else
            {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Save language translation data
     *
     * @authenticated
     * @urlParam lang string required Language code. Example: fr
     * @urlParam module string required Module name. Example: general
     * @bodyParam label object required JSON object of labels
     * @bodyParam message object optional JSON object of message files
     * @response redirect back to lang.index
     */
    public function storeData(Request $request, $lang,$module ='general' )
    {
        $user = \Auth::user();
        if($user->isAbleTo('language manage')){
            if($module == 'general'){
                $dir = base_path() . '/resources/lang/';
            }else{
                $modules = AddOn::where('name',$module)->first();
                if(!empty($modules))
                {
                    $this_module = Module::find($modules->module);
                    $path =   $this_module->getPath();
                    $dir = $path.'/src/Resources/lang/';
                }else{
                    return redirect()->back()->with('error', __('Please active this module.'));
                }

            }
            try{

                if(!is_dir($dir))
                {
                    mkdir($dir);
                    chmod($dir, 0777);
                }
                $jsonFile = $dir . "/" . $lang . ".json";

                file_put_contents($jsonFile, json_encode($request->label));

                $langFolder = $dir . "/" . $lang;
                if(!is_dir($langFolder))
                {
                    mkdir($langFolder);
                    chmod($langFolder, 0777);
                }
                if(($module == 'general' || $module == '') && (isset($request->message) && !empty($request->message))){
                    foreach($request->message as $fileName => $fileData)
                    {
                        $content = "<?php return [";
                        $content .= $this->buildArray($fileData);

                        $content .= "];";
                        file_put_contents($langFolder . "/" . $fileName . '.php', $content);
                    }
                }
            }catch(Exception $e){
                return redirect()->back()->with('error',$e->getMessage());
            }
            return redirect()->route('lang.index', [$lang,$module])->with('success', __('Language save successfully'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Build PHP array string from translation data
     *
     * @param array $fileData Translation data array
     * @return string
     */
    public function buildArray($fileData)
    {
        $content = "";
        foreach($fileData as $lable => $data)
        {
            if(is_array($data))
            {
                $content .= "'$lable'=>[" . $this->buildArray($data) . "],";
            }
            else
            {
                $content .= "'$lable'=>'" . addslashes($data) . "',";
            }
        }

        return $content;
    }

    /**
     * Change the current user's language
     *
     * @authenticated
     * @urlParam lang string required Language code. Example: fr
     * @response redirect back with success message
     */
    public function changeLang($lang)
    {
        // Define the data to be updated or inserted
        $data = [
            'key' => 'site_rtl',
            'workspace' => getActiveWorkSpace(),
            'created_by' => \Auth::user()->id,
        ];
        if($lang == "ar" || $lang == "he")
        {
             // Check if the record exists, and update or insert accordingly
            Setting::updateOrInsert($data, ['value' => 'on']);
        }
        else
        {
             // Check if the record exists, and update or insert accordingly
            Setting::updateOrInsert($data, ['value' => 'off']);
        }
        // Settings Cache forget
        AdminSettingCacheForget();
        comapnySettingCacheForget();
        sideMenuCacheForget();
        $user       = \Auth::user();
        $user->lang = $lang;
        $user->save();
        return redirect()->back()->with('success', __('Language Change Successfully!'));
    }

    /**
     * Enable or disable a language
     *
     * @authenticated
     * @bodyParam mode int required 1 to enable, 0 to disable. Example: 1
     * @bodyParam lang string required Language code. Example: fr
     * @response status=200, message="Language Enabled Successfully"
     */
    public function disableLang(Request $request)
    {
        if(\Auth::user()->isAbleTo('language manage'))
        {
            if($request->has('mode') && $request->lang){
                $lang = Language::where('code',$request->lang)->first();
                $lang->status = $request->mode;
                $lang->save();
            }
            if($request->mode == 0){
                $data['message'] = __('Language Disabled Successfully');
                $data['status'] = 200;
                return $data;
            }
            else
            {
                $data['message'] = __('Language Enabled Successfully');
                $data['status'] = 200;
                return $data;
            }
        }
    }

    /**
     * Export all language JSON files as a ZIP archive
     *
     * @authenticated
     * @response A ZIP file download containing all language JSON files
     */
    public function exportLangJson()
    {
        $tempDirectory = storage_path('temp-lang-files');

        if (!file_exists($tempDirectory)) {
            mkdir($tempDirectory, 0755, true);
        }

        $langDirectory = resource_path('lang');
        $baseJsonFiles = glob($langDirectory . DIRECTORY_SEPARATOR . "*.json");

        foreach ($baseJsonFiles as $filePath) {
            $fileName = basename($filePath);
            $newFileName = 'base-' . $fileName;
            copy($filePath, $tempDirectory . DIRECTORY_SEPARATOR . $newFileName);
        }

        $modules = Module::all();
        foreach ($modules as $module) {
            $moduleLangDirectories = base_path('packages/workdo/' . $module->name . '/src/Resources/lang');
            $moduleJsonFiles = glob($moduleLangDirectories . DIRECTORY_SEPARATOR . "*.json");
            foreach ($moduleJsonFiles as $moduleFilePath) {
                $moduleFileName = basename($moduleFilePath);
                $newModuleFileName = $module->name . '-' . $moduleFileName;
                copy($moduleFilePath, $tempDirectory . DIRECTORY_SEPARATOR . $newModuleFileName);
            }
        }

        $exportableFileName = 'language-file-version-'.config('verification.system_version').'.zip';
        $zipFilePath = storage_path($exportableFileName);
        $zip = new ZipArchive;

        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $renamedFiles = glob($tempDirectory . DIRECTORY_SEPARATOR . '*.json');
            foreach ($renamedFiles as $renamedFilePath) {
                $zip->addFile($renamedFilePath, basename($renamedFilePath));
            }
            $zip->close();
        } else {
            return response()->json(['error' => 'Could not create ZIP file'], 500);
        }

        array_map('unlink', glob($tempDirectory . DIRECTORY_SEPARATOR . '*'));
        rmdir($tempDirectory);

        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }

    /**
     * Show import language JSON form
     *
     * @authenticated
     * @response view="lang.import"
     */
    public function importLangJsonUpload()
    {
        return view('lang.import');
    }

    /**
     * Import language JSON from a ZIP file
     *
     * @authenticated
     * @bodyParam file file required The ZIP file containing language JSON files. Must be a zip file.
     * @response redirect back with success message
     */
    public function importLangJson(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'file' => 'required|file|mimes:zip',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $file = $request->file('file');
        $tempPath = storage_path('temp-zip');
        $zip = new ZipArchive;
        if ($zip->open($file->getPathname()) === TRUE) {
            $zip->extractTo($tempPath);
            $zip->close();
        } else {
            return response()->json(['message' => 'Failed to extract the ZIP file'], 500);
        }

        $files = glob($tempPath . '/*.json');

        foreach ($files as $uploadedFilePath) {
            $uploadedFileName = basename($uploadedFilePath);

            if (str_starts_with($uploadedFileName, 'base-')) {
                $language = str_replace('base-', '', $uploadedFileName);
                $existingFilePath = resource_path('lang/' . $language);

                $this->mergeJsonFiles($existingFilePath, $uploadedFilePath);
            } else {
                [$module, $language] = explode('-', $uploadedFileName, 2);
                $existingFilePath = base_path("packages/workdo/{$module}/src/Resources/lang/{$language}");

                $this->mergeJsonFiles($existingFilePath, $uploadedFilePath);
            }
        }

        array_map('unlink', glob($tempPath . '/*'));
        rmdir($tempPath);

        return redirect()->back()->with('success',__('Files merged successfully!'));
    }

    private function mergeJsonFiles($existingFilePath, $uploadedFilePath)
    {
        $existingData = file_exists($existingFilePath) ? json_decode(file_get_contents($existingFilePath), true) : [];

        $uploadedData = json_decode(file_get_contents($uploadedFilePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in file: $uploadedFilePath");
        }

        $mergedData = array_merge($existingData, $uploadedData);

        file_put_contents($existingFilePath, json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

}
