<?php
// Minimální stuby WordPressu pro test pluginu mimo WP.
define('ABSPATH', __DIR__ . '/');
define('HARMONY_MZ_BASE_URL', 'http://127.0.0.1:8765/HARMONYpisek/');
define('HARMONY_MZ_API_URL', 'http://127.0.0.1:8765/');
define('HARMONY_MZ_USER', 'servis@harmony');
define('HARMONY_MZ_PASS', getenv('TEST_PASS') ?: 'tajne');
$GLOBALS['T'] = []; $GLOBALS['O'] = []; $GLOBALS['CRON'] = []; $GLOBALS['HTTP_COUNT'] = 0;
class WP_Error { public $m; function __construct($c='', $m='', $d=null){ $this->m=$m; } function get_error_message(){ return $this->m; } }
class WP_Http_Cookie { public $name,$value,$expires; }
function is_wp_error($x){ return $x instanceof WP_Error; }
function get_transient($k){ $v=$GLOBALS['T'][$k]??null; return ($v && $v[1]>time()) ? $v[0] : false; }
function set_transient($k,$v,$ttl){ $GLOBALS['T'][$k]=[$v,time()+$ttl]; return true; }
function delete_transient($k){ unset($GLOBALS['T'][$k]); }
function get_option($k,$d=false){ return $GLOBALS['O'][$k] ?? $d; }
function update_option($k,$v,$a=null){ $GLOBALS['O'][$k]=$v; }
function delete_option($k){ unset($GLOBALS['O'][$k]); }
function wp_next_scheduled($h){ return in_array($h,$GLOBALS['CRON']) ? time() : false; }
function wp_schedule_single_event($t,$h){ $GLOBALS['CRON'][]=$h; }
function wp_salt($s){ return 'testsalt'; }
function wp_json_encode($d,$f=0){ return json_encode($d,$f); }
function trailingslashit($s){ return rtrim($s,'/').'/'; }
function wp_parse_url($u,$c=-1){ return parse_url($u,$c); }
function wp_strip_all_tags($s){ return trim(strip_tags($s)); }
function apply_filters($h,$v){ return $v; }
function wp_date($f,$ts){ return (new DateTime('@'.$ts))->setTimezone(new DateTimeZone('Europe/Prague'))->format($f); }
function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES); } function esc_attr($s){ return esc_html($s); } function esc_url($s){ return esc_html($s); }
function wp_enqueue_style(){} function wp_enqueue_script(){}
function shortcode_atts($d,$a){ return array_merge($d,(array)$a); }
function wp_remote_request($url,$args){
  $GLOBALS['HTTP_COUNT']++;
  $ch = curl_init($url);
  $hdrs = []; foreach ($args['headers'] ?? [] as $k=>$v) $hdrs[] = "$k: $v";
  $c = []; foreach ($args['cookies'] ?? [] as $k=>$v) $c[] = "$k=$v";
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>1, CURLOPT_HEADER=>1, CURLOPT_FOLLOWLOCATION=>0, CURLOPT_NOPROXY=>'*', CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_COOKIE=>implode('; ',$c), CURLOPT_USERAGENT=>$args['user-agent'] ?? '', CURLOPT_TIMEOUT=>$args['timeout'] ?? 10]);
  if (($args['method'] ?? 'GET') === 'POST') { curl_setopt($ch, CURLOPT_POST, 1); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args['body'])); }
  $raw = curl_exec($ch); if ($raw === false) return new WP_Error('http','curl: '.curl_error($ch));
  $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $head = substr($raw,0,$hs); $headers=[]; $cookies=[];
  foreach (explode("\r\n",$head) as $line) { if (!strpos($line,':')) continue; [$k,$v]=array_map('trim',explode(':',$line,2)); $headers[strtolower($k)]=$v;
    if (strtolower($k)==='set-cookie') { $ck=new WP_Http_Cookie; [$nv]=explode(';',$v); [$ck->name,$ck->value]=explode('=',$nv,2); $cookies[]=$ck; } }
  return ['response'=>['code'=>$code],'headers'=>$headers,'body'=>substr($raw,$hs),'cookies'=>$cookies];
}
function wp_remote_get($u,$a=[]){ $a['method']='GET'; return wp_remote_request($u,$a); }
function wp_remote_retrieve_response_code($r){ return $r['response']['code']; }
function wp_remote_retrieve_header($r,$h){ return $r['headers'][strtolower($h)] ?? ''; }
function wp_remote_retrieve_body($r){ return $r['body']; }
function wp_remote_retrieve_cookies($r){ return $r['cookies']; }
define('HARMONY_ROZVRH_DIR', dirname(__DIR__) . '/');
foreach (['config','html','parser','memberzone-client','api-client','categories','repository','render'] as $f) require HARMONY_ROZVRH_DIR."includes/class-$f.php";
