<?php 

$BASE_URI = "http://peer2.pr-sandbox.smartb.network:9090/";

//-------------------------------------------------------
// sign
//-------------------------------------------------------
 
function sign($value, $privateKey) {
  openssl_sign($value, $signature, $privateKey, "sha256WithRSAEncryption");
  return base64_encode($signature);
}



//-------------------------------------------------------
// query
//-------------------------------------------------------
 
function query($fcn, $args) {
  global $BASE_URI;
  $request = "$BASE_URI?cmd=query&fcn=$fcn&args=$args";

  echo "Do query on: $request\n";

  $options = array(
    "http" => array(
      "header" => "Content-type: application/json\r\n",
      "method" => "GET",
      'ignore_errors' => true
    )
  );

  $context = stream_context_create($options);
  return file_get_contents($request, false, $context);
}



//-------------------------------------------------------
// getCurrentState
//-------------------------------------------------------

function getCurrentState($session) {
    $response = json_decode(query("log",$session), true);

    if (empty($response)) {
        echo "getCurrentState: No record found for ssm session '".$session."'\n";
        return;
    }
   
    return $response[0]["state"];
}



//-------------------------------------------------------
// invoke
//-------------------------------------------------------

function invoke($fcn, $data, $signerName, $signerKey) {
  global $BASE_URI;
  echo "Do invoke for fcn $fcn on $BASE_URI\n";

  $signature = sign($data, $signerKey);
  //echo "\nSignature: $signature\n" ;

  $body = array(
    "cmd" => "invoke",
    "fcn" => $fcn,
    "args" => array($data, $signerName, $signature)
  );

  $options = array(
    "http" => array(
      "header"  => "Content-type: application/json\r\n",
      "method"  => "POST",
      "content" => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'ignore_errors' => true
    )
  );

  $context = stream_context_create($options);
  $response = file_get_contents($BASE_URI, false, $context);
  echo json_encode(json_decode($response, true),JSON_PRETTY_PRINT);
  echo "\n\n--\n\n";
}



//-------------------------------------------------------
// perform
//-------------------------------------------------------

function perform($action, $state, $signerName, $keyFile) {
  global $BASE_URI;

  echo "Do invoke for fcn perform on $BASE_URI\n";

  $signerKey = openssl_pkey_get_private($keyFile);
  $state = str_replace(array("\n", "\r", " "), '', $state);
  $toSign = "$action$state";
  $signature = sign($toSign, $signerKey);
  //echo "\nSignature: $signature\n" ;

  $body = array(
    "cmd" => "invoke",
    "fcn" => "perform",
    "args" => array($action, $state, $signerName, $signature)
  );

  $options = array(
    "http" => array(
      "header"  => "Content-type: application/json\r\n",
      "method"  => "POST",
      "content" => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'ignore_errors' => true
    )
  );

  $context = stream_context_create($options);
  $response = file_get_contents($BASE_URI, false, $context);
  echo json_encode(json_decode($response, true),JSON_PRETTY_PRINT);
  echo "\n\n--\n\n";
}



//-------------------------------------------------------
// credit
//-------------------------------------------------------

function credit ($session, $token, $amount, $signerName, $keyFile) {
    $state = getCurrentState($session);
    
    if (is_null($state)) {
        return;
    }

    if (array_key_exists("public", $state) &&
        array_key_exists($token, $state["public"])) {
        $state["public"][$token]+=$amount;
    }
    else {
        $state["public"][$token]=$amount;
    }

    perform("Credit", json_encode($state), $signerName, $keyFile);
}



//-------------------------------------------------------
// debit
//-------------------------------------------------------

function debit ($session, $token, $amount, $signerName, $keyFile) {
    $state = getCurrentState($session);

    if (is_null($state)) {
        return;
    }

    if (array_key_exists("public", $state) &&
        array_key_exists($token, $state["public"])) {
        $state["public"][$token]-=$amount;
    }
    else {
        $state["public"][$token]=(-1*$amount);
    }

    perform("Debit", json_encode($state), $signerName, $keyFile);
}



//-------------------------------------------------------
// Example query 
//-------------------------------------------------------
// 
// list users
// query("list", "user");
// 
// list admins
// query("list", "admin");
// 
// list ssm
// query("list", "ssm");
// 
// list sessions
// query("list", "session");
// 
// get user bob's details
// query("user", "bob");
// 
// get admin adam's details
// query("admin", "adam");
// 
// get ssm 'account' description
// query("ssm", "account");
// 
// get details on the session handling account 1234
// query("session", "account-1234");



// loading the admin private key used in the invoke function
$privateKey = openssl_pkey_get_private("file://keys/ssm-admin");



//-------------------------------------------------------
// Invoke example: create ssm
//
// The create command can on only be executed by an admin
//-------------------------------------------------------
//
// $data = file_get_contents("account.json");
// invoke("create", $data, "ssm-admin", $privateKey);



//-------------------------------------------------------
// Invoke example: register user
//-------------------------------------------------------
//  
// $userPublicKey = file_get_contents("shop.pub");
// $userPublicKeyStr = str_replace(array("-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n"), array(), $userPublicKey);
// 
// $user = json_encode(array(
//   "name" => "shop",
//   "pub" => $userPublicKeyStr
// ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
// 
// invoke("register", $user, "ssm-admin", $privateKey);



//-------------------------------------------------------
// Perform a transition on a SSM session
// 
// perform (
//      <action>, 
//      <state>, 
//      <user>, 
//      <path-to-user-private-key>
// )
//
// The below is the equivalent of the credit function
//-------------------------------------------------------
//
// $state = getCurrentState("account-1234");
//
// $state = json_encode(array(
//   "session"   => "account-1234",
//   "iteration" => $cState["iteration"],
//   "public"    => array(
//       "bleu"    => 0,
//       "gris"    => 12,
//       "orange"  => 0,
//       "rose"    => $cState["public"]["rose"]+=5,
//       "rouge"   => 12,
//       "vert"    => 5
//   )
// ));
// 
//perform("Credit", json_encode($state), "bank", "file://keys/bank");



//-------------------------------------------------------
// Invoke example: start ssm
//
// The start command can on only be executed by an admin
//-------------------------------------------------------
//  
// $initialState = array(
//   "ssm"     => "account",
//   "session" => "account-5678",
//   "roles"   => array(
//       "bank"    => "Creditor",
//       "shop"    => "Debitor"
//   )
// );
// 
// invoke("start", json_encode($initialState), "ssm-admin", $privateKey);



//-------------------------------------------------------
// credit: wrapper around the perform function
//      add the specified $amount to the $token balance
//-------------------------------------------------------
// 
// credit(
//    <account-id>,   // ssm session 
//    <token>, 
//    <amount>, 
//    <credito-name>, 
//    <path-to-creditor-priv-key>
// )

credit("account-5678", "vert", 1, "bank", "file://keys/bank");



//-------------------------------------------------------
// debit: wrapper around the perform function
//      decuced the specified $amount from $token 
//-------------------------------------------------------
// 
// debit(
//    <account-id>,   // ssm session 
//    <token>, 
//    <amount>, 
//    <credito-name>, 
//    <path-to-creditor-priv-key>
// )

debit("account-5678", "rouge", 4, "shop", "file://keys/shop");



//-------------------------------------------------------
// Display the current state for a session
//-------------------------------------------------------
 
$cState = getCurrentState("account-5678");

echo "\n-----------------\nCURRENT STATE:\n-----------------\n";
echo  json_encode($cState, JSON_PRETTY_PRINT);
echo "\n-----------------\n";

?>

