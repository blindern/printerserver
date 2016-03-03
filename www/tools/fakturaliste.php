<?php

require "config.php";
$amount = PrinterConfig::getCost('2013-04-01'); // TODO: support price change

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

$user = isset($_GET['user']) ? $_GET['user'] : null;
$group = isset($_GET['group']) ? $_GET['group'] : null;

$p = new PrinterTools();
$data = $p->getUsageData($from, $to, $user, $group);

$userhelper = new UserTools();

function get_realname($username)
{
	global $userhelper;
	$realname = $userhelper->getRealName($username);
	if (!$realname)
	{
		$realname = "Ukjent navn - ".$username;
	}
	return $realname;
}

function fornum($num)
{
	return number_format($num, 2, ",", " ");
}

/*
$list = array();
foreach ($sth as $row) {
	

	$list[$row['printername']][$realname][] = $row;
}*/


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

foreach ($data as $printer) {
	$printername = $printer['printername'];
	$printer_sum_prints = 0;
	$printer_sum_pages = 0;
	$printer_sum_cash = 0;
	$printer_totaler = array();

	// sort by realname
	$names = array();
	foreach ($printer['users'] as $user)
	{
		$names[] = $user['username'];
	}
	$realnames = array_map("get_realname", $names);
	array_multisort($realnames, $printer['users']);

	echo '
<div class="printergroup">
<h2>Printer/faktureres: <u'.(!in_array($printername, PrinterConfig::$no_faktura) && $printername != "beboer" ? ' style="color: #FF0000"' : '').'>'.htmlspecialchars($printername).'</u></h2>'.(isset(PrinterConfig::$texts[$printername]) ? '
<p style="color: #FF0000">'.PrinterConfig::$texts[$printername].'</p>' : '').'
<table border="1" cellpadding="2" cellspacing="0">
	<thead>
		<tr>
			<th rowspan="2">Navn</th>
			<th rowspan="2">Beløp</th>
			<th colspan="5">Periodisk oversikt</th>
		</tr>
		<tr>
			<td>Måned</td>
			<td>Antall jobber</td>
			<td>Sidepris</td>
			<td>Antall sider</td>
			<td>Månedskostnad</td>
		</tr>';

	$i = 0;
	foreach ($printer['users'] as $user) {
		$realname = $realnames[$i++];

		$user_sum_prints = 0;
		$user_sum_pages = 0;

		$a = count($user['prints']);

		// calculate total cost for the user
		$n = 0;
		foreach ($user['prints'] as $month)
		{
			$printer_sum_cash += $month['sum_jobsize'] * $month['cost_each'];
			if (!in_array($printer, PrinterConfig::$no_faktura))
			{
				$n += $month['sum_jobsize'] * $month['cost_each'];
				@$printer_totaler[(string)$month['cost_each']] += $month['sum_jobsize'];
			}
		}
		$n = fornum($n);

		echo '
		<tr>
			<td rowspan="'.$a.'"'.($printer == "beboer" ? ' style="font-size: 115%; color: #FF0000"' : '').'>'.htmlspecialchars($realname).'</td>
			<td rowspan="'.$a.'" style="text-align: right; font-weight: bold; font-size: 115%'.($printer == "beboer" ? '; color: #FF0000' : '').'">'.$n.'</td>';

		$f = true;
		foreach ($user['prints'] as $month) {
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
			<td style="text-align: right">'.fornum($month['cost_each']).'</td>
			<td style="text-align: right">'.$month['sum_jobsize'].'</td>
			<td style="text-align: right">'.fornum($month['cost_each']*$month['sum_jobsize']).'</td>';

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

	if (in_array($printer, PrinterConfig::$no_faktura))
	{
		$cash_alt = $printer_sum_cash;
		$cash = 0;
	}
	else
	{
		$cash_alt = false;
		$sum_cash += $printer_sum_cash;
		$cash = $printer_sum_cash;
	}

	echo '
<p>Totalt '.$printer_sum_prints.' utskriftsjobber på totalt '.$printer_sum_pages.' sider 
    = <b style="font-size: 115%'.($printer != "beboer" && $cash_alt === false ? '; color: #FF0000' : '').'">
    kr '.number_format($cash, 2, ",", " ").'</b>

    '.($cash_alt !== false ? ' (ikke fakturert: kr '.number_format($cash_alt, 2, ",", " ").')' : '').'</p>';

	if (count($printer_totaler))
	{
		echo '
<p>';

		foreach ($printer_totaler as $price => $num)
		{
			echo '
	'.$num.' * kr '.fornum($price).' = kr '.fornum($price*$num).'<br />';
		}

		echo '
</p>';
	}
}

echo '
<p><b>Totalt til fakturering: kr '.number_format($sum_cash, 2, ",", " ").'</b></p>
</body>
</html>';
