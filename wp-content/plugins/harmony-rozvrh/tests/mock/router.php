<?php
// Falešný memberzone pro test: ASP.NET-like login + rozvrh s btnNext.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$authed = ($_COOKIE['_ASPXAUTH'] ?? '') === 'OK123';
file_put_contents('/tmp/mock.log', $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' auth='.(int)$authed.' '.json_encode(array_keys($_POST))."\n", FILE_APPEND);
if (!isset($_COOKIE['ASP.NET_SessionId'])) setcookie('ASP.NET_SessionId', 'sess'.rand(100,999), 0, '/');

function page($body, $vs) {
  $ev = 'EV' . md5($vs);
  echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
   . '<form method="post" action="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '" id="form1">'
   . '<div class="aspNetHidden"><input type="hidden" name="__EVENTTARGET" id="__EVENTTARGET" value="" /><input type="hidden" name="__EVENTARGUMENT" value="" />'
   . '<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="' . $vs . '" /></div>'
   . '<input type="hidden" name="__VIEWSTATEGENERATOR" value="ABCD1234" /><input type="hidden" name="__EVENTVALIDATION" value="' . $ev . '" />'
   . $body . '</form></body></html>';
}


// --- API MemberPro (Basic Auth, JSON, 404 = žádná data) ---
if (strpos($path, '/api/') === 0) {
  header('Content-Type: application/json; charset=utf-8');
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if ($auth !== 'Basic ' . base64_encode('servis@harmony:tajne')) { http_response_code(401); echo '"Unauthorized"'; exit; }
  $fx = __DIR__ . '/../fixtures/';
  if ($path === '/api/Room') { echo file_get_contents($fx . 'api-room-test.json'); exit; }
  if ($path === '/api/Categories') { echo json_encode([['id'=>1,'nazev'=>'Tenis','type'=>10],['id'=>51,'nazev'=>'Skupinová cvičení','type'=>2],['id'=>52,'nazev'=>'Spinning','type'=>2]]); exit; }
  if ($path === '/api/Lesson') {
    $ds = $_GET['date_start'] ?? '';
    if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $ds)) { http_response_code(500); echo '"String was not recognized as a valid DateTime."'; exit; }
    $from = DateTime::createFromFormat('!d.m.Y', $ds); $to = (clone $from)->modify('+' . (int)($_GET['pocet_dni_dopredu'] ?? 7) . ' days');
    $rows = array_values(array_filter(json_decode(file_get_contents($fx . 'api-lesson-test.json'), true), function ($l) use ($from, $to) {
      $d = new DateTime(substr($l['date_time'], 0, 19)); return $l['kategorie'] == $_GET['id_kategorie'] && $d >= $from && $d < $to; }));
    if (!$rows) { http_response_code(404); exit; }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
  }
  http_response_code(404); exit;
}

if ($path === '/HARMONYpisek/Uvod.aspx') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = ($_POST['__VIEWSTATE'] ?? '') === 'LOGINVS' && ($_POST['__EVENTVALIDATION'] ?? '') === 'EV'.md5('LOGINVS')
      && ($_POST['ctl00$cph$txtLogin'] ?? '') === 'servis@harmony' && ($_POST['ctl00$cph$txtHeslo'] ?? '') === 'tajne'
      && isset($_POST['ctl00$cph$btnPrihlasit']);
    if ($ok) {
      setcookie('.ASPXAUTH', 'OK123', 0, '/', '', false, true);
      header('Location: ' . ($_GET['ReturnUrl'] ?? '/HARMONYpisek/Uvod.aspx'), true, 302); exit;
    }
  }
  page('<div class="login"><label>E-mail</label><input name="ctl00$cph$txtLogin" type="text" id="txtLogin" />'
     . '<input name="ctl00$cph$txtHeslo" type="password" id="txtHeslo" />'
     . '<input type="submit" name="ctl00$cph$btnPrihlasit" value="Přihlásit" /></div>'
     . '<input name="ctl00$txtHledat" type="text" />', 'LOGINVS');
  exit;
}

