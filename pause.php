<?php

/* Pauseinnstillinger */

$pause_msg = 'Server offline';

$pause = TRUE;

$pause_for_ticker	= TRUE;
$pause_for_spill	= TRUE;


$non_pause_brukere = array();
//$non_pause_brukere = array('31', '30');

if(isset($_GET['bruker_id']) && is_numeric($_GET['bruker_id']))
{
	if(in_array($_GET['bruker_id'], $non_pause_brukere))
		$pause = FALSE;
}

$pause = FALSE;



?>
