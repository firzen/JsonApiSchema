<?php
/**
 * JsonApiSchema
 *
 * @link https://github.com/firzen/JsonApiSchema
 * @copyright Copyright (c) 2018 fizen. {@link https://github.com/firzen}
 * @license MIT
 * @version $Id: q.php 1 2018-05-30 15:40:15Z firzen $
 */

class JsonApiSchema{
	static $default_endpoint='http://endpoint';
	private $apiDefineArray=array();
	/**
	 * 分析 API 配置
	 */
	function parse($apiDefineArray){
		$_apiDefineArray=array();
		foreach ($apiDefineArray as $key => $api){
			if (in_array($key,array(self::HEADER,self::CODES))){
				$_apiDefineArray[$key]=$api;
			}else {
				$_apiDefineArray[$key][self::DESC]=$api[self::DESC];
				$request=$api[self::REQUEST];
				if (isset($apiDefineArray[self::HEADER])){
					$request=array_merge($apiDefineArray[self::HEADER],$request);
				}
				$_apiDefineArray[$key][self::REQUEST]=self::loopParse($request);
				$_apiDefineArray[$key][self::RESPONSE]=self::loopParse($api[self::RESPONSE]);
				if (empty($api[self::ENDPOINT])){
					$api[self::ENDPOINT]=self::$default_endpoint;
				}
				$_apiDefineArray[$key][self::ENDPOINT]=$api[self::ENDPOINT];
			}
		}
		$_apiDefineArray[self::CODES]=self::$defaultCodes+$_apiDefineArray[self::CODES];
		$this->apiDefineArray=$_apiDefineArray;
	}
	/**
	 * 递归分析节点
	 * @param array $orgArray
	 * @param array $topLevel
	 * @return array
	 */
	static function loopParse($orgArray,&$topLevel=null){
		$_newArray=array();
		foreach ($orgArray as $k => $v){
			if (is_array($v)){
				if (isset($v[0])){
					$_newArray[$k]=array('type'=>'ARRAY');
					$_newArray[$k]['children']=self::loopParse($v[0],$_newArray[$k]);
				}else {
					$_newArray[$k]=array('type'=>'NODE');
					$_newArray[$k]['children']=self::loopParse($v,$_newArray[$k]);
				}
			}else {
				$vs=explode(',',$v);
				$_newArray[$k]=array(
					'required'=>true,
					'type'=>'CHAR',
					'length'=>32,
				);
				foreach ($vs as $setting){
					$setting=trim($setting);
					$typeSetting=array();
					if (preg_match('/^(\w+)\((.+?)\)$/',$setting,$typeSetting)){
						$_newArray[$k]['type']=strtoupper($typeSetting[1]);
						$_newArray[$k]['length']=$typeSetting[2];
					}elseif (strtolower($setting)=='optional'){
						$_newArray[$k]['required']=false;
					}elseif (substr($setting,0,1)=='@'){
						$_newArray[$k]['sample']=substr($setting,1);
					}else {
						if (method_exists('JsonApiSchema','_validate_'.strtoupper($setting))){
							$_newArray[$k]['type']=strtoupper($setting);
						}else {
							@$_newArray[$k]['comment'][]=$setting;
						}
					}
				}
				@$_newArray[$k]['comment']=implode(',',$_newArray[$k]['comment']);
			}
		}
		if (!is_null($topLevel)){
			$required=false;
			foreach ($_newArray as $k => $v){
				if ($v['required']==true){
					$required=true;
				}
			}
			$topLevel['required']=$required;
		}
		return $_newArray;
	}
	/**
	 * 验证 API 方法的数据结构和类型
	 * @param string $verb
	 * @param array $jsonArray
	 */
	function validate($verb,$jsonArray){
		if (!isset($this->apiDefineArray[$verb])){
			return self::error(404,$verb);
		}
		try {
			foreach ($this->apiDefineArray[$verb][self::REQUEST] as $field => $schema){
				self::loopValidate($jsonArray,$field,$schema);
			}
		}catch (JsonApiSchemaException $ex){
			return self::error(9000,$ex->getMessage());
		}
		return true;
	}
	static function loopValidate($dataArr,$field,$schema){
		if (isset($dataArr[$field])){
			//如果是节点或者数组
			if (in_array($schema['type'],array('NODE','ARRAY'))){
				if ($schema['type']=='ARRAY'){
					// 数组一定概要有第一个元素
					if (!isset($dataArr[$field][0])){
						throw new JsonApiSchemaException($field);
					}
					foreach ($dataArr[$field] as $row){
						foreach ($schema['children'] as $_f => $_sm){
							self::loopValidate($row[$field],$_f,$_sm);
						}
					}
				}else {
					foreach ($schema['children'] as $_f => $_sm){
						self::loopValidate($dataArr[$field],$_f,$_sm);
					}
				}
			}else {
				$val=trim($dataArr[$field]);
				if ($schema['required'] && empty($val)){
					throw new JsonApiSchemaException($field);
				}
				if (method_exists('JsonApiSchema','_validate_'.$schema['type'])){
					$r=call_user_func(array('JsonApiSchema','_validate_'.$schema['type']),$val,$schema);
					if ($r!==true){
						throw new JsonApiSchemaException($field.' '.$r);
					}
				}else {
					throw new JsonApiSchemaException($field.' Unknown field type.');
				}
			}
		}else {
			if ($schema['required']) {
				throw new JsonApiSchemaException($field);
			}
		}
	}
	/**
	 * int(10)
	 */
	static function _validate_INT($value,$schema){
		//长度检查
		if (strlen($value)> $schema['length']){
			throw new JsonApiSchemaException($field.' Length oversize.');
		}
		//值检查
		if (is_null(self::$_locale))
		{
			self::$_locale = localeconv();
		}
		
		$value = str_replace(self::$_locale['decimal_point'], '.', $value);
		$value = str_replace(self::$_locale['thousands_sep'], '', $value);
		
		if (strval(intval($value)) != $value)
		{
			return 'not a int.';
		}
		return true;
	}
	static function _sample_INT($schema){
		return rand(1000000,1000000000);
	}
	/**
	 * enum(a|b|c) 
	 */
	static function _validate_ENUM($value,$schema){
		if (!in_array($value,explode('|',$schema['length']))){
			return 'value invalid.';
		}
		return true;
	}
	static function _sample_ENUM($schema){
		$opts=explode('|',$schema['length']);
		return $opts[rand(0,count($opts)-1)];
	}
	/**
	 * char(32) 
	 */
	static function _validate_CHAR($value,$schema){
		//长度检查
		if (strlen($value)> $schema['length']){
			return 'Length oversize.';
		}
		return true;
	}
	static function _sample_CHAR($schema){
		return 'hello world';
	}
	/**
	 * float(10.3)
	 */
	static function _validate_FLOAT($value,$schema){
		if (is_null(self::$_locale))
		{
			self::$_locale = localeconv();
		}
		
		$value = str_replace(self::$_locale['decimal_point'], '.', $value);
		$value = str_replace(self::$_locale['thousands_sep'], '', $value);
		
		if (strval(floatval($value)) != $value)
		{
			return 'not a float.';
		}
		// 检验长度
		if (strpos($schema['length'],'.')){
			list($length1,$length2)=explode('.',$schema['length']);
			@list($v1,$v2)=explode('.',$value);
			if (strlen((string)$v1) > $length1){
				return 'Length oversize.';
			}
			if (strlen((string)$v2) > $length2){
				return 'Length oversize.';
			}
			
		}else {
			if (strlen((string)$value) > $schema['length']){
				return 'Length oversize.';
			}
		}
		return true;
	}
	static function _sample_FLOAT($schema){
		return 10.3;
	}
	static function error($code,$msg){
		return array('code'=>$code,'msg'=>$msg);
	}
	function getPostRawData(){
		return file_get_contents("php://input");
	}
	/**
	 * Generate Document 生成文档
	 */
	function genDoc(){
		echo '<link href="https://cdn.bootcss.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">';
		echo '<div class=container><div class=row>';
		echo '<h1>API LIST</h1>';
		foreach ($this->apiDefineArray as $verb => $schema){
			if (in_array($verb,array(self::HEADER,self::CODES))){
				continue;
			}
			echo '<h2 style="margin-top:50px">'.$verb.'</h2>';
			if (isset($schema[self::DESC])){
				echo "<p class=lead>".$schema[self::DESC]."</p>";
			}
			echo '<h3>Endpoint</h3>';
			echo '<p class=lead>'.$schema[self::ENDPOINT].'</p>';
			if (isset($schema[self::REQUEST])){
				echo '<h3>传入参数 Request Params</h3>';
				echo self::genDocTable($schema[self::REQUEST]);
				echo '<h4>Sample JSON</h4>';
				echo '<div class=json><pre> curl -X POST '. $schema[self::ENDPOINT].' -d "';
				echo json_encode(self::genSampleJson($schema[self::REQUEST]),JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).'"</pre></div>';
			}
			if (isset($schema[self::RESPONSE])){
				echo '<h3>返回参数 Response Params</h3>';
				echo self::genDocTable($schema[self::RESPONSE]);
				echo '<h4>Sample JSON</h4>';
				echo '<div class=json><pre>'.json_encode(self::genSampleJson($schema[self::RESPONSE]),JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).'</pre></div>';
			}
			echo '</table>';
		}
		echo '<h2>CODES</h2>';
		echo '<table class="table table-bordered table-striped table-condensed"><thead><tr><th>Code</th><th>Comment</th></thead><tbody>';
		foreach ($this->apiDefineArray[self::CODES] as $code => $desc){
			echo '<tr><td>'.$code.'</td><td>'.$desc.'</td></tr>';
		}
		echo '</table></div></div>';
	}
	static function genDocTable($schema){
		$html= '<table class="table table-bordered table-striped table-condensed"><thead><tr><th>Name</th><th>Type</th><th>Required</th><th>Sample</th><th>Comment</th></thead><tbody>';
		$nodes=array();
		foreach ($schema as $field => $subSchema){
			if (in_array($subSchema['type'],array('NODE','ARRAY'))){
				$nodes[$field]=$subSchema;
			}
			if ($subSchema['type']=='NODE'){
				$subSchema['length']='{}';
			}elseif ($subSchema['type']=='ARRAY'){
				$subSchema['length']='[]';
			}else {
				$subSchema['length']='('.$subSchema['length'].')';
			}
			$html.= '<tr><td>'.$field.'</td><td>'.$subSchema['type'].@$subSchema['length'].'</td><td>'.($subSchema['required']?'Y':'O').'</td><td>'.@$subSchema['sample'].'</td><td>'.@$subSchema['comment'].'</td></tr>';
		}
		$html.= '</tbody></table>';
		foreach ($nodes as $field => $n){
			$html.='<h4>'.$field.' '.$n['type'].'</h4>';
			$html.=self::genDocTable($n['children']);
		}
		return $html;
	}
	static function genSampleJson($schema){
		$ret=array();
		foreach ($schema as $field => $subSchema){
			if ($subSchema['type']=='NODE'){
				$ret[$field]=self::genSampleJson($subSchema['children']);
			}elseif ($subSchema['type']=='ARRAY'){
				$ret[$field]=array(self::genSampleJson($subSchema['children']),self::genSampleJson($subSchema['children']));
			}elseif (!empty($subSchema['sample'])) {
				$ret[$field]=$subSchema['sample'];
			}else{
				$ret[$field]=call_user_func(array('JsonApiSchema','_sample_'.$subSchema['type']),$subSchema);
			}
		}
		return $ret;
	}
	
	const REQUEST='apiRequest';
	const RESPONSE='apiResponse';
	const HEADER = 'apiHeader';
	const ENDPOINT = 'apiEndpoint';
	const CODES = 'apiCodes';
	const DESC='apiDescription';
	static $_locale;
	static $defaultCodes=array(
		'0'=>'Success',
		'404'=>'API not found.',
		'500'=>'Data missing or invalid.',
	);
}