if ($path === '/HARMONYpisek/StudioveHodiny.aspx') {
  if (!$authed) { header('Location: /HARMONYpisek/Uvod.aspx?ReturnUrl=%2fHARMONYpisek%2fStudioveHodiny.aspx', true, 302); exit; }
  $week = 0;
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vs = $_POST['__VIEWSTATE'] ?? '';
    if (!preg_match('/^W(\d)$/', $vs, $m) || ($_POST['__EVENTVALIDATION'] ?? '') !== 'EV'.md5($vs)) { http_response_code(500); echo 'Invalid viewstate'; exit; }
    $week = (int)$m[1] + (($_POST['__EVENTTARGET'] ?? '') === 'btnNext' ? 1 : (($_POST['__EVENTTARGET'] ?? '') === 'btnPrev' ? -1 : 0));
  }
  $monday = new DateTime('2026-11-16'); $monday->modify("+{$week} weeks");
  $L = [ // den, čas, délka, název, lektor, sál, obs, kap, cena
    [0,'08:30',55,'Zdravá záda','Eliška Nováková','Sál 1',6,14,'150,0000'],
    [0,'17:00',55,'Problémové partie','Edita K.','Sál 1',14,18,'150,0000'],
    [0,'18:10',50,'Kruháč 1','Vítek','Open gym',8,12,'130,0000'],
    [1,'17:00',55,'Jumping','Míša S.','Sál 2',18,18,'170,0000'],
    [1,'17:00',55,'Barre','Lucka S.','Sál 1',9,16,'150,0000'],
    [4,'15:30',90,'Jiu-jitsu','Harmony BJJ','Sál 2',4,20,'0,0000'],
    [4,'14:30',60,'Dětský kroužek','Harmony Academy','Sál 1',10,15,'100,0000'],
    [6,'19:00',55,'Fit aerobic','Ája P.','Sál 1',3,18,'150,0000'],
    [2,'18:00',55,'Lekce Novinka','Jana','Sál 1',0,10,'150,0000'],
  ];
  if ($week === 2) $L = array_slice($L, 0, 3); // kratší týden
  $html = '<a id="btnPrev" href="javascript:__doPostBack(&#39;btnPrev&#39;,&#39;&#39;)">&lt;</a> <span>Týden</span> <a id="btnNext" href="javascript:__doPostBack(&#39;btnNext&#39;,&#39;&#39;)">&gt;</a>';
  $html .= '<div class="rozvrh">';
  for ($d = 0; $d < 7; $d++) {
    $date = (clone $monday)->modify("+$d days");
    $html .= '<div class="den"><div class="den-hlavicka">' . ['Pondělí','Úterý','Středa','Čtvrtek','Pátek','Sobota','Neděle'][$d] . ' ' . $date->format('j.n.') . '</div>';
    $i = 0;
    foreach ($L as $k => $l) { if ($l[0] !== $d) continue; $i++;
      $p = 'ctl00$cph$rptDny$ctl0' . $d . '$rptLekce$ctl0' . $i . '$';
      [$hh,$mm] = explode(':', $l[1]);
      $html .= '<div class="lekce-karta" onclick="">'
       . '<input type="hidden" name="' . $p . 'hfID_Lekce" value="' . (46680 + $week*100 + $k) . '" />'
       . '<input type="hidden" name="' . $p . 'hfDatum2" value="' . $date->format('j.n.Y') . ' ' . (int)$hh . ':' . $mm . ':00" />'
       . '<input type="hidden" name="' . $p . 'hfDelka" value="30.12.1899 ' . intdiv($l[2],60) . ':' . sprintf('%02d', $l[2]%60) . ':00" />'
       . '<input type="hidden" name="' . $p . 'hfCena" value="' . $l[8] . '" />'
       . '<input type="hidden" name="' . $p . 'hfKapacita" value="' . $l[7] . '" />'
       . '<input type="hidden" name="' . $p . 'hfPocetRezervaci" value="' . $l[6] . '" />'
       . '<input type="hidden" name="' . $p . 'hfID_Sportoviste" value="' . ($l[5]==='Sál 2'?2:1) . '" />'
       . '<input type="hidden" name="' . $p . 'hfInstruktor" value="' . (100+$k) . '" />'
       . '<input type="hidden" name="' . $p . 'hfPopis_www" value="&lt;p&gt;Popis lekce ' . htmlspecialchars($l[3]) . '&lt;/p&gt;" />'
       . '<div class="cas">' . $l[1] . ' - ' . date('H:i', strtotime($l[1]) + $l[2]*60) . '</div>'
       . '<div class="nazev"><b>' . htmlspecialchars($l[3]) . '</b></div>'
       . '<div class="detail"><span class="instr">' . htmlspecialchars($l[4]) . '</span>&nbsp;<span class="sal">(' . $l[5] . ')</span></div>'
       . '<div class="obsazenost">' . $l[6] . '/' . $l[7] . '</div>'
       . '<a href="javascript:__doPostBack(&#39;' . $p . 'btnRez&#39;,&#39;&#39;)">Rezervovat</a>'
       . '</div>';
    }
    $html .= '</div>';
  }
  $html .= '</div>';
  page($html, 'W' . $week);
  exit;
}
http_response_code(404);
