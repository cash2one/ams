<?php
Yii::import('application.models.Car');
Yii::import('application.models.Fault');
Yii::import('application.models.Component');
Yii::import('application.models.AR.ComponentAR');
Yii::import('application.models.ComponentTrace');
Yii::import('application.models.Export');

class ComponentController extends BmsBaseController
{
	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
		);
	}


	public function actionQuery() {
		$vin = $this->validateStringVal('vin', '');
		$barcode = $this->validateStringVal('barcode', '');
		$node = $this->validateStringVal('node', '');

		$provider = $this->validateStringVal('provider', '');
        $component = $this->validateStringVal('component', '');
        $series = $this->validateStringVal('series', '');
        $stime = $this->validateStringVal('stime', '');
        $etime = $this->validateStringVal('etime', '');

		$perPage = $this->validateIntVal('perPage', 20);
		$curPage = $this->validateIntVal('curPage', 1);
		try{
            $seeker = ComponentTrace::createSeeker();
			list($total, $data) = $seeker->query($vin, $barcode, $node, $stime, $etime, $provider, $component, $series, $curPage, $perPage);
			$ret = array(
				'pager' => array('curPage' => $curPage, 'perPage' => $perPage, 'total' => $total),
				'data' => $data,
			);
            $this->renderJsonBms(true, 'OK', $ret);
        } catch(Exception $e) {
            $this->renderJsonBms(false , $e->getMessage());
        }

	}

	public function actionExport() {
        $vin = $this->validateStringVal('vin', '');
        $barcode = $this->validateStringVal('barcode', '');
        $node = $this->validateStringVal('node', '');

        $provider = $this->validateStringVal('provider', '');
        $component = $this->validateStringVal('component', '');
        $series = $this->validateStringVal('series', '');
        $stime = $this->validateStringVal('stime', '');
        $etime = $this->validateStringVal('etime', '');
        try{
            $seeker = ComponentTrace::createSeeker();
			list($total, $datas) = $seeker->query($vin, $barcode, $node, $stime, $etime, $provider, $component, $series, 0, 0);

			$content = "车系,车型,VIN号,零部件名称,零部件条码,供应商,节点,录入人员,录入时间,备注\n";
			foreach($datas as &$data) {
				$content .= "{$data['car_series']},";
				$data['car_type'] = str_replace(",", "，",$data['car_type']);
				$content .= "{$data['car_type']},";
				$content .= "{$data['vin']},";
				$content .= "{$data['component_name']},";
				$data['bar_code'] = "=\"" . $data['bar_code'] . "\"";
				$content .= "{$data['bar_code']},";
				$content .= "{$data['provider']},";
				$content .= "{$data['node_name']},";
				$content .= "{$data['user_name']},";
				$content .= "{$data['create_time']},";
				$content .= "{$data['modify_time']}\n";
			}
			$export = new Export('零部件_'.date('YmdHi'), $content);
			$export->toCSV();
		} catch(Exception $e) {
			echo $e->getMessage();
        }

	}

	//零部件清单
	public function actionShowList() {
        $series = $this->validateStringVal('series', '');
		
		$category = $this->validateIntVal('category', 0);
		$component = $this->validateStringVal('component', '');
		$code = $this->validateStringVal('code', '');
		$isfault = $this->validateIntVal('isfault', 0);

        $perPage = $this->validateIntVal('perPage', 20);
        $curPage = $this->validateIntVal('curPage', 1);
        try{
            $seeker = Component::createSeeker();
            list($total, $data) = $seeker->query($series, $category, $component, $code, $isfault, $curPage, $perPage);
            $ret = array(
                'pager' => array('curPage' => $curPage, 'perPage' => $perPage, 'total' => $total),
				'list' => $data,
            );
            $this->renderJsonBms(true, 'OK', $ret);
        } catch(Exception $e) {
            $this->renderJsonBms(false , $e->getMessage());
        }
	}

	public function actionSave() {
		try{
			$opUserId = Yii::app()->user->id;
            $user = User::model()->findByPk($opUserId);
            // if(!$user->admin) {
            //     BmsLogger::warning($opUserId . " try to save component");
            //     throw new Exception ('不要做坏事，有记录的！！');
            // }
            if(!Yii::app()->permitManager->checkPrivilage('BASE_DATA_EDIT')){
                BmsLogger::warning($opUserId . " try to save component");
                throw new Exception ('您无故障库维护权限');
            }

			$id = $this->validateIntVal('id', 0);
			$series = $this->validateStringVal('series', '');
			$category = $this->validateIntVal('category', 7);
			$code = $this->validateStringVal('code', '');
			$sapCode = $this->validateStringVal('sapCode', '');
			$name = $this->validateStringVal('name', '');
			$displayName = $this->validateStringVal('displayName', '');
			$isfault = $this->validateIntVal('isfault', 0);
			$simpleCode = $this->validateStringVal('simpleCode', '');
			$unitPrice = $this->validateStringVal('unitPrice', '');
			$remark = $this->validateStringVal('remark', '');
			if(empty($name) || empty($code)) {
				throw new Exception('零部件编号/名称不能为空');
			}
			$exist = ComponentAR::model()->find('code=? && id!=? && car_series=?', array($code, $id, $series));
			if(!empty($code) && !empty($exist)) {
				throw new Exception('零部件编码已存在');
			}
			$sapExist = ComponentAR::model()->find('sap_code=? && id!=?', array($sapCode, $id));
			if(!empty($sapCode) && !empty($sapExist)) {
				throw new Exception('SAP编码已存在');
			}
			$component = ComponentAR::model()->findByPk($id);
            if(empty($component)) {
				$component = new ComponentAR();
				$component->create_time = date('YmdHis');
            }
			$component->car_series = $series;
			$component->code = $code;
			$component->sap_code = $sapCode;
			$component->simple_code = $simpleCode;
			$component->name = $name;
			$component->display_name = $displayName;
			$component->is_fault = $isfault;
			$component->category_id = $category;
			$component->unit_price = $unitPrice;
			$component->remark = $remark;
			$component->modify_time = date('YmdHis');
			$component->user_id = Yii::app()->user->id;
			$component->save();
			
				
            $this->renderJsonBms(true, 'OK', $component->id);
        } catch(Exception $e) {
            $this->renderJsonBms(false , $e->getMessage());
        }
	}

	public function actionSaveProvider() {
		$componentId = $this->validateIntVal("componentId", 0);
		$providerIds = array();
		for($i=1;$i<=3;$i++){
			$providerIds[$i] = $this->validateIntVal("providerId".$i, 0);
		}
		try {
			$opUserId = Yii::app()->user->id;
            $user = User::model()->findByPk($opUserId);
            if(!Yii::app()->permitManager->checkPrivilage('BASE_DATA_EDIT')){
                BmsLogger::warning($opUserId . " try to save component");
                throw new Exception ('您无故障库维护权限');
            }
            $component = ComponentAR::model()->findByPk($componentId);
            if(empty($component)) {
            	throw new Exception("零部件不存在");
            } else {
            	for($i=1;$i<=3;$i++){
            		$provider = "provider_$i";
	            	$component->$provider = $providerIds[$i];
            	}
            	$component->save();
            }
            $data = $component;
			$this->renderJsonBms(true, 'OK', $data);
		} catch(Exception $e) {
			$this->renderJsonBms(false, $e->getMessage());
		}
	}

	public function actionRemove() {
		try{
			$opUserId = Yii::app()->user->id;
            $user = User::model()->findByPk($opUserId);
			$id = $this->validateIntVal('id', 0);
            // if(!$user->admin) {
            //     BmsLogger::warning($opUserId . " try to remove component @ " .$id);
            //     throw new Exception ('不要做坏事，有记录的！！');
            // }
            if(!Yii::app()->permitManager->checkPrivilage('BASE_DATA_EDIT')){
                BmsLogger::warning($opUserId . " try to save component");
                throw new Exception ('您无故障库维护权限');
            }
			BmsLogger::info("remove component @ " .$id);
			$component = ComponentAR::model()->findByPk($id);
			if(!empty($component)) {
				$component->delete();
			}
            $this->renderJsonBms(true, 'OK', '');
        } catch(Exception $e) {
            $this->renderJsonBms(false , $e->getMessage());
        }

	}


	public function actionSearch() {
        $series = $this->validateStringVal('series', '');
        $name = $this->validateStringVal('component', '');
        try{
            if(empty($name)) {
                throw new Exception('component cannot be null');
            }
            $seeker = Component::createSeeker();
            $data = $seeker->getAll($name, $series);

            $this->renderJsonBms(true, 'OK', $data);
        } catch(Exception $e) {
            $this->renderJsonBms(false , $e->getMessage());
        }

	}
	
	public function actionGetCode() {
		$series = $this->validateStringVal('series', '');
		$name =$this->validateStringVal('componentName', '');
		try{
			if(empty($name)){
				throw new Exception('component cannot be null');	
			}
			$seeker = new ComponentSeeker;
			$data = $seeker->getComponentCode($name, $series);
			
			$this->renderJsonBms(true, 'OK', $data);
		}catch(Exception $e) {
			$this->renderJsonBms(false, $e->getMessage());	
		}	
	}

	public function actionGetInfo() {
		$componentId = $this->validateIntVal("componentId", 0);
		try{
			$seeker = new ComponentSeeker;
			$data = $seeker->getComponentInfo($componentId);			
			$this->renderJsonBms(true, 'OK', $data);
		}catch(Exception $e) {
			$this->renderJsonBms(false, $e->getMessage());	
		}	
	}
}
