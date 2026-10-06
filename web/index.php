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


// Session is used only for CSRF protection
session_set_cookie_params(array('httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])));
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf_input = "<input type='hidden' name='csrf' value='".h($_SESSION['csrf'])."'>";


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);

// Getting vars from DB
$script_vars = get_script_vars($db);
isset($script_vars['version']) ? $script_ver = $script_vars['version'] : $script_ver = 1;
isset($script_vars['version_text']) ? $script_ver_text = $script_vars['version_text'] : $script_ver_text = "pre-1.5.0";

// Current group filter
$cur_group = (isset($_GET['group']) && ctype_digit((string)$_GET['group'])) ? (int)$_GET['group'] : 0;


function DrawHost($host_data, $host_vars) {

    global $db, $default_include_paths, $default_exclude_paths, $default_pre_script, $default_pre_schedule;

    isset($host_data['group_id']) ? $groupid=$host_data['group_id'] : $groupid=100000;
    $sql="SELECT * FROM host_groups";
    $res = $db->query($sql);
    $group_select="<select name='group'>";
    while ($row = $res->fetch_array()) {
        $row['id'] == $groupid ? $selected = "selected" : $selected = "";
        $group_select .= "<option $selected value='".h($row['id'])."'>".h($row['name'])."</option>";
    }
    $group_select.="</select>";

    $functions = get_defined_functions();
    $func_select="<select name='backup_function'>";
    foreach ($functions['user'] as $func)
    {
        preg_match ('/^(backup_.*)/', $func, $matches);
        if (!empty($matches)) {
            isset($host_vars['backup_function']) && $host_vars['backup_function'] == $matches[1] ? $selected = "selected" : $selected = "";
            $func_select .= "<option $selected value='".h($matches[1])."'>".h($matches[1])."</option>";
        }
    }
    $func_select.="</select>";


    $name = $host_data['name'] ?? "";
    $description = $host_data['description'] ?? "";
    $ip = $host_data['ip'] ?? "";
    $port = $host_data['port'] ?? 22;
    $user = $host_data['user'] ?? "root";
    $ssh_key = $host_data['ssh_key'] ?? "";
    $time_slots = $host_data['time_slots'] ?? "2-6";
    if (isset($host_data['enabled']) && $host_data['enabled']==1) $enabled="checked"; else $enabled="";
    $bperiod = $host_vars['backup_period'] ?? 24;
    $backup_keep_period = $host_vars['backup_keep_period'] ?? 30;
    $rsync_options = $host_vars['rsync_options'] ?? DEFAULT_RSYNC_OPTIONS;
    if (isset($host_vars['include_paths'])) $include_paths=base64_decode($host_vars['include_paths']); else $include_paths=$default_include_paths;
    if (isset($host_vars['exclude_paths'])) $exclude_paths=base64_decode($host_vars['exclude_paths']); else $exclude_paths=$default_exclude_paths;
    if (isset($host_vars['pre_script'])) $pre_script=base64_decode($host_vars['pre_script']); else $pre_script=$default_pre_script;
    if (isset($host_vars['pre_schedule'])) $pre_schedule=base64_decode($host_vars['pre_schedule']); else $pre_schedule=$default_pre_schedule;
    echo "<tr><td class='ip1'>Host name<br><span class=hint>Latin letters, digits, dots, dashes and underscores. Used as backup directory name</span></td><td class='ip1'><input type='text' size='100' name='name' value='".h($name)."'></td></tr>";
    echo "<tr><td class='ip1'>Host description</td><td class='ip1'><input type='text' size='100' name='description' value='".h($description)."'></td></tr>";
    echo "<tr><td class='ip1'>Host IP</td><td class='ip1'><input type='text' size='100' name='ip' value='".h($ip)."'></td></tr>";
    echo "<tr><td class='ip1'>Host group<br><span class=hint>Groups can be backed up into separate subdirectories</span></td><td class='ip1'>$group_select</td></tr>";
    echo "<tr><td class='ip1'>Host port<br><span class=hint>Port at host to connect to (22 - SSH, 23 - Telnet)</span></td><td class='ip1'><input type='text' size='100' name='port' value='".h($port)."'></td></tr>";
    echo "<tr><td class='ip1'>Host user<br><span class=hint>Username for connection</span></td><td class='ip1'><input type='text' size='100' name='user' value='".h($user)."'></td></tr>";
    echo "<tr><td class='ip1'>Host key/password<br><span class=hint>Password for backup user (used by switch backup functions)</span></td><td class='ip1'><input type='password' size='100' name='ssh_key' autocomplete='new-password' value='".h($ssh_key)."'></td></tr>";
    echo "<tr><td class='ip1'>Backup function<br><span class=hint>Which backup function to use for this device</span></td><td class='ip1'>$func_select</td></tr>";
    echo "<tr><td class='ip1'>Backup period<br><span class=hint>How often to do backups, hours</span></td><td class='ip1'><input type='text' size='100' name='backup_period' value='".h($bperiod)."'></td></tr>";
    echo "<tr><td class='ip1'>Backup time slots<br><span class=hint>Hours of day, during which backups are allowed, in comma separated, dash-delimited periods, like 0-2,4-7,8-11. Periods over midnight like 22-3 are allowed</span></td><td class='ip1'><input type='text' size='100' name='timestr' value='".h($time_slots)."'></td></tr>";
    echo "<tr><td class='ip1'>Backup keep period<br><span class=hint>For which time to store backups, days. The newest backups are always kept, even if they are older</span></td><td class='ip1'><input type='text' size='100' name='backup_keep_period' value='".h($backup_keep_period)."'></td></tr>";
    echo "<tr><td class='ip1'>Rsync options<br><span class=hint>Default: ".h(DEFAULT_RSYNC_OPTIONS).". Options with values should be written as --option=value</span></td><td class='ip1'><input type='text' size='100' name='rsync_options' value='".h($rsync_options)."'></td></tr>";
    echo "<tr><td class='ip1'>Pre-backup script<br><span class='hint'>A script which prepares data on the target server - dumps databases etc.</span><br><br><p style=\"color:#ff0000;\"><b>WARNING: this script will be run as root, <br>so it potentially can break your system!<br><br>Test it first and run very carefully!</b></p></td><td class='ip1'><textarea name='pre_script' cols=70 rows=10>".h($pre_script)."</textarea></td></tr>";
    echo "<tr><td class='ip1'>Pre-backup script schedule<br><span class='hint'>Crontab entity for pre-backup script. <br>Script name is /opt/phbackup.sh, cron file is being placed inside /etc/cron.d</span></td><td class='ip1'><input type='text' size='100' name='pre_schedule' value='".h($pre_schedule)."'></td></tr>";
    echo "<tr><td class='ip1'>Install pre-backup script<br><span class=hint>Install new script or update existing script and cron settings</span></td><td class='ip1'><input type='checkbox' name='pre_install' unchecked></td></tr>";
    echo "<tr><td class='ip1'>Paths to include in backup<br><span class='hint'>One path - one line</span></td><td class='ip1'><textarea name='include_paths' cols=70 rows=10>".h($include_paths)."</textarea></td></tr>";
    echo "<tr><td class='ip1'>Paths to exclude from backup<br><span class='hint'>One path - one line</span></td><td class='ip1'><textarea name='exclude_paths' cols=70 rows=10>".h($exclude_paths)."</textarea></td></tr>";
    echo "<tr><td class='ip1'>Enable backups<br><span class=hint>To do backups or no</span></td><td class='ip1'><input type='checkbox' name='enabled' $enabled></td></tr>";
}


function normalize_time_periods($timestr) {
	$updated_times=array();
	foreach (explode(",", (string)$timestr) as $time) {
	    $time = trim($time);
	    if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $time, $m)) { $start=(int)$m[1]; $end=(int)$m[2]; }
	    elseif (preg_match('/^(\d{1,2})$/', $time, $m)) { $start=(int)$m[1]; $end=$start+1; }
	    else continue;
	    $start = min(24, $start);
	    $end = min(24, $end);
	    if ($end > $start) $updated_times[] = "$start-$end";
	    // Period over midnight, like 22-3
	    elseif ($end < $start) { $updated_times[] = "$start-24"; if ($end > 0) $updated_times[] = "0-$end"; }
	}
	return implode(",", $updated_times);
}


