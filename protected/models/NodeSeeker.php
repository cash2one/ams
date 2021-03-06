<?php
Yii::import('application.models.AR.NodeAR');
Yii::import('application.models.AR.NodeTraceAR');
Yii::import('application.models.AR.CarAR');
Yii::import('application.models.AR.OrderConfigAR');
Yii::import('application.models.User');

class NodeSeeker
{
	public function __construct(){
	}

	public static $SECTION_NODEID_MAP = array(
					"PBS" => 1,
					"T0" =>2,
					"T0_2" =>201,
					"T1" =>3,
					"T2" =>4,
					"T3" =>5,
					"C1" =>6,
					"C2" =>7,
					"F1" =>8,
					"F2" =>9,
					"VQ1" =>10,
					"VQ1_2" =>209,
					"CHECK_LINE" =>13,
					"ROAD_TEST_FINISH" =>15,
					"VQ2" =>16,
					"VQ3" =>17,
					"CHECK_IN" =>18,
					"CHECK_OUT" =>19,
					'OutStandby'=>96,
					'WAREHOUSE_RETURN'=>97,
					'DETECT_SHOP_LEAVE'=>98,
					'DETECT_SHOP_RETURN'=>99,
				  );

	public function queryTrace($stime, $etime, $series, $node, $curPage, $perPage) {
		//list($stime, $etime) = $this->reviseSETime($stime, $etime);
		if(empty($node)){
			throw new Exception("车辆明细查询必须选择节点", 1);
		} else {
			$nodeId = self::$SECTION_NODEID_MAP[$node];
		}

		$traceTable = 'node_trace';
		// if($node === 'VQ3_WAREHOUSE_RETURN'){
		// 	$traceTable = 'warehouse_return_trace';
		// }

        $sql = "SELECT id,display_name FROM user";
        $users = Yii::app()->db->createCommand($sql)->queryAll();
        $userInfos = array();
        foreach($users as $user) {
            $userInfos[$user['id']] = $user['display_name'];
        }

        // $sql = "SELECT id,name FROM car_config";
        // $configs = Yii::app()->db->createCommand($sql)->queryAll();
        // $configInfos = array();
        // foreach($configs as $config) {
        //     $configInfos[$config['id']] = $config['name'];
        // }
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

        $sql = "SELECT id,display_name FROM node";
        $nodes = Yii::app()->db->createCommand($sql)->queryAll();
        $nodeInfos = array();
        foreach($nodes as $node) {
            $nodeInfos[$node['id']] = $node['display_name'];
        }

        $sql = "SELECT series, config_id, color, material_code, description FROM config_sap_map";
        $materials = Yii::app()->db->createCommand($sql)->queryAll();
        $materialCodes = array();
        $materialDescriptions = array();
        foreach($materials as $material) {
        	$key = $material['series'] . $material['config_id'] . $material['color'];
        	$materialCodes[$key] = $material['material_code'];
        	$materialDescriptions[$key] = $material['description'];
        }

		$conditions = array("node_id=$nodeId");

		if(!empty($stime)) {
            $conditions[] = "pass_time >= '$stime'";
        }
        if(!empty($etime)) {
            $conditions[] = "pass_time <= '$etime'";
        }

        if(!empty($series)){
	        $arraySeries = Series::parseSeries($series);
	        $cTmp = array();
	        foreach($arraySeries as $series){
	        	$cTmp[] = "car_series='$series'";
	        }
	        $conditions[] = "(" . join(' OR ', $cTmp) . ")";
        };

        $condition = join(' AND ', $conditions);

        $limit = "";
        if(!empty($perPage)) {
            $offset = ($curPage - 1) * $perPage;
            $limit = "LIMIT $offset, $perPage";
        }

        $returnParam = "'' as return_to, ";
        $joinTraceTable = "";
        if($nodeId == 97){
        	$returnParam = " r.return_to,";
        	$joinTraceTable ="LEFT JOIN warehouse_return_trace AS r ON r.trace_id = n.id";
        }

        $dataSql = "SELECT $returnParam n.node_id, n.car_id, n.user_id, n.pass_time,n.remark as node_remark, c.vin, c.series, c.serial_number, c.type, c.color,c.plan_id, c.config_id, c.remark, c.status, c.cold_resistant, c.special_order, c.distributor_name, c.order_id, c.engine_code, c.yielded
        		FROM $traceTable AS n
        		LEFT JOIN car AS c
        		ON n.car_id=c.id
        		$joinTraceTable
        		WHERE $condition
        		ORDER BY n.pass_time DESC
        		$limit";

        $datas = Yii::app()->db->createCommand($dataSql)->queryAll();
        $ret = array();
        foreach($datas as &$data){
        	$materialKey = $data['series'] . $data['config_id'] . $data['color'];
        	$data['material_code'] = empty($materialCodes[$materialKey]) ? '' : $materialCodes[$materialKey];
        	$data['material_description'] = empty($materialDescriptions[$materialKey]) ? '' : $materialDescriptions[$materialKey];
        	if($data['series'] == '6B') $data['series'] = '思锐';

        	if($data['cold_resistant'] == 1){
        		$data['cold_resistant'] = '耐寒';
        	} else {
        		$data['cold_resistant'] = '非耐寒';
        	}

        	if(!empty($data['user_id'])){
        		$data['user_name'] = $userInfos[$data['user_id']];
        	} else {
        		$data['user_name'] = '-';
        	}
        	if(!empty($data['driver_id'])) {
				$data['driver_name'] = $userInfos[$data['driver_id']];
			} else {
				$data['driver_name'] = $data['user_name'];
			}

        	if(!empty($data['type'])){
	        	$data['car_model'] = $modelInfo[$data['type']];
        	} else {
        		$data['car_model'] ='';
        	}
        	if(!empty($data['config_id'])){
        		$data['config_name'] = $configInfos[$data['config_id']]['configName'];
	        	$data['order_config_name'] = $configInfos[$data['config_id']]['orderConfigName'];
	        	$data['type_config'] = $data['car_model'] . '/' . $data['order_config_name'];
        	}else {
        		$data['config_name'] = '';
	        	$data['order_config_name'] = '';
	        	$data['type_config'] = $data['type'];

        	}

        	$data['order_number'] = '-';
        	if(!empty($data['order_id'])){
        		$sql = "SELECT order_number FROM `order` WHERE id = '{$data['order_id']}'";
        		$order_number = Yii::app()->db->createCommand($sql)->queryScalar();
        		$data['order_number']= $order_number;
        	}
            $data['plan_number'] = '-';
            if(!empty($data['plan_id'])) {
                $sql = "SELECT plan_number FROM plan_assembly WHERE id='{$data['plan_id']}'";
                $plan_number = Yii::app()->db->createCommand($sql)->queryScalar();
                $data['plan_number'] = $plan_number;
            }

        	$data['node_name'] = $nodeInfos[$data['node_id']];

        	$data['pass_time'] = substr($data['pass_time'],0,16);

			$key = join('_', $data);
			$ret[$key] = $data;
        }

        $countSql = "SELECT COUNT(*) FROM $traceTable WHERE $condition";
        $total = Yii::app()->db->createCommand($countSql)->queryScalar();

        $ret = array_values($ret);
		return array($total, $ret);
	}

