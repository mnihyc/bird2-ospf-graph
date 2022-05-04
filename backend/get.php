<?php
include_once 'db.php';
global $db;

if($_GET['t'] !== VER_GET_TOK)
	diemsg('invalid token', -1);
$rid = trim($_GET['rid']);
if(!empty($rid))
{
	if(!filter_var($rid, FILTER_VALIDATE_IP))
		diemsg('invalid parameter');
	$arr = $db->select('ospf', '*', ['router_id'=> $rid]);
}
else
	$arr = $db->select('ospf', '*');

if($_GET['m'] === 'raw')
	diemsg($arr, 1);
else if($_GET['m'] === 'par')
{
	$res = array();
	foreach($arr as $ta)
	{
		$tr = array();
		$tr['name'] = $ta['name'];
		$tr['name_alias'] = $ta['name_alias'];
		$tr['real_ip'] = $ta['real_ip'];
		$tr['router_id'] = $ta['router_id'];
		$tr['last_updated'] = $ta['last_updated'];
		
		$oc = $ta['ospfconf'];
		$ars = explode("\n", $oc);
		foreach($ars as &$s)
			if(($pos=strpos($s, '#')) !== FALSE)
				$s = substr($s, 0, $pos);
		unset($s);
		$oc = implode("\n", $ars);
		$cmt = GetStrBetween($oc, '/*', '*/');
		foreach($cmt as $cmti)
			$oc = str_replace($cmti, '', $oc);
		if(($pos=strpos($oc, '/*')) !== FALSE)
			$oc = substr($oc, 0, $pos);
		
		$st = explode('protocol ospf v3 ', $oc);
		array_shift($st);
		$intf4 = $intf6 = array();
		if(count($st)==0)
			diemsg('parse error');
		$ls = GetStrBetween($ta['linkstate'], '{', '}');
		if(count($ls) != count($st))
			diemsg('linkstate ospfconf mismatch');
		foreach(array_combine($st, $ls) as $sti => $lsi)
		{
			$ati = GetStrBetween($sti, 'ipv4', '{');
			if(count($ati) && trim($ati[0])=='')
				$intf4[] = [GetListIntf($sti), $lsi];
			$ati = GetStrBetween($sti, 'ipv6', '{');
			if(count($ati) && trim($ati[0])=='')
				$intf6[] = [GetListIntf($sti), $lsi];
		}
		
		$tr['intf4'] = GetDetailedIntf($ta['ipas'], $intf4, 'inet ');
		$tr['intf6'] = GetDetailedIntf($ta['ipas'], $intf6, 'inet6 ');
		$res[] = $tr;
	}
	diemsg($res, 1);
}
else
	diemsg('invalid method');

function GetListIntf($str)
{
	$intf = array();
	$dst = GetStrBetween($str, 'interfac', '};');
	foreach($dst as $dsti)
	{
		$dstip = GetStrBetween($dsti, 'e ', '{');
		$dstip = explode(',', $dstip[0]);
		foreach($dstip as $dstin)
		{
			$dstin = trim(str_replace('"', '', $dstin));
			if(strpos($dsti, 'type ptp;') !== FALSE)
			{
				$dstic = GetStrBetween($dsti, 'cost ', ';');
				$dstic = intval($dstic[0]);
				$intf[] = [$dstin, $dstic];
			}
			else
				$intf[] = $dstin;
		}
	}
	return $intf;
}

function GetDetailedIntf($str, $intfs, $ipstr)
{
	$ret = array();
	$arr = explode('link/', $str);
	foreach($intfs as $intfi)
	{
		$ls = str_replace("\t", ' ', $intfi[1]);
		$lsarr = explode("\n", $ls);
		foreach($intfi[0] as $intf)
		{
			$i = 0;
rept:
			$name = is_array($intf) ? $intf[0] : $intf;
			if(!preg_match('/^[a-z0-9_\-\*]*$/', $name))
				diemsg('parse error');
			$name = str_replace('*', '[a-z0-9_\-]*', $name);
			for(; $i < count($arr); $i++)
				if(preg_match('/: ('.$name.')[@|:]/i', $arr[$i], $mats))
				{
					$name = $mats[1];
					break;
				}
			if((++$i) >= count($arr))
				continue;
			$ths = array();
			$ths['name'] = $name;
			if(is_array($intf))
			{
				$ths['cost'] = $intf[1];
				if(($pos=strpos($name, 'peer')) !== FALSE)
				{
					$ths['peer_name'] = substr($name, $pos+4);
					$ths['peer_type'] = substr($name, 0, $pos);
				}
				else
					diemsg('invalid peer_name '.$name);
				$tar = GetStrBetween($arr[$i], '0.0.0.0 peer ', "\n");
				if(!empty($tar))
					$ths['peer_real_ip'] = $tar[0];
				$k = 0;
				for(; $k<count($lsarr); $k++)
					if(strpos($lsarr[$k], ' '.$name.' ') !== FALSE)
						break;
				if($k>=count($lsarr) || strpos($lsarr[$k], 'Full/PtP')===FALSE)
					$ths['link_down'] = true;
			}
			$ths['ip'] = GetStrBetween($arr[$i], $ipstr, ' ');
			$ret[] = $ths;
			goto rept;
		}
	}
	return $ret;
}

?>