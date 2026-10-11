<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class BookingLocationController extends Controller {
 public function store(Request $request) {
  $data=$request->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','accuracy'=>'required|numeric|between:0,5000']);
  $value=json_encode(['lat'=>round((float)$data['latitude'],5),'lng'=>round((float)$data['longitude'],5),'expires'=>time()+1800]);
  // Laravel EncryptCookies encrypts/authenticates this short-lived HttpOnly cookie; no GPS coordinates in URLs or database records.
  return response()->json(['saved'=>true])->cookie('booking_location',$value,30,'/',null,$request->isSecure(),true,false,'lax')->header('Cache-Control','private, no-store');
 }
}
