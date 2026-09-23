<?php

use App\Http\Controllers\AiTemplateController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AuthPageController;
use App\Http\Controllers\N8nController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\NoticeBoardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FAQController;
use App\Http\Controllers\HomePageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VehicleBrandController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\VehicleQrController;
use App\Http\Controllers\VehiclePortalController;

Route::middleware(['auth', 'XSS'])->prefix('vehicle-qr')->name('vehicle-qr.')->group(function () {
    Route::get('/', [VehicleQrController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/print', [VehicleQrController::class, 'print'])->name('print');
    Route::post('/printed', [VehicleQrController::class, 'printed'])->name('printed');
    Route::post('/assign/{vehicle}', [VehicleQrController::class, 'assign'])->name('assign');
});

Route::prefix('q/{token}')->where(['token' => '[a-f0-9]{64}'])->middleware('throttle:60,1')
    ->withoutMiddleware(\App\Http\Middleware\Verify2FA::class)->name('vehicle-portal.')->group(function () {
        Route::get('/', [VehiclePortalController::class, 'show'])->name('show');
        Route::get('/invoice/{invoiceId}', [VehiclePortalController::class, 'invoice'])->whereNumber('invoiceId')->name('invoice');
    });



use App\Models\User;
use GuzzleHttp\Psr7\Query;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

require __DIR__ . '/auth.php';

Route::get('/', [HomeController::class, 'index'])->middleware(
    [

        'XSS',
    ]
);
Route::get('home', [HomeController::class, 'index'])->name('home')->middleware(
    [

        'XSS',
    ]
);
Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard')->middleware(
    [

        'XSS',
    ]
);

//-------------------------------User-------------------------------------------

Route::resource('users', UserController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);


Route::get('login/otp', [OTPController::class, 'show'])->name('otp.show')->middleware(
    [

        'XSS',
    ]
);
Route::post('login/otp', [OTPController::class, 'check'])->name('otp.check')->middleware(
    [

        'XSS',
    ]
);
Route::get('login/2fa/disable', [OTPController::class, 'disable'])->name('2fa.disable')->middleware(['XSS',]);

//-------------------------------Subscription-------------------------------------------

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::resource('subscriptions', SubscriptionController::class);
        Route::get('coupons/history', [CouponController::class, 'history'])->name('coupons.history');
        Route::delete('coupons/history/{id}/destroy', [CouponController::class, 'historyDestroy'])->name('coupons.history.destroy');
        Route::get('coupons/apply', [CouponController::class, 'apply'])->name('coupons.apply');
        Route::resource('coupons', CouponController::class);
        Route::get('subscription/transaction', [SubscriptionController::class, 'transaction'])->name('subscription.transaction');
    }
);

//-------------------------------Subscription Payment-------------------------------------------

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::post('subscription/{id}/stripe/payment', [SubscriptionController::class, 'stripePayment'])->name('subscription.stripe.payment');
    }
);
//-------------------------------Settings-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('settings', [SettingController::class, 'index'])->name('setting.index');

        Route::post('settings/account', [SettingController::class, 'accountData'])->name('setting.account');
        Route::delete('settings/account/delete', [SettingController::class, 'accountDelete'])->name('setting.account.delete');
        Route::post('settings/password', [SettingController::class, 'passwordData'])->name('setting.password');
        Route::post('settings/general', [SettingController::class, 'generalData'])->name('setting.general');
        Route::post('settings/smtp', [SettingController::class, 'smtpData'])->name('setting.smtp');
        Route::get('settings/smtp-test', [SettingController::class, 'smtpTest'])->name('setting.smtp.test');
        Route::post('settings/smtp-test', [SettingController::class, 'smtpTestMailSend'])->name('setting.smtp.testing');
        Route::post('settings/payment', [SettingController::class, 'paymentData'])->name('setting.payment');
        Route::post('settings/site-seo', [SettingController::class, 'siteSEOData'])->name('setting.site.seo');
        Route::post('settings/google-recaptcha', [SettingController::class, 'googleRecaptchaData'])->name('setting.google.recaptcha');
        Route::post('settings/company', [SettingController::class, 'companyData'])->name('setting.company');
        Route::post('settings/2fa', [SettingController::class, 'twofaEnable'])->name('setting.twofa.enable');

        Route::get('footer-setting', [SettingController::class, 'footerSetting'])->name('footerSetting');
        Route::post('settings/footer', [SettingController::class, 'footerData'])->name('setting.footer');

        Route::get('language/{lang}', [SettingController::class, 'lanquageChange'])->name('language.change');
        Route::post('theme/settings', [SettingController::class, 'themeSettings'])->name('theme.settings');


        Route::post('settings/twilio', [SettingController::class, 'twilio'])->name('setting.twilio');
        Route::post('openai/settings', [SettingController::class, 'openai'])->name('openai.settings');
    }
);


