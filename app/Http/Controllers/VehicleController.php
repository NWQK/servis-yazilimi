<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use App\Models\VehicleQrCode;
use App\Services\VehicleQrPool;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage vehicle')) {
            if (auth()->user()->type == 'client') {
                $vehicles = Vehicle::where('parent_id', '=', parentId())->where('client', auth()->user()->id)->orderBy('id', 'desc')->get();
            } else {
                $vehicles = Vehicle::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('vehicle.index', compact('vehicles'));
    }


    public function create()
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('create vehicle'), 403);
        app(VehicleQrPool::class)->replenish(parentId());
        $qrCodes = VehicleQrCode::where('parent_id', parentId())->available()->whereNotNull('printed_at')->orderBy('id')->get();
        $types = VehicleType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Brand'), '');
        $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
        return view('vehicle.create', compact('types', 'clients', 'qrCodes'));
    }


    public function store(Request $request)
    {
        abort_if(auth()->user()->type === 'client', 403);
        if (\Auth::user()->can('create vehicle')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'client' => ['required', Rule::exists('users', 'id')->where('parent_id', parentId())->where('type', 'client')],
                    'type' => ['required', Rule::exists('vehicle_types', 'id')->where('parent_id', parentId())],
                    'brand' => ['required', Rule::exists('vehicle_brands', 'id')->where('parent_id', parentId())->where('type', $request->type)],
                    'qr_code_id' => 'required|integer',
                    'color' => 'nullable|string|max:255',
                    'license_plate' => 'required',
                    'engine_type' => 'nullable|string|max:255',
                    'engine_no' => 'nullable|string|max:255',
                    'chassis_no' => 'nullable|string|max:255',
                    'fuel_type' => 'nullable|string|max:255',
                    'mileage' => 'nullable|integer|min:0|max:2147483647',
                    'last_service_date' => 'nullable|date',
                    'next_service_due_date' => 'nullable|date',
                    'insurance_details' => 'nullable|string',
                    'notes' => 'nullable|string',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->withInput()->with('error', $messages->first());
            }
            $vehicle = app(VehicleQrPool::class)->createVehicle(parentId(), (int) $request->qr_code_id, function () use ($request) {
                $vehicle = new Vehicle();
                $vehicle->vehicle_id = $this->vehicleNumber();
                $vehicle->client = $request->client;
                $vehicle->type = $request->type;
                $vehicle->brand = $request->brand;
                $vehicle->license_plate = $request->license_plate;
                $vehicle->engine_type = $request->engine_type;
                $vehicle->engine_no = $request->engine_no;
                $vehicle->fuel_type = $request->fuel_type;
                $vehicle->chassis_no = $request->chassis_no;
                $vehicle->mileage = $request->mileage;
                $vehicle->last_service_date = $request->last_service_date;
                $vehicle->next_service_due_date = $request->next_service_due_date;
                $vehicle->insurance_details = $request->insurance_details;
                $vehicle->color = $request->color;
                $vehicle->status = $request->status;
                $vehicle->notes = $request->notes;
                $vehicle->parent_id = parentId();
                $vehicle->save();

                return $vehicle;
            });

            $module = 'vehicle_create';
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
            $setting = settings();
            $errorMessage = '';

          if (!empty($notification)) {
                $notificationResponse = MessageReplace($notification, $vehicle->id);
                $data['subject'] = $notificationResponse['subject'];
                $data['message'] = $notificationResponse['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $vehicle->clients->email;
                if ($notification->enabled_email == 1 && !empty($to)) {
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
                if ($notification->enabled_sms == 1) {
                    $twilio_sid = getSettingsValByName('twilio_sid');
                    if (!empty($twilio_sid)) {
                        send_twilio_msg($vehicle->clients->phone_number, $notificationResponse['sms_message']);
                    }
                }
                if ($notification->enabled_whatsapp == 1 && !empty($vehicle->clients->phone_number)) {
                    sendWhatshappSms([
                        'to' => $vehicle->clients->phone_number,
                        'body' => $notificationResponse['sms_message']
                    ]);
                }
            }
            return redirect()->route('vehicle.index')->with('success', __('Vehicle successfully created.') . '</br>' . $errorMessage);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function show(Vehicle $vehicle)
    {
        abort_unless(auth()->user()->can('show vehicle') && (int) $vehicle->parent_id === (int) parentId(), 403);
        abort_if(auth()->user()->type === 'client' && (int) $vehicle->client !== (int) auth()->id(), 403);
        $qrCodes = collect();
        if (auth()->user()->type !== 'client' && auth()->user()->can('create vehicle') && auth()->user()->can('edit vehicle') && !$vehicle->qrCode) {
            app(VehicleQrPool::class)->replenish(parentId());
            $qrCodes = VehicleQrCode::where('parent_id', parentId())->available()->whereNotNull('printed_at')->orderBy('id')->get();
        }
        return view('vehicle.show', compact('vehicle', 'qrCodes'));
    }


    public function edit(Vehicle $vehicle)
    {
        abort_unless(auth()->user()->can('edit vehicle') && (int) $vehicle->parent_id === (int) parentId() && auth()->user()->type !== 'client', 403);
        $types = VehicleType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Brand'), '');
        $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
        return view('vehicle.edit', compact('types', 'clients', 'vehicle'));
    }


    public function update(Request $request, Vehicle $vehicle)
    {
        abort_unless((int) $vehicle->parent_id === (int) parentId() && auth()->user()->type !== 'client', 403);
        if (\Auth::user()->can('edit vehicle')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'client' => ['required', Rule::exists('users', 'id')->where('parent_id', parentId())->where('type', 'client')],
                    'type' => ['required', Rule::exists('vehicle_types', 'id')->where('parent_id', parentId())],
                    'brand' => ['required', Rule::exists('vehicle_brands', 'id')->where('parent_id', parentId())->where('type', $request->type)],
                    'color' => 'nullable|string|max:255',
                    'license_plate' => 'required',
                    'engine_type' => 'nullable|string|max:255',
                    'engine_no' => 'nullable|string|max:255',
                    'chassis_no' => 'nullable|string|max:255',
                    'fuel_type' => 'nullable|string|max:255',
                    'mileage' => 'nullable|integer|min:0|max:2147483647',
                    'last_service_date' => 'nullable|date',
                    'next_service_due_date' => 'nullable|date',
                    'insurance_details' => 'nullable|string',
                    'notes' => 'nullable|string',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $vehicle->client = $request->client;
            $vehicle->type = $request->type;
            $vehicle->brand = $request->brand;
            $vehicle->license_plate = $request->license_plate;
            $vehicle->engine_type = $request->engine_type;
            $vehicle->engine_no = $request->engine_no;
            $vehicle->fuel_type = $request->fuel_type;
            $vehicle->chassis_no = $request->chassis_no;
            $vehicle->mileage = $request->mileage;
            $vehicle->last_service_date = $request->last_service_date;
            $vehicle->next_service_due_date = $request->next_service_due_date;
            $vehicle->insurance_details = $request->insurance_details;
            $vehicle->color = $request->color;
            $vehicle->status = $request->status;
            $vehicle->notes = $request->notes;
            $vehicle->save();

            return redirect()->route('vehicle.index')->with('success', __('Vehicle successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function destroy(Vehicle $vehicle)
    {
        abort_unless((int) $vehicle->parent_id === (int) parentId() && auth()->user()->type !== 'client', 403);
        if (\Auth::user()->can('delete vehicle')) {
            $vehicle->delete();
            return redirect()->route('vehicle.index')->with('success', __('Vehicle successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function vehicleNumber()
    {
        $latest = Vehicle::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->vehicle_id + 1;
    }

    public function getBrand($typeId)
    {
        $types = VehicleBrand::where('parent_id', parentId())->where('type', $typeId)->get()->pluck('name', 'id');
        return response()->json($types);
    }
}
