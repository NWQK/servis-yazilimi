<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
class GoogleMapsLocation {
 private function allowed(string $url): bool {
  $p=parse_url($url);
  if (!$p || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port']) || strlen($url)>2048) return false;
  $host=strtolower($p['host'] ?? '');$path=$p['path'] ?? '';
  if ($host==='maps.app.goo.gl') return (bool)preg_match('~^/[a-zA-Z0-9]+/?$~D',$path);
  if ($host==='goo.gl') return str_starts_with($path,'/maps/');
  if (in_array($host,['www.google.com','google.com','www.google.com.tr','google.com.tr','maps.google.com'],true)) {
   return ($host==='maps.google.com' && in_array($path,['','/'],true)) || ((bool)preg_match('~^/maps(?:/|$)~',$path) && !str_starts_with($path,'/maps/dir'));
  }
  return false;
 }
 private function coordinates(string $url): ?array {
  $decoded=rawurldecode($url);
  if (preg_match_all('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',$decoded,$m,PREG_SET_ORDER)) { $last=end($m);return [(float)$last[1],(float)$last[2]]; }
  parse_str(parse_url($url,PHP_URL_QUERY) ?? '',$query);
  foreach (['q','query'] as $key) {
   if (isset($query[$key]) && is_string($query[$key]) && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/D',$query[$key],$m)) return [(float)$m[1],(float)$m[2]];
  }
  // @latitude,longitude is often the camera centre, not the business pin. Never infer a pin from it.
  return null;
 }
 public function resolve(string $url, $latitude=null, $longitude=null): array {
  if (!$this->allowed($url)) $this->invalid('Geçerli bir HTTPS Google Maps işletme / konum bağlantısı girin.');
  $original=$url;
  $point=$latitude!==null && $longitude!==null ? [(float)$latitude,(float)$longitude] : $this->coordinates($url);
  for ($i=0;!$point && $i<3;$i++) {
   try { $response=Http::withoutRedirecting()->connectTimeout(2)->timeout(3)->head($url); }
   catch (\Throwable $e) { break; }
   $next=$response->header('Location');
   if (!$response->redirect() || !$next) break;
   if (str_starts_with($next,'/')) $next='https://'.parse_url($url,PHP_URL_HOST).$next;
   // Validate each redirect before making any request; private hosts and arbitrary URLs are never fetched.
   if (!$this->allowed($next)) $this->invalid('Google Maps bağlantısı güvenli bir konuma yönlenmiyor.');
   $url=$next;$point=$this->coordinates($url);
  }
  if (!$point || !is_finite($point[0]) || !is_finite($point[1]) || abs($point[0])>90 || abs($point[1])>180) {
   $this->invalid('Bağlantıdan işletmenin kesin konumu alınamadı. Google Maps’te işletme işaretinin enlem ve boylamını aşağıdaki alanlara girin.');
  }
  return ['google_maps_url'=>$original,'latitude'=>$point[0],'longitude'=>$point[1]];
 }
 private function invalid(string $message): void { throw ValidationException::withMessages(['google_maps_url'=>$message]); }
}
