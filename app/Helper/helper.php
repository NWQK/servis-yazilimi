<?php

use App\Mail\Common;
use App\Mail\EmailVerification;
use App\Mail\TestMail;
use App\Models\AiTemplate;
use App\Models\AuthPage;
use App\Models\Custom;
use App\Models\FAQ;
use App\Models\HomePage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LoggedHistory;
use App\Models\N8n;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FAQRCode\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

if (!function_exists('settingsKeys')) {
    function settingsKeys()
    {
        return $settingsKeys = [
            "app_name" => "",
            "theme_mode" => "light",
            "layout_font" => "Roboto",
            "accent_color" => "preset-6",
            "color_type" => "preset",
            "custom_color" => "--primary-rgb: 0,0,0",
            "custom_color_code" => "#000000",
            "sidebar_caption" => "true",
            "theme_layout" => "ltr",
            "layout_width" => "false",
            "owner_email_verification" => "off",
            "landing_page" => "on",
            "register_page" => "on",
            "company_logo" => "logo.png",
            "company_favicon" => "favicon.png",
            "landing_logo" => "landing_logo.png",
            "light_logo" => "light_logo.png",
            "meta_seo_title" => "",
            "meta_seo_keyword" => "",
            "meta_seo_description" => "",
            "meta_seo_image" => "",
            "company_date_format" => "d M Y",
            "company_time_format" => "H:i",
            "company_name" => "",
            "company_phone" => "",
            "company_address" => "",
            "company_email" => "",
            "company_email_from_name" => "",
            "google_recaptcha" => "off",
            "recaptcha_key" => "",
            "recaptcha_secret" => "",
            'SERVER_DRIVER' => "",
            'SERVER_HOST' => "",
            'SERVER_PORT' => "",
            'SERVER_USERNAME' => "",
            'SERVER_PASSWORD' => "",
            'SERVER_ENCRYPTION' => "",
            'FROM_EMAIL' => "",
            'FROM_NAME' => "",
            "client_number_prefix" => "#CLI-000",
            "employee_number_prefix" => "#EMP-000",
            "vehicle_number_prefix" => "#VHC-000",
            "invoice_number_prefix" => "#INV-000",
            "service_number_prefix" => "#SER-000",
            "quotation_number_prefix" => "#QUO-000",
            'CURRENCY' => "TRY",
            'CURRENCY_SYMBOL' => "₺",
            "bank_transfer_payment" => "off",
            "bank_name" => "",
            "bank_holder_name" => "",
            "bank_account_number" => "",
            "bank_ifsc_code" => "",
            "bank_other_details" => "",
            "timezone" => "Europe/Istanbul",
            "footer_column_1" => "Quick Links",
            "footer_column_1_enabled" => "active",
            "footer_column_2" => "Help",
            "footer_column_2_enabled" => "active",
            "footer_column_3" => "OverView",
            "footer_column_3_enabled" => "active",
            "footer_column_4" => "Core System",
            "footer_column_4_enabled" => "active",
            "pricing_feature" => "on",
            'openai_secret_key' => '',
            'openai_module' => '',
            'copyright' => '',
            'whatsapp_instance' => '',
            'whatsapp_token' => '',
        ];
    }
}

if (!function_exists('settings')) {
    function settings()
    {
        $settingData = DB::table('settings');
        if (\Auth::check()) {
            $userId = parentId();
            $settingData = $settingData->where('parent_id', $userId);
        } else {
            $settingData = $settingData->where('parent_id', 1);
        }
        $settingData = $settingData->get();
        $details = settingsKeys();

        foreach ($settingData as $row) {
            $details[$row->name] = $row->value;
        }

        config(
            [
                'captcha.secret' => $details['recaptcha_secret'],
                'captcha.sitekey' => $details['recaptcha_key'],
                'options' => [
                    'timeout' => 30,
                ]
            ]
        );

        return array_replace($details, ['CURRENCY' => 'TRY', 'CURRENCY_SYMBOL' => '₺', 'timezone' => 'Europe/Istanbul', 'company_date_format' => 'd M Y', 'company_time_format' => 'H:i']);
    }
}

if (!function_exists('subscriptionPaymentSettings')) {
    function subscriptionPaymentSettings()
    {
        $settingData = DB::table('settings')->where('type', 'payment')->where('parent_id', '=', 1)->get();
        $result = [
            'CURRENCY' => "TRY",
            'CURRENCY_SYMBOL' => "₺",
            "bank_transfer_payment" => "off",
            "bank_name" => "",
            "bank_holder_name" => "",
            "bank_account_number" => "",
            "bank_ifsc_code" => "",
            "bank_other_details" => "",
        ];

        foreach ($settingData as $setting) {
            $result[$setting->name] = $setting->value;
        }

        $result['CURRENCY'] = 'TRY';
        $result['CURRENCY_SYMBOL'] = '₺';
        return $result;
    }
}

if (!function_exists('invoicePaymentSettings')) {
    function invoicePaymentSettings($id)
    {
        $settingData = DB::table('settings')->where('type', 'payment')->where('parent_id', $id)->get();
        $result = [
            'CURRENCY' => "TRY",
            'CURRENCY_SYMBOL' => "₺",
            "bank_transfer_payment" => "off",
            "bank_name" => "",
            "bank_holder_name" => "",
            "bank_account_number" => "",
            "bank_ifsc_code" => "",
            "bank_other_details" => "",

        ];

        foreach ($settingData as $row) {
            $result[$row->name] = $row->value;
        }
        $result['CURRENCY'] = 'TRY';
        $result['CURRENCY_SYMBOL'] = '₺';
        return $result;
    }
}

if (!function_exists('getSettingsValByName')) {
    function getSettingsValByName($key)
    {
        $setting = settings();
        if (!isset($setting[$key]) || empty($setting[$key])) {
            $setting[$key] = '';
        }

        return $setting[$key];
    }
}

if (!function_exists('getSettingsValByIdName')) {
    function getSettingsValByIdName($id, $key)
    {
        $setting = settingsById($id);
        if (!isset($setting[$key]) || empty($setting[$key])) {
            $setting[$key] = '';
        }

        return $setting[$key];
    }
}

if (!function_exists('settingDateFormat')) {
    function settingDateFormat($settings, $date)
    {
        return $date ? \Carbon\Carbon::parse($date)->locale('tr')->setTimezone('Europe/Istanbul')->translatedFormat('d M Y') : '—';
    }
}
if (!function_exists('settingPriceFormat')) {
    function settingPriceFormat($settings, $price)
    {
        return number_format((float) $price, 2, ',', '.') . ' ₺';
    }
}
if (!function_exists('settingTimeFormat')) {
    function settingTimeFormat($settings, $time)
    {
        return $time ? \Carbon\Carbon::parse($time)->locale('tr')->setTimezone('Europe/Istanbul')->format('H:i') : '—';
    }
}
if (!function_exists('dateFormat')) {
    function dateFormat($date)
    {

        return $date ? \Carbon\Carbon::parse($date)->locale('tr')->setTimezone('Europe/Istanbul')->translatedFormat('d M Y') : '—';
    }
}
if (!function_exists('timeFormat')) {
    function timeFormat($time)
    {

        return $time ? \Carbon\Carbon::parse($time)->locale('tr')->setTimezone('Europe/Istanbul')->format('H:i') : '—';
    }
}
if (!function_exists('priceFormat')) {
    function priceFormat($price)
    {
        $settings = settings();

        return number_format((float) $price, 2, ',', '.') . ' ₺';
    }
}
if (!function_exists('parentId')) {
    function parentId()
    {
        if (\Auth::user()->type == 'owner' || \Auth::user()->type == 'super admin') {
            return \Auth::user()->id;
        } else {
            return \Auth::user()->parent_id;
        }
    }
}
// assignSubscription
if (!function_exists('assignSubscription')) {
    function assignSubscription($id)
    {
        $subscription = Subscription::find($id);
        if ($subscription) {
            \Auth::user()->subscription = $subscription->id;
            if ($subscription->interval == 'Monthly') {
                \Auth::user()->subscription_expire_date = Carbon::now()->addMonths(1)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Quarterly') {
                \Auth::user()->subscription_expire_date = Carbon::now()->addMonths(3)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Yearly') {
                \Auth::user()->subscription_expire_date = Carbon::now()->addYears(1)->isoFormat('YYYY-MM-DD');
            } else {
                \Auth::user()->subscription_expire_date = Carbon::now()->addMonths(1)->isoFormat('YYYY-MM-DD');
            }
            \Auth::user()->save();

            SetLimit($subscription, auth()->user()->id); // handles all is_active logic

            return ['is_success' => true];
        } else {
            return ['is_success' => false, 'error' => 'Subscription is deleted.'];
        }
    }
}

// assignManuallySubscription
if (!function_exists('assignManuallySubscription')) {
    function assignManuallySubscription($id, $userId)
    {
        $owner = User::find($userId);
        $subscription = Subscription::find($id);
        if ($subscription) {
            $owner->subscription = $subscription->id;
            if ($subscription->interval == 'Monthly') {
                $owner->subscription_expire_date = Carbon::now()->addMonths(1)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Quarterly') {
                $owner->subscription_expire_date = Carbon::now()->addMonths(3)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Yearly') {
                $owner->subscription_expire_date = Carbon::now()->addYears(1)->isoFormat('YYYY-MM-DD');
            } else {
                $owner->subscription_expire_date = Carbon::now()->addMonths(1)->isoFormat('YYYY-MM-DD');
            }
            $owner->save();

            SetLimit($subscription, $userId); // handles all is_active logic

            return ['is_success' => true];
        } else {
            return ['is_success' => false, 'error' => 'Subscription is deleted.'];
        }
    }
}


if (!function_exists('SetLimit')) {
    function SetLimit($subscription, $id)
    {
        // set user_limit
        $user_limit = $subscription->user_limit;
        $users = User::where('parent_id', '=', $id)->whereNotIn('type', ['super admin', 'owner', 'employee', 'client'])->update(['is_active' => 0]);
        $users = User::where('parent_id', '=', $id)->whereNotIn('type', ['super admin', 'owner', 'employee', 'client'])->take($user_limit)->get()->each(function ($user) {
            $user->update(['is_active' => 1]);
        });

        // set employee
        $employee_limit = $subscription->employee_limit;
        $employee = User::where('parent_id', '=', $id)->whereIn('type', ['employee'])->update(['is_active' => 0]);
        $employee = User::where('parent_id', '=', $id)->whereIn('type', ['employee'])->take($employee_limit)->get()->each(function ($employee) {
            $employee->update(['is_active' => 1]);
        });

        // set client
        $client_limit = $subscription->client_limit;
        $client = User::where('parent_id', '=', $id)->whereIn('type', ['client'])->update(['is_active' => 0]);
        $client = User::where('parent_id', '=', $id)->whereIn('type', ['client'])->take($client_limit)->get()->each(function ($client) {
            $client->update(['is_active' => 1]);
        });
    }
}
if (!function_exists('smtpDetail')) {
    function smtpDetail($id)
    {
        $settings = emailSettings($id);

        $smtpDetail = config(
            [
                'mail.mailers.smtp.transport' => $settings['SERVER_DRIVER'],
                'mail.mailers.smtp.host' => $settings['SERVER_HOST'],
                'mail.mailers.smtp.port' => $settings['SERVER_PORT'],
                'mail.mailers.smtp.encryption' => $settings['SERVER_ENCRYPTION'],
                'mail.mailers.smtp.username' => $settings['SERVER_USERNAME'],
                'mail.mailers.smtp.password' => $settings['SERVER_PASSWORD'],
                'mail.from.address' => $settings['FROM_EMAIL'],
                'mail.from.name' => $settings['FROM_NAME'],
            ]
        );

        return $smtpDetail;
    }
}

