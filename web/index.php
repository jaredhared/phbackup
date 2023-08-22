<?php

// PHBackup backup system
// Copyright (c) 2023, Host4Biz

// Settings
include("/etc/phbackup/opt.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db=mysqli_connect($db_host,$db_user,$db_pass, $db_name);


function DrawHost($host_data, $host_vars) {

    global $default_include_paths, $default_exclude_paths;

    if (isset($host_data['name'])) $name=$host_data['name'];
    if (isset($host_data['description'])) $description=$host_data['description'];
    if (isset($host_data['ip'])) $ip=$host_data['ip'];
    if (isset($host_data['port'])) $port=$host_data['port']; else $port=22;
    if (isset($host_data['user'])) $user=$host_data['user']; else $user="root";
    if (isset($host_data['ssh_key'])) $ssh_key=$host_data['ssh_key'];
    if ($host_data['enabled']==1) $enabled="checked";
    if (isset($host_vars['backup_period'])) $bperiod=$host_vars['backup_period']; else $bperiod=24;
    if (isset($host_vars['backup_keep_period'])) $backup_keep_period=$host_vars['backup_keep_period']; else $backup_keep_period=30;
    if (isset($host_vars['rsync_options'])) $rsync_options=$host_vars['rsync_options']; else $rsync_options="-vbrltz";
    if (isset($host_vars['include_paths'])) $include_paths=base64_decode($host_vars['include_paths']); else $include_paths=$default_include_paths;
    if (isset($host_vars['exclude_paths'])) $exclude_paths=base64_decode($host_vars['exclude_paths']); else $exclude_paths=$default_exclude_paths;

    echo "<tr><td class='ip1'>Host name</td><td class='ip1'><input type='text' size='100' name='name' value='$name'></td></tr>";
    echo "<tr><td class='ip1'>Host description</td><td class='ip1'><input type='text' size='100' name='description' value='$description'></td></tr>";
    echo "<tr><td class='ip1'>Host IP</td><td class='ip1'><input type='text' size='100' name='ip' value='$ip'></td></tr>";
    echo "<tr><td class='ip1'>Host SSH port</td><td class='ip1'><input type='text' size='100' name='port' value='$port'></td></tr>";
    echo "<tr><td class='ip1'>Host SSH user</td><td class='ip1'><input type='text' size='100' name='user' value='$user'></td></tr>";
    echo "<tr><td class='ip1'>Host SSH key<br><span class=hint>SSH key for backup user</span></td><td class='ip1'><input type='text' size='100' name='ssh_key' value='$ssh_key'></td></tr>";
    echo "<tr><td class='ip1'>Backup period<br><span class=hint>How often to do backups, hours</span></td><td class='ip1'><input type='text' size='100' name='backup_period' value='$bperiod'></td></tr>";
    echo "<tr><td class='ip1'>Backup keep period<br><span class=hint>For which time to store backups, days</span></td><td class='ip1'><input type='text' size='100' name='backup_keep_period' value='$backup_keep_period'></td></tr>";
    echo "<tr><td class='ip1'>Rsync options</td><td class='ip1'><input type='text' size='100' name='rsync_options' value='$rsync_options'></td></tr>";
    echo "<tr><td class='ip1'>Paths to include in backup<br><span class='hint'>One path - one line</span></td><td class='ip1'><textarea name='include_paths' cols=70 rows=10>$include_paths</textarea></td></tr>";
    echo "<tr><td class='ip1'>Paths to exclude from backup<br><span class='hint'>One path - one line</span></td><td class='ip1'><textarea name='exclude_paths' cols=70 rows=10>$exclude_paths</textarea></td></tr>";
    echo "<tr><td class='ip1'>Enable backups<br><span class=hint>To do backups or no</span></td><td class='ip1'><input type='checkbox' name='enabled' $enabled></td></tr>";
}


?>





<html>
<head>
<title>PHBackup <?php echo $script_ver; ?></title>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<h1>PHBackup <?php echo $script_ver; ?></h1>
<a href = "index.php" class="no-underline">🏠 Home page</a>
&nbsp;&nbsp;&nbsp;&nbsp;
<a href = "index.php?action=add" class="no-underline">➕ Add host</a>
&nbsp;&nbsp;&nbsp;&nbsp;
<a href="index.php?action=stats" class="no-underline">&#128203; View stats</a>

<br><br><hr>





<?php

// Performing actions
if (!empty($_POST['confirm']) && $_POST['confirm']=="yes")
{
    switch ((string)$_POST['action']) {
        case "add":
		$enabled=0;
		if($_POST['enabled']=="on") $enabled=1;
		$sql="insert into hosts (name, description, ip, port, user, ssh_key, enabled) values (
		'".$_POST['name']."',
		'".$_POST['description']."',
		'".$_POST['ip']."',
		".$_POST['port'].",
		'".$_POST['user']."',
		'".$_POST['ssh_key']."',".$enabled.")";
                $res = $db->query($sql);
                $new_host_id = $db->insert_id;

                $include_paths = base64_encode($_POST['include_paths']);
                $exclude_paths = base64_encode($_POST['exclude_paths']);
                $sql = "INSERT INTO host_vars (host,var,value) VALUES
                ($new_host_id, 'include_paths', '$include_paths'),
                ($new_host_id, 'exclude_paths', '$exclude_paths'),
                ($new_host_id, 'backup_period', ".$_POST['backup_period']."),
                ($new_host_id, 'backup_keep_period', ".$_POST['backup_keep_period']."),
                ($new_host_id, 'rsync_options', '".$_POST['rsync_options']."')
                ";
                $res = $db->query($sql);
                echo "<center><h3>Host <span>".$_POST['name']." (".$_POST['ip'].", id $new_host_id)</span> was successfully added";
		break;

        case "edit":
		$enabled=0;
		if($_POST['enabled']=="on") $enabled=1;
		$sql="UPDATE hosts SET 
		    name='".$_POST['name']."',
		    description='".$_POST['description']."',
		    ip='".$_POST['ip']."',
		    port=".$_POST['port'].",
		    user='".$_POST['user']."',
		    ssh_key='".$_POST['ssh_key']."',
		    enabled=".$enabled."
		WHERE id=".$_POST['id'];
                $res = $db->query($sql);

                $include_paths = base64_encode($_POST['include_paths']);
                $exclude_paths = base64_encode($_POST['exclude_paths']);
		$db->query("UPDATE host_vars SET value='$include_paths' WHERE host=".$_POST['id']." AND var='include_paths'");
		$db->query("UPDATE host_vars SET value='$exclude_paths' WHERE host=".$_POST['id']." AND var='exclude_paths'");
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
		$sql="UPDATE hosts SET worker=0, status=0, assigned_worker=0 WHERE id=".$_POST['id'];
		$res=$db->query($sql);
                echo "<center><h3>Host <span>".$_POST['id']."</span> was successfully unlocked";
                break;

        case "backup":
		$sql="UPDATE hosts SET worker=0, status=0, assigned_worker=0, last_backup='0000-00-00 00:00:00', next_try='0000-00-00 00:00:00' WHERE id=".$_POST['id'];
		$res=$db->query($sql);
                echo "<center><h3>Host <span>".$_POST['id']."</span> was successfully unlocked, backup will start soon";
                break;

    }
}





