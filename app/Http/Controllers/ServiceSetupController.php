<?php

namespace App\Http\Controllers;

use App\Models\{ServiceType, User, Vehicle};

class ServiceSetupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->type === 'owner' && auth()->user()->can('manage service'), 403);
        $owner = auth()->id();
        $clientCount = User::where('parent_id', $owner)->where('type', 'client')->whereNull('client_archived_at')->count();
        $vehicleCount = Vehicle::where('parent_id', $owner)->count();
        $employeeCount = User::where('parent_id', $owner)->where('type', 'employee')->count();
        $typeCount = ServiceType::where('parent_id', $owner)->count();
        return view('service.setup', compact('clientCount', 'vehicleCount', 'employeeCount', 'typeCount'));
    }
}
