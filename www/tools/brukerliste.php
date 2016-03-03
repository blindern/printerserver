<?php

if (substr($_SERVER['REMOTE_ADDR'], 0, 6) != "37.191") die("Liste over brukere er kun tilgjengelig fra BS-nettet.");


$groups = explode(",", trim(file_get_contents("../groups")));
#array("beboer", "dugnadprinter", "ffvprinter", "ffhprinter", "fsprinter", "hytteprinter", "kollegietprinter", "ukaprinter", "lpadmin", "utflyttet");
sort($groups);


// koble til LDAP
$ad = ldap_connect("ldap://ldap.vpn.foreningenbs.no") or die("Kunne ikke koble til LDAP-database");

// hent ut info om alle brukere i systemet
$r = ldap_search($ad, "ou=Users,dc=foreningenbs,dc=no", "(uid=*)", array("uid", "cn", "mail"));
$e = ldap_get_entries($ad, $r);

$all_users = array();
for ($i = 0; $i < $e['count']; $i++) {
	$all_users[$e[$i]['uid'][0]] = $e[$i];
}

function get_name($user) {
	global $all_users;
	if (!isset($all_users[$user])) return 'Ukjent navn';
	return $all_users[$user]['cn'][0];
}

// hent brukere fra gruppene
$users = array();
$users_names = array();
foreach ($groups as $group) {
	// hent medlemmer av denne gruppen
	$res = shell_exec("getent group $group");
	$res = trim(preg_replace("/^.*:/", "", $res));
	if (!$res) continue;

	$res = explode(",", $res);
	foreach ($res as $user) {
		if ($user == "lp") continue;
		$users[$user][] = $group;
		if (!isset($users_names[$user])) $users_names[$user] = get_name($user);
	}
}

array_multisort($users_names, $users);

echo '
<!DOCTYPE html>
<html>
<head>
<title>Brukere</title>
</head>
<body>
<table border="1">
	<thead>
		<tr>
			<th>Brukernavn</th>
			<th>Navn</th>
			<th>E-post</th>
			<th>Grupper</th>
		</tr>
	</thead>
	<tbody>';

$users2 = array(); // vis folk som ikke er i beboer-gruppa til slutt
for ($x = 0; $x < 2; $x++) {
foreach ($users as $user => $groups) {
	if ($x == 0 && !in_array("beboer", $groups)) {
		$users2[$user] = $groups;
		continue;
	}
	
	$u = isset($all_users[$user]) ? $all_users[$user] : null;
	$cn = $u ? htmlspecialchars($u['cn'][0]) : '<b>Ukjent (ikke i LDAP)</b>';
	$mail = $u ? (isset($u['mail'][0]) ? htmlspecialchars($u['mail'][0]) : '<b>Ukjent</b>') : '<b>Ukjent</b>';
	
	if (isset($all_users[$user])) unset($all_users[$user]);
	
	echo '
		<tr>
			<td>'.htmlspecialchars($user).'</td>
			<td>'.$cn.'</td>
			<td>'.$mail.'</td>
			<td>'.implode(", ", array_map("htmlspecialchars", $groups)).'</td>
		</tr>';
}
	$users = $users2;
	echo '
		<tr>
			<td colspan="4">Andre brukere</td>
		</tr>';
}

// vis de brukerene som ikke allerde er listet opp
foreach ($all_users as $u) {
	$cn = $u['cn'][0];
	$mail = isset($u['mail'][0]) ? htmlspecialchars($u['mail'][0]) : '<b>Ukjent</b>';
	echo '
		<tr>
			<td>'.htmlspecialchars($u['uid'][0]).'</td>
			<td>'.htmlspecialchars($cn).'</td>
			<td>'.$mail.'</td>
			<td><b>Ukjent</b></td>
		</tr>';
}

echo '
	</tbody>
</table>
</body>
</html>';

