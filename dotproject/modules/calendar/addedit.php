<?php /* CALENDAR $Id$ */
$event_id = intval( dPgetParam( $_GET, "event_id", 0 ) );
$is_clash = isset($_SESSION['event_is_clash']) ? $_SESSION['event_is_clash'] : false;

// check permissions
if (!($canAuthor && $event_id == 0) && !$canEdit) {
	$AppUI->redirect( "m=public&a=access_denied" );
}

// get the passed timestamp (today if none)
$date = dPgetParam( $_GET, 'date', null );

// load the record data
$obj = new CEvent();

if ($is_clash) {
  $obj->bind($_SESSION['add_event_post']);
}
else if ( !$obj->load( $event_id ) && $event_id ) {
	$AppUI->setMsg( 'Event' );
	$AppUI->setMsg( "invalidID", UI_MSG_ERROR, true );
	$AppUI->redirect();
}

// check only owner can edit
// TODO: Should assignee's be allowed to edit?
if ($obj->event_owner != $AppUI->user_id && $event_id != 0) {
	$AppUI->redirect( "m=public&a=access_denied" );
}

// load the event types
$types = dPgetSysVal( 'EventType' );

// Load the users
$perms =& $AppUI->acl();
$users = $perms->getPermittedUsers();

// Load the assignees
$assigned = array();
if ($is_clash) {
	$assignee_list = $_SESSION['add_event_attendees'];
	if (isset($assignee_list) && $assignee_list) {
		$q  = new DBQuery;
		$q->addTable('users', 'u');
		$q->addTable('contacts', 'con');
		$q->addQuery('user_id, CONCAT_WS(" " , contact_first_name, contact_last_name)');
		$q->addWhere("user_id in ($assignee_list)");
		$q->addWhere("user_contact = contact_id");
		$assigned = $q->loadHashList();
	} else {
	}
} else if ( $event_id == 0 ) {
	$assigned[$AppUI->user_id] = "$AppUI->user_first_name $AppUI->user_last_name";
} else {
	$assigned = $obj->getAssigned();
}
// Now that we have loaded the possible replacement event,  remove the stored
// details, NOTE: This could cause using a back button to make things break,
// but that is the least of our problems.
if ($is_clash) {
 	unset($_SESSION['add_event_post']);
	unset($_SESSION['add_event_attendees']);
	unset($_SESSION['add_event_mail']);
	unset($_SESSION['add_event_clash']);
	unset($_SESSION['event_is_clash']);
}
if ($_GET["event_project"]) 
	$obj->event_project = $_GET["event_project"];

// setup the title block
$titleBlock = new CTitleBlock( ($event_id ? "Edit Event" : "Add Event") , 'myevo-appointments.png', $m, "$m.$a" );
$titleBlock->addCrumb( "?m=calendar", "month view" );
if ($event_id) {
	$titleBlock->addCrumb( "?m=calendar&a=view&event_id=$event_id", "view this event" );
}
$titleBlock->show();

// format dates
$df = $AppUI->getPref('SHDATEFORMAT');

// pull projects
require_once( $AppUI->getModuleClass( 'projects' ) );
$q =& new DBQuery;
$q->addTable('projects', 'p');
$q->addQuery('p.project_id, p.project_name');

$prj =& new CProject;
$allowedProjects = $prj->getAllowedSQL($AppUI->user_id);

if (count($allowedProjects)) { 
	$prj->setAllowedSQL($AppUI->user_id, $q);
}
$q->addOrder('project_name');

$perso_projects = '(' . $AppUI->_('Personal Calendar', UI_OUTPUT_RAW) . ')';
$all_projects = '(' . $AppUI->_('Unspecified Calendar', UI_OUTPUT_RAW) . ')';
$projects = arrayMerge(  array( 0 => $all_projects ), $q->loadHashList() );
$projects = arrayMerge( array( -1 => $perso_projects ), $projects );

$tasks = array('' => '');
if ($obj->event_project)
{
	$q->addQuery('task_id, task_name');
	$q->addTable('tasks');
	$q->addWhere('task_project = ' . $obj->event_project);
	$tasks = $q->loadHashList();
}

/*
if ($event_id || $is_clash) {
	$start_date = intval( $obj->event_start_date ) ? new CDate( $obj->event_start_date ) : null;
	$end_date = intval( $obj->event_end_date ) ? new CDate( $obj->event_end_date ) : $start_date;
} else {
	$start_date = new CDate( $date );
	$start_date->setTime( 8,0,0 );
	$end_date = new CDate( $date );
	$end_date->setTime( 17,0,0 );
}
*/


$inc = intval(dPgetConfig('cal_day_increment')) ? intval(dPgetConfig('cal_day_increment')) : 30;
if (!$event_id && !$is_clash)
{

	$seldate = new CDate( $date );
	// If date is today, set start time to now + inc
	if ($date == date('Ymd'))
	{
		$h = date('H');
		// an interval after now.
		$min = intval(date('i') / $inc) + 1;
		$min *= $inc;
		if ($min > 60)
		{
			$min = 0;
			$h++;
		}
	}
	if ($h && $h < dPgetConfig('cal_day_end'))
	{
		$seldate->setTime($h, $min, 0);
		$obj->event_start_date = $seldate->format(FMT_TIMESTAMP);
		$seldate->addSeconds( $inc * 60 );
		$obj->event_end_date = $seldate->format(FMT_TIMESTAMP);
	}	
	else
	{
		$seldate->setTime(dPgetConfig('cal_day_start'),0,0);
		$obj->event_start_date = $seldate->format(FMT_TIMESTAMP);
		$seldate->setTime(dPgetConfig('cal_day_end'),0,0);
		$obj->event_end_date = $seldate->format(FMT_TIMESTAMP);
	}
}

