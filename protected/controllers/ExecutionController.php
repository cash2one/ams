<?php
Yii::import('application.models.Car');
Yii::import('application.models.Fault');
Yii::import('application.models.AR.PlanAR');
Yii::import('application.models.AR.CarAR');
class ExecutionController extends BmsBaseController
{
	public static $NODE_MAP = array(
		'T11','T21','T32','C10','C21','F10',
	);
	public static $MERGED_VIEW = "T11-F10";
	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
		);
	}

	/**
     */
    public function actionIndex()
    {
        $this->render('assembly/dataInput/NodeSelect',array(''));
    }

    /**
	 */
	public function actionHome()
	{
		$this->render('executionHome',array(''));
	}

    /**   query  actions
     */
    public function actionQuery()
    {
        $queryPanel = $this->validateStringVal('type','CarQuery');
        $this->render('assembly/query/' . $queryPanel,array(''));
    }
	
	public function actionChild() {
		$nodeName = $this->validateStringVal('node','NodeSelect');
		$view = $this->validateStringVal('view','NodeSelect');
		if(in_array($nodeName, self::$NODE_MAP)) {
			$view = self::$MERGED_VIEW;
		}
		$node = Node::createByName($nodeName); 
		$this->render('assembly/dataInput/' . $view ,array('node'=>$nodeName, 'nodeDisplayName' => $node->exist() ? $node->display_name : $nodeName));	
	}

	//进入彩车身库
	public function actionEnterPbs() {
		try{
			$vin = $this->validateStringVal('vin', '');
			$car = Car::create($vin);
        	$car->enterNode('PBS', 0 , true);
			$this->renderJsonBms(true, $vin . '成功录入彩车身库', $vin);
		} catch(Exception $e) {
			$this->renderJsonBms(false, $e->getMessage(), null);
		}
	}

	//打印上线
	public function actionEnterT0() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
			$planId = $this->validateIntVal('planId', 0);
			$date = $this->validateStringVal('date', date('Y-m-d'));
			if(empty($planId)) {
				throw new Exception('the car must fit a plan!!');
			}
            $car = Car::create($vin);
            $car->leftNode('PBS');
			$car->enterNode('T0', 0 ,true);
			$car->generateSerialNumber();
			$car->addToPlan($date, $planId);
            $serial_number = $car->car->serial_number;      //added by wujun
			$transaction->commit();
            $this->renderJsonBms(true, $vin . '成功录入T0', array($vin, $serial_number));   //modifed by wujun
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }


	//T11,T21,T32,C10,C21,F10
	public function actionEnterNode() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
			$nodeName = $this->validateStringVal('currentNode', 'T11');
			$componentCode = $this->validateStringVal('componentCode', '{}');

			$enterNode = Node::createByName($nodeName);
			$leftNode = $enterNode->getParentNode();

            $car = Car::create($vin);
            $car->leftNode($leftNode->name);
			$car->enterNode($enterNode->name);
			
			//save component trace
			$car->addTraceComponents($enterNode, $componentCode);
			$transaction->commit();
            $this->renderJsonBms(true, $vin . '成功录入' . $nodeName , $vin);
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }

	public function actionEnterF20() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
            $car = Car::create($vin);
            $car->leftNode('F10');
            $car->enterNode('F20');


			//print check trace 
			$data = $car->generateCheckTraceData();
			$transaction->commit();
            $this->renderJsonBms(true, 'OK', $data);
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }


	public function actionEnterVQ1() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
		    $faults = $this->validateStringVal('fault', '[]');

            $car = Car::create($vin);
            $car->leftNode('F20');
			$car->passNode('LEFT_WORK_SHOP');
            $car->enterNode('VQ1');
			$car->finish();

			$fault = Fault::create('VQ1_STATIC_TEST',$vin, $faults);
            $fault->save('在线');
			$transaction->commit();
            $this->renderJsonBms(true, 'OK');

        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }

	public function actionEnterLWS() {
        try{
            $vin = $this->validateStringVal('vin', '');
			$car = Car::create($vin);
			$fault = Fault::createSeeker();
            $exist = $fault->exist($car, '未修复', array('VQ1_STATIC_TEST_'));
            if(!empty($exist)) {
                throw new Exception ($vin .'车辆在VQ1还有未修复的故障');
            }

            $car->leftNode('VQ1');
            $car->enterNode('LEFT_WORK_SHOP', 0);
            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }

	public function actionEnterECS() {
        try{
            $vin = $this->validateStringVal('vin', '');
            $car = Car::create($vin);
            $car->leftNode('LEFT_WORK_SHOP');
            $car->enterNode('ENTER_CHECK_SHOP', 0);
            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }

	public function actionEnterCL() {
        try{
            $vin = $this->validateStringVal('vin', '');
            $car = Car::create($vin);
            $car->leftNode('ENTER_CHECK_SHOP');
            $car->enterNode('CHECK_LINE', 0);
            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
            $this->renderJsonBms(false, $e->getMessage(), null);
        } 
    }

	public function actionEnterRTS() {
        try{
            $vin = $this->validateStringVal('vin', '');
			$driverId = $this->validateIntVal('driverId',0);
			if(empty($driverId)) {
				throw new Exception('请选择司机后再开始路试');
			}
            $car = Car::create($vin);
            $car->leftNode('CHECK_LINE');
			$car->passNode('VQ3');
            $car->enterNode('ROAD_TEST_START', $driverId);

            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
            $this->renderJsonBms(false, $e->getMessage(), null);
        }   
    }   

	public function actionEnterRTF() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
			$faults = $this->validateStringVal('fault', '[]');
			$bagCode = $this->validateStringVal('bag', '');
            $car = Car::create($vin);
            $car->leftNode('ROAD_TEST_START');
			$car->passNode('VQ3');
            $car->enterNode('ROAD_TEST_FINISH');

			$fault = Fault::create('VQ2_ROAD_TEST',$vin, $faults);
            $fault->save('在线');

			$car->addGasBagTraceCode($bagCode);
			$transaction->commit();
            $this->renderJsonBms(true, 'OK', null);
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }   
    }   


	public function actionEnterVQ2Leak() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
			$faults = $this->validateStringVal('fault', '[]');

            $car = Car::create($vin);
            $car->leftNode('ROAD_TEST_FINISH');
			$car->passNode('VQ3');
            $car->enterNode('VQ2');


			$fault = Fault::create('VQ2_LEAK_TEST',$vin, $faults);
            $fault->save('在线');
			$transaction->commit();
            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }


	public function actionEnterVQ3() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
			$faults = $this->validateStringVal('fault', '');
            $car = Car::create($vin);


			$fault = Fault::createSeeker();
            $exist = $fault->exist($car, '未修复', array('VQ2_ROAD_TEST_', 'VQ2_LEAK_TEST_'));
            if(!empty($exist)) {
                throw new Exception ($vin .'车辆在VQ2还有未修复的故障');
            }

			
			//只要进入VQ2，则可以多次进入VQ3
            $car->leftNode('VQ2');
			$car->passNode('CHECK_IN');
            $car->enterNode('VQ3');
		
			$fault = Fault::create('VQ3_FACADE_TEST',$vin, $faults);
            $fault->save('在线');
			$transaction->commit();
            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }


	public function actionEnterCI() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
			$area = $this->validateIntVal('lane',0);
			$car = Car::create($vin);
			
			$fault = Fault::createSeeker();
            $exist = $fault->exist($car, '未修复',array('VQ3_FACADE_TEST_'));
            if(!empty($exist)) {
                throw new Exception ($vin .'车辆在VQ3还有未修复的故障');
            }

            $car->leftNode('VQ3');
			$car->passNode('CHECK_OUT');
            $car->enterNode('CHECK_IN',0);

			$car->moveToArea($area);
			$transaction->commit();
            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }


	public function actionEnterCO() {
		$transaction = Yii::app()->db->beginTransaction();
        try{
            $vin = $this->validateStringVal('vin', '');
            $lane = $this->validateIntVal('lane',0);
            if(empty($lane)) {
                throw new Exception('no lane has selected');
            }

            $car = Car::create($vin);
            $car->leftNode('CHECK_IN');
            $car->enterNode('CHECK_OUT',0);

			$car->moveToLane($lane);
			$transaction->commit();
            $this->renderJsonBms(true, 'OK', $vin);
        } catch(Exception $e) {
			$transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage(), null);
        }
    }

    //added by wujun
    //checkin warehouse
    public function actionWarehouseCheckin() {
        $transaction = Yii::app()->db->beginTransaction();
        try {
            $vin = $this->validateStringVal('vin', '');
            //$date = date('Y-m-d');
            $date = DateUtil::getCurDate();

            $car = Car::create($vin);

            $fault = Fault::createSeeker();
            $exist = $fault->exist($car, '未修复',array('VQ3_FACADE_TEST_'));
            if(!empty($exist)) {
                throw new 
                Exception ($vin .'车辆在VQ3还有未修复的故障');
            }

            $car->leftNode('VQ3');
            $car->passNode('CHECK_OUT');
            $car->enterNode('CHECK_IN');
            //$message = $vin . '未匹配订单';
            //$data = array();
            list($matched, $data) = $car->matchOrder($date);
            if($matched) {
                $message = $vin . '已匹配订单' . $data['orderNumber'] . '请开往WDI区';
            } else {
                $warehouse = new Warehouse;
                $data = $warehouse->checkin($vin);
                $message = $vin . '已成功入库，请开往' . $data['row'];
            }

            $transaction->commit();
            $this->renderJsonBms(true, $message, $data);
        } catch(Exception $e) {
            $transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage());
        }
    }

    //added by wujun
    //checkin warehouse
    public function actionWarehouseCheckout() {
        $transaction = Yii::app()->db->beginTransaction();
        try {
            $vin = $this->validateStringVal('vin', '');
            $date = date('Y-m-d');

            $car = Car::create($vin);
            $car->leftNode('CHECK_IN');
            $car->enterNode('CHECK_OUT');

            $warehouse = new Warehouse;
            $data = $warehouse->checkout($vin);
            $message = $vin . '已成功出库，请开往车道' . $data['lane'];

            $transaction->commit();
            $this->renderJsonBms(true, $message, $data);
        } catch(Exception $e) {
            $transaction->rollback();
            $this->renderJsonBms(false, $e->getMessage());
        }
    }

    public function actionMonitoringIndex() {
		
        $this->render('assembly/monitoring/monitoringIndex');
    }

    public function actionMonitoringSection() {
		$section = $this->validateStringVal('section');
        $this->render('assembly/monitoring/sectionBoard',array('section'=>$section));
    }

    public function actionMonitoringWorkshop() {
        $this->render('assembly/monitoring/workshopBoard');
    }

    public function actionConfigPlan() {
        $this->render('assembly/other/PlanAssembly');
    }
	
	//added by wujun
	public function actionConfigMaintain() {
		$this->render('assembly/other/ConfigMaintain');
	}
	
	//added by wujun
	public function actionConfigList() {
		$this->render('assembly/other/ConfigList');
	}
	
	//added by wujun
	public function actionConfigPaper() {
		$this->render('assembly/other/ConfigPaper');	
	}
	
	//added by wujun
	public function actionPauseEdit() {
		$this->render('assembly/dataInput/PauseEdit');	
	}
	
	//added by wujun
	public function actionOrderMaintain() {
		$this->render('assembly/other/OrderMaintain');	
	}

    //added by wujun
    public function actionOutStandby() {
        $this->render('assembly/dataInput/OutStandby');  
    }

    //added by wujun
    public function actionWarehouseReturn() {
        $this->render('assembly/dataInput/WarehouseReturn');  
    }

    //added by wujun
    public function actionWelcomeShop() {
        $this->render('assembly/monitoring/workshopWelcome');
    }

    //added by wujun
    public function actionWelcomeSection() {
        $this->render('assembly/monitoring/sectionBoardWelcome');
    }

    //added by wujun
    public function actionTest() {
        $name = '';
        $code = '222';
        $sql = "SELECT id AS provider_id, code AS provider_code, name AS provider_name, display_name FROM provider";
        

        $conditionsName = array();      
        if(!empty($name)){
            $conditions = array();
            $conditions[] = $conditionsName[][] = "name LIKE '%$name%'";
            $conditions[] = $conditionsName[][] = "display_name LIKE '%$name%'";
        }

        if(!empty($code)) {
            $conditions = array();
            if(!empty($conditionsName)){
                foreach($conditionsName as $condition){
                    $condition[] = "code LIKE '%$code%'";
                    $conditions[] = join(' AND ', $condition);
                }                        
            } else {
                $conditions[] = "code LIKE '%$code%'";
            }           
        }

        $sqls = array();
        if(!empty($conditions)){
            foreach($conditions as $condition){
                $sqls[] = $sql . ' WHERE ' . $condition;
            }
        } else {
            $sqls[] = $sql;
        }

        $sql = join(' UNION ', $sqls);

        print_r($sql);
        //$seeker = new MonitorSeeker();
        //echo $seeker->getRestTime(date("Y-m-d H:i:s"));
        // $lastDate = DateUtil::getLastDate();
        // print_r($lastDate);
        // $plans = PlanAR::model()->findAll("plan_date=? ORDER BY priority ASC", array($lastDate));
        // $count = PlanAR::model()->count("plan_date=? ORDER BY priority ASC", array($lastDate));
        // $data = array();
        // foreach($plans as $plan){
        //     $data[] = $plan->getAttributes();
        // }
        // print_r($data);
        // echo '<br>';
        // print_r($count);

        // echo '<br>';
        // $curYear = date('Y');
        // $year = CarYear::getYearCode($curYear);
        // echo $year;

        // $vin = "4C0127057";
        // $car = CarAR::model()->find("vin like ?", array("%$vin"));
        //    echo $car->vin;
    }
}
