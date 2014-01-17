<?php

// kostnad per side
$amount = 0.5;

$dbh = new PDO("pgsql:host=localhost;dbname=pykota", "pykotaadmin", "readwritepw");
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_GET['from'])) die("Mangler dato fra!");
if (!isset($_GET['to'])) die("Mangler dato til!");

$from = $_GET['from'];
$to = $_GET['to'];
if (!preg_match("/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/", $from)) {
	die("Ugyldig 'fra' dato!");
}
if (!preg_match("/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/", $to)) {
	die("Ugyldig 'til' dato!");
}

#  SELECT to_char(j.jobdate, 'YYYY') jobyear, to_char(j.jobdate, 'MM') jobmonth, COUNT(j.id) count_jobs, SUM(j.jobsize) sum_jobsize, MAX(j.jobdate) last_jobdate,

// koble til LDAP
$ad = ldap_connect("ldap://ldap.blindern-studenterhjem.no") or die("Kunne ikke koble til LDAP-database");

function get_name($uid) {
	global $ad;
	$r = ldap_search($ad, "ou=Users,dc=blindern-studenterhjem,dc=no", "(uid=$uid)", array("cn"));
	$e = ldap_get_entries($ad, $r);
	if ($e['count'] == 0) return "Ukjent navn - $uid";
	return $e[0]['cn'][0];
}

$sql = "
  SELECT to_char(j.jobdate, 'YYYY') jobyear, to_char(j.jobdate, 'MM') jobmonth, COUNT(j.id) count_jobs, SUM(j.jobsize) sum_jobsize, MAX(j.jobdate) last_jobdate,
         u.username,
         p.printername
  FROM jobhistory j
       JOIN users u ON j.userid = u.id
       JOIN printers p ON j.printerid = p.id
  WHERE j.jobdate::date >= date '$from' AND j.jobdate::date <= date '$to'
  GROUP BY u.username, p.printername, jobyear, jobmonth
  ORDER BY jobyear, jobmonth, p.printername, u.username";

$sth = $dbh->prepare($sql);
$sth->execute();

$list = array();
foreach ($sth as $row) {
	$group = $row['printername'] == "beboer" ? "beboer" : "gruppe";
	$list[$group][$row['printername']][get_name($row['username'])][] = $row;
}

$texts = array(
	"printoppmann" => "Skal ikke faktureres!",
	"fs" => "Skal ikke faktureres!",
	"beboer" => "Faktureres per beboer!",
	"ffvaar" => "Faktureres festforeningen for vårsemesteret.",
	"ffhost" => "Faktureres festforeningen for høstsemesteret."
);
$no_faktura = array(
	"printoppmann",
	"fs"
);

$sum_cash = 0;

echo '
<!DOCTYPE html>
<html>
<head>
<title>Utskriftsoversikt for fakturering</title>
<link type="text/css" rel="stylesheet" href="fakturaliste.css" />
</head>
<body>

<h1>Utskriftsoversikt for fakturering av printer</h1>
<p style="color: #FF0000">Tidsperiode:<br />
Fra: '.$from.'<br />
Til: '.$to.'</p>';

$out = '';

#foreach ($list as $printer => $users) {
foreach ($list as $group => $printers) {
	$is_group = $group != "beboer";
	if ($is_group) ksort($printers);
	$group_sum_pages = 0;
	$group_sum_cash = 0;
	$group_sum_cash_alt = 0;

	echo '
<div class="printergroup">';

	if (!$is_group) {
		$out .= '
<h2>Fakturering enkeltbeboere</h2>';
	} else {
		$out .= '
<h2>Fakturering grupper</h2>';
	}

	$out .= '
<table border="1" cellpadding="2" cellspacing="0">
	<thead>
		<tr>
			<th>Navn</th>
			<th>Beløp</th>'.($is_group ? '
			<th>Kommentar</th>' : '').'
		</tr>
	</thead>
	<tbody>';

	foreach ($printers as $printer => $users) {
		$printer_sum_pages = 0;
		ksort($users);

		foreach ($users as $user => $printlist) {
			$user_sum_pages = 0;
			foreach ($printlist as $month) {
				$user_sum_pages += $month['sum_jobsize'];
			}
			$n = number_format($user_sum_pages*$amount, 2, ",", " ");

			if (!$is_group) $out .= '
		<tr>
			<td>'.htmlspecialchars($user).'</td>
			<td style="text-align: right">'.$n.'</td>
		</tr>';

			$printer_sum_pages += $user_sum_pages;
		}

		$group_sum_pages += $printer_sum_pages;

		$cash = in_array($printer, $no_faktura) ? 0 : $printer_sum_pages*$amount;
		$cash_alt = in_array($printer, $no_faktura) ? $printer_sum_pages*$amount : false;
		$group_sum_cash += $cash;
		$group_sum_cash_alt += $cash_alt;

		if ($is_group) {
			$n = number_format($cash, 2, ",", " ");

			$t = array();
			if (isset($texts[$printer])) $t[] = $texts[$printer];
			if ($cash_alt !== false) $t[] = "(ikke fakturert: kr ".number_format($cash_alt, 2, ",", " ").")";
			if (count($t) == 0) $t[] = '&nbsp;';

			$out .= '
		<tr>
			<td>'.htmlspecialchars($printer).'</td>
			<td style="text-align: right">'.$n.'</td>
			<td>'.implode("<br />", $t).'</td>
		</tr>';
		}

	}

	$out .= '
	</tbody>
</table>
</div>';

	$out .= '
<p>Totalt '.$group_sum_pages.' sider = <b>kr '.number_format($group_sum_cash, 2, ",", " ").'</b>'.($group_sum_cash_alt ? ' (ikke fakturert: kr '.number_format($group_sum_cash_alt, 2, ",", " ").')' : '').'</p>';

	$sum_cash += $group_sum_cash;
}

echo '
<p><b>Totalt til fakturering: kr '.number_format($sum_cash, 2, ",", " ").'</b></p>'.$out.'
</body>
</html>';
