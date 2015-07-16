<?php
	// sae one page press
	define('KC_PASS', 'pass'); // 默认密码，请修改！
	define('KC_TITLE', 'Jesse😼随想'); // 定义标题 
	define('KC_PERPAGE', 0 ); // 分页条数，0 为不分页，其他大于0整数为分页。
	define('KC_LOGIN', 'login'); // login action 默认不需要修改，修改后可以隐藏登陆地址，更安全！
	define('KC_QINIU_AK', '');	// 七牛的 AccessKey
	define('KC_QINIU_SK', '');	// 七牛的 SecretKey
	define('KC_QINIU_SCOPE', '');		// 七牛的空间名称！
	define('KC_QINIU_DOMAIN', '');		// 七牛的空间对应的域名！
	define('KC_QINIU_DEADTIME', 600);	// token有效期
	define('KC_DEBUG', true); // 打开调试，默认不需要修改
	if(!defined('SAE_MYSQL_HOST_M')){ // define your localhost host,user,pass,database,port
		define('SAE_MYSQL_HOST_M', 'localhost');
		define('SAE_MYSQL_USER', 'test');
		define('SAE_MYSQL_PASS', 'test');
		define('SAE_MYSQL_DB', 'test');
		define('SAE_MYSQL_PORT', 3306);
	}
	/* ******************************** -- 下面的不需要修改 -- ******************************** */
	define('KC_IP', $_SERVER['REMOTE_ADDR']);
	define('KC_TIME', time());
	date_default_timezone_set('PRC');
	//session_save_path('/tmp/');
	session_start();
	if (get_magic_quotes_gpc() && (!ini_get('magic_quotes_sybase'))){
		!empty($_GET) && kc_stripslashes($_GET);
		!empty($_POST) && kc_stripslashes($_POST);
	}else{
		!empty($_GET) && kc_trim($_GET);
		!empty($_POST) && kc_trim($_POST);
	}
	$is_ok = !empty($_SESSION['pass']) && KC_IP == $_SESSION['pass']['ip'] && KC_PASS === $_SESSION['pass']['pass'];
	$is_post = strtoupper($_SERVER['REQUEST_METHOD']) == 'POST';
	$is_json = !empty($_SERVER['HTTP_ACCEPT']) && false !== stripos($_SERVER['HTTP_ACCEPT'], 'application/json');
	$is_file = defined('KC_QINIU_AK') && KC_QINIU_AK && defined('KC_QINIU_SK') && KC_QINIU_SK && defined('KC_QINIU_SCOPE') && KC_QINIU_SCOPE && defined('KC_QINIU_DOMAIN') && KC_QINIU_DOMAIN;
	header("Content-Type: ".($is_json ? 'application/json' : 'text/html')."; charset=UTF-8");// type
	
	$title = KC_TITLE;
	$host  = $_SERVER['HTTP_HOST'];
	$perpage = defined('KC_PERPAGE') && is_int(KC_PERPAGE) && KC_PERPAGE >= 0 ? KC_PERPAGE : 0 ; // get all
	$page = !empty($_GET['page']) ? intval($_GET['page']) : 1; 
	$base_url = explode('?', $_SERVER['REQUEST_URI'])[0];
	$s_action = $action = !empty($_GET['a']) ? $_GET['a'] : '';
	$a_login = defined('KC_LOGIN') && !in_array(KC_LOGIN, array('press', 'logout', 'view', 'error', 'home')) ? KC_LOGIN : 'login';
	
	if(!$is_ok && in_array($action, array('view', 'press', ''))){
		$_SESSION['last_url'] = $_SERVER['REQUEST_URI'];
	}
	$db = new DB();
	if($a_login==$action){
		if($is_ok){
			header("Location: {$base_url}");
			exit;
		}
		if($is_post && !empty($_POST['pass']) && KC_PASS===$_POST['pass']){
			$is_ok = true;
			$_SESSION['pass'] = array('ip'=>KC_IP, 'time'=>KC_TIME, 'pass'=>$_POST['pass']);
			header("Location: ".(!empty($_SESSION['last_url']) ? $_SESSION['last_url'] : $base_url.'?a=press'));
			unset($_SESSION['last_url']);
			exit;
		}
	}elseif('logout'==$action){
		if($is_ok){
			$_SESSION['pass'] = null;
			unset($_SESSION['pass']);
			header("Location: {$base_url}");
			exit;
		}
	}elseif('qiniu_token'==$action){
		!$is_ok && exit(json_encode(array('s'=>0, 'm'=>'请先登录！', 'login'=> 'login'==$a_login ? $base_url.'?a=login' : '')));
		$ext = kc_ext(isset($_POST['filetype']) ? $_POST['filetype'] : '');
		!$ext && exit(json_encode(array('s'=>0, 'm'=>'文件扩展名不合法')));
		$file = !empty($_POST['filename']) && preg_match('/^[a-zA-Z0-9\.\-_]+$/', $_POST['filename']) ? strtolower($_POST['filename']) : date('YmdHis', KC_TIME).'.'.$ext;
		exit(json_encode(array('s'=>1, 'filename'=>$file, 'token'=>kc_qiniu_token($file))));
	}elseif('press'==$action){
		if(!$is_ok){
			$is_json && exit(json_encode(array('s'=>0, 'm'=>'请先登录！', 'login'=> 'login'==$a_login ? $base_url.'?a=login' : '')));
			if('login'==$a_login){
				header("Location: {$base_url}?a=login");
				exit;
			}else{
				$error = '请先登录！';
				$action = 'error';
			}
		}else{
			$is_edit = isset($_GET['p']);
			$id = isset($_GET['p']) ? intval($_GET['p']) : 0;
			if($is_edit){
				$press = $data = $db->fetchRow("SELECT * FROM press WHERE pre_id='{$id}' LIMIT 1");
			}
		}
		if($is_ok && $is_post){
			$r = $to_url = $error = '';
			$press = array(
				'pre_status'=>!empty($_POST['pre_status']) ? 1 : 0,
				'pre_pass'=>!empty($_POST['pre_pass']) ? $_POST['pre_pass'] : '',
				'pre_title'=>!empty($_POST['pre_title']) ? $_POST['pre_title'] : '',
				'pre_content'=>!empty($_POST['pre_content']) ? $_POST['pre_content'] : '',
			); 
			if($is_edit && !$data){
				$error = '没有找到内容';
			}elseif($is_edit && $data['pre_status']==$press['pre_status'] && $data['pre_pass']==$press['pre_pass'] &&
								$data['pre_title']==$press['pre_title'] && $data['pre_content']==$press['pre_content']){
				$to_url = $base_url.'?a=view&p='.$id;
				$r = 1;
			}elseif(empty($press['pre_title'])){
				$error = '请填写标题';
			}elseif(empty($press['pre_content'])){
				$error = '请填写内容';
			}elseif($db->fetchField("SELECT pre_id FROM press WHERE pre_title='".$db->escape($press['pre_title'])."' ".($is_edit ? " AND pre_id!='$id' " : '')." LIMIT 1")){
				$error = '标题已存在，请更换';
			}else{
				if(!$is_edit) $press['pre_time'] = KC_TIME ;
				$r = $db->update($press, 'press', $is_edit ? " WHERE pre_id='{$id}'" : '');
				$to_url = $r ? $base_url."?a=view&p=".($is_edit ? $id : $db->insert_id()) : '';
				$error = $r ? '' : '保存失败';
			}
			$is_json && exit(json_encode(array('s'=>$r ? 1 : 0, 'url'=>$to_url, 'm'=>$error)));
			if($to_url){
				header("Location: {$to_url}");
				exit;
			}
		}
		if($is_ok){
			if(empty($press)){
				$press = array('pre_status'=>'1', 'pre_pass'=>'', 'pre_title'=>'', 'pre_content'=>'');
			}
			$title = ($is_edit ? '修改' : '发布') . '内容 - '. $title;
		}
	}elseif('view'==$action){
		$id = !empty($_GET['p']) ? intval($_GET['p']) : '';
		$is_passed = true;
		$data = $db->fetchRow("SELECT * FROM press WHERE pre_id='{$id}' LIMIT 1");
		if(!$data){
			$error = '没有找到内容';
		}elseif(!$data['pre_status']){
			$error = '内容已关闭';
		}elseif($data['pre_pass'] && (empty($_SESSION['view_pass'][$id]) || $_SESSION['view_pass'][$id]['ip'] != KC_IP || $_SESSION['view_pass'][$id]['pass'] != $data['pre_pass'])){
			$is_passed = false;
		}
		if($is_post && $data && !empty($_POST['pass']) && $_POST['pass']==$data['pre_pass']){
			$is_passed = true;
			$_SESSION['view_pass'][$id] = array('ip'=>KC_IP, 'time'=>KC_TIME, 'pass'=>$_POST['pass']);
		}
		if(!empty($error)){
			$action = 'error';
		}
		$title = htmlspecialchars($data['pre_title']) . ' - '. $title; 
	}else{
		$action = 'home';
		$db->initTable();
		$cond  = $is_ok ? '' : " AND pre_status='1' ";
		$total = $db->fetchField("SELECT COUNT(*) AS COUNT FROM press WHERE 1 $cond");
		$pages = $perpage > 0 ? ceil($total / $perpage) : 0;
		$limit = $perpage > 0 ? sprintf(" LIMIT %d, %d", max(array($page - 1, 0)) * $perpage, $perpage) : '';
		$data_list = $total ? $db->fetchAll("SELECT pre_id,pre_status,pre_title, pre_time, pre_pass FROM press WHERE 1 $cond ORDER BY pre_id DESC {$limit}") : array();
		$prev = $page > 1 ? '<a href="'.$base_url.'?page='.($page-1).'">Prev</a>' : '<span>Prev</span>';
		$next = $page < $pages ? '<a href="'.$base_url.'?page='.($page+1).'">Next</a>' : '<span>Next</span>';
		$paginator = $perpage && $total > $perpage ? '<div class="paginator">'.$prev.'<b>'.$page.'/'.$pages.'</b>'.$next.'</div>' : '';
	}
	// 逻辑部分结束
	if('error'==$action && !empty($error)){
		$title = $error . ' - '. KC_TITLE;
	}
	function kc_ext($s){
        $exts = array('image/gif'=>'gif', 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/pjpeg'=>'jpg'); 
        $s = strtolower($s);
        return !empty($exts[$s]) ? $exts[$s] : '';
	}
	function kc_qiniu_token($name){ // qiniu token create
		$putPolicy = sprintf('{"scope":"%s","deadline":%d,"returnBody":"{\"name\":$(fname),\"size\":$(fsize),\"w\":$(imageInfo.width),\"h\":$(imageInfo.height),\"hash\":$(etag)}"}', 
					  KC_QINIU_SCOPE.':'.$name, KC_TIME+KC_QINIU_DEADTIME); 
		$encodePutPolicy = kc_base64_encode($putPolicy);
		return KC_QINIU_AK.':'.kc_base64_encode(hash_hmac('sha1', $encodePutPolicy, KC_QINIU_SK, true)).':'.$encodePutPolicy;
	}
    function kc_base64_encode($data){ // qiniu url_safe_base64_encode()
		return str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
    }
	function kc_stripslashes(&$arr){
		if(is_array($arr)){
			array_walk_recursive($arr, 'kc_stripslashes');
		}else{
			$arr = stripslashes(trim($arr));
		}
	}
	function kc_trim(&$arr){
		if(is_array($arr)){
			array_walk_recursive($arr, 'kc_trim');
		}else{
			$arr = trim($arr);
		}
	}
	function kc_cont($s){
		$s = preg_replace_callback('/\!\[([^\[\]]+)\]\(([^\(\)]+)\)/', function($m){
			return '<img class="img" src="'.$m[2].'" title="'.$m[1].'" />';
		}, htmlspecialchars(trim($s)));
		return implode('', array_map(function($v){
			return $v ? '<p>'.$v.'</p>' : '<p class="empty">&nbsp;</p>';
		}, preg_split("/\r\n|\r|\n/", $s)));
	}
class DB {
	private $db;
	public $sqls;
	function __construct() {
		$this->db = new mysqli(SAE_MYSQL_HOST_M, SAE_MYSQL_USER, SAE_MYSQL_PASS, SAE_MYSQL_DB, SAE_MYSQL_PORT);
		if($this->db->connect_errno){
			exit("mysql error: ".$this->db->connect_error);
		}
		$this->sqls = array();
		$version = $this->db->get_server_info();
		if($version>'4.1'){
			$this->query("SET character_set_connection='utf8mb4', character_set_results='utf8mb4', character_set_client='binary'");
			if($version>'5.0.1'){
				$this->query("SET sql_mode=''");
			}
		}
	}
	
	private function query($sql){
		$result = $this->db->query($sql);
		$this->sqls[] = array($sql, $this->db->error);
		if(KC_DEBUG && $this->db->errno){
			exit('<pre>'.print_r($this->db, true).'</pre>');
		}
		return preg_match('/^\s*INSERT|UPDATE|DELETE/', $sql) ? !$this->db->errno : $result;
	}
	
	public function update($arr, $table, $where=''){
		$sql = ($where ? "UPDATE":"INSERT INTO")." {$table} SET ".implode(',', array_map(function($k, $v){
			return "`{$k}`='".$this->escape(trim($v))."'";
		}, array_keys($arr), $arr)).$where;
		return $this->execSql($sql);
	}
	
	public function fetchAll($sql){
		$ret = array();
		if(($result = $this->query($sql)) && $result->num_rows){
			while($row = $result->fetch_array(MYSQL_ASSOC)){
				$ret[] = $row;
			}
			$result->free_result();
		}
		return $ret;
	}
	
	public function fetchRow($sql){
		$result = $this->query($sql);
		return $result && $result->num_rows ? $result->fetch_array(MYSQL_ASSOC) : null;
	}
	
	public function fetchField($sql){
		$result = $this->query($sql);
		return $result && $result->num_rows ? $result->fetch_row()[0] : null;
	}
	
	public function insert_id(){
		return $this->db->insert_id;
	}
	
	public function execSql($sql){
		return $this->query($sql);
	}
	
	public function escape($s){
		return $this->db->real_escape_string($s);
	}
	
	public function initTable(){
		$result = $this->query("SHOW TABLES LIKE 'press'");
		if(!$result || !$result->num_rows){
			$table = "CREATE TABLE IF NOT EXISTS press(
				pre_id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'pid',
				pre_time INT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'time',
				pre_status TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT 'stauts',
				pre_pass VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'pass',
				pre_title VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'title',
				pre_content TEXT NULL COMMENT 'content',
				PRIMARY KEY(pre_id),
				UNIQUE KEY(pre_title)
			) ENGINE=MyISAM CHARSET=utf8mb4 AUTO_INCREMENT=10001 ";
			return $this->execSql($table);
		}
		return true;
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no" />
		<title><?php echo $title; ?></title>
		<style type="text/css">
		html,body,div,h1,h2,h3,h4,h5,h6,ul,ol,li,form,p,input,textarea,a,span{margin:0; padding:0;}
		html,body{height:100%; font:13px/1.5 Verdana,sans-serif; }
		body{color: #555; text-shadow: 0 0 2px rgba(0,0,0,0.2); background: #F2F2F2;}
		h1{text-align:center; font-size: 1.8rem; line-height: 1.4em; padding:1% 0; margin-bottom: 0.5em; word-break:break-all;}
		ul{list-style-type:none;}
		a{color: #222; text-decoration:none;}
		a:hover{text-decoration: underline;}
		#main{max-width: 800px; margin: 0 auto; background: #FFF; min-height:100%; box-shadow:0 0 10px rgba(0,0,0,0.2);}
        #content{padding:1% 2%;}
		#pass_form{text-align:center;}
        .links{padding:0.2em; margin-bottom: 0.8em; font-size:0.9rem; color: #999; border-bottom: 1px solid #CCC;}
		.links a{margin-right: 0.5em;}
		.links span{float:right;}
		.links span.s-logout{margin:0 0 0 0.5em;}
		.links span.s-logout a{margin:0;}
		.error{text-align:center; font-size:1.5rem; line-height:3em;}
		/* press */
		.fld{margin-bottom: 0.5rem;}
		.fld p, .fld .fld-p{margin-bottom: 4px; padding: 0 4px;}
		.fld-p{position:relative;}
		.fld-c{position:relative; z-index:2;}
		.fld-pro{position: absolute; z-index: 0; left:0; top:0; width:0; height: 100%; background: #adffdc}
		.txt,.txa{border:1px solid #CCC; box-shadow:0px 1px 1px rgba(0, 0, 0, 0.075) inset; font-size:1rem; padding:1px 3px; line-height:1.5; color:#666; width:100%; display:border-box;box-sizing: border-box; -webkit-box-sizing:border-box; -moz-box-sizing: border-box; transition: border-color 0.15s ease-in-out 0s, box-shadow 0.15s ease-in-out 0s;}
		.txt:focus, .txa:focus{border-color:#66AFE9;box-shadow:0 1px 1px rgba(0, 0, 0, 0.075) inset, 0 0 5px rgba(102, 175, 233, 0.6); outline: 0px none; }
		.pre-note{word-break:break-all; color: #999; text-shadow:none; border-top: 1px dashed #CCC; margin-top: 2em;padding: 0.5em 0;}
		/* view */
        .cont{font-size: 1.2rem; line-height:1.8em; color: #707070; word-break:break-all;}
		.cont p{margin-top: 0.5em;}
		.cont p.empty{line-height: 1em;}
		.cont p:first-child, .cont p.empty{margin-top:0;}
        .view:after{content:"----------(END)----------"; display:block; width: 100%; margin-top: 1.5em; text-align: center; color: #DDD;}
		.press-time{float:right; color: #999;}
		.img{display:block; margin:0 auto; max-width: 100%;}
        /* home */
		.paginator{text-align:center; color: #999; padding: 0.5em 0;}
		.paginator a, .paginator span, .paginator b{padding:0.25em 0.5em; font-weight:normal;}
		.paginator b{color: #C5C5C5;}
		.list li{background:#F2F2F2; margin-bottom:0.8em;}
		.list li.lock{position:relative;}
		.list li.lock:before{content:"🔒"; position:absolute; left:0; top:-0.5em;}
		.list li.down a{color: #999; text-decoration: line-through;}
        .list a{display:block; font-size:1.2rem; line-height:1.3em; padding:0.5em; transition: box-shadow 0.15s ease-in-out 0s; word-break:break-all;}
		.list a:hover{box-shadow: 0 0 10px rgba(0,0,0,0.2) inset; text-decoration:none;}
		.list a span{float:right; color: #CBCBCB; font-size:1rem;}
		.total{margin-top: 1em; line-height: 2em; text-align:center; color: #AAA;}
		@media only screen and (max-device-width : 800px) {
			html,body{font-size: 12px;}
			h1{font-size:1.5rem;}
		}
		</style>
	</head>
	<body>
		<div id="main">
			<div id="content">
				<h1><?php echo $title; ?></h1>
				<?php if($is_ok || 'home'!=$action){ ?>
					<div class="links">
						<?php if($is_ok){ ?><span class="s-logout"><a href="<?php echo $base_url.'?a=logout';?>">Logout</a></span><?php } ?>
						<?php if('view'==$action){ ?><span><?php echo empty($error) ? date('Y-m-d H:i:s', $data['pre_time']) : ''; ?></span><?php } ?>
						<?php if('home'!=$action){ ?><a href="<?php echo $base_url;?>">Home</a><?php } ?>
						<?php if($is_ok){ ?>
							<?php if('press'!=$action || !empty($is_edit)){ ?><a href="<?php echo $base_url.'?a=press';?>">Press</a><?php } ?>
							<?php if('view'==$s_action){ ?><a href="<?php echo $base_url.'?a=press&amp;p='.$data['pre_id'];?>">Edit</a><?php } ?>
							<?php if(!empty($is_edit)){ ?><a href="<?php echo $base_url.'?a=view&amp;p='.$id;?>">View</a><?php } ?>
						<?php } ?>
					</div>
				<?php } ?>
				<?php if('error'==$action){ ?>
					<div class="error"><?php echo $error; ?></div>
				<?php }elseif($a_login==$action){?>
					<form action="" method="POST" id="pass_form">
						<div class="fld"><p>密码：</p><input type="password" class="txt" name="pass" value="" /></div>
						<div><input type="submit" value="进 入" /></div>
					</form>
				<?php }elseif('press'==$action){ ?>
					<form action="" method="POST" id="press_form">
						<div class="fld"><p>标题：</p><input type="text" class="txt" name="pre_title" id="pre_title" value="<?php echo htmlspecialchars($press['pre_title']); ?>" /></div>
						<div class="fld"><p>密码：</p><input type="text" class="txt" name="pre_pass" value="<?php echo htmlspecialchars($press['pre_pass']); ?>" /></div>
						<div class="fld"><label><input type="checkbox" name="pre_status" value="1"<?php echo $press['pre_status'] ? 
							' checked' : ''; ?> />正常</label></div>
						<div class="fld"><div class="fld-p"><div class="fld-c"><?php if($is_file){ ?>图片：<input type="file" name="file" id="file" value="" /><?php }else{ echo '内容：'; } ?></div><div class="fld-pro" id="pro" url="<?php echo $base_url.'?a=qiniu_token';?>" link="<?php echo KC_QINIU_DOMAIN; ?>"></div></div>
							<textarea cols="60" rows="8" class="txa" name="pre_content" id="pre_content"><?php echo htmlspecialchars($press['pre_content']);?></textarea>
						</div>
						<div><span id="press_time" class="press-time"></span><input type="submit" id="pre_submit" value="保 存" /></div>
						<div class="pre-note">备注：<br>编辑内容未保存，再次打开，会自动恢复。<br>
							上传图片文件名只含有（数字、字母、_-.等）不会被重命名。</div>
					</form>
				<?php }elseif('view'==$action){ ?>
					<div class="view">
						<?php if(!$is_passed){ ?>
							<form action="" method="POST" id="pass_form">
								<div class="fld"><p>输入Pass查看：</p><input type="text" class="txt" name="pass" value="" /></div>
								<div><input type="submit" value="查 看" /></div>
							</form>
						<?php }else{ ?>
							<div class="cont"><?php echo kc_cont($data['pre_content']);?></div>
						<?php } ?>
					</div>
				<?php }else{ ?>
					<div id="list" class="list">
						<ul>
							<?php foreach($data_list as $d){ $style = array(); $d['pre_pass'] && $style[]='lock'; !$d['pre_status'] && $style[]='down';  ?>
								<li<?php echo !empty($style) ? ' class="'.implode(' ', $style).'"' : '';?>><a href="<?php echo $base_url . '?a=view&amp;p='.$d['pre_id']; ?>"><span><?php echo date('Y/m/d', $d['pre_time']);?></span><?php echo $d['pre_title']; ?></a></li>
							<?php } ?>
						</ul>
					</div>
					<?php echo $paginator ? $paginator : '<div class="total">Total: '.$total.'</div>'; ?>
				<?php } ?>
			</div>
		</div>
		<?php echo !empty($error) && 'error'!=$action ? '<script type="text/javascript">alert("'.$error.'");</script>' : ''; ?>
		<?php if('press'==$action){ ?>
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
			function date_time(date){
				var d = [date.getFullYear(), date.getMonth()+1, date.getDate(), date.getHours(), date.getMinutes(), date.getSeconds(), date.getMilliseconds()];
				for(var k in d) if(d[k].toString().length<2) d[k] = '0'+d[k];
				return d.slice(0,3).join('-')+' '+d.slice(3,6).join(':')+'.'+d[6];
			}
			function insert_image(name, link){
				var img = ' !['+name+']('+link+') ';
				var pc = id('pre_content');
				var pcv = pc.value;
				pc.focus();
				if(pc.selectionStart){
					pc.value = pcv.substring(0, pc.selectionStart) + img + pcv.substring(pc.selectionStart, pcv.length);
				}else{
					pc.value += img;
				}
			}
			function upload_qiniu(token, filename, file){
				var xhr = newXhr();
				var fm = new FormData();
				fm.append('token', token);
				fm.append('key', filename);
				fm.append('file', file);
				ajaxPost(xhr, 'http://upload.qiniu.com/', fm, function(qr){
					insert_image(filename.replace(/\.[^\.]+$/, ''), 'http://'+id('pro').getAttribute('link')+'/'+filename);
					setObject(id('pro').style, {width: '0'});
				}, id('pre_submit'), 'json', id('pro'));
			}
			function init_press(){
				var cache = localStorage.getItem('press');
				if(cache){
					var cj = JSON.parse(cache);
					if(cj && location.href === cj.url){
						id('press_time').innerHTML = date_time(new Date(cj.time));
						id('pre_content').value = cj.press;
					}
				}
				addEvent(id('pre_content'), 'input', function(){
					var date = new Date();
					id('press_time').innerHTML = date_time(date);
					localStorage.setItem('press', JSON.stringify({time: date.getTime(), url: location.href, press: id('pre_content').value })); 
				});
				addEvent(id('press_form'), 'submit', function(evt){
					var xhr = newXhr();
					if(xhr){
						evt.preventDefault();
						ajaxPost(xhr, this.action, new FormData(this), function(r){
							if(r && r.s){
								localStorage.removeItem('press');
								if(r.url) location.href = r.url;
							}else if(r && r.login){
								alert(r.m);
								location.href = r.login;
							}else{
								alert(r && r.m ? r.m : '请求错误');
							}
						}, id('pre_submit'));
					}
				});
				addEvent(id('file'), 'change', function(evt){
					var xhr = newXhr();
					if(xhr){
						var file = this.files[0];
						ajaxPost(xhr, id('pro').getAttribute('url'), 'filename='+encodeURIComponent(file.name)+'&filetype='+file.type, function(r){
							if(r && r.token) upload_qiniu(r.token, r.filename, file); 
							else alert(r && r.m ? r.m : '请求错误');
						}, id('pre_submit'));
					}
				}, id('pre_submit'));
			}
			(function(){ // for press form 
				window.onload = function(){ if('FormData' in window && 'localStorage' in window) init_press(); };
			})();
		</script>
		<?php } ?>
	</body>
</html>