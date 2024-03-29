var app = angular.module("votaction", []);

app.service('actionService', function ($http) {
  this.getData = function (scope, action) {
    console.log("Action:" + action);
    return $http({
      method: 'GET',
      url: 'action.php?sessionId=' + scope.sessionId + '&action=' + action + '&roleName=' + scope.roleName + '&roleOptions=' + scope.roleOptionsTxt + '&roleMaxOptions=' + scope.roleMaxOptions + '&roleVote=' + scope.roleVote + '&clientSeq=' + scope.clientSeq
    }).success(function (response) {
      return response;
    }).error(function (response) {
      beepError();
      return null;
    });
  }
});

function promptSessionId(promptStr){
    return prompt(promptStr).trim();
}

var stop = undefined;
app.controller("adminCtrl", function ($scope, actionService, $interval) {
  $scope.status = "menu";
  $scope.roleName = "NO_DATA";
  $scope.roleOptionsTxt = "";
  $scope.roleOptions = [];
  $scope.roleMaxOptions = 1;
  $scope.roleVote = "";
  $scope.roleVotesCount = 0;
  $scope.roleVotes = [];
  $scope.clientsVotes = [];

  $scope.sessionId = "Não definido"; // Defina com um ID válido para agilizar os teste

  $scope.clientSeq = 0;

  $scope.startNewRole = function () {
    $scope.status = "newRole";
    $('#roleName').focus(); // Focus

    $scope.roleName = "";
    $scope.roleOptionsTxt = "";
    $scope.roleOptions = [];
    $scope.roleMaxOptions = 1;

    $scope.suggestions = "";

    if (stop) {
      $interval.cancel(stop);
      stop = undefined;
    }
  };

  $scope.confirmNewRole = function () {
    $scope.message = "Registrando novo cargo...";

    $scope.roleOptionsTxt = $scope.roleOptionsTxt.replace(/\n/g, "<br>");
    actionService.getData($scope, 'newRole').then(
      function (response) {
        $('#myTab li:first-child a').tab('show'); // Focus
        $scope.message = response.data.status;
        beepOk();
        setTimeout($scope.startResults, 1000);
      });
  };

  $scope.startVote = function () {
    $scope.status = "waitNewRole";
    $('#roleOptionsTxt').focus(); // Focus
    $scope.message = "Aguardando nova votação...";
    $scope.roleVote = "";
    actionService.getData($scope, 'startVote').then(
      function (response) {
        try {
          if (response.data.roleName && response.data.roleName != "" && $scope.roleName != response.data.roleName) {
            $scope.roleName = response.data.roleName;
            $scope.clientSeq = response.data.clientSeq;
            $scope.roleOptions = new Array();
            var options = response.data.roleOptions.split("<br>");
            for (var i in options) {
              $scope.roleOptions.push({ "name": options[i], "selected": false });
            }
            $scope.roleMaxOptions = response.data.roleMaxOptions;
            $scope.status = "vote";
            $scope.message = "Votação autorizada";
          } else {
            $scope.message = response.data.status;
            setTimeout($scope.startVote, 2000);
          }
        } catch (error) {
          $scope.message = error.message;
          setTimeout($scope.startVote, 2000);
        }
      });
  };

  $scope.updateRoleVote = function () {
    $scope.roleVote = [];
    for (var i in $scope.roleOptions)
      if ($scope.roleOptions[i].selected)
        $scope.roleVote.push($scope.roleOptions[i].name);
    if ($scope.roleVote.length < $scope.roleMaxOptions)
      $scope.message = "Selecione mais " + ($scope.roleMaxOptions - $scope.roleVote.length) + " opção(ões).";
    else if ($scope.roleVote.length > $scope.roleMaxOptions)
      $scope.message = "Desmarque " + ($scope.roleVote.length - $scope.roleMaxOptions) + " opção(ões).";
    else $scope.message = "Tudo certo! Clique em confirmar.";
  };

  $scope.confirmVote = function () {
    $scope.roleVote = [];
    for (var i in $scope.roleOptions)
      if ($scope.roleOptions[i].selected)
        $scope.roleVote.push($scope.roleOptions[i].name);
    if ($scope.roleVote.length != $scope.roleMaxOptions) {
      $scope.message = "Erro: Você selecionou " + $scope.roleVote.length + " opções. Escolha " + $scope.roleMaxOptions + ".";
      beepError();
      return;
    }
    $scope.status = "confirmVote";
    $scope.message = "Registrando o voto...";
    actionService.getData($scope, 'newVote').then(
      function (response) {
        $scope.message = response.data.status;
        if (response.data.error == 0)
          beepOk();
        else
          beepError();
        setTimeout($scope.startVote, 5000);
      });
  };

  $scope.startResults = function () {
    $scope.status = "results";
    if ($scope.sessionId == "Não definido")
      $scope.sessionId = promptSessionId("Digite a chave da nova sessão de votação que você está criando");
    else {
    }

    stop = $interval($scope.getResults, 2000);
  };

  $scope.getResults = function (callback) {
      $scope.message = "Atualizando...";
      actionService.getData($scope, 'results').then(
        function (response) {
          if (response.data.error) {
            $scope.message = response.data.status;
          } else {
            $scope.roleName = response.data.roleName;
            $scope.roleMaxOptions = response.data.roleMaxOptions;
            $scope.roleVotesCount = 0;
            $scope.sessionAdmin = response.data.sessionAdmin;

            $scope.clientSeq = response.data.clientSeq;
            var votes = {};
            response.data.roleVotes.forEach(function (item, index) {
              if (votes[item]) votes[item] = votes[item] + 1;
              else votes[item] = 1;
              $scope.roleVotesCount++;
            });
            $scope.roleVotes = [];
            for (var k in votes) {
              $scope.roleVotes.push({ "roleName": k, "total": votes[k] });
            }
            $scope.roleVotes.sort((a,b)=>b.total-a.total);

            // Check tied first to use it in elected expression check
            $scope.roleVotes.forEach((item,index)=>{item.tied = (index<$scope.roleMaxOptions && $scope.roleVotes.length>$scope.roleMaxOptions && $scope.roleVotes[$scope.roleMaxOptions].total==item.total) || (index>=$scope.roleMaxOptions && $scope.roleVotes[$scope.roleMaxOptions-1].total==item.total)});

            $scope.roleVotes.forEach((item,index)=>{item.elected = index<$scope.roleMaxOptions && !item.tied});

            $scope.clientsVotes = new Array($scope.clientSeq).fill(false);

            response.data.votesClients.forEach((item, index)=>{$scope.clientsVotes[+item-1] = true});

            $scope.message = "Atualizado";

            if(typeof callback == 'function') callback();
          }
        });
  }

  $scope.newSession = function () {
    $scope.sessionId = promptSessionId("Digite a chave da nova sessão de votação que você está criando");
    $scope.message = "Iniciando nova sessão " + $scope.sessionId + "...";

    actionService.getData($scope, 'newSession').then(
      function (response) {
        $scope.message = response.data.status;
        if (response.data.error == 0) {
          $scope.startNewRole();
          $scope.sessionAdmin = true; // Identify when a user starts a session and may see results and add new role
        }
      });
  };

  $scope.joinSession = function () {
    $scope.sessionId = promptSessionId("Digite a chave da sessão de votação na qual você quer participar");
    $scope.startVote();
  };

  $scope.shareSession = function () {
    const shareData = {
      title: 'Vote :' + $scope.roleName,
      text: 'Participe da votação para :' + $scope.roleName,
      url: './?' + $scope.sessionId,
    };
    if (navigator.share)
      navigator.share(shareData);
    else{
      navigator.clipboard.writeText(document.location.href + '?' + $scope.sessionId);
    }
  };

  $scope.suggest = function () {
    $scope.roleOptionsTxt = $scope.roleOptionsTxt.replace(/\n/g, "<br>");
    actionService.getData($scope, 'suggest').then(
      function (response) {
        $scope.message = response.data.status;
        if (response.data.error == 0) {
          $scope.roleOptionsTxt = "";
        } else {
          beepError();
        }
      });
  };

  $scope.getSuggestions = function () {
    actionService.getData($scope, 'getSuggestions').then(
      function (response) {
        if (response.data.error == 0) {
          $scope.roleOptionsTxt = response.data.status.join("\n");
          $scope.message = "Sugestões obtidas com sucesso.";
        } else {
          $scope.message = response.data.status;
          beepError();
        }
      });
  };

  $scope.sortSuggestions = function () {
      $scope.roleOptionsTxt = $scope.roleOptionsTxt.split('\n').sort((a,b)=> a.toLowerCase().localeCompare(b.toLowerCase())).join('\n');
      $scope.message = "Nomes ordenados com sucesso.";
  };

  $scope.pasteLastResult = function () {
      $scope.getResults(function(){
        result = "";
        $scope.roleVotes.forEach(function (item, index){
            result += (index>0?"\n":"") + item.roleName + " " + (item.elected?"(":"") + (item.tied?"[":"") + item.total+ (item.elected?")":"") + (item.tied?"]":"");
        });
        $scope.roleName += "_";
        $scope.roleOptionsTxt = result;
        $scope.message = "Resultado colado com sucesso.";
      });
  };

  $scope.copyResult = function () {
      result = "";
      $scope.roleVotes.forEach(function (item, index){
        result += (index>0?"\n":"") + (index+1) + "º - " + item.roleName + " " + (item.elected?"(Eleito)":"") + (item.tied?"(Empate)":"") + item.total + " voto" + (item.total==1?"":"s");
      });
      navigator.clipboard.writeText(result);
      $scope.message = "Resultado copiado com sucesso.";
  };

  $scope.copyResultNames = function () {
      result = "";
      $scope.roleVotes.forEach(function (item, index){
        result += (index>0?"\n":"") + item.roleName;
      });
      navigator.clipboard.writeText(result);
      $scope.message = "Nomes copiados com sucesso.";
  };



  //Detecta sessão passada na URL
  if (window.location.search != "") {
    $scope.sessionId = window.location.search.substring(1);
    $scope.startVote();
  };
});

