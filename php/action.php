<?php
 $sessionOwner = null;
 function isSessionOwner($sessionFile, $clientId){
  global $sessionOwner;
  global $sessionRoleActive;
  if (file_exists($sessionFile)) {
     $file = fopen($sessionFile, 'r');
     $sessionOwner = rtrim(fgets($file));
     fclose($file);
     return $sessionOwner == $clientId;
   }
   return true;
 }

 /** Client identification */
 $cookieNameClientID = 'clientId';
 $clientId;
 if(isset($_COOKIE[$cookieNameClientID]))
   $clientId = $_COOKIE[$cookieNameClientID];
 else {
   $clientId = uniqid();
   setcookie($cookieNameClientID, $clientId, strtotime( '+30 days' ) );
 }

 header('Content-Type: application/json; charset=UTF-8');

 $sessionId = $_REQUEST['sessionId'];
 $action =  $_REQUEST['action'];
 $token = sem_get(1, 1); // Control concurrent access

 $sessionFile = 'sessions/'.$sessionId;
 $sessionVotesFile = 'sessions/'.$sessionId.'.votes';
 if (!file_exists('sessions')) mkdir('sessions', 0777, true);
 
 if($action=='reset'){
  if (file_exists($sessionFile)) {
        unlink($sessionFile);
    }
  echo '{ "error": 0, "status": "Sessão apagada:'.$sessionId.'"}';
 }

 if($action=='newSession'){
  if (!isSessionOwner($sessionFile, $clientId)){
    echo '{ "error": 1, "status": "Sessão já existente. Utilize a ação RESET para apagá-la:'.$sessionId.'/'.$sessionOwner.'/'.$clientId.'"}';
  }else{
    sem_acquire($token);
    $file = fopen($sessionFile, 'w');
    if(!$file)
      echo '{ "error": 1, "status": "Erro ao registrar nova sessão '.$_REQUEST['sessionId'].'"}';
    else{
      fwrite($file, $clientId.PHP_EOL);
      fclose($file);
      sem_release($token); 
      echo '{ "error": 0, "status": "Sessão registrada:'.$sessionId.'"}';
    }
  }
 }

 if($action=='newRole'){
  if (isSessionOwner($sessionFile, $clientId)){
    sem_acquire($token);
    $file = fopen($sessionFile, 'w');
    $fileV = fopen($sessionVotesFile, 'w');
    if(!$file OR !$fileV)
      echo '{ "error": 1, "status": "Erro ao abrir votação da sessão '.$_REQUEST['sessionId'].'"}';
    else{
      fwrite($file, $clientId.PHP_EOL);
      fwrite($file, $_REQUEST['roleName'].PHP_EOL);
      fwrite($file, $_REQUEST['roleOptions'].PHP_EOL);
      fwrite($file, $_REQUEST['roleMaxOptions'].PHP_EOL);
      fclose($file);

      fwrite($fileV, $clientId.PHP_EOL);
      fclose($fileV);

      sem_release($token); 
      echo '{ "error": 0, "status": "Novo cargo registrado:'.$_REQUEST['roleName'].'"}';
    }
  }else{
   echo '{ "error": 1, "status": "Sessão controlada por outro cliente:'.$_REQUEST['sessionId'].'"}';
  }
 }

 if($action=='newVote'){
  $file = fopen($sessionFile, 'r');
  if(!$file){
    echo '{ "error": 1, "status": "Erro ao abrir votação da sessão '.$_REQUEST['sessionId'].'"}';
    return;
  }else{
    $sessionOwner = rtrim(fgets($file));
    $roleName = rtrim(fgets($file));
  }

  if($roleName != $_REQUEST['roleName']){
    echo '{ "error": 1, "status": "A votação atual não corresponde mais a '.$_REQUEST['roleName'].'"}';
    return;
  }

  sem_acquire($token);

  $file = fopen($sessionFile, 'a');
  $fileV = fopen($sessionVotesFile, 'a');
  if(!$file OR !$fileV)
    echo '{ "error": 1, "status": "Erro ao registrar votação na sessão '.$_REQUEST['sessionId'].'"}';
  else{
    /** Check one vote per client */
    $foundVote = false;
//    while(!feof($fileV)){
//      $line = fgets($fileV);
//      if($line == $clientId){
//         $foundVote = true;
//         break;
//      }
//    }

    if($foundVote){
      echo '{ "error": 1, "status": "Erro ao votar. Voto já foi registrado anteriormente para '.$_REQUEST['roleVote'].'"}';
    }else{
      $roleVotes = explode ("," , $_REQUEST['roleVote']);
      foreach ($roleVotes as &$value) {
        fwrite($file, $value.PHP_EOL);
      }    
      fwrite($fileV, $clientId.PHP_EOL);
      echo '{ "error": 0, "status": "Novo voto registrado para '.$_REQUEST['roleVote'].'"}';
    }

    fclose($fileV);
    fclose($file);
  
    sem_release($token); 
  }
 }

 if($action=='results'){
  $file = fopen($sessionFile, 'r');
  if(!$file)
    echo '{ "error": 1, "status": "Erro ao abrir votação da sessão '.$_REQUEST['sessionId'].'"}';
  else{
    $sessionOwner = rtrim(fgets($file));
    $roleName = rtrim(fgets($file));
    $roleOptions = rtrim(fgets($file));
    $roleMaxOptions = (int)rtrim(fgets($file));
    $roleVotes = array();
    while(!feof($file)){
      $line = fgets($file);
      if($line) $roleVotes[] = rtrim($line); //Last line empty
    }
    fclose($file);

    $results = array("roleName"=>$roleName, "roleOptions"=>$roleOptions, "roleMaxOptions"=>$roleMaxOptions, "roleVotes"=>$roleVotes, "sessionAdmin"=> ($sessionOwner==$clientId));

    echo json_encode($results);
  }

 }
?>
