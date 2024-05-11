<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz

// Settings
include("/etc/phbackup/opt.php");

// Functions
try {
    require '/etc/phbackup/functions.php';
}
catch (Error $e) {
    // debugging example:
    die('Caught error => ' . $e->getMessage());
}


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);

// Getting vars from DB
$script_vars = get_script_vars($db);
isset($script_vars['version']) ? $script_ver = $script_vars['version'] : $script_ver = 1;
isset($script_vars['version_text']) ? $script_ver_text = $script_vars['version_text'] : $script_ver_text = "pre-1.5.0";


function DrawHost($host_data, $host_vars) {

    global $db, $default_include_paths, $default_exclude_paths, $default_pre_script, $default_pre_schedule;

    isset($host_data['group_id']) ? $groupid=$host_data['group_id'] : $groupid=100000;
    $sql="SELECT * FROM host_groups";
    $res = $db->query($sql);
    $group_select="<select name='group'>";
    while ($row = $res->fetch_array()) {
        $row['id'] == $groupid ? $selected = "selected" : $selected = "";
        $group_select .= "<option $selected value=".$row['id'].">".$row['name']."</option>";
    }
    $group_select.="</select>";

    $functions = get_defined_functions();
    $func_select="<select name='backup_function'>";
    foreach ($functions['user'] as $func)
    {
        preg_match ('/(backup_.*)/', $func, $matches);
        if (!empty($matches)) {
            $host_vars['backup_function'] == $matches[1] ? $selected = "selected" : $selected = "";
            $func_select .= "<option $selected value=".$matches[1].">".$matches[1]."</option>";
        }
    }
    $func_select.="</select>";


    if (isset($host_data['name'])) $name=$host_data['name'];
    if (isset($host_data['description'])) $description=$host_data['description'];
    if (isset($host_data['ip'])) $ip=$host_data['ip'];
    if (isset($host_data['port'])) $port=$host_data['port']; else $port=22;
    if (isset($host_data['user'])) $user=$host_data['user']; else $user="root";
    if (isset($host_data['ssh_key'])) $ssh_key=$host_data['ssh_key'];
    if (isset($host_data['time_slots'])) $time_slots=$host_data['time_slots']; else $time_slots="2-6";
    if (isset($host_data['enabled']) && $host_data['enabled']==1) $enabled="checked"; else $enabled="";
    if (isset($host_vars['backup_period'])) $bperiod=$host_vars['backup_period']; else $bperiod=24;
    if (isset($host_vars['backup_keep_period'])) $backup_keep_period=$host_vars['backup_keep_period']; else $backup_keep_period=30;
    if (isset($host_vars['rsync_options'])) $rsync_options=$host_vars['rsync_options']; else $rsync_options="-vbrltz";
    if (isset($host_vars['include_paths'])) $include_paths=base64_decode($host_vars['include_paths']); else $include_paths=$default_include_paths;
    if (isset($host_vars['exclude_paths'])) $exclude_paths=base64_decode($host_vars['exclude_paths']); else $exclude_paths=$default_exclude_paths;
    if (isset($host_vars['pre_script'])) $pre_script=base64_decode($host_vars['pre_script']); else $pre_script=$default_pre_script;
    if (isset($host_vars['pre_schedule'])) $pre_schedule=base64_decode($host_vars['pre_schedule']); else $pre_schedule=$default_pre_schedule;
    echo "<tr><td class='ip1'>Host name</td><td class='ip1'><input type='text' size='100' name='name' value='$name'></td></tr>";
    echo "<tr><td class='ip1'>Host description</td><td class='ip1'><input type='text' size='100' name='description' value='$description'></td></tr>";
    echo "<tr><td class='ip1'>Host IP</td><td class='ip1'><input type='text' size='100' name='ip' value='$ip'></td></tr>";
    echo "<tr><td class='ip1'>Host group<br><span class=hint>Groups can be backed up into separate subdirectories</span></td><td class='ip1'>$group_select</td></tr>";
    echo "<tr><td class='ip1'>Host port<br><span class=hint>Port at host to connect to (22 - SSH, 23 - Telnet)</span></td><td class='ip1'><input type='text' size='100' name='port' value='$port'></td></tr>";
    echo "<tr><td class='ip1'>Host user<br><span class=hint>Username for connection</span></td><td class='ip1'><input type='text' size='100' name='user' value='$user'></td></tr>";
    echo "<tr><td class='ip1'>Host key/password<br><span class=hint>Password or SSH key for backup user</span></td><td class='ip1'><input type='text' size='100' name='ssh_key' value='$ssh_key'></td></tr>";
    echo "<tr><td class='ip1'>Backup function<br><span class=hint>Which backup function to use for this device</span></td><td class='ip1'>$func_select</td></tr>";
    echo "<tr><td class='ip1'>Backup period<br><span class=hint>How often to do backups, hours</span></td><td class='ip1'><input type='text' size='100' name='backup_period' value='$bperiod'></td></tr>";
    echo "<tr><td class='ip1'>Backup time slots<br><span class=hint>Hours of day, during which backups are allowed, in comma separated, dash-delimited periods, like 0-2,4-7,8-11</span></td><td class='ip1'><input type='text' size='100' name='timestr' value='".$time_slots."'></td></tr>";
    echo "<tr><td class='ip1'>Backup keep period<br><span class=hint>For which time to store backups, days</span></td><td class='ip1'><input type='text' size='100' name='backup_keep_period' value='$backup_keep_period'></td></tr>";
    echo "<tr><td class='ip1'>Rsync options</td><td class='ip1'><input type='text' size='100' name='rsync_options' value='$rsync_options'></td></tr>";
    echo "<tr><td class='ip1'>Pre-backup script<br><span class='hint'>A script which prepares data on the target server - dumps databases etc.</span><br><br><p style=\"color:#ff0000;\"><b>WARNING: this script will be run as root, <br>so it potentially can break your system!<br><br>Test it first and run very carefully!</b></p></td><td class='ip1'><textarea name='pre_script' cols=70 rows=10>$pre_script</textarea></td></tr>";
    echo "<tr><td class='ip1'>Pre-backup script schedule<br><span class='hint'>Crontab entity for pre-backup script. <br>Script name is /opt/phbackup.sh, cron file is being placed inside /etc/cron.d</span></td><td class='ip1'><input type='text' size='100' name='pre_schedule' value='".$pre_schedule."'></td></tr>";
    echo "<tr><td class='ip1'>Install pre-backup script<br><span class=hint>Install new script or update existing script and cron settings</span></td><td class='ip1'><input type='checkbox' name='pre_install' unchecked></td></tr>";
    echo "<tr><td class='ip1'>Paths to include in backup<br><span class='hint'>One path - one line</span></td><td class='ip1'><textarea name='include_paths' cols=70 rows=10>$include_paths</textarea></td></tr>";
    echo "<tr><td class='ip1'>Paths to exclude from backup<br><span class='hint'>One path - one line</span></td><td class='ip1'><textarea name='exclude_paths' cols=70 rows=10>$exclude_paths</textarea></td></tr>";
    echo "<tr><td class='ip1'>Enable backups<br><span class=hint>To do backups or no</span></td><td class='ip1'><input type='checkbox' name='enabled' $enabled></td></tr>";
}


