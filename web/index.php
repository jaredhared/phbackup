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
$db=db_connect();

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
    echo "<tr><td>Host name<span class=hint>Latin letters, digits, dots, dashes and underscores. Used as backup directory name: after renaming, backups go to a new directory</span></td><td><input type='text' size='100' name='name' value='".h($name)."'></td></tr>";
    echo "<tr><td>Host description</td><td><input type='text' size='100' name='description' value='".h($description)."'></td></tr>";
    echo "<tr><td>Host IP</td><td><input type='text' size='100' name='ip' value='".h($ip)."'></td></tr>";
    echo "<tr><td>Host group<span class=hint>Groups can be backed up into separate subdirectories</span></td><td>$group_select <a href='index.php?action=groups' class='small'>Edit groups</a></td></tr>";
    echo "<tr><td>Host port<span class=hint>Port at host to connect to (22 - SSH, 23 - Telnet)</span></td><td><input type='text' size='100' name='port' value='".h($port)."'></td></tr>";
    echo "<tr><td>Host user<span class=hint>Username for connection</span></td><td><input type='text' size='100' name='user' value='".h($user)."'></td></tr>";
    echo "<tr><td>Host key/password<span class=hint>Password for backup user (used by switch backup functions)</span></td><td><input type='password' size='100' name='ssh_key' autocomplete='new-password' value='".h($ssh_key)."'></td></tr>";
    echo "<tr><td>Backup function<span class=hint>Which backup function to use for this device</span></td><td>$func_select</td></tr>";
    echo "<tr><td>Backup period<span class=hint>How often to do backups, hours</span></td><td><input type='text' size='100' name='backup_period' value='".h($bperiod)."'></td></tr>";
    echo "<tr><td>Backup time slots<span class=hint>Hours of day, during which backups are allowed, in comma separated, dash-delimited periods, like 0-2,4-7,8-11. Periods over midnight like 22-3 are allowed</span></td><td><input type='text' size='100' name='timestr' value='".h($time_slots)."'></td></tr>";
    echo "<tr><td>Backup keep period<span class=hint>For which time to store backups, days. The newest backups are always kept, even if they are older</span></td><td><input type='text' size='100' name='backup_keep_period' value='".h($backup_keep_period)."'></td></tr>";
    echo "<tr><td>Rsync options<span class=hint>Default: ".h(DEFAULT_RSYNC_OPTIONS).". Options with values should be written as --option=value. For slow links compression can be enabled with --zc=zstd (rsync 3.2+ on both sides) or --zc=zlibx; plain -z may fail with \"inflate returned -3\"</span></td><td><input type='text' size='100' name='rsync_options' value='".h($rsync_options)."'></td></tr>";
    echo "<tr><td>Pre-backup script<span class='hint'>A script which prepares data on the target server - dumps databases etc.</span><span class='warn'>Runs as root on the target host and can break the system. Test it first!</span></td><td><textarea name='pre_script' cols=70 rows=10>".h($pre_script)."</textarea></td></tr>";
    echo "<tr><td>Pre-backup script schedule<span class='hint'>Crontab entity for pre-backup script. Script name is /opt/phbackup.sh, cron file is being placed inside /etc/cron.d</span></td><td><input type='text' size='100' name='pre_schedule' value='".h($pre_schedule)."'></td></tr>";
    echo "<tr><td>Install pre-backup script<span class=hint>Install new script or update existing script and cron settings</span></td><td><input type='checkbox' name='pre_install' unchecked></td></tr>";
    echo "<tr><td>Paths to include in backup<span class='hint'>One path - one line</span></td><td><textarea name='include_paths' cols=70 rows=6>".h($include_paths)."</textarea></td></tr>";
    echo "<tr><td>Paths to exclude from backup<span class='hint'>One path - one line</span></td><td><textarea name='exclude_paths' cols=70 rows=6>".h($exclude_paths)."</textarea></td></tr>";
    echo "<tr><td>Enable backups<span class=hint>To do backups or no</span></td><td><input type='checkbox' name='enabled' $enabled></td></tr>";
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
	$list = "";
	foreach ($errors as $error) $list .= "<li>".h($error)."</li>";
	notice("<b>Changes were not saved:</b><ul>$list</ul><a href='javascript:history.back()'>&larr; Go back and fix</a>", "err");
}