function beepError() {
  var snd = new Audio("data:audio/wav;base64,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qJrx4KOQjahZxWbcZgztj2c49nKmkId44S71j0c8eV9yDK6uPRzx5X18eDvjvQ6yKo9ZSS6l//8elePK/Lf//IInrOF/FvDoADYAGBMGb7FtErm5MXMlmPAJQVgWta7Zx2go+8xJ0UiCb8LHHdftWyLJE0QIAIsI+UbXu67dZMjmgDGCGl1H+vpF4NSDckSIkk7Vd+sxEhBQMRU8j/12UIRhzSaUdQ+rQU5kGeFxm+hb1oh6pWWmv3uvmReDl0UnvtapVaIzo1jZbf/pD6ElLqSX+rUmOQNpJFa/r+sa4e/pBlAABoAAAAA3CUgShLdGIxsY7AUABPRrgCABdDuQ5GC7DqPQCgbbJUAoRSUj+NIEig0YfyWUho1VBBBA//uQZB4ABZx5zfMakeAAAAmwAAAAF5F3P0w9GtAAACfAAAAAwLhMDmAYWMgVEG1U0FIGCBgXBXAtfMH10000EEEEEECUBYln03TTTdNBDZopopYvrTTdNa325mImNg3TTPV9q3pmY0xoO6bv3r00y+IDGid/9aaaZTGMuj9mpu9Mpio1dXrr5HERTZSmqU36A3CumzN/9Robv/Xx4v9ijkSRSNLQhAWumap82WRSBUqXStV/YcS+XVLnSS+WLDroqArFkMEsAS+eWmrUzrO0oEmE40RlMZ5+ODIkAyKAGUwZ3mVKmcamcJnMW26MRPgUw6j+LkhyHGVGYjSUUKNpuJUQoOIAyDvEyG8S5yfK6dhZc0Tx1KI/gviKL6qvvFs1+bWtaz58uUNnryq6kt5RzOCkPWlVqVX2a/EEBUdU1KrXLf40GoiiFXK///qpoiDXrOgqDR38JB0bw7SoL+ZB9o1RCkQjQ2CBYZKd/+VJxZRRZlqSkKiws0WFxUyCwsKiMy7hUVFhIaCrNQsKkTIsLivwKKigsj8XYlwt/WKi2N4d//uQRCSAAjURNIHpMZBGYiaQPSYyAAABLAAAAAAAACWAAAAApUF/Mg+0aohSIRobBAsMlO//Kk4soosy1JSFRYWaLC4qZBYWFRGZdwqKiwkNBVmoWFSJkWFxX4FFRQWR+LsS4W/rFRb/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////VEFHAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAU291bmRib3kuZGUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMjAwNGh0dHA6Ly93d3cuc291bmRib3kuZGUAAAAAAAAAACU=");
  snd.volume = 1;
  snd.play();
}

function beepOk() {
  var snd = new Audio("toque.mp3");
  snd.play();
}