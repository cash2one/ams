<?php
Yii::import('application.models.AR.monitor.*');
Yii::import('application.models.AR.LaneAR');
Yii::import('application.models.SeriesSeeker');

class MonitorSeeker
{
	private static $NODE_BALANCE_STATE = array(
			// 'PBS' => array('彩车身库'),
			'PBS' => array('预上线'),
			'VQ1' => array('VQ1异常','VQ1退库'),
			'VQ1-EXCEPTION' => array('VQ1异常'),
			'VQ2' => array('VQ1合格', '出生产车间', '检测线缓冲','VQ2检测线', 'VQ2路试', 'VQ2淋雨', 'VQ2异常.路试', 'VQ2异常.漏雨', 'VQ2退库'),
			'VQ3' => array('VQ3检验' ,'VQ3合格', 'VQ3异常','VQ3退库'),
			);

	public function __construct(){
	}

	public function querySeats($section) {
		$sql = "SELECT display_name FROM node WHERE section='$section' AND type!='device'";
		$seats = Yii::app()->db->createCommand($sql)->queryColumn();
		$ret = array();
		foreach($seats as $seat) {
			$seat = substr($seat, 1);
			$ret[] = sprintf('%02d', $seat);
		}
		sort($ret);
		return $ret;
	}

	public function queryLabel($type, $stime,$etime) {
		$ret = array();
		if(empty($type)) {
			$types = array('production','quality','balance');
			foreach($types as $type) {
				$method = "query" . ucFirst($type) . "Label";
				if(method_exists($this, $method)) {
					$ret[$type] = $this->$method($stime,$etime);
				}
			}
		} else {
			$method = "query" . ucFirst($type) . "Label";
			if(method_exists($this, $method)) {
				$ret = $this->$method($stime,$etime);
			}
		}
		return $ret;
	}

	public function queryProductionLabel($stime,$etime) {
		//pbs t0 vq1
		$date = date("Y-m-d", strtotime($stime));
		//$planCars = $this->queryPlanCars($date);
		$seriesArray = SeriesSeeker::findAllCode();
        $seriesArray[] = 'all';
		$nodes = array('PBS','T0','VQ1');
		foreach($nodes as $node) {
			foreach($seriesArray as $series) {
				$ret[$node][$series] = $this->queryFinishCars($stime,$etime,$node,$series);
			}
		}
		return $ret;
	}

	public function queryQualityLabel($stime,$etime) {
		//$dpu = $this->queryDPU($stime,$etime);
		$seriesArray = SeriesSeeker::findAllCode();
        $seriesArray[] = 'all';

		$ret = array();
		foreach($seriesArray as $series) {
			$ret['VQ1'][$series] = $this->queryQualified($stime,$etime, 'VQ1', $series);
		}

		return $ret;
	}

	public function queryBalanceLabel($stime,$etime) {
		return array(
				'PBS' => $this->queryStateCars(self::$NODE_BALANCE_STATE['PBS']),
				'VQ1' => $this->queryStateCars(self::$NODE_BALANCE_STATE['VQ1']),
			    );
	}

	public function queryLaneInfo() {
		$datas=array();
		for($i=1;$i<51;$i++){
			$sql="SELECT lane_id, SUM(amount) as amount, SUM(count) as count FROM `order` WHERE lane_id=$i AND lane_status=1";
			$data = Yii::app()->db->createCommand($sql)->queryRow();
			$data['lane_id'] = $i;
			$data['lane_name']= LaneAR::model()->findByPk($i)->name;
			if(empty($data['amount'])){
				$data['amount'] = 0;
				$data['count'] = 0;
			}

			$orderSql = "SELECT lane_id,
							MIN(activate_time) AS min_activate,
							MAX(activate_time) AS max_activate,
							MIN(out_finish_time) AS min_out,
							MAX(out_finish_time) AS max_out,
							MIN(lane_release_time) AS min_release,
							MAX(lane_release_time) AS max_relaese
						FROM `order`
						WHERE lane_status=1 AND lane_id=$i
						GROUP BY lane_id";
			$time = Yii::app()->db->createCommand($orderSql)->queryRow();
			$laneActivate = $time['min_activate'];
			$now = date("Y-m-d H:i:s");
			$last = 0;
			if($time['min_out'] === '0000-00-00 00:00:00'){
				$last = strtotime($now) - strtotime($laneActivate);
			} else {
				$laneOutFinish = $time['max_out'];
				$last = strtotime($now) - strtotime($laneOutFinish);
			}

			$last = round($last/3600, 1);
			$data['last'] = $last;
			if(empty($laneActivate) || $laneActivate === '0000-00-00 00:00:00'){
				$data['last'] = '';
			}
			$datas[] = $data;
		}
		return $datas;
	}