$recurs =  array (
	'Never',
	'Hourly',
	'Daily',
	'Weekly',
	'Bi-Weekly',
	'Every Month',
	'Quarterly',
	'Every 6 months',
	'Every Year'
);

$remind = array (
	"900" => '15 mins',
	"1800" => '30 mins',
	"3600" => '1 hour',
	"7200" => '2 hours',
	"14400" => '4 hours',
	"28800" => '8 hours',
	"56600" => '16 hours',
	"86400" => '1 day',
	"172800" => '2 days'
);

// build array of times in 30 minute increments
$times = array();
$t = new CDate();
$t->setTime( 0,0,0 );
if (!defined('LOCALE_TIME_FORMAT'))
  define('LOCALE_TIME_FORMAT', '%I:%M %p');
//$m clashes with global $m (module)
for ($minutes=0; $minutes < ((24 * 60) / $inc); $minutes++) {
	$times[$t->format( "%H%M%S" )] = $t->format( LOCALE_TIME_FORMAT );
	$t->addSeconds( $inc * 60 );
}


require_once $AppUI->getSystemClass("CustomFields");
$custom_fields = New CustomFields( 'calendar', 'addedit', $obj->event_id, "edit" );
$tpl->assign('custom_fields', $custom_fields->getHTML());


$tpl->assign('event_id', $event_id);

$tpl->assign('remind', $remind);
$tpl->assign('recurs', $recurs);
$tpl->assign('times', $times);
$tpl->assign('projects', $projects);
$tpl->assign('tasks', $tasks);
$tpl->assign('types', $types);
$tpl->assign('assigned', $assigned);
$tpl->assign('users', $users);
$tpl->assign('extras', $extras);

$tpl->displayAddEdit($obj);
?>

<script language="javascript">
function submitIt(){
	var form = document.editFrm;
	if (form.event_title.value.length < 1) {
		alert('<?php echo $AppUI->_('Please enter a valid event title',  UI_OUTPUT_JS); ?>');
		form.event_title.focus();
		return;
	}
	if (form.event_start_date.value.length < 1){
		alert('<?php echo $AppUI->_("Please enter a start date", UI_OUTPUT_JS); ?>');
		form.event_start_date.focus();
		return;
	}
	if (form.event_end_date.value.length < 1){
		alert('<?php echo $AppUI->_("Please enter an end date", UI_OUTPUT_JS); ?>');
		form.event_end_date.focus();
		return;
	}
	
	if (form.event_start_date.value > form.event_end_date.value) {
		alert('<?php echo $AppUI->_('Start date must be before end date!',  UI_OUTPUT_JS); ?>');
		form.event_title.focus();
		return;
	}
	if (form.event_start_date.value == form.event_end_date.value && form.end_time.value < form.start_time.value) {
		alert('<?php echo $AppUI->_('Start time must be before end time!',  UI_OUTPUT_JS); ?>');
		form.event_title.focus();
		return;
	}

		
  if ( (!(form.event_times_recuring.value>0))
    && (form.event_recurs[0].selected!=true) ) {
    alert("<?php echo $AppUI->_('Please enter number of recurrences', UI_OUTPUT_JS); ?>");
    form.event_times_recuring.value=1;
    form.event_times_recuring.focus();
    return;
  }
	// Ensure that the assigned values are selected before submitting.
	var assigned = form.assigned;
	var len = assigned.length;
	var users = form.event_assigned;
	users.value = "";
	for (var i = 0; i < len; i++) {
		if (i)
			users.value += ",";
		users.value += assigned.options[i].value;
	}
	form.submit();
}

var calendarField = '';

function popCalendar( field ){
	calendarField = field;
	idate = eval( 'document.editFrm.event_' + field + '.value' );
	window.open( 'index.php?m=public&a=calendar&dialog=1&callback=setCalendar&date=' + idate, 'calwin', 'top=250,left=250,width=250, height=220, scollbars=false' );
}

/**
 *	@param string Input date in the format YYYYMMDD
 *	@param string Formatted date
 */
function setCalendar( idate, fdate ) {
	fld_date = eval( 'document.editFrm.event_' + calendarField );
	fld_fdate = eval( 'document.editFrm.' + calendarField );
	fld_date.value = idate;
	fld_fdate.value = fdate;

	// set end date automatically with start date if start date is after end date
	if (calendarField == 'start_date') {
		if( document.editFrm.end_date.value < idate) {
			document.editFrm.event_end_date.value = idate;
			document.editFrm.end_date.value = fdate;
		}
	}
}

function addUser() {
	var form = document.editFrm;
	var fl = form.resources.length -1;
	var au = form.assigned.length -1;
	//gets value of percentage assignment of selected resource

	var users = "x";

	//build array of assiged users
	for (au; au > -1; au--) {
		users = users + "," + form.assigned.options[au].value + ","
	}

	//Pull selected resources and add them to list
	for (fl; fl > -1; fl--) {
		if (form.resources.options[fl].selected && users.indexOf( "," + form.resources.options[fl].value + "," ) == -1) {
			t = form.assigned.length
			opt = new Option( form.resources.options[fl].text, form.resources.options[fl].value);
			form.assigned.options[t] = opt
		}
	}

}

function removeUser() {
	var form = document.editFrm;
	fl = form.assigned.length -1;
	for (fl; fl > -1; fl--) {
		if (form.assigned.options[fl].selected) {
			//remove from hperc_assign
			var selValue = form.assigned.options[fl].value;			
			var re = ".*("+selValue+"=[0-9]*;).*";
			form.assigned.options[fl] = null;
		}
	}
}

</script>