<?php
 /*
 * 74cms SMS
 * ============================================================================
 * 版权所有: 骑士网络，并保留所有权利。
 * 网站地址: http://www.74cms.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
*/
define('IN_QISHI', true);
require_once(dirname(__FILE__).'/../include/common.inc.php');
require_once(QISHI_ROOT_PATH.'include/mysql.class.php');
$db = new mysql($dbhost,$dbuser,$dbpass,$dbname);
$act = !empty($_REQUEST['act']) ? trim($_REQUEST['act']) : ''; 
$mobile=trim($_POST['mobile']);
$send_key=trim($_POST['send_key']);
if (empty($send_key) || $send_key<>$_SESSION['send_key'])
{
exit("效验码错误");
}
$SMSconfig=get_cache('sms_config');
if ($SMSconfig['open']!="1")
{
exit("短信模块处于关闭状态");
}
if ($act=="send_code")
{
		if (empty($mobile) || !preg_match("/^(13|15|18)\d{9}$/",$mobile))
		{
		exit("手机号错误");
		}
		$sql = "select * from ".table('members')." where mobile = '{$mobile}' LIMIT 1";
		$userinfo=$db->getone($sql);
		if ($userinfo && $userinfo['uid']<>$_SESSION['uid'])
		{
		exit("手机号已经存在！请填写其他手机号码");
		}
		elseif(!empty($userinfo['mobile']) && $userinfo['mobile_audit']=="1" && $userinfo['mobile']==$mobile)
		{
		exit("你的手机号 {$mobile} 已经通过验证！");
		}
		else
		{
			if ($_SESSION['send_time'] && (time()-$_SESSION['send_time'])<60)
			{
			exit("请60秒后再进行验证！");
			}
			$rand=mt_rand(100000, 999999);	
			$r=send_sms($mobile,"感谢您使用{$_CFG['site_name']}手机认证,验证码为:{$rand}");
			if ($r=="success")
			{
			$_SESSION['mobile_rand']=$rand;
			$_SESSION['send_time']=time();
			$_SESSION['verify_mobile']=$mobile;
			exit("success");
			}
			else
			{
			exit("SMS配置出错，请联系网站管理员");
			}
		} 
}
elseif ($act=="verify_code")
{
	$verifycode=trim($_POST['verifycode']);
	if (empty($verifycode) || empty($_SESSION['mobile_rand']) || $verifycode<>$_SESSION['mobile_rand'])
	{
		exit("验证码错误");
	}
	else
	{
			$uid=intval($_SESSION['uid']);
			if (empty($uid))
			{
				exit("系统错误，UID丢失！");
			}
			else
			{
					$setsqlarr['mobile']=$_SESSION['verify_mobile'];
					$setsqlarr['mobile_audit']=1;
					updatetable(table('members'),$setsqlarr," uid='{$uid}'");
					unset($setsqlarr,$_SESSION['verify_mobile'],$_SESSION['mobile_rand']);
					if ($_SESSION['utype']=="1" && $_CFG['operation_mode']=='1')
					{
						$rule=get_cache('points_rule');
						if ($rule['verifymobile']['value']>0)
						{
							$info=$db->getone("SELECT uid FROM ".table('members_handsel')." WHERE uid ='{$_SESSION['uid']}' AND htype='verifymobile' LIMIT 1");
							if(empty($info))
							{
							$time=time();			
							$db->query("INSERT INTO ".table('members_handsel')." (uid,htype,addtime) VALUES ('{$_SESSION['uid']}', 'verifymobile','{$time}')");
							require_once(QISHI_ROOT_PATH.'include/fun_comapny.php');
							report_deal($_SESSION['uid'],$rule['verifymobile']['type'],$rule['verifymobile']['value']);
							$user_points=get_user_points($_SESSION['uid']);
							$operator=$rule['verifymobile']['type']=="1"?"+":"-";
							$_SESSION['handsel_verifymobile']=$_CFG['points_byname'].$operator.$rule['verifymobile']['value'];
							write_memberslog($_SESSION['uid'],1,9001,$_SESSION['username']," 手机通过验证，{$_CFG['points_byname']}({$operator}{$rule['verifymobile']['value']})，(剩余:{$user_points})");
							}
						}
					} 
					exit("success");
			}
	}
}
?>