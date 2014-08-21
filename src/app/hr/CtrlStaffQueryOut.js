define([
  'app',
  'hr/ServiceStaff',
], function (app) {
  app.registerController('CtrlStaffQueryOut', [
    '$scope',
    '$rootScope',
    '$filter',
    '$window',
    'Staff',
    'CModal',
  function ($scope, $rootScope, $filter, $window, Staff, CModal) {
    $scope.checkPagePrivilage('HR_QUERY');
    $rootScope.appState = 'hr';
    //员工信息
    Staff.initQueryOut($scope);

    $scope.orgChanged = function (level) {
      Staff.orgClear($scope, level);
      Staff.resetStaffList($scope);
      Staff.resetQueryTabs($scope);
    };

    $scope.resetStaffList = function () {
      Staff.resetStaffList($scope);
      Staff.resetQueryTabs($scope);
    };

    $scope.tabQuery = function (tab) {
      Staff.resetQueryTabs($scope);
      tab.selected = true;
      $scope.curQueryKey = tab.queryKey;
      doQuery($scope.curQueryKey);
    };

    $scope.exportStaffList = function () {
      var postData = queryData();
      var conditionsJson = angular.toJson(postData.conditions);
      var url = '/bms/staff/exportStaffList?isResigned=1&conditions=' + conditionsJson;
      $window.open(url, '_blank');
    };

    $scope.$watch( "pager.pageNumber", function ( newValue, oldValue ){
      if ( newValue && newValue !== oldValue ) {
        doQuery($scope.curQueryKey);
      }
    });

    function doQuery(queryKey) {
      var postData = queryData();
      Staff[queryKey]($scope, postData);
    }

    function queryData() {
      var
        query = $scope.query,
        data = {
          employee: query.employee,
          conditions: {
            isResigned: true,
            startDate: query.startDate && $filter('date')(query.startDate.val, 'yyyy-MM-dd'),
            endDate: query.endDate && $filter('date')(query.endDate.val, 'yyyy-MM-dd'),
            gradeId: query.grade,
            staffGrade: query.staffGrade,
            deptId: ($scope.org[3] && $scope.org[3].id) || ($scope.org[2] && $scope.org[2].id) || ($scope.org[1] && $scope.org[1].id),
            includeResigned: query.includeResigned || false
          },
          pager: $scope.pager
        };

      return data;
    }

    function resetPageNumber() {
      $scope.pager.pageNumber = 1;
    }

  }]);
});