	public function queryWarehouseBlockBalance($block) {
        $sql = "SELECT row,capacity, quantity FROM warehouse WHERE block='$block'";
        $rows = Yii::app()->db->createCommand($sql)->queryAll();

        return $rows;
    }


	public function queryWarehouseBalanceDetail($suffix, $type = 'block') {
		if($type == 'block') {
			$sql = "SELECT id FROM warehouse WHERE block='$suffix'";

		} else {
			$sql = "SELECT id FROM warehouse WHERE row = '$suffix'";
		}
		$states = Yii::app()->db->createCommand($sql)->queryColumn();

		return $this->queryBalanceDetailByWareHouseId($states);
	}

	public function queryBalanceDetailByWareHouseId($ids) {
        $str = "'" . join("','", $ids) . "'";
        $sql = "SELECT series,vin,type,color,modify_time as time FROM car WHERE warehouse_id IN ($str)";
        return Yii::app()->db->createCommand($sql)->queryAll();
    }


	public function queryBalanceDetail($node) {
		if(!is_array($node)) {
			if(!empty(self::$NODE_BALANCE_STATE[$node])) {
				$states = self::$NODE_BALANCE_STATE[$node];
			} else {
				$states = array($node);
			}
		} else {
			$states = $node;
		}
		$str = "'" . join("','", $states) . "'";
		$sql = "SELECT id,serial_number,sps_serial,series,vin,type,color,modify_time as time FROM car WHERE status IN ($str) ORDER BY modify_time";

		$datas = Yii::app()->db->createCommand($sql)->queryAll();
		if($node == "PBS") {
			foreach($datas as &$data) {
				$traceSql = "SELECT pass_time FROM node_trace WHERE car_id={$data['id']} AND node_id=1 AND user_id>100 ORDER BY pass_time DESC";
				$traceTime = Yii::app()->db->createCommand($traceSql)->queryScalar();
				if(!empty($traceTime)) {
					$data['time'] = !empty($traceTime) ? $traceTime : $data['time'];
				}
			}
			$datas = $this->multi_array_sort($datas, 'time');
		}

		return $datas;

	}

	public function multi_array_sort ($multi_array,$sort_key,$sort=SORT_ASC) {
        if(is_array($multi_array)){
            foreach ($multi_array as $row_array){
                if(is_array($row_array)){
                    $key_array[] = $row_array[$sort_key];
                }else{
                    return -1;
                }
            }
        }else{
            return -1;
        }
        array_multisort($key_array,$sort,$multi_array);
        return $multi_array;
    }

	public function queryBalanceCount($node, $series = 'all') {
		if(!is_array($node)) {
			if(!empty(self::$NODE_BALANCE_STATE[$node])) {
				$states = self::$NODE_BALANCE_STATE[$node];
			} else {
				$states = array($node);
			}
		} else {
			$states = $node;
		}

		return $this->queryStateCars($states, $series);
	}

	public function queryStateCars($states,$series = 'all' , $stime = null, $etime = null) {
		$condition = '';
		$conditions = array();
		if(!empty($stime)) {
			$conditions[] = "modify_time >= '$stime'";
		}
		if(!empty($etime)) {
			$conditions[] = "modify_time <= '$etime'";
		}
		if($series !== 'all') {
			$conditions[] = "series = '$series'";
		}
		if(!empty($conditions)) {
			$condition = ' AND ' . join(' AND ', $conditions);
		}

		$str = "'" . join("','", $states) . "'";
		$sql = "SELECT count(*) FROM car WHERE status IN ($str) $condition";
		return Yii::app()->db->createCommand($sql)->queryScalar();
	}

	public function queryWareHourseCars($state, $series = 'all', $stime = null, $etime = null) {
		$condition = '';
        $conditions = array();
        if(!empty($stime)) {
            $conditions[] = "modify_time >= '$stime'";
        }
        if(!empty($etime)) {
            $conditions[] = "modify_time <= '$etime'";
        }
        if($series !== 'all') {
            $conditions[] = "series = '$series'";
        }
        if(!empty($conditions)) {
            $condition = ' AND ' . join(' AND ', $conditions);
        }


		$sql = "SELECT count(*) FROM car WHERE status LIKE '$state%' $condition";
		$sql = "SELECT count(*) FROM car WHERE status = '$state' OR status='WDI' $condition";
		return Yii::app()->db->createCommand($sql)->queryScalar();
	}

