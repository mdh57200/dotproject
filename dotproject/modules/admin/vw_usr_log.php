<script languaje="JavaScript">
var calendarField = '';
var calWin = null;


function popCalendar( field ){
	calendarField = field;
	idate = eval( 'document.frmDate.log_' + field + '.value' );
	window.open( 'index.php?m=public&a=calendar&dialog=1&callback=setCalendar&date=' + idate, 'calwin', 'top=250,left=250,width=251, height=220, scollbars=false' );
}

function setCalendar( idate, fdate ) {
	fld_date = eval( 'document.frmDate.log_' + calendarField );
	fld_fdate = eval( 'document.frmDate.' + calendarField );
	fld_date.value = idate;
	fld_fdate.value = fdate;
}

function checkDate(){
           if (document.frmDate.log_start_date.value == "" || document.frmDate.log_end_date.value== ""){
                alert("<?php echo $AppUI->_('You must fill fields') ?>");
                return false;
           } 
           return true;
}
</script>

<?php
$date_reg = date("Y-m-d");
$start_date = intval( $date_reg) ? new CDate( $date_reg ) : null;
$end_date = intval( $date_reg) ? new CDate( $date_reg ) : null;

$df = $AppUI->getPref('SHDATEFORMAT');
global $currentTabId;
if ($a = dPgetParam($_REQUEST, "a", "") == ""){
    $a = "&tab={$currentTabId}&showdetails=1";
} else {
    $user_id = dPgetParam($_REQUEST, "user_id", 0);
    $a = "&a=viewuser&user_id={$user_id}&tab={$currentTabId}&showdetails=1";
}

?>

<table align="center">
	<tr>
		<td>
			<h1><?php echo $AppUI->_('User Log');?></h1>
		</td>
	</tr>
</table>

<form action="index.php?m=admin<?php echo $a; ?>" method="post" name="frmDate">
<table align="center" " width="100%">
	<tr align="center">
		<td align="right" width="45%" ><?php echo $AppUI->_( 'Start Date' );?></td>
			<td width="55%" align="left">
				<input type="hidden" name="log_start_date" value="<?php echo $start_date ? $start_date->format( FMT_TIMESTAMP_DATE ) : "" ;?>" />
				<input type="text" name="start_date" value="<?php echo $start_date ? $start_date->format( $df ) : "" ;?>" class="text" readonly disabled="disabled" />
				<a href="#" onClick="popCalendar('start_date')">
				<img src="./images/calendar.gif" width="24" height="12" alt="<?php echo $AppUI->_('Calendar');?>" border="0" ></a>
			</td>
	</tr>
	<tr align="center">
		<td align="right" width="45%"><?php echo $AppUI->_( 'End Date' );?></td>
			<td width="55%" align="left">
				<input type="hidden" name="log_end_date" value="<?php echo $end_date ? $end_date->format( FMT_TIMESTAMP_DATE ) : '';?>" />
				<input type="text" name="end_date" value="<?php echo $end_date ? $end_date->format( $df ) : '';?>" class="text" readonly disabled="disabled" />
				<a href="#" onClick="popCalendar('end_date')">
				<img src="./images/calendar.gif" width="24" height="12" alt="<?php echo $AppUI->_('Calendar');?>" border="0"></a>
		</td>
	</tr>
</table>
<table align="center">
	<tr align="center">
		<td><input type="submit" class="button" value="<?php echo $AppUI->_('Submit');?>" onClick="return checkDate('start','end')"></td>
	</tr>
</table>
</form>

<?php 
if (dPgetParam($_REQUEST, "showdetails", 0) == 1 ) {  
    $user_filter = $user_id == 0 ? "" : "and ual.user_id='$user_id'";
    $sql = "select ual.*, u.*, c.*
                from user_access_log as ual,
                        users as u,
                        contacts as c
                where ual.user_id = u.user_id
                          and user_contact = contact_id
                          $user_filter
                          and ual.date_time_in >=' ".dPgetParam($_REQUEST, "start_date", "")." ' 
                          and ual.date_time_out <='".dPgetParam($_REQUEST, "end_date", "")."'";
    $logs = db_loadList($sql);
?>
<table align="center" class="tbl" width="50%">
    <th nowrap="nowrap"  STYLE="background: #08245b"><?php echo $AppUI->_('Name(s)');?></th>
    <th nowrap="nowrap"  STYLE="background: #08245b"><?php echo $AppUI->_('Last Name');?></th>
    <th nowrap="nowrap"  STYLE="background: #08245b"><?php echo $AppUI->_('Date Time IN');?></th>
    <th nowrap="nowrap"  STYLE="background: #08245b"><?php echo $AppUI->_('Date Time OUT');?></th>
<?php foreach ($logs as $detail){?>
	<tr>
		<td align="center"><?php echo $detail["contact_first_name"];?></td>
		<td align="center"><?php echo $detail["contact_last_name"];?></td>
		<td align="center"><?php echo $detail["date_time_in"];?></td>
		<td align="center"><?php echo $detail["date_time_out"];?></td>
	</tr>
<?php } ?>
</table>
<?php } ?>

