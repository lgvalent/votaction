<?php
 header("Content-Type: application/json; charset=UTF-8");

 $sessionId = $_REQUEST['sessionId'];
 $action =  $_REQUEST['action'];

 $sessionFile = 'sessions/'.$sessionId;
 if (!file_exists('sessions')) mkdir('sessions', 0777, true);
 
 if($action=='reset'){
  if (file_exists($sessionFile)) {
        unlink($sessionFile);
    }
  echo '{ "status": "Sessão apagada:'.$sessionId.'"}';
 }

 if($action=='newSession'){
  $file = fopen($sessionFile, 'w');
  fclose($file);
  echo '{ "status": "Sessão registrada:'.$sessionId.'"}';
 }

 if($action=='newRole'){
  $file = fopen($sessionFile, 'w');
  fwrite($file, $_REQUEST['roleName'].PHP_EOL);
  fwrite($file, $_REQUEST['roleOptions'].PHP_EOL);
  fclose($file);
  echo '{ "status": "Novo cargo registrado:'.$_REQUEST['roleName'].'"}';
 }

 if($action=='newVote'){
  $token = sem_get($action, 1); // Control concurrent access
  sem_acquire($token);

  $file = fopen($sessionFile, 'a');
  fwrite($file, $_REQUEST['roleVote'].PHP_EOL);
  fclose($file);
  
  sem_release($token); 
  
  echo '{ "status": "Novo voto registrado:'.$_REQUEST['roleVote'].'"}';
 }

 if($action=='results'){
  $file = fopen($sessionFile, 'r');
  $roleName = rtrim(fgets($file));
  $roleOptions = rtrim(fgets($file));
  $roleVotes = array();
  while(!feof($file)){
   $line = fgets($file);
   if($line) $roleVotes[] = rtrim($line); //Last line empty
  }
  fclose($file);

  $results = array("roleName"=>$roleName, "roleOptions"=>$roleOptions, "roleVotes"=>$roleVotes);

  echo json_encode($results);
 }
?>