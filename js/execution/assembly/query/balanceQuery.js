$(document).ready(function () {
	initPage();
//------------------- common functions -----------------------	
	//initialize this page
	/*
		1.add head class and resetPage
		2.resetPage();
		3.hide alert
	*/
	function initPage(){
		//add head class
		$("#headAssemblyLi").addClass("active");
		$("#leftBalanceQueryLi").addClass("active");

		// $("#startTime").val(currentDate8());
		// $("#endTime").val(currentDate16());
		resetAll();
	}
	

	function resetAll (argument) {
		$(".pager").hide();
		$("#resultTable").hide();
		// $("#tableCarsDistribute").hide();
		$("#divCheckbox").hide();
	}

/*
 * ----------------------------------------------------------------
 * Event bindings
 * ----------------------------------------------------------------
 */	
 	$(window).bind('keydown', enterHandler);
	function enterHandler (event) {
		if (event.keyCode == "13"){
		    toQuery();
		    return false;
		}
	}
	
	$("#btnQuery").bind("click",toQuery);
	function toQuery() {
		//clear last
		$("#resultTable tbody").text("");

		//if validate passed
		var index = $("#tabs li").index($('#tabs .active'));
		$("#paginationCars").hide();
		if (index == 0)
			ajaxDetailQuery();
		return false;
	}

	$("#exportCars").click(
		function () {
			ajaxExportBalanceDetail();
			return false;
		}
	);

	//car pagination
	$("#preCars").click(
		function (){
			if(parseInt($("#curCars").attr("page")) > 1){
				$("#tableCars tbody").html("");
				ajaxDetailQuery(parseInt($("#curCars").attr("page")) - 1);
			}
		}
	);

	$("#nextCars").click(
		function (){
			if(parseInt($("#curCars").attr("page")) * 20 < parseInt($("#totalCars").attr("total")) ){
			$("#tableCars tbody").html("");
			ajaxDetailQuery(parseInt($("#curCars").attr("page")) + 1);
		}
		}
	);

	$("#firstCars").click(
		function () {
			if(parseInt($("#curCars").attr("page")) > 1){
				$("#tableCars tbody").html("");
				ajaxDetailQuery(parseInt(1));
			}
		}
	);

	$("#lastCars").click(
		function () {
			if(parseInt($("#curCars").attr("page")) * 20 < parseInt($("#totalCars").attr("total")) ){
				$("#tableCars tbody").html("");
				totalPage = parseInt($("#totalCars").attr("total"))%20 === 0 ? parseInt($("#totalCars").attr("total"))/20 : parseInt($("#totalCars").attr("total"))/20 + 1;
				ajaxDetailQuery(parseInt(totalPage));
			}
		}
	)

	$("#tabs li").click(function () {
		var index = $("#tabs li").index(this);
		if(index<3){
			$("#paginationCars").hide();
		}
		if (index == 0){
			$("#area").val("");
			ajaxDetailQuery();
		}else if (index === 1){
			carsDistribute();
		}
	});

	$("#checkboxMerge").change(function () {
		if($(this).attr("checked") == "checked"){
			ajaxQueryBalanceAssembly('mergeRecyle');
		} else {
			ajaxQueryBalanceAssembly();
		}
	})

	$(".queryCars").live("click", function(e){
		orderConfigId = $(this).attr("orderConfigId");
		coldResistant = $(this).attr("coldResistant");
		color = $(this).attr("color");
		headInfo =$("#selectState option:selected").html() + "-" + $("#selectSeries").val() + "-" + $(this).attr("configName");
		if(color !=""){
			headInfo = headInfo + "-" + color
		}
		$("#carsModal .modal-header h4").html(headInfo);
		ajaxQueryCars(orderConfigId,coldResistant,color);
		$("#carsModal").modal("show");

	})

	$("#area").change(function(){
		ajaxDetailQuery();
	})

//-------------------END event bindings -----------------------



/*
 * ----------------------------------------------------------------
 * Ajax query
 * ----------------------------------------------------------------
 */
	function ajaxDetailQuery (targetPage) {
		areaVal = $("#area").val();
		$.ajax({
			type: "get",//使用get方法访问后台
    	    dataType: "json",//返回json格式的数据
		    url: BALANCE_DETAIL_QUERY,//ref:  /bms/js/service.js
		    data: { 
		    	"state" : $("#selectState").val(),
		    	"series" : $("#selectSeries").val(),
		    	"area" : areaVal,
				"curPage":targetPage || 1,
		    	"perPage":20,
		    },
		    success:function (response) {
		    	if(response.success){
		    		$("#resultTable tbody").html("");
		    		$.each(response.data.data,function (index,value) {
		    			var serialTd = "<td>" + value.serial_number + "</td>";
		    			var seriesTd = "<td>" + value.series + "</td>";
		    			var vinTd = "<td>" + value.vin + "</td>";
		    			var colorTd = "<td>" + value.color + "</td>";
						var typeInfoTd = "<td>" + value.type_info + "</td>";
						var coldTd = "<td>" + value.cold + "</td>";
		    			var statusTd = "<td>" + value.status + "</td>";
		    			var rowTd = "<td>" + value.row + "</td>";
		    			var finishTimeTd = "<td>" + value.finish_time + "</td>";
		    			var warehouseTimeTd = "<td>" + value.warehouse_time + "</td>";
		    			var tr = "<tr>" + serialTd + vinTd + seriesTd + typeInfoTd + 
		    				coldTd + colorTd + statusTd + rowTd + finishTimeTd + warehouseTimeTd + "</tr>";
		    			$("#resultTable tbody").append(tr);
						$("#resultTable").show();
		    		});
		    		//deal with pager
		    		if(response.data.pager.curPage == 1) {
	    			//$(".prePage").hide();
						$("#preCars, #firstCars").addClass("disabled");
						$("#preCars a, #firstCars a").removeAttr("href");
					} else {
	    				//$(".prePage").show();
						$("#preCars, #firstCars").removeClass("disabled");
						$("#preCars a, #firstCars a").attr("href","#");
					}
	    			if(response.data.pager.curPage * 20 >= response.data.pager.total ) {
	    				//$(".nextPage").hide();
						$("#nextCars, #lastCars").addClass("disabled");
						$("#nextCars a, #lastCars a").removeAttr("href");
					} else {
	    				//$(".nextPage").show();
						$("#nextCars, #lastCars").removeClass("disabled");
						$("#nextCars a, #lastCars a").attr("href","#");
					}
					$("#curCars").attr("page", response.data.pager.curPage);
					$("#curCars a").html(response.data.pager.curPage);
					$("#totalCars").attr("total", response.data.pager.total);
					$("#totalCars").html("导出全部" + response.data.pager.total + "条记录");
					
					//area condition
					if(areaVal == ""){
						$("#area").html("");
						$("<option />").val("").html("库区").appendTo($("#area"));
						$.each(response.data.areaArray, function (key, area){
							if(area !=''){
								$("<option />").val(area).html(area).appendTo($("#area"));
							}
						})
					}
					$("#area").val(areaVal);


					$("#paginationCars").show();

		    	}else
		    		alert(response.message);
		    },
		    error:function(){alertError();}
		});
	}

	function ajaxExportBalanceDetail(){
		areaVal = $("#area").val();
		window.open(BALANCE_DETAIL_EXPORT 
			+ "?state=" + $("#selectState").val() 
			+ "&series="+ $("#selectSeries").val()
			+ "&area=" + areaVal
			);
	}

	function carsDistribute() {
		if($("#selectSeries").val() == ""){
			if($("#selectState").val() == "assembly"){
				if($("#checkboxMerge").attr("checked") == "checked"){
					ajaxQueryBalanceAssembly('mergeRecyle');
				} else {
					ajaxQueryBalanceAssembly('assembly');
				}
				$("#divCheckbox").show()
			} else {
				ajaxQueryBalanceAssembly($("#selectState").val());
				$("#checkboxMerge").removeAttr("checked");
				$("#divCheckbox").hide();
			}
			$("#carsDistribute .tableContainer").addClass("span10");
		} else {
			$("#divCheckbox").hide();
			$("#carsDistribute .tableContainer").removeClass("span10");
			ajaxQueryBalanceDistribute();
		}
	}

	function ajaxQueryBalanceAssembly(state) {
		$(".carsDistributeContainer").hide();
		$.ajax({
			url: QUERY_BALANCE_ASSEMBLY,
			type: "get",
			data: {
				"state" : state,
			},
			dataType: "json",
			success: function(response) {
				balanceQuery.AssemblyAll.ajaxData = response.data;
				balanceQuery.AssemblyAll.updateDistributeTable();
				balanceQuery.AssemblyAll.drawColumn();
				$("#columnContainer").show();
				$(".carsDistributeContainer").show();
			},
			error: function(){
				alertError();
			}
		})
	}

	function ajaxQueryBalanceDistribute() {
		$(".carsDistributeContainer").hide();
		$.ajax({
			url: QUERY_BALANCE_DISTRIBUTE,
			type: "get",
			data: {
				"state" : $("#selectState").val(),
				"series" : $("#selectSeries").val(), 
			},
			dataType: "json",
			success: function (response) {
				if(response.success){
					balanceQuery.distribute.ajaxData = response.data;
					balanceQuery.distribute.updateDistributeTable();
					$("#columnContainer").hide();
					$(".carsDistributeContainer").show();
				} else {
					alert(response.message);
				}
			},
			error: function(){
				alertError();
			}
		})
	}

	function ajaxQueryCars(orderConfigId,coldResistant,color) {
		$("#resultCars>tbody").html("");
		$.ajax({
			url: SHOW_BALANCE_CARS,
			type: "get",
			dataType: "json",
			data: {
				"state" : $("#selectState").val(),
				"orderConfigId" : orderConfigId,
				"coldResistant" : coldResistant,
				"color" : color || "",
			},
			success: function (response) {
				if(response.success){
					cars = response.data
					$.each(cars, function (index, car){
						tr = $("<tr />");
						$("<td />").html(car.serial_number).appendTo(tr);
						$("<td />").html(car.vin).appendTo(tr);
						$("<td />").html(car.series).appendTo(tr);
						$("<td />").html(car.type_info).appendTo(tr);
						$("<td />").html(car.cold).appendTo(tr);
						$("<td />").html(car.color).appendTo(tr);
						$("<td />").html(car.status).appendTo(tr);
						$("<td />").html(car.row).appendTo(tr);
						$("<td />").html(car.finish_time.substring(0,16)).appendTo(tr);
						$("<td />").html(car.warehouse_time.substring(0,16)).appendTo(tr);

						$("#resultCars>tbody").append(tr);
					})
					$("#resultCars").show();
				} else {
					alert(response.message);
				}
			},
			error: function(){
				alertError();
			}
		})
	}
	
	$('body').tooltip(
        {
         selector: "select[rel=tooltip], a[rel=tooltip]"
    });
//-------------------END ajax query -----------------------

});

