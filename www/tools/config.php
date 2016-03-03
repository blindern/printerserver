<?php

class PrinterConfig {
	public static $texts = array(
		"beboer" => "Faktureres den enkelte beboer gjennom BS og utbetales av BS.",
		"dugnaden" => "Dekkes av BS.",
		"ffhost" => "Kostnadsføres direkte på festforeningen for høstsemesteret.",
		"ffvaar" => "Kostnadsføres direkte på festforeningen for vårsemesteret.",
		"fs" => "Kostnadsføres direkte på foreningsstyret.",
		"hyttestyret" => "Kostnadsføres direkte på hyttestyret.",
		"kollegiet" => "Dekkes av BS.",
		"printeroppmann" => "Ikke inntektsbringende.",
		"uka" => "Kostnadsføres direkte på UKA.",
		"velferden" => "Kostnadsføres direkte på velferden.",
	);

	public static $no_faktura = array(
		"printeroppmann",
	);

	public static $section_default = "fbs";

	public static $sections = array(
		"beboer" => array(
			"printers" => array("beboer"),
			"is_beboer" => true,
			"title" => "Faktureres enkeltbeboere",
			"description" => ""
		),
		"other" => array(
			"printers" => array("kollegiet", "dugnaden"),
			"is_beboer" => false,
			"title" => "Faktureres administrasjonen",
			"description" => "Faktureres/dekkes gjennom BS og utbetales av BS."
		),
		"fbs" => array(
			"printers" => array("ffhost", "ffvaar", "fs", "hyttestyret", "printeroppmann", "uka", "velferden"),
			"is_beboer" => false,
			"title" => "Kostnadsføres internt i FBS",
			"description" => "Føres som en kostnad direkte i foreningens regnskap samtidig med inntekten."
		)
	);

	public static $accounts = array(
		array(
			"printers" => null,
			"account" => 3261,
			"text" => "Avdeling/prosjekt: Foreningsstyret/printer",
		),
		array(
			"printers" => array("beboer", "kollegiet", "dugnaden"),
			"account" => "1500",
			"text" => "Kunde: Blindern Studenterhjem"
		),
		array(
			"printers" => array("ffhost"),
			"account" => "6820",
			"text" => "Avdeling/prosjekt: Festforening høst"
		),
		array(
			"printers" => array("ffvaar"),
			"account" => "6820",
			"text" => "Avdeling/prosjekt: Festforening vår"
		),
		array(
			"printers" => array("fs"),
			"account" => "6820",
			"text" => "Avdeling/prosjekt: Foreningsstyret"
		),
		array(
			"printers" => array("velferden"),
			"account" => "6820",
			"text" => "Avdeling/prosjekt: Foreningsstyret/Velferden"
		),
		array(
			"printers" => array("hyttestyret"),
			"account" => "6820",
			"text" => "Avdeling/prosjekt: Hyttestyret"
		),
		array(
			"printers" => array("uka"),
			"account" => "6820",
			"text" => "Avdeling/prosjekt: UKA"
		)
	);

	public static $amount = array(
		0 => 0.5,
		'2014-03' => 0.4

		//'2013-10' => 0.7,
		//'2014-01' => 0.4,

		// examples:
		#"2014-05" => 0.3, // from 2014-05-01
		#"2014-06" => 0.5  // from 2014-06-01

		// list must be ordered by date
		// only works by months
	);

	/**
	 * Get amount for a specified month
	 *
	 * @param string eg. '2014-01'
	 */
	public static function getCost($month_date)
	{
		static $cache;
		list($year, $month) = explode("-", $month_date);

		if (isset($cache[$month_date])) return $cache[$month_date];

		$last_amount = 0;
		foreach (self::$amount as $start => $amount)
		{
			if ($start == 0)
			{
				$last_amount = $amount;
			}

			else
			{
				list($check_year, $check_month) = explode("-", $start);
				if ($check_year < $year || ($check_year == $year && $check_month <= $month))
				{
					$last_amount = $amount;
				}
			}
		}

		$cache[$month_date] = $last_amount;
		return $last_amount;
	}
}

