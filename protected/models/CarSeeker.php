<?php
Yii::import('application.models.AR.CarAR');
Yii::import('application.models.AR.OrderConfigAR');
Yii::import('application.models.AR.OrderAR');
Yii::import('application.models.AR.WarehouseAR');
Yii::import('application.models.AR.LaneAR');
Yii::import('application.models.Car');

class CarSeeker
{
	public function __construct(){
	}

	private static $NODE_BALANCE_STATE = array(
		'PBS' => array('彩车身库','预上线'),
		'PBS-inventory' => array('彩车身库'),
		'PBS-inQueue' => array('预上线'),
		'onLine-all' => array('T1工段' ,'T2工段', 'T3工段', 'C1工段', 'C2工段', 'F1工段', 'F2工段', 'VQ1检验','II_T1工段' ,'II_T2工段', 'II_T3工段', 'II_C1工段', 'II_C2工段', 'II_F1工段', 'II_F2工段', 'II_VQ1检验'),
		'onLine' => array('T1工段' ,'T2工段', 'T3工段', 'C1工段', 'C2工段', 'F1工段', 'F2工段', 'VQ1检验'),
		'onLine-2' => array('II_T1工段' ,'II_T2工段', 'II_T3工段', 'II_C1工段', 'II_C2工段', 'II_F1工段', 'II_F2工段', 'II_VQ1检验'),
		'VQ1' => array('VQ1异常','VQ1退库'),
		'VQ1-NORMAL' => array('VQ1异常'),
		'VQ1-RETURN'=> array('VQ1退库'),
		'VQ2' => array('VQ1合格', '出生产车间', '检测线缓冲','VQ2检测线', 'VQ2路试', 'VQ2淋雨', 'VQ2异常.路试', 'VQ2异常.漏雨', 'VQ2退库'),
		'VQ2-NORMAL' => array('VQ1合格', '出生产车间', '检测线缓冲','VQ2检测线', 'VQ2路试', 'VQ2淋雨', 'VQ2异常.路试', 'VQ2异常.漏雨'),
		'VQ2-RETURN'=> array('VQ2退库'),
		'VQ3' => array('VQ3检验' ,'VQ3合格', 'VQ3异常','VQ3退库'),
		'VQ3-OK' => array('VQ3合格'),
		'VQ3-NORMAL' => array('VQ3检验' ,'VQ3合格', 'VQ3异常'),
		'recycle' => array('VQ1异常','VQ1合格', 'VQ1退库', '出生产车间', '检测线缓冲','VQ2检测线','VQ2路试', 'VQ2淋雨', 'VQ2异常.路试', 'VQ2异常.漏雨' , 'VQ2退库', 'VQ3检验' ,'VQ3合格', 'VQ3异常', 'VQ3退库'),
		'VQ3-RETURN'=> array('VQ3退库'),
		'WH' => array('成品库','WDI'),
		'WHin' => array('成品库'),
		'WH-1' => array('成品库'),
		'WH-2' => array('成品库'),
		'WH-5' => array('成品库'),
		'WH-3' => array('成品库'),
		'WH-4' => array('成品库'),
		//'WH-5' => array('成品库'),
		'WH-27-export' => array('成品库'),
		'WH-X' => array('成品库'),
		//'WH-T' => array('成品库'),
		'WH-WDI' => array('WDI'),
		'assembly' => array('T1工段' ,'T2工段', 'T3工段', 'C1工段', 'C2工段', 'F1工段', 'F2工段', 'VQ1检验', 'II_T1工段' ,'II_T2工段', 'II_T3工段', 'II_C1工段', 'II_C2工段', 'II_F1工段', 'II_F2工段', 'II_VQ1检验', 'VQ1异常','VQ1合格', '出生产车间' , 'VQ1退库', '检测线缓冲','VQ2检测线','VQ2路试', 'VQ2淋雨', 'VQ2异常.路试', 'VQ2异常.漏雨' , 'VQ2退库', 'VQ3检验' ,'VQ3合格', 'VQ3异常' , 'VQ3退库','成品库', 'WDI'),
	);

	private static $COLD_RESISTANT = array('非耐寒','耐寒');

	public function queryCheckinDetail ($startTime, $endTime, $series='', $curPage=0, $perPage=0) {
		if(empty($startTime) || empty($endTime)){
			throw new Exception ('起止时间不可为空');
		}

		$configName = $this->configNameList();
		$condition = "warehouse_time>='$startTime' AND warehouse_time<='$endTime'";
		if(!empty($series)){
			$condition .= " AND series='$series'";
		}
		$limit = "";
		if(!empty($perPage)) {
			$offset = ($curPage - 1) * $perPage;
			$limit = "LIMIT $offset, $perPage";
		}

		$dataSql = "SELECT id as car_id,serial_number,warehouse_id,vin,series,type,config_id,cold_resistant,color,engine_code,finish_time,warehouse_time,remark,special_order,assembly_line
				      FROM car
				     WHERE $condition
			      ORDER BY order_id, lane_id, distribute_time ASC $limit";

		$datas = Yii::app()->db->createCommand($dataSql)->queryAll();

		foreach($datas as &$data){
			if($data['series'] == '6B') $data['series'] = '思锐';
			$data['config_name'] = $configName[$data['config_id']];
			$data['cold'] = self::$COLD_RESISTANT[$data['cold_resistant']];

			$data['row'] = '-';
			if(!empty($data['warehouse_id'])){
				$data['row'] = WarehouseAR::model()->findByPk($data['warehouse_id'])->row;
			}
		}

		$countSql = "SELECT count(*) FROM car where $condition";
		$total = Yii::app()->db->createCommand($countSql)->queryScalar();

		return array($total, $datas);
	}