if (!function_exists('clientPrefix')) {
    function clientPrefix()
    {
        $settings = settings();
        return $settings["client_number_prefix"];
    }
}
if (!function_exists('employeePrefix')) {
    function employeePrefix()
    {
        $settings = settings();
        return $settings["employee_number_prefix"];
    }
}
if (!function_exists('vehiclePrefix')) {
    function vehiclePrefix()
    {
        $settings = settings();
        return $settings["vehicle_number_prefix"];
    }
}
if (!function_exists('servicePrefix')) {
    function servicePrefix()
    {
        $settings = settings();
        return $settings["service_number_prefix"];
    }
}
if (!function_exists('invoicePrefix')) {
    function invoicePrefix()
    {
        $settings = settings();
        return $settings["invoice_number_prefix"];
    }
}
if (!function_exists('quotationPrefix')) {
    function quotationPrefix()
    {
        $settings = settings();
        return $settings["quotation_number_prefix"];
    }
}

if (!function_exists('timeCalculation')) {
    function timeCalculation($startDate, $startTime, $endDate, $endTime)
    {
        $startdate = $startDate . ' ' . $startTime;
        $enddate = $endDate . ' ' . $endTime;

        $startDateTime = new DateTime($startdate);
        $endDateTime = new DateTime($enddate);

        $interval = $startDateTime->diff($endDateTime);
        $totalHours = $interval->h + $interval->i / 60;

        return number_format($totalHours, 2);
    }
}

if (!function_exists('setup')) {
    function setup()
    {
        $setupPath = storage_path() . "/installed";
        return $setupPath;
    }
}

if (!function_exists('userLoggedHistory')) {
    function userLoggedHistory()
    {
        $serverip = $_SERVER['REMOTE_ADDR'];
        $data = @unserialize(file_get_contents('http://ip-api.com/php/' . $serverip));
        if (isset($data['status']) && $data['status'] == 'success') {
            $browser = new \WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);
            if ($browser->device->type == 'bot') {
                return redirect()->intended(RouteServiceProvider::HOME);
            }
            $referrerData = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : null;
            $data['browser'] = $browser->browser->name ?? null;
            $data['os'] = $browser->os->name ?? null;
            $data['language'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null;
            $data['device'] = User::getDevice($_SERVER['HTTP_USER_AGENT']);
            $data['referrer_host'] = !empty($referrerData['host']);
            $data['referrer_path'] = !empty($referrerData['path']);
            $result = json_encode($data);
            $details = new LoggedHistory();
            $details->type = Auth::user()->type;
            $details->user_id = Auth::user()->id;
            $details->date = date('Y-m-d H:i:s');
            $details->Details = $result;
            $details->ip = $serverip;
            $details->parent_id = parentId();
            $details->save();
        }
    }
}

if (!function_exists('defaultEmployeeCreate')) {
    function defaultEmployeeCreate($id)
    {
        // Default Employee role
        $employeeRoleData = [
            'name' => 'employee',
            'parent_id' => $id,
        ];
        $systemEmployeeRole = Role::create($employeeRoleData);
        // Default Employee permissions
        $systemEmployeePermissions = [
            ['name' => 'manage contact'],
            ['name' => 'create contact'],
            ['name' => 'edit contact'],
            ['name' => 'delete contact'],
            ['name' => 'manage note'],
            ['name' => 'manage account settings'],
            ['name' => 'manage password settings'],
            ['name' => 'manage 2FA settings'],
            ['name' => 'manage service'],
            ['name' => 'show service'],
            ['name' => 'manage item'],
            ['name' => 'show item'],
            ['name' => 'create quotation'],
            ['name' => 'manage quotation'],
            ['name' => 'show quotation'],

        ];
        $permission = Permission::whereIn('name', $systemEmployeePermissions)->get();
        $systemEmployeeRole->givePermissionTo($permission);
        return $systemEmployeeRole;
    }
}

if (!function_exists('settingsById')) {

    function settingsById($userId)
    {
        $data = DB::table('settings');
        $data = $data->where('parent_id', $userId);
        $data = $data->get();
        $settings = settingsKeys();

        foreach ($data as $row) {
            $settings[$row->name] = $row->value;
        }

        config(
            [
                'captcha.secret' => $settings['recaptcha_key'],
                'captcha.sitekey' => $settings['recaptcha_secret'],
                'options' => [
                    'timeout' => 30,
                ],
            ]
        );

        return array_replace($settings, ['CURRENCY' => 'TRY', 'CURRENCY_SYMBOL' => '₺', 'timezone' => 'Europe/Istanbul', 'company_date_format' => 'd M Y', 'company_time_format' => 'H:i']);
    }
}


if (!function_exists('defaultClientCreate')) {
    function defaultClientCreate($id)
    {
        // Default Client role
        $clientRoleData = [
            'name' => 'client',
            'parent_id' => $id,
        ];
        $systemClientRole = Role::create($clientRoleData);
        // Default Client permissions
        $systemClientPermissions = [
            ['name' => 'manage contact'],
            ['name' => 'create contact'],
            ['name' => 'edit contact'],
            ['name' => 'delete contact'],
            ['name' => 'manage note'],
            ['name' => 'create quotation'],
            ['name' => 'manage quotation'],
            ['name' => 'show quotation'],
            ['name' => 'edit quotation'],
            ['name' => 'manage invoice'],
            ['name' => 'show invoice'],
            ['name' => 'manage vehicle'],
            ['name' => 'show vehicle'],
            ['name' => 'create invoice payment'],
            ['name' => 'manage service'],
            ['name' => 'show service'],
            ['name' => 'manage account settings'],
            ['name' => 'manage password settings'],
            ['name' => 'manage 2FA settings'],
        ];
        $permission = Permission::whereIn('name', $systemClientPermissions)->get();
        $systemClientRole->givePermissionTo($permission);
        return $systemClientRole;
    }
}

