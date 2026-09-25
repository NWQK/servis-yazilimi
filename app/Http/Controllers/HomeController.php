<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Custom;
use App\Models\FAQ;
use App\Models\HomePage;
use App\Models\NoticeBoard;
use App\Models\PackageTransaction;
use App\Models\Page;
use App\Models\Subscription;
use App\Models\Support;
use App\Models\User;
use App\Models\Service;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\ServiceItem;
use App\Models\ServiceType;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\Include_;

class HomeController extends Controller
{
    public function index()
    {
        if (\Auth::check()) {
            if (\Auth::user()->type == 'super admin') {
                $result['totalOrganization'] = User::where('type', 'owner')->count();
                $result['totalSubscription'] = Subscription::count();
                $result['totalTransaction'] = PackageTransaction::count();
                $result['totalIncome'] = PackageTransaction::sum('amount');
                $result['totalNote'] = NoticeBoard::where('parent_id', parentId())->count();
                $result['totalContact'] = Contact::where('parent_id', parentId())->count();
                $result['organizationByMonth'] = $this->organizationByMonth();
                $result['paymentByMonth'] = $this->paymentByMonth();
                return view('dashboard.super_admin', compact('result'));
            } else {
                if (\Auth::user()->type == 'client') {
                    $user = Auth::user();
                    $result['totalInvoice'] = Invoice::where('client', $user->id)->count();
                    $result['todayService'] = Service::where('parent_id', parentId())->where('client', $user->id)->where('service_date', date('Y-m-d'))->count();
                    $result['completeService'] = Service::where('parent_id', parentId())->where('client', $user->id)->where('status', 'completed')->count();
                    $result['totalQuotation'] = Quotation::where('parent_id', parentId())->where('client_id', $user->id)->count();
                    // $result['lastService'] = $this->lastService();
                    $result['vehicleServiceGraph'] = $this->vehicleServiceGraph();
                    $result['totalVehicle'] = Vehicle::where('client', $user->id)->where('parent_id', parentId())->count();
                    $result['totalService'] = Service::where('client', $user->id)->where('parent_id', parentId())->count();

                    $result['MonthPayment'] = InvoicePayment::whereHas('invoice', function ($q) use ($user) {
                        $q->where('client', $user->id)->where('parent_id', parentId());
                    })->whereMonth('payment_date', Carbon::now()->month)->whereYear('payment_date', Carbon::now()->year)->where('payment_status', 'success')->sum('amount');

                    $result['latestCompletedService'] = Service::with('vehicles')->where('client', $user->id)->where('parent_id', parentId())->where('status', 'completed')->latest('due_date')->first();

                    $result['incomingServices'] = Service::with(['vehicles', 'clients'])->where('parent_id', parentId())->where('client', $user->id)->whereIn('status', ['scheduled', 'in_progress'])->orderBy('service_date', 'asc')->get();
                    $result['monthPayments'] = InvoicePayment::with([
                        'invoice.clients',
                        'invoice.services.vehicles'
                    ])->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->whereHas('invoice', function ($q) use ($user) {
                        $q->where('client', $user->id)->where('parent_id', parentId());
                    })->latest('payment_date')->get();

                    return view('dashboard.client', compact('result', 'user'));
                }
                if (\Auth::user()->type == 'employee') {
                    $user = Auth::user();
                    $result['todayService'] = Service::where('parent_id', parentId())->where('assign', $user->id)->where('service_date', date('Y-m-d'))->count();
                    $result['completeService'] = Service::where('parent_id', parentId())->where('assign', $user->id)->where('status', 'completed')->count();
                    $result['inProgressService'] = Service::where('parent_id', parentId())->where('assign', $user->id)->where('status', 'in_progress')->count();
                    $result['totalService'] = Service::where('parent_id', parentId())->where('assign', $user->id)->count();
                    $result['lastAssignService'] = $this->lastAssignService();
                    $result['monthServices'] = Service::with(['clients', 'vehicles'])->where('parent_id', parentId())->where('assign', $user->id)->whereMonth('service_date', now()->month)->whereYear('service_date', now()->year)->latest()->get();
                    $result['todayServicesList'] = Service::with(['clients', 'vehicles'])->where('parent_id', parentId())->where('assign', $user->id)->whereDate('service_date', today())->latest()->get();
                    return view('dashboard.employee', compact('result', 'user'));
                }
                $result['totalUser'] = User::where('parent_id', parentId())->count();
                $result['totalClient'] = User::where('type', 'client')->where('parent_id', parentId())->count();
                $result['todayService'] = Service::where('parent_id', parentId())->where('service_date', date('Y-m-d'))->count();
                $result['incomeExpenseByMonth'] = $this->incomeExpenseByMonth();
                $result['settings'] = settings();
                $result['curentMonthIncome'] = InvoicePayment::whereMonth('payment_date', Carbon::now()->month)->whereYear('payment_date', Carbon::now()->year)->sum('amount');
                $result['curentMonthExpense'] = Expense::whereMonth('date', Carbon::now()->month)->whereYear('date', Carbon::now()->year)->sum('amount');
                $result['incomingServices'] = Service::with(['vehicles', 'clients'])->whereDate('service_date', Carbon::today())->orderBy('service_time', 'asc')->get();
                $result['monthPayments'] = InvoicePayment::with(['invoice', 'invoice.services', 'invoice.services.vehicles'])->whereDate('payment_date', Carbon::today())->latest()->get();

                $result['serviceStatusChart'] = [
                    'scheduled' => Service::where('parent_id', parentId())->where('status', 'scheduled')->count(),
                    'in_progress' => Service::where('parent_id', parentId())->where('status', 'in_progress')->count(),
                    'completed' => Service::where('parent_id', parentId())->where('status', 'completed')->count(),
                    'pending_parts' => Service::where('parent_id', parentId())->where('status', 'pending_parts')->count(),
                    'on_hold' => Service::where('parent_id', parentId())->where('status', 'on_hold')->count(),
                    'cancelled' => Service::where('parent_id', parentId())->where('status', 'cancelled')->count(),
                ];

                $services = Service::where('parent_id', parentId())->get();
                $eventData = $currentMonth = [];
                foreach ($services as $service) {
                if (!$service->service_date) continue; // Undated services have no calendar position.
                    $assign_user = User::find($service->assign);
                    $event = [
                        'title' => servicePrefix() . $service->service_id,
                        'assign' => ucfirst($assign_user->name ?? "-"),
                        'start' => date("Y-m-d", strtotime($service->service_date)),
                        'end' => date("Y-m-d", strtotime($service->service_date)),
                        'urls' => route('service.show', encrypt($service->id)),
                    ];
                    $eventData[] = $event;
                }
                return view('dashboard.index', compact('result', 'eventData'));
            }
        } else {
            if (!file_exists(setup())) {
                header('location:install');
                die;
            } else {
                $landingPage = getSettingsValByName('landing_page');
                if ($landingPage == 'on') {
                    $subscriptions = Subscription::get();
                    $menus = Page::where('enabled', 1)->get();
                    $FAQs = FAQ::where('enabled', 1)->get();

                    $user = \App\Models\User::find(1);
                    \App::setLocale('tr');

                    return view('layouts.landing', compact('subscriptions', 'menus', 'FAQs'));
                } else {
                    return redirect()->route('login');
                }
            }
        }
    }

