<?php
//从B2C直接注册玩家会员帐号接口模块
class B2cAction extends frontendAction {
		 /**
     * 检测用户名
     */
    public function check_username() {
			$username = $this->_post('username', 'trim', '');
			if(empty($username))
				$this->ajaxReturn(1, '用户名不能为空');
			$username = addslashes(trim(stripslashes($username)));
			if(!isValidAccount($username))
				$this->ajaxReturn(2, '用户名格式有误');
			//连接用户中心
			$passport = $this->_user_server();
			if($passport->check_username($username))
				$this->ajaxReturn(0);
      else
				$this->ajaxReturn(3, '该用户名已被使用');
    }
    
    /**
     * 绑定手机号
     */
    public function bind_mobile() {
			$username = $this->_post('username', 'trim');
			$mobile = $this->_post('mobile', 'trim');
			$code = $this->_post('code', 'trim');
			!$code && $this->ajaxReturn(1, '验证码不能为空');
			!$mobile && $this->ajaxReturn(2, '手机号不能为空');
			!$username && $this->ajaxReturn(3, '用户名不能为空');
			
			$userinfo = D('user')->where("username='".$username."'")->find();
			!$userinfo && $this->ajaxReturn(4, '用户不存在');
			//
			//if(preg_match("/^\d{6}$/gi", $code) === 0)
			if(strlen($code) != 6)
				$this->ajaxReturn(3, '验证码是6位数字');

			$verify = D('verify_sms')->where('mobile="'.$mobile.'" and code='.$code)->order('dateline DESC')->find();
			if($verify)
			{
				//dump($verify);exit;
				//
				$data['uid'] = $userinfo['uid'];
				$data['mobile'] = $mobile;
				$data['mobile_verify'] = 1;
				$ret = M('user')->save($data);
				//file_put_contents("/www/jtd/Public/temp.log",M('user')->getLastSql());

				if($ret === false)
				{
					$this->ajaxReturn(500, '抱歉,系统维护中,稍候');
				}
				else
				{
					//检查用户表是否已有该手机号码
					$yonghu = D('yonghu')->where('mobile="'.$verify['mobile'].'"')->find();
					$member = D('user')->where('userno="'.$userno.'"')->find();
					if ($yonghu){
						$userno = $userinfo['userno'];
						$data = array();
						$data['yonghu_id'] = $yonghu['yonghu_id'];
						$data['userno'] = $userno;
						$data['shop_id'] = $member['shop_id'];
						$data['shop_urp'] = $member['shop_urp'];
						$data['account'] = $member['account'];
						$data['memo'] = 'android'.date('Y-m-d');
						$ret = M('yonghu')->save($data);
					}else{
						$userno = $userinfo['userno'];
						$data = array();
						$data['mobile'] = $mobile;
						$data['name'] = $member['username'];
						$data['userno'] = $userno;
						$data['shop_id'] = $member['shop_id'];
						$data['shop_urp'] = $member['shop_urp'];
						$data['account'] = $member['account'];
						$data['from'] = 'user';
						$data['memo'] = 'android'.date('Y-m-d');
						$data['crtime'] = date('Y-m-d');
						$ret = M('yonghu')->add($data);
					}
					
					$msg = '尊敬的玩家，您的玩家账号'.$userinfo['username'].'已成功绑定了手机号'.$mobile.'。【捷通达玩家系统】';
					sms_send($verify['mobile'], $msg);
					$this->ajaxReturn(0);
				}
					
			}
			else
				$this->ajaxReturn(4, '对不起，该验证码不存在或已过期');
    }
		 
		 /**
     * 用户登录,仅支持POST方法
     */
    public function login() {
			//参数检测
			$username = $this->_post('username', 'trim');
			$password = $this->_post('password', 'trim');

			if (empty($username)) {
				$this->ajaxReturn(1, '账号不能为空');
			}
			if (empty($password)) {
				$this->ajaxReturn(2, '密码不能为空');
			}
			//连接用户中心
			$passport = $this->_user_server();
			$uid = $passport->auth($username, $password);
			if (!$uid) {
				switch ($passport->get_error())
				{
					case Passport::USER_NOT_EXISTS:
						$this->ajaxReturn(3, '该账号不存在');
						break;
					case Passport::USER_BLOCK:
						$this->ajaxReturn(4, '该账号已被限制');
						break;
					case Passport::PASSWORD_ERROR:
						$this->ajaxReturn(5, '密码错');
						break;
				}
			}
			//登录积分
			$reward = alterCreditByAction('login', $uid, $username);
			//登录记录
			$this->visitor->login($uid);
			
			$user = D("User")->where("uid=".$uid)->find();
			$user_info = D("User_info")->where("uid=".$uid)->find();

			$data = array();
			$data['username'] = $user['username'];
			$data['userno'] = $user['userno'];
			$data['mobile'] = $user['mobile'];
			$data['mobile_verify'] = $user['mobile_verify'];
			$data['email'] = $user['email'];
			$data['email_check'] = $user['email_check'];
			$data['is_vip'] = $user['is_pay'];
			$data['nickname'] = $user_info['nickname'];
			$data['realname'] = $user_info['realname'];
			
			$this->ajaxReturn(200, '登录成功',$data);
    }
    