function normalize_time_periods($timestr) {
	$times=explode(",",$timestr);
//echo "timestr = '$timestr'<br>";
	$updated_times="";
	foreach ($times as $time) {
//echo "time = '$time'<br>";
	    if($hours=explode("-",$time)) {
		if ($hours[0] > 24) $hours[0]=24;
		if ($hours[1] > 24) $hours[1]=24;
		if ($hours[0] < 0) $hours[0]=0;
		if ($hours[1] < 0) $hours[1]=0;
		if(empty($hours[1]) && !empty($hours[0])) $hours[1]=$hours[0]+1;
		if(empty($hours[0]) && !empty($hours[1]) && $hours[0]!=0 ) $hours[0]=$hours[1]-1;
		if ( $hours[1]>$hours[0] ) {
	    	    if($updated_times=="") $updated_times=$hours[0]."-".$hours[1]; 
	    	    else $updated_times.=",".$hours[0]."-".$hours[1];
		}
	    }
	}
	return($updated_times);
}



?>





<html>
<head>
<title>PHBackup <?php echo $script_ver_text; ?></title>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<h1>PHBackup <?php echo $script_ver_text; ?></h1>
<form method="GET" action="index.php">
<a href = <?php if (!empty($_GET['group'])) echo "'index.php?group=".$_GET['group']."'"; else echo "'index.php'"; ?> class="no-underline">🏠 Home page</a>
&nbsp;&nbsp;&nbsp;&nbsp;
<a href = <?php if (!empty($_GET['group'])) echo "'index.php?action=add&group=".$_GET['group']."'"; else echo "'index.php?action=add'"; ?> class="no-underline">➕ Add host</a>
&nbsp;&nbsp;&nbsp;&nbsp;
<a href="zabbix.php" class="no-underline">&#128203; Zabbix stats</a>
&nbsp;&nbsp;&nbsp;&nbsp;
<label for="group">Host group:</label>
<select name="group" onchange="this.form.submit()">
<option value=10000>All</option>
<?php

    $sql="SELECT * FROM host_groups";
    $res = $db->query($sql);
    while ($row = $res->fetch_array()) {
        !empty($_GET['group']) && $_GET['group'] == $row['id'] ? $selected = "selected" : $selected = "";
        echo "<option $selected value=".$row['id'].">".$row['name']."</option>";
    }