//-------------------------------Role & Permissions-------------------------------------------
Route::resource('permission', PermissionController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

Route::resource('role', RoleController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Note-------------------------------------------
Route::resource('note', NoticeBoardController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Contact-------------------------------------------
Route::resource('contact', ContactController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------logged History-------------------------------------------

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::get('logged/history', [UserController::class, 'loggedHistory'])->name('logged.history');
        Route::get('logged/{id}/history/show', [UserController::class, 'loggedHistoryShow'])->name('logged.history.show');
        Route::delete('logged/{id}/history', [UserController::class, 'loggedHistoryDestroy'])->name('logged.history.destroy');
    }
);


//-------------------------------Plan Payment-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::post('subscription/{id}/bank-transfer', [PaymentController::class, 'subscriptionBankTransfer'])->name('subscription.bank.transfer');
        Route::get('subscription/{id}/bank-transfer/action/{status}', [PaymentController::class, 'subscriptionBankTransferAction'])->name('subscription.bank.transfer.action');
        Route::post('subscription/{id}/paypal', [PaymentController::class, 'subscriptionPaypal'])->name('subscription.paypal');
        Route::get('subscription/{id}/paypal/{status}', [PaymentController::class, 'subscriptionPaypalStatus'])->name('subscription.paypal.status');
        Route::post('subscription/{id}/{user_id}/manual-assign-package', [PaymentController::class, 'subscriptionManualAssignPackage'])->name('subscription.manual_assign_package');
        Route::get('subscription/flutterwave/{sid}/{tx_ref}', [PaymentController::class, 'subscriptionFlutterwave'])->name('subscription.flutterwave');

        Route::post('/subscription-pay-with-paystack', [PaymentController::class, 'subscriptionPaystack'])->name('subscription.pay.with.paystack')->middleware(['auth', 'XSS']);
        Route::get('/subscription/paystack/{pay_id}/{s_id}', [PaymentController::class, 'subscriptionPaystackStatus'])->name('subscription.paystack');
    }
);

//-------------------------------Employee-------------------------------------------
Route::resource('employee', EmployeeController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);


//-------------------------------Client-------------------------------------------

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::resource('client', ClientController::class);
        Route::get('client/{id}/vehicle', [ClientController::class, 'getVehicle'])->name('client.vehicle');
        Route::get('client/{id}/service', [ClientController::class, 'getService'])->name('client.service');
    }
);


//-------------------------------Item-------------------------------------------
Route::resource('item-category', \App\Http\Controllers\ItemCategoryController::class)->except('show')->middleware(['auth', 'XSS']);

Route::resource('item', ItemController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Vehicle Type-------------------------------------------
Route::resource('vehicle-type', VehicleTypeController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Vehicle-------------------------------------------

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::resource('vehicle', VehicleController::class);
        Route::get('vehicle/brand/{tid}', [VehicleController::class, 'getBrand'])->name('vehicle.brand');
    }
);

//-------------------------------Service-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('service/today', [ServiceController::class, 'todayService'])->name('service.today');
        Route::resource('service', ServiceController::class);
        Route::post('service/type', [ServiceController::class, 'type'])->name('service.type');
        Route::delete('service/type/destroy', [ServiceController::class, 'serviceTypeDestroy'])->name('service.type.destroy');
        Route::get('calendar', [ServiceController::class, 'calendar'])->name('calendar');
        Route::get('service-kanban',[ServiceController::class , 'serviceKanban'])->name('service.kanban');
        Route::post('/service/change-status',[ServiceController::class, 'changeStatus'])->name('service.change.status');
    }
);

Route::get('report/service', [ReportController::class, 'service'])->name('report.service');
Route::get('report/income', [ReportController::class, 'income'])->name('report.income');
Route::get('report/expense', [ReportController::class, 'expense'])->name('report.expense');
Route::get('report/profit-loss', [ReportController::class, 'reportProfitLoss'])->name('report.profit_loss');