	public function queryCheckoutDetail ($startTime, $endTime, $series='', $curPage=0, $perPage=0) {
		if(empty($startTime) || empty($endTime)){
			throw new Exception ('起止时间不可为空');
		}

		$configName = $this->configNameList();
		$condition = "distribute_time>='$startTime' AND distribute_time<='$endTime'";
		if(!empty($series)){
			$condition .= " AND series='$series'";
		}
		$limit = "";
		if(!empty($perPage)) {
			$offset = ($curPage - 1) * $perPage;
			$limit = "LIMIT $offset, $perPage";
		}

		$dataSql = "SELECT id as car_id,vin,series,type,config_id,cold_resistant,color,engine_code,distributor_name,lane_id,distribute_time,order_id,remark,special_order
				  FROM car
				  WHERE $condition
			  ORDER BY distribute_time ASC $limit";

		$datas = Yii::app()->db->createCommand($dataSql)->queryAll();

		foreach($datas as &$data){
			if($data['series'] == '6B') $data['series'] = '思锐';
			$data['config_name'] = $configName[$data['config_id']];
			$data['cold'] = self::$COLD_RESISTANT[$data['cold_resistant']];

			$data['order_number'] = '-';
			if(!empty($data['order_id'])){
				$data['order_number'] = OrderAR::model()->findByPk($data['order_id'])->order_number;
			}

			$data['lane'] = '-';
			if(!empty($data['lane_id'])){
				$data['lane'] = LaneAR::model()->findByPk($data['lane_id'])->name;
			}
		}

		$countSql = "SELECT count(*) FROM car where $condition";
		$total = Yii::app()->db->createCommand($countSql)->queryScalar();

		return array($total, $datas);
	}

	public function queryBalanceDetail ($state, $series='', $curPage=0, $perPage=0, $whAvailableOnly = false, $area='') {
		if(!is_array($state)) {
			if(!empty(self::$NODE_BALANCE_STATE[$state])) {
				$states = self::$NODE_BALANCE_STATE[$state];
			} else {
				$states = array($state);
			}
		} else {
			$states = $state;
		}

		$sql = "SELECT id, name, order_config_id FROM car_config";
		$configs = Yii::app()->db->createCommand($sql)->queryAll();
		$configInfos = array();
		foreach($configs as $config){
			$configInfos[$config['id']]['configName'] = $config['name'];
			$order = OrderConfigAR::model()->findByPk($config['order_config_id']);
			if(!empty($order)){
				$configInfos[$config['id']]['orderConfigName'] = $order->name;
			} else {
				$configInfos[$config['id']]['orderConfigName'] = $config['name'];
			}
		}
		$sql = "SELECT car_type, car_model FROM car_type_map";
		$carModels = Yii::app()->db->createCommand($sql)->queryAll();
		$modelInfo = array();
		foreach($carModels as $carModel){
			$modelInfo[$carModel['car_type']]= $carModel['car_model'];
		}
		$sql = "SELECT id, row FROM warehouse";
		$rows = Yii::app()->db->createCommand($sql)->queryAll();
		$rowInfo = array();
		foreach($rows as $row){
			$rowInfo[$row['id']] = $row['row'];
		}

		$str = "'" . join("','", $states) . "'";
		$condition = " WHERE status IN ($str)";
		if(!empty($series)){
			$condition .= " AND series='$series'";
		}

		if($whAvailableOnly){
			$condition .= " AND warehouse_id > 1 AND warehouse_id < 1000 AND special_property=0";
		}
		if(!empty($area)){
			$areaIds = $this->getWarehoseAreaIds($area);
			$condition .= " AND warehouse_id>={$areaIds['areaMin']} AND warehouse_id<={$areaIds['areaMax']}";
		}

		$limit = "";
		if(!empty($perPage)) {
			$offset = ($curPage - 1) * $perPage;
			$limit = "LIMIT $offset, $perPage";
		}
		$sql = "SELECT id as car_id, series,serial_number, vin, type, color, cold_resistant, status, config_id, modify_time,warehouse_id,area, assembly_line, finish_time, warehouse_time, distribute_time,special_order, remark FROM car $condition ORDER BY finish_time ASC $limit";
        $cars = Yii::app()->db->createCommand($sql)->queryAll();

        foreach($cars as &$car){
        	if($car['series'] == '6B') $car['series'] = '思锐';
        	if(!empty($car['type'])){
	        	$car['car_model'] = $modelInfo[$car['type']];
        	} else {
        		$car['car_model'] ='';
        	}
        	if(!empty($car['config_id'])){
        		$car['config_Name'] = $configInfos[$car['config_id']]['configName'];
	        	$car['order_config_name'] = $configInfos[$car['config_id']]['orderConfigName'];
	        	$car['type_info'] = $car['car_model'] . '/' . $car['order_config_name'];
        	}else {
        		$car['config_Name'] = '';
	        	$car['order_config_name'] = '';
	        	$car['type_info'] = $car['type'];
        	}
        	if(!empty($car['warehouse_id'])){
        		$car['row']=$rowInfo[$car['warehouse_id']];
        	}else{
        		$car['row']='-';
        	}
        	$car['cold'] = $car['cold_resistant'] == 1 ? '耐寒':'非耐寒';
        	if($car['finish_time'] == '0000-00-00 00:00:00')  $car['finish_time'] = '-';
        	if($car['warehouse_time'] == '0000-00-00 00:00:00')  $car['warehouse_time'] = '-';
        }

        $sql = "SELECT count(*) FROM car $condition";
		$total = Yii::app()->db->createCommand($sql)->queryScalar();

		$sql = "SELECT DISTINCT(area) FROM car $condition ORDER BY area ASC";
        $areaArray = Yii::app()->db->createCommand($sql)->queryColumn();

        return  array($total, $cars, $areaArray);
	}

