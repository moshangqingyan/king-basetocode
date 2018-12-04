<?php
/**
 * Created by PhpStorm.
 * User: WX
 * Date: 2018/11/27
 * Time: 14:57
 */
namespace King\BaseToCode;

class Code
{
    /*
     *取得图片路径和图片尺寸
     */
    public $ImagePath;
    public $ImageSize;
    public $ImageInfo;

    public function __construct($path)
    {
        if (!file_exists($path)) {
            return false;
        };
        $this->ImagePath = $path;
        $this->ImageSize = getimagesize($this->ImagePath);
        $this->getInfo();
    }

    public static function baseToCode($path)
    {
        $name = time() . uniqid() . '.jpg';
        file_put_contents($name, base64_decode($path));
        $ocr = new Code($name);
        $result =  $ocr->run(config('basetocode'));
        @unlink($name);
        return $result;
    }
    /*
     *获取图像标识符，保存到ImageInfo，只能处理bmp,png,jpg图片
     *ImageCreateFromBmp是我自己定义的函数，最后会给出
     */
    function getInfo(){
        $filetype = substr($this->ImagePath,-3);
        if($filetype == 'bmp'){
            $this->ImageInfo = $this->ImageCreateFromBmp($this->ImagePath);
        }elseif($filetype == 'jpg'){
            $this->ImageInfo = imagecreatefromjpeg($this->ImagePath);
        }elseif($filetype == 'png'){
            $this->ImageInfo = imagecreatefrompng($this->ImagePath);
        }
    }

    /*获取图片RGB信息*/
    function getRgb(){
        $rgbArray = array();
        $res = $this->ImageInfo;
        $size = $this->ImageSize;
        $wid = $size['0'];
        $hid = $size['1'];
        for($i=0; $i < $hid; ++$i){
            for($j=0; $j < $wid; ++$j){
                $rgb = imagecolorat($res,$j,$i);
                $rgbArray[$i][$j] = imagecolorsforindex($res, $rgb);
            }
        }
        return $rgbArray;
    }
    /*
     *获取灰度信息
     */
    function getGray(){
        $grayArray = array();
        $size = $this->ImageSize;
        $rgbarray = $this->getRgb();
        $wid = $size['0'];
        $hid = $size['1'];
        for($i=0; $i < $hid; ++$i){
            for($j=0; $j < $wid; ++$j){
                $grayArray[$i][$j] = (299*$rgbarray[$i][$j]['red']+587*$rgbarray[$i][$j]['green']+144*$rgbarray[$i][$j]['blue'])/1000;
            }
        }
        return $grayArray;
    }
    /*
     *根据自定义的规则，获取二值化二维数组
     *@return  图片高*宽的二值数组（0,1）
     */
    function getErzhi(){
        $erzhiArray = array();
        $size = $this->ImageSize;
        $grayArray = $this->getGray();
        $wid = $size['0'];
        $hid = $size['1'];
        for($i=0; $i < $hid; ++$i){
            for($j=0; $j <$wid; ++$j){
                if( $grayArray[$i][$j] < 30 ){
                    $erzhiArray[$i][$j]=1;
                }else{
                    $erzhiArray[$i][$j]=0;
                }
            }
        }
        return $erzhiArray;
    }
    /*
     *二值化图片降噪
     *@param $erzhiArray二值化数组
     * 如果一个黑点周围全是白点或者仅有一个黑点，我们就认为他是一个噪点
     */
    function reduceZao($erzhiArray){
        $data = $erzhiArray;
        $gao = count($erzhiArray);
        $chang = count($erzhiArray['0']);

        $jiangzaoErzhiArray = array();

        for($i=0;$i<$gao;$i++){
            for($j=0;$j<$chang;$j++){
                $num = 0;
                if($data[$i][$j] == 1)
                {
                    // 上
                    if(isset($data[$i-1][$j])){
                        $num = $num + $data[$i-1][$j];
                    }
                    // 下
                    if(isset($data[$i+1][$j])){
                        $num = $num + $data[$i+1][$j];
                    }
                    // 左
                    if(isset($data[$i][$j-1])){
                        $num = $num + $data[$i][$j-1];
                    }
                    // 右
                    if(isset($data[$i][$j+1])){
                        $num = $num + $data[$i][$j+1];
                    }
                    // 上左
                    if(isset($data[$i-1][$j-1])){
                        $num = $num + $data[$i-1][$j-1];
                    }
                    // 上右
                    if(isset($data[$i-1][$j+1])){
                        $num = $num + $data[$i-1][$j+1];
                    }
                    // 下左
                    if(isset($data[$i+1][$j-1])){
                        $num = $num + $data[$i+1][$j-1];
                    }
                    // 下右
                    if(isset($data[$i+1][$j+1])){
                        $num = $num + $data[$i+1][$j+1];
                    }
                }

                if($num <= 1){
                    $jiangzaoErzhiArray[$i][$j] = 0;
                }else{
                    $jiangzaoErzhiArray[$i][$j] = 1;
                }
            }
        }
        return $jiangzaoErzhiArray;

    }

