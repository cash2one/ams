<?php
Yii::import('application.models.AR.NodeAR');
Yii::import('application.models.AR.OrderAR');
Yii::import('application.models.AR.LaneAR');
Yii::import('application.models.AR.WarehouseAR');
Yii::import('application.models.CarSeeker');

class ReportSeeker
{
	public function __construct(){
	}

	private static $NODE_BALANCE_STATE = array(
		'PBS' => array('彩车身库'),
		'onLine' => array('T1工段' ,'T2工段', 'T3工段', 'C1工段', 'C2工段', 'F1工段', 'F2工段', 'VQ1检验'),
		'VQ1' => array('VQ1异常','退库VQ1'),
		'VQ1-NORMAL' => array('VQ1异常'),
		'VQ1-RETURN'=> array('退库VQ1'),
		'VQ2' => array('整车下线', '出生产车间', '检测线缓冲','VQ2检测线检验', 'VQ2路试', 'VQ2淋雨检验', 'VQ2异常.路试', 'VQ2异常.漏雨', '退库VQ2'),
		'VQ2-NORMAL' => array('整车下线', '出生产车间', '检测线缓冲','VQ2检测线检验', 'VQ2路试', 'VQ2淋雨检验', 'VQ2异常.路试', 'VQ2异常.漏雨'),
		'VQ2-RETURN'=> array('退库VQ2'),
		'VQ3' => array('VQ3检验' ,'VQ3合格', 'VQ3异常','退库VQ3'),
		'VQ3-OK' => array('VQ3合格'),
		'VQ3-NORMAL' => array('VQ3检验' ,'VQ3合格', 'VQ3异常'),
		'VQ3-RETURN'=> array('退库VQ3'),
		'recycle' => array('VQ1异常','整车下线', '退库VQ1', '出生产车间', '检测线缓冲','VQ2检测线检验','VQ2路试', 'VQ2淋雨检验', 'VQ2异常.路试', 'VQ2异常.漏雨' , '退库VQ2', 'VQ3检验' ,'VQ3合格', 'VQ3异常', '退库VQ3'),
		'WH' => array('成品库','WDI'),
		'WHin' => array('成品库'),
		'WH-0' =>array('成品库'),
		'WH-27-export' =>array('成品库'),
		'WH-27-normal' =>array('成品库'),
		'WH-35' =>array('成品库'),
		'WH-X' =>array('成品库'),
		'WH-WDI' =>array('WDI'),
		'assembly' => array('T1工段' ,'T2工段', 'T3工段', 'C1工段', 'C2工段', 'F1工段', 'F2工段', 'VQ1检验','VQ1异常','整车下线', '出生产车间' , '退库VQ1', '检测线缓冲','VQ2检测线检验','VQ2路试', 'VQ2淋雨检验', 'VQ2异常.路试', 'VQ2异常.漏雨' , '退库VQ2', 'VQ3检验' ,'VQ3合格', 'VQ3异常' , '退库VQ3','成品库'),
	);

	private static $COUNT_POINT_DAILY = array(
		"assemblyCount" => "上线",
		"finishCount" => "下线",
		"warehouseCount" => "入库",
		"distributeCount" => "出库",
		"recycleBalance" => "周转车",
		"warehouseBalance" => "库存",
	);

	private static $SERIES_NAME = array(
			'F0' => 'F0',
			'M6' => 'M6',
			'6B' => '思锐'
	);

	private static $COLD_RESISTANT = array('非耐寒','耐寒');