	public function queryAssemblyBalance ($state) {
		$seriesArray = Series::parseSeries('all');
		$seriesName = Series::getNameList();
		$stateArray = $this->stateArray($state);
		$stateName = $this->stateName();

		$detail = array();
		$dataSeriesX = array();
		$dataSeriesY = array();
		$seriesTotal = array();
		$stateTotal = array();
		foreach($seriesArray as $series){
			$seriesTotal[$seriesName[$series]] = 0;
		}
		foreach($stateArray as $state){
			$stateTotal[$stateName[$state]] = 0;
		}

		foreach($stateArray as $state){
			$temp = array();
			foreach($seriesArray as $series){
				$count = $this->countStateCars($state, $series);
				$temp[$seriesName[$series]] = $count;
				$dataSeriesY[$seriesName[$series]][] = intval($count);
				$seriesTotal[$seriesName[$series]] += intval($count);
				$stateTotal[$stateName[$state]] += intval($count);
			}
			$detail[] = array_merge(array('state' => $stateName[$state]), $temp);
			$dataSeriesX[] = $stateName[$state];
		}

		$carSeries = array();
		foreach($seriesArray as $key => $series){
			$carSeries[] = $seriesName[$series];
		}

		return array(
			'carSeries' => $carSeries,
			'detail' => $detail,
			'seriesTotal'=> $seriesTotal,
			'stateTotal'=> $stateTotal,
			'series' => array(
				'x'=> $dataSeriesX,
				'y'=> $dataSeriesY,
			)
		);
	}

	public function balanceDistribute ($state, $series) {

		$stateName = $this->stateName();

		$colorArray = $this->colorCategories($series);
		$configColdArray = $this->configColdArray($state, $series);

		$detail = array();
		$colorTotal = array();
		foreach($colorArray as $color){
			$colorTotal[$color] = 0;
		}

		$configNameArray = array();
		$configTotal = array();
		foreach($configColdArray as $configCold){
			$configNameArray[] = $configCold['name'];
			$configTotal[$configCold['name']]['count'] = 0;
			$configTotal[$configCold['name']]['orderConfigId'] = $configCold['order_config_id'];
			$configTotal[$configCold['name']]['coldResistant'] = $configCold['cold_resistant'];
		}
		$dataPie = array();

		foreach($colorArray as $index => $color){
			$data = array();
			$temp = array();

			$colorCount = $this->countStateCars($state, $series, $color);
			$data['y'] = $colorCount;
			$colorTotal[$color] += $colorCount;

			//$configColdArray = $this->configColdArray($state, $series, $color);
			$drilldownCategories = array();
			$drilldownData = array();
			foreach($configColdArray as $configCold){
				$configCount = $this->countStateCars($state,$series, $color, $configCold['order_config_id'], $configCold['cold_resistant']);
				if(!empty($configCount)){
					$drilldownCategories[] = $configCold['name'];
					$drilldownData[] = $configCount;
					$configTotal[$configCold['name']]['count'] += $configCount;
				}
				// $temp[$configCold['name']] = $configCount;
				$temp[$configCold['name']]['count'] = $configCount;
				$temp[$configCold['name']]['orderConfigId'] = $configCold['order_config_id'];
				$temp[$configCold['name']]['coldResistant'] = $configCold['cold_resistant'];

			}
			$data['drilldown'] = array(
				'name' => $color,
				'categories' => $drilldownCategories,
				'data' => $drilldownData,
			);
			$dataPie[] = $data;
			$detail[] = array_merge(array('color' => $color), $temp);
		}

		// sort($configNameArray);

		return array(
			'colorArray' => $colorArray,
			'configNameArray' =>$configNameArray,
			'detail' => $detail,
			'colorTotal'=> $colorTotal,
			'configTotal'=> $configTotal,
			'dataPie' => $dataPie,
		);

	}

	public function queryBalanceCars ($state, $series, $whAvailableOnly=false) {
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
		$condition = "status IN ($str)";
		if(!empty($series)){
			$condition .= " AND series='$series'";
		}
		if($whAvailableOnly){
			$condition .= " AND warehouse_id > 1 AND warehouse_id < 1000 AND special_property=0";
		}

		$sql = "SELECT car_id,, `status`, vin, series, color, type, car_model, config_id, config_name, order_config_name,
				FROM view_car_info_main
				WHERE $condition";
		$cars = Yii::app()->db->createCommand($sql)->queryAll();
		foreach($cars as &$car){
			$car['type_info'] = $car['car_model'] . "/" . $car['order_config_name'];
			if($car['cold_resistant'] == 1){
				$car['type_info'] .= "/耐寒";
			} else {
				$car['type_info'] .= "/非耐寒";
			}
		}
		return $cars;
	}