?>
</select>
</form>
<hr>





<?php

// Checking if we need an upgrade

$upgrade_versions = is_upgraded($db);
$upgrade_path = $script_ver_text;

if (is_array($upgrade_versions)) {
    foreach ($upgrade_versions as $short => $version ) { if ($short > $script_ver) $upgrade_path .= " => $version"; }

    echo "<center><b>An upgrade is needed: $upgrade_path<br><br> Please upgrade your PHBackup installation before use!</b></center>";
    die();
};




// Performing actions
if (!empty($_POST['confirm']) && $_POST['confirm']=="yes")
{
    switch ((string)$_POST['action']) {
        case "add":
		(isset($_POST['enabled']) && $_POST['enabled']=="on") ? $enabled=1 : $enabled=0;
		( isset($_POST['pre_install']) && $_POST['pre_install']=="on" ) ? $pre_install="1" : $pre_install="0";

		$updated_times=normalize_time_periods($_POST['timestr']);

		$sql="SELECT id FROM hosts WHERE name='".$_POST['name']."' and description='".$_POST['description']."' and ip='".$_POST['ip']."';";
	        $res = $db->query($sql);
	        if ($res->num_rows == 0) {

			$sql="insert into hosts (name, description, group_id, ip, port, user, ssh_key, enabled, time_slots, pre_install) values (
			'".$_POST['name']."',
			'".$_POST['description']."',
			'".$_POST['group']."',
			'".$_POST['ip']."',
			".$_POST['port'].",
			'".$_POST['user']."',
			'".$_POST['ssh_key']."',".$enabled.",'".$updated_times."', '".$pre_install."')";
	                $res = $db->query($sql);
	                $new_host_id = $db->insert_id;
	
	                $include_paths = base64_encode($_POST['include_paths']);
	                $exclude_paths = base64_encode($_POST['exclude_paths']);
	                $pre_script = base64_encode($_POST['pre_script']);
	                $pre_schedule = base64_encode($_POST['pre_schedule']);

	                $sql = "INSERT INTO host_vars (host,var,value) VALUES
	                ($new_host_id, 'include_paths', '$include_paths'),
	                ($new_host_id, 'exclude_paths', '$exclude_paths'),
	                ($new_host_id, 'pre_script', '$pre_script'),
	                ($new_host_id, 'pre_schedule', '$pre_schedule'),
	                ($new_host_id, 'backup_function', '".$_POST['backup_function']."'),
	                ($new_host_id, 'backup_period', ".$_POST['backup_period']."),
	                ($new_host_id, 'backup_keep_period', ".$_POST['backup_keep_period']."),
	                ($new_host_id, 'rsync_options', '".$_POST['rsync_options']."')
	                ";
	                $res = $db->query($sql);
	                echo "<center><h3>Host <span>".$_POST['name']." (".$_POST['ip'].", id $new_host_id)</span> was successfully added";
		}
		else echo "<center><h3>Host <span>".$_POST['name']." (".$_POST['ip'].")</span> is already in database!";
		break;





	// Editing host
        case "edit":
		$enabled=0;
		if(!empty($_POST['enabled']) && $_POST['enabled']=="on") $enabled=1;

//		$times=explode(",",$_POST['timestr']);
//		$updated_times=normalize_time_periods($times);
		$updated_times=normalize_time_periods($_POST['timestr']);

		// If checkbox is on, scheduling script install
		(isset($_POST['pre_install']) && $_POST['pre_install']=="on") ? $pre_install="1" : $pre_install="0";

		$sql="UPDATE hosts SET 
		    name='".$_POST['name']."',
		    description='".$_POST['description']."',
		    group_id='".$_POST['group']."',
		    ip='".$_POST['ip']."',
		    port=".$_POST['port'].",
		    user='".$_POST['user']."',
		    ssh_key='".$_POST['ssh_key']."',
		    time_slots='".$updated_times."',
		    pre_install='".$pre_install."',
		    enabled=".$enabled."
		WHERE id=".$_POST['id'];
                $res = $db->query($sql);

                $include_paths = base64_encode($_POST['include_paths']);
                $exclude_paths = base64_encode($_POST['exclude_paths']);
                $pre_script = base64_encode($_POST['pre_script']);
                $pre_schedule = base64_encode($_POST['pre_schedule']);

		$db->query("UPDATE host_vars SET value='$pre_script' WHERE host=".$_POST['id']." AND var='pre_script'");
		$db->query("UPDATE host_vars SET value='$pre_schedule' WHERE host=".$_POST['id']." AND var='pre_schedule'");
		$db->query("UPDATE host_vars SET value='$include_paths' WHERE host=".$_POST['id']." AND var='include_paths'");
		$db->query("UPDATE host_vars SET value='$exclude_paths' WHERE host=".$_POST['id']." AND var='exclude_paths'");
		$db->query("UPDATE host_vars SET value='".$_POST['backup_function']."' WHERE host=".$_POST['id']." AND var='backup_function'"); 
		$db->query("UPDATE host_vars SET value='".$_POST['backup_period']."' WHERE host=".$_POST['id']." AND var='backup_period'");
		$db->query("UPDATE host_vars SET value='".$_POST['backup_keep_period']."' WHERE host=".$_POST['id']." AND var='backup_keep_period'");
		$db->query("UPDATE host_vars SET value='".$_POST['rsync_options']."' WHERE host=".$_POST['id']." AND var='rsync_options'");

                echo "<center><h3>Host <span>".$_POST['name']." (".$_POST['ip'].", id ".$_POST['id'].")</span> was successfully updated";
		break;

        case "delete":
		$sql="delete from hosts where id=".$_POST['id'];
		$res=$db->query($sql);
		$sql="delete from host_vars where host=".$_POST['id'];
		$res=$db->query($sql);
                echo "<center><h3>Host <span>".$_POST['id']."</span> was successfully removed";
                break;

        case "unlock":
		$sql="UPDATE hosts SET worker=-1, status=0 WHERE id=".$_POST['id'];
		$res=$db->query($sql);
                echo "<center><h3>Host <span>".$_POST['id']."</span> was successfully unlocked";
                break;

        case "backup":
		$sql="UPDATE hosts SET worker=-1, status=0, backup_now=1 WHERE id=".$_POST['id'];
		$res=$db->query($sql);
                echo "<center><h3>Host <span>".$_POST['id']."</span> was successfully unlocked, backup will start soon";
                break;

    }
}







