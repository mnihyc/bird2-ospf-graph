<?php
include_once 'db.php';
global $db;

if($_GET['t'] !== VER_UPD_TOK)
	diemsg('invalid token', -1);
$rid = trim($_GET['rid']);
if(!filter_var($rid, FILTER_VALIDATE_IP))
	diemsg('invalid parameter');
$ret = $db->select('ospf', '*', ['router_id'=> $rid]);
if(empty($ret))
	$db->insert('ospf', [
		'name'=> $rid,
		'name_alias'=> $rid,
		'real_ip'=> $_SERVER['REMOTE_ADDR'],
		'router_id'=> $rid,
		'last_updated'=> time()
	]);
$raw = file_get_contents('php://input');
$raw = str_replace("\r\n", "\n", $raw);
$ospfconf = $ipas = $netintf = $linkstate = null;
if($_GET['m'] === 'ospfconf')
	$ospfconf = $raw;
elseif($_GET['m'] === 'ipas')
	$ipas = $raw;
elseif($_GET['m'] === 'netintf')
	$netintf = $raw;
elseif($_GET['m'] === 'linkstate')
	$linkstate = $raw;
elseif($_GET['m'] === 'all')
{
	$arr = explode("\x01\x02", $raw);
	if(count($arr) != 4)
		diemsg('invalid parameter');
	$ospfconf = $arr[0];
	$ipas = $arr[1];
	$netintf = $arr[2];
	$linkstate = $arr[3];
}
else
	diemsg('unknown method');
if(!empty($ospfconf))
{
	$arr = GetStrBetween($ospfconf, 'protocol ospf v3 ', '{');
	if(count($arr) === 0)
		diemsg('invalid ospfconf');
	$name = $arr[count($arr)-1];
	$name = trim(str_replace("\n", "", $name));
	if(substr($name, 0, 4) !== 'ospf')
		diemsg('invalid ospfconf');
	$name = substr($name, 4, strlen($name) - 4);
	if(substr($name, -2) === 'v4')
		$name = substr($name, 0, strlen($name) - 2);
	$db->update('ospf', [
		'ospfconf'=> $ospfconf,
		'name'=> $name,
		'name_alias'=> $name,
		'last_updated'=> time()
	], ['router_id'=> $rid]);
}
if(!empty($ipas))
	$db->update('ospf', [
		'ipas'=> $ipas,
		'last_updated'=> time()
	], ['router_id'=> $rid]);
if(!empty($netintf))
	$db->update('ospf', [
		'netintf'=> $netintf,
		'last_updated'=> time()
	], ['router_id'=> $rid]);
if(!empty($linkstate))
	$db->update('ospf', [
		'linkstate'=> $linkstate,
		'last_updated'=> time()
	], ['router_id'=> $rid]);
diemsg('success', 1);

?>