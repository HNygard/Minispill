<?php

/*
	Minispill1
	- Startet 13:00 26. mars 2007 av Hallvard NYgård
	- Baseres på tilfeldighet for å bygge opp cash
*/

require "pause.php";
if($pause && $pause_for_spill)
{
	echo $pause_msg;
	exit();
}

$leet_time = '13:37';
srand((float) microtime() * 10000000);

/*
if(date('Hi') < '1730')
{
	echo 'Restart av spillet 17.30';
	exit();
}*/

$enable_password = TRUE;
//$enable_password = FALSE;

$laaste_iper = array(
'bruker' => 'id'
);

$ban_ip = array();
if(in_array($_SERVER['REMOTE_ADDR'], $ban_ip))
{
	echo 'Du er utestengt.';
	exit();
}

// MySQL
$mysql_server	= 'localhost';
$mysql_db		= 'spill';
$mysql_username	= 'spill';
$mysql_passwd	= '546waslkdjalkj3q4';

// Koble til MySQL server
if(!$database = @mysql_connect($mysql_server, $mysql_username, $mysql_passwd))
{
	echo 'Kan ikke koble til MySQL tjeneren. Siden har stoppet på grunn av dette.'.chr(10);
	echo '<br><br>'.chr(10);
	echo '<br>MySQL error code '.mysql_errno().' - mysql_connect faild:<br>'.chr(10);
	echo mysql_error();
	exit();
}
if(!@mysql_select_db($mysql_db,$database))
{
	echo 'Kan ikke koble til MySQL tjeneren. Siden har stoppet på grunn av dette.'.chr(10);
	echo '<br><br>'.chr(10);
	echo '<br>MySQL error code '.mysql_errno().' - mysql_select_db faild:<br>'.chr(10);
	echo mysql_error();
	exit();
}

