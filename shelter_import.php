<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply($data, $code=200) { http_response_code($code); echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['role']??'')) !== 'admin') reply(['error'=>'Admin access required'],403);
if ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals((string)($_SESSION['shelter_admin_csrf']??''),(string)($_POST['csrf']??''))) reply(['error'=>'Invalid request token'],403);
$mode=(string)($_POST['mode']??'link');
$input=trim((string)($_POST['link']??''));
if (strlen($input)>2048) reply(['error'=>'Link too long'],400);
function allowedHost($url) {
  $host=strtolower((string)parse_url($url,PHP_URL_HOST));
  return in_array($host,['maps.app.goo.gl','goo.gl','google.com','www.google.com','maps.google.com','google.com.ph','www.google.com.ph'],true);
}
function fetchText($url,$headers=[], $head=false) {
  if (!function_exists('curl_init')) {
    $context=stream_context_create(['http'=>['method'=>$head?'HEAD':'GET','timeout'=>9,'follow_location'=>0,'ignore_errors'=>true,'header'=>implode("\r\n",array_merge(['User-Agent: PetRescueShelterLocator/1.0','Accept: application/json'],$headers))], 'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $body=@file_get_contents($url,false,$context);
    $responseHeaders=$http_response_header??[];
    $status=0;$location=null;
    foreach($responseHeaders as $header){if(preg_match('~^HTTP/\S+\s+(\d+)~i',$header,$m))$status=(int)$m[1];if(preg_match('/^location:\s*(.+)$/i',$header,$m))$location=trim($m[1]);}
    return [$body===false?null:$body,$status,$location];
  }
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>9,CURLOPT_USERAGENT=>'PetRescueShelterLocator/1.0 (admin import)',CURLOPT_HTTPHEADER=>$headers,CURLOPT_MAXREDIRS=>0,CURLOPT_HEADER=>true,CURLOPT_NOBODY=>$head,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
  $res=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$headerLen=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
  if (!is_string($res)) return [null,$code,null];
  $headText=substr($res,0,$headerLen);$location=null;
  if(preg_match('/^location:\s*(.+)$/im',$headText,$m)) $location=trim($m[1]);
  return [substr($res,$headerLen),$code,$location];
}
function getJson($url) { [$body,$code]=fetchText($url,['Accept: application/json','Accept-Language: en']);if($code!==200||!$body)return null;return json_decode($body,true); }
function coordinates($url) {
  $decoded=rawurldecode($url);
  foreach(['/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/','/[?&](?:q|query|ll|center|destination)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/','/\/search\/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/'] as $pattern){
    if(preg_match($pattern,$decoded,$m)){ $a=(float)$m[1];$b=(float)$m[2];if(abs($a)<=90&&abs($b)<=180)return [$a,$b]; }
  }
  // @lat,lng is the map camera, not necessarily the selected place; do not treat it as exact.
  return null;
}
if($mode==='link'){
  if(!filter_var($input,FILTER_VALIDATE_URL)||strtolower((string)parse_url($input,PHP_URL_SCHEME))!=='https'||!allowedHost($input)) reply(['error'=>'Paste a valid HTTPS Google Maps link.'],400);
  $url=$input;
  for($i=0;$i<4;$i++){
    if(!allowedHost($url)) break;
    [$body,$status,$location]=fetchText($url,[],true);
    if($status>=300&&$status<400&&$location){
      $next=$location;
      if(str_starts_with($next,'/')){ $p=parse_url($url);$next='https://'.$p['host'].$next; }
      if(!allowedHost($next)||strtolower((string)parse_url($next,PHP_URL_SCHEME))!=='https') break;
      $url=$next;continue;
    }
    break;
  }
  $coords=coordinates($url);
  if(!$coords){
    $decoded=rawurldecode($url);$query='';
    if(preg_match('~/maps/place/([^/?]+)~',$decoded,$m))$query=str_replace('+',' ',$m[1]);
    if(!$query){parse_str((string)parse_url($url,PHP_URL_QUERY),$params);$query=trim((string)($params['query']??$params['q']??''));}
    if(!$query)reply(['error'=>'This link has no exact coordinates or searchable place. Try the place name search or pin the map.'],422);
    $mode='search';$input=$query;
  }else{[$lat,$lng]=$coords;}
}
if($mode==='search'){
  $q=trim($input);
  if(strlen($q)<3||strlen($q)>200)reply(['error'=>'Enter a place or address to search.'],400);
  $matches=getJson('https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=ph&q='.rawurlencode($q));
  if(!is_array($matches)||!count($matches))reply(['error'=>'No matching location found. Try a more specific address or pin the map.'],404);
  $match=$matches[0];$lat=(float)$match['lat'];$lng=(float)$match['lon'];
}
if(!isset($lat,$lng)||abs($lat)>90||abs($lng)>180)reply(['error'=>'Invalid coordinates'],422);
$reverse=getJson('https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=18&lat='.rawurlencode((string)$lat).'&lon='.rawurlencode((string)$lng));
$addr=$reverse['address']??($match['address']??[]);
$city=$addr['city']??$addr['town']??$addr['municipality']??$addr['village']??'';
$province=$addr['province']??$addr['state']??$addr['region']??'';
$name=$reverse['name']??($match['name']??'');
if(!$name&&isset($q))$name=$q;
$street=$reverse['display_name']??($match['display_name']??'');
$phone='';$hours='';$website='';
// Nearby OSM business tags may contain opening_hours, phone, and website.
// Never claim a nearby POI is the same shelter unless name and distance are plausible.
$overpass='[out:json][timeout:8];(node(around:45,'.(float)$lat.','.(float)$lng.')[name];way(around:45,'.(float)$lat.','.(float)$lng.')[name];);out center 20;';
$osm=getJson('https://overpass.kumi.systems/api/interpreter?data='.rawurlencode($overpass));
if(is_array($osm['elements']??null)){
  $best=null;$score=-1;
  foreach($osm['elements'] as $item){$tags=$item['tags']??[];$n=$tags['name']??'';if(!$n)continue;
    $s=0;if($name&&mb_strtolower(trim($n))===mb_strtolower(trim($name)))$s+=10;
    if(isset($q)&&stripos($q,$n)!==false)$s+=10;
    if(preg_match('/shelter|rescue|animal|pet|paws|veterinary/i',$n.' '.($tags['amenity']??'')))$s+=2;
    if($s>$score){$score=$s;$best=$tags;}
  }
  if($best&&$score>=2){$name=$best['name']??$name;$phone=$best['phone']??$best['contact:phone']??'';$hours=$best['opening_hours']??'';$website=$best['website']??$best['contact:website']??'';}
}
reply(['name'=>mb_substr((string)$name,0,150),'address'=>mb_substr((string)$street,0,255),'city'=>mb_substr((string)$city,0,100),'province'=>mb_substr((string)$province,0,100),'latitude'=>$lat,'longitude'=>$lng,'phone'=>mb_substr((string)$phone,0,30),'osm_opening_hours'=>$hours,'website'=>$website,'source'=>'OpenStreetMap / Nominatim','note'=>'Confirm location and hours before saving. Google-only business hours cannot be retrieved without Google Places API.']);