    /**
     * 归一化处理,针对一个个的数字,即去除字符周围的白点
     * @param $singleArray
     * @return array
     */
    function getJinsuo($singleArray){
        $dianCount = 0;
        $rearr = array();

        $gao = count($singleArray);
        $kuan = count($singleArray['0']);

        $dianCount = 0;
        $shangKuang = 0;
        $xiaKuang = 0;
        $zuoKuang = 0;
        $youKuang = 0;
        //从上到下扫描
        for($i=0; $i < $gao; ++$i){
            for($j=0; $j < $kuan; ++$j){
                if( $singleArray[$i][$j] == 1){
                    $dianCount++;
                }
            }
            if($dianCount>1){
                $shangKuang = $i;
                $dianCount = 0;
                break;
            }
        }
        //从下到上扫描
        for($i=$gao-1; $i > -1; $i--){
            for($j=0; $j < $kuan; ++$j){
                if( $singleArray[$i][$j] == 1){
                    $dianCount++;
                }
            }
            if($dianCount>1){
                $xiaKuang = $i;
                $dianCount = 0;
                break;
            }
        }
        //从左到右扫描
        for($i=0; $i < $kuan; ++$i){
            for($j=0; $j < $gao; ++$j){
                if( $singleArray[$j][$i] == 1){
                    $dianCount++;
                }
            }
            if($dianCount>1){
                $zuoKuang = $i;
                $dianCount = 0;
                break;
            }
        }
        //从右到左扫描
        for($i=$kuan-1; $i > -1; --$i){
            for($j=0; $j < $gao; ++$j){
                if( $singleArray[$j][$i] == 1){
                    $dianCount++;
                }
            }
            if($dianCount>1){
                $youKuang = $i;
                $dianCount = 0;
                break;
            }
        }
        for($i=0;$i<$xiaKuang-$shangKuang+1;$i++){
            for($j=0;$j<$youKuang-$zuoKuang+1;$j++){
                $rearr[$i][$j] = $singleArray[$shangKuang+$i][$zuoKuang+$j];
            }
        }
        return $rearr;
    }

    /*
     *切割成三维数组，每个小数字在一个数组里面
     *只适用四个数字一起的数组
     *@param 经过归一化处理的二值化数组
     */
    function cutSmall($erzhiArray){
        $doubleArray = array();
        $jieZouyou = array();
        $gao = count($erzhiArray);
        $kuan = count($erzhiArray['0']);

        $jie = 0;
        $s = 0;
        $jieZouyou[$s] = 0;
        $s++;

        //从左到右扫描
        for($i=0; $i < $kuan;){
            for($j=0; $j < $gao; ++$j){
                $jie = $jie + $erzhiArray[$j][$i];
            }
            //如果有一列全部是白，设置$jieZouyou,并且跳过中间空白部分
            if($jie == 0){
                $jieZouyou[$s] = $i+1;
                do{
                    $n = ++$i;
                    $qian = 0;
                    $hou = 0;
                    for($m=0; $m < $gao; ++$m){
                        $qian = $qian + $erzhiArray[$m][$n];
                        $hou = $hou + $erzhiArray[$m][$n+1];
                    }
                    $jieZouyou[$s+1] = $n+1;
                }
                    //当有两列同时全部为白，说明有间隙，循环，知道间隙没有了
                while($qian == 0 && $hou == 0);
                $s+=2;
                $i++;
            }else{
                $i++;
            }
            $jie = 0;
        }
        $jieZouyou[] = $kuan;
        //极端节点数量，(应该是字符个数)*2
        $jieZouyouCount = count($jieZouyou);

        for($k=0;$k<$jieZouyouCount/2;$k++){
            for($i=0; $i < $gao; $i++){
                for($j=0; $j < $jieZouyou[$k*2+1]-$jieZouyou[$k*2]-1; ++$j){
                    $doubleArray[$k][$i][$j] = $erzhiArray[$i][$j+$jieZouyou[$k*2]];
                }
            }

        }
        return $doubleArray;
    }

