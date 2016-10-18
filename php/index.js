var app = angular.module("votaction", []); 

app.service('actionService', function($http) {
  this.getData = function(scope, action) {
      console.log("Action:"+action);
      return $http({
          method: 'GET',
          url: 'action.php?sessionId=' + scope.sessionId + '&action=' + action + '&roleName=' + scope.roleName+ '&roleOptions=' + scope.roleOptions+ '&roleVote=' + scope.roleVote
      }).success(function(response){
        return response;
      }).error(function(){
         alert("error");
         return null ;
      });
   }
 });

var stop = undefined;
app.controller("adminCtrl", function($scope, actionService, $interval) {
        $scope.status = "menu";
        $scope.roleName = "";
        $scope.roleOptions = "";
        $scope.roleVote = "";

        $scope.sessionId = "Não definido";

        $scope.startNewRole = function(){
           $scope.status = "newRole";

           $scope.roleName = "";
           $scope.roleOptions = "";

           if(stop){
              $interval.cancel(stop);
              stop = undefined;
           }
        };

        $scope.startVote = function(){
           $scope.status = "waitNewRole";
           $scope.message = "Aguardando nova votação...";
           $scope.roleVote = "";
           actionService.getData($scope, 'results').then(
             function(response){
               if($scope.roleName != response.data.roleName){
                 $scope.roleName = response.data.roleName;
                 $scope.roleOptions = response.data.roleOptions.split("<br>");
                 $scope.status = "vote";
                 $scope.message = "Votação autorizada";
               }else{
                  setTimeout($scope.startVote, 2000);
               }
             });
        };

        $scope.confirmVote = function(){
           $scope.status = "confirmVote";
           $scope.message = "Registrando o voto...";
           actionService.getData($scope, 'newVote').then(
             function(response){
                $scope.message = response.data.status;
                setTimeout($scope.startVote, 3000);
             });
        };

        $scope.startResults = function(){
           $scope.status = "results";
           if($scope.sessionId == "Não definido")
             $scope.sessionId = prompt("Digite a chave da nova sessão de votação que você está criando");
           else{
            $scope.roleOptions = $scope.roleOptions.replace(/\n/g, "<br>");
            actionService.getData($scope, 'newRole').then(
             function(response){
                $scope.message = response.data.status;
             });
            }

           stop = $interval(function(){
             $scope.message = "Atualizando...";
             actionService.getData($scope, 'results').then(
               function(response){
                 $scope.roleName = response.data.roleName;
                 $scope.roleVotesCount = 0;
                 var votes  = {};
                 response.data.roleVotes.forEach(function(item, index){
                   if(votes[item]) votes[item] = votes[item] +1;
                   else votes[item] = 1;
                   $scope.roleVotesCount++;
                 });
                 $scope.roleVotes = [];
                 for(var k in votes){
                   $scope.roleVotes.push({"roleName": k, "total": votes[k]});
                 }
                 $scope.message = "Atualizado";
               });
          }, 2000);
        };

        $scope.newSession = function(){
           $scope.sessionId = prompt("Digite a chave da nova sessão de votação que você está criando");

           actionService.getData($scope, 'newSession').then(
             function(response){
                $scope.message = response.data.status;
             });

           $scope.startNewRole();
        };

        $scope.joinSession = function(){
            $scope.sessionId = prompt("Digite a chave da sessão de votação na qual você quer participar");
            $scope.startVote();
        };

});