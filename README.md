# JsonApiSchema
Define / Validate / Generate API and document for JSON, compatible with all frameworks

JosnApiSchema 尝试做的是建立一个便捷的方式，快速定义 API（通常是 JSON 作为数据交换格式，HTTP POST 作为交换协议），并提供验证和文档是生成功能。

# 来由
网上虽然有很多 API 框架，使用也比较简单，但是比较重，实现了 route、orm 等 的同时，也让引入增加了难度。特别是如果想在已有项目上面增加 API 服务，我只是想写 API 更方便，只是懒得写文档，没必要学习很多新的东西。所以本项目聚焦 API 快速定义，文档自动生成和数据自动验证。

# 定义
更自然的PHP style

```php
$schema=array(
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
  )
);
 ```
 
 ## 支持验证类型
 - int(10)
 - char(32)
 - float(5,2)
 - enum(a|b|c)
 - 可扩展
 
 # 自动文档生成
 ```php
 $jsonApiSchema=new JsonApiSchema();
 $jsonApiSchema->parse($schema);
 $jsonApiSchema->genDoc();
 ```
 
效果图： https://prnt.sc/joepqq

# 验证
 ```php
 $jsonApiSchema=new JsonApiSchema();
 $jsonApiSchema->parse($schema);
 $jsonApiSchema->validate(file_get_contents("php://input"));
 ```
 
 # 扩展
 
 ## 验证类型扩展
 ```php
 class mySchema extends JsonApiSchema{
  static function _validate_IPV4($value,$schema){
    $test = @ip2long($value);
    return $test !== - 1 && $test !== false;
  }
  static function _sample_IPV$($value){
    return '120.25.52.130';
  }
 }
 ```

```php
  'field'=>'ipv4,IP 地址',
```

 