	//验证输入的会员是否存在
	public function checkuid() {
		if($this->isPost()){
			//dump($this->_post());exit;
			$uid = $this->_post('uid', 'trim');

			if (!(strlen($uid)==11||strlen($uid)==16)){
				$this->ajaxReturn(400, '无效的UID');
		  	return;
			}
		  
		  if (strlen($uid)==11){	//手机号
			  //$userno = D('user')->where('mobile_verify = 1 and mobile = "'.$uid.'"')->getField('userno');
			  $user = D('user')->where('mobile_verify = 1 and mobile = "'.$uid.'"')->find();
				if ($user){
					$is_pay = $user['is_pay'];
					$userno = $user['userno'];
					if ($is_pay){	//vip
						$this->ajaxReturn(2, $userno);
			  		return;
			  	}else{	//普通会员
			  		$this->ajaxReturn(1, $userno);
			  		return;
			  	}
				}else{//在会员表找不到，到用户表去查
					//还要检测会员是否已绑定手机
					$user = D('user')->where('mobile = "'.$uid.'"')->find();
					if ($user){
						$mobile_verify = $user['mobile_verify'];
						if (!$mobile_verify){
				  		$this->ajaxReturn(0, '此手机号码未绑定');
				  		return;
				  	}
					}
					
					$find_yonghu = D('yonghu')->where("mobile='".$uid."' or mobile1='".$uid."' or mobile2='".$uid."' or mobile3='".$uid."'")->find();
					if ($find_yonghu){
						$userno = $find_yonghu['userno'];
						$is_pay = $find_yonghu['is_pay'];
						if ($is_pay){	//vip
							$this->ajaxReturn(2, $userno);
				  		return;
				  	}else{	//普通会员
				  		$this->ajaxReturn(1, $userno);
				  		return;
				  	}
					}
					$this->ajaxReturn(0, '此手机号码未绑定');
			  	return;
				}
			}
			
			if (strlen($uid)==16){	//会员卡号
			  //$userno = D('user')->where('userno = "'.$uid.'"')->getField('userno');
			  $user = D('user')->where('userno = "'.$uid.'"')->find();
				if ($user){
					$is_pay = $user['is_pay'];
					if ($is_pay){	//vip
						$this->ajaxReturn(2, $userno);
			  		return;
			  	}else{
			  		$this->ajaxReturn(1, $userno);
			  		return;
			  	}
				}else{
					$this->ajaxReturn(0, '此会员卡号不存在');
			  	return;
				}
			}

		}
		else
		{
			$this->ajaxReturn(400, '目前本接口仅支持HTTP POST方法');
			return;
		}
	}
	
