<?php
	define('KC_PASSWORD', 'password');	// 你的密码
	define('KC_SAVEDOMAIN', 'doc');		// 在SAE里设置的文件domain
	define('KC_KVFILE', 'docs.json');	// k-v 缓存文件名
	define('KC_LOCALDIR', '/Users/jesse/Sites/data/');		// 非SAE环境：存储根路径
	define('KC_LOCALURL', 'http://localhost/~jesse/data/'); // 非SAE环境：访问网址
	define('KC_INFODIR',  '/Users/jesse/Sites/data/'); 		// 非SAE环境：文件描述存储路径
	define('KC_IP', $_SERVER['REMOTE_ADDR']);
	define('KC_TIME', time());
	date_default_timezone_set('PRC');
	session_start();
	$is_json = !empty($_SERVER['HTTP_ACCEPT']) && false !== stripos($_SERVER['HTTP_ACCEPT'], 'application/json');
	$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && false!==stripos($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest');
	$is_ok = !empty($_SESSION['pass']) && KC_IP == $_SESSION['pass']['ip'] && KC_PASSWORD === $_SESSION['pass']['pass'];
	if(!empty($_POST['pass'])){
        if(KC_PASSWORD===$_POST['pass']){
        	$is_ok = true;
			$_SESSION['pass'] = array('ip'=>KC_IP, 'time'=>KC_TIME, 'pass'=>$_POST['pass']);
        }
	}
	session_write_close();
	header("Content-Type: ".($is_json ? 'application/json' : 'text/html')."; charset=UTF-8");// type
	$base_url = explode('?', $_SERVER['REQUEST_URI'])[0];
   	$dir = KC_SAVEDOMAIN;
    $cachefile = 'saekv://'.KC_KVFILE;
	if(!class_exists('SaeStorage')){
		$cachefile = KC_INFODIR.KC_KVFILE;
		class SaeStorage {
			public function getListByPath($dir){
				$lists = array();
				$files = glob(KC_LOCALDIR.$dir.'/*.{jpg,png,gif}', GLOB_BRACE);
				foreach($files as $f){
					$fb = basename($f);
					$lists[$fb] = array('fullName'=>$fb, 'name'=>$fb, 'length'=>filesize($f), 'time'=>filectime($f));
				}
				return array('files'=>$lists);
			}
			public function getUrl($dir, $f){
				return KC_LOCALURL.$dir.'/'.$f;
			}
			public function delete($dir, $f){
				$dir = trim($dir, './');
				$f = trim($f, './');
				file_exists(KC_LOCALDIR.$dir.'/'.$f) && @unlink(KC_LOCALDIR .$dir.'/'. $f);
				return true;
			}
			public function upload($dir, $newfile, $upload){
				if(file_exists($upload) && is_uploaded_file($upload) && @move_uploaded_file($upload, KC_LOCALDIR.$dir.'/'.$newfile)){
					return true;
				}
				return false;
			}
		}
	}
    if ($is_ok) {
        $cache = file_exists($cachefile) ? @json_decode(file_get_contents($cachefile), true) : array();
        if(empty($cache) && !is_array($cache)) {
            $cache = array();
        }
        $s = new SaeStorage(); 
       	if (!empty($_REQUEST['del'])) { // 删除文件
            if($s->delete($dir, $_REQUEST['del'])){
                $msg = '删除成功';
                unset($cache[$_REQUEST['del']]);
                file_put_contents($cachefile, json_encode($cache));
            }
        }
        // 上传文件
        if(!empty($_POST['upload'])){
            $time = time();
            $upload = !empty($_FILES['file']) ? $_FILES['file'] : array();
            $ext = !empty($upload['type']) ? file_ext($upload['type']) : '';
            $newfile = date('YmdHis', $time) .'-'. rand(1000, 9999) . '.' . $ext;
            $desc = !empty($_POST['desc']) ? $_POST['desc'] : '';
            $upload_errors = array(
                0 => '上传成功',
                1 => '文件大小超过服务器限制',
                2 => '文件大小超出浏览器限制',
                3 => '文件上传不完整',
                4 => '没有找到要上传的文件',
                5 => '服务器临时文件夹丢失',
                6 => '文件写入到临时文件夹出错',
                7 => '上传目录不可写',
            );
            
            if(empty($upload)) {
                $msg = "上传失败，请重新上传";
            }elseif($upload['error'] > 0){
                $msg = '上传错误：'.$upload_errors[$upload['error']];
            }elseif(!$ext){
                $msg = '文件类型：'.$upload['type'].' 不允许，请上传jpg/png/gif图片';    
            }elseif(!is_uploaded_file($upload['tmp_name'])){
                $msg = '没找到上传的缓存文件。';
            }else{
                if(!$s->upload($dir, $newfile, $upload['tmp_name'])){
                    $msg = '存储文件失败，请重新上传';
                }else{ // $s->getUrl('jesse', $newfile);
                    $cache[$newfile] = mb_substr($desc,0, 30, 'UTF-8'); 
                    file_put_contents($cachefile, json_encode($cache));
                    $msg = '上传成功';
                    $desc = '';
                }
            }
            '上传成功'!=$msg && $is_ajax && exit('Error: '.$msg);
        }
        
        // 读取文件
        $attaches = array();
        $lists = $s->getListByPath($dir);
		if(!empty($lists['files'])) {
			foreach($lists['files'] as $l) $attaches[] = array('url'=>$s->getUrl($dir, $l['fullName']), 'name'=>$l['fullName'], 'size'=>$l['length']);
		} 
    }
	
	function file_ext($s){
        $exts = array('image/gif'=>'gif', 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/pjpeg'=>'jpg'); 
        $s = strtolower($s);
        return !empty($exts[$s]) ? $exts[$s] : '';
	}
?>
<?php if(!$is_ajax){ ?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no">
        <title>文档传输</title>
		<style type="text/css">
		body{font-size: 14px; color: #666;}
		.main{max-width: 600px; text-align:center; margin: 0 auto;}
		.attaches{margin-top: 10px; border: 1px solid #F2F2F2;}
		.attaches ol{list-style-type: decimal; list-style-position:inside; text-align: left; padding:0 0.8em; -moz-user-select: none; -webkit-user-select: none; -ms-user-select:none; user-select:none;-o-user-select:none;}
		.attaches li{line-height: 2em; padding: 0 5px;}
		.attaches li i{float: right;font-style:normal;margin-left: 5px; display:none;cursor:pointer;}
		.attaches li b{margin-left: 2em;}
		.attaches li.on{background: #FFF205;}
		.attaches li.on i{display:block;}
		.fields{margin:10px auto; border: 1px dashed #CCC; padding: 10px; text-align: center; position: relative;}
		.fields p{margin:0 0 10px 0; padding: 5px 0;}
		.pro{left:0; top:0; z-index:-1; width:0; height: 100%; position: absolute; background: #adffdc;}
		</style>
	</head>
	<body>
        <div class="main" id="main"><?php } ?>
        <?php if($is_ok){ ?>
			<h1>文档传输</h1>
			<p><a href="">刷新网页</a></p>
			<?php if(!empty($attaches)){ ?>
				<div class="attaches" id="attaches">
					<p>上传的文件</p>
					<ol>
						<?php foreach($attaches as $a){ ?>
							<li onclick="toggle(event, this);"><i onclick="del(this);">×</i><a href="<?php echo $a['url']; ?>" download="<?php echo $a['name']; ?>"><?php echo $a['name']; ?></a><b><?php echo !empty($cache[$a['name']]) ? $cache[$a['name']] : '';?></b></li>
						<?php } ?>
					</ol>
				</div>
			<?php } ?>
				<div class="fields">
					<form action="" method="POST" enctype="multipart/form-data" id="upload_form" onsubmit="return upload_form();">
						<p><input type="hidden" name="upload" value="upload" />
							<input type="file" name="file" id="file" /></p>
						<p>描述：<input type="text" name="desc" id="desc" value="<?php echo !empty($desc) ? $desc : ''; ?>" /></p>
						<input type="submit" value="上 传" id="submit" />
					</form>
					<div class="pro" id="pro"></div>
				</div>
			<?php }else{ ?>
				<form action="" method="POST" id="pass_form" onsubmit="return pass_form();">
					<p>输入密码查看</p>
					<p><input type="input" name="pass" id="pass" value="" /></p>
					<p><input type="submit" value="提 交" id="submit" /></p>
				</form>
			<?php } ?>
<?php if(!$is_ajax){ ?>
		</div>
		<?php echo !empty($msg) ? '<script type="text/javascript">alert("'.$msg.'");</script>' : '';?>
		<script type="text/javascript">
			function id(domid){
				return document.getElementById(domid);
            }
			function addEvent(el, evt, func){
				return el.addEventListener ? el.addEventListener(evt, func, false) : (el.attacheEvent ? el.attacheEvent('on'+evt, func) : null);
			}
			function newXhr(){
				return window.XMLHttpRequest ? new XMLHttpRequest() : (window.ActiveXObject ? new ActiveXObject('Microsoft.XMLHTTP') : null );
			}
			function setObject(obj, arr){
				for(var i in arr) obj[i] = arr[i];
			}
			function ajaxPost(xhr, url, data, func, btn, type, pro){
				if(btn.disabled) return false;
				setObject(btn, {'disabled': true, src_value: btn.value, value: '正在请求...'});
				type = type || 'json';
				if(pro){
					setObject(pro.style, {width: '0', display: 'block'});
                    xhr.upload.onprogress = function(evt){ if (evt.lengthComputable) pro.style.width=(100*evt.loaded/evt.total)+'%'; };
                }
                xhr.onreadystatechange = function(){
					if ( xhr.readyState == 4) {
						setObject(btn, {disabled: false, value: btn.src_value});
						if (xhr.status == 200) {
							var rsp = (xhr.responseText+'').replace(/(^\s+)|(\s+$)/, '');
							func('json'===type ? JSON.parse(rsp) : rsp);
                        }else{
							alert('请求错误');
                        }
					}
				};
				xhr.open('POST', url || location.href);
				xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
				if('json'===type) xhr.setRequestHeader('Accept', 'application/json');
				if (typeof data === 'string') xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
				xhr.send(data);
            }
			function pass_form() {
				var pass = id('pass').value;
				if (!pass){
					alert('请填写密码');
					return false;
				}
				var xhr = newXhr();
				if (xhr){
					ajaxPost(xhr, id('pass_form').action, 'pass='+pass, function(html){
						if (!/id="pass_form"/.test(html)) id('main').innerHTML = html;
						else alert("密码错误");
					}, id('submit'), 'html');
					return false;
				}
			}
			function upload_form(){
				if (!id('desc').value) {
					alert('请填写描述');
					return false;
				} else if(!id('file').value) {
					alert('请先选择一个文件'); 
					return false;
				}
				var xhr = newXhr();
				if (xhr && window.FormData) {
					ajaxPost(xhr, id('upload_form').action, new FormData(id('upload_form')), function(html){
						if(/^Error:/.test(html)) { 
							setObject(id('pro').style, {width: '0'});
							alert(html);
						}else id('main').innerHTML = html;
                    }, id('submit'), 'html', id('pro'));
					return false;
				}
			}
			function toggle(event, obj){ 
				event = event || window.event; 
				var lis = id('attaches').getElementsByTagName('li');
				for (var i=0; i<lis.length; i++) if(lis[i] != obj) lis[i].className = '';
				if(event && ['a','i'].indexOf(event.target.nodeName.toLowerCase())>=0 ) return ;
				obj.className = obj.className ? '' : 'on';
			}
			function del(obj){
				var link = obj.parentNode.getElementsByTagName('a')[0].text;
				var xhr = newXhr();
				if (xhr) ajaxPost(xhr, location.href, 'del='+link, function(html){ id('main').innerHTML = html; }, id('submit'), 'html');
			}
		</script>
	</body>
</html><?php } ?>