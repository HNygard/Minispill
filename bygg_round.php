<?php

/*
Kjører runder for byggene som er bygget

*/

set_time_limit('200');

require "bygg_settings.php";

echo '<link rel="stylesheet" type="text/css" href="spill.css" />
';

echo '
<script type="text/javascript" src="jquery-1.3.2.min.js"></script>

<script type="text/javascript">
function reload() {
location = "bygg_round.php"}

setTimeout("reload()", '.$sekund_per_inntekt.'000);
</script>'.chr(10);


?>

Det er <span id="countdown"></span> sekunder til neste tick.<br><br>

<script> 
<!-- 
var milisec=0 
var seconds=<?php echo $sekund_per_inntekt.chr(10); ?>
$("#countdown").text(<?php echo $sekund_per_inntekt; ?>);

function display(){ 
 if (milisec<=0){ 
    milisec=9 
    seconds-=1 
 } 
 if (seconds<=-1){ 
    milisec=0 
    seconds+=1 
 } 
 else 
    milisec-=1 
    $("#countdown").text(seconds+"."+milisec);
    setTimeout("display()",100) 
} 
display();
--> 
</script> 

<?php

require "pause.php";
if($pause && $pause_for_ticker)
{
	echo $pause_msg;
	exit();
}

function bygg_oppdragering_kost($start, $level)
{
	$kost = $start * pow($level,2);
	return $kost;
}

function bygg_inntekt($start, $level)
{
	$level = $level - 1;
	$inntekt = ($start / 3) * pow($level,2) + $start;
	return round($inntekt);
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

$Q_alle_bygg = mysql_query("select * from `minispill1_bygg`");
$bygg = array();
while($R_bygg = mysql_fetch_assoc($Q_alle_bygg))
{
	$bygg_id = $R_bygg['bygg_id'];
	$bygg[$bygg_id]['bygg_id']				= $R_bygg['bygg_id'];
	$bygg[$bygg_id]['bygg_navn']			= $R_bygg['bygg_navn'];
	//$bygg[$bygg_id]['bygg_kost']			= unserialze($R_bygg['bygg_kost']);
	//$bygg[$bygg_id]['bygg_inntekt']		= unserialze($R_bygg['bygg_inntekt']);
	$bygg[$bygg_id]['bygg_kost_start']		= $R_bygg['bygg_kost_start'];
	$bygg[$bygg_id]['bygg_inntekt_start']	= $R_bygg['bygg_inntekt_start'];
}

$tidsformat = 'Ymd H:i:s';

$rounds = 0;
$stopp = false;

echo '<table class="prettytable">';
echo '<tr>';
echo '	<th>Tid</th>';
//echo '	<th>Bygg_bruker_id</th>';
echo '	<th>Inntekt</th>';
echo '	<th>Inntekt</th>';
echo '</tr>
';
while(!$stopp)
{
	$Q_mine_bygg = mysql_query("
		select
			bygg_bruker.*,
			bygg.bygg_navn as bygg_navn,
			bruker.bruker_navn as bruker_navn
		from 
		(`minispill1_bygg_bruker` bygg_bruker left join `minispill1` bruker
			on bygg_bruker.bruker_id = bruker.bruker_id) left join `minispill1_bygg` bygg
		on bygg_bruker.bygg_id = bygg.bygg_id");
	
	while($R_bygg = mysql_fetch_assoc($Q_mine_bygg))
	{
		$bygg_id			= $R_bygg['bygg_id'];
		$bygg_bruker_id		= $R_bygg['bygg_bruker_id'];
		$bruker_id			= $R_bygg['bruker_id'];
		$bygg_level			= $R_bygg['bygg_level'];
		$bygg_inntekt_sist	= $R_bygg['bygg_inntekt_sist'];
		
		$bygg_navn = $R_bygg['bygg_navn'];
		$bruker_navn = $R_bygg['bruker_navn'];
		
		echo '<tr>';
		echo '<td>'.date($tidsformat).'</td>';
		//echo '<td>'.$bygg_bruker_id.'</td>';
		echo '<td>'.$bruker_navn.'</td>';
		echo '<td>'.$bygg_navn.'</td>';
		if((time() - $sekund_per_inntekt) < $bygg_inntekt_sist)
		{
			$tid_igjen = $bygg_inntekt_sist - time() + $sekund_per_inntekt;
			echo '<td><font color="red">Du må vente til det er gått '.$sekund_per_inntekt.
				' sekunder siden sist inntekt ble hentet ('.$tid_igjen.
				' sekunder til).</font></td>';
		}
		else
		{
			// Oppdaterer sist inntekt ble hentet:
			$SQL_inntekt_hent = "UPDATE `minispill1_bygg_bruker` SET `bygg_inntekt_sist` = '".time()."' WHERE `bygg_bruker_id` = '".$bygg_bruker_id."' LIMIT 1 ;";
			mysql_query($SQL_inntekt_hent);
			
			// Endrer bruker_cash
			$Q_bruker_cash	= mysql_query("select bruker_cash from `minispill1` where bruker_id = '".$bruker_id."'");
			$bruker_cash	= mysql_result($Q_bruker_cash, '0','bruker_cash');
			$ny_bruker_cash	= $bruker_cash + bygg_inntekt($bygg[$bygg_id]['bygg_inntekt_start'], $bygg_level);
			mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
			
			echo '<td>'.bygg_inntekt($bygg[$bygg_id]['bygg_inntekt_start'], $bygg_level).'</td>';
			
		}
		echo '</tr>';
	}
	
	//echo date($tidsformat).' [Sleep]<br>'.chr(10);
	//flush();
	//sleep($sekund_per_inntekt);
	//sleep(5);
	//echo date($tidsformat).' [Awake]<br>'.chr(10);
	//$rounds++;
	
	//if($rounds > 3)
		$stopp = TRUE;
}
echo '</table>';

mysql_query("INSERT INTO `minispill1_bygg_ticker` ( `ticker_id` , `ticker_tid` ) VALUES ('', '".time()."');");

?>