	public function getBalanceCars ($state,$orderConfigId,$coldResistant,$color,$whAvailableOnly=false,$recyclePeriod="") {
		$conditions = array();
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
		$conditions[] = "status IN ($str)";

		if(!empty($orderConfigId)){
			$conditions[] = "order_config_id = $orderConfigId";
		}
		if($coldResistant != 2){
			$conditions[] = "cold_resistant = $coldResistant";
		}
		if(!empty($color)){
			$conditions[] = "color = '$color'";
		}
		if($whAvailableOnly){
			$conditions[] = "warehouse_id > 1 AND warehouse_id < 1000 AND special_property=0";
		}

		if(!empty($recyclePeriod)){
			$periodArray = $this->periodArray();
			$lowRP = $periodArray[$recyclePeriod]['low'];
			$highRP = $periodArray[$recyclePeriod]['high'];
			$cc = array();
			if(!empty($lowRP)){
				$cc[] = "finish_time>'0000-00-00 00:00:00' AND TIMESTAMPDIFF(hour,finish_time,CURRENT_TIMESTAMP) > $lowRP";
			}
			if(!empty($highRP)){
				$cc[] = "TIMESTAMPDIFF(hour,finish_time,CURRENT_TIMESTAMP) <= $highRP";
			}
			$con = join(" AND ", $cc);
			if(!empty($cc))	$conditions[] = $con;
		}

		$condition = join(" AND ", $conditions);

		$sql = "SELECT car_id, `status`, vin, serial_number, series, color, car_type, car_model, config_id, config_name, order_config_name, finish_time, warehouse_time, warehouse_id, cold_resistant
				FROM view_car_info_main
				WHERE $condition
				ORDER BY finish_time ASC" ;
		$cars = Yii::app()->db->createCommand($sql)->queryAll();
		foreach($cars as &$car){
			$car['type_info'] = $car['car_model'] . "/" . $car['order_config_name'];
			$car['cold'] = self::$COLD_RESISTANT[$car['cold_resistant']];
			$car['row'] = "-";
			if(!empty($car["warehouse_id"])){
				$car['row'] = WarehouseAR::model()->findByPk($car['warehouse_id'])->row;
			}

			$car['recycle_last'] = 0;
			if($car['finish_time'] > "0000-00-00 00:00:00"){
				$warehouseTime = $car['warehouse_time'] > "0000-00-00 00:00:00" ? $warehouseTime = $car['warehouse_time'] : date("Y-m-d H:i:s");
				$car['recycle_last'] = (strtotime($warehouseTime) - strtotime($car['finish_time'])) / 3600;
				$car['recycle_last'] = round($car['recycle_last'], 1);
			}
		}

		return $cars;
	}

	public function queryRecycleBalancePeriod ($state, $series) {
		$seriesArray = Series::parseSeries($series);
		$seriesName = Series::getNameList();
		$stateArray = $this->stateArray($state);
		$stateName = $this->stateName();
		$periodArray = $this->periodArray();

		$detail = array();
		$stateTotal = array();
		$periodTotal = array();
		$dataDonut = array();
		foreach($stateArray as $state){
			$stateTotal[$stateName[$state]]['countSum'] = 0;
			foreach($seriesArray as $series){
				$stateTotal[$stateName[$state]][$seriesName[$series]]=0;
			}
		}
		foreach($periodArray as $periodName => $period){
			$periodTotal[$periodName]['countSum'] = 0;
			foreach($seriesArray as $series){
				$periodTotal[$periodName][$seriesName[$series]]=0;
			}
		}

		$iColor = 0;
		foreach($stateArray as $state){
			$temp = array();
			$data = array();
			$drilldownCategories = array();
			$drilldownData = array();

			foreach($periodArray as $periodName => $period){
				$countSum = 0;
				foreach($seriesArray as $series){
					$count = $this->countStateCars($state, $series, $color='', $orderConfigId=0, $coldResistant=2, $period['low'],$period['high']);
					$temp[$periodName][$seriesName[$series]] = $count;
					$periodTotal[$periodName][$seriesName[$series]] += $count;
					$stateTotal[$stateName[$state]][$seriesName[$series]] =+ $count;
					$countSum += $count;
				}
				$drilldownCategories[] = $periodName;
				$drilldownData[] = $countSum;

				$temp[$periodName]['countSum'] = $countSum;
				$periodTotal[$periodName]['countSum'] += $countSum;
				$stateTotal[$stateName[$state]]['countSum'] += $countSum;
				$data['y'] = $stateTotal[$stateName[$state]]['countSum'];
			}
			$data['colorIndex'] = $iColor++;
			$data['drilldown'] = array(
				'name' => $stateName[$state],
				'categories' => $drilldownCategories,
				'data' => $drilldownData,
			);

			$dataDonut[$stateName[$state]] = $data;
			$detail[] = array_merge(array('state' => $stateName[$state]), $temp);
		}

		$recyclePeriod = array_keys($periodArray);
		return  array(
			'recyclePeriod' => $recyclePeriod,
			'detail' => $detail,
			'stateTotal'=> $stateTotal,
			'periodTotal'=> $periodTotal,
			'dataDonut' => $dataDonut,
		);
	}