function dude_online()
{
	global $_SERVER, $bruker_id;
	
	// Sjekker om vi skal "insert" eller "update"
	if(mysql_num_rows(mysql_query("select * from `minispill1_online` where bruker_id = '".$bruker_id."' and online_ip = '".$_SERVER['REMOTE_ADDR']."'")))
	{
		// Vi kjører en "update"
		mysql_query("update `minispill1_online` set 
			online_time = '".time()."',
			online_user_agent = '".$_SERVER['HTTP_USER_AGENT']."'
			where 
				online_ip = '".$_SERVER['REMOTE_ADDR']."' and
				bruker_id = '".$bruker_id."'");
		
		if(mysql_affected_rows() > '0')
		{
			return TRUE;
		}
		elseif(mysql_affected_rows() == '0')
		{
			//echo 'Dudes_online update query oppdaterte ingen. Bad...';
			//return FALSE;
			// Gidde faensje
			return TRUE;
		}
		elseif(mysql_affected_rows() == '-1')
		{
			echo 'Dudes_online update query feilet. MySQL error: '.mysql_error();
			return FALSE;
		}
	}
	else
	{
		// Vi kjører "insert" pga session_iden er ikke i tabellen
		mysql_query("insert into `minispill1_online` 
			(`bruker_id`, `online_time_first`, `online_time`, `online_user_agent`, `online_ip`)
			VALUES (
			'".$bruker_id."',
			'".time()."',
			'".time()."',
			'".$_SERVER['HTTP_USER_AGENT']."',
			'".$_SERVER['REMOTE_ADDR']."'
			)");
		
		if(mysql_affected_rows() > '0')
		{
			return TRUE;
		}
		elseif(mysql_affected_rows() == '0')
		{
			echo 'Dudes_online insert query, ingen nye rader. Bad...';
			return FALSE;
		}
		elseif(mysql_affected_rows() == '-1')
		{
			echo 'Dudes_online insert query feilet. MySQL error: '.mysql_error();
			return FALSE;
		}
	}
}

function cash_printer ($cash)
{
	$antall_mellomrom		= floor(strlen($cash) / 3);
	$antall_siffer_forst	= strlen($cash) - ($antall_mellomrom * 3);
	
	if($antall_siffer_forst == '0')
		$antall_siffer_til_neste_mellomrom = '3';
	else
		$antall_siffer_til_neste_mellomrom = $antall_siffer_forst;
	
	$return = '';
	for ($i = 0; $i <= strlen($cash); $i++)
	{
		$antall_siffer_til_neste_mellomrom--;
		$return .= substr($cash, $i, 1);
		
		if($antall_siffer_til_neste_mellomrom == '0')
		{
			$return .= ' ';
			$antall_siffer_til_neste_mellomrom = '3';
		}
	}
	
	return $return;
}

if($enable_password)
{
	function login_check ()
	{
		global $_SESSION;
		
		if(!isset($_SESSION['bruker_id']) || $_SESSION['bruker_id'] == '' || $_SESSION['bruker_passord_md5'] == '')
		{
			return FALSE;
		}
		else
		{
			$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$_SESSION['bruker_id']."' and bruker_passord_md5 = '".$_SESSION['bruker_passord_md5']."' limit 1");
			if(mysql_num_rows($Q_bruker) > '0')
			{
				return TRUE;
			}
			else return FALSE;
		}
		return FALSE;	
	}
}

if(isset($_POST['bruker_navn']))
{
	if($_POST['bruker_navn'] != '' && $_POST['bruker_passord'] != '')
	{
		$_POST['bruker_navn']	= addslashes($_POST['bruker_navn']);
		$bruker_passord_md5		= md5($_POST['bruker_passord']);
		mysql_query("INSERT INTO `minispill1` ( `bruker_id` , `bruker_navn` , `bruker_passord_md5` , `bruker_cash` , `bruker_madeby_ip`) 
		VALUES ('', '".$_POST['bruker_navn']."', '".$bruker_passord_md5."', '1200', '".$_SERVER['REMOTE_ADDR']."');");
		
		header('Location: '.$_SERVER['PHP_SELF']);
		exit();
	}
}

if($enable_password)
{
	session_start();
	// Logger vi inn nå?
	if(isset($_GET['logout']))
	{
		session_destroy();
		header('Location: index.php');
		exit();
	}
	elseif (
		(isset($_GET['bruker_id']) && $_GET['bruker_id'] != '') && 
		(isset($_GET['bruker_passord']) && $_GET['bruker_passord'] != '')
	)
	{
		// Selve innloggingen
		$Q_bruker = mysql_query("select * from `minispill1`
		where `bruker_id` = '".((int)$_GET['bruker_id'])."'");
		if(
			mysql_num_rows($Q_bruker) && 
			mysql_result($Q_bruker, 0, 'bruker_passord_md5') == md5($_GET['bruker_passord'])
		)
		{
			$_SESSION['bruker_id'] = (int)$_GET['bruker_id'];
			$vis_innlogging = false;
		}
		else
			$vis_innlogging = true;
	
	}
	elseif(isset($_SESSION['bruker_id']))
		$vis_innlogging = false;
	elseif(isset($_GET['bruker_id']) && $_GET['bruker_id'] != '')
	{
		// Tast inn passord
		echo '<h2>Tast passord:</h2>';
		echo '<form method="get">';
		echo '<input type="hidden" name="bruker_id" value="'.(int)$_GET['bruker_id'].'">';
		echo '<input type="text" name="bruker_passord">';
		echo '<input type="submit" value="Jawoll">';
		echo '</form>';
		exit();
	}
	else
		$vis_innlogging = true;
}
else
{
	if(!isset($_GET['bruker_id']) || !is_numeric($_GET['bruker_id']))
		$vis_innlogging = TRUE;
	else
		$vis_innlogging = FALSE;
}

if($vis_innlogging)
{
	// Valg av bruker
	
	echo '<h1>Velg bruker</h1>'.chr(10);
	$Q_brukere = mysql_query("select * from `minispill1` order by bruker_cash desc");
	while($R_bruker = mysql_fetch_assoc($Q_brukere))
	{
		echo '<b>'.$R_bruker['bruker_cash'].'</b> <a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$R_bruker['bruker_id'].'">'.$R_bruker['bruker_navn'].'</a><br>'.chr(10);
	}
	
	echo '<h2>Opprett ny</h2>'.chR(10);
	echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'">'.chr(10);
	echo '<input type="text" name="bruker_navn"> - Brukernavn<br>'.chr(10);
	echo '<input type="password" name="bruker_passord"> - Passord<br>'.chr(10);
	echo '<input type="submit" value="Lag bruker">'.chr(10);
	exit();
}

if($enable_password)
{
	$Q_bruker = mysql_query("select * from `minispill1` where `bruker_id` = '".$_SESSION['bruker_id']."'");
	if(!mysql_num_rows($Q_bruker))
	{
		echo 'Finner ikke bruker';
		exit();
	}
}
else
{
	$Q_bruker = mysql_query("select * from `minispill1` where `bruker_id` = '".$_GET['bruker_id']."'");
	if(!mysql_num_rows($Q_bruker))
	{
		echo 'Finner ikke bruker';
		exit();
	}
}

$bruker_id			= mysql_result($Q_bruker, '0', 'bruker_id');
$bruker_navn		= mysql_result($Q_bruker, '0', 'bruker_navn');
$bruker_cash		= mysql_result($Q_bruker, '0', 'bruker_cash');
$bruker_fyll_paa	= mysql_result($Q_bruker, '0', 'bruker_fyll_paa');
$bruker_fyll_paa2	= mysql_result($Q_bruker, '0', 'bruker_fyll_paa2');

if(array_key_exists($_SERVER['REMOTE_ADDR'], $laaste_iper))
{
	if($laaste_iper[$_SERVER['REMOTE_ADDR']] != $bruker_id)
	{
		echo 'Ikke din bruker...';
		exit();
	}
}


if(!dude_online())
{
	exit();
}

$mulige = array();
$mulige[1] = 1;
$mulige[2] = 2;
$mulige[3] = 3;
$mulige[4] = 4;
$mulige[5] = 5;
$mulige[6] = 6;

// Fyll på penger 1
if(isset($_GET['fyll_paa']))
{
	if((time() - $bruker_fyll_paa) > 60)
	{
		$ny_bruker_cash = $bruker_cash + 1200;
		mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."', `bruker_fyll_paa` = '".time()."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
		$bruker_cash = $ny_bruker_cash;
		$bruker_fyll_paa = time();
	}
}

// Fyll på penger 2
if(isset($_GET['fyll_paa2']))
{
	$ny_bruker_cash = $bruker_cash + 10;
	mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."', `bruker_fyll_paa` = '".time()."', `bruker_fyll_paa2` = '".time()."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
	$bruker_cash = $ny_bruker_cash;
	$bruker_fyll_paa = time();
}

// Fyll på penger 3
if(isset($_GET['fyll_paa3']) && date('H:i') == $leet_time)
{
	if((time() - $bruker_fyll_paa3) > 60)
	{
		$ny_bruker_cash = $bruker_cash + 12000;
		mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."', `bruker_fyll_paa3` = '".time()."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
		$bruker_cash = $ny_bruker_cash;
		$bruker_fyll_paa = time();
	}
}

// Opprett duell
if(isset($_POST['duell_start']))
{
	// Starter duell
	if($_POST['duell_mot'] == '0')
	{
		echo 'Ingen motstander valgt.';
		exit();
	}
	elseif(!is_numeric($_POST['duell_mot']))
	{
		echo 'Finner ikke motstanderen.';
		exit();
	}
	elseif(!is_numeric($_POST['duell_innsats']) || $_POST['duell_innsats'] <= 0)
	{
		echo 'Ingen innstats?';
		exit();
	}
	else
	{
		$Q_motstander = mysql_query("select * from `minispill1` where bruker_id = '".$_POST['duell_mot']."'");
		if(!mysql_num_rows($Q_motstander))
		{
			echo 'Finner ikke motstanderen.';
			exit();
		}
		else
		{
			if($_POST['duell_innsats'] > $bruker_cash)
				$_POST['duell_innsats'] = $bruker_cash;
			
			mysql_query("INSERT INTO `minispill1_duell` 
			       ( `duell_id` , `duell_tid` , `duell_tid_respons` , `duell_respons` , `duell_starter` , `duell_mot` ,
				   `duell_innsats` , `duell_utfall` , `duell_vinner` , `duell_tipping_starter` , `duell_tipping_mot` )
			VALUES (
				'0',
				'".time()."',
				'0',
				'2',
				'".$bruker_id."',
				'".$_POST['duell_mot']."',
				'".$_POST['duell_innsats']."',
				'',
				'0',
				'".$_POST['duell_sats_paa']."',
				''
			);");
			$duell_id = mysql_insert_id();
			
			// Oppdaterer pengebeholdning
			$ny_bruker_cash = $bruker_cash - $_POST['duell_innsats'];
			mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
			$bruker_cash = $ny_bruker_cash;
			
			header('Location: '.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&vis_duell=1&duell_id='.$duell_id);
			exit();
		}
	}
}

// Aksepter duell
elseif(isset($_GET['aksepter_duell']))
{
	// Aksepterer og gjennomfører duell!
	
	if(!is_numeric($_GET['duell_id']))
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	$Q_duell = mysql_query("select * from `minispill1_duell` where duell_id = '".$_GET['duell_id']."' limit 1");
	if(!mysql_num_rows($Q_duell))
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	$duell_id				= mysql_result($Q_duell, '0', 'duell_id');
	$duell_tid				= mysql_result($Q_duell, '0', 'duell_tid');
	$duell_tid_respons		= mysql_result($Q_duell, '0', 'duell_tid_respons');
	$duell_respons			= mysql_result($Q_duell, '0', 'duell_respons');
	$duell_starter			= mysql_result($Q_duell, '0', 'duell_starter');
	$duell_mot				= mysql_result($Q_duell, '0', 'duell_mot');
	$duell_innsats			= mysql_result($Q_duell, '0', 'duell_innsats');
	$duell_utfall			= mysql_result($Q_duell, '0', 'duell_utfall');
	$duell_vinner			= mysql_result($Q_duell, '0', 'duell_vinner');
	$duell_tipping_starter	= mysql_result($Q_duell, '0', 'duell_tipping_starter');
	$duell_tipping_mot		= mysql_result($Q_duell, '0', 'duell_tipping_mot');
	
	if(!is_array($duell_utfall))
		$duell_utfall = array();
	
	
	if($duell_mot != $bruker_id)
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	if($duell_respons != '2')
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	// Setter tall for duell_tipping_mot
	if(!in_array($_POST['duell_sats_paa'], $mulige))
	{
		echo 'Feil med tallet du oppgav at du ville satse på.';
		exit();
	}
	$duell_tipping_mot = $_POST['duell_sats_paa'];
	
	// Oppdaterer pengebeholdning
	if($duell_innsats > $bruker_cash)
	{
		echo 'Du har ikke råd.';
		exit();
	}
	
	$ny_bruker_cash = $bruker_cash - $duell_innsats;
	mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
	
	// Henter tall
	$duell_vinner = '';
	$duell_utfall = array();
	while($duell_vinner == '')
	{
		$vinnertall		= array_rand($mulige);
		$duell_utfall[]	= $vinnertall;
		
		if($vinnertall == $duell_tipping_starter)
		{
			//$duell_taper	= $duell_mot;
			$duell_vinner	= $duell_starter;
		}
		elseif($vinnertall == $duell_tipping_mot)
		{
			//$duell_taper	= $duell_starter;
			$duell_vinner	= $duell_mot;
		}
	}
	
	$duell_utfall = serialize($duell_utfall);
	
	mysql_query("UPDATE `minispill1_duell` SET 
		`duell_tid_respons`	= '".time()."',
		`duell_respons`		= '1',
		`duell_vinner`		= '".$duell_vinner."',
		`duell_utfall`		= '".$duell_utfall."',
		`duell_tipping_mot`	= '".$duell_tipping_mot."'
	WHERE `duell_id` = '".$duell_id."' LIMIT 1 ;");
	
	// Oppdaterer pengebeholdning
	$Q_vinner = mysql_query("select * from `minispill1` where bruker_id = '".$duell_vinner."'");
	$ny_bruker_cash = mysql_result($Q_vinner, '0', 'bruker_cash') + $duell_innsats + $duell_innsats;
	mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$duell_vinner."' LIMIT 1 ;");
	//$Q_taper = mysql_query("select * from `minispill1` where bruker_id = '".$duell_taper."'");
	//$ny_bruker_cash = mysql_result($Q_taper, '0', 'bruker_cash') + $duell_innsats;
	//mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$duell_vinner."' LIMIT 1 ;");
	
	header('Location: '.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&vis_duell=1&duell_id='.$duell_id);
	exit();
}

// Avlys duell
elseif(isset($_GET['ikke_aksepter_duell']))
{
	// Aksepterer ikke duell
	
	if(!is_numeric($_GET['duell_id']))
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	$Q_duell = mysql_query("select * from `minispill1_duell` where duell_id = '".$_GET['duell_id']."' limit 1");
	if(!mysql_num_rows($Q_duell))
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	$duell_id				= mysql_result($Q_duell, '0', 'duell_id');
	$duell_tid				= mysql_result($Q_duell, '0', 'duell_tid');
	$duell_tid_respons		= mysql_result($Q_duell, '0', 'duell_tid_respons');
	$duell_respons			= mysql_result($Q_duell, '0', 'duell_respons');
	$duell_starter			= mysql_result($Q_duell, '0', 'duell_starter');
	$duell_mot				= mysql_result($Q_duell, '0', 'duell_mot');
	$duell_innsats			= mysql_result($Q_duell, '0', 'duell_innsats');
	
	if($duell_mot != $bruker_id && $duell_starter != $bruker_id)
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	if($duell_respons != '2')
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	mysql_query("UPDATE `minispill1_duell` SET `duell_tid_respons` = '".time()."', `duell_respons` = '0' WHERE `duell_id` = '".$duell_id."' LIMIT 1 ;");
	
	// Oppdaterer pengebeholdning
	$Q_starter = mysql_query("select * from `minispill1` where bruker_id = '".$duell_starter."'");
	$ny_bruker_cash = mysql_result($Q_starter, '0', 'bruker_cash') + $duell_innsats;
	mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$duell_starter."' LIMIT 1 ;");
	
	header('Location: '.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&vis_duell=1&duell_id='.$duell_id);
	exit();
}

// Send melding
if(isset($_POST['send_melding']))
{
	// Starter duell
	if($_POST['bruker_til'] == '0')
	{
		echo 'Ingen valgt som mottaker.';
		exit();
	}
	elseif(!is_numeric($_POST['bruker_til']))
	{
		echo 'Finner ikke mottaker.';
		exit();
	}
	elseif($_POST['meldingen'] == '')
	{
		echo 'Ingen melding?';
		exit();
	}
	else
	{
		$Q_mottaker = mysql_query("select * from `minispill1` where bruker_id = '".$_POST['bruker_til']."'");
		if(!mysql_num_rows($Q_mottaker))
		{
			echo 'Finner ikke mottakeren.';
			exit();
		}
		else
		{
			$_POST['meldingen'] = addslashes(htmlspecialchars($_POST['meldingen'],ENT_QUOTES));
			
			mysql_query("INSERT INTO `minispill1_meldinger` ( 
				`melding_id` , `melding_tid` , 
				`bruker_fra` , `bruker_til` , 
				`bruker_til_lest` , `meldingen` )
			VALUES (
				'', 
				'".time()."', 
				'".$bruker_id."', 
				'".$_POST['bruker_til']."', 
				'0', 
				'".$_POST['meldingen']."'
				);");
			
			header('Location: '.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&melding=1');
			exit();
		}
	}
}

$Q_vinner = mysql_query("
SELECT
	bruker.bruker_navn as bruker_navn,
	bygg.bygg_navn as bygg_navn
FROM
	`minispill1` bruker LEFT JOIN
	(
		`minispill1_bygg_bruker` bygg_bruker RIGHT JOIN 
		`minispill1_bygg` bygg ON bygg_bruker.bygg_id = bygg.bygg_id
	)
	ON bygg_bruker.bruker_id = bruker.bruker_id

WHERE
	bygg_lagervinner = '1'
ORDER BY
	bygg_bruker.bygg_bruker_id
");

if(mysql_num_rows($Q_vinner)) {
	echo '<div style="color: red; font-size: 50px;">'.
	mysql_result($Q_vinner, 0, 'bruker_navn').' har vunnet!!1!!1one<br>'.
	' Hun bygget '.mysql_result($Q_vinner, 0, 'bygg_navn').
	'</div>';
}

echo '<link rel="stylesheet" type="text/css" href="spill.css" />
';

echo '<font size="2">';
echo 'Du har <b><span id="bruker_cash">'.cash_printer($bruker_cash).'</span></b>, '.date('H:i:s').'<br>';
echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'">Status</a> - ';
echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;sats_penger=1">Sats penger</a> - ';
echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;highscore_bet=1">Highscore</a> - ';
echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;bygg=1">Bygninger</a> - ';
echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;duell=1">Start duell</a> - ';
echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;siste_dueller=1">Siste dueller</a> - ';
echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;duell_stats=1">Duellstatistikk</a> - ';


if(mysql_num_rows(mysql_query("select melding_id from `minispill1_meldinger` where bruker_til = '".$bruker_id."' and bruker_til_lest = '0'")))
	echo '<a class="red" href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;melding=1">Innboks ('.mysql_num_rows(mysql_query("select melding_id from `minispill1_meldinger` where bruker_til = '".$bruker_id."' and bruker_til_lest = '0'")).')</a> ';
else
	echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;melding=1">Innboks</a> ';

echo ' - <a href="'.$_SERVER['PHP_SELF'].'?logout=1">Logg ut</a>';

echo '</font>';

if(date('H:i') == $leet_time)
	echo '<h1 style="color: red;">Hva er klokka?!?11!!one</h1>'.chr(10);

echo '<table>'.chr(10);
echo '<tr><td valign="top">'.chr(10).chr(10);

// Selve satsingen
if(isset($_GET['bruk_cash']) && isset($_GET['bruk_paa']))
{
	if(!is_numeric($_GET['bruk_cash']))
		$bruk_cash = 0;
	elseif($_GET['bruk_cash'] > $bruker_cash)
		$bruk_cash = $bruker_cash;
	else
		$bruk_cash = $_GET['bruk_cash'];
	
	if(!in_array($_GET['bruk_paa'], $mulige))
	{
		$bruk_cash	= 0;
		$bruk_paa	= 0;
	}
	else
	{
		$bruk_paa	= $_GET['bruk_paa'];
	}
	
	echo '<h1>Spill</h1>'.chr(10);
	echo 'Du bruker <b>'.$bruk_cash.'</b> og satser på '.$bruk_paa.'<br><br>'.chr(10);
	
	echo '<b>Tallet ble:</b><br>'.chr(10);
	$vinnertallet = array_rand($mulige);
	echo $vinnertallet.'<br><br>'.chr(10);
	
	if($vinnertallet == $bruk_paa)
	{
		$vinne_cash = ($bruk_cash * 3);
		echo '<h3>Du vant!!! :-)<br>Fortjeneste: '.$vinne_cash.'</h3>'.chr(10);
		$ny_bruker_cash = $bruker_cash + $vinne_cash;
		mysql_query("INSERT INTO `minispill1_highscore` 
			       ( `highscore_id` , `highscore_cash` , `bruker_id` , `highscore_kommentar` , `highscore_tid` )
			VALUES ('', $vinne_cash, $bruker_id, '', '".time()."');");
	}
	else
	{
		echo '<h3>Looooooser...<br>Du tapte '.$bruk_cash.'</h3>'.chr(10);
		$ny_bruker_cash = $bruker_cash - $bruk_cash;
	}
	
	mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
	$bruker_cash = $ny_bruker_cash;
	
	echo '<br><br>'.chr(10).chr(10);
}

// Utfordre til duell
elseif(isset($_GET['duell']))
{
	echo '<h1>Utfordre til duell</h1>'.chr(10);
	
	echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'">'.chr(10);
	echo '<b>Velg utfordrer:</b><br>'.chr(10);
	$duell_mot_satt = FALSE;
	echo '<select name="duell_mot">'.chr(10);
	$Q_brukere = mysql_query("select * from `minispill1` order by bruker_navn");
	while($R_bruker = mysql_fetch_assoc($Q_brukere))
	{
		echo ' <option value="'.$R_bruker['bruker_id'].'"';
		if(isset($_GET['duell_mot']) && $R_bruker['bruker_id'] == $_GET['duell_mot'])
		{
			echo ' selected="selected"';
			$duell_mot_satt = TRUE;
		}
		echo '>'.$R_bruker['bruker_navn'].'</option>'.chr(10);
	}
	echo ' <option';
	if(!$duell_mot_satt)
		echo ' selected="selected"';
	echo ' value="0">Ikke valgt</option>'.chr(10);
	echo '</select><br><br>'.chr(10);
	
	echo '<b>Velg innsats:</b><br>'.chr(10);
	echo '<input type="text" name="duell_innsats"><br><br>'.chr(10).chr(10);
	
	echo '<b>Hvilket tall satser du på?</b><br>'.chr(10);
	echo '<select name="duell_sats_paa">'.chr(10);
	echo ' <option>1</option>'.chr(10);
	echo ' <option>2</option>'.chr(10);
	echo ' <option>3</option>'.chr(10);
	echo ' <option>4</option>'.chr(10);
	echo ' <option>5</option>'.chr(10);
	echo ' <option>6</option>'.chr(10);
	echo '</select><br><br>'.chr(10).chr(10);
	
	echo '<input type="submit" name="duell_start" value="Utfordre">'.chr(10);
	
	echo '</form>'.chr(10);
	
}

// Vis duell
elseif(isset($_GET['vis_duell']))
{
	if(!is_numeric($_GET['duell_id']))
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	$Q_duell = mysql_query("select * from `minispill1_duell` where duell_id = '".$_GET['duell_id']."' limit 1");
	
	if(!mysql_num_rows($Q_duell))
	{
		echo 'Finner ikke duellen.';
		exit();
	}
	
	
	$duell_id				= mysql_result($Q_duell, '0', 'duell_id');
	$duell_tid				= mysql_result($Q_duell, '0', 'duell_tid');
	$duell_tid_respons		= mysql_result($Q_duell, '0', 'duell_tid_respons');
	$duell_respons			= mysql_result($Q_duell, '0', 'duell_respons');
	$duell_starter			= mysql_result($Q_duell, '0', 'duell_starter');
	$duell_mot				= mysql_result($Q_duell, '0', 'duell_mot');
	$duell_innsats			= mysql_result($Q_duell, '0', 'duell_innsats');
	$duell_utfall			= unserialize(mysql_result($Q_duell, '0', 'duell_utfall'));
	$duell_vinner			= mysql_result($Q_duell, '0', 'duell_vinner');
	$duell_tipping_starter	= mysql_result($Q_duell, '0', 'duell_tipping_starter');
	$duell_tipping_mot		= mysql_result($Q_duell, '0', 'duell_tipping_mot');
	
	if(!is_array($duell_utfall))
		$duell_utfall = array();
	
	// Henter motstanderne
	$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$duell_starter."'");
	$bruker_navn1 = mysql_result($Q_bruker, '0', 'bruker_navn');
	$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$duell_mot."'");
	$bruker_navn2 = mysql_result($Q_bruker, '0', 'bruker_navn');
	
	
	echo '<h1>Duell '.$duell_id.'</h1>';
	echo 'Duell mellom <b>'.$bruker_navn1.'</b> og <b>'.$bruker_navn2.'</b>.<br>'.chr(10);
	echo 'Utfordring sendt <b>'.date('H:i:s d-m-Y', $duell_tid).'</b>.<br><br>'.chr(10);
	echo '<b>Innsats:</b> '.$duell_innsats.'<br>'.chr(10);
	echo '<b>Tall for utfordrer:</b> '.$duell_tipping_starter.'<br>'.chr(10);
	if($duell_tipping_mot == '0' || $duell_tipping_mot == '')
		echo '<b>Tall for motstander:</b> <i>ikke valgt</i><br>'.chr(10);
	else
		echo '<b>Tall for motstander:</b> '.$duell_tipping_mot.'<br>'.chr(10);
	echo '<b>Status:</b> ';
	if($duell_respons == '0')
		echo 'avlyst ('.date('H:i:s d-m-Y', $duell_tid_respons).')';
	elseif($duell_respons == '2')
	{
		echo 'venter på respons';
		
		// Gi respons
		if($bruker_id == $duell_mot)
		{
			echo '<br><br>';
			
			echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;aksepter_duell=1&amp;duell_id='.$duell_id.'">'.chr(10);
			echo '<b>Innsats:</b><br>'.chr(10);
			echo '<input disabled value="'.$duell_innsats.'"><br>';
			if($duell_innsats > $bruker_cash)
				echo '<font color="red"><b><i>OBS! Du har ikke råd!</i></b></font><br>';
			echo '<br>'.chr(10);
			
			echo '<b>Velg ditt tall</b><br>'.chr(10);
			echo '<font size="2"><i>Kan ikke satse på samme tallet som motstanderen din.</i></font><br>'.chr(10);
			echo '<select name="duell_sats_paa">'.chr(10);
			if($duell_tipping_starter != '1')
				echo ' <option>1</option>'.chr(10);
			if($duell_tipping_starter != '2')
				echo ' <option>2</option>'.chr(10);
			if($duell_tipping_starter != '3')
				echo ' <option>3</option>'.chr(10);
			if($duell_tipping_starter != '4')
				echo ' <option>4</option>'.chr(10);
			if($duell_tipping_starter != '5')
				echo ' <option>5</option>'.chr(10);
			if($duell_tipping_starter != '6')
				echo ' <option>6</option>'.chr(10);
			echo '</select><br><br>'.chr(10).chr(10);
			
			if($duell_innsats > $bruker_cash)
				echo '<input type="submit" value="Aksepter duell" disabled>'.chr(10);
			else
				echo '<input type="submit" value="Aksepter duell">'.chr(10);
			echo '<input type="button" value="Ikke aksepter duell" onclick="document.skjema.submit();">'.chr(10);
			echo '</form>'.chr(10);
			echo '<form method="post" name="skjema" action="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;ikke_aksepter_duell=1&amp;duell_id='.$duell_id.'">'.chr(10);
			echo '</form>'.chr(10);
		}
		elseif($bruker_id == $duell_starter)
		{
			echo '<form method="post" name="skjema" action="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;ikke_aksepter_duell=1&amp;duell_id='.$duell_id.'">'.chr(10);
			echo '<br><input type="submit" value="Avlys duell">'.chr(10);
			echo '</form>'.chr(10);
		}
	}
	else // $duell_respons == '1')
	{
		echo 'duell utført ('.date('H:i:s d-m-Y', $duell_tid_respons).')';
		echo '<br><br>';
		
		foreach ($duell_utfall as $utfall)
		{
			echo 'Treningen trilles og blir '.$utfall.'.';
			if($utfall == $duell_tipping_starter)
				echo ' <i>'.$bruker_navn1.' vinner.</i>';
			if($utfall == $duell_tipping_mot)
				echo ' <i>'.$bruker_navn2.' vinner.</i>';
			echo '<br>'.chr(10);
		}
	}
}

// Satse penger
elseif(isset($_GET['sats_penger']))
{
	echo '<h1>Sats penger</h1>'.chr(10).chr(10);
	
	echo '<form method="get" action="'.$_SERVER['PHP_SELF'].'">'.chr(10);
	echo '<input type="hidden" value="'.$bruker_id.'" name="bruker_id">'.chr(10);
	echo '<b>Hvor mye vil du satse?</b><br>'.chr(10);
	echo '<input type="text" name="bruk_cash" id="satsepenger">';
	// All da money
	/*
	echo '<script type="text/javascript">'.chr(10);
	echo "function insertvalue(id, value) {
	alert('abc');
	var elementet = document.getElementById(id);
	elementet.value=count;
	if(value.value != count)
		value.innerHTML=count;
	else
		value.innerHTML='';
	}
	}
	";
	echo '</script>'.chr(10).chr(10);
	echo '<a href=\'javascript:insertvalue("satsepenger", "'.$bruker_cash.'")\'>('.$bruker_cash.')</a>';*/
	echo '<br><br>'.chr(10);
	
	echo '<b>Hvilket tall satser du på?</b><br>'.chr(10);
	echo '<select name="bruk_paa">'.chr(10);
	echo ' <option>1</option>'.chr(10);
	echo ' <option>2</option>'.chr(10);
	echo ' <option>3</option>'.chr(10);
	echo ' <option>4</option>'.chr(10);
	echo ' <option>5</option>'.chr(10);
	echo ' <option>6</option>'.chr(10);
	echo '</select><br><br>'.chr(10);
	
	echo '<input type="submit" value="Spill!">'.chr(10);
	echo '</form>'.chr(10);
}

// Siste dueller
elseif(isset($_GET['siste_dueller']))
{
	echo '<h1>20 siste dueller</h1>'.chr(10);
	echo '<table class="prettytable">';
	$Q_duell_siste = mysql_query("select * from `minispill1_duell` order by duell_tid desc limit 20");
	while($R_duell = mysql_fetch_assoc($Q_duell_siste))
	{
		$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_duell['duell_starter']."'");
		$bruker_navn1 = mysql_result($Q_bruker, '0', 'bruker_navn');
		$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_duell['duell_mot']."'");
		$bruker_navn2 = mysql_result($Q_bruker, '0', 'bruker_navn');
		
		echo '<tr><td align="right"><font size="2"><b>'.$R_duell['duell_innsats'].'</b></font></td>';
		echo '<td>';
		echo '<font size="2">'.
			'<a href="'.$_SERVER['PHP_SELF'].'?vis_duell=1&amp;bruker_id='.$bruker_id.
			'&amp;duell_id='.$R_duell['duell_id'].'">'.$R_duell['duell_id'].
			'</a></font>';
		echo '</td><td><font size="2">';
		echo $bruker_navn1.' mot '.$bruker_navn2.'</font>';
		echo '</td>'.chr(10);
		
		echo '<td>&nbsp;&nbsp;'.chr(10);
		if($R_duell['duell_respons'] == '2')
			echo '<font color="lightgray"><i>venter på respons</i></font>';
		elseif($R_duell['duell_respons'] == '0')
			echo '<font color="gray"><i>avlyst</i></font>';
		else
		{
			if($R_duell['duell_vinner'] == $R_duell['duell_starter'])
				echo '<i>'.$bruker_navn1.'</i> vant';
			else
				echo '<i>'.$bruker_navn2.'</i> vant';
		}
		echo '</td>'.chr(10);
		
		echo '</tr>'.chr(10);
	}
	echo '</table>'.chr(10).chr(10);
}

elseif(isset($_GET['duell_stats']))
{
	echo '<h1>Statistikk for dueller</h1>'.chr(10);
	
	$Q_brukere = mysql_query("select * from `minispill1` order by bruker_navn");
	echo '<table class="prettytable">'.chr(10);
	echo ' <tr>'.chr(10);
	echo '  <th>Navn</th>'.chr(10);
	echo '  <th>Vinne-<br>prosent</th>'.chr(10);
	echo '  <th>Dueller<br>vunnet</th>'.chr(10);
	echo '  <th>Dueller<br>tapt</th>'.chr(10);
	echo '  <th>Dueller<br>totalt</th>'.chr(10);
	echo '  <th>Peng<br>vunnet</th>'.chr(10);
	echo '  <th>Peng<br>tapt</th>'.chr(10);
	echo '  <th>Resultat</th>'.chr(10);
	echo ' </tr>'.chr(10).chr(10);
	while($R_bruker = mysql_fetch_assoc($Q_brukere))
	{
		$vinneprosent	= '&nbsp;';
		$dueller_vunnet	= 0;
		$dueller_tapt	= 0;
		$dueller_tot	= 0;
		$cash_vunnet	= 0;
		$cash_tapt		= 0;
		$cash_resultat	= 0;
		
		$Q_dueller = mysql_query("select * from `minispill1_duell` where duell_starter = '".$R_bruker['bruker_id']."' or duell_mot = '".$R_bruker['bruker_id']."'");
		while($R_duell = mysql_fetch_assoc($Q_dueller))
		{
			if($R_duell['duell_respons'] == '1')
			{
				$dueller_tot++;
				if($R_duell['duell_vinner'] == $R_bruker['bruker_id'])
				{
					$dueller_vunnet++;
					$cash_vunnet += $R_duell['duell_innsats'];
				}
				else
				{
					$dueller_tapt++;
					$cash_tapt += $R_duell['duell_innsats'];
				}
			}
		}
		
		if($dueller_tot != 0)
			$vinneprosent = round(($dueller_vunnet / $dueller_tot), 2) * 100 .'%';
		
		$cash_resultat = $cash_vunnet - $cash_tapt;
		
		echo ' <tr>'.chr(10);
		$td = '  <td align="right" style="border: 1px black dotted;';
		echo $td.'"><b>'.$R_bruker['bruker_navn'].'</b></td>'.chr(10);
		echo $td.'">'.$vinneprosent.'</td>'.chr(10);
		echo $td.'">'.$dueller_vunnet.'</td>'.chr(10);
		echo $td.'">'.$dueller_tapt.'</td>'.chr(10);
		echo $td.'"><b>'.$dueller_tot.'</b></td>'.chr(10);
		echo $td.'">'.$cash_vunnet.'</td>'.chr(10);
		echo $td.'">'.$cash_tapt.'</td>'.chr(10);
		if($cash_resultat >= 0)
			echo $td.'"><font color="green"><b>'.$cash_resultat.'</b></font></td>'.chr(10);
		else
			echo $td.'"><font color="red"><b>'.$cash_resultat.'</b></font></td>'.chr(10);
		echo ' </tr>'.chr(10).chr(10);
	}
	echo '</table>'.chr(10);
	
	echo '<br><br><br><h2>Statistikk mellom deg og andre brukere</h2>'.chr(10);
	echo '<i>Alle tall er i forhold til deg. Dueller vunnet = antall du har vunnet f.eks.</i><br><br>'.chr(10);
	$Q_brukere = mysql_query("select * from `minispill1` order by bruker_navn");
	echo '<table class="prettytable">'.chr(10);
	echo ' <tr>'.chr(10);
	echo '  <th>Navn</th>'.chr(10);
	echo '  <th>Vinne-<br>prosent</th>'.chr(10);
	echo '  <th>Dueller<br>vunnet</th>'.chr(10);
	echo '  <th>Dueller<br>tapt</th>'.chr(10);
	echo '  <th>Dueller<br>totalt</th>'.chr(10);
	echo '  <th>Peng<br>vunnet</th>'.chr(10);
	echo '  <th>Peng<br>tapt</th>'.chr(10);
	echo '  <th>Resultat</th>'.chr(10);
	echo ' </tr>'.chr(10).chr(10);
	while($R_bruker = mysql_fetch_assoc($Q_brukere))
	{
		$vinneprosent	= '&nbsp;';
		$dueller_vunnet	= 0;
		$dueller_tapt	= 0;
		$dueller_tot	= 0;
		$cash_vunnet	= 0;
		$cash_tapt		= 0;
		$cash_resultat	= 0;
		
		$Q_dueller = mysql_query("select * from `minispill1_duell` where (duell_starter = '".$R_bruker['bruker_id']."' and duell_mot = '".$bruker_id."') or (duell_starter = '".$bruker_id."' and duell_mot = '".$R_bruker['bruker_id']."') ");
		while($R_duell = mysql_fetch_assoc($Q_dueller))
		{
			if($R_duell['duell_respons'] == '1')
			{
				$dueller_tot++;
				if($R_duell['duell_vinner'] == $bruker_id)
				{
					$dueller_vunnet++;
					$cash_vunnet += $R_duell['duell_innsats'];
				}
				else
				{
					$dueller_tapt++;
					$cash_tapt += $R_duell['duell_innsats'];
				}
			}
		}
		
		if($dueller_tot != 0)
			$vinneprosent = round(($dueller_vunnet / $dueller_tot), 2) * 100 .'%';
		
		$cash_resultat = $cash_vunnet - $cash_tapt;
		
		echo ' <tr>'.chr(10);
		$td = '  <td align="right" style="border: 1px black dotted;';
		echo $td.'"><b>'.$R_bruker['bruker_navn'].'</b></td>'.chr(10);
		echo $td.'">'.$vinneprosent.'</td>'.chr(10);
		echo $td.'">'.$dueller_vunnet.'</td>'.chr(10);
		echo $td.'">'.$dueller_tapt.'</td>'.chr(10);
		echo $td.'"><b>'.$dueller_tot.'</b></td>'.chr(10);
		echo $td.'">'.$cash_vunnet.'</td>'.chr(10);
		echo $td.'">'.$cash_tapt.'</td>'.chr(10);
		if($cash_resultat >= 0)
			echo $td.'"><font color="green"><b>'.$cash_resultat.'</b></font></td>'.chr(10);
		else
			echo $td.'"><font color="red"><b>'.$cash_resultat.'</b></font></td>'.chr(10);
		echo ' </tr>'.chr(10).chr(10);
	}
	echo '</table>'.chr(10);
}

elseif(isset($_GET['highscore_bet']))
{
	// Highscorevisning
	
	echo '<h1>Topp 10 highscore</h1>'.chr(10);
	echo '<i>Highscore-listen gjelder for satsing av penger på terningkast.<br>Den som står øverst har høyest fortjeneste på en enkelsatsing.</i><br><br>'.chr(10);
	echo '<table class="prettytable">';
	$Q_highscore = mysql_query("select * from `minispill1_highscore` order by highscore_cash desc limit 10");
	$i = 0;
	while($R_highscore = mysql_fetch_assoc($Q_highscore))
	{
		$i++;
		$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_highscore['bruker_id']."' limit 1");
		$bruker_navn1 = mysql_result($Q_bruker, '0', 'bruker_navn');
		echo '<tr>';
		echo '<td><b>'.$i.'</b></td>';
		echo '<td align="right"><font size="2">'.$R_highscore['highscore_cash'].'</font></td>';
		echo '<td>';
		echo '<font size="2"><b>'.$bruker_navn1.'</b></font>';
		echo '</td>';
		echo '<td><font size="2">'.date('H:i:s d-m-Y', $R_highscore['highscore_tid']).'</font></td>';
		echo '<td><font size="2">'.chr(10);
		
		if($R_highscore['highscore_kommentar'] == '')
		{
			if($R_highscore['bruker_id'] == $bruker_id)
				echo ' [<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;highscore_bet_kommentar=1&amp;highscore_id='.$R_highscore['highscore_id'].'">legg inn kommentar</a>]';
			else
				echo '<i>Ingen kommentar</i>';
		}
		else
		{
			echo '<i>'.wordwrap($R_highscore['highscore_kommentar'], 30, '<br>').'</i>';
		}
		echo '</font></td></tr>'.chr(10);
	}
	echo '</table>'.chr(10);
}

elseif(isset($_GET['highscore_bet_kommentar']))
{
	// Legger inn kommentar
	if(!is_numeric($_GET['highscore_id']))
	{
		echo 'Finner ikke highscoren';
	}
	else
	{
		$Q_highscore = mysql_query("select * from `minispill1_highscore` where highscore_id = '".$_GET['highscore_id']."' limit 1");
		if(!mysql_num_rows($Q_highscore))
		{
			echo 'Finner ikke highscoren';
		}
		else
		{
			$highscore_id			= mysql_result($Q_highscore, '0', 'highscore_id');
			$highscore_cash			= mysql_result($Q_highscore, '0', 'highscore_cash');
			$highscore_bruker_id	= mysql_result($Q_highscore, '0', 'bruker_id');
			$highscore_kommentar	= mysql_result($Q_highscore, '0', 'highscore_kommentar');
			$highscore_tid			= mysql_result($Q_highscore, '0', 'highscore_tid');
			
			if($highscore_bruker_id != $bruker_id)
			{
				echo 'Finner ikke highscoren';
			}
			else
			{
				// Kan endre...
				if(!isset($_POST['highscore_kommentar']))
				{
					echo '<h1>Legg inn kommentar</h1>'.chr(10);
					echo 'For highscoren din '.date('H:i:s d-m-Y', $highscore_tid).'<br>';
					echo 'Vant '.$highscore_cash.'<br><br>';
					
					echo '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;highscore_bet_kommentar=1&amp;highscore_id='.$highscore_id.'">'.chr(10);
					echo '<input name="highscore_kommentar" value="'.$highscore_kommentar.'"><br>'.chr(10);
					echo '<input type="submit" value="Legg til kommentar">'.chr(10);
					echo '</form>'.chr(10);
				}
				else
				{
					$highscore_kommentar = addslashes(htmlspecialchars($_POST['highscore_kommentar'],ENT_QUOTES));
					
					mysql_query("UPDATE `minispill1_highscore` SET `highscore_kommentar` = '".$highscore_kommentar."' WHERE `highscore_id` = '".$highscore_id."' LIMIT 1 ;");
					header("Location: ".$_SERVER['PHP_SELF']."?bruker_id=".$bruker_id."&highscore_bet=1");
					exit();
				}
			}
		}
	}
}

elseif(isset($_GET['melding']))
{
	// Innboks
	
	if(isset($_GET['vis_utboks']))
	{
		echo '<h1>Utboks</h1>'.chr(10);
		$Q_meldinger = mysql_query("select * from `minispill1_meldinger` where bruker_fra = '".$bruker_id."' order by `melding_tid` desc");
		echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;melding=1">Vis innboks</a><br>'.chr(10);
	}
	else
	{
		echo '<h1>Innboks</h1>'.chr(10);
		$Q_meldinger = mysql_query("select * from `minispill1_meldinger` where bruker_til = '".$bruker_id."' order by `melding_tid` desc");
		echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;melding=1&amp;vis_utboks=1">Vis utboks</a><br>'.chr(10);
	}

	echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;send_melding=1">Send melding</a><br><br>'.chr(10);
	
	if(!mysql_num_rows($Q_meldinger))
		echo '<i>Ingen meldinger funnet.</i>';
	else
	{
		echo '<table>'.chr(10);
		echo ' <tr>'.chr(10);
		echo '  <td><b>Tidspunkt</b></td>'.chr(10);
		echo '  <td><b>Til</b></td>'.chr(10);
		echo '  <td><b>Fra</b></td>'.chr(10);
		echo '  <td><b>Lest?</b></td>'.chr(10);
		echo '  <td>&nbsp;</td>'.chr(10);
		echo ' </tr>'.chr(10);
		
		while ($R_melding = mysql_fetch_assoc($Q_meldinger))
		{
			$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_melding['bruker_til']."' limit 1");
			$bruker_navn1 = mysql_result($Q_bruker, '0', 'bruker_navn');
			$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_melding['bruker_fra']."' limit 1");
			$bruker_navn2 = mysql_result($Q_bruker, '0', 'bruker_navn');
			echo ' <tr>'.chr(10);
			echo '  <td><font size="2">'.date('H:i:s d-m-Y', $R_melding['melding_tid']).'</font></td>'.chr(10);
			echo '  <td>'.$bruker_navn1.'</td>'.chr(10);
			echo '  <td>'.$bruker_navn2.'</td>'.chr(10);
			if($R_melding['bruker_til_lest'] == '1')
				echo '  <td>Ja</td>'.chr(10);
			else
				echo '  <td><font color="red">Nei</font></td>'.chr(10);
			echo '  <td><font size="2"><a href="'.$_SERVER['PHP_SELF'].'?vis_melding=1&amp;bruker_id='.$bruker_id.'&amp;melding_id='.$R_melding['melding_id'].'">Les meldingen</a></font></td>'.chr(10);
			echo ' </tr>'.chr(10);
		}
		echo '</table>'.chr(10);
	}
}

elseif(isset($_GET['vis_melding']))
{
	// Visning av melding
	if(!is_numeric($_GET['melding_id']))
	{
		echo 'Finner ikke meldingen.';
	}
	else
	{
		$Q_melding = mysql_query("select * from `minispill1_meldinger` where melding_id = '".$_GET['melding_id']."'");
		if(!mysql_num_rows($Q_melding))
		{
			echo 'Finner ikke meldingen.';
		}
		else
		{
			$melding_id			= mysql_result($Q_melding, '0', 'melding_id');
			$melding_tid		= mysql_result($Q_melding, '0', 'melding_tid');
			$bruker_fra			= mysql_result($Q_melding, '0', 'bruker_fra');
			$bruker_til			= mysql_result($Q_melding, '0', 'bruker_til');
			$bruker_til_lest	= mysql_result($Q_melding, '0', 'bruker_til_lest');
			$meldingen			= mysql_result($Q_melding, '0', 'meldingen');
			
			$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$bruker_til."' limit 1");
			$bruker_navn1 = mysql_result($Q_bruker, '0', 'bruker_navn');
			$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$bruker_fra."' limit 1");
			$bruker_navn2 = mysql_result($Q_bruker, '0', 'bruker_navn');
			
			if($bruker_id != $bruker_fra && $bruker_id != $bruker_til)
			{
				echo 'Meldingen er ikke til deg.';
			}
			else
			{
				echo '<h1>Melding</h1>'.chr(10).chr(10);
				echo 'Melding fra: <b>'.$bruker_navn1.'</b><br>'.chr(10);
				echo 'Melding til: <b>'.$bruker_navn2.'</b><br>'.chr(10);
				echo 'Lest av mottaker? <i>';
				if($bruker_til_lest == '1')
					echo 'ja';
				else
				{
					if($bruker_til == $bruker_id)
					{
						// Leser første gang nå
						mysql_query("UPDATE `minispill1_meldinger` SET `bruker_til_lest` = '1' WHERE `melding_id` = '".$melding_id."' LIMIT 1 ;");
						echo 'ja, første gang nå';
					}
					else
						echo 'nei';
				}
				echo '</i><br>'.chr(10);
				echo 'Melding sendt: <b>'.date('H:i:s d-m-Y', $melding_tid).'</b><br><br>'.chr(10);
				
				echo '<b>Meldingen:</b><br>'.chr(10);
				echo nl2br(wordwrap($meldingen, 50));
				
				echo '<br><br><a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;send_melding=1&amp;melding_til='.$bruker_fra.'">Svar til sender</a><br><br>'.chr(10);
			}
		}
	}
}

