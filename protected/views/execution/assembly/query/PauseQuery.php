<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>停线查询</title>
	<!-- Le styles -->
	<link href="/bms/css/bootstrap.css" rel="stylesheet">
	<link href="/bms/css/common.css" rel="stylesheet">
	<link href="/bms/css/execution/assembly/query/PauseQuery.css" rel="stylesheet">	
	<!-- Le script -->
	<script type="text/javascript" src="/bms/js/jquery-1.8.0.min.js"></script>
	<script type="text/javascript" src="/bms/js/service.js"></script>
	<script type="text/javascript" src="/bms/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="/bms/js/head.js"></script>
	<script type="text/javascript" src="/bms/js/execution/assembly/query/pauseQuery.js"></script>
	<script type="text/javascript" src="/bms/js/datePicker/WdatePicker.js"></script>
</head>
<body>
		
	<?php
		require_once(dirname(__FILE__)."/../../../common/head.php");
	?>
	<div class="offhead">
	   <?php
		require_once(dirname(__FILE__)."/../../../common/left/assembly_dataInput_left.php");
		?>
     
        <div id="bodyright" class="offset2"><!-- 页体 -->
            <div ><ul class="breadcrumb"><!-- 面包屑 -->
                        <li><a href="#">生产执行</a><span class="divider">&gt;</span></li>
                        <li><a href="#">总装</a><span class="divider">&gt;</span></li>
                        <li><a href="#">数据查询</a><span class="divider">&gt;</span></li>
                        <li class="active">停线查询</li>                
                </ul></div><!-- end 面包屑 -->
            
   	   		<div><!-- 主体 -->
				<form id="form" class="well form-search">
					<table>
						<tr>
							<td>停线类型</td>
							<td>开始时间</td>
							<td>结束时间</td>
							<td>工段</td>
							<td>责任部门</td>
							<td></td>
						</tr>
						<tr>
							<td>
								<select name="" id="pauseType" class="input-medium">
									<option value="" selected>全部</option>
									<option value="工位求助">工位求助</option>
									<option value="紧急停止">紧急停止</option>
									<option value="设备故障">设备故障</option>
									<option value="计划停线">计划停线</option>
								</select>
							</td>
							<td>
								<input type="text" class="span3" placeholder="开始时间..." id="startTime" onClick="WdatePicker({el:'startTime',dateFmt:'yyyy-MM-dd HH:00'});"/>
							</td>
							<td>
								<input type="text" class="span3" placeholder="结束时间..." id="endTime" onClick="WdatePicker({el:'endTime',dateFmt:'yyyy-MM-dd HH:00'});"/>
							</td>
							<td>
								<select name="" id="section" class="input-small">
									<option value="" selected>全部</option>
									<option value="T1">T1</option>
									<option value="T2">T2</option>
									<option value="T3">T3</option>
									<option value="C1">C1</option>
									<option value="C2">C2</option>
									<option value="F1">F1</option>
									<option value="F2">F2</option>
								</select>
							</td>
							<td>
								<input type="text" class="input-medium" placeholder="可输入责任部门..." id="dutyDepartment"/>
							</td>
							<td>
								<input id="btnQuery" type="button" class="btn btn-primary" value="查询"></input>   
							</td>
						</tr>
					</table>
				</form>
				
				<table id="tableResult" class="table table-condensed table-hover">
					<thead>
						<tr>
							<th>ID</th>
							<th id="thType">停线类型</th>
							<th id="thSeat">工位</th>
							<th id="thDuty">责任部门</th>
							<th id="thReason">原因</th>
							<th id="thHowlong" class="alignRight">时长</th>
							<th id="thPauseTime">停线时刻</th>
							<th id="thRecoverTime">恢复时刻</th>
							<th id="thEditor">编辑人</th>
						</tr>
					</thead>
					<tbody>
						
					</tbody>
				</table>
				<div class="pagination">
					<ul>
						<li class="prePage"><a href="#"><span>&lt;</span></a></li>
						<li class="active curPage" page="1"><a href="#"><span>1</span></a></li>
						<li class="nextPage"><a href="#"><span>&gt;</span></a></li>
					</ul>
					<ul>
						<li id="export"><a href=""><span id="totalText"></span></a></li>
					</ul>
				</div>
		  	</div><!-- end of 主体 -->
        </div><!-- end of 页体 -->
	</div><!-- offhead -->	
</body>
</html>