// Drawing everything
if (empty($_GET['action'])) {

    // Listing hosts
    $_GET['order'] == "desc" ? $sort_order="desc" : $sort_order="asc";
    $_GET['order'] == "desc" ? $sort_order1="asc" : $sort_order1="desc";
    empty($_GET['order-by']) ? $order_by="name" : $order_by=$_GET['order-by'];
    $order="$order_by $sort_order";
    echo "<h2>Hosts list</h2>";

    $sql="select * from hosts order by $order;";
    $res = $db->query($sql);
    if ($res->num_rows > 0) {
        echo "<table border='0' cellspacing='5' cellpadding='5' width='100%'><tr>
    	    <th class='ip2'>Host <a href='?order-by=name&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Status <a href='?order-by=status&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Last backup <a href='?order-by=last_backup&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Description <a href='?order-by=description&order=$sort_order1'>&#8645;</a></th>
    	    <th class='ip2'>Actions</th></tr>";
        $i=0;
        $color=1;
        $status = array (-1 => "Unknown", 0 => "Ok", 1 => "Backing up", 2 => "Error");
	$status_arr = array();

        while ($row = $res->fetch_array()) {
            echo '<tr>';
            echo '<td class="ip'.$color.'"><a href="index.php?action=edit&host='.$row['id'].'">'.$row['name'].'</a></td>';

            if ($row['enabled']==1) $enablestr="Enabled, "; else $enablestr="Disabled, ";
            if ($row['worker']>0) $workerstr=" (".$row['worker'].")"; else $workerstr="";
            echo '<td class="ip'.$color.' status'.$row['status'].' align-center">'.$enablestr.$status[$row['status']].$workerstr.'</td>';

            echo '<td class="ip'.$color.' status'.$row['status'].' align-center">'.$row['last_backup'];
            if($row['status']>1) echo '<br><span class=hint>Last try: '.$row['last_backup'].'</span>
            <br><span class=hint>Next try: '.$row['next_try'].'</span>';
            echo '</td>';

            echo '<td class="ip'.$color.'">'.$row['description'].'</td>';
            echo '<td class="ip'.$color.'">
            <a href="index.php?host='.$row['id'].'&action=backup" class="red no-underline" title="Backup now!">&#128190;</a>
            <a href="index.php?host='.$row['id'].'&action=edit" class="red no-underline" title="Edit host">&#128203;</a>
            <a href="index.php?host='.$row['id'].'&action=unlock" class="red no-underline" title="Unlock host">&#128275;</a>
            <a href="index.php?host='.$row['id'].'&action=delete" class="red no-underline" title="Delete host">&#10060;</a>
            </td>';
            echo '</tr>';
//            if ($color==0) $color++; else $color=0;
            $i++;
	    $status_arr[$row['status']] ++;
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

        switch ((string)$action) {
            case 'delete':
        	// Delete host
                echo "<form method='post' action='index.php'><input type='hidden' name='confirm' value='yes'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='delete'>
                <center><h3>You are going to delete host<br><br>
                <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br><br>
                Are you sure?<br><br>
                <input type='submit' value='Yes, I am sure'>
                <a href='index.php'>No, go back</a>
                </center>
                </form>";
                break;
            case 'unlock':
        	// Unlock host
                echo "<form method='post' action='index.php'><input type='hidden' name='confirm' value='yes'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='unlock'>";
		if($host_data['worker']>0)
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is locked by backup worker ".$host_data['worker']." since ".$host_data['backup_started']."
    	    	    Do you want to unlock it?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php'>No, go back</a>
    		    </center>
    	            </form>";
    	        else
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is not locked by any backup worker.<br><br>
    	    	    <a href='index.php'>Go back to the host list</a>
    		    </center>
    	            </form>";
                break;
            case 'backup':
        	// Backup host
                echo "<form method='post' action='index.php'><input type='hidden' name='confirm' value='yes'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='backup'>";
		if($host_data['worker']>0)
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is locked by backup worker ".$host_data['worker']." since ".$host_data['backup_started']."
    	    	    Do you want to unlock it and start a new backup?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php'>No, go back</a>
    		    </center>
    	            </form>";
    	        else
                    echo "<center><h4>Host <span class=red>".$host_data['name']." (".$host_data['ip'].")</span><br>
	            is not locked by any backup worker.<br><br>
    	    	    Do you want to unlock it and start a new backup?<br><br>
    	    	    <input type='submit' value='Yes, I am sure'>
    	    	    <a href='index.php'>No, go back</a>
    		    </center>
    	            </form>";
                break;
            case 'edit':
        	// Edit host
                echo "<form method='post' action='index.php'>
                <input type='hidden' name='id' value='".$host_data['id']."'>
                <input type='hidden' name='action' value='edit'>
                <input type='hidden' name='confirm' value='yes'>
                <h2>Edit host ".$host_data['name']." (".$host_data['ip'].")</h2>";
	        echo "<table border='0' cellspacing='5' cellpadding='5' width='100%'>";
        	DrawHost($host_data, $host_vars);
	        echo "<tr><td></td><td class='ip1'><input type='submit' value='Apply changes'></td></tr></table>";
                break;
            default:
        	// Add new host
                echo "<form method='post' action='index.php'>
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