elseif(isset($_GET['send_melding']))
{
	// Sending av melding
	echo '<h1>Send en melding</h1>'.chr(10);
	
	echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'">'.chr(10);
	echo '<b>Send til:</b><br>'.chr(10);
	$melding_til_satt = FALSE;
	echo '<select name="bruker_til">'.chr(10);
	$Q_brukere = mysql_query("select * from `minispill1` order by bruker_navn");
	while($R_bruker = mysql_fetch_assoc($Q_brukere))
	{
		echo ' <option value="'.$R_bruker['bruker_id'].'"';
		if(isset($_GET['melding_til']) && $R_bruker['bruker_id'] == $_GET['melding_til'])
		{
			echo ' selected="selected"';
			$melding_til_satt = TRUE;
		}
		echo '>'.$R_bruker['bruker_navn'].'</option>'.chr(10);
	}
	echo ' <option';
	if(!$melding_til_satt)
		echo ' selected="selected"';
	echo ' value="0">Ikke valgt</option>'.chr(10);
	echo '</select><br><br>'.chr(10);
	
	echo '<b>Melding:</b><br>'.chr(10);
	echo '<textarea name="meldingen" cols="25" rows="5"></textarea><br><br>'.chr(10).chr(10);
	
	echo '<input type="submit" name="send_melding" value="Send melding">'.chr(10);
	
	echo '</form>'.chr(10);
}