// Drawing everything
if (empty($_GET['action'])) {

    // Listing hosts
    $sort_order="asc";
    $sort_order1="desc";
    if (!empty($_GET['order'])) {
        $_GET['order'] == "desc" ? $sort_order="desc" : $sort_order="asc";
        $_GET['order'] == "desc" ? $sort_order1="asc" : $sort_order1="desc";
    }
    empty($_GET['order-by']) ? $order_by="name" : $order_by=$_GET['order-by'];
    $order="$order_by $sort_order";

    empty($_GET['group']) ? $group=10000 : $group=$_GET['group'];

    if ($group!=10000)
    {
        $sql="SELECT * FROM host_groups WHERE id=$group";
        $res = $db->query($sql);
        $row = $res->fetch_array();
        $groupname = $row['name'];
        $groupstr = "WHERE group_id=".$row['id'];
    }
    else {
        $groupname = "All";
        $groupstr = "";
    }

    echo "<h2>Hosts list ($groupname)</h2>";

    $sql="SELECT * FROM hosts $groupstr ORDER BY $order;";
    $res = $db->query($sql);
    if ($res->num_rows > 0) {
        echo "<table border='0' cellspacing='5' cellpadding='5' width='100%'><tr>
    	    <th class='ip2'>Host <a href='?group=$group&order-by=name&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Status <a href='?group=$group&order-by=status&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Last backup (+time slots) <a href='?group=$group&order-by=last_backup&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Description <a href='?group=$group&order-by=description&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Actions</th></tr>";
        $i=0;
        $color=1;
        $status = array (-1 => "Unknown", 0 => "Ok", 1 => "Backing up", 2 => "Error", 3 => "Backup too old");
	$status_arr = array();

        while ($row = $res->fetch_array()) {
            echo '<tr>';
            echo '<td class="ip'.$color.'"><a href="index.php?action=edit&host='.$row['id'].'">'.$row['name'].'</a></td>';

            if ($row['enabled']==1) $enablestr="Enabled, "; else $enablestr="Disabled, ";
            if ($row['pre_install']==1) $prestr="<br><span class='hint'>Pre-script install pending</span>"; else $prestr="";
            if ($row['pre_install']==2) $prestr="<br><span class='hint red'>Pre-script install failed</span>"; else $prestr="";
            if ($row['worker']>=0) $workerstr=" (".$row['worker'].")"; else $workerstr="";
            echo '<td class="ip'.$color.' status'.$row['status'].' align-center">'.$enablestr.$status[$row['status']].$workerstr.$prestr.'</td>';

            echo '<td class="ip'.$color.' status'.$row['status'].' align-center">'.$row['last_backup'];
            if($row['status']>1) echo '<br><span class=hint>Last try: '.$row['last_backup'].'</span>';
            echo '<br><span class=hint>Next try: '.$row['next_try'].'</span>';
//	    echo '<br><span class=hint>Time slots: '.$row['time_slots'].'</span>';
            echo '</td>';

            echo '<td class="ip'.$color.'">'.$row['description'].'</td>';
            echo '<td class="ip'.$color.'">
            <a href="index.php?host='.$row['id'].'&action=backup" class="red no-underline" title="Backup now!">&#128190;</a>
            <a href="index.php?host='.$row['id'].'&action=edit" class="red no-underline" title="Edit host">&#128736;</a>
            <a href="index.php?host='.$row['id'].'&action=unlock" class="red no-underline" title="Unlock host">&#128275;</a>
            <a href="index.php?host='.$row['id'].'&action=log" class="red no-underline" title="Last backup log">&#128220;</a>
            <a href="index.php?host='.$row['id'].'&action=delete" class="red no-underline" title="Delete host">&#10060;</a>
            </td>';
            echo '</tr>';
//            if ($color==0) $color++; else $color=0;
            $i++;
	    isset($status_arr[$row['status']]) ? $status_arr[$row['status']]++ : $status_arr[$row['status']] = 1;
        }
        echo "</table>";
        echo "<br><b>Total:</b> $i hosts<br>";
        foreach($status_arr as $stat => $num) {
	    echo "<span class='status".$stat."'><b>".$status[$stat]."</b></span> - $num<br>";
        }
    }

}