	public function queryManufactureDaily($date) {
		list($stime, $etime) = $this->reviseDailyTime($date);
		$countArray = array();
		$countArray["assemblyCount"] = $this->countCarByPoint($stime, $etime, "assembly");
		$countArray["finishCount"] = $this->countCarByPoint($stime, $etime, "finish");
		$countArray["warehouseCount"] = $this->countCarByPoint($stime, $etime, "warehouse");
		$countArray["distributeCount"] = $this->countCarByPoint($stime, $etime, "distribute");

		$dataSeriesX = array();
		$dataSeriesY = array();
		foreach($countArray as $point => $count){
			$dataSeriesX[] = self::$COUNT_POINT_DAILY[$point];
			foreach(self::$SERIES_NAME as $series => $seriesName){
				$dataSeriesY[$seriesName][] = $count[$series];
			}
		}
		$columnSeries = array("x"=>$dataSeriesX,"y"=>$dataSeriesY);

		$countArray["recycleBalance"] = array("F0"=>"","M6"=>"","6B"=>"");
		$countArray["warehouseBalance"] = array("F0"=>"","M6"=>"","6B"=>"");
		$curDate = DateUtil::getCurDate();
		if($date == $curDate){
			$countArray["recycleBalance"] = $this->countCarByState("recycle");
			$countArray["warehouseBalance"] = $this->countCarByState("WH");
		}

		foreach($countArray as &$count){
			$sum = "";
			foreach($count as &$seriesCount){
				$sum += $seriesCount;
				if($seriesCount === "") $seriesCount = "-";
			}
			$count['sum'] = $sum === "" ? "-" : $sum;
		}

		$carSeeker = new CarSeeker();
		$recyclePeriod = $carSeeker->queryRecycleBalancePeriod("recycle", "all");

		$countSeries = $seriesName = self::$SERIES_NAME;

		$countSeries['sum'] = "合计";

		$ret = array(
			"countPoint" => self::$COUNT_POINT_DAILY,
			"countSeries" => $countSeries,
			"count" => $countArray,
			"carSeries" => array_values($seriesName),
			"columnSeries" => $columnSeries,
			"dataDonut" => $recyclePeriod["dataDonut"],
		);

		return $ret;
	}

	public function queryCompletion($date, $timespan){
		switch($timespan) {
			case "monthly":
				list($stime, $etime) = $this->reviseMonthlyTime($date);
				break;
			case "yearly":
				list($stime, $etime) = $this->reviseYearlyTime($date);
				break;
			default:
				list($stime, $etime) = $this->reviseDailyTime($date);
		}
		$timeArray = $this->parseQueryTime($stime, $etime, $timespan);
		$seriesArray = self::$SERIES_NAME;
		$countDetail = array();
		$countTotal = array();
		$completionDetail = array();
		$completionTotal =array(
			"totalSum" => 0,
			"readySum" => 0,
			"completion" => 0,
		);
		$columnSeriesX = array();
		$columnSeriesY = array();
		$lineSeriesY = array();
		foreach($seriesArray as $series => $seriesName){
			$countTotal[$seriesName] = 0;
			$columnSeriesY[$seriesName] = array();
		}

		foreach($timeArray as $queryTime){
			//count assembly cars
			$countTmp = array();
			$completionTmp = array();

			$sDate = substr($queryTime['stime'], 0, 10);
			$eDate = substr($queryTime['etime'], 0, 10);
			$completionArray = $this->queryPlanCompletion($sDate,$eDate);
			$readySum = 0;
			$totalSum = 0;
			foreach($completionArray as $series => $count){
				$columnSeriesY[$seriesArray[$series]][] = $count['ready'];
				$countTmp[$seriesArray[$series]] =  $count['ready'];
				$countTotal[$seriesArray[$series]] += $count['ready'];

				$readySum += $count['ready'];
				$totalSum += $count['total'];
			}
					
			$rate = empty($totalSum) ? null : round(($readySum/$totalSum) , 2);
			$lineSeriesY[] = $rate;
			$completionTmp['completion'] = $rate;
			$completionTmp['totalSum'] = $totalSum;
			$completionTmp['readySum'] = $readySum;

			$completionTotal['totalSum'] += $totalSum;
			$completionTotal['readySum'] += $readySum;
			$columnSeriesX[] = $queryTime['point'];
			$countDetail[] = array_merge(array('time' => $queryTime['point']), $countTmp);
			$completionDetail[] = array_merge(array('time' => $queryTime['point']), $completionTmp);
		}
		$completionTotal['completion'] = empty($completionTotal['totalSum']) ? null : round(($completionTotal['readySum']/$completionTotal['totalSum']) , 2);

		$ret = array(
			"carSeries" => array_values(self::$SERIES_NAME),
			"countDetail" => $countDetail,
			"completionDetail" => $completionDetail,
			"countTotal" => $countTotal,
			"completionTotal" => $completionTotal,
			"series" => array(
				'x' => $columnSeriesX,
				'column' => $columnSeriesY,
				'line' => $lineSeriesY,
			),
		);

		return $ret;
	}

