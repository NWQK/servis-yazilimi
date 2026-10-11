<?php
namespace App\Services;
use Illuminate\Http\Request;
class BookingLocation {
 public static function current(Request $request): ?array {
  $value=json_decode($request->cookie('booking_location',''),true);
  if (!is_array($value) || !isset($value['lat'],$value['lng'],$value['expires']) || $value['expires']<time()) return null;
  if (!is_numeric($value['lat']) || !is_numeric($value['lng']) || abs($value['lat'])>90 || abs($value['lng'])>180) return null;
  return ['lat'=>(float)$value['lat'],'lng'=>(float)$value['lng']];
 }
 public static function distanceExpression(): string {
  // Production MySQL provides these functions. Register their equivalents for SQLite installations.
  if (\DB::connection()->getDriverName()==='sqlite') {
   $pdo=\DB::connection()->getPdo();
   foreach (['RADIANS'=>'deg2rad','COS'=>'cos','SIN'=>'sin','ACOS'=>'acos','LEAST'=>'min','GREATEST'=>'max'] as $name=>$fn) $pdo->sqliteCreateFunction($name,$fn);
  }
  return '6371 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(?)) * COS(RADIANS(business_locations.latitude)) * COS(RADIANS(business_locations.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(business_locations.latitude)))))';
 }
}