    /**
     * 训练特征值
     * @return array
     */
    public function xunlian()
    {
        $minCodeArr = [];
        $codeArray = [];

        // 获取二值化的数组
        $erzhiArray = $this->getErzhi();
        // 二值化的数组去噪
        $r2 = $this->reduceZao($erzhiArray);
        // 分割数组
        $jinsou = $this->getJinsuo($r2);
        $cut = $this->cutSmall($jinsou);
        // 每个字符最小包裹
        foreach ($cut as $k=>$v) {
            $minCodeArr[$k] = $this->getJinsuo($v);
        }
//        foreach ($minCodeArr as $k=>$v) {
//            $codeArray[] = $this->arrtostring($v);
//        }
        return $this->arrtostring($minCodeArr);
    }

    /*
     *进行匹配
     *@param  $Image  图片路径
     */
    public function run($Keys)
    {
        $result="";
        $minCodeArr = [];
        $maxarr = [];

        // 获取二值化的数组
        $erzhiArray = $this->getErzhi();
        // 二值化的数组去噪
        $r2 = $this->reduceZao($erzhiArray);
        // 分割数组
        $jinsou = $this->getJinsuo($r2);
        $cut = $this->cutSmall($jinsou);
        // 每个字符最小包裹
        foreach ($cut as $k=>$v) {
            $minCodeArr[$k] = $this->getJinsuo($v);
        }

        // 转换成01字符串
        $arr = $this->arrtostring($minCodeArr);
        // 进行关键字匹配
        foreach($arr as $numKey => $numString)
        {
            $max = 0;
            $num = 0;
            foreach($Keys as $value => $key)
            {
                similar_text($value, $numString,$percent);
                if($percent > $max)
                {
                    $max = $percent;
                    $num = $key;
                    $zim = $value;
                }
//                if($max>95){
//                    break;
//                }
            }
            $result .=$num;
            $maxarr[] = $max;
        }
        // 查找最佳匹配数字
        $re = $maxarr;
        $re[] = $result; // 结果及每个字符的相似策划高程度
        return $result;
        //return $result.'|max|一:'.$maxarr['0'].'|二:'.$maxarr['1'].'|三:'.$maxarr['2'].'|四:'.$maxarr['3'];
    }

    public function tongyidaxiao($array)
    {
        $return = $array;
        foreach ($array as $k=>$v) {
            foreach ($v as $key=>$val) {
                $count = count($val);
                if ($count < 20) {
                    for ($i=$count; $i<20; $i++) {
                        $return[$k][$key][$i] = 0;
                    }
                }
            }
        }
        return $return;
    }

    /**
     * 转化为字符串进行相似度比较
     * @param $arr
     * @return array
     */
    public function arrtostring($arr)
    {
        $arrs = [];
        $str = '';
        foreach ($arr as $v) {
            foreach ($v as $value) {
                foreach ($value as $s) {
                    $str .= $s;
                }
            }
            if (substr_count($str,'1') > 10)
                $arrs[] = $str;
            $str = '';
        }
        return $arrs;
    }

    public function printByGray3($array)
    {
        foreach ($array as $v) {
            foreach ($v as $value) {
                if ($value == 1) {
                    echo '■';
                } else {
                    echo '□';
                }
            }
            echo "<br>";
        }
    }


    /*根据灰度信息打印图片*/
    public function printByGray2($array = null)
    {
        $size = $this->ImageSize;
        $grayArray = $this->getGray();
        $grayArray = $array ? $array : $grayArray;
        $wid = $size['0'];
        $hid = $size['1'];
        for($i=0; $i < $hid; ++$i){
            for($j=0; $j < $wid; ++$j){
                if(array_get($grayArray, $i.'.'.$j) == 1){
                    echo '■';
                }else{
                    echo '□';
                }
            }
            echo "<br>";
        }
    }
    /*根据灰度信息打印图片*/
    public function printByGray()
    {
        $size = $this->ImageSize;
        $grayArray = $this->getGray();
        $wid = $size['0'];
        $hid = $size['1'];
        for($i=0; $i < $hid; ++$i){
            for($j=0; $j < $wid; ++$j){
                if($grayArray[$i][$j] < 50){
                    echo '■';
                }else{
                    echo '□';
                }
            }
            echo "<br>";
        }

    }
}