    public function queryPbsQueue($stime, $etime, $series, $curPage, $perPage) {
        $sql = "SELECT id,display_name FROM user";
        $users = Yii::app()->db->createCommand($sql)->queryAll();
        $userInfos = array();
        foreach($users as $user) {
            $userInfos[$user['id']] = $user['display_name'];
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

        $sql = "SELECT series, config_id, color, material_code, description FROM config_sap_map";
        $materials = Yii::app()->db->createCommand($sql)->queryAll();
        $materialCodes = array();
        $materialDescriptions = array();
        foreach($materials as $material) {
            $key = $material['series'] . $material['config_id'] . $material['color'];
            $materialCodes[$key] = $material['material_code'];
            $materialDescriptions[$key] = $material['description'];
        }

        $conditions = array();

        if(!empty($stime)) {
            $conditions[] = "queue_time >= '$stime'";
        }
        if(!empty($etime)) {
            $conditions[] = "queue_time <= '$etime'";
        }

        if(!empty($series)){
            $arraySeries = Series::parseSeries($series);
            $cTmp = array();
            foreach($arraySeries as $series){
                $cTmp[] = "car_series='$series'";
            }
            $conditions[] = "(" . join(' OR ', $cTmp) . ")";
        };
        $condition = join(' AND ', $conditions);

        $limit = "";
        if(!empty($perPage)) {
            $offset = ($curPage - 1) * $perPage;
            $limit = "LIMIT $offset, $perPage";
        }

        $dataSql = "SELECT car_id, 2 as user_id, queue_time as pass_time, remark as node_remark, vin, series, serial_number, type, color, plan_id, config_id, remark, status, cold_resistant, special_order, distributor_name, order_id, engine_code, yielded
            FROM view_pbs_queue
            WHERE $condition
            ORDER BY queue_time DESC
            $limit";
        $datas = Yii::app()->db->createCommand($dataSql)->queryAll();

        $ret = array();
        foreach($datas as &$data){
            $materialKey = $data['series'] . $data['config_id'] . $data['color'];
            $data['material_code'] = empty($materialCodes[$materialKey]) ? '' : $materialCodes[$materialKey];
            $data['material_description'] = empty($materialDescriptions[$materialKey]) ? '' : $materialDescriptions[$materialKey];
            if($data['series'] == '6B') $data['series'] = '思锐';

            if($data['cold_resistant'] == 1){
                $data['cold_resistant'] = '耐寒';
            } else {
                $data['cold_resistant'] = '非耐寒';
            }

            if(!empty($data['user_id'])){
                $data['user_name'] = $userInfos[$data['user_id']];
            } else {
                $data['user_name'] = '-';
            }
            if(!empty($data['driver_id'])) {
                $data['driver_name'] = $userInfos[$data['driver_id']];
            } else {
                $data['driver_name'] = $data['user_name'];
            }

            if(!empty($data['type'])){
                $data['car_model'] = $modelInfo[$data['type']];
            } else {
                $data['car_model'] ='';
            }
            if(!empty($data['config_id'])){
                $data['config_name'] = $configInfos[$data['config_id']]['configName'];
                $data['order_config_name'] = $configInfos[$data['config_id']]['orderConfigName'];
                $data['type_config'] = $data['car_model'] . '/' . $data['order_config_name'];
            }else {
                $data['config_name'] = '';
                $data['order_config_name'] = '';
                $data['type_config'] = $data['type'];

            }

            $data['order_number'] = '-';
            if(!empty($data['order_id'])){
                $sql = "SELECT order_number FROM `order` WHERE id = '{$data['order_id']}'";
                $order_number = Yii::app()->db->createCommand($sql)->queryScalar();
                $data['order_number']= $order_number;
            }
            $data['plan_number'] = '-';
            if(!empty($data['plan_id'])) {
                $sql = "SELECT plan_number FROM plan_assembly WHERE id='{$data['plan_id']}'";
                $plan_number = Yii::app()->db->createCommand($sql)->queryScalar();
                $data['plan_number'] = $plan_number;
            }

            $data['node_name'] = 'PBS排序（虚拟）';

            $data['pass_time'] = substr($data['pass_time'],0,16);
            $data['return_to'] = "";
            $key = join('_', $data);
            $ret[$key] = $data;
        }

        $countSql = "SELECT COUNT(*) FROM view_pbs_queue WHERE $condition";
        $total = Yii::app()->db->createCommand($countSql)->queryScalar();

        $ret = array_values($ret);
        return array($total, $ret);
    }

	private function reviseSETime($stime,$etime) {
		//cancel the revise function
		return array($stime, $etime);

		$s = strtotime($stime);
		$e = strtotime($etime);

		$sd = date('Ymd', $s);
		$ed = date('Ymd', $e);

		$sm = date('m', $s);
		$em = date('m', $e);

		$lastHour = ($e - $s) / 3600;
		$lastDay = (strtotime($ed) - strtotime($sd)) / 86400;//days

		$ret = array();
		if($lastHour <= 24) {//hour
			$format = 'Y-m-d H';
			$stime = date($format, $s) . ":00:00";
			$eNextH = strtotime('+1 hour', $e);
			$etime = date($format, $eNextH) . ":00:00";
		} elseif($lastDay <= 31) {//day
			$format = 'Y-m-d';
			$stime = date($format, $s) . " 08:00:00";								//added by wujun
			$eNextD = strtotime('+1 day', $e);		//next day						//added by wujun
			$etime = date($format, $eNextD) . " 07:59:59";	//befor next workday	//added by wujun
		} else {//month
			$format = 'Y-m';
			$stime = date($format, $s) . "-01 08:00:00";	//firstday				//added by wujun
			$lastDay = strtotime(date("Y-m-t", $s));
			$eNextM = strtotime('+1 day', $lastDay);		//next month
			// $eNextM = strtotime('+1 month', $e);			//next month			//added by wujun
			$etime = date('Y-m', $eNextM) . "-01 07:59:59";	//next month firstday	//added by wujun
		}


		return array($stime, $etime);
	}

	// private function parseSeries($series) {
	// 	if(empty($series) || $series === 'all') {
 //            $series = array('F0', 'M6', '6B');
 //        } else {
 //            $series = explode(',', $series);
 //        }
	// 	return $series;
	// }
}