if (!function_exists('defultTemplate')) {
    function defultTemplate($id)
    {
        $templateData = [
            'user_create' => [
                'module' => 'user_create',
                'name' => 'New User',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_user_name}', '{app_link}', '{username}', '{password}'],
                'subject' => 'Welcome',
                'templete' => '
                    <p><strong>Dear {new_user_name}</strong>,</p><p>&nbsp;</p><blockquote><p>Welcome to {company_name}! We are excited to have you on board and look forward to providing you with an exceptional experience.</p><p>We hope you enjoy your experience with us. If you have any feedback, feel free to share it with us.</p><p>&nbsp;</p><p>Your account details are as follows:</p><p><strong>App Link:</strong> <a href="{app_link}">{app_link}</a></p><p><strong>Username:</strong> {username}</p><p><strong>Password:</strong> {password}</p><p>&nbsp;</p><p>Thank you for choosing .</p></blockquote>',
            ],
            'employee_create' => [
                'module' => 'employee_create',
                'name' => 'New Employee',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_employee_name}'],
                'subject' => 'Welcome to {company_name}',
                'templete' => '
                    <p><strong>Dear {new_employee_name}</strong>,</p><p>&nbsp;</p><blockquote><p>Welcome to {company_name}!</p><p>We are thrilled to have you join our team. At {company_name}, we strive to create an environment that fosters growth, collaboration, and innovation. We are confident that you will make a valuable contribution to our success.</p><p>If you have any questions or need assistance, please feel free to reach out to us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Our office is located at:</p><p><strong>{company_address}</strong></p><p>&nbsp;</p><p>We look forward to an exciting journey together. Welcome aboard!</p></blockquote>
                ',
            ],
            'client_create' => [
                'module' => 'client_create',
                'name' => 'New Client',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_client_name}'],
                'subject' => 'Welcome to {company_name}',
                'templete' => '
                    <p><strong>Dear {new_client_name}</strong>,</p><p>&nbsp;</p><blockquote><p>Welcome to {company_name}!</p><p>We are delighted to have you as a valued client. At {company_name}, we are dedicated to providing you with outstanding service and support tailored to your needs.</p><p>If you have any questions or require assistance, please don’t hesitate to contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}. We’re here to help!</p><p>&nbsp;</p><p>Our office is located at:</p><p><strong>{company_address}</strong></p><p>&nbsp;</p><p>Thank you for choosing {company_name}. We look forward to a successful partnership!</p></blockquote>
                ',
            ],
            'vehicle_create' => [
                'module' => 'vehicle_create',
                'name' => 'New Vehicle',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{vehicle_color}', '{license_plate}', '{engine_type}', '{fuel_type}', '{mileage}', '{last_service_date}', '{next_service_date}', '{insurance_details}'],
                'subject' => 'Your Vehicle Information with {company_name}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>Thank you for choosing {company_name}! We are pleased to inform you that your vehicle details have been successfully registered in our system.</p><p>Please find your vehicle details below:</p><ul><li><strong>Vehicle Number:</strong> {vehicle_number}</li><li><strong>Vehicle Type:</strong> {vehicle_type}</li><li><strong>Brand:</strong> {vehicle_brand}</li><li><strong>Model:</strong> {vehicle_model}</li><li><strong>Color:</strong> {vehicle_color}</li><li><strong>License Plate:</strong> {license_plate}</li><li><strong>Engine Type:</strong> {engine_type}</li><li><strong>Fuel Type:</strong> {fuel_type}</li><li><strong>Mileage:</strong> {mileage}</li><li><strong>Last Service Date:</strong> {last_service_date}</li><li><strong>Next Service Due:</strong> {next_service_date}</li><li><strong>Insurance Details:</strong> {insurance_details}</li></ul><p>If you have any questions or require assistance, please feel free to reach out to us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}. We are always here to help!</p><p>&nbsp;</p><p>Thank you for trusting {company_name}.</p></blockquote>
                ',
            ],
            'service_create' => [
                'module' => 'service_create',
                'name' => 'New Service',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{license_plate}', '{engine_type}', '{fuel_type}', '{last_service_date}', '{employee_name}', '{employee_email}', '{employee_phone_number}', '{status}'],
                'subject' => 'Service Booking Confirmation with {company_name}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>Thank you for choosing {company_name} for your vehicle service needs! Your service booking has been successfully created. Below are the details of your booking:</p><ul><li><strong>Vehicle Number:</strong> {vehicle_number}</li><li><strong>Vehicle Type:</strong> {vehicle_type}</li><li><strong>Brand:</strong> {vehicle_brand}</li><li><strong>Model:</strong> {vehicle_model}</li><li><strong>License Plate:</strong> {license_plate}</li><li><strong>Engine Type:</strong> {engine_type}</li><li><strong>Fuel Type:</strong> {fuel_type}</li><li><strong>Last Service Date:</strong> {last_service_date}</li><li><strong>Service Status:</strong> {status}</li></ul><p>Your service will be handled by:</p><ul><li><strong>Employee Name:</strong> {employee_name}</li><li><strong>Email:</strong> {employee_email}</li><li><strong>Phone Number:</strong> {employee_phone_number}</li></ul><p>If you have any questions or need to make changes to your booking, please feel free to contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}. We’re here to assist you!</p><p>&nbsp;</p><p>Thank you for trusting {company_name}. We look forward to providing you with excellent service.</p></blockquote>
                ',
            ],
            'service_assign' => [
                'module' => 'service_assign',
                'name' => 'Assign Servicce',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{employee_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{license_plate}', '{engine_type}', '{fuel_type}', '{last_service_date}', '{client_name}', '{client_email}', '{client_phone_number}', '{status}'],
                'subject' => 'Service Assignment Notification - {company_name}',
                'templete' => '
                    <p><strong>Dear {employee_name},</strong></p><p>&nbsp;</p><blockquote><p>We are pleased to inform you that a new service has been assigned to you. Below are the details of the assigned service:</p><ul><li><strong>Client Name:</strong> {client_name}</li><li><strong>Client Email:</strong> {client_email}</li><li><strong>Client Phone Number:</strong> {client_phone_number}</li></ul><p>Vehicle Details:</p><ul><li><strong>Vehicle Number:</strong> {vehicle_number}</li><li><strong>Vehicle Type:</strong> {vehicle_type}</li><li><strong>Brand:</strong> {vehicle_brand}</li><li><strong>Model:</strong> {vehicle_model}</li><li><strong>License Plate:</strong> {license_plate}</li><li><strong>Engine Type:</strong> {engine_type}</li><li><strong>Fuel Type:</strong> {fuel_type}</li><li><strong>Last Service Date:</strong> {last_service_date}</li><li><strong>Service Status:</strong> {status}</li></ul><p>If you have any questions or require additional information, please contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Thank you for your commitment to providing excellent service at {company_name}.</p></blockquote>
                ',
            ],
            'invoice_create' => [
                'module' => 'invoice_create',
                'name' => 'New Invoice',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{invoice_number}', '{service_number}', '{invoice_date}', '{total_amount}', '{status}'],
                'subject' => 'Invoice #{invoice_number} from {company_name}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>We are pleased to inform you that an invoice has been generated for the services provided. Below are the details of your invoice:</p><ul><li><strong>Invoice Number:</strong> {invoice_number}</li><li><strong>Service Number:</strong> {service_number}</li><li><strong>Invoice Date:</strong> {invoice_date}</li><li><strong>Total Amount:</strong> {company_currency} {total_amount}</li><li><strong>Status:</strong> {status}</li></ul><p>If you have any questions or require further clarification, please feel free to reach out to us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Thank you for choosing {company_name}. We look forward to serving you again!</p></blockquote>
                ',
            ],
            'payment_create' => [
                'module' => 'payment_create',
                'name' => 'New Payment',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{invoice_number}', '{total_amount}', '{due_amount}', '{paid_amount}', '{status}'],
                'subject' => 'Payment Confirmation - Invoice #{invoice_number}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>We are pleased to inform you that we have received your payment. Below are the payment details:</p><ul><li><strong>Invoice Number:</strong> {invoice_number}</li><li><strong>Total Amount:</strong> {company_currency} {total_amount}</li><li><strong>Paid Amount:</strong> {company_currency} {paid_amount}</li><li><strong>Due Amount:</strong> {company_currency} {due_amount}</li><li><strong>Status:</strong> {status}</li></ul><p>If you have any questions or need further clarification, please do not hesitate to contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Thank you for your prompt payment and for choosing {company_name}. We look forward to serving you again in the future!</p></blockquote>
                ',
            ],

        ];

        // Store all created templates if needed
        $createdTemplates = [];

        foreach ($templateData as $key => $value) {
            $template = new Notification();
            $template->module = $value['module'];
            $template->name = $value['name'];
            $template->subject = $value['subject'];
            $template->message = $value['templete'];
            $template->short_code = json_encode($value['short_code']);
            $template->enabled_email = 0;
            $template->parent_id = $id; // Associate with the provided ID
            $template->save();

            $createdTemplates[] = $template; // Collect all created templates
        }

        // Return all created templates if needed
        return $createdTemplates;
    }
}



if (!function_exists('defaultTemplateList')) {
    function defaultTemplateList()
    {

        return [
            'user_create' => [
                'module' => 'user_create',
                'name' => 'New User',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_user_name}', '{app_link}', '{username}', '{password}'],
                'subject' => 'Welcome',
                'templete' => '
                    <p><strong>Dear {new_user_name}</strong>,</p><p>&nbsp;</p><blockquote><p>Welcome to {company_name}! We are excited to have you on board and look forward to providing you with an exceptional experience.</p><p>We hope you enjoy your experience with us. If you have any feedback, feel free to share it with us.</p><p>&nbsp;</p><p>Your account details are as follows:</p><p><strong>App Link:</strong> <a href="{app_link}">{app_link}</a></p><p><strong>Username:</strong> {username}</p><p><strong>Password:</strong> {password}</p><p>&nbsp;</p><p>Thank you for choosing .</p></blockquote>',
                'sms_message' => 'Hi {new_user_name},
                                welcome to {company_name}!
                                App: {app_link}
                                Username: {username}
                                Password: {password}',
            ],
            'employee_create' => [
                'module' => 'employee_create',
                'name' => 'New Employee',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_employee_name}'],
                'subject' => 'Welcome to {company_name}',
                'templete' => '
                    <p><strong>Dear {new_employee_name}</strong>,</p><p>&nbsp;</p><blockquote><p>Welcome to {company_name}!</p><p>We are thrilled to have you join our team. At {company_name}, we strive to create an environment that fosters growth, collaboration, and innovation. We are confident that you will make a valuable contribution to our success.</p><p>If you have any questions or need assistance, please feel free to reach out to us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Our office is located at:</p><p><strong>{company_address}</strong></p><p>&nbsp;</p><p>We look forward to an exciting journey together. Welcome aboard!</p></blockquote>
                ',
                'sms_message' => 'Dear {new_employee_name},
                                welcome to {company_name}!
                                We’re excited to have you join our team.
                                For any queries, contact us at {company_phone_number}.',
            ],
            'client_create' => [
                'module' => 'client_create',
                'name' => 'New Client',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_client_name}'],
                'subject' => 'Welcome to {company_name}',
                'templete' => '
                    <p><strong>Dear {new_client_name}</strong>,</p><p>&nbsp;</p><blockquote><p>Welcome to {company_name}!</p><p>We are delighted to have you as a valued client. At {company_name}, we are dedicated to providing you with outstanding service and support tailored to your needs.</p><p>If you have any questions or require assistance, please don’t hesitate to contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}. We’re here to help!</p><p>&nbsp;</p><p>Our office is located at:</p><p><strong>{company_address}</strong></p><p>&nbsp;</p><p>Thank you for choosing {company_name}. We look forward to a successful partnership!</p></blockquote>
                ',
                'sms_message' => 'Dear {new_client_name},
                                    welcome to {company_name}!
                                    We’re glad to have you with us.
                                    For assistance, call {company_phone_number}.',
            ],
            'vehicle_create' => [
                'module' => 'vehicle_create',
                'name' => 'New Vehicle',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{vehicle_color}', '{license_plate}', '{engine_type}', '{fuel_type}', '{mileage}', '{last_service_date}', '{next_service_date}', '{insurance_details}'],
                'subject' => 'Your Vehicle Information with {company_name}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>Thank you for choosing {company_name}! We are pleased to inform you that your vehicle details have been successfully registered in our system.</p><p>Please find your vehicle details below:</p><ul><li><strong>Vehicle Number:</strong> {vehicle_number}</li><li><strong>Vehicle Type:</strong> {vehicle_type}</li><li><strong>Brand:</strong> {vehicle_brand}</li><li><strong>Model:</strong> {vehicle_model}</li><li><strong>Color:</strong> {vehicle_color}</li><li><strong>License Plate:</strong> {license_plate}</li><li><strong>Engine Type:</strong> {engine_type}</li><li><strong>Fuel Type:</strong> {fuel_type}</li><li><strong>Mileage:</strong> {mileage}</li><li><strong>Last Service Date:</strong> {last_service_date}</li><li><strong>Next Service Due:</strong> {next_service_date}</li><li><strong>Insurance Details:</strong> {insurance_details}</li></ul><p>If you have any questions or require assistance, please feel free to reach out to us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}. We are always here to help!</p><p>&nbsp;</p><p>Thank you for trusting {company_name}.</p></blockquote>
                ',
                'sms_message' => 'Dear {client_name},
                                    your vehicle ({vehicle_number}, {vehicle_brand} {vehicle_model}) has been registered with {company_name}.
                                    For queries, call {company_phone_number}',
            ],
            'service_create' => [
                'module' => 'service_create',
                'name' => 'New Service',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{license_plate}', '{engine_type}', '{fuel_type}', '{last_service_date}', '{employee_name}', '{employee_email}', '{employee_phone_number}', '{status}'],
                'subject' => 'Service Booking Confirmation with {company_name}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>Thank you for choosing {company_name} for your vehicle service needs! Your service booking has been successfully created. Below are the details of your booking:</p><ul><li><strong>Vehicle Number:</strong> {vehicle_number}</li><li><strong>Vehicle Type:</strong> {vehicle_type}</li><li><strong>Brand:</strong> {vehicle_brand}</li><li><strong>Model:</strong> {vehicle_model}</li><li><strong>License Plate:</strong> {license_plate}</li><li><strong>Engine Type:</strong> {engine_type}</li><li><strong>Fuel Type:</strong> {fuel_type}</li><li><strong>Last Service Date:</strong> {last_service_date}</li><li><strong>Service Status:</strong> {status}</li></ul><p>Your service will be handled by:</p><ul><li><strong>Employee Name:</strong> {employee_name}</li><li><strong>Email:</strong> {employee_email}</li><li><strong>Phone Number:</strong> {employee_phone_number}</li></ul><p>If you have any questions or need to make changes to your booking, please feel free to contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}. We’re here to assist you!</p><p>&nbsp;</p><p>Thank you for trusting {company_name}. We look forward to providing you with excellent service.</p></blockquote>
                ',
                'sms_message' => 'Dear {client_name},
                                    your vehicle service booking for {vehicle_number} is confirmed with {company_name}.
                                    Assigned to {employee_name} ({employee_phone_number}).',
            ],
            'service_assign' => [
                'module' => 'service_assign',
                'name' => 'Assign Servicce',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{employee_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{license_plate}', '{engine_type}', '{fuel_type}', '{last_service_date}', '{client_name}', '{client_email}', '{client_phone_number}', '{status}'],
                'subject' => 'Service Assignment Notification - {company_name}',
                'templete' => '
                    <p><strong>Dear {employee_name},</strong></p><p>&nbsp;</p><blockquote><p>We are pleased to inform you that a new service has been assigned to you. Below are the details of the assigned service:</p><ul><li><strong>Client Name:</strong> {client_name}</li><li><strong>Client Email:</strong> {client_email}</li><li><strong>Client Phone Number:</strong> {client_phone_number}</li></ul><p>Vehicle Details:</p><ul><li><strong>Vehicle Number:</strong> {vehicle_number}</li><li><strong>Vehicle Type:</strong> {vehicle_type}</li><li><strong>Brand:</strong> {vehicle_brand}</li><li><strong>Model:</strong> {vehicle_model}</li><li><strong>License Plate:</strong> {license_plate}</li><li><strong>Engine Type:</strong> {engine_type}</li><li><strong>Fuel Type:</strong> {fuel_type}</li><li><strong>Last Service Date:</strong> {last_service_date}</li><li><strong>Service Status:</strong> {status}</li></ul><p>If you have any questions or require additional information, please contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Thank you for your commitment to providing excellent service at {company_name}.</p></blockquote>
                ',
                'sms_message' => 'Dear {employee_name},
                                a new service for {vehicle_number} ({vehicle_brand} {vehicle_model}) has been assigned to you.
                                Client: {client_name},
                                Ph: {client_phone_number}.',
            ],
            'invoice_create' => [
                'module' => 'invoice_create',
                'name' => 'New Invoice',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{invoice_number}', '{service_number}', '{invoice_date}', '{total_amount}', '{status}'],
                'subject' => 'Invoice #{invoice_number} from {company_name}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>We are pleased to inform you that an invoice has been generated for the services provided. Below are the details of your invoice:</p><ul><li><strong>Invoice Number:</strong> {invoice_number}</li><li><strong>Service Number:</strong> {service_number}</li><li><strong>Invoice Date:</strong> {invoice_date}</li><li><strong>Total Amount:</strong> {company_currency} {total_amount}</li><li><strong>Status:</strong> {status}</li></ul><p>If you have any questions or require further clarification, please feel free to reach out to us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Thank you for choosing {company_name}. We look forward to serving you again!</p></blockquote>
                ',
                'sms_message' => 'Dear {client_name},
                                    your invoice #{invoice_number} for service #{service_number} amounting to {company_currency}{total_amount} has been generated. Status: {status}.',
            ],
            'payment_create' => [
                'module' => 'payment_create',
                'name' => 'New Payment',
                'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{invoice_number}', '{total_amount}', '{due_amount}', '{paid_amount}', '{status}'],
                'subject' => 'Payment Confirmation - Invoice #{invoice_number}',
                'templete' => '
                    <p><strong>Dear {client_name},</strong></p><p>&nbsp;</p><blockquote><p>We are pleased to inform you that we have received your payment. Below are the payment details:</p><ul><li><strong>Invoice Number:</strong> {invoice_number}</li><li><strong>Total Amount:</strong> {company_currency} {total_amount}</li><li><strong>Paid Amount:</strong> {company_currency} {paid_amount}</li><li><strong>Due Amount:</strong> {company_currency} {due_amount}</li><li><strong>Status:</strong> {status}</li></ul><p>If you have any questions or need further clarification, please do not hesitate to contact us at <a href="mailto:{company_email}">{company_email}</a> or call us at {company_phone_number}.</p><p>&nbsp;</p><p>Thank you for your prompt payment and for choosing {company_name}. We look forward to serving you again in the future!</p></blockquote>
                ',
                'sms_message' => 'Dear {client_name}, we have received your payment for invoice #{invoice_number}.
                                    Paid: {company_currency}{paid_amount},
                                    Due: {company_currency}{due_amount}.
                                    Status: {status}',
            ],
        ];
    }
}