//-------------------------------Invoice-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::post('invoice/item', [InvoiceController::class, 'item'])->name('invoice.item');
        Route::get('invoice/product', [InvoiceController::class, 'product'])->name('invoice.product');
        Route::post('invoice/item/destroy', [InvoiceController::class, 'itemDestroy'])->name('invoice.item.destroy');
        Route::get('invoice/{id}/status/change', [InvoiceController::class, 'statusChange'])->name('invoice.status.change');
        Route::get('invoice/{id}/payment', [InvoiceController::class, 'payment'])->name('invoice.payment');
        Route::post('invoice/{id}/payment', [InvoiceController::class, 'createPayment'])->name('invoice.payment');
        Route::post('invoice/{id}/payment/{pid}/destroy', [InvoiceController::class, 'paymentDestroy'])->name('invoice.payment.destroy');

        Route::get('invoice/{id}/create/item', [InvoiceController::class, 'invoiceItem'])->name('invoice.create.item');
        Route::post('invoice/{id}/item/store', [InvoiceController::class, 'invoiceItemStore'])->name('invoice.item.store');
        Route::post('invoice/{id}/item/{tid}/store', [InvoiceController::class, 'invoiceItemDestroy'])->name('invoice.item.destroy');
        Route::resource('invoice', InvoiceController::class);

        Route::post('invoice/{id}/banktransfer/payment', [InvoiceController::class, 'banktransferPayment'])->name(name: 'invoice.banktransfer.payment');
        Route::get('invoice-payment-status/{id}/{status}', [InvoiceController::class, 'invoicePaymentStatus'])->name('invoice.bank.transfer.action');
        Route::post('invoice/{id}/stripe/payment', [InvoiceController::class, 'invoiceStripePayment'])->name('invoice.stripe.payment');
        Route::post('booing/{id}/paypal', [InvoiceController::class, 'invoicePaypal'])->name('invoice.paypal');
        Route::get('invoice/{id}/paypal/{status}', [InvoiceController::class, 'invoicePaypalStatus'])->name('invoice.paypal.status');
        Route::get('invoice/flutterwave/{id}/{tx_ref}', [InvoiceController::class, 'invoiceFlutterwave'])->name('invoice.flutterwave');


        Route::post('invoice/{id}/paystack/payment', [InvoiceController::class, 'invoicePaystack'])->name('invoice.paystack.payment');
        Route::get('invoice/paystack/{pay_id}/{id}', [InvoiceController::class, 'invoicePaystackStatus'])->name('invoice.paystack');

        Route::get('get-service-type/{id}', [InvoiceController::class, 'getServiceType'])->name('get.service.type');
    }
);

//-------------------------------Expense-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::resource('expense', ExpenseController::class);
    }
);

//-------------------------------Vehicle Brand-------------------------------------------
Route::resource('vehicle-brand', VehicleBrandController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);


//-----------------------------------quotation---------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::resource('quotation', QuotationController::class);
        Route::get('quotation/{id}/convert', [QuotationController::class, 'convert'])->name('quotation.convert');
        Route::post('quotation/{id}/convert-in-service', [QuotationController::class, 'convertInServiceData'])->name('quotation.convertservice');
        Route::get('quotation-item', [QuotationController::class, 'quotationItem'])->name('quotation.item');
    }
);




//-------------------------------Service Type-------------------------------------------
Route::resource('service-type', ServiceTypeController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Tax-------------------------------------------
Route::resource('tax', TaxController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Unit-------------------------------------------
Route::resource('unit', UnitController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);



//-------------------------------Notification-------------------------------------------
Route::resource('notification', NotificationController::class)->middleware(
    [
        'auth',
        'XSS',

    ]
);

Route::get('email-verification/{token}', [VerifyEmailController::class, 'verifyEmail'])->name('email-verification')->middleware(
    [
        'XSS',
    ]
);

//-------------------------------FAQ-------------------------------------------
Route::resource('FAQ', FAQController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Home Page-------------------------------------------
Route::resource('homepage', HomePageController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------FAQ-------------------------------------------
Route::resource('pages', PageController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Auth page-------------------------------------------
Route::resource('authPage', AuthPageController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('generate-template/{title}', [AiTemplateController::class, 'create'])->name('generate.template');
        Route::post('generate-template-keywords/{id}', [AiTemplateController::class, 'getTemplateKeywords'])->name('generate.template.keywords');
        Route::post('generate-prompt-response', [AiTemplateController::class, 'AiPromptGenerate'])->name('generate.prompt.response');
        Route::resource('n8n', N8nController::class);
    }
);


Route::get('page/{slug}', [PageController::class, 'page'])->name('page');
//-------------------------------FAQ-------------------------------------------
Route::impersonate();