elseif (isset($_GET['bygg']))
{
	require "bygg.php";
}

else
{
	echo '<h1>Status</h1>'.chr(10);
	echo 'Du, '.$bruker_navn.', har <b>'.cash_printer($bruker_cash).'</b><br><br>';
	
	if((time() - $bruker_fyll_paa) > 60)
		$tid_til = 'klart nå';
	else
	{
		$tid_til = 60 - (time() - $bruker_fyll_paa).' sekund';
	}
	echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&fyll_paa=1">Fyll på med penger</a> (1200 hvert minutt, neste: '.$tid_til.')<br>'.chr(10);
	echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&fyll_paa2=1">Fyll på med penger 2</a> (10 for hvert klikk)<br>'.chr(10);
	if(date('H:i') == $leet_time)
		echo '<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&fyll_paa3=1">Fyll på med penger 3</a> (12000 for hvert klikk! Only for 1337 people!1!!111one)<br>'.chr(10);
	echo '<br>'.chr(10);
	
	echo '<h2>Ubesvarte dueller</h2>'.chr(10);
	$Q_duell_uoppgjort = mysql_query("select * from `minispill1_duell` where duell_respons = '2' and (duell_mot = '".$bruker_id."' or duell_starter = '".$bruker_id."')");
	if(!mysql_num_rows($Q_duell_uoppgjort))
	{
		echo '<i>Ingen</i>'.chr(10);
	}
	else
	{
		echo '<table>';
		while($R_duell = mysql_fetch_assoc($Q_duell_uoppgjort))
		{
			$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_duell['duell_starter']."'");
			$bruker_navn1 = mysql_result($Q_bruker, '0', 'bruker_navn');
			$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_duell['duell_mot']."'");
			$bruker_navn2 = mysql_result($Q_bruker, '0', 'bruker_navn');
			
			echo '<tr><td align="right"><font size="2"><b>'.$R_duell['duell_innsats'].'</b></font></td>';
			echo '<td>&nbsp;&nbsp;';
			if($R_duell['duell_mot'] != $bruker_id)
				echo '<font size="2">mot <a href="'.$_SERVER['PHP_SELF'].'?vis_duell=1&amp;bruker_id='.$bruker_id.'&amp;duell_id='.$R_duell['duell_id'].'">'.$bruker_navn2.'</a></font>';
			else
				echo '<font size="2">mot <a href="'.$_SERVER['PHP_SELF'].'?vis_duell=1&amp;bruker_id='.$bruker_id.'&amp;duell_id='.$R_duell['duell_id'].'">'.$bruker_navn1.'</a></font>';
			echo '</td>'.chr(10);
			
			echo '<td>'.chr(10);
			if($R_duell['duell_respons'] == '2')
			{
				if($R_duell['duell_starter'] == $bruker_id)
					echo '<font color="lightgray"><i>venter på motspiller</i></font>';
				else
					echo '<font color="lightgray"><i>venter på deg</i></font>';
			}
			elseif($R_duell['duell_respons'] == '0')
				echo '<font color="lightgreen">avlyst</font>';
			else
			{
				if($R_duell['duell_vinner'] == $bruker_id)
					echo '<font color="green">seier</font>';
				else
					echo '<font color="red">tap</font>';
			}
			echo '</td>'.chr(10);
			
			echo '</tr>'.chr(10);
		}
		echo '</table>'.chr(10).chr(10);
	}
}