!$(function (){
	window.balanceQuery = window.balanceQuery || {};
	window.balanceQuery.AssemblyAll = {
		ajaxData : {},

		columnData: {
			chart: {
                type: 'column',
                renderTo: 'columnContainer'
            },
            title: {
                text: ''
            },
            subtitle: {
                text: ''
            },
            xAxis: {
                categories: []
            },
            credits: {
                enabled: false
            },
            yAxis: {
                min: 0,
                title: {
                    text: '结存数量（辆）'
                },
                stackLabels: {
                    enabled: true,
                    style: {
                        fontWeight: 'bold',
                        color: (Highcharts.theme && Highcharts.theme.textColor) || 'gray'
                    }
                }
            },
            tooltip: {
                headerFormat: '<span style="font-size:14px">{point.key}</span><table>',
                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                    '<td style="padding:0"><b>{point.y}</b></td></tr>',
                footerFormat: '</table>',
                shared: true,
                useHTML: true
            },
            plotOptions: {
                column: {
                	stacking: 'normal',
                    pointPadding: 0.1,
                    borderWidth: 0,
                    pointWidth: 15
                }
            },
            series: [],
            navigation: {
	            buttonOptions: {
	                verticalAlign: 'bottom',
	                y: -20,
	            }
	        }
		},

		updateDistributeTable: function() {
			var series = this.ajaxData.carSeries;
			var detail = this.ajaxData.detail;
			var stateTotal = this.ajaxData.stateTotal;
			var seriesTotal = this.ajaxData.seriesTotal;

			//clear table and initialize it
			$("#tableCarsDistribute thead").html("<tr />");
			$("#tableCarsDistribute tbody").html("");
			$.each(series, function (index, series) {
				$("<tr />").appendTo($("#tableCarsDistribute tbody"));
			});
			stateTotalTr = $("<tr />").appendTo($("#tableCarsDistribute tbody"));

			//first column description
			var stateTr = $("#tableCarsDistribute tr:eq(0)");
			$("<td />").html('车系').addClass('alignCenter').appendTo(stateTr);
			$.each(series, function (index, series){
				$("<td />").html(series).addClass('alignCenter').appendTo($("#tableCarsDistribute tr:eq("+ (index+1) +")"));
			});
			$("<td />").html('合计').addClass('alignCenter').appendTo(stateTotalTr);

			//detail data
			$.each(detail, function (index ,value) {
				$("<td />").html(value.state).appendTo(stateTr);
				$.each(series, function (index, series){
					$("<td />").html(value[series]).appendTo($("#tableCarsDistribute tr:eq("+ (index+1) +")"));
				});
			})

			//series total
			$("<td />").html('总计').appendTo(stateTr);
			$.each(series, function (index, series) {
				$("<td />").html(seriesTotal[series]).appendTo($("#tableCarsDistribute tr:eq("+ (index+1) +")"));
			})

			//state total
			var totalTotal = 0;
			$.each(stateTotal, function (index, value) {
				$("<td />").html(value).appendTo(stateTotalTr);
				totalTotal += value;
			})
			$("<td />").html(totalTotal).appendTo(stateTotalTr);

		},


		drawColumn: function() {
			columnSeries = [];
			carSeries = this.ajaxData.carSeries;
			columnSeriesData = this.ajaxData.series;
			$.each(carSeries, function (index, series) {
				columnSeries[index] = {
					name: series,
					data: columnSeriesData.y[series]
				}
			})

            console.log(this);
			this.columnData.xAxis.categories = columnSeriesData.x;
			this.columnData.series = columnSeries;
			var chart;
			chart = new Highcharts.Chart(this.columnData);
		}
	}

	window.balanceQuery.distribute = {
		ajaxData: {},
		updateDistributeTable: function() {
			 var color = this.ajaxData.colorArray;
			 var configName = this.ajaxData.configNameArray;
			 var detail = this.ajaxData.detail;
			 var colorTotal = this.ajaxData.colorTotal;
			 var configTotal = this.ajaxData.configTotal;

			//clear table and initialize it
			$("#tableCarsDistribute thead").html("<tr />");
			$("#tableCarsDistribute tbody").html("");
			$.each(configName, function (index, configName) {
				$("<tr />").appendTo($("#tableCarsDistribute tbody"));
			})
			colorTotalTr = $("<tr />").appendTo($("#tableCarsDistribute tbody"));

			//first column description
			var colorTr = $("#tableCarsDistribute tr:eq(0)");
			$("<td />").html('车型/配置').addClass('alignCenter').appendTo(colorTr);
			$.each(configName, function (index, configName) {
				$("<td />").html(configName).addClass('configNameTd').appendTo($("#tableCarsDistribute tr:eq("+ (index+1) +")"));
			});
			$("<td />").html('合计').addClass('alignCenter').appendTo(colorTotalTr);

			//detail data
			$.each(detail, function (index, value) {
				$("<td />").html(value.color).appendTo(colorTr);
				$.each(configName, function (index, configName) {
					aCount = $("<a />").addClass("queryCars").attr("rel", "tooltip").attr("data-toggle", "tooltip").attr("data-placement", "top").attr("title", value.color);
					aCount.html(value[configName]['count']);
					aCount.attr("orderConfigId", value[configName]['orderConfigId']);
					aCount.attr("coldResistant", value[configName]['coldResistant']);
					aCount.attr("color", value.color);
					aCount.attr("configName", configName);
					$("<td />").html(aCount).appendTo($("#tableCarsDistribute tr:eq("+ (index+1) +")"));
				});
			});

			//config total
			$("<td />").html('总计').appendTo(colorTr)
			$.each(configName, function (index, configName) {
				aCount = $("<a />").addClass("queryCars").attr("rel", "tooltip").attr("data-toggle", "tooltip").attr("data-placement", "top");
				aCount.html(configTotal[configName]['count']);
				aCount.attr("orderConfigId", configTotal[configName]['orderConfigId']);
				aCount.attr("coldResistant", configTotal[configName]['coldResistant']);
				aCount.attr("color", "");
				aCount.attr("configName", configName);
				$("<td />").html(aCount).appendTo($("#tableCarsDistribute tr:eq("+ (index+1) +")"));
			});

			//color total
			var totalTotal = 0;
			$.each(colorTotal, function (index, value) {
				$("<td />").html(value).appendTo(colorTotalTr);
				totalTotal += value;
			})
			$("<td />").html(totalTotal).appendTo(colorTotalTr);
		}
	}
})