	public function queryWareHousePassCars($stime = null, $etime = null) {
		$condition = '';
		// $conditionIn = '';
		// $conditionOut = '';
		if(!empty($stime)) {
			$condition .= " AND pass_time >= '$stime'";
			// $conditionIn .= "warehouse_time >= '$stime'";
			// $conditionOut .= "distribute_time >= '$stime'";
		}
		if(!empty($etime)) {
			$condition .= " AND pass_time <= '$etime'";
			// $conditionIn .= " AND warehouse_time <= '$etime'";
			// $conditionOut .= " AND distribute_time <= '$etime'";
		}
		$seriesArray = SeriesSeeker::findAllCode();
        $seriesArray[] = 'all';

		$ret = array();
		foreach($seriesArray as $series) {
			$sqlIn = "SELECT count(distinct car_id) FROM node_trace WHERE node_id=18 $condition";
			// $sqlIn = "SELECT count(*) FROM car WHERE $conditionIn";

			$sqlOut = "SELECT count(distinct car_id) FROM node_trace WHERE node_id=19 $condition";
			// $sqlOut = "SELECT count(*) FROM car WHERE $conditionOut";

			if($series !== 'all') {
				$sqlIn .= " AND car_series = '$series'";
				// $sqlIn .= " AND series = '$series'";
				$sqlOut .= " AND car_series = '$series'";
				// $sqlOut .= " AND series = '$series'";
			}


			$in = Yii::app()->db->createCommand($sqlIn)->queryScalar();

			$out = Yii::app()->db->createCommand($sqlOut)->queryScalar();

			$ret['warehourse_in'][$series] = $in;
			$ret['warehourse_out'][$series] = $out;
		}

		return $ret;
	}

	public function queryStandbyPlan($standbyDate){
		$condition = "standby_date='$standbyDate'";
		$sql = "SELECT SUM(amount) FROM `order` WHERE $condition";
		$ret = Yii::app()->db->createCommand($sql)->queryScalar();
		return $ret;
	}

	public function queryPlanCars($date) {
		$seeker = new PlanSeeker();
		$plans = $seeker->search($date, '', '');
		$planCars = 0;
		$finishCars = 0;

		//stat car pass node
		foreach($plans as $plan) {
			$planCars += intval($plan['total']);
			//$finishCars += intval($plan['finished']);
		}
		return $planCars;
	}

	public function queryFinishCars($stime, $etime, $node,$series = 'all') {
		$sql = "SELECT id FROM node WHERE name='$node'";
		$nodeId = Yii::app()->db->createCommand($sql)->queryScalar();
		$sql = "SELECT count(distinct car_id) FROM node_trace WHERE pass_time>'$stime' AND pass_time < '$etime' AND node_id=$nodeId";
		if($series !== 'all') {
			$sql .= " AND car_series='$series'";
		}
		$finishCars = Yii::app()->db->createCommand($sql)->queryScalar();
		return $finishCars;
	}

	public function queryLinePauseTime($section, $stime, $etime, $mode = 'all', $type = '') {
		$condition = '';
		if(!empty($section)) {
			$sql = "SELECT id FROM node WHERE section='$section'";
			if($type === 'device') {
				$sql = "SELECT id FROM node WHERE name='$section'";
			} else {
				$sql .= " AND type != 'device'";
			}
			$nodeIds = Yii::app()->db->createCommand($sql)->queryColumn();
			if(empty($nodeIds)) {
				return 0;
			}
			$nodeIdStr = join(',', $nodeIds);
			$condition = "AND node_id IN ($nodeIdStr)";
		}
		$values = array($stime, $etime);
		if($mode == 'without_plan_to_pause' ) {
			$condition .= " AND pause_type != ?";
			$values[] = '计划停线';
		}
		$pauses = LinePauseAR::model()->findAll("pause_time >= ? AND pause_time <= ? $condition", $values);
		$total = 0;
		foreach($pauses as $pause) {
			$ps = strtotime($pause->pause_time);
			$pe = strtotime($pause->recover_time);
			if($pause->status == 1) {
				$pe = time();
			}

			$total += $pe - $ps;
		}

		return $total;
	}

	public function queryBlockRate() {
		$sql = "select sum(quantity)/sum(capacity) as rate,block from warehouse group by block";
		$datas = Yii::app()->db->createcommand($sql)->queryAll();
		$ret = array();
		foreach($datas as $data) {
			$ret[$data['block']] = $data['rate'];
		}
		return $ret;
	}