if (!function_exists('defaultTemplate')) {
    function defaultTemplate($id)
    {
        $templateData = defaultTemplateList();

        // Store all created templates if needed
        $createdTemplates = [];

        foreach ($templateData as $key => $value) {
            $template = new Notification();
            $template->module = $value['module'];
            $template->name = $value['name'];
            $template->subject = $value['subject'];
            $template->message = $value['templete'];
            $template->short_code = json_encode($value['short_code']);
            $template->enabled_email = 0;
            $template->parent_id = $id; // Associate with the provided ID
            if (!empty($value['sms_message'])) {
                $template->sms_message = $value['sms_message'];
            }
            $template->enabled_sms = 0;
            $template->save();

            $createdTemplates[] = $template; // Collect all created templates
        }

        // Return all created templates if needed
        return $createdTemplates;
    }
}


if (!function_exists('defaultSMSTemplate')) {
    function defaultSMSTemplate()
    {
        $templateData = defaultTemplateList();

        // Store all created templates if needed
        $createdTemplates = [];

        foreach ($templateData as $key => $value) {
            $Users = User::where('type', 'owner')->get();
            foreach ($Users as $User) {
                $template = Notification::where('module', $value['module'])->where('parent_id', $User->id)->first();
                if (empty($template)) {
                    $template = new Notification();
                    $template->module = $value['module'];
                    $template->name = $value['name'];
                    $template->subject = $value['subject'];
                    $template->message = $value['templete'];
                    $template->short_code = json_encode($value['short_code']);
                    $template->enabled_email = 0;
                    $template->enabled_sms = 0;
                    $template->parent_id = $User->id;
                    $template->save();
                }
            }
            Notification::where('module', $value['module'])->whereNull('sms_message')->update(['sms_message' => $value['sms_message'], 'enabled_sms' => 0]);
            $createdTemplates[] = $value;
        }

        // Return all created templates if needed
        return $createdTemplates;
    }
}

if (!function_exists('MessageReplace')) {
    function MessageReplace($notification, $id = 0)
    {
        $return['subject'] = $notification->subject;
        $return['message'] = $notification->message;
        $return['sms_message'] = $notification->sms_message;
        if (!empty($notification->password)) {
            $notification['password'] = $notification->password;
        }
        $settings = settings();
        if (!empty($notification)) {
            $search = [];
            $replace = [];
            if ($notification->module == 'user_create') {
                $user = User::find($id);
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_user_name}', '{app_link}', '{username}', '{password}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $user->name, env('APP_URL'), $user->email, $notification['password']];
            }
            if ($notification->module == 'employee_create') {
                $user = User::find($id);
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_employee_name}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $user->name];
            }
            if ($notification->module == 'client_create') {
                $user = User::find($id);
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_client_name}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $user->name];
            }
            if ($notification->module == 'vehicle_create') {
                $vehicle = Vehicle::find($id);
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{vehicle_color}', '{license_plate}', '{engine_type}', '{fuel_type}', '{mileage}', '{last_service_date}', '{next_service_date}', '{insurance_details}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $vehicle->clients->name, vehiclePrefix() . $vehicle->vehicle_id, $vehicle->types->type, $vehicle->brand_name, $vehicle->model, $vehicle->color, $vehicle->license_plate, $vehicle->engine_type, $vehicle->fuel_type, $vehicle->mileage, dateFormat($vehicle->last_service_date), dateFormat($vehicle->next_service_due_date), $vehicle->insurance_details];
            }
            if ($notification->module == 'service_create') {
                $service = Service::find($id);
                $vehicle = Vehicle::find($service->vehicle);
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{license_plate}', '{engine_type}', '{fuel_type}', '{last_service_date}', '{employee_name}', '{employee_email}', '{employee_phone_number}', '{status}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $service->clients->name, vehiclePrefix() . $vehicle->vehicle_id, $vehicle->types->type, $vehicle->brand_name, $vehicle->model, $vehicle->license_plate, $vehicle->engine_type, $vehicle->fuel_type, dateFormat($vehicle->last_service_date), $service->assigns->name, $service->assigns->email, $service->assigns->phone_number, $service->status];
            }
            if ($notification->module == 'service_assign') {
                $service = Service::find($id);
                $vehicle = Vehicle::find($service->vehicle);
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{employee_name}', '{vehicle_number}', '{vehicle_type}', '{vehicle_brand}', '{vehicle_model}', '{license_plate}', '{engine_type}', '{fuel_type}', '{last_service_date}', '{client_name}', '{client_email}', '{client_phone_number}', '{status}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $service->assigns->name, vehiclePrefix() . $vehicle->vehicle_id, $vehicle->types->type, $vehicle->brand_name, $vehicle->model, $vehicle->license_plate, $vehicle->engine_type, $vehicle->fuel_type, dateFormat($vehicle->last_service_date), $service->clients->name, $service->clients->email, $service->clients->phone_number, $service->status];
            }
            if ($notification->module == 'invoice_create') {
                $invoice = Invoice::find($id);
                if ($invoice->status == 0) {
                    $status = 'Unpaid';
                }
                if ($invoice->status == 2) {
                    $status = 'Paid';
                }

                $amount = InvoiceItem::where('invoice_id', $invoice->id)->sum('amount');
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{invoice_number}', '{service_number}', '{invoice_date}', '{total_amount}', '{status}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $invoice->clients->name, invoicePrefix() . $invoice->invoice_id, servicePrefix() . $invoice->services->service_id, dateFormat($invoice->invoice_date), $amount, $status];
            }
            if ($notification->module == 'payment_create') {
                $invoice = Invoice::find($id);
                $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{client_name}', '{invoice_number}', '{total_amount}', '{due_amount}', '{paid_amount}', '{status}'];
                $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $invoice->clients->name, invoicePrefix() . $invoice->invoice_id, $invoice->getInvoiceAllTotalAmount(), $invoice->getInvoiceTotalDueAmount(), $invoice->getInvoiceAllTotalAmount() - $invoice->getInvoiceTotalDueAmount(), $notification->status];
            }


            $return['subject'] = str_replace($search, $replace, $notification->subject);
            $return['message'] = str_replace($search, $replace, $notification->message);
            $return['sms_message'] = str_replace($search, $replace, $notification->sms_message);
        }

        return $return;
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail($to, $datas)
    {
        $datas['settings'] = settings();
        try {
            emailSettings(parentId());
            Mail::to($to)->send(new TestMail($datas));
            return [
                'status' => 'success',
                'message' => __('Email successfully sent'),
            ];
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return [
                'status' => 'error',
                'message' => __('We noticed that the email settings have not been configured for this system. As a result, email-related functionalities may not work as expected. please add valide email smtp details first.')
            ];
        }
    }
}


