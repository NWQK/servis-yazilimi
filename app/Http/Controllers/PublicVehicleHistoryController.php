<?php
namespace App\Http\Controllers;
use App\Services\{ChassisNumber,PublicVehicleHistory};
use Illuminate\Http\Request;

class PublicVehicleHistoryController extends Controller
{
    public function index(Request $request,PublicVehicleHistory $history)
    {
        $search=$request->session()->get('public_vehicle_history');
        if ($search && $search['expires_at']<now()->timestamp) {$request->session()->forget('public_vehicle_history');$search=null;}
        $request->validate(['page'=>'nullable|integer|min:1|max:10000']);
        $results=$search ? $history->results($search['hash']) : null;
        return response()->view('vehicle_history.public',compact('search','results'))->header('Cache-Control','private, no-store')
            ->header('Referrer-Policy','no-referrer')->header('X-Robots-Tag','noindex, nofollow, noarchive');
    }
    public function search(Request $request)
    {
        $request->merge(['chassis_no'=>ChassisNumber::normalize($request->input('chassis_no'))]);
        $request->validate(['chassis_no'=>ChassisNumber::rules()]);
        $vin=$request->input('chassis_no');
        $request->session()->put('public_vehicle_history',['hash'=>hash('sha256',$vin),'suffix'=>substr($vin,-4),'expires_at'=>now()->addMinutes(15)->timestamp]);
        return redirect()->route('vehicle-history.index');
    }
}