	public function queryBlockQuantity(){
		$sql = "SELECT SUM(quantity) AS quantity, block FROM warehouse GROUP BY block";
		$datas = Yii::app()->db->createCommand($sql)->queryAll();
		$ret = array();
		foreach($datas as $data) {
			$ret[$data['block']] = $data['quantity'];
		}

		//WDI & Y quantity
		// $sql = "SELECT COUNT(id) FROM car WHERE warehouse_id=1 OR warehouse_id=600 GROUP BY area";
		// $dataSpecial = Yii::app()->db->createCommand($sql)->queryColumn();
		// $ret['WDI'] = $dataSpecial[0];	//WDI
		// $ret['Y'] = $dataSpecial[1];	//Y

		return $ret;
	}

	public function queryCapacityRate() {
		$sql = "SELECT SUM(capacity) AS capacity_sum, SUM(quantity) AS quantity_sum, SUM(free_seat) AS free_seat_sum FROM warehouse WHERE id>1 AND id<200";
		$data = Yii::app()->db->createCommand($sql)->queryRow();

		return $data;
	}

	public function queryPeriod() {
		$curDate = DateUtil::getCurDate();
		$stime = $curDate . ' 08:00:00';
		$etime = date("Y-m-d H:i:s");

		$sql = "SELECT 	board_number,
						MIN(activate_time) AS min_activate,
						MAX(activate_time) AS max_activate,
						MIN(out_finish_time) AS min_out,
						MAX(out_finish_time) AS max_out,
						MIN(lane_release_time) AS min_release,
						MAX(lane_release_time) AS max_relaese
				FROM `order`
				WHERE activate_time>='$stime' AND activate_time<'$etime'
				GROUP BY board_number";

		$datas = Yii::app()->db->createCommand($sql)->queryAll();

		$warehousePeriod = 0;
		$transportPeriod = 0;
		foreach($datas as &$data){
			//获得每板的激活、完成、释放这三个周期时间点
			$boardActivate = $data['min_activate'];
			if($data['min_out'] === '0000-00-00 00:00:00'){
				$boardOutFinish = date('Y-m-d H:i:s');
			} else {
				$boardOutFinish = $data['max_out'];
			}
			if($data['min_release'] === '0000-00-00 00:00:00'){
				$boardRelease = date('Y-m-d H:i:s');
			} else {
				$boardRelease = $data['max_relaese'];
			}

			//计算成品库周期，出库完成时间-激活时间
			$data['warehousePeriod'] = strtotime($boardOutFinish) - strtotime($boardActivate);
			$warehousePeriod += $data['warehousePeriod'] ;
			//计算储运周期，车道释放时间-完成时间
			$data['transportPeriod'] = strtotime($boardRelease) - strtotime($boardOutFinish);
			$transportPeriod += $data['transportPeriod'];
		}
		$totalPeriod = $warehousePeriod + $transportPeriod;

		//计算板板数
		$countSql = "SELECT COUNT(DISTINCT board_number) FROM `order` WHERE activate_time>='$stime' AND activate_time<'$etime'";
		$boardCount = Yii::app()->db->createCommand($countSql)->queryScalar();

		if($boardCount == 0){
			$totalPeriodAvg = null;
			$warehousePeriodAvg = null;
			$transportPeriodAvg = null;
		} else {
			$totalPeriodAvg = round((($warehousePeriod + $transportPeriod) / $boardCount / 3600), 1);
			$warehousePeriodAvg = round(($warehousePeriod / $boardCount / 3600), 1);
			$transportPeriodAvg = round(($transportPeriod / $boardCount / 3600), 1);
		}

		$ret = array(
			"warehousePeriod" => $warehousePeriodAvg,
			"transportPeriod" => $transportPeriodAvg,
		);

		return $ret;
	}

	public function queryPlan($section, $date) {
		$seeker = new PlanSeeker();
		$plans = $seeker->search($date, '', '');
		$planCars = 0;
		$finishCars = 0;

		//stat car pass node
		foreach($plans as $plan) {
			$planCars += intval($plan['total']);
			//$finishCars += intval($plan['finished']);
		}
		if(empty($section)) {
			$node = 'T0';		//modifed by wujun
			$sql = "SELECT id FROM node WHERE name='$node'";
		} else {
			$sql = "SELECT id FROM node WHERE section='$section' AND type='normal'";
		}
		$nodeId = Yii::app()->db->createCommand($sql)->queryScalar();
		$sql = "SELECT count(distinct car_id) FROM node_trace WHERE pass_time>'$date' AND node_id=$nodeId";
		$finishCars = Yii::app()->db->createCommand($sql)->queryScalar();

		return array($planCars, $finishCars);
	}