if (!function_exists('commonEmailSend')) {
    function commonEmailSend($to, $datas)
    {
        $datas['settings'] = settings();
        try {
            if (Auth::check()) {
                if ($datas['module'] == 'owner_create') {
                    emailSettings(1);
                } else {
                    emailSettings(parentId());
                }
            } else {
                emailSettings($datas['parent_id']);
            }
            Mail::to($to)->send(new Common($datas));
            return [
                'status' => 'success',
                'message' => __('Email successfully sent'),
            ];
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return [
                'status' => 'error',
                'message' => __('We noticed that the email settings have not been configured for this system. As a result, email-related functionalities may not work as expected. please add valide email smtp details first.')
            ];
        }
    }
}


if (!function_exists('emailSettings')) {
    function emailSettings($id)
    {
        $settingData = DB::table('settings')
            ->where('type', 'smtp')
            ->where('parent_id', $id)
            ->get();

        $result = [
            'FROM_EMAIL' => "",
            'FROM_NAME' => "",
            'SERVER_DRIVER' => "",
            'SERVER_HOST' => "",
            'SERVER_PORT' => "",
            'SERVER_USERNAME' => "",
            'SERVER_PASSWORD' => "",
            'SERVER_ENCRYPTION' => "",
        ];

        foreach ($settingData as $setting) {
            $result[$setting->name] = $setting->value;
        }

        // Apply settings dynamically
        config([
            'mail.default' => $result['SERVER_DRIVER'] ?? '',
            'mail.mailers.smtp.host' => $result['SERVER_HOST'] ?? '',
            'mail.mailers.smtp.port' => $result['SERVER_PORT'] ?? '',
            'mail.mailers.smtp.encryption' => $result['SERVER_ENCRYPTION'] ?? '',
            'mail.mailers.smtp.username' => $result['SERVER_USERNAME'] ?? '',
            'mail.mailers.smtp.password' => $result['SERVER_PASSWORD'] ?? '',
            'mail.from.name' => $result['FROM_NAME'] ?? '',
            'mail.from.address' => $result['FROM_EMAIL'] ?? '',
        ]);
        return $result;
    }
}


if (!function_exists('sendEmailVerification')) {
    function sendEmailVerification($to, $data)
    {
        $data['settings'] = emailSettings(1);
        try {
            Mail::to($to)->send(new EmailVerification($data));

            return [
                'status' => 'success',
                'message' => __('Email successfully sent'),
            ];
        } catch (\Exception $e) {
            Log::error('Email Sending Failed: ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => __('We noticed that the email settings have not been configured for this system. As a result, email-related functionalities may not work as expected. please contact the administrator to resolve this issue.')
            ];
            return redirect()->back()->with('error', __(''));
        }
    }
}


if (!function_exists('RoleName')) {
    function RoleName($permission_id = '0')
    {
        $retuen = '';
        $role_id_array = DB::table('role_has_permissions')->where('permission_id', $permission_id)->pluck('role_id');
        if (!empty($role_id_array)) {
            $role_id_array = DB::table('roles')->whereIn('id', $role_id_array)->pluck('name')->toArray();
            $retuen = implode(', ', $role_id_array);
        }

        return $retuen;
    }
}

if (!function_exists('HomePageSection')) {
    function HomePageSection()
    {
        $retuen = [
            [
                'title' => 'Header Menu',
                'section' => 'Section 0',
                'content' => '',
                'content_value' => '{"name":"Header Menu","menu_pages":["1","2"]}',
            ],
            [
                'title' => 'Banner',
                'section' => 'Section 1',
                'content' => '',
                'content_value' => '{"name":"Banner","section_enabled":"active","title":"Service Hub - Vehicle Repair Center Management","sub_title":"Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops. It provides an integrated solution to effectively manage day-to-day operations, enhance productivity, and improve customer satisfaction.","btn_name":"Get Started","btn_link":"#","section_footer_text":"Manage your business efficiently with our all-in-one solution designed for performance, security, and scalability.","section_footer_image":{},"section_main_image":{},"section_footer_image_path":"upload\/homepage\/banner_2.png","section_main_image_path":"upload\/homepage\/banner_1.png","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',

            ],
            [
                'title' => 'OverView',
                'section' => 'Section 2',
                'content' => '',
                'content_value' => '{"name":"OverView","section_enabled":"active","Box1_title":"Customers","Box1_number":"500+","Box2_title":"Subscription Plan","Box2_number":"4+","Box3_title":"Language","Box3_number":"11+","box1_number_image":{},"box2_number_image":{},"box3_number_image":{},"section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"upload\/homepage\/OverView_1.svg","box_image_2_path":"upload\/homepage\/OverView_2.svg","box_image_3_path":"upload\/homepage\/OverView_3.svg","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',

            ],
            [
                'title' => 'AboutUs',
                'section' => 'Section 3',
                'content' => '',
                'content_value' => '{"name":"AboutUs","section_enabled":"active","Box1_title":"Empower Your Business to Thrive with Us","Box1_info":"Unlock growth, streamline operations, and achieve success with our innovative solutions.","Box1_list":["Simplify and automate your business processes for maximum efficiency.","Receive tailored strategies to meet business needs and unlock potential.","Grow confidently with flexible solutions that adapt to your business needs.","Make smarter decisions with real-time analytics and performance tracking.","Rely on 24\/7 expert assistance to keep your business running smoothly."],"Box2_title":"Eliminate Paperwork, Elevate Productivity","Box2_info":"Simplify your operations with seamless digital solutions and focus on what truly matters.","Box2_list":["Replace manual paperwork with automated workflows.","Secure cloud storage lets you manage documents on the go.","Streamlined processes save time and reduce errors.","Keep your information safe with encrypted storage.","Reduce printing, storage, and administrative expenses.","Go green by minimizing paper use and waste."],"section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"upload\/homepage\/img-customize-1.svg","Box2_image_path":"upload\/homepage\/img-customize-2.svg","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}'

            ],
            [
                'title' => 'Offer',
                'section' => 'Section 4',
                'content' => '',
                'content_value' => '{"name":"Offer","section_enabled":"active","Sec4_title":"What Our Software Offers","Sec4_info":"Our software provides powerful, scalable solutions designed to streamline your business operations.","Sec4_box1_title":"User-Friendly Interface","Sec4_box1_enabled":"active","Sec4_box1_info":"Simplify operations with an intuitive and easy-to-use platform.","Sec4_box2_title":"End-to-End Automation","Sec4_box2_enabled":"active","Sec4_box2_info":"Automate repetitive tasks to save time and increase efficiency.","Sec4_box3_title":"Customizable Solutions","Sec4_box3_enabled":"active","Sec4_box3_info":"Tailor features to fit your unique business needs and workflows.","Sec4_box4_title":"Scalable Features","Sec4_box4_enabled":"active","Sec4_box4_info":"Grow your business with flexible solutions that scale with you.","Sec4_box5_title":"Enhanced Security","Sec4_box5_enabled":"active","Sec4_box5_info":"Protect your data with advanced encryption and security protocols.","Sec4_box6_title":"Real-Time Analytics","Sec4_box6_enabled":"active","Sec4_box6_info":"Gain actionable insights with live data tracking and reporting.","Sec4_box1_image":{},"Sec4_box2_image":{},"Sec4_box3_image":{},"Sec4_box4_image":{},"Sec4_box5_image":{},"Sec4_box6_image":{},"section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"upload\/homepage\/offers_1.svg","Sec4_box2_image_path":"upload\/homepage\/offers_2.svg","Sec4_box3_image_path":"upload\/homepage\/offers_3.svg","Sec4_box4_image_path":"upload\/homepage\/offers_4.svg","Sec4_box5_image_path":"upload\/homepage\/offers_5.svg","Sec4_box6_image_path":"upload\/homepage\/offers_6.svg","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',

            ],
            [
                'title' => 'Pricing',
                'section' => 'Section 5',
                'content' => '',
                'content_value' => '{"name":"Pricing","section_enabled":"active","Sec5_title":"Flexible Pricing","Sec5_info":"Get started for free, upgrade later in our application.","section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',
            ],
            [
                'title' => 'Core Features',
                'section' => 'Section 6',
                'content' => '',
                'content_value' => '{"name":"Core Features","section_enabled":"active","Sec6_title":"Core Features","Sec6_info":"Core Modules For Your Business","Sec6_Box_title":["Dashboard","Subscription","Items / Parts","Invoice Details","Expense"],"Sec6_Box_subtitle":["Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops.","Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops.","Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops.","Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops.","Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops."],"Sec6_box_image":[{},{},{},{},{},{}],"section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec6_box0_image_path":"upload\/homepage\/1.png","Sec6_box1_image_path":"upload\/homepage\/2.png","Sec6_box2_image_path":"upload\/homepage\/3.png","Sec6_box3_image_path":"upload\/homepage\/4.png","Sec6_box4_image_path":"upload\/homepage\/5.png","Sec6_box5_image_path":"upload\/homepage\/6.png","Sec6_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',

            ],
            [
                'title' => 'Testimonials',
                'section' => 'Section 7',
                'content' => '',
                'content_value' => '{"name":"Testimonials","section_enabled":"active","Sec7_title":"What Our Customers Say About Us","Sec7_info":"We\u2019re proud of the impact our software has had on businesses just like yours. Hear directly from our customers about how our solutions have made a difference in their day-to-day operations","Sec7_box1_name":"Lenore Becker","Sec7_box1_tag":null,"Sec7_box1_Enabled":"active","Sec7_box1_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Quisque ut nisi. Nulla porta dolor. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc.","Sec7_box2_name":"Damian Morales","Sec7_box2_tag":"New","Sec7_box2_Enabled":"active","Sec7_box2_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Vestibulum rutrum.","Sec7_box3_name":"Oleg Lucas","Sec7_box3_tag":null,"Sec7_box3_Enabled":"active","Sec7_box3_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Quisque ut nisi. Nulla porta dolor. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc.","Sec7_box4_name":"Jerome Mccoy","Sec7_box4_tag":null,"Sec7_box4_Enabled":"active","Sec7_box4_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Quisque ut nisi. Nulla porta dolor. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc.","Sec7_box5_name":"Rafael Carver","Sec7_box5_tag":null,"Sec7_box5_Enabled":"active","Sec7_box5_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend.","Sec7_box6_name":"Edan Rodriguez","Sec7_box6_tag":null,"Sec7_box6_Enabled":"active","Sec7_box6_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Quisque ut nisi. Nulla porta dolor. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc.","Sec7_box7_name":"Kalia Middleton","Sec7_box7_tag":null,"Sec7_box7_Enabled":"active","Sec7_box7_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Vestibulum rutrum, mi nec elementum.","Sec7_box8_name":"Zenaida Chandler","Sec7_box8_tag":null,"Sec7_box8_Enabled":"active","Sec7_box8_review":"Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Quisque ut nisi. Nulla porta dolor. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc.","Sec7_box1_image":{},"Sec7_box2_image":{},"Sec7_box3_image":{},"Sec7_box4_image":{},"Sec7_box5_image":{},"Sec7_box6_image":{},"Sec7_box7_image":{},"Sec7_box8_image":{},"section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"upload\/homepage\/review_1.png","Sec7_box2_image_path":"upload\/homepage\/review_2.png","Sec7_box3_image_path":"upload\/homepage\/review_3.png","Sec7_box4_image_path":"upload\/homepage\/review_4.png","Sec7_box5_image_path":"upload\/homepage\/review_5.png","Sec7_box6_image_path":"upload\/homepage\/review_6.png","Sec7_box7_image_path":"upload\/homepage\/review_7.png","Sec7_box8_image_path":"upload\/homepage\/review_8.png"}',

            ],
            [
                'title' => 'Choose US',
                'section' => 'Section 8',
                'content' => '',
                'content_value' => '{"name":"Choose US","section_enabled":"active","Sec8_title":"Reason to Choose US","Sec8_box1_info":"Proven Expertise","Sec8_box2_info":"Customizable Solutions","Sec8_box3_info":"Seamless Integration","Sec8_box4_info":"Exceptional Support","Sec8_box5_info":"Scalable and Future-Proof","Sec8_box6_info":"Security You Can Trust","Sec8_box7_info":"User-Friendly Interface","Sec8_box8_info":"Innovation at Its Core","section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',

            ],
            [
                'title' => 'FAQ',
                'section' => 'Section 9',
                'content' => '',
                'content_value' => '{"name":"FAQ","section_enabled":"active","Sec9_title":"Frequently Asked Questions (FAQ)","Sec9_info":"Please refer the Frequently ask question for your quick help","section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',

            ],
            [
                'title' => 'AboutUS - Footer',
                'section' => 'Section 10',
                'content' => '',
                'content_value' => '{"name":"AboutUS - Footer","section_enabled":"active","Sec10_title":"About Service Hub","Sec10_info":"Service Hub Management System is a robust software platform tailored to the needs of automotive repair shops, garages, and workshops. It provides an integrated solution to effectively manage day-to-day operations, enhance productivity, and improve customer satisfaction.","section_footer_image_path":"","section_main_image_path":"","box_image_1_path":"","box_image_2_path":"","box_image_3_path":"","Box1_image_path":"","Box2_image_path":"","Sec4_box1_image_path":"","Sec4_box2_image_path":"","Sec4_box3_image_path":"","Sec4_box4_image_path":"","Sec4_box5_image_path":"","Sec4_box6_image_path":"","Sec7_box1_image_path":"","Sec7_box2_image_path":"","Sec7_box3_image_path":"","Sec7_box4_image_path":"","Sec7_box5_image_path":"","Sec7_box6_image_path":"","Sec7_box7_image_path":"","Sec7_box8_image_path":""}',

            ],
        ];

        foreach ($retuen as $key => $value) {
            $HomePage = new HomePage();
            $HomePage->title = $value['title'];
            $HomePage->content = $value['content'];
            $HomePage->section = $value['section'];
            if (!empty($value['content_value'])) {
                $HomePage->content_value = $value['content_value'];
            }
            $HomePage->enabled = 1;
            $HomePage->parent_id = 1;
            $HomePage->save();
        }
        return '';
    }
}