    public function organizationByMonth()
    {
        $start = strtotime(date('Y-01'));
        $end = strtotime(date('Y-12'));
        $currentdate = $start;
        $organization = [];
        while ($currentdate <= $end) {
            $organization['label'][] = \Carbon\Carbon::createFromTimestamp($currentdate)->locale('tr')->translatedFormat('M Y');
            $month = date('m', $currentdate);
            $year = date('Y', $currentdate);
            $organization['data'][] = User::where('type', 'owner')->whereMonth('created_at', $month)->whereYear('created_at', $year)->count();
            $currentdate = strtotime('+1 month', $currentdate);
        }
        return $organization;
    }

    public function paymentByMonth()
    {
        $start = strtotime(date('Y-01'));
        $end = strtotime(date('Y-12'));
        $currentdate = $start;
        $payment = [];
        while ($currentdate <= $end) {
            $payment['label'][] = \Carbon\Carbon::createFromTimestamp($currentdate)->locale('tr')->translatedFormat('M Y');
            $month = date('m', $currentdate);
            $year = date('Y', $currentdate);
            $payment['data'][] = PackageTransaction::whereMonth('created_at', $month)->whereYear('created_at', $year)->sum('amount');
            $currentdate = strtotime('+1 month', $currentdate);
        }
        return $payment;
    }