	//run time
	public function queryLineRunTime($stime, $etime) {
		$lineRunTime = strtotime($etime) - strtotime($stime);
		$linePauses = LinePauseAR::model()->findAll("pause_time>=? AND pause_time<=? AND pause_type=?" , array($stime, $etime, '计划停线'));
		$now = time();
		$planPauseTime = 0;
		foreach($linePauses as $linePause) {
			$tRecover = strtotime($linePause->recover_time);
			$tPause = strtotime($linePause->pause_time);
			if($linePause->status == 1 || ($now > $tPause && $now < $tRecover)) {
				$planPauseTime += ($now - $tPause);
			} else if($now > $tRecover) {
				$planPauseTime += ($tRecover - $tPause);
			}
		}

		//日常休息时间，此为临时方案，最终将合并到计划停线中，为可维护的“计划停线”，设备将根据
		$restTime = $this->getRestTime($etime);
		$lineRunTime = $lineRunTime - $planPauseTime - $restTime;

		return $lineRunTime;
	}

	//added by wujun
	public function getRestTime($etime) {
		$workDate = DateUtil::getCurDate();
		$thisDate = date("Y-m-d");
		$etimeHM = date("H:i", strtotime($etime));

		$restTime = 0;
		if($etimeHM >= "08:00" && $etimeHM < "10:00"){
			$restTime = 0;
		}
		if($etimeHM >= "10:00" && $etimeHM < "11:30"){
			$restTime = 600;
		}
		if($etimeHM >= "11:30" && $etimeHM < "12:30"){
			$restTime = 600 + strtotime($etime) - strtotime($thisDate . " 11:30:00");
		}

		if($etimeHM >= "12:30" && $etimeHM < "15:00"){
			$restTime = 4200;
		}
		if($etimeHM >= "15:00" && $etimeHM < "17:00"){
			$restTime = 4800;
		}
		if($etimeHM >= "17:00" && $etimeHM < "18:00"){
			$restTime = 4800 + (strtotime($etime) - strtotime($thisDate . " 17:00:00"));
		}
		if($etimeHM >= "18:00" && $etimeHM < "23:00"){
			$restTime = 8400;
		}
		if($etimeHM >= "23:00" || $etimeHM < "00:30"){
			$restTime = 9000;
		}
		if($etimeHM >= "00:30" && $etimeHM < "01:30"){
			$restTime = 9000 + (strtotime($etime) - strtotime($workDate . " 23:30:00"));
		}
		if($etimeHM >= "01:30" && $etimeHM < "05:00"){
			$restTime = 12600;
		}
		if($etimeHM >= "05:00" && $etimeHM < "07:00"){
			$restTime = 13200;
		}
		if($etimeHM >= "07:00" && $etimeHM < "08:00"){
			$restTime = 13200 + (strtotime($etime) - strtotime($thisDate . " 07:00:00"));
		}

		return $restTime;
	}


	public function queryLineURate($stime , $etime) {
		$lineRunTime = $this->queryLineRunTime($stime, $etime);
		$node = 'T0';
		$online = $this->queryFinishCars($stime, $etime, $node);
		$lineSpeed = $this->queryLineSpeed();

		$rate = '-';
		if(!empty($lineRunTime) && !empty($lineSpeed)){
			$capacity = $lineRunTime / $lineSpeed;
			$rate = intval(100 * $online / $capacity);
			if($rate > 100){
				$rate = "100%";
			} else {
				$rate = "$rate%";
			}
		}

		return $rate;
	}

	public function queryLineStatus($stime , $etime) {
		$lineRun = LineRunAR::model()->find('event=? AND create_time >= ?', array('启动', $stime ));

		$lineStop = LineRunAR::model()->find('event=? AND create_time >= ?', array('停止', $stime));

		$linePause = LinePauseAR::model()->find("status = ? AND pause_time > ?" , array(1,$stime));

		$status = 'play';
		if(empty($lineRun) || !empty($lineStop)) {//
			$status = 'halt';
		} elseif(!empty($linePause)) {
			if($linePause->pause_type === '计划停线') {
				$status = 'white-pause';
			} else {
				$status = 'red-pause';
			}
		}
		return $status;
	}

	public function queryLineSpeed() {
		$sql = "SELECT value FROM device_parameter WHERE name='line_speed'";
		$value = Yii::app()->db->createCommand($sql)->queryScalar();
		return intval($value);
	}

	public function queryPauseSeat($stime , $etime) {
		$linePause = LinePauseAR::model()->find("status = ? AND pause_time > ?" , array(1,$stime));
		$seat = '';
		if(!empty($linePause)) {
			$sql = "SELECT display_name FROM node WHERE id=" . $linePause->node_id;
			$seat = Yii::app()->db->createCommand($sql)->queryScalar();
		}

		return $seat;
	}

