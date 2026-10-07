<?php

namespace App\Http\Controllers;

use App\Models\Custom;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

class SettingController extends Controller
{

    //    ---------------------- Account --------------------------------------------------------
    public function index()
    {
        $loginUser = \Auth::user();
        $settings = settings();
        if ($loginUser->type === 'super admin') {
            $central = \App\Services\CentralEmail::administrator();
            $settings = array_merge($settings, \DB::table('settings')->where('type', 'smtp')->where('parent_id', $central->id)->pluck('value', 'name')->all());
        }
        return view('settings.index', compact('loginUser', 'settings'));
    }

    public function accountData(Request $request)
    {
        $loginUser = \Auth::user();
        $user = User::find($loginUser->id);
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email|unique:users,email,' . $user->id,
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }


        if ($request->hasFile('profile')) {
            $filenameWithExt = $request->file('profile')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('profile')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $dir = storage_path('uploads/profile/');
            $image_path = $dir . $loginUser->avatar;

            if (\File::exists($image_path)) {
                \File::delete($image_path);
            }

            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $request->file('profile')->storeAs('upload/profile/', $fileNameToStore);
        }

        if (!empty($request->profile)) {
            $user->profile = $fileNameToStore;
        }
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone_number = $request->phone_number;
        $user->save();


        return redirect()->back()->with('success', __('User profile settings successfully updated.'))->with('tab', 'user_profile_settings');
    }

    public function accountDelete(Request $request)
    {
        $loginUser = \Auth::user();
        $loginUser->delete();

        return redirect()->back()->with('success', __('Your account successfully deleted.'));
    }

    //    ---------------------- Password --------------------------------------------------------



    public function passwordData(Request $request)
    {
        if (\Auth::Check()) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'current_password' => 'required',
                    'new_password' => 'required|min:6',
                    'confirm_password' => 'required|same:new_password',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $loginUser = \Auth::user();
            $data = $request->All();

            $current_password = $loginUser->password;
            if (Hash::check($data['current_password'], $current_password)) {
                $user_id = $loginUser->id;
                $user = User::find($user_id);
                $user->password = Hash::make($data['new_password']);
                ;
                $user->save();

                return redirect()->back()->with('success', __('Password successfully updated.'))->with('tab', 'password_settings');
            } else {
                return redirect()->back()->with('error', __('Please enter valid current password.'))->with('tab', 'password_settings');
            }
        } else {
            return redirect()->back()->with('error', __('Invalid user.'))->with('tab', 'password_settings');
        }
    }

    //    ---------------------- General --------------------------------------------------------

    public function generalData(Request $request)
    {
        abort_unless(auth()->user()->can('manage general settings'), 403);
        $isAdmin = auth()->user()->type === 'super admin';
        $ownerId = parentId();
        $fileFields = $isAdmin ? ['logo', 'favicon', 'light_logo', 'landing_logo', 'invoice_logo'] : ['logo', 'favicon', 'light_logo', 'invoice_logo'];
        $rules = $isAdmin ? ['application_name'=>'required|string|max:150', 'copyright'=>'nullable|string|max:500'] : [];
        foreach ($fileFields as $field) { $rules[$field] = $field === 'invoice_logo' ? 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=6000,max_height=6000' : 'nullable|file|mimes:png|max:2048'; }
        $data = $request->validate($rules);
        $values = $isAdmin
            ? ['app_name'=>$data['application_name'], 'copyright'=>$data['copyright'] ?? '']
            : ['app_name'=>'sanayirandevu.com', 'copyright'=>'© sanayirandevu.com. Tüm hakları saklıdır.'];

        foreach ($fileFields as $field) {
            if (!$request->hasFile($field)) { continue; }
            $extension = $field === 'invoice_logo' ? $request->file($field)->extension() : 'png';
            $filename = $field === 'invoice_logo' ? $ownerId.'_invoice_'.\Illuminate\Support\Str::uuid().'.'.$extension : ($isAdmin ? $field.'.png' : $ownerId.'_'.$field.'.png');
            if (!$request->file($field)->storeAs('upload/logo/', $filename)) {
                throw ValidationException::withMessages([$field=>'Logo kaydedilemedi. Yükleme klasörünün yazma izinlerini kontrol edin.']);
            }
            // Keep legacy names compatible with existing uploaded logos.
            $values[$field === 'invoice_logo' ? 'invoice_logo' : ($isAdmin ? $field : 'company_'.$field)] = $filename;
        }
        if ($isAdmin) {
            foreach (['landing_page','register_page','owner_email_verification','pricing_feature'] as $key) {
                $values[$key] = $request->input($key) === 'on' ? 'on' : 'off';
            }
        }
        \DB::transaction(function () use ($values, $ownerId) {
            foreach ($values as $name=>$value) {
                \DB::table('settings')->updateOrInsert(['parent_id'=>$ownerId,'name'=>$name], ['value'=>$value]);
            }
        });
        // Branding belongs in tenant settings; saving a logo must never write the shared .env file.
        return back()->with('success','Genel ayarlar kaydedildi.')->with('tab',$isAdmin ? 'general_settings' : 'user_profile_settings');
    }

    //    ---------------------- SMTP --------------------------------------------------------



    public function smtpData(Request $request)
    {
        abort_unless(auth()->user()?->type === 'super admin', 403);
        $data = $request->validate([
            'sender_name'=>'required|string|max:150', 'sender_email'=>'required|email|max:255',
            'server_driver'=>'required|in:smtp', 'server_host'=>'required|string|max:255|regex:/^[a-zA-Z0-9.-]+$/',
            'server_port'=>'required|integer|between:1,65535', 'server_username'=>'required|string|max:255',
            'server_password'=>'nullable|string|max:1000', 'server_encryption'=>'required|in:tls,ssl',
        ]);
        $oldPassword = \DB::table('settings')->where('type','smtp')->where('parent_id',\App\Services\CentralEmail::administrator()->id)->where('name','SERVER_PASSWORD')->value('value');
        if (empty($data['server_password']) && empty($oldPassword)) {
            return back()->withErrors(['server_password'=>'İlk kurulumda SMTP şifresini girin.'])->with('tab','email_SMTP_settings');
        }
        $smtpArray = [
            'FROM_NAME'=>$data['sender_name'], 'FROM_EMAIL'=>$data['sender_email'], 'SERVER_DRIVER'=>'smtp',
            'SERVER_HOST'=>$data['server_host'], 'SERVER_PORT'=>$data['server_port'], 'SERVER_USERNAME'=>$data['server_username'],
            'SERVER_PASSWORD'=>($data['server_password'] ?? '') ?: $oldPassword, 'SERVER_ENCRYPTION'=>$data['server_encryption'],
        ];
        \DB::transaction(function () use ($smtpArray) {
            $before=\DB::table('settings')->where('type','smtp')->where('parent_id',\App\Services\CentralEmail::administrator()->id)->pluck('value','name')->all();
            foreach ($smtpArray as $key=>$value) {
                \DB::table('settings')->updateOrInsert(['parent_id'=>\App\Services\CentralEmail::administrator()->id,'type'=>'smtp','name'=>$key], ['value'=>$value]);
            }
            \App\Services\AdminAudit::record('communication.smtp',null,$before,$smtpArray);
        });
        if (app('mail.manager') instanceof \Illuminate\Mail\MailManager) { app('mail.manager')->purge('smtp'); }
        return back()->with('success','SMTP ayarları kaydedildi. Gönderimi doğrulamak için test e-postası gönderebilirsiniz.')->with('tab','email_SMTP_settings');
    }

    public function smtpTest(Request $request)
    {
        abort_unless(auth()->user()?->type === 'super admin', 403);
        return view('settings.testmail');
    }

    public function smtpTestMailSend(Request $request)
    {
        abort_unless(auth()->user()?->type === 'super admin', 403);
        $data = $request->validate(['email'=>'required|email|max:255']);
        $response = sendEmail($data['email'], ['module'=>'test_mail', 'subject'=>'SanayiRandevu — SMTP test e-postası', 'message'=>'E-posta gönderim ayarlarınızın test mesajıdır.']);
        return back()->with($response['status']=='error' ? 'error' : 'success', $response['message'])->with('tab','email_SMTP_settings');
    }

    //    ---------------------- Payment --------------------------------------------------------



    public function paymentData(Request $request)
    {
        abort_unless(auth()->check() && auth()->user()->can('manage payment settings'), 403);
        $data = $request->validate([
            'bank_transfer_payment' => 'nullable|in:on,off',
            'bank_name' => 'required_if:bank_transfer_payment,on|nullable|string|max:255',
            'bank_holder_name' => 'required_if:bank_transfer_payment,on|nullable|string|max:255',
            'bank_account_number' => 'required_if:bank_transfer_payment,on|nullable|string|max:255',
            'bank_ifsc_code' => 'nullable|string|max:255',
            'bank_other_details' => 'nullable|string|max:2000',
        ]);
        $data['bank_transfer_payment'] = $data['bank_transfer_payment'] ?? 'off';
        $data['CURRENCY'] = 'TRY';
        $data['CURRENCY_SYMBOL'] = '₺';
        \DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                \DB::table('settings')->updateOrInsert(['name'=>$key, 'parent_id'=>parentId()], ['value'=>$value ?? '', 'type'=>'payment']);
            }
        });
        return back()->with('success', __('Payment successfully saved.'))->with('tab', 'payment_settings');
    }

    public function companyData(Request $request)
    {

        // dd($request->all());


        $validator = \Validator::make(
            $request->all(),
            [
                'company_name' => 'required',
                'company_email' => 'required',
                'company_phone' => 'required',
                'company_address' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $settings = array_replace($request->all(), [
            'CURRENCY' => 'TRY', 'CURRENCY_SYMBOL' => '₺', 'timezone' => 'Europe/Istanbul', 'company_date_format' => 'd M Y', 'company_time_format' => 'H:i',
        ]);
        unset($settings['_token']);

        foreach ($settings as $key => $val) {
            if (preg_match('/^(stripe|paypal|flutterwave|razorpay|paystack|twilio)_/i', $key)) continue;
            if (!empty($val)) {

                \DB::table('settings')->updateOrInsert(
                    ['name' => $key, 'parent_id' => parentId()],
                    ['value' => $val]
                );
            }
        }
        return redirect()->back()->with('success', __('Company setting successfully saved.'))->with('tab', 'company_settings');
    }

    //    ---------------------- Language --------------------------------------------------------


    public function themeSettings(Request $request)
    {

        $themeSettings = $request->all();
        $themeSettings['theme_layout'] = 'ltr';
        unset($themeSettings['_token']);

        foreach ($themeSettings as $key => $val) {
            if (!empty($val)) {

                \DB::insert(
                    'insert into settings (`value`, `name`,`type`,`parent_id`) values (?, ?, ?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                    [
                        $val,
                        $key,
                        'common',
                        parentId(),
                    ]
                );
            }
        }

        return redirect()->back()->with('success', __('Theme settings save successfully.'));
    }

    //    ---------------------- SEO Settings --------------------------------------------------------



    public function siteSEOData(Request $request)
    {

        $validator = \Validator::make(
            $request->all(),
            [
                'meta_seo_title' => 'required',
                'meta_seo_keyword' => 'required',
                'meta_seo_description' => 'required|max:190',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $settings = $request->all();
        unset($settings['_token']);
        if ($request->meta_seo_image) {
            $seoFilenameWithExt = $request->file('meta_seo_image')->getClientOriginalName();
            $seoFilename = pathinfo($seoFilenameWithExt, PATHINFO_FILENAME);
            $supportExtension = $request->file('meta_seo_image')->getClientOriginalExtension();
            $seoFileName = $seoFilename . '_' . time() . '.' . $supportExtension;


            $request->file('meta_seo_image')->storeAs('upload/seo/', $seoFileName);


            \DB::insert(
                'insert into settings (`value`, `name`, `type`,`parent_id`) values (?, ?, ?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                [
                    $seoFileName,
                    'meta_seo_image',
                    'SEO',
                    parentId(),
                ]
            );
        }
        unset($settings['meta_seo_image']);
        foreach ($settings as $key => $val) {
            if (preg_match('/^(stripe|paypal|flutterwave|razorpay|paystack|twilio)_/i', $key)) continue;
            if (!empty($val)) {

                \DB::insert(
                    'insert into settings (`value`, `name`, `type`,`parent_id`) values (?, ?, ?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                    [
                        $val,
                        $key,
                        'SEO',
                        parentId(),
                    ]
                );
            }
        }

        return redirect()->back()->with('success', __('Site SEO settings save successfully.'))->with('tab', 'site_SEO_settings');
    }

    // ---------------------- Google ReCaptcha Settings ---------------------------------------------
    public function googleRecaptchaData(Request $request)
    {

        $validator = \Validator::make(
            $request->all(),
            [
                'recaptcha_key' => 'required',
                'recaptcha_secret' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $settings = $request->all();
        unset($settings['_token']);

        $recaptchaArray = [
            'google_recaptcha' => $request->google_recaptcha ?? 'off',
            'recaptcha_key' => $request->recaptcha_key,
            'recaptcha_secret' => $request->recaptcha_secret,
        ];

        foreach ($recaptchaArray as $key => $val) {
            if (!empty($val)) {

                \DB::insert(
                    'insert into settings (`value`, `name`, `type`,`parent_id`) values (?, ?, ?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                    [
                        $val,
                        $key,
                        'recaptcha',
                        parentId(),
                    ]
                );
            }
        }

        return redirect()->back()->with('success', __('Google Recaptcha settings save successfully.'))->with('tab', 'google_recaptcha_settings');
    }

    // ---------------------- Footer Setting ---------------------------------------------
    public function footerSetting(Request $request)
    {
        if (!Auth::user()->can('manage footer')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        $loginUser = Auth::user();
        $pages = Page::where('enabled', 1)->pluck('title', 'id');
        return view('home_pages.footerSetting', compact('loginUser', 'pages'));
    }

    public function footerData(Request $request)
    {
        $settings = $request->all();
        unset($settings['_token']);
        unset($settings['tab']);
        foreach ($settings as $s_key => $s_value) {
            if (in_array($s_key, ['footer_column_1_pages', 'footer_column_2_pages', 'footer_column_3_pages', 'footer_column_4_pages'])) {
                $s_value = json_encode($s_value);
            }
            if (!empty($s_value)) {
                \DB::insert(
                    'insert into settings (`value`, `name`, `type`,`parent_id`) values (?, ?, ?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                    [
                        $s_value,
                        $s_key,
                        'footer',
                        parentId(),
                    ]
                );
            }
        }
        return redirect()->back()->with('success', __('Footer settings save successfully.'))->with('tab', $request->tab);
    }


    // ---------------------- 2FA Setting --------------------------------
    public function twofaEnable(Request $request)
    {
        abort_unless(Auth::user()->can('manage 2FA settings') || (Auth::user()->type === 'owner' && Auth::user()->twofa_required), 403);
        $request->session()->flash('tab','2FA');
        $request->validate(['otp'=>['required','string','regex:/^\d{6}$/D']]);
        $user = Auth::user();
        if ($user->twofa_secret || session('2fa_setup_user') !== $user->id || !session('2fa_secret')) {
            throw ValidationException::withMessages(['otp'=>'Kurulum süresi doldu. Sayfayı yenileyip yeniden deneyin.']);
        }
        $secret = session('2fa_secret');
        $step = (new Google2FA())->verifyKeyNewer($secret, $request->otp, 0, 1);
        if ($step === false) {
            throw ValidationException::withMessages(['otp'=>'Doğrulama kodu hatalı veya süresi dolmuş.']);
        }
        $codes = app(\App\Services\TwoFactorAuthentication::class)->recoveryCodes();
        \DB::transaction(function () use ($user, $secret, $step, $codes) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->twofa_secret) {
                throw ValidationException::withMessages(['otp'=>'İki aşamalı doğrulama zaten etkin.']);
            }
            $locked->twofa_secret = $secret;
            $locked->twofa_last_used_at = $step;
            $locked->twofa_recovery_codes = array_map(fn($code)=>Hash::make($code), $codes);
            $locked->save();
        });
        $user->refresh();
        $request->session()->forget(['2fa_secret','2fa_setup_user']);
        session(['2fa_checked'=>true,'2fa_verified_key'=>hash('sha256',$secret)]);
        app(\App\Services\OwnerLoginSecurity::class)->record($request);
        return redirect()->route('setting.index')->with('tab','2FA')->with('twofa_recovery_codes',$codes)
            ->with('success','İki aşamalı doğrulama etkinleştirildi. Kurtarma kodlarını güvenli bir yere kaydedin.');
    }

    public function openai(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'openai_secret_key' => $request->openai_module === 'on'
                ? 'required'
                : 'nullable',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->messages()->first());
        }

        $openaiArray = [
            'openai_secret_key' => $request->openai_secret_key,
            'openai_module' => $request->openai_module ?? 'off',
        ];

        foreach ($openaiArray as $key => $val) {
            if (!empty($val)) {
                \DB::insert(
                    'insert into settings (`value`, `name`, `type`,`parent_id`) values (?, ?, ?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                    [
                        $val,
                        $key,
                        'openai',
                        parentId(),
                    ]
                );
            }
        }
        return redirect()->back()->with('success', __('Open ai settings updated successfully.'))->with('tab', 'openai');
    }
}