	public function getRecycleCars ($state,$series,$recyclePeriod) {
		$conditions = array();
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
		$conditions[] = "status IN ($str)";

		if(!empty($series)){
			$conditions[] = "series='$series'";
		};

		if(!empty($recyclePeriod)){
			$periodArray = $this->periodArray();
			$lowRP = $periodArray[$recyclePeriod]['low'];
			$highRP = $periodArray[$recyclePeriod]['high'];
			$cc = array();
			if(!empty($lowRP)){
				$cc[] = "finish_time>'0000-00-00 00:00:00' AND TIMESTAMPDIFF(hour,finish_time,CURRENT_TIMESTAMP) > $lowRP";
			}
			if(!empty($highRP)){
				$cc[] = "TIMESTAMPDIFF(hour,finish_time,CURRENT_TIMESTAMP) <= $highRP";
			}
			$con = join(" AND ", $cc);
			if(!empty($cc))	$conditions[] = $con;
		}

		$condition = join(" AND ", $conditions);

		$sql = "SELECT car_id, `status`, vin, serial_number, series, color, car_type, car_model, config_id, config_name, order_config_name, finish_time, warehouse_time, warehouse_id, cold_resistant
				FROM view_car_info_main
				WHERE $condition
				ORDER BY finish_time ASC" ;
		$cars = Yii::app()->db->createCommand($sql)->queryAll();
		foreach($cars as &$car){
			$car['type_info'] = $car['car_model'] . "/" . $car['order_config_name'];
			$car['cold'] = self::$COLD_RESISTANT[$car['cold_resistant']];
			$car['row'] = "-";
			if(!empty($car["warehouse_id"])){
				$car['row'] = WarehouseAR::model()->findByPk($car['warehouse_id'])->row;
			}

			$car['recycle_last'] = 0;
			if($car['finish_time'] > "0000-00-00 00:00:00"){
				$warehouseTime = $car['warehouse_time'] > "0000-00-00 00:00:00" ? $warehouseTime = $car['warehouse_time'] : date("Y-m-d H:i:s");
				$car['recycle_last'] = (strtotime($warehouseTime) - strtotime($car['finish_time'])) / 3600;
				$car['recycle_last'] = round($car['recycle_last'], 1);
			}
		}

		return $cars;
	}

	public function countStateCars ($state,$series='', $color='', $orderConfigId=0, $coldResistant=2, $lowRP=0, $highRP=0) {
		if(!is_array($state)) {
			if(!empty(self::$NODE_BALANCE_STATE[$state])) {
				$states = self::$NODE_BALANCE_STATE[$state];
			} else {
				$states = array($state);
			}
		} else {
			$states = $state;
		}


		$sql = "SELECT id FROM car_config WHERE order_config_id = $orderConfigId";
        $configId = Yii::app()->db->createCommand($sql)->queryColumn();
        $configIds = join(',', $configId);

		$str = "'" . join("','", $states) . "'";
		$condition = " WHERE status IN ($str)";
		if(!empty($series)){
			$condition .= " AND series='$series'";
		}
		if(!empty($color)){
			$condition .= " AND color='$color'";
		}
		if(!empty($orderConfigId)){
			$condition .= " AND config_id IN ($configIds)";
		}
		if($coldResistant != 2){
			$condition .= " AND cold_resistant=$coldResistant";
		}

        if($state === 'WHin'){
			$condition .= " AND warehouse_id>1 AND warehouse_id< 1000 AND special_property=0";
		}
		if($state === 'WH-1'){
			$condition .= " AND warehouse_id>1 AND warehouse_id< 200";
		}
		if($state === 'WH-2'){
			$condition .= " AND warehouse_id > 800 AND warehouse_id < 900";
		}
		if($state === 'WH-3'){
			$condition .= " AND warehouse_id>=600 AND warehouse_id< 700";
		}
		if($state === 'WH-4'){
			$condition .= " AND warehouse_id>=400 AND warehouse_id< 500";
		}
		if($state === 'WH-5'){
			$condition .= " AND warehouse_id >700 AND warehouse_id < 800";
		}
		if($state === 'WH-27-export'){
			$condition .= " AND warehouse_id>=200 AND warehouse_id< 300 AND special_property=1";
		}
		if($state === 'WH-X'){
			$condition .= " AND warehouse_id=1000";
		}
		//if($state === 'WH-T'){
			//$condition .= " AND warehouse_id=2000";
		//}

		//recyclePeriod = now - assembly_time, or recyclePeriod = assembly_time - warehouse_time
		if(!empty($lowRP)){
			$condition .= " AND finish_time>'0000-00-00 00:00:00' AND TIMESTAMPDIFF(hour,finish_time,CURRENT_TIMESTAMP) > $lowRP";
		}
		if(!empty($highRP)){
			$condition .= " AND TIMESTAMPDIFF(hour,finish_time,CURRENT_TIMESTAMP) <= $highRP";
		}

		$sql = "SELECT count(*) FROM car $condition";
		$total = Yii::app()->db->createCommand($sql)->queryScalar();
		return $total;
	}

	public function queryOrderCar ($standbyDate, $orderNumber, $distributor, $status='all', $series='', $curPage=0, $perPage=0,$orderBy='lane_id,priority,`status`', $standbyDateEnd='', $boardNumber='', $carrier='') {
		$configNames = $this->configNameList();
		$orderNumberArray = array();
		$orderSeeker = new OrderSeeker();
		$orders = $orderSeeker-> query($standbyDate, $orderNumber, $distributor, $status, $series,$orderBy, $standbyDateEnd, $boardNumber, $carrier);

		$contConditions = array();
		foreach($orders as $order){
			$orderId= $order['id'];
			$orderNumberArray[$orderId] = $order['order_number'];
			$sqls[] = "(SELECT serial_number, vin,series,type,config_id,cold_resistant,color,engine_code,distributor_name,lane_id,distribute_time,order_id,old_wh_id,remark
							FROM car
							WHERE order_id = $orderId)";
			$countConditions[] = "order_id = $orderId";
		}

		$dataSql = join(' UNION ALL ', $sqls);
		$dataSql .= "ORDER BY order_id, distribute_time ASC";

		$countCondition = join(' OR ', $countConditions);
		$countSql = "SELECT count(*) FROM car where $countCondition";
		$total = Yii::app()->db->createCommand($countSql)->queryScalar();