function print_errors($errors) {
	echo "<center><h3 class='red'>Changes were not saved:</h3>";
	foreach ($errors as $error) echo h($error)."<br>";
	echo "<br><a href='javascript:history.back()'>Go back and fix</a></center>";
}


function save_host_vars($db, $host_id, $post) {
	$vars = array(
	    'include_paths' => base64_encode($post['include_paths'] ?? ''),
	    'exclude_paths' => base64_encode($post['exclude_paths'] ?? ''),
	    'pre_script' => base64_encode($post['pre_script'] ?? ''),
	    'pre_schedule' => base64_encode($post['pre_schedule'] ?? ''),
	    'backup_function' => $post['backup_function'],
	    'backup_period' => (int)$post['backup_period'],
	    'backup_keep_period' => (int)$post['backup_keep_period'],
	    'rsync_options' => implode(" ", parse_rsync_options($post['rsync_options'])),
	);
	foreach ($vars as $var => $value) {
	    db_query($db, "INSERT INTO host_vars (host,var,value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)", array($host_id, $var, $value));
	}
}


?>





<html>
<head>
<title>PHBackup <?php echo h($script_ver_text); ?></title>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<h1>PHBackup <?php echo h($script_ver_text); ?></h1>
<form method="GET" action="index.php">
<a href = <?php if ($cur_group) echo "'index.php?group=$cur_group'"; else echo "'index.php'"; ?> class="no-underline">🏠 Home page</a>
&nbsp;&nbsp;&nbsp;&nbsp;
<a href = <?php if ($cur_group) echo "'index.php?action=add&group=$cur_group'"; else echo "'index.php?action=add'"; ?> class="no-underline">➕ Add host</a>
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
        $cur_group == $row['id'] ? $selected = "selected" : $selected = "";
        echo "<option $selected value='".h($row['id'])."'>".h($row['name'])."</option>";
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

    echo "<center><b>An upgrade is needed: ".h($upgrade_path)."<br><br> Please upgrade your PHBackup installation before use!</b></center>";
    die();
};