	public function queryLinePauseDetail($stime , $etime) {
		$sections = array(
				'T1','T2','T3','C1','C2','F1','F2','VQ1','L1','EF1','EF2','EF3'
				);
		$ret = array();
		$ret['total'] = 0;
		foreach($sections as $section) {
			$type = '';
			if(in_array($section, array('L1','EF1','EF2','EF3'))) {
				$type = 'device';
			}
			$time = $this->queryLinePauseTime($section, $stime, $etime, 'all', $type);

			$ret[$section] = intval($time / 60);
			$ret['total'] += $ret[$section];
		}
		//merge L1, EF1 EF2 EF3
		$ret['device'] = $ret['L1'] + $ret['EF1'] + $ret['EF2'] + $ret['EF3'];
		return $ret;
	}


	public function queryDPU($stime, $etime, $node = 'VQ1') {
		$conditions = array();
		if(!empty($stime)) {
			$conditions[] = "create_time >= '$stime'";
		}
		if(!empty($etime)) {
			$conditions[] = "create_time <= '$etime'";
		}
		$condition = join(' AND ', $conditions);
		if(!empty($condition)) {
			$condition = 'WHERE ' . $condition;
		}

		$cars = 0;
		$total = 0;
		$nodeIdStr = $this->parseNodeId($node);
		$arraySeries = Series::parseSeries('all');

		foreach($arraySeries as $series) {
			$tables = $this->parseTables($node,$series);
			foreach($tables as $table=>$nodeName) {
				$countSql = "(SELECT count(*) FROM $table $condition)";
				$total += Yii::app()->db->createCommand($countSql)->queryScalar();
			}
			$sql = "SELECT count(DISTINCT car_id) FROM node_trace WHERE pass_time >= '$stime' AND pass_time <= '$etime' AND node_id IN ($nodeIdStr) AND car_series='$series'";
			$cars += Yii::app()->db->createCommand($sql)->queryScalar();

		}
		$dpu = '-';
		if(!empty($cars)) {
			$dpu = round($total / $cars, 2);
		}
		return $dpu;
	}

	public function queryQualified($stime, $etime, $node = "VQ1" , $series = 'all', $roundBit = 3) {
		$cars = 0;
		$faults = 0;
		$nodeIdStr = $this->parseNodeId($node);
		$arraySeries = Series::parseSeries($series);


		$conditions = array("status != '在线修复'");
		if(!empty($stime)) {
			$conditions[] = "create_time >= '$stime'";
		}
		if(!empty($etime)) {
			$conditions[] = "create_time <= '$etime'";
		}
		$condition = join(' AND ', $conditions);
		if(!empty($condition)) {
			$condition = 'WHERE ' . $condition;
		}


		foreach($arraySeries as $series) {
			$total = 0;
			$dataSqls = array();
			$tables = $this->parseTables($node, $series);
			foreach($tables as $table=>$nodeName) {
				$dataSqls[] = "(SELECT car_id FROM $table $condition)";
			}
			$sql = join(' UNION ALL ', $dataSqls);
			$datas = Yii::app()->db->createCommand($sql)->queryColumn();
			$datas = array_unique($datas);
			$faults += count($datas);

			$sql = "SELECT count(DISTINCT car_id) FROM node_trace WHERE pass_time >= '$stime' AND pass_time <= '$etime' AND node_id IN ($nodeIdStr) AND car_series = '$series'";
			$cars += Yii::app()->db->createCommand($sql)->queryScalar();
		}
		$rate = empty($cars) ? 0 : round(($cars - $faults) / $cars, $roundBit);
		$rate = empty($cars) ? '-' : $rate * 100 . "%";


		return $rate;
	}

	public function queryOtherCall($section, $curDay) {
		//$calls = AndonCallAR::model()->findAll("status = ? AND call_time > ? ORDER by call_time DESC", array(1, $curDay));

		$pause = LinePauseAR::model()->find("status = ? AND pause_time > ?" , array(1,$curDay));



		$sql = "SELECT id FROM node WHERE section='$section'";
		$nodeIds = Yii::app()->db->createCommand($sql)->queryColumn();

		$callStatus = '';
		//foreach($calls as $call) {
		//	if(!in_array($call->node_id, $nodeIds)) {
		//		$callStatus[] = $this->mapSection($call->node_id) . $call->call_type;
		//	}
		//}
		if(!empty($pause) && !in_array($pause->node_id, $nodeIds)) {
			$sql = "SELECT display_name FROM node WHERE id={$pause->node_id}" ;
			$seat = Yii::app()->db->createCommand($sql)->queryScalar();
			$callStatus = $seat . $pause->pause_type;
		}

		return $callStatus;
	}

