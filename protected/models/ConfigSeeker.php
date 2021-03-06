<?php
Yii::import('application.models.Node');
Yii::import('application.models.User');
Yii::import('application.models.AR.ComponentAR');
Yii::import('application.models.AR.CarConfigListAR');
Yii::import('application.models.AR.ProviderAR');
Yii::import('application.models.AR.NodeAR');

class ConfigSeeker
{
	public function __construct(){
	}

	public function getName($configId) {
		$sql = "SELECT name FROM car_config WHERE id=$configId";
        $configName = Yii::app()->db->createCommand($sql)->queryScalar();
		return $configName;
	}

	public function getDetail($nodeName) {
		$node = Node::createByName($nodeName);
		if(!$node->exist()) {
			throw new Exception('node ' . $nodeName . ' is not exit');
		}
		$config = Config::create();

		$sql = "SELECT name FROM car_config WHERE id=$configId";
	}

	//added by wujun
	public function search($series, $type, $configName, $column = '') {
		$condition = "car_series=?";
		$values = array($series);
		if(!empty($type)) {
			$condition .= " AND car_type=?";
			$values[] = $type;
		}
		if(!empty($configName)) {
			$condition .= " AND name=?";
			$values[] = $configName;
		}
				
		$configs = CarConfigAR::model()->findAll($condition . ' ORDER BY id ASC', $values);
		$datas = array();
		foreach($configs as $config){
			if($column === 'car_type') {
				$data = array(
						'id' => $config->{$column},
						'name' => $config->{$column},
					);
			} elseif($column === 'name') {
				$data = array(
                        'id' => $config->id,
                        'name' => $config->{$column},
                    );
			} else {
				$data = $config->getAttributes();
				$user = User::model()->findByPk($config->user_id);
				$user_name = empty($user) ? '' : $user->display_name;
				$data['user_name']= $user_name;
			}
			if(!in_array($data, $datas)) {
				$datas[]=$data;	
			}
		}
		
		return $datas;
	}
	
	//added by wujun
	public function getNameList ($carSeries, $carType) {
		$condition = "is_disabled=0 AND car_series=?";
		$values = array($carSeries);
		if(!empty($carType)) {
			$condition .= " AND car_type=?";
			$values[] = $carType;
		}
		$configs = CarConfigAR::model()->findAll($condition . ' ORDER BY id ASC', $values);
		
		$datas = array();
		foreach($configs as $config) {
			$data['config_id'] = $config->id;
			$data['config_name']= $config->name;
			$datas[]=$data;
		}
		return $datas;
	}
	
	//added by wujun
	public function getList ($configId, $nodeId, $curPage, $perPage) {
		
		$condition = "config_id='$configId'";
		if(!empty($nodeId)){
			$condition .= " AND node_id='$nodeId'";
		}
		$limit = $perPage;
		$offset = ($curPage - 1) * $perPage;
		
		$sql = "SELECT id, config_id, istrace, provider_id, component_id,replacement_id, node_id, remark, modify_time, user_id 
				  FROM car_config_list 
				 WHERE $condition 
				 ORDER BY id ASC 
				 LIMIT $offset,$limit";
		$list = Yii::app()->db->createCommand($sql)->queryAll();
		$countSql = "SELECT count(*) 
					   FROM car_config_list 
					  WHERE $condition";
		$total = Yii::app()->db->createCommand($countSql)->queryScalar();
		$detail = array();			
		foreach($list as &$detail) {
			$detail['user_name'] = User::model()->findByPk($detail['user_id'])->display_name;
			
			$component = ComponentAR::model()->findByPk($detail['component_id']);
			if(!empty($component)){
				$detail['component_name'] = $component->display_name;
				$detail['component_code'] = $component->code;
			}			
			
			$replacement = ComponentAR::model()->findByPk($detail['replacement_id']);
			if(!empty($replacement)){
				$detail['replacement_name'] = $replacement->display_name;
				$detail['replacement_code'] = $replacement->code;
			} else {
				$detail['replacement_name'] = "";
				$detail['replacement_code'] = "";
			}

			$provider = ProviderAR::model()->findByPk($detail['provider_id']);
			if(!empty($provider)){
				$detail['provider_name'] = $provider->display_name;
				$detail['provider_code'] = $provider->code;	
			} else {
				$detail["provider_name"] = '';
				$detail["provider_code"] = '';
			}
			
			$node = NodeAR::model()->findByPk($detail['node_id']);
			if(!empty($node)){
				$detail['node_name'] = $node->display_name;
			} else {
				$detali['node_name'] = '';	
			}
		}
		return array($total, $list);			
		
	}

	//added by wujun
	public function getListDetail($configId) {
		if(!empty($configId)) {
		$condition = "config_id='$configId'";
			$sql = "SELECT istrace, provider_id, component_id,replacement_id, node_id, remark
					  FROM car_config_list
					 WHERE $condition";
			$details = Yii::app()->db->createCommand($sql)->queryAll();
		} else {
			throw new Exception ('config id cannot be null');
		}
		return $details;
	}
	
	public function getTraceList($configId) {
		$sql = "SELECT istrace, provider_id, component_id, replacement_id, node_id
				FROM car_config_list
				WHERE config_id=$configId AND istrace>0";
		$list = Yii::app()->db->createCommand($sql)->queryAll();
		return $list;
	}
}