// Performing actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['confirm']) && $_POST['confirm']=="yes")
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        echo "<center><h3 class='red'>Security token is invalid or expired. Please reload the page and try again.</h3></center>";
        $_POST['action'] = "";
    }
    $post_id = (int)($_POST['id'] ?? 0);

    switch ((string)$_POST['action']) {
        case "add":
		(isset($_POST['enabled']) && $_POST['enabled']=="on") ? $enabled=1 : $enabled=0;
		( isset($_POST['pre_install']) && $_POST['pre_install']=="on" ) ? $pre_install="1" : $pre_install="0";

		$errors = validate_host_form($db, $_POST);
		$updated_times=normalize_time_periods($_POST['timestr'] ?? '');
		if ($updated_times == "") $errors[] = "Backup time slots are invalid, host would never be backed up";
		if (!empty($errors)) { print_errors($errors); break; }

		$res = db_query($db, "SELECT id FROM hosts WHERE name=?", array($_POST['name']));
	        if ($res->num_rows == 0) {

			db_query($db, "insert into hosts (name, description, group_id, ip, port, user, ssh_key, enabled, time_slots, pre_install) values (?,?,?,?,?,?,?,?,?,?)",
			    array($_POST['name'], $_POST['description'] ?? '', (int)$_POST['group'], $_POST['ip'], (int)$_POST['port'], $_POST['user'], $_POST['ssh_key'] ?? '', $enabled, $updated_times, $pre_install));
	                $new_host_id = $db->insert_id;

			save_host_vars($db, $new_host_id, $_POST);
	                echo "<center><h3>Host <span>".h($_POST['name'])." (".h($_POST['ip']).", id $new_host_id)</span> was successfully added";
		}
		else echo "<center><h3>Host <span>".h($_POST['name'])."</span> is already in database!";
		break;





	// Editing host
        case "edit":
		$enabled=0;
		if(!empty($_POST['enabled']) && $_POST['enabled']=="on") $enabled=1;

		$errors = validate_host_form($db, $_POST);
		$res = db_query($db, "SELECT id FROM hosts WHERE name=? AND id<>?", array($_POST['name'] ?? '', $post_id));
		if ($res->num_rows > 0) $errors[] = "Host with this name already exists";
		$updated_times=normalize_time_periods($_POST['timestr'] ?? '');
		if ($updated_times == "") $errors[] = "Backup time slots are invalid, host would never be backed up";
		if (!empty($errors)) { print_errors($errors); break; }

		// If checkbox is on, scheduling script install
		(isset($_POST['pre_install']) && $_POST['pre_install']=="on") ? $pre_install="1" : $pre_install="0";

		db_query($db, "UPDATE hosts SET name=?, description=?, group_id=?, ip=?, port=?, user=?, ssh_key=?, time_slots=?, pre_install=?, enabled=? WHERE id=?",
		    array($_POST['name'], $_POST['description'] ?? '', (int)$_POST['group'], $_POST['ip'], (int)$_POST['port'], $_POST['user'], $_POST['ssh_key'] ?? '', $updated_times, $pre_install, $enabled, $post_id));

		save_host_vars($db, $post_id, $_POST);

                echo "<center><h3>Host <span>".h($_POST['name'])." (".h($_POST['ip']).", id $post_id)</span> was successfully updated";
		break;

        case "delete":
		db_query($db, "delete from hosts where id=?", array($post_id));
		db_query($db, "delete from host_vars where host=?", array($post_id));
                echo "<center><h3>Host <span>$post_id</span> was successfully removed";
                break;

        case "unlock":
		// Status is set to the result of the last finished backup, not to "Ok"
		db_query($db, "UPDATE hosts SET worker=-1, status=last_result WHERE id=?", array($post_id));
                echo "<center><h3>Host <span>$post_id</span> was successfully unlocked";
                break;

        case "backup":
		// A running backup is not interrupted, otherwise a second backup of the same host would start in parallel
		if (db_query($db, "UPDATE hosts SET backup_now=1 WHERE id=? AND worker=-1", array($post_id)) == 1) echo "<center><h3>Backup of host <span>$post_id</span> will start soon";
		else echo "<center><h3 class='red'>Host <span>$post_id</span> is being backed up right now. If it is stuck, unlock it first.";
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
    $sort_columns = array("name", "status", "last_backup", "description");
    $order_by = (isset($_GET['order-by']) && in_array($_GET['order-by'], $sort_columns, true)) ? $_GET['order-by'] : "name";
    $order="$order_by $sort_order";

    $group = $cur_group ? $cur_group : 10000;

    $groupname = "All";
    $res = db_query($db, "SELECT * FROM hosts ORDER BY $order");
    if ($group!=10000)
    {
        $gres = db_query($db, "SELECT * FROM host_groups WHERE id=?", array($group));
        if ($row = $gres->fetch_array()) {
            $groupname = $row['name'];
            $res = db_query($db, "SELECT * FROM hosts WHERE group_id=? ORDER BY $order", array($row['id']));
        }
    }

    echo "<h2>Hosts list (".h($groupname).")</h2>";

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
            $id = (int)$row['id'];
            $st = (int)$row['status'];
            echo '<tr>';
            echo '<td class="ip'.$color.'"><a href="index.php?action=edit&host='.$id.'">'.h($row['name']).'</a></td>';

            if ($row['enabled']==1) $enablestr="Enabled, "; else $enablestr="Disabled, ";
            $prestrs = array(1 => "<br><span class='hint'>Pre-script install pending</span>", 2 => "<br><span class='hint red'>Pre-script install failed</span>", 3 => "<br><span class='hint'>Pre-script installing</span>");
            $prestr = $prestrs[$row['pre_install']] ?? "";
            if ($row['worker']>=0) $workerstr=" (".(int)$row['worker'].")"; else $workerstr="";
            if ($st==1 && (int)$row['last_result']==2) $workerstr.="<br><span class='hint red'>Last backup failed</span>";
            echo '<td class="ip'.$color.' status'.$st.' align-center">'.$enablestr.h($status[$st] ?? $st).$workerstr.$prestr.'</td>';

            echo '<td class="ip'.$color.' status'.$st.' align-center">'.h($row['last_backup']);
            if($st>1) echo '<br><span class=hint>Last try: '.h($row['backup_started']).'</span>';
            echo '<br><span class=hint>Next try: '.h($row['next_try']).'</span>';
//	    echo '<br><span class=hint>Time slots: '.$row['time_slots'].'</span>';
            echo '</td>';

            echo '<td class="ip'.$color.'">'.h($row['description']).'</td>';
            echo '<td class="ip'.$color.'">
            <a href="index.php?host='.$id.'&action=backup" class="red no-underline" title="Backup now!">&#128190;</a>
            <a href="index.php?host='.$id.'&action=edit" class="red no-underline" title="Edit host">&#128736;</a>
            <a href="index.php?host='.$id.'&action=unlock" class="red no-underline" title="Unlock host">&#128275;</a>
            <a href="index.php?host='.$id.'&action=log" class="red no-underline" title="Last backup log">&#128220;</a>
            <a href="index.php?host='.$id.'&action=delete" class="red no-underline" title="Delete host">&#10060;</a>
            </td>';
            echo '</tr>';
//            if ($color==0) $color++; else $color=0;
            $i++;
	    isset($status_arr[$st]) ? $status_arr[$st]++ : $status_arr[$st] = 1;
        }
        echo "</table>";
        echo "<br><b>Total:</b> $i hosts<br>";
        foreach($status_arr as $stat => $num) {
	    echo "<span class='status".$stat."'><b>".h($status[$stat] ?? $stat)."</b></span> - $num<br>";
        }
    }

}