		$limit = "";
		if(!empty($perPage)) {
			$offset = ($curPage - 1) * $perPage;
			$limit = " LIMIT $offset, $perPage";
			$dataSql .= $limit;
		}

		if(!empty($sqls)){
			$datas = Yii::app()->db->createCommand($dataSql)->queryAll();
		}
		if(empty($datas)){
			throw new Exception("查无车辆");
		}
		foreach($datas as &$data){
			$data['config_name'] = $configNames[$data['config_id']];
			$data['cold'] = self::$COLD_RESISTANT[$data['cold_resistant']];
			$data['order_number'] = $orderNumberArray[$data['order_id']];
			$data['lane'] = '-';
			if(!empty($data['lane_id'])){
				$lane = LaneAR::model()->findByPk($data['lane_id']);
				$data['lane'] = $lane->name;
			}
			$data['row'] = '-';
			if(!empty($data['old_wh_id'])){
				$oldRow = WarehouseAR::model()->findByPk($data['old_wh_id']);
				if(!empty($oldRow)) {
					$data['row'] = $oldRow->row;
				}
			}
		}

		return array($total, $datas);
	}

	public function queryCar ($vin='',$series='',$serial='',$line='I') {
		$conditions = array();

		if(!empty($vin)){
			$conditions[] = "vin LIKE '%$vin'";
		}
		if(!empty($series)){
			$conditions[] = "series='$series'";
		};
		if(!empty($serial)){
			$conditions[] = "serial_number LIKE '%$serial' AND assembly_line='$line'";
		}
		if(!empty($conditions)){
			$condition = join(' AND ', $conditions);
		}

		$sql = "SELECT vin FROM car WHERE $condition ORDER BY id ASC";
		$vin = Yii::app()->db->createCommand($sql)->queryScalar();
		return $vin;
	}

	public function queryManufacturePeriod($stime, $etime, $series='') {
		$queryTimes = $this->parseQueryTime($stime,$etime);
		$seriesArray = Series::parseSeries($series);
		$seriesName = Series::getNameList();

		$columnSeriesX = array();
		$columnSeriesY = array();
		$detail = array();
		$avgTotal = array();

		$periodArray = array(
			'装配' => array('start'=>'assembly_time', 'end'=>'finish_time', 'return'=>'assembly_time'),
			'VQ1' => array('start'=>'finish_time', 'end'=>'vq1_finish_time', 'return'=>'vq1_return_time'),
			'VQ2' => array('start'=>'vq1_finish_time', 'end'=>'vq2_finish_time', 'return'=>'vq2_return_time'),
			'VQ3' => array('start'=>'vq2_finish_time', 'end'=>'warehouse_time', 'return'=>'vq3_return_time'),
			// '库存周期' => array('start'=>'warehouse_time', 'end'=>'standby_time'),
			// '备车周期' => array('start'=>'standby_time', 'end'=>'distribute_time'),
		);

		foreach($periodArray as $key => $period) {
			$avgTotal[$key] = array(
				'secondTotal' => 0,
				'carCount' => 0,
				'hourAvg' => 0
			);
		}

		foreach($queryTimes as $queryTime) {
			$columnSeriesX[] = $queryTime['point'];
			$total = array();
			$count = array();
			$avg = array();
			foreach($periodArray as $key => $period) {
				$total[$key] = 0;
				$count[$key] = 0;
			}
			$cars = $this->queryPeiodCars($queryTime['stime'], $queryTime['etime'], $series);
			foreach($cars as &$car){
				foreach($periodArray as $key => $period) {
					if($car[$period['start']] > "0000-00-00 00:00:00"){
						$startTime = $car[$period['return']] > "0000-00-00 00:00:00" ? $car[$period['return']] : $car[$period['start']];
						$seconds = $this->calculatePeriod($startTime, $car[$period['end']]);
						$total[$key] += $seconds;
						$count[$key]++;

						$avgTotal[$key]['secondTotal'] += $seconds;
						$avgTotal[$key]['carCount']++;
					}
				}
			}
			foreach($periodArray as $key => $period) {
				$avg[$key] = empty($count[$key]) ? null : round($total[$key] / $count[$key] / 3600, 1);
				$columnSeriesY[$key][] = $avg[$key];
				$temp[$key] = array(
					'secondTotal' => $total[$key],
					'carCount' => $count[$key],
					'hourAvg' => is_null($avg[$key]) ? 0 : $avg[$key]
				);
			}
			$detail[] = array_merge(array('time'=>$queryTime['point']), $temp);
		}

		foreach($periodArray as $key => $period) {
			$avgTotal[$key]['hourAvg'] = empty($avgTotal[$key]['carCount']) ? 0 : round($avgTotal[$key]['secondTotal'] / $avgTotal[$key]['carCount'] / 3600, 1);
		}

		return array(
			'periodArray' => $periodArray,
			'detail' => $detail,
			'avgTotal' => $avgTotal,
			'series' => array(
				'x' => $columnSeriesX,
				'y' => $columnSeriesY
			)
		);

	}

	public function queryPeiodCars ($stime, $etime, $series='') {
		$condition = "assembly_time>='$stime' AND assembly_time<'$etime'";
		$arraySeries = Series::parseSeries($series);
		if(!(empty($series) || $series == 'all')){
			$seriesConditons = array();
		    foreach($arraySeries as $series) {
		        $seriesConditons[] = "series = '$series'";
		    }
		    $seriesConditon = "(" . join(' OR ', $seriesConditons) . ")";
		    $condition .= " AND $seriesConditon";
		}
		$sql = "SELECT id AS car_id, vin, assembly_line, `status`, special_property, series, assembly_time, finish_time, vq1_finish_time, vq2_finish_time, warehouse_time, standby_time, distribute_time, vq1_return_time, vq2_return_time, vq3_return_time
				FROM car
				WHERE $condition";
		$cars = Yii::app()->db->createCommand($sql)->queryAll();
		return $cars;
	}

	private function calculatePeriod ($start, $end) {
		$time = 0;
		if($start > '0000-00-00 00:00:00') {
			$time = $end > '0000-00-00 00:00:00' ? (strtotime($end) - strtotime($start)) : (time() - strtotime($start));
			if($start>$end) {
				$time = 0;
			}
		}
		return $time;
	}

	public function queryTestlineRecord ($vin) {
		$sql = "SELECT * FROM view_testline_summary WHERE vin='$vin'";
		$record = Yii::app()->db->createCommand($sql)->queryRow();
		$record['Light_Flag_L'] = 'F';
		$record['Light_Flag_R'] = 'F';
		if($record['LM_Flag'] === 'T' && $record['LL_Flag'] === 'T'){
			$record['Light_Flag_L'] = 'T';
		}

		if($record['RM_Flag'] === 'T' && $record['RL_Flag'] === 'T'){
			$record['Light_Flag_R'] = 'T';
		}

		return $record;
	}

	public function configColdArray ($state, $series){
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
		$condition = " WHERE status IN ($str) AND series='$series'";

		$sql = "SELECT DISTINCT order_config_id, cold_resistant, order_config_name,mark_clime FROM view_car_info_order_config $condition ORDER BY mark_clime DESC,order_config_name ASC";
		$configColdArray = Yii::app()->db->createCommand($sql)->queryAll();

		foreach($configColdArray as &$configCold){
			$configCold['name'] = '';
			if(!empty($configCold['order_config_id'])){
				$configFullName = $configCold['order_config_name'] . '/' . self::$COLD_RESISTANT[$configCold['cold_resistant']];
				$configCold['name'] = $configFullName;
			}
		}
		return $configColdArray;
	}

	public function queryVins ($vinText) {

		$vinArray = preg_split ('[\s|,|，]',$vinText);
        ArrayFunc::array_remove_empty($vinArray);

        $conditions = array();
        foreach($vinArray as &$vin){
        	$vin = str_replace('[,|，]',' ', $vin);
        	$vin = trim($vin);
        	if(strlen($vin)>=8){
	        	$conditions[] = "vin LIKE '%$vin'";
        	}
        }

        $conVin = join(' OR ', $conditions);

        $condition = "warehouse_time>'0000-00-00 00:00:00' AND ($conVin)";
        $sql = "SELECT id, vin, serial_number, series, type, order_config_name, cold_resistant, config_id, order_config_id, color, engine_code, remark, warehouse_time, order_id
        		FROM view_car_info_order_config
        		WHERE $condition";
        $datas = Yii::app()->db->createCommand($sql)->queryAll();
        foreach($datas as &$data){
        	$data['cold'] = self::$COLD_RESISTANT[$data['cold_resistant']];
        }

        return $datas;
	}

	public function countByPlanId ($planId) {
		$sql = "SELECT COUNT(*) FROM car WHERE plan_id=$planId";
		$count = Yii::app()->db->createCommand($sql)->queryScalar();
		return intval($count);
	}
	// private function parseSeries ($series) {
	// 	if(empty($series) || $series === 'all') {
 //            $series = array('F0', 'M6', '6B');
 //        } else {
 //            $series = explode(',', $series);
 //        }
	// 	return $series;
	// }

	// private function seriesName () {
	// 	$seriesName = array(
	// 		'F0' => 'F0',
	// 		'M6' => 'M6',
	// 		'6B' => '思锐'
	// 	);

	// 	return $seriesName;
	// }

	private function stateArray ($state) {
		$stateMap=array(
			'PBS' => array('PBS-inventory', 'PBS-inQueue'),
			'PBS-inventory' => array('PBS-inventory'),
			'PBS-inQueue' => array('PBS-inQueue'),
			'onLine-all' => array('onLine', 'onLine-2'),
			'onLine' => array('onLine'),
			'onLine-2' => array('onLine-2'),
			'VQ1' => array('VQ1-NORMAL', 'VQ1-RETURN'),
			'VQ2' => array('VQ2-NORMAL', 'VQ2-RETURN'),
			'VQ3' => array('VQ3-NORMAL','VQ3-RETURN'),
			'VQ3-OK' => array('VQ3-OK'),
			'recycle' => array('VQ1', 'VQ2', 'VQ3'),
			//'WH' => array('WH-1', 'WH-2', 'WH-3', 'WH-4', 'WH-5', 'WH-27-export', 'WH-X', 'WH-T', 'WH-WDI'),
			'WH' => array('WH-1', 'WH-2', 'WH-5','WH-3', 'WH-4',  'WH-27-export', 'WH-X', 'WH-WDI'),
			'WH-WDI' => array('WH-WDI'),
			'WHin' => array('WHin'),
			'assembly' => array('PBS', 'onLine', 'onLine-2', 'VQ1', 'VQ2', 'VQ3', 'WH'),
			'mergeRecyle' => array('PBS','onLine', 'onLine-2','recycle', 'WH'),
		);
		return $stateMap[$state];
	}

	private function stateName () {
		$stateName = array(
			'PBS' => 'PBS',
			'PBS-inventory' => 'PBS库存',
			'PBS-inQueue' => '预上线',
			'onLine-all' => '产线',
			'onLine' => 'I线',
			'onLine-2' => 'II线',
			'VQ1' => 'VQ1',
			'VQ1-NORMAL' => 'VQ1普通',
			'VQ1-RETURN' => 'VQ1退库',
			'VQ2' => 'VQ2',
			'VQ2-NORMAL' => 'VQ2普通',
			'VQ2-RETURN' => 'VQ2退库',
			'VQ3-NORMAL' => 'VQ3普通',
			'VQ3-RETURN' => 'VQ3退库',
			'VQ3-OK' => 'VQ3合格',
			'VQ3' => 'VQ3',
			'recycle' => '周转车',
			'WH' => '成品库',
			'WHin' => '成品库可备',
			'WH-WDI' => 'WDI',
			'assembly' => '总装',
			'WH-1' => '1号库',
			'WH-2' => '2号库',
			'WH-5' => '3号库(油库区)',
			'WH-3' => '4号库(35#)',
			'WH-4' => '5号库(14#)',
			//'WH-5' => '3号库(油库区)',
			'WH-27-export' => '出口车(27#)',
			'WH-X' => 'X(非商品车区)',
			//'WH-T' => 'T(非库位区)',
			'WH-WDI' => 'WDI'
		);

		return $stateName;
	}

	private function periodArray () {
		$periodArray = array(
			'&lt;8H' => array('low'=>0,'high'=>8),
			'8-16H' => array('low'=>8,'high'=>16),
			'16-24H' => array('low'=>16,'high'=>24),
			'>24H' => array('low'=>24,'high'=>0),
		);

		return $periodArray;
	}

	private function configNameList () {
		$configName = array();
		$sql = "SELECT car_config_id, order_config_id , name , car_model FROM view_config_name";
		$datas = Yii::app()->db->createCommand($sql)->queryAll();
		foreach ($datas as $data){
			$configName[$data['car_config_id']] = $data['car_model'] . '/' . $data['name'];
		}
		return $configName;
	}

	private function configIdList () {
		$configIds = array();
		$sql = "SELECT car_config_id, order_config_id , name FROM view_config_name";
		$datas = Yii::app()->db->createCommand($sql)->queryAll();
		foreach ($data as $data){
			if(empty($configId[$data['name']])){
				$configIds[$data['name']] = array();
			}
			$configIds[$data['name']][] = $data['car_config_id'];
		}
		return $configIds;
	}

	private function colorCategories ($series) {
		$colors = array();
		$sql = "SELECT color from car_color_map WHERE series='$series'";
		$datas = Yii::app()->db->createCommand($sql)->queryColumn();
		// foreach($datas as $data){
		// 	$colors[] = $data['color'];
		// }
		return $datas;
	}

	private function configCategories ($series) {
		$configs = array();
		$sql = "SELECT id from car_config WHERE series='$series'";
		$datas = Yii::app()->db->createCommand($sql)->queryColumn();
		foreach($datas as $data){
			$configs[] = $data['id'];
		}
		return $configs;
	}

	private function getWarehoseAreaIds ($area) {
		$sql = "SELECT MIN(id) AS areaMin, MAX(id) AS areaMax FROM warehouse WHERE area='$area'";
		$data = Yii::app()->db->createCommand($sql)->queryRow();

		return array("areaMin"=>$data['areaMin'], "areaMax"=>$data['areaMax']);
	}

	public function parseQueryTime($stime,$etime) {
		$s = strtotime($stime);
		$e = strtotime($etime);

		$sd = date('Ymd', $s);
		$ed = date('Ymd', $e);

		$lastHour = ($e - $s) / 3600;
		$lastDay = (strtotime($ed) - strtotime($sd)) / 86400;

		$ret = array();
		if($lastHour <= 24) {
			$pointFormat = 'H';
			$format = 'Y-m-d H:i:s';
			$slice = 3600;
		} elseif($lastDay <= 31) {
			$pointFormat = 'm-d';
			$format = 'Y-m-d H:i:s';
			$slice = 86400;
		} else {
			$pointFormat = 'Y-m';
			$format = 'Y-m-d H:i:s';
		}

		//首个分割段
		$t0 = $s;
		if($pointFormat === 'H'){
			$eNextH = strtotime('+1 hour', $t0);
			$ee = date('Y-m-d H', $eNextH) . ":00:00";
			$t = strtotime($ee);
		} else if($pointFormat === 'm-d'){
			$eNextD = strtotime('+1 day', $t0);
			$ee = date(('Y-m-d'), $eNextD) . " 08:00:00";
			$t = strtotime($ee);
		} else if($pointFormat === 'Y-m'){
			$lastDay = strtotime(date("Y-m-t", $t0));
			$eNextM = strtotime('+1 day', $lastDay);
			$ee = date('Y-m', $eNextM) . "-01 08:00:00";
			$t = strtotime($ee);
		}

		if($pointFormat === 'H') {
				$point = date($pointFormat, $t0) . '～' . date($pointFormat, $t) . '点';
			} else {
				$point = date($pointFormat, $t0);
		}
		$ret[] = array(
				'stime' => date($format, $t0),
				'etime' => date($format, $t),
				'point' => $point,
		);

		// $t = $s;
		while($t < $e) {
			if($pointFormat === 'H') {
				$point = date($pointFormat, $t) . '～' . date($pointFormat, $t + $slice) . '点';
			} else {
				$point = date($pointFormat, $t);
			}

			if($pointFormat === 'Y-m') {
				$eNextM = strtotime('first day of next month', $t);
				$ee = date('Y-m-d', $eNextM) . " 08:00:00";	//next month firstday
				$etmp = strtotime($ee);	//next month firstday
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