// Validates host group form. Returns array of error messages
function validate_group_form($db, $post, $id = 0) {
	$errors = array();
	$name = trim($post['group_name'] ?? '');
	$path = trim($post['group_path'] ?? '', " /");
	if ($name === '') $errors[] = "Group name must not be empty";
	elseif (mb_strlen($name) > 255) $errors[] = "Group name is too long";
	elseif (db_query($db, "SELECT id FROM host_groups WHERE name=? AND id<>?", array($name, $id))->num_rows > 0) $errors[] = "Group with this name already exists";
	if (!valid_group_path($path) || strlen($path) > 255) $errors[] = "Group path may contain only latin letters, digits, dots, dashes and underscores, subdirectories are separated by /";
	return $errors;
}


// Group directory inside backup path, for display
function group_dir($path) {
	global $backup_path;
	return rtrim($backup_path, '/').($path !== '' ? "/$path" : "");
}


// Message box. $html must be already escaped
function notice($html, $kind = "ok") {
	echo "<div class='notice $kind'>$html</div>";
}


// Small monochrome icons for host actions
function icon($name) {
	$paths = array(
	    'backup' => '<polygon points="7 4 19 12 7 20 7 4"/>',
	    'edit'   => '<path d="M16 3.5l4.5 4.5L8 20.5H3.5V16z"/>',
	    'unlock' => '<rect x="4.5" y="11" width="15" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.6-1.7"/>',
	    'log'    => '<path d="M6 3h9l4 4v14H6z"/><path d="M9 11h7M9 15h7M9 7h3"/>',
	    'delete' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
	);
	return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths[$name].'</svg>';
}


// Date without seconds, "never" for empty dates
function short_date($date) {
	if (empty($date) || strpos($date, "0000-00-00") === 0) return "never";
	return h(substr($date, 0, 16));
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





<!DOCTYPE html>
<html>
<head>
<title>PHBackup <?php echo h($script_ver_text); ?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
// Applying saved theme before page is drawn, so it does not blink
try { var t = localStorage.getItem('phb-theme'); if (t == 'light' || t == 'dark') document.documentElement.setAttribute('data-theme', t); } catch (e) {}
</script>
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<header class="topbar">
<?php $gq = $cur_group ? "group=$cur_group" : ""; ?>
<a class="brand" href="index.php<?php echo $gq ? "?$gq" : ""; ?>">PHBackup <span class="ver"><?php echo h($script_ver_text); ?></span></a>
<form method="GET" action="index.php" class="group-filter">
<label>Group
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
</label>
</form>
<nav>
<a href="index.php?action=add<?php echo $gq ? "&$gq" : ""; ?>">+ Add host</a>
<a href="index.php?action=groups">Groups</a>
<a href="zabbix.php">Zabbix JSON</a>
<button type="button" class="theme-toggle" onclick="toggleTheme()" title="Switch light/dark theme" aria-label="Switch light/dark theme">
<svg class="i-moon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
<svg class="i-sun" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
</button>
</nav>
<script>
function toggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme');
    if (!cur) cur = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    var next = (cur == 'dark') ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('phb-theme', next); } catch (e) {}
}
</script>
</header>
<main>





<?php

// Checking if we need an upgrade

$upgrade_versions = is_upgraded($db);
$upgrade_path = $script_ver_text;

if (is_array($upgrade_versions)) {
    foreach ($upgrade_versions as $short => $version ) { if ($short > $script_ver) $upgrade_path .= " => $version"; }

    notice("<b>An upgrade is needed: ".h($upgrade_path)."</b><br>Please run upgrade.php before using PHBackup.", "warn");
    echo "</main></body></html>";
    die();
};




