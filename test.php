<?php
require 'jsonapischema.php';
$JsonApiSchema= new JsonApiSchema();
		$JsonApiSchema->parse(array(
			JsonApiSchema::CODES =>array(
				'9404'=>'用户 ID 不存在'
			),
			JsonApiSchema::HEADER=>array(
				'Auth'=>array(
					'accessKey'=>'char(5),鉴权 KEY',
					'accessId'=>'char(32),鉴权 ID'
				)
			),
			'getUser'=>array(
				JsonApiSchema::DESC =>'通过用户 ID 获得用户详细信息',
				JsonApiSchema::ENDPOINT=>'http://abc.com/getuser',
				JsonApiSchema::REQUEST =>array(
					'UserId'=>'int(10),用户 ID'
				),
				JsonApiSchema::RESPONSE =>array(
					'UserId'=>'int(10),用户 ID',
					'UserName'=>'char(32),用户名',
					'Sex'=>'enum(male|female),optional',
					'Height'=>'float(3.2),身高,@170.2',
					'Contact'=>array(
						'Phone'=>'char(16), optional, 电话号码, @0571-5689656',
						'QQ'=>'int(11), optional, QQ 号码'
					),
					'Addr'=>array(
						array('id'=>'int','addr'=>'char(60)'),
					)
				)
			),
			'setUser'=>array(
				JsonApiSchema::DESC=>'根据传入资料更新用户资料',
				JsonApiSchema::REQUEST=>array(
					'UserId'=>'int(10), 用户 ID',
					'UserName'=>'char, 用户名',
					'Height'=>'float(3.2),身高,@170.2',
					'Contact'=>array(
						'Phone'=>'char(16), optional, 电话号码',
						'QQ'=>'int(11), optional, QQ 号码'
					)
				),
				JsonApiSchema::RESPONSE=>array(
					'UserId'=>'int'
				)
			)
		));
		$JsonApiSchema->genDoc();
		$r=$JsonApiSchema->validate('setUser',array(
			'Auth'=>array(
				'accessKey'=>'abc',
				'accessId'=>'def',
			),
			'UserId'=>111,
			'UserName'=>'abc',
			'Height'=>170.3
		));
		if ($r!==true){
			var_dump($r,1,11);
		}
