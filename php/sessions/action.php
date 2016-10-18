<?php
 session_start();
 header("Content-Type: application/json; charset=UTF-8");

 $sessionId = $_REQUEST['sessionId'];
 $sessionFile = 'sessions/'.$sessionId;
 $action =  $_REQUEST['action'];

/* 
 $file = fopen('lucio.txt', 'a');
 fwrite($file, $_REQUEST['apc']);
 fclose($file);

 $file = fopen('lucio.txt', 'r');
 echo fread($file,filesize("lucio.txt"));
 fclose($file);
*/
 
 if(!$_SESSION['sessions'])
  $_SESSION['sessions'] = array();

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
  fwrite($file, $_REQUEST['roleName']."\n");
  fwrite($file, $_REQUEST['roleOptions']."\n");
  fclose($file);
  echo '{ "status": "Novo cargo registrado:'.$_REQUEST['roleName'].'"}';
 }

 if($action=='newVote'){
  $file = fopen($sessionFile, 'a');
  fwrite($file, $_REQUEST['roleVote']."\n");
  fclose($file);
  echo '{ "status": "Novo voto registrado:'.$_REQUEST['roleVote'].'"}';
 }

 if($action=='results'){
  $file = fopen($sessionFile, 'r');
  $roleName = fgets($file);
  $roleOptions = fgets($file);
  $roleVotes = array();
  echo realpath($sessionFile);
/*
  while(!feof($file))
   $roleVotes[] = fgets($file); 
  fclose($file);
*/
  $results = array("roleName"=>$roleName, "roleOptions"=>$roleOptions, "roleVotes"=>$roleVotes);

  echo json_encode($results);
 }

/*
 echo json_encode($_SESSION['sessions']);
*/
?>