class PrinterTools {
	/**
	 * Connection to psql
	 */
	public $conn;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->conn = new PDO("pgsql:host=localhost;dbname=pykota", "pykotaadmin", "readwritepw");
		$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/**
	 * Get list of last prints
	 *
	 * @param int Number of prints to show
	 * @return array
	 */
	public function getLastPrints($num)
	{
		$num = (int) $num;

		// hent siste 30 stk utskrifter
		$sql = "
		  SELECT j.jobsize, j.jobdate, LOWER(u.username) username, p.printername
		  FROM jobhistory j
		    JOIN users u ON j.userid = u.id
		    JOIN printers p ON j.printerid = p.id
		  ORDER BY j.jobdate DESC
		  LIMIT $num";
		$last = $this->conn->prepare($sql);
		$last->execute();

		return $last->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Get list of usage
	 *
	 * @param string Date from (must be valid)
	 * @param stirng Date to (must be valid)
	 * @return array
	 */
	public function getUsageData($from, $to, $username = null, $group = null)
	{
		$extra = '';
		$prepares = array();
		if ($username)
		{
			$extra .= ' AND u.username = ?';
			$prepares[] = $username;
		}
		if ($group)
		{
			$extra .= ' AND p.printername = ?';
			$prepares[] = $group;
		}

		$sql = "
		  SELECT to_char(j.jobdate, 'YYYY') jobyear, to_char(j.jobdate, 'MM') jobmonth,
		         COUNT(j.id) count_jobs, SUM(j.jobsize) sum_jobsize, MAX(j.jobdate) last_jobdate,
		         LOWER(u.username) username,
		         p.printername
		  FROM jobhistory j
		       JOIN users u ON j.userid = u.id
		       JOIN printers p ON j.printerid = p.id
		  WHERE j.jobdate::date >= date '$from' AND j.jobdate::date <= date '$to'$extra
		  GROUP BY LOWER(u.username), p.printername, jobyear, jobmonth
		  ORDER BY jobyear, jobmonth, p.printername, LOWER(u.username)";

		$sth = $this->conn->prepare($sql);
		$sth->execute($prepares);

		$list = array();
		$d = $sth->fetchAll(PDO::FETCH_ASSOC);
		foreach ($d as $row) {
			$row['cost_each'] = PrinterConfig::getCost($row['jobyear'].'-'.$row['jobmonth']);
			$row['username'] = strtolower($row['username']);
			$list[$row['printername']][$row['username']][] = $row;
		}

		/*
		data:
			[printername]
			  [username]
			    []
			      jobyear
			      jobmonth
			      count_jobs
			      sum_jobsize
			      last_jobdate
			      cost_each
		*/

		// set up correct array
		/*
		data:
			[]
				printername
				users
					[]
						username
						prints
							[]
								jobyear
								...

		*/
		$n = array();
		foreach ($list as $printername => $users)
		{
			$x_users = array();
			foreach ($users as $username => $prints)
			{
				$x_users[] = array(
					"username" => strtolower($username),
					"prints" => $prints
				);
			}
			$n[] = array(
				"printername" => $printername,
				"users" => $x_users
			);
		}

		return $n;
	}

	/**
	 * Get daily usage numbers
	 *
	 * @param string Date from (must be valid)
	 * @param stirng Date to (must be valid)
	 * @return array
	 */
	public function getDailyUsageData($from, $to, $username = null, $group = null)
	{
		$extra = '';
		$prepares = array();
		if ($username)
		{
			$extra .= ' AND u.username = ?';
			$prepares[] = $username;
		}
		if ($group)
		{
			$extra .= ' AND p.printername = ?';
			$prepares[] = $group;
		}

		$sql = "
		  SELECT to_char(j.jobdate, 'YYYY-MM-DD') jobday,
		         COUNT(j.id) count_jobs, SUM(j.jobsize) sum_jobsize
		  FROM jobhistory j
		  WHERE j.jobdate::date >= date '$from' AND j.jobdate::date <= date '$to'$extra
		  GROUP BY jobday
		  ORDER BY jobday";

		$sth = $this->conn->prepare($sql);
		$sth->execute($prepares);

		$list = array();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

}

class UserTools {
	/**
	 * LDAP-connection
	 */
	public $ldap;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// koble til LDAP
		$this->ldap = ldap_connect("ldap://ldap.vpn.foreningenbs.no") or die("Kunne ikke koble til LDAP-database");
	}

	/**
	 * Get name of user
	 *
	 * @param string username
	 * @return string name|null
	 */
	public function getRealName($username)
	{
		$r = ldap_search($this->ldap, "ou=Users,dc=foreningenbs,dc=no", "(uid=$username)", array("cn"));
		$e = ldap_get_entries($this->ldap, $r);

		if ($e['count'] == 0) return null;
		return $e[0]['cn'][0];
	}
}