    public function incomeByMonth()
    {
        $start = strtotime(date('Y-01'));
        $end = strtotime(date('Y-12'));
        $currentdate = $start;
        $payment = [];
        while ($currentdate <= $end) {
            $payment['label'][] = \Carbon\Carbon::createFromTimestamp($currentdate)->locale('tr')->translatedFormat('M Y');
            $month = date('m', $currentdate);
            $year = date('Y', $currentdate);
            $payment['income'][] = InvoicePayment::where('parent_id', parentId())->whereMonth('payment_date', $month)->whereYear('payment_date', $year)->sum('amount');
            $payment['expense'][] = Expense::where('parent_id', parentId())->whereMonth('date', $month)->whereYear('date', $year)->sum('amount');
            $currentdate = strtotime('+1 month', $currentdate);
        }
        return $payment;
    }

    public function incomeExpenseByMonth()
    {
        $start = strtotime(date('Y-01'));
        $end = strtotime(date('Y-12'));
        $currentdate = $start;
        $payment = [];
        while ($currentdate <= $end) {
            $payment['label'][] = \Carbon\Carbon::createFromTimestamp($currentdate)->locale('tr')->translatedFormat('M Y');
            $month = date('m', $currentdate);
            $year = date('Y', $currentdate);
            $payment['income'][] = InvoicePayment::where('parent_id', parentId())->whereMonth('payment_date', $month)->whereYear('payment_date', $year)->sum('amount');
            $payment['expense'][] = Expense::where('parent_id', parentId())->whereMonth('date', $month)->whereYear('date', $year)->sum('amount');
            $currentdate = strtotime('+1 month', $currentdate);
        }
        return $payment;
    }

    public function lastService()
    {
        $today = now();
        $startDate = $today->copy()->subDays(14);
        $userId = auth()->id();
        $services = DB::table('services')
            ->selectRaw('DATE(service_date) as date, COUNT(*) as total_count')
            ->whereYear('service_date', $today->year)
            ->where('client', $userId)
            ->whereBetween('service_date', [$startDate, $today])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->pluck('total_count', 'date');
        $labels = [];
        $counts = [];
        for ($i = 0; $i < 15; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $date;
            $counts[] = $services[$date] ?? 0;
        }
        return [
            'label'   => $labels,
            'service' => $counts,
        ];
    }

    public function lastAssignService()
    {
        $today = now();
        $startDate = $today->copy()->subDays(14);
        $userId = auth()->id();
        $services = DB::table('services')
            ->selectRaw('DATE(service_date) as date, COUNT(*) as total_count')
            ->whereYear('service_date', $today->year)
            ->where('assign', $userId)
            ->whereBetween('service_date', [$startDate, $today])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->pluck('total_count', 'date');
        $labels = [];
        $counts = [];
        for ($i = 0; $i < 15; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $date;
            $counts[] = $services[$date] ?? 0;
        }
        return [
            'label'   => $labels,
            'service' => $counts,
        ];
    }

   public function vehicleServiceGraph()
{
    $userId = auth()->id();

    $data = Service::join('vehicles', 'vehicles.id', '=', 'services.vehicle')
        ->where('services.client', $userId)
        ->whereMonth('services.service_date', now()->month)
        ->whereYear('services.service_date', now()->year)
        ->select(
            'vehicles.id',
            'vehicles.model',
            'vehicles.license_plate'
        )
        ->selectRaw('COUNT(services.id) as total_service')
        ->groupBy(
            'vehicles.id',
            'vehicles.model',
            'vehicles.license_plate'
        )
        ->orderBy('vehicles.model')
        ->get();

    $labels = [];
    $services = [];
    $serviceTypes = [];

    $vehicleNames = Vehicle::with(['types', 'brands'])->whereIn('id', $data->pluck('id'))->get()->keyBy('id');
    foreach ($data as $row) {

        $labels[] = ($vehicleNames->get($row->id)?->display_name ?: 'Vehicle') . ' (' . $row->license_plate . ')';
        $services[] = (int) $row->total_service;

        $typeIds = ServiceItem::join('services', 'services.id', '=', 'service_items.service_id')
            ->where('services.vehicle', $row->id)
            ->pluck('service_items.type_id')
            ->unique()
            ->toArray();

        $types = ServiceType::whereIn('id', $typeIds)
            ->pluck('type')
            ->implode(', ');

        $serviceTypes[] = $types ?: 'N/A';
    }

    return [
        'label' => $labels,
        'service' => $services,
        'serviceTypes' => $serviceTypes,
    ];
}
}