echo chr(10).chr(10).'</td><td valign="top">'.chr(10);
echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
echo chr(10).chr(10).'</td><td valign="top">'.chr(10);

$Q_duell_uoppgjort = mysql_query("select * from `minispill1_duell` where duell_respons = '2' and duell_mot = '".$bruker_id."'");
if(mysql_num_rows($Q_duell_uoppgjort))
{
	echo '<h1>Uoppgjorte dueller</h1>'.chr(10);
	echo '<table>';
	while($R_duell = mysql_fetch_assoc($Q_duell_uoppgjort))
	{
		$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_duell['duell_starter']."'");
		$bruker_navn2 = mysql_result($Q_bruker, '0', 'bruker_navn');
		echo '<tr><td align="right"><b>'.$R_duell['duell_innsats'].'</b></td>';
		echo '<td>&nbsp;&nbsp;';
		echo '<font size="2">mot <a href="'.$_SERVER['PHP_SELF'].'?vis_duell=1&amp;bruker_id='.$bruker_id.'&amp;duell_id='.$R_duell['duell_id'].'">'.$bruker_navn2.'</a></font>';
		echo '</td></tr>'.chr(10);
	}
	echo '</table>'.chr(10).chr(10);
}

echo '<h1>Topp 10</h1>'.chr(10);
echo '<table>';
$Q_brukere = mysql_query("select * from `minispill1` order by bruker_cash desc limit 10");
$online_tid = 60 * 2; // 2 minutter
$tid = time() - $online_tid;
$i = 0;
while($R_bruker = mysql_fetch_assoc($Q_brukere))
{
	$i++;
	echo '<tr>';
	echo '<td align="right">'.$i.'</td>'.chr(10);
	echo '<td align="right"><b>'.cash_printer($R_bruker['bruker_cash']).'</b></td>';
	echo '<td>&nbsp;&nbsp;';
	// Online?
	$Q_online = mysql_query("select bruker_id from `minispill1_online` where bruker_id = '".$R_bruker['bruker_id']."' and online_time >= '$tid'");
	if(mysql_num_rows($Q_online))
		echo '<font size="2">[<font color="lightgreen">online</font>]</font>';
	else
		echo '<font size="2">[<font color="pink">offline</font>]</font>';
	echo ' '.$R_bruker['bruker_navn'];
	echo ' <font size="2">[<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;duell=1&amp;duell_mot='.$R_bruker['bruker_id'].'">duell</a>]</font>';
	echo '</td></tr>'.chr(10);
}
echo '</table>'.chr(10);

