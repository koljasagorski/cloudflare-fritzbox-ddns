<?php
// Use script authentication ?
$sAuth = 0;                 // 0 (False) / 1 (True)
$username = "a";    // used if script authentication is enabled 
$password = "b";

// Cloudflare DNS Update via API V4
$email = "cloudflare domain";                  // Cloudflare login e-mail
$apiKey = "003092f6de07aad6816afe8ce870499e58e8a";  // Cloudflare API Key

// DNS Record options
$enableProxy = false;  // Toggle Cloudflare proxying for the address  
$ttl = 1;              // TTL in seconds (1 for auto, or a value greater than 120)

// * Do not edit below this point, unless you know... meh, do what you want * //
// If auth is enabled check user&pass
if ($sAuth == 1) {
  if ($_REQUEST['user'] != $username || $_REQUEST['pass'] != $password) {
    exit ("Wrong credentials!");
  }
}

// Check minimum parameter requirements
if (!$_REQUEST['domain'] || !$_REQUEST['ipv4']) {
  exit ("Minimum parameter requirements not met!");
}

$zone = explode('.', $_REQUEST['domain'], 2)[1];  // domain
$dnsRecord = $_REQUEST['domain'];                 // subdomain 
$ip4 = $_REQUEST['ipv4'];                         // ipv4 address
if ($_REQUEST['ipv6']) {
  $ip6 = $_REQUEST['ipv6'];                       // ipv6 address
}

{ // get Zone ID
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "cache-control: no-cache",
    "X-Auth-Email: ".$email,
    "X-Auth-Key: ".$apiKey
  ),
));
$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
  exit ("cURL Error #:" . $err);
} else {
  $obj = json_decode($response,true);
  if( $obj["success"]!=1)
  {
    exit ("GetZoneId Failed");
  }
  $zones =($obj['result']);
  foreach($zones as $zoneResult) {
    if($zoneResult['name']==$zone)
        $zoneId = $zoneResult['id'];
  }
  if ($zoneId =="") {
    exit ("Zone not found!");
  }
}
} // end get Zone ID

{ // get Record ID
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/".$zoneId."/dns_records",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "Cache-Control: no-cache",
    "X-Auth-Email: ".$email,
    "X-Auth-Key: ".$apiKey
  ),
));
$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
  exit ("cURL Error #:" . $err);
} else {
  $obj = json_decode($response,true);
  if( $obj["success"]!=1) {
      exit ("GetRecordID Failed");
  }
  $zones =($obj['result']);
  foreach($zones as $zoneResult) {
      if($zoneResult['name'] == $dnsRecord)
          $recordId[$zoneResult['type']] = $zoneResult['id'];
  }
}
} // end get Record ID

{ // Update records
function updateCloudflare($zid, $rid, $eml, $aky, $d, $opr) {
  if ($rid!="") {
    $rid = "/".$rid;
    if ($opr!="DELETE") {
        $opr = "PUT";
    }
  }
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/".$zid."/dns_records".$rid,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => $opr,
    CURLOPT_HTTPHEADER => array(
      "Cache-Control: no-cache",
      "Content-Type: application/json",
      "X-Auth-Email:".$eml,
      "X-Auth-Key: ".$aky
    )
  ));
  if ($opr!="DELETE") {
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($d));
  }
  $response = curl_exec($curl);
  $err = curl_error($curl);
  curl_close($curl);
  if ($err) {
    exit ("cURL Error #:" . $err);
  } else {
    return json_decode($response);
  }
} // end function
// Update/Create ipv4 record
$data = array (
  'type' => 'A',
  'name' => $dnsRecord,
  'content' => $ip4,
  'proxiable' => true,
  'proxied' => $enableProxy,
  'ttl' => $ttl,
  'locked' => false,
  'zone_id' => $zoneId,
  'zone_name' => $zone
);
updateCloudflare($zoneId, $recordId['A'], $email, $apiKey, $data, "POST");
if ($ip6) {
  $data['type'] = 'AAAA';
  $data['content'] = $ip6;
  updateCloudflare($zoneId, $recordId['AAAA'], $email, $apiKey, $data, "POST");
} elseif ($recordId['AAAA']) { // we've received no ipv6 address but there is one on Cloudflare
  updateCloudflare($zoneId, $recordId['AAAA'], $email, $apiKey, $data, "DELETE");
}
} // end update records
?>
