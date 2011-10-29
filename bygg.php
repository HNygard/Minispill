<?php

/*


- Bygning går på level
- Høyere level gir høyere inntekt
- Formel for utregning av kost for oppgradering


WRONG!!!:
- Materialer kjøres for penger
- Materialene brukes til å bygge bygninger
- Bygningene genererer penger og materialer per "tick"
- Må klikke for å få output

*/

require "bygg_settings.php";

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

function bygg_location ($location)
{
	$location = explode (':', $location);
	return array('x' => $location[0], 'y' => $location[1]);
}
	

echo '<h1>Bygninger</h1>'.chr(10);

$Q_alle_bygg = mysql_query("select * from `minispill1_bygg` order by bygg_kost_start");
if(!mysql_num_rows($Q_alle_bygg))
{
	echo '<i>Ingen bygninger...</i>';
}
else
{
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
	
	// Bygg nytt
	if(isset($_GET['bygg_nytt']))
	{
		if(array_key_exists($_GET['bygg_nytt'],$bygg))
		{
			$bygg_id = $_GET['bygg_nytt'];
			$N_bygg = mysql_num_rows(mysql_query("select * from `minispill1_bygg_bruker` where bruker_id = '$bruker_id' and bygg_id = '$bygg_id'"));
			if($N_bygg >= $bygg_maks)
			{
				echo '<div class="error">Du har nådd maksgrensen for denne typen bygg (maks '.$bygg_maks.')</div>';
			}
			elseif($bruker_cash < $bygg[$bygg_id]['bygg_kost_start'])
			{
				echo '<div class="error">Beklager, du har ikke råd til å bygge '.$bygg[$bygg_id]['bygg_navn'].'.</div>';
			}
			else
			{
				// Har råd -> bygger
				$SQL_nytt_bygg = "INSERT INTO `minispill1_bygg_bruker` 
					( `bygg_bruker_id` , `bruker_id` , `bygg_id` , `bygg_level` , `bygg_inntekt_sist` )
					VALUES ('', '".$bruker_id."', '".$bygg_id."', '1', '".time()."');";
				
				mysql_query($SQL_nytt_bygg);
				
				// Endrer bruker_cash
				$ny_bruker_cash = $bruker_cash - $bygg[$bygg_id]['bygg_kost_start'];
				mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
				$bruker_cash = $ny_bruker_cash;
				
				echo '<div class="success">Ett nytt bygg ble bygget: '.$bygg[$bygg_id]['bygg_navn'].'</div>';
			}
			
			echo '<br><br>'.chr(10).chr(10);
		}
	}
	
	// Inntekt
	if(isset($_GET['bygg_inntekt']))
	{
		$bygg_bruker_id = $_GET['bygg_inntekt'];
		
		// Henter bygg_bruker
		$Q_hent_bygg = mysql_query("select * from `minispill1_bygg_bruker` where bruker_id = '".$bruker_id."' and bygg_bruker_id = '".$bygg_bruker_id."'");
		if(!mysql_num_rows($Q_hent_bygg))
		{
			echo '<font color="red">Finner ikke bygget</font>';
		}
		else
		{
			$bygg_id			= mysql_result($Q_hent_bygg, '0', 'bygg_id');
			$bygg_level			= mysql_result($Q_hent_bygg, '0', 'bygg_level');
			$bygg_inntekt_sist	= mysql_result($Q_hent_bygg, '0', 'bygg_inntekt_sist');
			
			if(!array_key_exists($bygg_id,$bygg))
			{
				echo '<font color="red">Finner ikke byggtypen.</font>';
			}
			else
			{
				if((time() - $sekund_per_inntekt) < $bygg_inntekt_sist)
				{
					$tid_igjen = $bygg_inntekt_sist - time() + $sekund_per_inntekt;
					echo '<font color="red">Du må vente til det er gått '.$sekund_per_inntekt.' sekunder siden sist inntekt ble hentet ('.$tid_igjen.' sekunder til).</font>';
				}
				else
				{
					// Oppdaterer sist inntekt ble hentet:
					$SQL_inntekt_hent = "UPDATE `minispill1_bygg_bruker` SET `bygg_inntekt_sist` = '".time()."' WHERE `bygg_bruker_id` = '".$bygg_bruker_id."' LIMIT 1 ;";
					mysql_query($SQL_inntekt_hent);
					
					// Endrer bruker_cash
					$ny_bruker_cash = $bruker_cash + bygg_inntekt($bygg[$bygg_id]['bygg_inntekt_start'], $bygg_level);
					mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
					$bruker_cash = $ny_bruker_cash;
					
					echo 'Hentet '.bygg_inntekt($bygg[$bygg_id]['bygg_inntekt_start'], $bygg_level).' i inntekt fra bygg nummer '.$bygg_bruker_id.'.';
				}
			}
		}
		
		echo '<br><br>'.chr(10).chr(10);
	}
	
	// Oppgrader
	if(isset($_GET['bygg_update']))
	{
		$bygg_bruker_id = $_GET['bygg_update'];
		
		// Henter bygg_bruker
		$Q_hent_bygg = mysql_query("select * from `minispill1_bygg_bruker` where bruker_id = '".$bruker_id."' and bygg_bruker_id = '".$bygg_bruker_id."'");
		if(!mysql_num_rows($Q_hent_bygg))
		{
			echo '<div class="error">Finner ikke bygget</div>';
		}
		else
		{
			$bygg_id			= mysql_result($Q_hent_bygg, '0', 'bygg_id');
			$bygg_level			= mysql_result($Q_hent_bygg, '0', 'bygg_level');
			$bygg_inntekt_sist	= mysql_result($Q_hent_bygg, '0', 'bygg_inntekt_sist');
			
			if(!array_key_exists($bygg_id,$bygg))
			{
				echo '<div class="error">Finner ikke byggtypen.</div>';
			}
			else
			{
				if($bruker_cash < bygg_oppdragering_kost($bygg[$bygg_id]['bygg_kost_start'], $bygg_level))
				{
					echo '<div class="error">Ikke råd til oppgradere bygget</div>';
				}
				else
				{
					// Har råd -> oppgraderer
					$ny_level = $bygg_level + 1;
					$SQL_oppgradere = "UPDATE `minispill1_bygg_bruker` SET `bygg_level` = '".$ny_level."' WHERE `bygg_bruker_id` = '".$bygg_bruker_id."' LIMIT 1 ;";
					mysql_query($SQL_oppgradere);
					
					// Endrer bruker_cash
					$ny_bruker_cash = $bruker_cash - bygg_oppdragering_kost($bygg[$bygg_id]['bygg_kost_start'], $bygg_level);
					mysql_query("UPDATE `minispill1` SET `bruker_cash` = '".$ny_bruker_cash."' WHERE `bruker_id` = '".$bruker_id."' LIMIT 1 ;");
					$bruker_cash = $ny_bruker_cash;
					
					echo '<div class="success">'.
					'Oppgraderte bygg '.$bygg_bruker_id.' til level '.$ny_level.'.</div>';
				}
			}
		}
		
		echo '<br><br>'.chr(10).chr(10);
	}
	
	$ticker_tid = (time() - $sekund_per_inntekt - 10);
	$Q_ticker = mysql_query("select * from `minispill1_bygg_ticker` where `ticker_tid` >= '$ticker_tid' order by `ticker_tid` desc limit 1");
	echo mysql_error();
	echo '<i>Penger hentes hvert '.$sekund_per_inntekt.'. sekund hvis ticker kjører.</i><br><br>'.chr(10).chr(10);
	echo 'Ticker: ';
	if(mysql_num_rows($Q_ticker))
	{
		echo '[<font color="green">online</font>]';
		$time_to_next = mysql_result($Q_ticker, '0', 'ticker_tid') + $sekund_per_inntekt - time();
		echo ' ('.$time_to_next.' sekunder til neste)';
	}
	else
		echo '[<font color="red">offline</font>]';
	echo '<br><br>'.chr(10).chr(10);
	
	echo '- <a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;bygg=1&amp;bygg_stats=1">Vis statistikk</a><br>';
	
	if(!isset($_GET['bygg_stats']))
	{
		echo '<h2>Mine bygg</h2>'.chr(10);
		$Q_mine_bygg = mysql_query("select * from `minispill1_bygg_bruker` where bruker_id = '".$bruker_id."' ORDER BY `bygg_id` DESC");
		if(!mysql_num_rows($Q_mine_bygg))
		{
			echo '<i>Ingen bygg...</i>'.chr(10);
		}
		else
		{
			echo '<table class="prettytable">'.chr(10);
			
			echo ' <tr>'.chr(10);
			echo '  <th>Sted</th>'.chr(10);
			echo '  <th>Byggtype</th>'.chr(10);
			echo '  <th>Level</th>'.chr(10);
			echo '  <th>Inntekt</th>'.chr(10);
			echo '  <th>Oppdragere</th>'.chr(10);
			echo ' </tr>'.chr(10).chr(10);
			
			while($R_bygg = mysql_fetch_assoc($Q_mine_bygg))
			{
				$bygg_id		= $R_bygg['bygg_id'];
				$bygg_bruker_id	= $R_bygg['bygg_bruker_id'];
				$location		= bygg_location($R_bygg['bygg_location']);
				
				echo ' <tr>'.chr(10);
				echo '  <td>('.$location['x'].'.'.$location['y'].')</td>'.chr(10);
				echo '  <td>'.$bygg[$bygg_id]['bygg_navn'].'</td>'.chr(10);
				echo '  <td>'.$R_bygg['bygg_level'].'</td>'.chr(10);
				echo '  <td align="right"><a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;bygg=1&amp;bygg_inntekt='.$bygg_bruker_id.'">'.cash_printer(bygg_inntekt($bygg[$bygg_id]['bygg_inntekt_start'], $R_bygg['bygg_level'])).'</a></td>'.chr(10);
				echo '  <td align="right"><a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;bygg=1&amp;bygg_update='.$bygg_bruker_id.'">'.cash_printer(bygg_oppdragering_kost($bygg[$bygg_id]['bygg_kost_start'], $R_bygg['bygg_level'])).'</a></td>'.chr(10);
				echo ' </tr>'.chr(10).chr(10);
			}
			
			echo '</table>'.chr(10);
		}
		
		echo '<br>'.chr(10);
		echo '<h2>Bygg nye</h2>'.chr(10);
		echo '<table class="prettytable" border="1">';
		echo '<tr>
			<th>Byggning</th>
			<th>inntekt</th>
			<th>kost</th>
			<th>Du har</th>
			<th>Bygg?</th>
			</tr>';
		foreach($bygg as $dette_bygg)
		{
			$N_bygg = mysql_num_rows(
				mysql_query("
					select * from `minispill1_bygg_bruker` 
					where 
						bruker_id = '$bruker_id' and 
						bygg_id = '".$dette_bygg['bygg_id']."'
					"));
			echo '<tr>';
			echo '<td>'.$dette_bygg['bygg_navn'].'</td>';
			echo '<td style="text-align: right;">'.cash_printer($dette_bygg['bygg_inntekt_start']).'</td>';
			echo '<td style="text-align: right;">'.cash_printer($dette_bygg['bygg_kost_start']).'</td>';
			echo '<td>'.$N_bygg.'/'.$bygg_maks.'</td>';
			if ($N_bygg >= $bygg_maks) {
				echo '<td>Du Kan ikke bygge flere</td>';
			}
			else {
				if ($bruker_cash < $dette_bygg['bygg_kost_start']) {
					echo '<td><font color="red">Ikke rå!</font></td>';
				}
				else {
					echo '<td><a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;bygg=1&amp;bygg_nytt='.$dette_bygg['bygg_id'].'">Bygg!</a></td>';
				}
			}
			echo '</tr>';
			/*
			echo  '.$dette_bygg['bygg_navn'].', koster '.cash_printer($dette_bygg['bygg_kost_start']).' og starter på '.cash_printer($dette_bygg['bygg_inntekt_start']).' i inntekt.';
			echo ' Du har '.$N_bygg.' bygninger av denne typen';
			if($N_bygg >= $bygg_maks)
				echo ' og kan ikke bygge flere.';
			else
				echo ' og kan [<a href="'.$_SERVER['PHP_SELF'].'?bruker_id='.$bruker_id.'&amp;bygg=1&amp;bygg_nytt='.$dette_bygg['bygg_id'].'">bygge</a>] flere.';
			echo '<br>'.chr(10);
			*/
		}
		
		echo '</table>';
	}
	else
	{
		echo '<h2>Byggstats</h2>'.chr(10);
		$brukere_cash = array();
		$brukere_bygg = array();
		$Q_brukere = mysql_query("select * from `minispill1` order by bruker_navn");
		echo '<table class="prettytable">'.chr(10);
		echo '	<tr>'.chr(10);
		echo '		<th>Navn</th>'.chr(10);
		echo '		<th>Bygninger</th>'.chr(10);
		echo '		<th>Total inntekt</th>'.chr(10);
		echo '		<th>Gjennomsnitt</th>'.chr(10);
		echo '	</tr>'.chr(10).chr(10);
		while($R_bruker = mysql_fetch_assoc($Q_brukere))
		{
			$bygg_totalt	= 0;
			$cash_totalt	= 0;
			
			$Q_bygg = mysql_query("select * from `minispill1_bygg_bruker` where bruker_id = '".$R_bruker['bruker_id']."'");
			while($R_bygg = mysql_fetch_assoc($Q_bygg))
			{
				$cash_totalt = $cash_totalt + bygg_inntekt($bygg[$R_bygg['bygg_id']]['bygg_inntekt_start'], $R_bygg['bygg_level']);
				$bygg_totalt++;
			}
			
			$brukere_cash[$R_bruker['bruker_id']]	= $cash_totalt;
			$brukere_bygg[$R_bruker['bruker_id']]	= $bygg_totalt;
			$brukere[$R_bruker['bruker_id']]		= $R_bruker['bruker_navn'];
		}
		
		arsort($brukere_cash);
		
		$td = '  <td align="right" style="border: 1px black dotted;';
		foreach($brukere_cash as $bruker_iden => $cash_totalt)
		{
			if($brukere_bygg[$bruker_iden] != 0)
				$gjennomsnitt_bygg = round($brukere_cash[$bruker_iden] / $brukere_bygg[$bruker_iden]);
			//else
			//	$gjennomsnitt_bygg = 0;
			
			echo ' <tr>'.chr(10);
			echo $td.'"><b>'.$brukere[$bruker_iden].'</b></td>'.chr(10);
			echo $td.'">'.$brukere_bygg[$bruker_iden].'</td>'.chr(10);
			echo $td.'">'.cash_printer($brukere_cash[$bruker_iden]).'</td>'.chr(10);
			echo $td.'">'.cash_printer($gjennomsnitt_bygg).'</td>'.chr(10);
			echo ' </tr>'.chr(10).chr(10);
		}
		echo '</table>'.chr(10);
	}
}


?>