if (!function_exists('CustomPage')) {
    function CustomPage()
    {
        $retuen = [
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy_policy',
                'content' => "<h3><strong>1. Information We Collect</strong></h3><p>We may collect the following types of information from you:</p><h4><strong>a. Personal Information</strong></h4><ul><li>Name, email address, phone number, and other contact details.</li><li>Payment information (if applicable).</li></ul><h4><strong>b. Non-Personal Information</strong></h4><ul><li>Browser type, operating system, and device information.</li><li>Usage data, including pages visited, time spent, and other analytical data.</li></ul><h4><strong>c. Information You Provide</strong></h4><ul><li>Information you voluntarily provide when contacting us, signing up, or completing forms.</li></ul><h4><strong>d. Cookies and Tracking Technologies</strong></h4><ul><li>We use cookies, web beacons, and other tracking tools to enhance your experience and analyze usage patterns.</li></ul><h3><strong>2. How We Use Your Information</strong></h3><p>We use the information collected for the following purposes:</p><ul><li>To provide, maintain, and improve our Services.</li><li>To process transactions and send you confirmations.</li><li>To communicate with you, including responding to inquiries or providing updates.</li><li>To personalize your experience and deliver tailored content.</li><li>To comply with legal obligations and protect against fraud or misuse.</li></ul><h3><strong>3. How We Share Your Information</strong></h3><p>We do not sell your personal information. However, we may share your information with:</p><ul><li><strong>Service Providers:</strong> Third-party vendors who assist in providing our Services.</li><li><strong>Legal Authorities:</strong> When required to comply with legal obligations or protect our rights.</li><li><strong>Business Transfers:</strong> In the event of a merger, acquisition, or sale of assets, your information may be transferred.</li></ul><h3><strong>4. Data Security</strong></h3><p>We implement appropriate technical and organizational measures to protect your data against unauthorized access, disclosure, alteration, or destruction. However, no method of transmission or storage is 100% secure, and we cannot guarantee absolute security.</p><h3><strong>5. Your Rights</strong></h3><p>You have the right to:</p><ul><li>Access, correct, or delete your personal data.</li><li>Opt-out of certain data processing activities, including marketing communications.</li><li>Withdraw consent where processing is based on consent.</li></ul><p>To exercise your rights, please contact us at [contact email].</p><h3><strong>6. Third-Party Links</strong></h3><p>Our Services may contain links to third-party websites. We are not responsible for the privacy practices or content of these websites. Please review their privacy policies before engaging with them.</p><h3><strong>7. Children's Privacy</strong></h3><p>Our Services are not intended for children under the age of [13/16], and we do not knowingly collect personal information from them. If we become aware that a child has provided us with personal data, we will take steps to delete it.</p><h3><strong>8. Changes to This Privacy Policy</strong></h3><p>We may update this Privacy Policy from time to time. Any changes will be posted on this page with a revised 'Last Updated' date. Your continued use of the Services after such changes constitutes your acceptance of the new terms.</p><h3>&nbsp;</h3>"
            ],
            [
                'title' => 'Terms & Conditions',
                'slug' => 'terms_conditions',
                'content' => "<h3><strong>1. Acceptance of Terms</strong></h3><p>By using our Services, you confirm that you are at least [18 years old or the legal age in your jurisdiction] and capable of entering into a binding agreement. If you are using our Services on behalf of an organization, you represent that you have the authority to bind that organization to these Terms.</p><h3><strong>2. Use of Services</strong></h3><p>You agree to use our Services only for lawful purposes and in accordance with these Terms. You must not:</p><ul><li>Violate any applicable laws or regulations.</li><li>Use our Services in a manner that could harm, disable, overburden, or impair them.</li><li>Attempt to gain unauthorized access to our systems or networks.</li><li>Transmit any harmful code, viruses, or malicious software.</li></ul><h3><strong>3. User Accounts</strong></h3><p>If you create an account with us, you are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account or breach of security.</p><h3><strong>4. Intellectual Property</strong></h3><p>All content, trademarks, logos, and intellectual property associated with our Services are owned by [Your Company Name] or our licensors. You are granted a limited, non-exclusive, non-transferable license to access and use the Services for personal or authorized business purposes. Any unauthorized use, reproduction, or distribution is prohibited.</p><h3><strong>5. Payment and Billing</strong> (if applicable)</h3><p>If our Services involve payments:</p><ul><li>All fees are due at the time of purchase unless otherwise agreed.</li><li>We reserve the right to change pricing or introduce new fees with prior notice.</li><li>Refunds, if applicable, will be handled according to our [Refund Policy].</li></ul><h3><strong>6. Termination of Services</strong></h3><p>We reserve the right to suspend or terminate your access to our Services at our discretion, without prior notice, if:</p><ul><li>You breach these Terms.</li><li>We are required to do so by law.</li><li>Our Services are discontinued or altered.</li></ul><h3><strong>7. Limitation of Liability</strong></h3><p>To the fullest extent permitted by law:</p><ul><li>[Your Company Name] and its affiliates shall not be liable for any direct, indirect, incidental, or consequential damages resulting from your use of our Services.</li><li>Our liability is limited to the amount you paid, if any, for accessing our Services.</li></ul><h3><strong>8. Indemnification</strong></h3><p>You agree to indemnify and hold [Your Company Name], its affiliates, employees, and partners harmless from any claims, liabilities, damages, losses, or expenses arising from your use of the Services or violation of these Terms.</p><h3><strong>9. Modifications to Terms</strong></h3><p>We may update these Terms from time to time. Any changes will be effective immediately upon posting, and your continued use of the Services constitutes your acceptance of the revised Terms.</p>"
            ],
        ];
        foreach ($retuen as $key => $value) {
            $Page = new Page();
            $Page->title = $value['title'];
            $Page->slug = $value['slug'];
            $Page->content = $value['content'];
            $Page->enabled = 1;
            $Page->parent_id = 1;
            $Page->save();
        }


        $FAQ_retuen = [
            [
                'question' => 'What features does your software offer?',
                'description' => 'Our software provides a range of features including automation tools, real-time analytics, cloud-based access, secure data storage, seamless integrations, and customizable solutions tailored to your business needs.',
            ],
            [
                'question' => 'Is your software easy to use?',
                'description' => 'Yes! Our platform is designed to be user-friendly and intuitive, so your team can get started quickly without a steep learning curve.',
            ],
            [
                'question' => 'Can I integrate your software with my existing systems?',
                'description' => 'Absolutely! Our software is built to easily integrate with your current tools and systems, making the transition seamless and efficient.',
            ],
            [
                'question' => 'Is customer support available?',
                'description' => 'Yes! We offer 24/7 customer support. Our dedicated team is ready to assist you with any questions or issues you may have.',
            ],
            [
                'question' => 'Is my data secure with your software?',
                'description' => 'Yes. We use advanced encryption and data protection protocols to ensure your data is secure and private at all times.',
            ],
            [
                'question' => 'Can I customize the software to fit my business needs?',
                'description' => 'Yes! Our software is highly customizable to adapt to your unique workflows and requirements.',
            ],
            [
                'question' => 'What types of businesses can benefit from your software?',
                'description' => 'Our solutions are suitable for a wide range of industries, including retail, healthcare, finance, marketing, and more. We tailor our offerings to meet the specific needs of each business.',
            ],

            [
                'question' => 'Is there a free trial available?',
                'description' => 'Yes! We offer a free trial so you can explore the features and capabilities of our software before committing.',
            ],

            [
                'question' => 'Do I need technical expertise to use the software?',
                'description' => 'Not at all. Our software is designed for users of all skill levels. Plus, our support team is available to guide you through any setup or usage questions.',
            ],

            [
                'question' => 'How often is the software updated?',
                'description' => 'We regularly release updates to improve features, security, and overall performance, ensuring that you always have access to the latest technology.',
            ],
        ];
        foreach ($FAQ_retuen as $key => $FAQ_value) {
            $FAQs = new FAQ();
            $FAQs->question = $FAQ_value['question'];
            $FAQs->description = $FAQ_value['description'];
            $FAQs->enabled = 1;
            $FAQs->parent_id = 1;
            $FAQs->save();
        }
        return '';
    }
}
if (!function_exists('DefaultCustomPage')) {
    function DefaultCustomPage()
    {
        $return = Page::where('enabled', 1)->whereIn('id', [1, 2])->get();
        return $return;
    }
}

