<?php

require "tools/config.php";

// enkel api for å hente data fra pykota

// begrens tilgang
$allow = array(
	"217.170.200.58", // blindern-studenterhjem.no
	"37.191.203.140", // webdev.hsw.no (midlertidig adresse)
	"37.191.201.59",  // darask-1301 (midlertidig adresse)
	gethostbyname("athene.foreningenbs.no."), // foreningenbs.no (midlertidig adresse)
);
if (!in_array($_SERVER['REMOTE_ADDR'], $allow)) die("Not authorized.");

$request = isset($_GET['method']) ? $_GET['method'] : null;

if ($request == "pykotalast")
{
	$p = new PrinterTools();
	$data = $p->getLastPrints(30);
	echo json_encode($data);
	return;
}

elseif ($request == "fakturere")
{
	if (!isset($_GET['from']) || !isset($_GET['to']))
	{
		die("Missing parameters.");
	}

	$from = $_GET['from'];
	$to = $_GET['to'];
	if (!preg_match("/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/", $from)) {
		die("Ugyldig 'fra' dato!");
	}
	if (!preg_match("/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/", $to)) {
		die("Ugyldig 'til' dato!");
	}

	$p = new PrinterTools();
	$data['prints'] = $p->getUsageData($from, $to);
	$data['texts'] = PrinterConfig::$texts;
	$data['no_faktura'] = PrinterConfig::$no_faktura;
	$data['from'] = $from;
	$data['to'] = $to;
	$data['daily'] = $p->getDailyUsageData($from, $to);
	$data['sections'] = PrinterConfig::$sections;
	$data['section_default'] = PrinterConfig::$section_default;
	$data['accounts'] = PrinterConfig::$accounts;

	echo json_encode($data);
	return;
}