	public function queryCallStatus($section, $curDay) {
		$condition = "";
		if(!empty($section)) {
			$sql = "SELECT id FROM node WHERE section='$section'";
			$nodeIds = Yii::app()->db->createCommand($sql)->queryColumn();
			if(empty($nodeIds)) {
				return array();
			}
			$nodeIdStr = join(',', $nodeIds);
			$condition = "AND node_id IN ($nodeIdStr)";
		}



		$calls = AndonCallAR::model()->findAll("status = ? AND call_time > ? $condition ORDER by call_time DESC", array(1, $curDay));


		$pause = LinePauseAR::model()->find("status = ? AND pause_time > ?" , array(1,$curDay));

		$seatStatus = array();
		$sectionStatus = array();
		foreach($calls as $call) {
			$otherSection = $this->mapSection($call->node_id);
			list($fullSeat, $seat) = $this->mapSeat($call->node_id);
			if(in_array($fullSeat,array('L1','EF1','EF2','EF3'))) {
				$otherSection = $fullSeat;
			}
			$callType = $this->mapCallType($call->call_type);
			if($call->call_type === '质量关卡') {
				$multi = !empty($seatStatus[$call->node_id]);
				if(!empty($section)) {
					$seatStatus[$call->node_id] = array(
							'node_id' => $call->node_id,
							'seat' => $seat,
							'full_seat' => $fullSeat,
							'section' => $otherSection,
							'background_text' => 'QG',
							'background_font_color' => 'red',
							'foreground_text' => $seat,
							'foreground_font_color' => 'black',
							'foreground_color' => 'yellow',
							'multi' => $multi,
							'flash_type' => $multi ? 'fast' : 'normal',
							);
				}
				$sectionStatus['QG'] = array(
						'section' => 'QG',
						'type' => 'flash',
						'background_text' => $otherSection,
						'background_font_color' => 'red',
						'foreground_text' => 'QG',
						'foreground_font_color' => 'black',
						'foreground_color' => 'yellow',
						);
			} else {
				if(isset($seatStatus[$call->node_id])) {
					$seatStatus[$call->node_id]['multi'] = true;
					$seatStatus[$call->node_id]['flash_type'] = 'fast';
					continue;
				}
				$flashType = ($callType === 'A' || $callType === '设备故障' ) ? 'normal' : 'block';
				$seatStatus[$call->node_id] = array(
						'node_id' => $call->node_id,
						'seat' => $seat,
						'full_seat' => $fullSeat,
						'section' => $otherSection,
						'background_text' => $callType === '设备故障' ? '&nbsp;' : $callType,
						'background_font_color' => $callType === 'A' ? 'yellow' : 'red',
						'foreground_text' => $callType === '设备故障' ? ($fullSeat === 'L1' ? '主链' : $fullSeat) : $seat,
						'foreground_font_color' => 'black',
						'foreground_color' => 'yellow',
						'multi' => false,
						'flash_type' => $flashType,
						);

				$sectionStatus[$otherSection] = array(
						'section' => $otherSection,
						'type' => 'block',
						'background_text' => '&nbsp;',
						'background_font_color' => 'red',
						'foreground_text' => $otherSection,
						'foreground_font_color' => 'red',
						'foreground_color' => 'grey',
						);
			}
		}

		if(!empty($pause) ) {
			$otherSection = $this->mapSection($pause->node_id);
			list($fullSeat, $seat) = $this->mapSeat($pause->node_id);
			if(in_array($fullSeat,array('L1','EF1','EF2','EF3'))) {
				$otherSection = $fullSeat;
			}

			if(!empty($seatStatus[$pause->node_id])) {
				$seatStatus[$pause->node_id]['background_font_color'] = 'red';
				$seatStatus[$pause->node_id]['background_text'] = intval((time() - strtotime($pause->pause_time)) / 60);
				$seatStatus[$pause->node_id]['foreground_font_color'] = 'white';
				$seatStatus[$pause->node_id]['foreground_color'] = 'red';
				$seatStatus[$pause->node_id]['multi'] = false;
				$seatStatus[$pause->node_id]['flash_type'] = 'red';
			} else {
				$seatStatus[$pause->node_id] = array(
						'node_id' => $pause->node_id,
						'seat' => $seat,
						'section' => $otherSection,
						'full_seat' => $fullSeat,
						'background_text' => intval((time() - strtotime($pause->pause_time)) / 60),
						'background_font_color' => 'red',
						'foreground_text' => $seat === '00' ? ($fullSeat === 'L1' ? '主链' : $fullSeat) : $seat ,
						'foreground_font_color' => 'white',
						'foreground_color' => 'red',
						'multi' => isset($seatStatus[$pause->node_id]['multi']) ? $seatStatus[$pause->node_id]['multi'] : false,
						'flash_type' => 'red',
						);

			}
			$otherSection = $this->mapSection($pause->node_id);
			if(in_array($fullSeat,array('L1','EF1','EF2','EF3'))) {
				$otherSection = $fullSeat;
			}
			if($otherSection !== $section) {
				$sectionStatus[$otherSection] = array(
						'section' => $otherSection,
						'type' => 'block',
						'background_text' => '',
						'background_font_color' => 'red',
						'foreground_text' => $otherSection === 'L1' ? '主链' : $otherSection,
						'foreground_font_color' => 'red',
						'foreground_color' => 'grey',
						);
			}
		}

		$runStatus = $this->queryLineStatus($curDay, '');

		$retSeats = array_values($seatStatus);
		if(!empty($section)) {
			$retSeats = array();
			foreach($seatStatus as $status) {
				if($status['section'] === $section || in_array($status['section'],array('L1','EF1','EF2','EF3'))) {
					$retSeats[] = $status;
				}
			}
		}

		return array('seatStatus' => $retSeats, 'sectionStatus' => $sectionStatus, 'lineStatus' => $runStatus , $curDay);
	}

