<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessLocation extends Model {
 protected $guarded=[];
 protected $casts=['latitude'=>'float','longitude'=>'float'];
 public function directionsUrl(): string {
  return 'https://www.google.com/maps/dir/?'.http_build_query(['api'=>1,'destination'=>$this->latitude.','.$this->longitude,'travelmode'=>'driving'], '', '&', PHP_QUERY_RFC3986);
 }
}