if (!function_exists('DefaultBankTransferPayment')) {
    function DefaultBankTransferPayment()
    {
        $bankArray = [
            'bank_transfer_payment' => 'on',
            'bank_name' => 'Bank of America',
            'bank_holder_name' => 'SmartWeb Infotech',
            'bank_account_number' => '4242 4242 4242 4242',
            'bank_ifsc_code' => 'BOA45678',
            'bank_other_details' => '',
        ];

        foreach ($bankArray as $key => $val) {
            \DB::insert(
                'insert into settings (`value`, `name`, `type`,`parent_id`) values (?, ?, ?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                [
                    $val,
                    $key,
                    'payment',
                    1,
                ]
            );
        }

        return '';
    }
}

if (!function_exists('QrCode2FA')) {
    function QrCode2FA()
    {
        $user = Auth::user();

        $google2fa = new Google2FA();

        // generate a secret
        $secret = $google2fa->generateSecretKey();

        // generate the QR code, indicating the address
        // of the web application and the user name
        // or email in this case
        $company = env('APP_NAME');
        if ($user->type != 'super admin') {
            $company = isset(settings()['company_name']) && !empty(settings()['company_name']) ? settings()['company_name'] : $company;
        }

        $qr_code = $google2fa->getQRCodeInline(
            $company,
            $user->email,
            $secret
        );

        // store the current secret in the session
        // will be used when we enable 2FA (see below)
        session(["2fa_secret" => $secret]);

        return $qr_code;
    }
}

if (!function_exists('authPage')) {
    function authPage($id)
    {

        $templateData = [
            'title' => [
                "Secure Access, Seamless Experience.",
                "Your Trusted Gateway to Digital Security.",
                "Fast, Safe & Effortless Login."
            ],
            'description' => [
                "Securely access your account with ease. Whether you're logging in, signing up, or resetting your password, we ensure a seamless and protected experience. Your data, your security, our priority.",
                "Fast, secure, and hassle-free authentication. Sign in with confidence and experience a seamless way to access your account—because your security matters.",
                "A seamless and secure way to access your account. Whether you're logging in, signing up, or recovering your password, we ensure your data stays protected at every step."
            ],
        ];

        $authPage = new AuthPage();
        $authPage->title = json_encode($templateData['title']);
        $authPage->description = json_encode($templateData['description']);
        $authPage->section = 1;
        $authPage->image = 'upload/images/auth_page.svg';
        $authPage->parent_id = $id;
        $authPage->save();

        $createdTemplates[] = $authPage;

        return $createdTemplates;
    }
}

if (!function_exists('NewPermission')) {
    function NewPermission()
    {

        $permissions = [

            ['name' => 'manage account settings', 'guard_name' => 'web', 'roles' => ['client', 'employee']],
            ['name' => 'manage password settings', 'guard_name' => 'web', 'roles' => ['client', 'employee']],
            ['name' => 'manage 2FA settings', 'guard_name' => 'web', 'roles' => ['client', 'employee']],
            ['name' => 'manage quotation', 'guard_name' => 'web', 'roles' => ['owner', 'client', 'manager', 'employee']],
            ['name' => 'create quotation', 'guard_name' => 'web', 'roles' => ['owner', 'manager', 'client', 'employee']],
            ['name' => 'edit quotation', 'guard_name' => 'web', 'roles' => ['owner', 'manager', 'client', 'employee']],
            ['name' => 'delete quotation', 'guard_name' => 'web', 'roles' => ['owner', 'manager']],
            ['name' => 'show quotation', 'guard_name' => 'web', 'roles' => ['owner', 'client', 'manager', 'employee']],
            ['name' => 'manage invoice', 'guard_name' => 'web', 'roles' => ['owner', 'client', 'manager']],
            ['name' => 'show invoice', 'guard_name' => 'web', 'roles' => ['owner', 'client', 'manager']],

            ['name' => 'manage service report', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'manage income report', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'manage expense report', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'manage profile and loss report', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'manage service', 'guard_name' => 'web', 'roles' => ['employee', 'client']],
            ['name' => 'show service', 'guard_name' => 'web', 'roles' => ['employee', 'client']],

            ['name' => 'manage openai settings', 'guard_name' => 'web', 'roles' => ['owner', 'super admin']],
            ['name' => 'manage payment settings', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'manage n8n', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'create n8n', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'edit n8n', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'delete n8n', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'convert quotation', 'guard_name' => 'web', 'roles' => ['owner']],
            ['name' => 'manage item', 'guard_name' => 'web', 'roles' => ['employee']],
            ['name' => 'manage vehicle', 'guard_name' => 'web', 'roles' => ['client']],
            ['name' => 'show vehicle', 'guard_name' => 'web', 'roles' => ['client']],
            ['name' => 'create invoice payment', 'guard_name' => 'web', 'roles' => ['client']],
            ['name' => 'kanban service' , 'guard_name' => 'web' , 'roles' => ['owner']],
        ];

        if (!empty($permissions)) {
            foreach ($permissions as $permData) {
                // Create new Permission
                Permission::firstOrCreate([
                    'name' => $permData['name'],
                    'guard_name' => $permData['guard_name']
                ]);
            }

            $permissionsByRole = [];

            foreach ($permissions as $permData) {
                foreach ($permData['roles'] as $roleName) {
                    $permissionsByRole[$roleName][] = $permData['name'];
                }
            }

            foreach ($permissionsByRole as $roleName => $permNames) {
                $roles = Role::where('name', $roleName)->get();

                foreach ($roles as $role) {
                    // assign permissions to role
                    $role->givePermissionTo($permNames);
                }
            }
        }

        $removePermissions = [
            ['name' => 'create note', 'guard_name' => 'web', 'roles' => ['manager', 'client', 'employee']],
            ['name' => 'edit note', 'guard_name' => 'web', 'roles' => ['manager', 'client', 'employee']],
            ['name' => 'delete note', 'guard_name' => 'web', 'roles' => ['manager', 'client', 'employee']],
            ['name' => 'show note', 'guard_name' => 'web', 'roles' => ['manager', 'client', 'employee']],

        ];

        foreach ($removePermissions as $permData) {
            $permission = Permission::where('name', $permData['name'])
                ->where('guard_name', $permData['guard_name'])
                ->first();

            if ($permission) {
                foreach ($permData['roles'] as $roleName) {
                    $roles = Role::where('name', $roleName)->get();
                    foreach ($roles as $role) {
                        // remove permissions to role
                        $role->revokePermissionTo($permission);
                    }
                }
            }
        }

        defaultAiTemplate();
        defaultSMSTemplate();

        return true;
    }
}

