# king-basetocode
解析图片中的验证码
# 调用方式
## 1.添加扩展包
### composer require wx-king-fly/basetocode
## 2.在config/app.php中注册服务
### King\BaseToCode\Provider\CodeServiceProvider::class,
## 3.发布文件配置
### php artisan vendor:publish --provider="King\BaseToCode\Provider\CodeServiceProvider"

# 使用
## use King\BaseToCode\Code;
## Code::baseToCode($str); // 传入base64格式的字符串