	protected function mapSection($nodeId) {
		$sql = "SELECT section FROM node WHERE id=$nodeId";
		$section = Yii::app()->db->createCommand($sql)->queryScalar();
		return $section;
	}

	protected function mapSeat($nodeId) {
		$sql = "SELECT name,type FROM node WHERE id=$nodeId";
		$seat = Yii::app()->db->createCommand($sql)->queryRow();

		$name = $seat['name'];
		if($seat['type'] !== 'device') {
			$name = sprintf('%02d', substr($name, 1));
		} else {
			$name = '00';
		}
		return array($seat['name'], $name);
	}



	protected function mapCallType($callType) {
		if($callType === '工位求助') {
			return 'A';
		}
		if($callType === '工段质量') {
			return 'QS';
		}
		if($callType === '质量关卡') {
			return 'QG';
		}
		if($callType === '设备故障') {
			return '设备故障';
		}

	}

	protected function parseTables($node, $series) {
		$tablePrefixs = array(
				'VQ1_STATIC_TEST' => 10,
				'VQ2_ROAD_TEST' => 15,
				'VQ2_LEAK_TEST' => 16,
				'VQ3_FACADE_TEST' => 17,
				);
		$nodeTables = array(
				'VQ1' => 'VQ1_STATIC_TEST',
				'ROAD_TEST_FINISH' => 'VQ2_ROAD_TEST',
				'VQ2' => 'VQ2_LEAK_TEST',
				'VQ3' => 'VQ3_FACADE_TEST',
				);

		$temps = array();
		if(empty($node) || $node === 'all') {
			$temps = $tablePrefixs;
		} elseif($node === 'VQ2_ALL') {
			$temps = array(
					'VQ2_ROAD_TEST' => 15,
					'VQ2_LEAK_TEST' => 16,
				      );
		} elseif(!empty($nodeTables[$node])) {
			$temps = array($nodeTables[$node]=>$tablePrefixs[$nodeTables[$node]]);
		}

		$tables = array();
		if(empty($series) || $series === 'all') {
			$series = array('F0', 'M6', '6B');
		} else {
			$series = explode(',', $series);
		}
		foreach($temps as $prefix=>$name) {
			foreach($series as $serie) {
				$tables[$prefix . "_" .$serie] = $name;
			}
		}

		return $tables;
	}

	private function parseNodeId($node) {
		$nodeIds = array(
				'PBS' => 1,
				'T0'  => 2,
				'VQ1' => 10,
				'CHECK_LINE' => 13,
				'ROAD_TEST_FINISH' => 15,
				'VQ2' => 16,
				'VQ2_ALL' => '13,15,16',
				'VQ3' => 17,
				'CHECK_IN' => 18,
				'CHECK_OUT' => 19,
				);

		if(empty($node) || $node === 'all') {
			return join(',', array_values($nodeIds));
		} else {
			return $nodeIds[$node];
		}

	}

	// private function parseSeries($series) {
	// 	if(empty($series) || $series === 'all') {
	// 		$series = array('F0', 'M6', '6B');
	// 	} else {
	// 		$series = explode(',', $series);
	// 	}
	// 	return $series;
	// }

}