else {
    // Drawing actions forms
    if (!empty($_GET['action']))
    {
	$action = (string)$_GET['action'];
	$host_id = (int)($_GET['host'] ?? 0);
        if(!empty($new_host_id)) { $host_id=$new_host_id; $action="edit"; }

	$host_data = array();
	$host_vars = array();
	if ($action !="add") {
            // Getting host vars
            $res = db_query($db, "select hosts.*, host_groups.path from hosts left join host_groups on hosts.group_id=host_groups.id where hosts.id=?", array($host_id));
            $host_data = $res->fetch_array();
            if (!$host_data) {
                echo "<center><h3>Host not found</h3><a href='index.php'>Go back to the host list</a></center></body></html>";
                $db->close();
                die();
            }

            $res = db_query($db, "select * from host_vars where host=?", array($host_id));
            while ($row = $res->fetch_array()) {
                $host_vars[$row['var']] = $row['value'];
            }
	}

        if ($cur_group) $grouplink="group=$cur_group"; elseif (!empty($host_data['group_id'])) $grouplink="group=".(int)$host_data['group_id']; else $grouplink="";

        $hid = (int)($host_data['id'] ?? 0);
        $hname = h(($host_data['name'] ?? '')." (".($host_data['ip'] ?? '').")");

        switch ($action) {
            case 'delete':
        	// Delete host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>$csrf_input
                <input type='hidden' name='id' value='$hid'>
                <input type='hidden' name='action' value='delete'>
                <center><h3>You are going to delete host<br><br>
                <span class=red>$hname</span><br><br>
                Are you sure?<br><br>
                <input type='submit' value='Yes, I am sure'>
                <a href='index.php?$grouplink'>No, go back</a>
                </center>
                </form>";
                break;
            case 'unlock':
        	// Unlock host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>$csrf_input
                <input type='hidden' name='id' value='$hid'>
                <input type='hidden' name='action' value='unlock'>";
		if($host_data['worker']>-1)
                    echo "<center><h4>Host <span class=red>$hname</span><br>
	            is locked by backup worker ".(int)$host_data['worker']." since ".h($host_data['backup_started'])."<br><br>
    	    	    Unlocking does not stop the running backup process. Stuck hosts are unlocked automatically after 5 minutes.<br>
    	    	    Do you want to unlock it?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php?$grouplink'>No, go back</a>
    		    </center>
    	            </form>";
    	        else
                    echo "<center><h4>Host <span class=red>$hname</span><br>
	            is not locked by any backup worker.<br><br>
    	    	    <a href='index.php?$grouplink'>Go back to the host list</a>
    		    </center>
    	            </form>";
                break;
            case 'backup':
        	// Backup host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>$csrf_input
                <input type='hidden' name='id' value='$hid'>
                <input type='hidden' name='action' value='backup'>";
		if($host_data['worker']>=0)
                    echo "<center><h4>Host <span class=red>$hname</span><br>
	            is being backed up by worker ".(int)$host_data['worker']." since ".h($host_data['backup_started']).".<br><br>
    	    	    <a href='index.php?$grouplink'>Go back to the host list</a>
    		    </center>
    	            </form>";
    	        else
                    echo "<center><h4>Host <span class=red>$hname</span><br>
	            is not locked by any backup worker.<br><br>
    	    	    Do you want to start a new backup now?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php?$grouplink'>No, go back</a>
    		    </center>
    	            </form>";
                break;
            case 'edit':
        	// Edit host
                echo "<form method='post' action='index.php?$grouplink'>
                <input type='hidden' name='id' value='$hid'>
                <input type='hidden' name='action' value='edit'>
                <input type='hidden' name='confirm' value='yes'>$csrf_input
                <h2>Edit host $hname</h2>";
	        echo "<table border='0' cellspacing='5' cellpadding='5' width='100%'>";
        	DrawHost($host_data, $host_vars);
	        echo "<tr><td></td><td class='ip1'><input type='submit' value='Apply changes'></td></tr></table>";
                break;
            case 'log':
        	// Show log
                echo "<h2>Last backup log for $hname</h2>";
		$logfile = (valid_host_name($host_data['name']) && valid_group_path($host_data['path'])) ? host_backup_path($host_data)."/backup.log" : "";
		if ($logfile != "" && file_exists($logfile))
		{
		    echo "<pre>";
		    $fp = @fopen($logfile, "r");
		    if ($fp) {
		        while (($buffer = fgets($fp, 4096)) !== false) {
		            echo h($buffer);
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
                <input type='hidden' name='confirm' value='yes'>$csrf_input
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