	//获取验证码
	public function getverify() {
		if($this->isPost()){
			//dump($this->_post());exit;
			$mobile = $this->_post('mobile', 'trim');

			if(strlen($mobile)!=11){
				$this->ajaxReturn(400, '无效的手机号码');
		  	return;
			}
		  
		  	$username = D('user')->where('mobile_verify = 1 and mobile = "'.$mobile.'"')->getField('username');
			if (!$username){
				$this->ajaxReturn(400, '此手机号码未被绑定');
		  		return;
			}
				
			//发送验证码
			//$msg = '您的手机注册验证码为/code/，有效期为30分钟，请输入验证码以继续注册操作。【捷通达玩家系统】';
			//改用语音验证码
			$msg = '验证码：/code/';

			try{
				$result = $this->send_verifycode($mobile, $msg);
				if($result){
					$this->ajaxReturn(500, '发送验证码失败');
					return;
				}else{
					$this->ajaxReturn(200, '验证码已发送');
					return;
				}
			}catch ( Exception $e ) {
				$this->ajaxReturn(500, $e->getMessage());
				return;
			}

		}
		else
		{
			$this->ajaxReturn(400, '目前本接口仅支持HTTP POST方法');
			return;
		}
	}

	
	//注册并绑定手机号码
	public function registermobile() {
		if($this->isPost()){
			//dump($this->_post());exit;
			$mobile = $this->_post('mobile', 'trim');
			$username = $this->_post('username', 'trim');
			$password = $this->_post('password', 'trim');	//password
			
			//$code = $this->_post('code', 'trim');	//验证码
			$email = $this->_post('email', 'trim');	//email
			if (!$email) $email = '';
			
			$name = $this->_post('name', 'trim');	//用户名称
			if (!$name) $name = '';
			$nickname = $this->_post('nickname', 'trim');	//称谓
			if (!$nickname) $nickname = '';

			$shop_id = $this->_post('shop_id', 'trim');	//营业点的URP编码
			$operator = $this->_post('operator', 'trim');	//操作员编码
		
			//$pass = random(6, 1);	//随机密码
			$pass = $password;
			
		  if(!$username){
				$this->ajaxReturn(-1, '帐号名不能为空');
				return;
			}
			if(!$name){
				$this->ajaxReturn(-2, '用户名不能为空');
				return;
			}
			if(!$password){
				$this->ajaxReturn(-3, '密码不能为空');
				return;
			}
			
			if(!$shop_id){
				$this->ajaxReturn(-4, '营业点的URP编码不能为空');
				return;
			}
			if(!$operator){
				$this->ajaxReturn(-5, '操作员编码不能为空');
				return;
			}
		  
			$findname = D('user')->where('username = "'.$username.'"')->getField('username');
			if ($findname){
				$this->ajaxReturn(-99, '此用户名已被注册');
		  	return;
			}
			
			
			
        //注册并绑定
        //$username = $mobile;
        //连接用户中心
				$passport = $this->_user_server();
				//
				if(!$passport->check_username($username)){
					$status = -6;
					$info = '用户名被占用';
					$this->ajaxReturn($status, $info);
					return;
				}
					
				//生成会员号 16位数字
				$k = 0;
				while (1)
				{
					$userno = random(16, 1);
					//会员号未被用
					if($passport->check_userno($userno))
					{
						break;
					}
					$k++;
					//防止会员号生成错误导致死循环
					if($k > 10)
						break;
				}
				if($k > 10)
				{
					$status = 400;
					$info = '会员号生成错误，注册失败';
					$this->ajaxReturn($status, $info);
					return;
				}

				//注册
				$uid = $passport->register($username, $pass, $mobile, '', $userno);
				if(!$uid)
				{
					$status = -7;
					$info = '抱歉,系统维护中,稍候';
					$this->ajaxReturn($status, $info);
					return;
				}
				else
				{
					//注册积分
					//初始化玩家等级
					D('user_count')->add(array('uid'=>$uid) );
					//检查用户等级
					checkusergroup($uid, $username);
					//D('user_experience')->add(array('uid'=>$uid) );
					
					//注册奖励
					$reward = alterCreditByAction('register', $uid, $username);

					//登录
					//$this->visitor->login($uid);
					//$this->get_userinfo();
				}
				
				$data = array();
				$data['uid'] = $uid;
				$data['username'] = $username;
				$data['mobile'] = $mobile;
				$data['email'] = $email;
				$data['shop_urp'] = $shop_id;
				$data['account'] = $operator;
				$data['password'] = md5($pass);
				$ret = M('user')->save($data);

				if($ret === false)
				{
					$this->ajaxReturn(-8, '抱歉,系统维护中,稍候');
					return;
				}
				
				
				//保存到jtd_register_log表中
				$shopModel = D('Shop');
				$shop = $shopModel->where(" shop_urp='".$shop_id."' AND status=1")->find();
				$shop_name = $shop['name'];
				$data = array();
				$data['uid'] = $uid;
				$data['mobile'] = $mobile;
				$data['operator'] = $operator;
				$data['operator_name'] = $operator_name;
				$data['shop_id'] = $shop['id'];
				$data['shop_urp'] = $shop_id;
				$data['shop_name'] = $shop_name;
				$data['dateline'] = time();
				M('register_log')->add($data);
				
				
				//user_info表
				$info = array();
				$info['uid'] = $uid;
				$info['nickname'] = $nickname;
				$info['realname'] = $name;
				$info['shop_id'] = $shop['id'];
				$flag = D('user_info')->add($info);
				
				
				//jtduser主表
				$data = array();
				$data['uid'] = $uid;
				$data['mobile'] = $mobile;
				$data['account'] = $operator;
				$data['shop_id'] = $shop['id'];
				$data['shop_urp'] = $shop_id;
				$data['password'] = md5($pass);
				$ret = M('user')->save($data);
				
				//检查用户表是否已有该手机号码
				if ($mobile){
					$yonghu = D('yonghu')->where("mobile='".$mobile."' or mobile1='".$mobile."' or mobile2='".$mobile."' or mobile3='".$mobile."'")->find();
					if ($yonghu){
						$data = array();
						$data['yonghu_id'] = $yonghu['yonghu_id'];
						$data['userno'] = $userno;
						$data['memo'] = 'b2c'.date('Y-m-d');
						
						$data['shop_urp'] = $shop_id;	//这个shop_id是接口传过来的URP编码
						$data['account'] = $operator;
						if ($name) $data['name'] = $name;
						if ($nickname) $data['nick'] = $nickname;
	
						$ret = M('yonghu')->save($data);
					}else{
						$data = array();
						$data['mobile'] = $mobile;
						
						$data['userno'] = $userno;
						if ($name) $data['name'] = $name;
						if ($nickname) $data['nick'] = $nickname;
						
						$data['shop_urp'] = $shop_id;
						$data['account'] = $operator;
						$data['from'] = 'user';
						$data['memo'] = 'b2c'.date('Y-m-d');
						$data['crtime'] = date('Y-m-d');
						$model = D('yonghu');
						$ret = $model->add($data);
						//file_put_contents("/www/jtd/Public/temp.log",$model->getLastSql());
					}
					
					//$msg = '您的号码'.$mobile.'已成功注册玩家电讯会员，密码'.$pass.'。关注微信服务号“玩家电讯”可查个人信息和特权。';
					//file_put_contents("/www/jtd/Public/temp.log",$msg);
					//sms_send($mobile, $msg);
				}
				$this->ajaxReturn(200, $userno);
  

		}
		else
		{
			$this->ajaxReturn(-10, '目前本接口仅支持HTTP POST方法');
			return;
		}
	}

	
	//发送6位验证码短信
	public function send_verifycode($mobile='', $msg = '') {
		$verifycode = random(6, 1);
		//$verifycode = '888888';
		$msg = str_replace('/code/', $verifycode, $msg);
		try{
			//$status = sms_send($mobile, $msg);
			//语音验证码
			$status = yuyin_send($mobile,$verifycode);
			
		}catch ( Exception $e ) {
		}
		//
		$data = array();
		$data['mobile'] = $mobile;
		$data['code'] = $verifycode;
		$data['status'] = $status;
		$data['dateline'] = time();
		M('verify_sms')->add($data);
		//
		return $status;
	}
	
	
	//确认VIP会员
	public function confirmvip() {
		if($this->isPost()){
			//dump($this->_post());exit;
			//file_put_contents("/www/jtd/Public/temp.log",var_export($this->_post(),true));
			$uid = $this->_post('uid', 'trim');
			$orderid = $this->_post('orderid', 'trim');	//URP订单号
			$shop_id = $this->_post('shop_id', 'trim');	//营业点的URP编码
			$operator = $this->_post('operator', 'trim');	//操作员编码

			if (!(strlen($uid)==11||strlen($uid)==16)){
				$this->ajaxReturn(-1, '无效的UID');
		  	return;
			}
			if(!$orderid){
				$this->ajaxReturn(-2, 'URP订单号不能为空');
				return;
			}
			if(!$shop_id){
				$this->ajaxReturn(-3, '营业点的URP编码不能为空');
				return;
			}
			if(!$operator){
				$this->ajaxReturn(-4, '操作员编码不能为空');
				return;
			}
		  
		  $shopModel = D('Shop');
			$shop = $shopModel->where(" shop_urp='".$shop_id."'")->find();
			
		  $mobile = $user = '';
		  if (strlen($uid)==11){	//手机号
		  	$mobile = $uid;
			  $user = D('user')->where('mobile_verify = 1 and mobile = "'.$uid.'"')->find();
				if (!$user){
			  		$this->ajaxReturn(-5, '会员不存在');
			  		return;
		  	}
		  	
		  	$is_pay = $user['is_pay'];
		  	if ($is_pay){
		  		$this->ajaxReturn(-5, '用户已经是VIP会员，不需要确认');
			  	return;
		  	}
		  	
		  	//jtduser主表
				$data = array();
				$data['uid'] = $user['uid'];
				$data['account'] = $operator;
				$data['shop_id'] = $shop['id'];
				$data['shop_urp'] = $shop_id;
				$data['is_pay'] = 1;
				$ret = M('user')->save($data);
		  	
				//在用户表找
				$yonghu = D('yonghu')->where("mobile='".$uid."' or mobile1='".$uid."' or mobile2='".$uid."' or mobile3='".$uid."'")->find();
				if ($yonghu){
					$data = array();
					$data['yonghu_id'] = $yonghu['yonghu_id'];
					$data['userno'] = $user['userno'];
					$data['memo'] = 'pay'.date('Y-m-d');
					$data['shop_urp'] = $shop_id;	//这个shop_id是接口传过来的URP编码
					$data['account'] = $operator;
					$data['is_pay'] = 1;
					$ret = M('yonghu')->save($data);
				}else{
					$data = array();
					$data['mobile'] = $uid;
					$data['userno'] = $user['userno'];
					$data['memo'] = 'pay'.date('Y-m-d');
					$data['name'] = $user['username'];;
					$data['shop_urp'] = $shop_id;
					$data['account'] = $operator;
					$data['is_pay'] = 1;
					$data['from'] = 'user';
					$data['crtime'] = date('Y-m-d');
					$ret = M('yonghu')->add($data);
				}
			}
			
			if (strlen($uid)==16){	//会员卡号
			  $user = D('user')->where('userno = "'.$uid.'"')->find();
				if (!$user){
			  		$this->ajaxReturn(-6, '会员不存在');
			  		return;
		  	}
		  	
		  	$is_pay = $user['is_pay'];
		  	if ($is_pay){
		  		$this->ajaxReturn(-6, '用户已经是VIP会员，不需要确认');
			  	return;
		  	}
		  	
		  	/*
		  	$mobile = $user['mobile'];
		  	$mobile_verify = $user['mobile_verify'];
		  	if (!$mobile || !$mobile_verify){
		  		$this->ajaxReturn(-7, 'VIP会员必须绑定手机');
			  	return;
		  	}
		  	*/
		  	
		  	//jtduser主表
				$data = array();
				$data['uid'] = $user['uid'];
				$data['account'] = $operator;
				$data['shop_id'] = $shop['id'];
				$data['shop_urp'] = $shop_id;
				$data['is_pay'] = 1;
				$ret = M('user')->save($data);
		  	
				//在用户表找
				$yonghu = D('yonghu')->where("userno='".$uid."'")->find();
				if ($yonghu){
					$data = array();
					$data['yonghu_id'] = $yonghu['yonghu_id'];
					$data['userno'] = $user['userno'];
					$data['memo'] = 'pay'.date('Y-m-d');
					$data['shop_urp'] = $shop_id;	//这个shop_id是接口传过来的URP编码
					$data['account'] = $operator;
					$data['is_pay'] = 1;
					$ret = M('yonghu')->save($data);
				}else{
					$data = array();
					$data['mobile'] = $user['mobile'];
					$data['userno'] = $user['userno'];
					$data['memo'] = 'pay'.date('Y-m-d');
					$data['name'] = $user['username'];;
					$data['shop_urp'] = $shop_id;
					$data['account'] = $operator;
					$data['is_pay'] = 1;
					$data['from'] = 'user';
					$data['crtime'] = date('Y-m-d');
					$ret = M('yonghu')->add($data);
				}
			}
			
			//日志表jtd_pay_register_log
			$operator_id = $operator;
			$userModel = D("Employee"); // 实例化Employee对象
			// 查找status值为1name值为think的用户数据 
			$employee = $userModel->where('account="'.$operator_id.'"')->find();
			$operator_mobile = $employee['mobile'];
			$operator_name = $employee['nickname'];

			$shopModel = D('Shop');
			$shop = $shopModel->where(" shop_urp='".$shop_id."'")->find();
					
			$data = array(
			  'uid' => $user['uid'],
				'mobile' => $uid,
				'operator' => $operator,
				'operator_name' => $operator_name,
				'shop_id' => $shop['id'],
				'shop_urp' => $shop_id,	//这个shop_id是接口传过来的URP编码
				'shop_name' => $shop['name'],
				'order_id' => $orderid,
				'is_recommend' => 0,
				'recommend_mobile' => '',
				'by_urp' => 1
			);
			$data['dateline'] = time();
			$model = M('pay_register_log');
			$model->add($data);
			//var_dump($model->getLastSql());die;
			
			$model = M('register_log');
			$model->add($data);
			
			//生成特权
			$issued_month = date('Y-m');
			$created = date('Y-m-d H:i:s');
			$data = array(
			  'uid' => $user['uid'],
				'mobile' => $uid,
				'operator' => $operator,
				'operator_name' => $operator_name,
				'shop_id' => $shop['id'],
				'shop_urp' => $shop_id,	//这个shop_id是接口传过来的URP编码
				'shop_name' => $shop['name'],
				'order_id' => $orderid,
				'privilege_times' => 12,
				'use_times' => 1,
				'left_times' => 11,
				'issued_month' => $issued_month,
				'created' => $created
			);
			$model = M('pay_privilege');
			$model->add($data);
			//生成第一次特权礼品
			$data = array();
			$now = time();
			$data['gift_id'] = 1019;	//手机贴膜服务卡
			$data['uid'] = $user['uid'];
			$data['username'] = $user['username'];
			$data['num'] = 1;
			$data['last_gettime'] = mktime(23,59,59,date("m",$now),date("t",$now),date("Y",$now));
			$data['source_type'] = 5; //获取来源，1兑换 2升级赠送 3抽奖 4关怀 5特权
			$data['shop_id'] = $shop['id'];
			$data['dateline'] = $now;
			$id = M('gift_exchange')->add($data);
			
			
			$this->ajaxReturn(0, 'VIP会员确认成功');
			return;

		}
		else
		{
			$this->ajaxReturn(-8, '目前本接口仅支持HTTP POST方法');
			return;
		}
	}
	
