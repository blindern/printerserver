<?php

// enkel api for å hente data fra pykota

// begrens tilgang
$allow = array(
	"217.170.200.58", // blindern-studenterhjem.no
	"37.191.203.140", // webdev.hsw.no (midlertidig adresse)
);
if (!in_array($_SERVER['REMOTE_ADDR'], $allow)) die("Not authorized.");


$request = isset($_GET['method']) ? $_GET['method'] : null;

$dbh = new PDO("pgsql:host=localhost;dbname=pykota", "pykotaadmin", "readwritepw");
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($request == "pykotalast")
{
	// hent siste 30 stk utskrifter
	$sql = "
	  SELECT j.jobsize, j.jobdate, u.username, p.printername
	  FROM jobhistory j
	    JOIN users u ON j.userid = u.id
	    JOIN printers p ON j.printerid = p.id
	  ORDER BY j.jobdate DESC
	  LIMIT 30";
	$last = $dbh->prepare($sql);
	$last->execute();

	$data = array();
	foreach ($last as $row)
	{
		$data[] = $row;
	}

	echo json_encode($data);
	return;
}


elseif ($request == "fakturere")
{
	if (!isset($_GET['from']) || !isset($_GET['to']))
	{
		die("Missing parameters.");
	}

	// kostnad per side
	$amount = 0.5;

	$from = $_GET['from'];
	$to = $_GET['to'];
	if (!preg_match("/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/", $from)) {
		die("Ugyldig 'fra' dato!");
	}
	if (!preg_match("/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/", $to)) {
		die("Ugyldig 'til' dato!");
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
		$list[$row['printername']][$row['username']][] = $row;
	}

	$texts = array(
		"printoppmann" => "Skal ikke faktureres!",
		"fs" => "Skal ikke faktureres!",
		"beboer" => "Faktureres per beboer!",
		"ffvaar" => "Faktureres festforeningen for vårsemesteret.",
		"ffhost" => "Faktureres festforeningen for høstsemesteret.",
		"velferden" => "Skal ikke faktureres!",
	);
	$no_faktura = array(
		"printoppmann",
		"fs",
		"velferden",
	);

	$data = array(
		"prints" => $list,
		"texts" => $texts,
		"no_faktura" => $no_faktura,
		"from" => $from,
		"to" => $to,
		"amount" => $amount
	);

	echo json_encode($data);
	return;
}

