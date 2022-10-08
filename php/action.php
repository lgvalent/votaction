<?php
/* https://sourceforge.net/p/phpcrawl/discussion/307696/thread/35f1252e/ Windows system doesn't have sem_get() */
if (!function_exists('sem_get')) {
  function sem_get($key) {
    return fopen(__FILE__ . '.sem.' . $key, 'w+');
  }
  function sem_acquire($sem_id) {
    return flock($sem_id, LOCK_EX);
  }
  function sem_release($sem_id) {
    return flock($sem_id, LOCK_UN);
  }
}

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

 header('Content-Type: application/json; charset=UTF-8');

 $sessionId = $_REQUEST['sessionId'];
 $action =  $_REQUEST['action'];
 $token = sem_get(1, 1); // Control concurrent access

 $sessionFile = 'sessions/'.$sessionId;
 $sessionVotesFile = 'sessions/'.$sessionId.'.votes';
 $sessionClientsFile = 'sessions/'.$sessionId.'.clients';
 $sessionSuggestionsFile = 'sessions/'.$sessionId.'.suggestions';

 if (!file_exists('sessions')) mkdir('sessions', 0777, true);
 
 /** Client identification */
 $cookieClientID = 'clientId';
 $clientId;
 if(isset($_COOKIE[$cookieClientID]))
   $clientId = $_COOKIE[$cookieClientID];
 else {
   $clientId = uniqid();
   setcookie($cookieClientID, $clientId, strtotime( '+30 days' ) );
 }

 if($action=='reset'){
  if (file_exists($sessionFile)) {
        unlink($sessionFile);
        unlink($sessionVotesFile);
        unlink($sessionClientsFile);
        unlink($sessionSuggestionsFile);
    }
  echo '{ "error": 0, "status": "Sessão apagada:'.$sessionId.'"}';
 }

 if($action=='newSession'){
  if (!isSessionOwner($sessionFile, $clientId)){
    echo '{ "error": 1, "status": "Sessão já existente. Utilize a ação RESET para apagá-la:'.$sessionId.'/'.$sessionOwner.'/'.$clientId.'"}';
  }else{
    sem_acquire($token);
    $file = fopen($sessionFile, 'w');
    $fileS = fopen($sessionSuggestionsFile, 'w');
    if(!$file)
      echo '{ "error": 1, "status": "Erro ao registrar nova sessão '.$_REQUEST['sessionId'].'"}';
    else{
      fwrite($file, $clientId.PHP_EOL);
      fclose($file);
      fclose($fileS);
      echo '{ "error": 0, "status": "Sessão registrada:'.$sessionId.'"}';
    }
    sem_release($token); 
  }
 }

 if($action=='newRole'){
  if (isSessionOwner($sessionFile, $clientId)){
    sem_acquire($token);
    $file = fopen($sessionFile, 'w');
    $fileV = fopen($sessionVotesFile, 'w');
    $fileC = fopen($sessionClientsFile, 'w');
    $fileS = fopen($sessionSuggestionsFile, 'w');
    if(!$file OR !$fileV OR !$fileC)
      echo '{ "error": 1, "status": "Erro ao abrir votação da sessão '.$_REQUEST['sessionId'].'"}';
    else{
      fwrite($file, $clientId.PHP_EOL);
      fwrite($file, $_REQUEST['roleName'].PHP_EOL);
      fwrite($file, $_REQUEST['roleOptions'].PHP_EOL);
      fwrite($file, $_REQUEST['roleMaxOptions'].PHP_EOL);

      fwrite($fileC, 1);

      fclose($file);
      fclose($fileV);
      fclose($fileC);
      fclose($fileS);

      echo '{ "error": 0, "status": "Novo cargo registrado:'.$_REQUEST['roleName'].'"}';
    }
    sem_release($token); 
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
  $fileV = fopen($sessionVotesFile, 'r');
  if(!$file OR !$fileV)
    echo '{ "error": 1, "status": "Erro ao registrar votação na sessão '.$_REQUEST['sessionId'].'"}';
  else{
    /** Check one vote per client */
    $foundVote = false;
    while(!feof($fileV)){
	  $line = rtrim(fgets($fileV));
      $vote = explode ("," , $line);
      if($vote[0] == $clientId){
         $foundVote = true;
         break;
      }
    }
    fclose($fileV);
    $fileV = fopen($sessionVotesFile, 'a');
	  
    if($foundVote){
      echo '{ "error": 1, "status": "Erro ao votar. Voto já foi registrado anteriormente para '.$_REQUEST['roleName'].'"}';
    }else{
      $roleVotes = explode ("," , $_REQUEST['roleVote']);
      foreach ($roleVotes as &$value) {
        fwrite($file, $value.PHP_EOL);
      }    
      fwrite($fileV, $clientId.",".$_REQUEST['clientSeq'].PHP_EOL);
      echo '{ "error": 0, "status": "Novo voto registrado para '.$_REQUEST['roleVote'].'"}';
    }

    fclose($fileV);
    fclose($file);
  
  }
  sem_release($token); 
 }

 if($action=='startVote'){
  sem_acquire($token);
  $file = fopen($sessionFile, 'r');
  $fileV = fopen($sessionVotesFile, 'r');
  $fileC = fopen($sessionClientsFile, 'r');
  if(!$file OR !$fileV OR !$fileC)
    echo '{ "error": 1, "status": "Erro ao iniciar a votação na sessão '.$_REQUEST['sessionId'].'"}';
  else{
    /** Check one vote per client */
    $foundVote = false;
    while(!feof($fileV)){
	  $line = rtrim(fgets($fileV));
      $vote = explode ("," , $line);
      if($vote[0] == $clientId){
         $foundVote = true;
         break;
      }
    }
    
    if(!$foundVote){
        $sessionOwner = rtrim(fgets($file));
        $roleName = rtrim(fgets($file));
        $roleOptions = rtrim(fgets($file));
        $roleMaxOptions = (int)rtrim(fgets($file));

        $clientSeq =  (int)rtrim(fgets($fileC));

        fclose($fileC);
        $fileC = fopen($sessionClientsFile, 'w');
        fwrite($fileC, $clientSeq+1);
    
        $results = array("roleName"=>$roleName, "roleOptions"=>$roleOptions, "roleMaxOptions"=>$roleMaxOptions, "clientSeq"=>$clientSeq, "sessionAdmin"=> ($sessionOwner==$clientId));

        echo json_encode($results);
    }else
       echo '{ "error": 1, "status": "Voto já foi registrado anteriormente para '.$_REQUEST['roleName'].'. Aguarde a próxima votação."}';

  }
  fclose($file);
  fclose($fileV);
  fclose($fileC);

  sem_release($token);
 }

 if($action=='results'){
  $file = fopen($sessionFile, 'r');
  $fileV = fopen($sessionVotesFile, 'r');
  $fileC = fopen($sessionClientsFile, 'r');
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

    $votesClients = array();
    while(!feof($fileV)){
      $voteClientSeq = explode ("," , fgets($fileV))[1];
      if($voteClientSeq) $votesClients[] = rtrim($voteClientSeq);
    }

    $clientSeq = (int)rtrim(fgets($fileC)-1);

    fclose($file);
    fclose($fileV);
    fclose($fileC);

    $results = array("roleName"=>$roleName, "roleOptions"=>$roleOptions, "roleMaxOptions"=>$roleMaxOptions, "roleVotes"=>$roleVotes, "votesClients"=>$votesClients, "clientSeq"=>$clientSeq, "sessionAdmin"=> ($sessionOwner==$clientId));

    echo json_encode($results);
  }
 }

 if($action=='suggest'){
  sem_acquire($token);
  $fileS = fopen($sessionSuggestionsFile, 'r');
  if(!$fileS)
    echo '{ "error": 1, "status": "Erro ao abrir arquivos de sugestões da sessão '.$_REQUEST['sessionId'].'"}';
  else{
    $suggestions = explode ("<br>" , $_REQUEST['roleOptions']);
    while(!feof($fileS)){
	  $line = rtrim(fgets($fileS));
      $i = count($suggestions);
      while($i > 0){
        if(strtoupper($line) == strtoupper($suggestions[$i-1]))
            unset($suggestions[$i-1]);
        $i--;  
      }
    }
    if(count($suggestions)>0){
        fclose($fileS);
        $fileS = fopen($sessionSuggestionsFile, 'a');
        foreach ($suggestions as &$value) {
            fwrite($fileS, $value.PHP_EOL);
        }    
       echo '{ "error": 0, "status": "Sugestões registradas com sucesso! Aguarde a votação."}';
    }else
       echo '{ "error": 0, "status": "Nenhuma sugestão nova encontrada. Aguarde a próxima votação."}';

  }
  fclose($fileS);
  sem_release($token);
 }

 if($action=='getSuggestions'){
  $fileS = fopen($sessionSuggestionsFile, 'r');
  if(!$fileS)
    echo '{ "error": 1, "status": "Erro ao abrir arquivos de sugestões da sessão '.$_REQUEST['sessionId'].'"}';
  else{
    $results = file($sessionSuggestionsFile, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
    echo '{ "error": 0, "status": '.json_encode($results).'}';
  }
  fclose($fileS);
 }


?>