$Q_duell_siste = mysql_query("select * from `minispill1_duell` where duell_starter = '".$bruker_id."' or duell_mot = '".$bruker_id."' order by duell_tid desc limit 10");
if(mysql_num_rows($Q_duell_siste))
{
	echo '<h1>Dine 10 siste dueller</h1>'.chr(10);
	echo '<table>';
	while($R_duell = mysql_fetch_assoc($Q_duell_siste))
	{
		$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_duell['duell_starter']."'");
		$bruker_navn1 = mysql_result($Q_bruker, '0', 'bruker_navn');
		$Q_bruker = mysql_query("select * from `minispill1` where bruker_id = '".$R_duell['duell_mot']."'");
		$bruker_navn2 = mysql_result($Q_bruker, '0', 'bruker_navn');
		
		echo '<tr><td align="right"><font size="2"><b>'.cash_printer($R_duell['duell_innsats']).'</b></font></td>';
		echo '<td>&nbsp;&nbsp;';
		if($R_duell['duell_mot'] != $bruker_id)
			echo '<font size="2">mot <a href="'.$_SERVER['PHP_SELF'].'?vis_duell=1&amp;bruker_id='.$bruker_id.'&amp;duell_id='.$R_duell['duell_id'].'">'.$bruker_navn2.'</a></font>';
		else
			echo '<font size="2">mot <a href="'.$_SERVER['PHP_SELF'].'?vis_duell=1&amp;bruker_id='.$bruker_id.'&amp;duell_id='.$R_duell['duell_id'].'">'.$bruker_navn1.'</a></font>';
		echo '</td>'.chr(10);
		
		echo '<td>'.chr(10);
		if($R_duell['duell_respons'] == '2')
		{
			if($R_duell['duell_starter'] == $bruker_id)
				echo '<font color="lightgray"><i>venter på motspiller</i></font>';
			else
				echo '<font color="lightgray"><i>venter på deg</i></font>';
		}
		elseif($R_duell['duell_respons'] == '0')
			echo '<font color="lightgreen">avlyst</font>';
		else
		{
			if($R_duell['duell_vinner'] == $bruker_id)
				echo '<font color="green">seier</font>';
			else
				echo '<font color="red">tap</font>';
		}
		echo '</td>'.chr(10);
		
		echo '</tr>'.chr(10);
	}
	echo '</table>'.chr(10).chr(10);
}

echo '</td></tr></table>'.chr(10);

// Skript som oppdaterer cashen i toppen
echo '<script type="text/javascript">'.chr(10);
echo 'function endre_cash (BrukerCash)
{
	var cashelement = document.getElementById("bruker_cash");
	cashelement.innerHTML = BrukerCash;
}
';
echo '</script>'.chr(10).chr(10);

echo '<script type="text/javascript">endre_cash('.cash_printer($bruker_cash).');</script>'.chr(10);


/*

TODO:

passord på brukeren

bug - kan duellere mot seg selv
fjerne meldinger (delvis lagt inn, mangler kodene men database er ok)

Du har vunnet!
Fortjeneste
DETTE ER HIGHSCORE!!

bank
- setter inn penger og tar ut med rente
- kan bare ta ut enkeltvis innskudd
- renten settes enten til 0,6
	- eller man kan gamble med den
	- (1, 2, 3, 4, 5 eller 6) * 0,02 (fra 0,02 til 0,12 i rente)
- renten legges til hver time
- minste tiden på et innskudd er en time

byggespill
- bygger bygninger (kjøper bygninger)
- bygningene kan gi avkasting

javascript som oppdaterer "du har ..." når antall penger blir oppdatert lenger nede i scriptet

*/
?>