	public function queryPlanCompletion($sDate, $eDate){
		$sql = "SELECT car_series as series, SUM(total) as total, SUM(ready) as ready FROM plan_assembly WHERE plan_date>='$sDate' AND plan_date<'$eDate' GROUP BY series";
		$datas = Yii::app()->db->createCommand($sql)->queryAll();
		
		$count = array(
			"F0"=>array(),
			"M6"=>array(),
			"6B"=>array(),
		);
		foreach($count as $key => &$one){
			$one = array('total'=>0, 'ready'=>0, 'completion'=>null);
		}
		foreach($datas as $data){
			$totalValue = intval($data['total']);
			$readyValue = intval($data['ready']);
			$count[$data['series']]['total'] = isset($data['total']) ? $totalValue : 0;
			$count[$data['series']]['ready'] = isset($data['ready']) ? $readyValue : 0;
			$count[$data['series']]['completion'] = empty($totalValue) ? null : round(($readyValue/$totalValue) , 2);
		}

		return $count;
	}

	public function queryCarDetail($date, $point, $timeSpan="daily"){
		switch($timeSpan) {
			case "daily":
				list($stime, $etime) = $this->reviseDailyTime($date);
				break;
			case "monthly":
				list($stime, $etime) = $this->reviseMonthlyTime($date);
				break;
			case "yearly":
				list($stime, $etime) = $this->reviseYearlyTime($date);
				break;
			default:
				list($stime, $etime) = $this->reviseDailyTime($date);
		}

		$data = $this->queryDetailByPoint($stime, $etime, $point);

		return $data;
	}

	public function countCarByPoint($stime,$etime,$point="assembly"){
		$point .= "_time";
		$sql = "SELECT series, COUNT(id) as `count` FROM car WHERE $point>='$stime' AND $point<'$etime' GROUP BY series";
		$datas = Yii::app()->db->createCommand($sql)->queryAll();

		$count = array(
			"F0"=>0,
			"M6"=>0,
			"6B"=>0,
		);
		foreach($datas as $data){
			$count[$data['series']] = intval($data['count']);
		}

		return $count;
	}

	public function countCarByState($state){
		if(!is_array($state)) {
			if(!empty(self::$NODE_BALANCE_STATE[$state])) {
				$states = self::$NODE_BALANCE_STATE[$state];
			} else {
				$states = array($state);
			}
		} else {
			$states = $state;
		}

		$str = "'" . join("','", $states) . "'";
		$condition = " WHERE status IN ($str)";

		$sql = "SELECT series, COUNT(id) as `count` FROM car $condition GROUP BY  series";	
		$datas = Yii::app()->db->createCommand($sql)->queryAll();
		$count = array(
			"F0"=>0,
			"M6"=>0,
			"6B"=>0,
		);
		foreach($datas as $data){
			$count[$data['series']] = intval($data['count']);
		}

		return $count;
	}

	public function queryDetailByPoint($stime, $etime, $point="assembly"){
		$point .= "_time";
		$sql = "SELECT id as car_id, vin, assembly_line, serial_number, series, type, config_id, cold_resistant, color,status, engine_code, assembly_time, finish_time, warehouse_time, distribute_time, warehouse_id, order_id, lane_id, distributor_name, remark, special_order
				FROM car 
				WHERE $point>='$stime' AND $point<'$etime' ORDER BY assembly_time";
		$datas = Yii::app()->db->createCommand($sql)->queryAll();

		$configName = $this->configNameList();
		$configName[0] = "";
		foreach($datas as &$data){
			if($data['series'] == '6B') $data['series'] = '思锐';
			$data['config_name'] = $configName[$data['config_id']];
			$data['cold'] = self::$COLD_RESISTANT[$data['cold_resistant']];

			$data['row'] = '-';
			if(!empty($data['warehouse_id'])){
				$row = WarehouseAR::model()->findByPk($data['warehouse_id']);
				if(!empty($row)) $data['row'] = $row->row;
			}

			$data['order_number'] = '-';
			if(!empty($data['order_id'])){
				$order = OrderAR::model()->findByPk($data['order_id']);
				if(!empty($order)) $data['order_number'] = $order->order_number;
			}

			$data['lane'] = '-';
			if(!empty($data['lane_id'])){
				$lane = LaneAR::model()->findByPk($data['lane_id']);
				if(!empty($lane)) $data['lane'] = $lane->name;
			}
		}

		return $datas;
	}