if (!function_exists('triggerN8n')) {
    function triggerN8n(string $module, array $payload): bool
    {
        $webhook = N8n::where([
            'module' => $module,
            'status' => 1,
            'parent_id' => parentId(),
        ])->first();
        if (empty($webhook)) {
            return false;
        }
        try {
            $method = strtolower($webhook->method);

            $response = Http::asJson()
                ->timeout(10)
                ->$method($webhook->url, $payload);

            if ($response->failed()) {
                Log::error('N8n webhook failed', [
                    'module' => $module,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('N8n webhook exception', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
if (!function_exists('currentSubscription')) {
    function currentSubscription()
    {
        $ids = parentId();
        $authUser = User::find($ids);
        return [
            'subscription' => Subscription::find($authUser->subscription),
            'pricing_feature_settings' => getSettingsValByIdName(1, 'pricing_feature')
        ];
    }
}

if (!function_exists('defaultAiTemplate')) {
    function defaultAiTemplate()
    {
        $aiTemplateData = [
            [
                'title' => 'contact_subject',
                'template_prompt' => 'Generate a short, professional contact subject for "##subject##" in a ##communication_style## style.',
                'content_type' => 'contact',
                'field' => [
                    [
                        'label' => 'Contact Subject',
                        'placeholder' => 'Enter contact subject',
                        'type' => 'text',
                        'name' => 'subject'
                    ],
                ]
            ],
            [
                'title' => 'contact_description',
                'template_prompt' => 'Write a concise, professional description for this contact: ##subject##. using the communication style: ##communication_style##.',
                'content_type' => 'contact',
                'field' => [
                    [
                        'label' => 'Contact Description',
                        'placeholder' => 'Enter contact description',
                        'type' => 'textarea',
                        'name' => 'subject'
                    ],
                ]
            ],
            [
                'title' => 'noticeboard_title',
                'template_prompt' => 'Generate a concise, professional noticeboard title for "##noticeboard_title##" in a ##communication_style## style.',
                'content_type' => 'noticeboard',
                'field' => [
                    [
                        'label' => 'Noticeboard Title',
                        'placeholder' => 'Enter noticeboard title',
                        'type' => 'text',
                        'name' => 'noticeboard_title'
                    ],
                ],
            ],
            [
                'title' => 'noticeboard_description',
                'template_prompt' => 'Generate a concise, professional noticeboard description for "##noticeboard_description##" in a ##communication_style## style.',  // ✅
                'content_type' => 'noticeboard',
                'field' => [
                    [
                        'label' => 'Noticeboard Description',
                        'placeholder' => 'Enter noticeboard description',
                        'type' => 'textarea',
                        'name' => 'noticeboard_description'
                    ],
                ],
            ],
            [
                'title' => 'faq_question',
                'template_prompt' => 'Generate a clear, concise FAQ question for "##question##" in a ##communication_style## style.',
                'content_type' => 'faq',
                'field' => [
                    [
                        'label' => 'FAQ Question',
                        'placeholder' => 'Enter FAQ topic or rough question',
                        'type' => 'text',
                        'name' => 'question'
                    ],
                ],
            ],
            [
                'title' => 'faq_description',
                'template_prompt' => 'Generate a clear and helpful FAQ answer for "##question##" in a ##communication_style## style.',
                'content_type' => 'faq',
                'field' => [
                    [
                        'label' => 'FAQ Description',
                        'placeholder' => 'Enter FAQ description',
                        'type' => 'textarea',
                        'name' => 'question'
                    ],
                ],
            ],
            [
                'title' => 'page_name',
                'template_prompt' => 'Generate a clear, professional page name for "##page_name##" in a ##communication_style## style.',
                'content_type' => 'custom_page',
                'field' => [
                    [
                        'label' => 'Page Name',
                        'placeholder' => 'Enter page name or topic',
                        'type' => 'text',
                        'name' => 'page_name'
                    ],
                ],
            ],
            [
                'title' => 'page_content',
                'template_prompt' => 'Generate clear, engaging, and professional page content for "##content##" in a ##communication_style## style.',  // ✅
                'content_type' => 'custom_page',
                'field' => [
                    [
                        'label' => 'Page Content',
                        'placeholder' => 'Enter page content',
                        'type' => 'textarea',
                        'name' => 'content'
                    ],
                ],
            ],
            [
                'title' => 'subscription_title',
                'template_prompt' => 'Generate a catchy subscription name for "##subscription_title##" in a ##communication_style## tone.',
                'content_type' => 'subscription',
                'field' => [
                    [
                        'label' => 'Subscription Title',
                        'placeholder' => 'Enter subscription title',
                        'type' => 'text',
                        'name' => 'subscription_title'
                    ],
                ],
            ],
            [
                'title' => 'coupon_name',
                'template_prompt' => 'Generate a catchy, professional coupon name for "##coupon_name##" in a ##communication_style## style.',
                'content_type' => 'coupon',
                'field' => [

                    [
                        'label' => 'Coupon Name',
                        'placeholder' => 'Enter coupon name',
                        'type' => 'text',
                        'name' => 'coupon_name'
                    ],

                ],
            ],
            [
                'title' => 'coupon_code',
                'template_prompt' => 'Create a short, unique coupon code based on "##coupon_title##" using a ##communication_style## tone.',
                'content_type' => 'coupon',
                'field' => [
                    [
                        'label' => 'Coupon Title',
                        'placeholder' => 'e.g. Summer Sale, New User Discount',
                        'type' => 'text',
                        'name' => 'coupon_title'
                    ],
                ],
            ],
            [
                'title' => 'item_name',
                'template_prompt' => 'Generate a clear, professional, and standardized item name for a field management service system based on the following details: "##item_name##". The item belongs to the category "##item_category##". The item name should be concise, descriptive, and follow a professional ##communication_style## naming convention suitable for inventory or service management.',
                'content_type' => 'item',
                'field' => [
                    [
                        'label' => 'Item Description',
                        'placeholder' => 'Describe the item (e.g., function, material, or purpose)',
                        'type' => 'textarea',
                        'name' => 'item_name'
                    ],
                ],
            ],
            [
                'title' => 'item_code',
                'template_prompt' => 'Generate a unique and standardized item code for a field management service system based on the following details: "##item_code##". The code should follow a professional ##communication_style## format suitable for inventory tracking and service management.',
                'content_type' => 'item',
                'field' => [
                    [
                        'label' => 'Item Code Details',
                        'placeholder' => 'Describe the item to generate a code for (e.g., item type, usage)',
                        'type' => 'textarea',
                        'name' => 'item_code'
                    ],
                ],
            ],
            [
                'title' => 'item_note',
                'template_prompt' => 'Generate a clear and professional item note based on the following details: "##item_note##". The note should explain the item details, usage, and any relevant information in a professional ##communication_style## tone.',
                'content_type' => 'item',
                'field' => [
                    [

                        'label' => 'Item Note Details',
                        'placeholder' => 'Describe the item note or any relevant details about the item',
                        'type' => 'textarea',
                        'name' => 'item_note'
                    ],
                ],
            ],
            [
                'title' => 'insurance_detail',
                'template_prompt' => 'Generate a clear and professional insurance detail for a vehicle based on the following information: "##insurance_detail##". The detail should explain the insurance coverage, policy terms, vehicle information, and any relevant conditions in a professional ##communication_style## tone.',
                'content_type' => 'vehicle',
                'field' => [
                    [
                        'label' => 'Insurance Detail',
                        'placeholder' => 'Describe the vehicle insurance details (e.g., policy number, coverage, expiry date)',
                        'type' => 'textarea',
                        'name' => 'insurance_detail'
                    ],
                ],
            ],
            [
                'title' => 'vehicle_note',
                'template_prompt' => 'Generate a clear and professional vehicle note based on the following information: "##vehicle_note##". The note should explain the vehicle details, condition, usage, and any relevant information in a professional ##communication_style## tone.',
                'content_type' => 'vehicle',
                'field' => [
                    [
                        'label' => 'Vehicle Note Details',
                        'placeholder' => 'Describe the vehicle note or any relevant details about the vehicle',
                        'type' => 'textarea',
                        'name' => 'vehicle_note'
                    ],
                ],
            ],
            [
                'title' => 'vehicle_note',
                'template_prompt' => 'Generate a clear and professional vehicle note based on the following information: "##vehicle_note##". The note should explain the vehicle details, condition, usage, and any relevant information in a professional ##communication_style## tone.',
                'content_type' => 'vehicle',
                'field' => [
                    [
                        'label' => 'Vehicle Note Details',
                        'placeholder' => 'Describe the vehicle note or any relevant details about the vehicle',
                        'type' => 'textarea',
                        'name' => 'vehicle_note'
                    ],
                ],
            ],
            [
                'title' => 'warranty_information',
                'template_prompt' => 'Generate a clear and professional warranty information based on the following details: "##warranty_information##". The warranty information should explain the warranty terms, coverage, duration, and any relevant conditions in a professional ##communication_style## tone.',
                'content_type' => 'vehicle',
                'field' => [
                    [
                        'label' => 'Warranty Information Details',
                        'placeholder' => 'Describe the warranty details (e.g., product, duration, coverage)',
                        'type' => 'textarea',
                        'name' => 'warranty_information'
                    ],
                ],
            ],
            [
                'title' => 'service_note',
                'template_prompt' => 'Generate a clear and professional service note based on the following information: "##service_note##". The note should explain the service details, work performed, and any relevant information in a professional ##communication_style## tone.',
                'content_type' => 'service',
                'field' => [
                    [
                        'label' => 'Service Note Details',
                        'placeholder' => 'Describe the service note or any relevant details about the service performed',
                        'type' => 'textarea',
                        'name' => 'service_note'
                    ],
                ],
            ],

            [
                'title' => 'expense_note',
                'template_prompt' => 'Generate a clear and professional expense note based on the following information: "##expense_note##". The note should explain the purpose of the expense, where it was used, and any relevant details in a professional ##communication_style## tone.',
                'content_type' => 'expense',
                'field' => [
                    [
                        'label' => 'Expense Note Details',
                        'placeholder' => 'Describe the expense or reason for the expense',
                        'type' => 'textarea',
                        'name' => 'expense_note'
                    ],
                ],
            ],
            [
                'title' => 'vehicle_type',
                'template_prompt' => 'Generate a clear and professional vehicle type name based on the following information: "##vehicle_type##". The type should be accurate, recognizable, and follow a professional ##communication_style## tone.',
                'content_type' => 'vehicle_type',
                'field' => [
                    [
                        'label' => 'Vehicle Type Details',
                        'placeholder' => 'Describe the vehicle type (e.g., Sedan, SUV, Truck, Van)',
                        'type' => 'textarea',
                        'name' => 'vehicle_type'
                    ],
                ],
            ],
            [
                'title' => 'service_type',
                'template_prompt' => 'Generate a clear and professional service type name based on the following information: "##service_type##". The service type should be accurate, descriptive, and follow a professional ##communication_style## tone suitable for a field management service system.',
                'content_type' => 'service_type',
                'field' => [
                    [
                        'label' => 'Service Type Details',
                        'placeholder' => 'Describe the service type (e.g., Maintenance, Repair, Inspection)',
                        'type' => 'textarea',
                        'name' => 'service_type'
                    ],
                ],
            ],
            [
                'title' => 'vehicle_brand',
                'template_prompt' => 'Generate a clear and professional vehicle brand name based on the following information: "##vehicle_brand##". The brand name should be accurate, recognizable, and follow a professional ##communication_style## tone.',
                'content_type' => 'vehicle_brand',
                'field' => [
                    [
                        'label' => 'Vehicle Brand Details',
                        'placeholder' => 'Describe the vehicle brand (e.g., manufacturer, origin, type)',
                        'type' => 'textarea',
                        'name' => 'vehicle_brand'
                    ],
                ],
            ],

            [
                'title' => 'email_subject',
                'template_prompt' => 'Generate a short, professional, and engaging email subject based on the following topic: ##email_subject## in a ##communication_style## style.',
                'content_type' => 'notification',
                'field' => [
                    [
                        'label' => 'Email Subject',
                        'placeholder' => 'Enter the email topic or idea',
                        'type' => 'text',
                        'name' => 'email_subject'
                    ],
                ]
            ],
            [
                'title' => 'email_template_content',
                'template_prompt' => 'Write a clear, professional, and engaging email content based on the topic: ##email_template_content## in a ##communication_style## style.',
                'content_type' => 'notification',
                'field' => [
                    [
                        'label' => 'Email Content',
                        'placeholder' => 'Enter the main email content or details',
                        'type' => 'textarea',
                        'name' => 'email_template_content'
                    ],
                ]
            ],
            [
                'title' => 'sms_content',
                'template_prompt' => 'Write a concise, clear, and engaging SMS message based on the following topic: ##sms_content## in a ##communication_style## style.',
                'content_type' => 'notification',
                'field' => [
                    [
                        'label' => 'SMS Content',
                        'placeholder' => 'Enter SMS message or key points',
                        'type' => 'textarea',
                        'name' => 'sms_content'
                    ],
                ]
            ],
        ];
        $createdTemplates = [];

        foreach ($aiTemplateData as $value) {
            $exists = AiTemplate::where('title', $value['title'])->exists();

            if (!$exists) {
                $template = new AiTemplate();
                $template->title = $value['title'];
                $template->template_prompt = $value['template_prompt'];
                $template->content_type = $value['content_type'];
                $template->field = json_encode($value['field']);
                $template->parent_id = 1;
                $template->is_active = 1;
                $template->save();

                $createdTemplates[] = $template;
            }
        }

        return $createdTemplates;
    }
}
