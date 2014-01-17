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

$user = isset($_GET['user']) ? " AND p.printername = 'beboer' AND u.username = ".$dbh->quote($_GET['user']) : '';

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
  WHERE j.jobdate::date >= date '$from' AND j.jobdate::date <= date '$to'$user
  GROUP BY u.username, p.printername, jobyear, jobmonth
  ORDER BY jobyear, jobmonth, p.printername, u.username";

$sth = $dbh->prepare($sql);
$sth->execute();

$list = array();
foreach ($sth as $row) {
	$list[$row['printername']][get_name($row['username'])][] = $row;
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

<h1>Utskriftsoversikt for fakturering</h1>
<p style="color: #FF0000">Tidsperiode:<br />
Fra: '.$from.'<br />
Til: '.$to.'</p>';

foreach ($list as $printer => $users) {
	$printer_sum_prints = 0;
	$printer_sum_pages = 0;

	ksort($users);

	echo '
<div class="printergroup">
<h2>Printer/faktureres: <u'.(!in_array($printer, $no_faktura) && $printer != "beboer" ? ' style="color: #FF0000"' : '').'>'.htmlspecialchars($printer).'</u></h2>'.(isset($texts[$printer]) ? '
<p style="color: #FF0000">'.$texts[$printer].'</p>' : '').'
<table border="1" cellpadding="2" cellspacing="0">
	<thead>
		<tr>
			<th rowspan="2">Navn</th>
			<th rowspan="2">Beløp</th>
			<th colspan="3">Periodisk oversikt</th>
		</tr>
		<tr>
			<td>Måned</td>
			<td>Antall jobber</td>
			<td>Antall sider</td>
		</tr>';

	foreach ($users as $user => $printlist) {
		$user_sum_prints = 0;
		$user_sum_pages = 0;

		$a = count($printlist);

		$n = 0;
		if (!in_array($printer, $no_faktura)) { foreach ($printlist as $month) $n += $month['sum_jobsize']; }
		$n = number_format($n*$amount, 2, ",", " ");

		echo '
		<tr>
			<td rowspan="'.$a.'"'.($printer == "beboer" ? ' style="font-size: 115%; color: #FF0000"' : '').'>'.htmlspecialchars($user).'</td>
			<td rowspan="'.$a.'" style="text-align: right; font-weight: bold; font-size: 115%'.($printer == "beboer" ? '; color: #FF0000' : '').'">'.$n.'</td>';

		$f = true;
		foreach ($printlist as $month) {
			if (!$f) {
				echo '
		</tr>
		<tr>';
			}

			$user_sum_prints += $month['count_jobs'];
			$user_sum_pages += $month['sum_jobsize'];

			echo '
			<td>'.$month['jobyear'].'-'.$month['jobmonth'].'</td>
			<td style="text-align: right">'.$month['count_jobs'].'</td>
			<td style="text-align: right">'.$month['sum_jobsize'].'</td>';

			if ($f) {
				$f = false;
			}
		}

		echo '
		</tr>';

		$printer_sum_prints += $user_sum_prints;
		$printer_sum_pages += $user_sum_pages;
	}

	echo '
	</tbody>
</table>
</div>';

	$cash = in_array($printer, $no_faktura) ? 0 : $printer_sum_pages*$amount;
	$cash_alt = in_array($printer, $no_faktura) ? $printer_sum_pages*$amount : false;
	$sum_cash += $cash;

	echo '
<p>Totalt '.$printer_sum_prints.' utskriftsjobber på totalt '.$printer_sum_pages.' sider = <b style="font-size: 115%'.($printer != "beboer" && $cash_alt === false ? '; color: #FF0000' : '').'">kr '.number_format($cash, 2, ",", " ").'</b>'.($cash_alt !== false ? ' (ikke fakturert: kr '.number_format($cash_alt, 2, ",", " ").')' : '').'</p>';
}

echo '
<p><b>Totalt til fakturering: kr '.number_format($sum_cash, 2, ",", " ").'</b></p>
</body>
</html>';