	private function reviseDailyTime($date) {
		$d = strtotime($date);
		$Hnow = intval(date("H"));
		if($Hnow>=0 && $Hnow<8){
			$lastDay = strtotime('-1 day', $d);
			$stime = date("Y-m-d 08:00:00", $lastDay);
			$etime = date("Y-m-d 08:00:00", $d);
		} else {
			$nextDay = strtotime('+1 day', $d);
			$stime = date("Y-m-d 08:00:00", $d);
			$etime = date("Y-m-d 08:00:00", $nextDay);
		}

		return array($stime, $etime);
	}

	private function reviseMonthlyTime($date) {
		$d = strtotime($date);
		$nextM = strtotime('+1 month', $d);
		$stime = date("Y-m-01 08:00:00", $d);
		$etime = date("Y-m-01 08:00:00", $nextM);

		return array($stime, $etime);
	}

	private function reviseYearlyTime($date) {
		$d = strtotime($date);
		$nextY = strtotime('+1 year', $d);
		$stime = date("Y-01-01 08:00:00", $d);
		$etime = date("Y-01-01 08:00:00", $nextY);

		return array($stime, $etime);
	}

	private function stateArray($state){
		$stateMap=array(
			'PBS' => array('PBS'),
			'onLine' => array('onLine'),
			'VQ1' => array('VQ1-NORMAL', 'VQ1-RETURN'),
			'VQ2' => array('VQ2-NORMAL', 'VQ2-RETURN'),
			'VQ3' => array('VQ3-NORMAL','VQ3-RETURN'),
			'VQ3-OK' => array('VQ3-OK'),
			'recycle' => array('VQ1', 'VQ2', 'VQ3'),
			// 'WH' => array('WH'),
			'WH' => array('WH-0','WH-27-export','WH-27-normal','WH-35','WH-X','WH-WDI'),
			'WHin' => array('WHin'),
			'assembly' => array('PBS', 'onLine','VQ1', 'VQ2', 'VQ3', 'WH'),
			'mergeRecyle' => array('PBS','onLine','recycle', 'WH'),
		);
		return $stateMap[$state];
	}

	private function configNameList(){
		$configName = array();
		$sql = "SELECT car_config_id, order_config_id , name , car_model FROM view_config_name";
		$datas = Yii::app()->db->createCommand($sql)->queryAll();
		foreach ($datas as $data){
			$configName[$data['car_config_id']] = $data['car_model'] . '/' . $data['name'];
		}
		return $configName;
	}

	public function parseQueryTime($stime, $etime, $timespan){
		$s = strtotime($stime);
		$e = strtotime($etime);

		$ret = array();

		switch($timespan) {
			case "monthly":
				$pointFormat = 'm-d';
				$format = 'Y-m-d H:i:s';
				$slice = 86400;
				break;
			case "yearly":
				$pointFormat = 'Y-m';
				$format = 'Y-m-d H:i:s';
				break;
			default:
				$pointFormat = 'm-d';
				$format = 'Y-m-d H:i:s';
				$slice = 86400;
		}

		$t = $s;
		while($t<$e) {
			$point = date($pointFormat, $t);
			if($pointFormat === 'Y-m') {
				// $slice = 86400 * intval(date('t' ,$t));
				$eNextM = strtotime('+1 month', $t);			//next month			//added by wujun
				$ee = date('Y-m', $eNextM) . "-01 08:00:00";	//next month firstday	//added by wujun
				$etmp = strtotime($ee);	//next month firstday	//added by wujun
			} else {
				$etmp = $t+$slice;
			}
			if($etmp>=$e){
				$etmp=$e;
			} 

			$ret[] = array(
				'stime' => date($format, $t),
				'etime' => date($format, $etmp),
				'point' => $point,
			);	
			$t = $etmp;
		}

		return $ret;
	}
}