// Performing actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['confirm']) && $_POST['confirm']=="yes")
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        notice("Security token is invalid or expired. Please reload the page and try again.", "err");
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
	                notice("Host <b>".h($_POST['name'])."</b> (".h($_POST['ip']).", id $new_host_id) was added");
		}
		else notice("Host <b>".h($_POST['name'])."</b> is already in database!", "err");
		break;





	// Editing host
        case "edit":
		$enabled=0;
		if(!empty($_POST['enabled']) && $_POST['enabled']=="on") $enabled=1;

		$errors = validate_host_form($db, $_POST);
		$res = db_query($db, "SELECT id FROM hosts WHERE name=? AND id<>?", array($_POST['name'] ?? '', $post_id));
		if ($res->num_rows > 0) $errors[] = "Host with this name already exists";
		// Running backup is tracked by host name, renaming would make it look stale and start a second backup
		$cur = db_query($db, "SELECT name, worker FROM hosts WHERE id=?", array($post_id))->fetch_array();
		if ($cur && $cur['worker'] >= 0 && ($_POST['name'] ?? '') !== $cur['name']) $errors[] = "Host can not be renamed while it is being backed up";
		$updated_times=normalize_time_periods($_POST['timestr'] ?? '');
		if ($updated_times == "") $errors[] = "Backup time slots are invalid, host would never be backed up";
		if (!empty($errors)) { print_errors($errors); break; }

		// If checkbox is on, scheduling script install
		(isset($_POST['pre_install']) && $_POST['pre_install']=="on") ? $pre_install="1" : $pre_install="0";

		// Unchecked box keeps current state, so pending or failed installation is not reset by editing other fields
		db_query($db, "UPDATE hosts SET name=?, description=?, group_id=?, ip=?, port=?, user=?, ssh_key=?, time_slots=?, pre_install=IF(?='1', 1, pre_install), enabled=? WHERE id=?",
		    array($_POST['name'], $_POST['description'] ?? '', (int)$_POST['group'], $_POST['ip'], (int)$_POST['port'], $_POST['user'], $_POST['ssh_key'] ?? '', $updated_times, $pre_install, $enabled, $post_id));

		save_host_vars($db, $post_id, $_POST);

                notice("Host <b>".h($_POST['name'])."</b> (".h($_POST['ip']).", id $post_id) was updated");
		break;

        case "delete":
		db_query($db, "delete from hosts where id=?", array($post_id));
		db_query($db, "delete from host_vars where host=?", array($post_id));
                notice("Host id $post_id was removed");
                break;

        case "unlock":
		// Status is set to the result of the last finished backup, not to "Ok"
		db_query($db, "UPDATE hosts SET worker=-1, status=last_result WHERE id=?", array($post_id));
                notice("Host id $post_id was unlocked");
                break;

        case "backup":
		// A running backup is not interrupted, otherwise a second backup of the same host would start in parallel
		if (db_query($db, "UPDATE hosts SET backup_now=1 WHERE id=? AND worker=-1 AND enabled=1", array($post_id)) == 1) notice("Backup of host id $post_id will start soon");
		else {
		    $cur = db_query($db, "SELECT enabled, worker FROM hosts WHERE id=?", array($post_id))->fetch_array();
		    if (!$cur) notice("Host not found", "err");
		    elseif ($cur['enabled'] != 1) notice("Host id $post_id is disabled. Enable backups in host settings first.", "err");
		    else notice("Host id $post_id is being backed up right now. If it is stuck, unlock it first.", "err");
		}
                break;

	// Host groups
        case "group_add":
		$errors = validate_group_form($db, $_POST);
		if (!empty($errors)) { print_errors($errors); break; }
		$name = trim($_POST['group_name']);
		db_query($db, "INSERT INTO host_groups (name, path) VALUES (?,?)", array($name, trim($_POST['group_path'] ?? '', " /")));
		notice("Group <b>".h($name)."</b> was added");
		break;

        case "group_edit":
		$gres = db_query($db, "SELECT * FROM host_groups WHERE id=?", array($post_id));
		$old = $gres->fetch_array();
		if (!$old) { notice("Group not found", "err"); break; }
		$errors = validate_group_form($db, $_POST, $post_id);
		if (!empty($errors)) { print_errors($errors); break; }
		$name = trim($_POST['group_name']);
		$path = trim($_POST['group_path'] ?? '', " /");
		db_query($db, "UPDATE host_groups SET name=?, path=? WHERE id=?", array($name, $path, $post_id));
		notice("Group <b>".h($name)."</b> was updated");
		break;

        case "group_delete":
		$hosts_num = db_query($db, "SELECT id FROM hosts WHERE group_id=?", array($post_id))->num_rows;
		$groups_num = $db->query("SELECT id FROM host_groups")->num_rows;
		if ($hosts_num > 0) notice("Group has $hosts_num host(s), move them to another group first", "err");
		elseif ($groups_num <= 1) notice("The last group can not be deleted", "err");
		elseif (db_query($db, "DELETE FROM host_groups WHERE id=?", array($post_id)) == 1) notice("Group id $post_id was removed");
		else notice("Group not found", "err");
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

    echo "<h2>Hosts <span class='muted'>".h($groupname)."</span></h2>";

    if ($res->num_rows == 0) notice("No hosts yet. <a href='index.php?action=add".($cur_group ? "&group=$cur_group" : "")."'>Add the first one</a>", "warn");
    if ($res->num_rows > 0) {
        // Sortable column header
        $sort_th = function($col, $title) use ($group, $order_by, $sort_order, $sort_order1) {
            $arrow = ($order_by == $col) ? ($sort_order == "asc" ? "&#8593;" : "&#8595;") : "<span class='muted'>&#8645;</span>";
            return "<th><a href='?group=$group&order-by=$col&order=$sort_order1'>$title $arrow</a></th>";
        };
        echo "<div class='table-wrap'><table class='hosts'><thead><tr>"
            .$sort_th("name", "Host")
            .$sort_th("status", "Status")
            .$sort_th("last_backup", "Last backup")
            .$sort_th("description", "Description")
            ."<th class='actions'></th></tr></thead><tbody>";
        $i=0;
        $status = array (-1 => "Unknown", 0 => "Ok", 1 => "Backing up", 2 => "Error", 3 => "Backup too old");
	$status_arr = array();
	$pre_states = array(1 => "<div class='sub'>Pre-script install pending</div>", 2 => "<div class='sub red'>Pre-script install failed</div>", 3 => "<div class='sub'>Pre-script installing</div>");

        while ($row = $res->fetch_array()) {
            $id = (int)$row['id'];
            $st = (int)$row['status'];
            $disabled = $row['enabled'] != 1;
            echo $disabled ? "<tr class='disabled'>" : "<tr>";
            echo '<td><a class="host" href="index.php?action=edit&host='.$id.'">'.h($row['name']).'</a><div class="sub">'.h($row['ip']).'</div></td>';

            $badge = h($status[$st] ?? $st);
            if ($row['worker']>=0) $badge .= " &middot; w".(int)$row['worker'];
            echo "<td><span class='badge s$st'>$badge</span>";
            if ($disabled) echo " <span class='badge off'>Disabled</span>";
            if ($st==1 && (int)$row['last_result']==2) echo "<div class='sub red'>Last backup failed</div>";
            $pre = (int)$row['pre_install'];
            if ($pre >= 100) $pre = 3; // claimed by a worker, installing
            echo ($pre_states[$pre] ?? "")."</td>";

            echo '<td class="nowrap"><span class="ts">'.short_date($row['last_backup']).'</span>';
            if($st>1) echo '<div class="sub">Last try: '.short_date($row['backup_started']).'</div>';
            echo '<div class="sub">Next try: '.short_date($row['next_try']).'</div>';
            echo '</td>';

            echo '<td class="desc">'.h($row['description']).'</td>';
            echo '<td class="actions">'
                .'<a href="index.php?host='.$id.'&action=backup" title="Backup now">'.icon('backup').'</a>'
                .'<a href="index.php?host='.$id.'&action=edit" title="Edit host">'.icon('edit').'</a>'
                .'<a href="index.php?host='.$id.'&action=unlock" title="Unlock host">'.icon('unlock').'</a>'
                .'<a href="index.php?host='.$id.'&action=log" title="Last backup log">'.icon('log').'</a>'
                .'<a href="index.php?host='.$id.'&action=delete" class="danger" title="Delete host">'.icon('delete').'</a>'
                .'</td>';
            echo '</tr>';
            $i++;
	    isset($status_arr[$st]) ? $status_arr[$st]++ : $status_arr[$st] = 1;
        }
        echo "</tbody></table></div>";
        echo "<div class='summary'><b>$i</b> hosts";
        ksort($status_arr);
        foreach($status_arr as $stat => $num) {
	    echo " <span class='badge s$stat'>".h($status[$stat] ?? $stat)." $num</span>";
        }
        echo "</div>";
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
	$group_actions = array('groups', 'group_edit', 'group_delete');
	if ($action !="add" && !in_array($action, $group_actions)) {
            // Getting host vars
            $res = db_query($db, "select hosts.*, host_groups.path from hosts left join host_groups on hosts.group_id=host_groups.id where hosts.id=?", array($host_id));
            $host_data = $res->fetch_array();
            if (!$host_data) {
                notice("Host not found. <a href='index.php'>Go back to the host list</a>", "err");
                echo "</main></body></html>";
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
                <div class='card'><h3>Delete host $hname?</h3>
                <p>The host will be removed from PHBackup. Backup files on disk are not deleted.</p>
                <button type='submit' class='btn danger'>Delete</button>
                <a class='btn' href='index.php?$grouplink'>Cancel</a>
                </div>
                </form>";
                break;
            case 'unlock':
        	// Unlock host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>$csrf_input
                <input type='hidden' name='id' value='$hid'>
                <input type='hidden' name='action' value='unlock'>";
		if($host_data['worker']>-1)
                    echo "<div class='card'><h3>Unlock host $hname?</h3>
	            <p>It is locked by backup worker ".(int)$host_data['worker']." since ".h($host_data['backup_started']).".<br>
    	    	    Unlocking does not stop the running backup process. Stuck hosts are unlocked automatically after 5 minutes.</p>
    	    	    <button type='submit' class='btn primary'>Unlock</button>
    	    	    <a class='btn' href='index.php?$grouplink'>Cancel</a>
    		    </div>
    	            </form>";
    	        else
                    echo "<div class='card'><h3>Host $hname</h3>
	            <p>The host is not locked by any backup worker.</p>
    	    	    <a class='btn' href='index.php?$grouplink'>Back to the host list</a>
    		    </div>
    	            </form>";
                break;
            case 'backup':
        	// Backup host
                echo "<form method='post' action='index.php?$grouplink'><input type='hidden' name='confirm' value='yes'>$csrf_input
                <input type='hidden' name='id' value='$hid'>
                <input type='hidden' name='action' value='backup'>";
		if($host_data['worker']>=0)
                    echo "<div class='card'><h3>Host $hname</h3>
	            <p>The host is being backed up by worker ".(int)$host_data['worker']." since ".h($host_data['backup_started']).".</p>
    	    	    <a class='btn' href='index.php?$grouplink'>Back to the host list</a>
    		    </div>
    	            </form>";
    	        elseif($host_data['enabled']!=1)
                    echo "<div class='card'><h3>Host $hname is disabled</h3>
	            <p>Enable backups in host settings first.</p>
    	    	    <a class='btn' href='index.php?action=edit&host=$hid'>Host settings</a>
    	    	    <a class='btn' href='index.php?$grouplink'>Cancel</a>
    		    </div>
    	            </form>";
    	        else
                    echo "<div class='card'><h3>Back up host $hname now?</h3>
	            <p>The backup starts within a few seconds, regardless of time slots.</p>
    	    	    <button type='submit' class='btn primary'>Start backup</button>
    	    	    <a class='btn' href='index.php?$grouplink'>Cancel</a>
    		    </div>
    	            </form>";
                break;
            case 'edit':
        	// Edit host
                echo "<form method='post' action='index.php?$grouplink'>
                <input type='hidden' name='id' value='$hid'>
                <input type='hidden' name='action' value='edit'>
                <input type='hidden' name='confirm' value='yes'>$csrf_input
                <h2>Edit host <span class='muted'>$hname</span></h2>";
	        echo "<table class='form'>";
        	DrawHost($host_data, $host_vars);
	        echo "<tr><td></td><td><button type='submit' class='btn primary'>Apply changes</button> <a class='btn' href='index.php?$grouplink'>Cancel</a></td></tr></table></form>";
                break;
            case 'log':
        	// Show log
                echo "<h2>Last backup log <span class='muted'>$hname</span></h2>";
		$logfile = (valid_host_name($host_data['name']) && valid_group_path($host_data['path'])) ? host_backup_path($host_data)."/backup.log" : "";
		if ($logfile != "" && file_exists($logfile))
		{
		    echo "<pre class='log'>";
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
		else notice("No backup log available.", "warn");
                break;
            case 'groups':
        	// Host groups list
                echo "<h2>Host groups</h2>";
                $gres = $db->query("SELECT host_groups.*, COUNT(hosts.id) AS hosts_num FROM host_groups LEFT JOIN hosts ON hosts.group_id=host_groups.id GROUP BY host_groups.id ORDER BY host_groups.name");
                echo "<div class='table-wrap narrow'><table class='hosts'><thead><tr><th>Name</th><th>Backup directory</th><th>Hosts</th><th class='actions'></th></tr></thead><tbody>";
                while ($row = $gres->fetch_array()) {
                    $gid = (int)$row['id'];
                    echo "<tr><td><a class='host' href='index.php?group=$gid'>".h($row['name'])."</a></td>"
                        ."<td class='mono'>".h(group_dir($row['path']))."/</td>"
                        ."<td>".(int)$row['hosts_num']."</td>"
                        ."<td class='actions'>"
                        ."<a href='index.php?action=group_edit&id=$gid' title='Edit group'>".icon('edit')."</a>"
                        ."<a href='index.php?action=group_delete&id=$gid' class='danger' title='Delete group'>".icon('delete')."</a>"
                        ."</td></tr>";
                }
                echo "</tbody></table></div>";

                echo "<h3 class='section'>Add group</h3>
                <form method='post' action='index.php?action=groups'>
                <input type='hidden' name='action' value='group_add'>
                <input type='hidden' name='confirm' value='yes'>$csrf_input
                <table class='form narrow'>
                <tr><td>Name</td><td><input type='text' name='group_name'></td></tr>
                <tr><td>Path<span class=hint>Subdirectory inside ".h(rtrim($backup_path, '/'))." for backups of this group. Empty - backups are stored in the root of backup directory</span></td><td><input type='text' name='group_path' class='mono'></td></tr>
                <tr><td></td><td><button type='submit' class='btn primary'>Add group</button></td></tr>
                </table></form>";
                break;
            case 'group_edit':
        	// Edit host group
                $gid = (int)($_GET['id'] ?? 0);
                $group = db_query($db, "SELECT * FROM host_groups WHERE id=?", array($gid))->fetch_array();
                if (!$group) { notice("Group not found. <a href='index.php?action=groups'>Back to groups</a>", "err"); break; }
                $hosts_num = db_query($db, "SELECT id FROM hosts WHERE group_id=?", array($gid))->num_rows;
                echo "<h2>Edit group <span class='muted'>".h($group['name'])."</span></h2>
                <form method='post' action='index.php?action=groups'>
                <input type='hidden' name='action' value='group_edit'>
                <input type='hidden' name='id' value='$gid'>
                <input type='hidden' name='confirm' value='yes'>$csrf_input
                <table class='form narrow'>
                <tr><td>Name</td><td><input type='text' name='group_name' value='".h($group['name'])."'></td></tr>
                <tr><td>Path<span class=hint>Subdirectory inside ".h(rtrim($backup_path, '/'))." for backups of this group. Empty - backups are stored in the root of backup directory</span>"
                    .($hosts_num > 0 ? "<span class='hint'>New backups of $hosts_num host(s) will go to the new directory, existing backups stay where they are</span>" : "")
                    ."</td><td><input type='text' name='group_path' class='mono' value='".h($group['path'])."'></td></tr>
                <tr><td></td><td><button type='submit' class='btn primary'>Apply changes</button> <a class='btn' href='index.php?action=groups'>Cancel</a></td></tr>
                </table></form>";
                break;
            case 'group_delete':
        	// Delete host group
                $gid = (int)($_GET['id'] ?? 0);
                $group = db_query($db, "SELECT * FROM host_groups WHERE id=?", array($gid))->fetch_array();
                if (!$group) { notice("Group not found. <a href='index.php?action=groups'>Back to groups</a>", "err"); break; }
                $hosts_num = db_query($db, "SELECT id FROM hosts WHERE group_id=?", array($gid))->num_rows;
                $gname = h($group['name']);
                if ($hosts_num > 0) {
                    echo "<div class='card'><h3>Group $gname can not be deleted</h3>
                    <p>It has $hosts_num host(s). Move them to another group or delete them first.</p>
                    <a class='btn' href='index.php?group=$gid'>Show hosts</a> <a class='btn' href='index.php?action=groups'>Back to groups</a></div>";
                    break;
                }
                echo "<form method='post' action='index.php?action=groups'><input type='hidden' name='confirm' value='yes'>$csrf_input
                <input type='hidden' name='id' value='$gid'>
                <input type='hidden' name='action' value='group_delete'>
                <div class='card'><h3>Delete group $gname?</h3>
                <p>The group has no hosts. Files on disk are not touched.</p>
                <button type='submit' class='btn danger'>Delete</button>
                <a class='btn' href='index.php?action=groups'>Cancel</a>
                </div></form>";
                break;
            default:
        	// Add new host
                echo "<form method='post' action='index.php?$grouplink'>
                <input type='hidden' name='action' value='add'>
                <input type='hidden' name='confirm' value='yes'>$csrf_input
                <h2>Add new host</h2>";
	        echo "<table class='form'>";
        	DrawHost(null, null);
	        echo "<tr><td></td><td><button type='submit' class='btn primary'>Add host</button> <a class='btn' href='index.php?$grouplink'>Cancel</a></td></tr></table></form>";
        }
    }
}



$db->close();

?>
</main>
</body>
</html>