else {
    // Drawing actions forms
    if (!empty($_GET['action']))
    {
	$action = $_GET['action'];
	$host_id = $_GET['host'];
        if(!empty($new_host_id)) { $host_id=$new_host_id; $action="edit"; }


	if ($action !="add") {
            // Getting host vars
            $sql="select * from hosts where id=".$host_id.";";
            $res = $db->query($sql);
            $host_data = $res->fetch_array();

            $sql="select * from host_vars where host=".$host_id.";";
            $res = $db->query($sql);
            while ($row = $res->fetch_array()) {
                $host_vars[$row['var']] = $row['value'];
            }
	}

        if (!empty($_GET['group'])) $grouplink="group=".$_GET['group']; elseif (!empty($host_data['group_id'])) $grouplink="group=".$host_data['group_id']; else $grouplink="";

        switch ((string)$action) {
            case 'delete':
        	// Delete host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='delete'>
                <center><h3>You are going to delete host<br><br>
                <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br><br>
                Are you sure?<br><br>
                <input type='submit' value='Yes, I am sure'>
                <a href='index.php?$grouplink'>No, go back</a>
                </center>
                </form>";
                break;
            case 'unlock':
        	// Unlock host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='unlock'>";
		if($host_data['worker']>-1)
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is locked by backup worker ".$host_data['worker']." since ".$host_data['backup_started']."
    	    	    Do you want to unlock it?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php?$grouplink'>No, go back</a>
    		    </center>
    	            </form>";
    	        else
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is not locked by any backup worker.<br><br>
    	    	    <a href='index.php?$grouplink'>Go back to the host list</a>
    		    </center>
    	            </form>";
                break;
            case 'backup':
        	// Backup host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='backup'>";
		if($host_data['worker']>0)
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is locked by backup worker ".$host_data['worker']." since ".$host_data['backup_started']."
    	    	    Do you want to unlock it and start a new backup?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php?$grouplink'>No, go back</a>
    		    </center>
    	            </form>";
    	        else
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is not locked by any backup worker.<br><br>
    	    	    Do you want to unlock it and start a new backup?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php?$grouplink'>No, go back</a>
    		    </center>
    	            </form>";
                break;
            case 'edit':
        	// Edit host
                echo "<form method='post' action='index.php?$grouplink'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='edit'>
                <input type='hidden' name='confirm' value='yes'>
                <h2>Edit host ".$host_data['name']." (".$host_data['ip'].")</h2>";
	        echo "<table border='0' cellspacing='5' cellpadding='5' width='100%'>";
        	DrawHost($host_data, $host_vars);
	        echo "<tr><td></td><td class='ip1'><input type='submit' value='Apply changes'></td></tr></table>";
                break;
            case 'log':
        	// Edit host
                echo "<h2>Last backup log for ".$host_data['name']." (".$host_data['ip'].")</h2>";
		if (file_exists("$backup_path/".$host_data['name']."/backup.log"))
		{
		    echo "<pre>";
		    $fp = @fopen("$backup_path/".$host_data['name']."/backup.log", "r");
		    if ($fp) {
		        while (($buffer = fgets($fp, 4096)) !== false) {
		            echo $buffer;
		        }
		        if (!feof($fp)) {
		            echo "Error: unexpected fgets() fail\n";
		        }
		        fclose($fp);
		    }
		    else echo "Can not open backup log!";
		    echo "</pre>";
		}
		else echo "No backup log available!";
                break;
            default:
        	// Add new host
                echo "<form method='post' action='index.php?$grouplink'>
                <input type='hidden' name='action' value='add'>
                <input type='hidden' name='confirm' value='yes'>
                <h2>Add new host</h2>";
	        echo "<table border='0' cellspacing='5' cellpadding='5' width='100%'>";
        	DrawHost(null, null);
	        echo "<tr><td></td><td class='ip1'><input type='submit' value='Add new host'></td></tr></table>";
        }
    }
}



$db->close();

?>



</body>
</html>