	//忘记密码重置
	public function resetpw() {
		//dump($this->_post());exit;
		$mobile = $this->_post('mobile', 'trim');
		$code = $this->_post('code', 'trim');
		$newpw = $this->_post('newpw', 'trim');

		if(strlen($mobile)!=11){
			$this->ajaxReturn(-1, '无效的手机号码');
			return;
		}
	  
		$user = D('user')->where("mobile='".$mobile."' and mobile_verify=1")->find();
		if(!$user)
		{
			$this->ajaxReturn(-2, '此手机号未被用户绑定');
			return;
		}
		
		//file_put_contents("/www/jtd/Public/temp.log",var_export($_POST,true));
		$verify = D('verify_sms')->where("mobile='".$mobile."' and code=".$code)->order('dateline DESC')->find();
		//var_dump($verify);die;
		if(!$verify){
			$this->ajaxReturn(-3, '对不起，该验证码不存在或已过期');
			return;
		}

		$uid = $user['uid'];
		$flag = M('user')->where('uid='.$uid)->setField('password', md5($newpw));
		if ($flag === false) {
			$this->ajaxReturn(-4, '抱歉，系统维护中，请稍候');
			return;
		} else {
			$this->ajaxReturn(0, '密码重置成功');
		}
	}
	
	/**
     * 查询用户
     */
    public function checkuser() {
			//参数检测
			$uid = $this->_post('uid', 'trim');

			if (empty($uid)) {
				$this->ajaxReturn(-1, '查询会员卡号不能为空');
			}
			
			$user = D("User")->where("userno='".$uid."' or (mobile_verify=1 and mobile='".$uid."')")->find();
			if (!$user){
				$this->ajaxReturn(-2, '查询会员不存在');
			}
			$user_id = $user['uid'];
			$user_info = D("User_info")->where("uid=".$user_id)->find();
			$user_count = D("User_count")->where("uid=".$user_id)->find();

			$data = array();
			$data['username'] = $user['username'];
			$data['userno'] = $user['userno'];
			$data['mobile'] = $user['mobile'];
			$data['mobile_verify'] = $user['mobile_verify'];
			$data['email'] = $user['email'];
			$data['email_check'] = $user['email_check'];
			$data['is_vip'] = $user['is_pay'];
			$data['nickname'] = $user_info['nickname'];
			$data['realname'] = $user_info['realname'];
			$data['credit'] = $user_count['credit'];
			$data['experience'] = $user_count['experience'];
			
			$this->ajaxReturn(0, '查询成功',$data);
    }

}