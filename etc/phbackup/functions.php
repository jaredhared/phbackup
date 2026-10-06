<?php

require_once("/etc/phbackup/upgrades.php");

if (file_exists("/etc/phbackup/functions.custom.php")) {
  include_once("/etc/phbackup/functions.custom.php");
}

// Default rsync options for new hosts
const DEFAULT_RSYNC_OPTIONS = "-aHAXz --numeric-ids";


function get_script_vars($db) {
    $script_vars = array();
    $sql="select * from host_vars where host=10000;";
    $res = $db->query($sql);
    while ($row = $res->fetch_array()) {
        $script_vars[$row['var']] = $row['value'];
    }

    return $script_vars;
}


function is_upgraded($db) {

    $script_vars = get_script_vars($db);
    isset($script_vars['version']) ? $script_ver = $script_vars['version'] : $script_ver = 1;
    isset($script_vars['version_text']) ? $script_ver_text = $script_vars['version_text'] : $script_ver_text = "pre-1.5.0";
    $max_ver=$script_ver;
    $max_ver_text=$script_ver_text;

    $upgrade_versions = array();

    $functions = get_defined_functions();
    foreach ($functions['user'] as $func)
    {
        preg_match ('/upgrade_(\d+)_(\d+)_(\d+)_(\d+)/', $func, $matches);
        if (!empty($matches)) {
            $upgrade_versions[$matches[1]] = $matches[2].".".$matches[3].".".$matches[4];
        }
    }

    ksort($upgrade_versions,SORT_NUMERIC);
    $upgrade_path = $script_ver_text;
    foreach ($upgrade_versions as $short => $version )
    {
        if ( $short > $max_ver) {
            $max_ver = $short;
            $max_ver_text = $version;
            if ($short > $script_ver) $upgrade_path .= " => $version";
        }
    }

    if ($script_ver == $max_ver) {
//        echo "Ok, max version is $max_ver_text\n";
        return true;
    }
    else {
//        echo "Max version $max_ver_text is higher then current $script_ver_text ($max_ver > $script_ver). \nUpgrade path is $upgrade_path\n";
        return $upgrade_versions;
    }


}



// ---------------------------------------------------------------------------
// Safety helpers: DB queries, HTML output, input validation
// ---------------------------------------------------------------------------

// Runs a prepared statement. Returns mysqli_result for SELECTs, number of affected rows otherwise.
// Note: $db->affected_rows is not reliable after prepared statements, use the returned value.
function db_query($db, $sql, $params = array()) {
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $params = array_values($params);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    return $res === false ? $stmt->affected_rows : $res;
}


// Escapes a value for HTML output
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// Host name is used as a directory name and in process titles
function valid_host_name($str) {
    return is_string($str) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}$/', $str) === 1;
}

// IPv4, IPv6 or DNS name
function valid_host_address($str) {
    return is_string($str) && preg_match('/^[A-Za-z0-9][A-Za-z0-9.:_-]{0,254}$/', $str) === 1;
}

function valid_host_user($str) {
    return is_string($str) && preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]{0,63}$/', $str) === 1;
}

function valid_port($port) {
    return is_numeric($port) && (int)$port == $port && $port >= 1 && $port <= 65535;
}

// Group path is a relative subdirectory inside $backup_path, may be empty
function valid_group_path($str) {
    if ($str === '' || $str === null) return true;
    if (!is_string($str) || preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $str) !== 1) return false;
    foreach (explode('/', $str) as $part) {
        if ($part == '.' || $part == '..') return false;
    }
    return true;
}

function valid_backup_function($str) {
    return is_string($str) && preg_match('/^backup_[A-Za-z0-9_]+$/', $str) === 1 && function_exists($str);
}


// Splits rsync options string into separate arguments.
// Returns array of options or error message string.
// Options which execute commands or point rsync to local files are forbidden.
function parse_rsync_options($str) {
    $forbidden = array('rsh', 'rsync-path', 'remote-option', 'log-file', 'log-file-format',
        'files-from', 'exclude-from', 'include-from', 'link-dest', 'compare-dest', 'copy-dest',
        'write-batch', 'only-write-batch', 'read-batch', 'daemon', 'config', 'password-file',
        'early-input', 'partial-dir', 'backup-dir', 'temp-dir');

    $opts = preg_split('/\s+/', trim((string)$str), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($opts as $opt) {
        if (preg_match('/^--([A-Za-z0-9][A-Za-z0-9-]*)(=.*)?$/', $opt, $m)) {
            if (in_array(strtolower($m[1]), $forbidden)) return "rsync option --".$m[1]." is not allowed";
        }
        elseif (preg_match('/^-[A-Za-z0-9]+$/', $opt)) {
            // -e (rsh), -M (remote option), -T (temp dir) take the rest as an argument
            if (strpbrk($opt, 'eMT') !== false) return "rsync options -e, -M and -T are not allowed ($opt)";
        }
        else return "invalid rsync option '$opt' (use --option=value form)";
    }
    return $opts;
}


// Validates host form data. Returns array of error messages, empty if everything is ok.
function validate_host_form($db, $post) {
    $errors = array();
    if (!valid_host_name($post['name'] ?? '')) $errors[] = "Host name may contain only latin letters, digits, dots, dashes and underscores";
    if (!valid_host_address($post['ip'] ?? '')) $errors[] = "Host IP must be an IP address or DNS name";
    if (!valid_port($post['port'] ?? '')) $errors[] = "Port must be a number between 1 and 65535";
    if (!valid_host_user($post['user'] ?? '')) $errors[] = "User may contain only latin letters, digits, dots, dashes and underscores";
    if (!valid_backup_function($post['backup_function'] ?? '')) $errors[] = "Unknown backup function";
    if (!ctype_digit((string)($post['backup_period'] ?? '')) || $post['backup_period'] < 1) $errors[] = "Backup period must be a positive number of hours";
    if (!ctype_digit((string)($post['backup_keep_period'] ?? '')) || $post['backup_keep_period'] < 1) $errors[] = "Backup keep period must be a positive number of days";
    if (!is_array(parse_rsync_options($post['rsync_options'] ?? ''))) $errors[] = parse_rsync_options($post['rsync_options'] ?? '');

    $res = db_query($db, "SELECT id FROM host_groups WHERE id=?", array($post['group'] ?? ''));
    if ($res->num_rows == 0) $errors[] = "Unknown host group";

    return $errors;
}


// Checks host data loaded from DB before using it in shell commands.
// Returns error message or empty string.
function check_host_data($host_data) {
    if (!valid_host_name($host_data['name'])) return "invalid host name";
    if (!valid_host_address($host_data['ip'])) return "invalid host address";
    if (!valid_host_user($host_data['user'])) return "invalid host user";
    if (!valid_port($host_data['port'])) return "invalid host port";
    if (!valid_group_path($host_data['path'])) return "invalid group path";
    return "";
}


// Backup directory of the host
function host_backup_path($host_data) {
    global $backup_path;
    $path = rtrim($backup_path, '/');
    if ($host_data['path'] !== '' && $host_data['path'] !== null) $path .= "/".$host_data['path'];
    return $path."/".$host_data['name'];
}


// Common SSH options. $port_flag is -p for ssh and -P for scp
// BatchMode - never hang on password prompt, ConnectTimeout - unreachable host fails in 30s,
// ServerAlive* - dead connection is detected in ~2 minutes
function ssh_options($port, $port_flag = "-p") {
    return "-ocompression=no -oLogLevel=ERROR -oBatchMode=yes -oConnectTimeout=30 -oServerAliveInterval=15 -oServerAliveCountMax=8 -oStrictHostKeyChecking=no -oUserKnownHostsFile=/dev/null $port_flag ".(int)$port;
}


function mark_backup_failed($db, $host_id) {
    db_query($db, "UPDATE hosts set worker=-1, status=2, last_result=2, next_try=DATE_ADD(NOW(), INTERVAL 1 HOUR), backup_now=0 where id=?", array($host_id));
}


// Parses timestamp from backup name, returns unix time or false.
// Note: functions named backup_* are treated as backup functions, so helpers must not use this prefix
function parse_backup_timestamp($datestamp) {
    $dt = DateTime::createFromFormat('Y-m-d_H:i:s', $datestamp);
    return $dt === false ? false : $dt->getTimestamp();
}


// Removes old backups of a host.
// Backups are recognized by name (datestamp + $suffix), not by mtime.
// The newest $min_keep backups are never removed, even if they are older than $keep_days,
// so a host which fails to back up for a long time does not lose all its copies.
// Leftovers of failed runs (processing-*, error-*) are removed after $keep_days.
function rotate_backups($bkpath, $keep_days, $min_keep, $suffix = '') {
    $keep_days = max(1, (int)$keep_days);
    $min_keep = max(1, (int)$min_keep);
    $cutoff = time() - $keep_days * 86400;
    $q = preg_quote($suffix, '/');

    $backups = array();
    $leftovers = array();
    foreach (scandir($bkpath) as $entry) {
        $full = "$bkpath/$entry";
        if (is_link($full)) continue;
        if ($suffix == '' ? !is_dir($full) : !is_file($full)) continue;

        if (preg_match('/^(\d{4}-\d{2}-\d{2}_\d{2}:\d{2}:\d{2})'.$q.'$/', $entry, $m)) {
            $ts = parse_backup_timestamp($m[1]);
            if ($ts !== false) $backups[$entry] = $ts;
        }
        elseif (preg_match('/^(processing|error)-(\d{4}-\d{2}-\d{2}_\d{2}:\d{2}:\d{2})'.$q.'$/', $entry, $m)) {
            $ts = parse_backup_timestamp($m[2]);
            if ($ts !== false) $leftovers[$entry] = $ts;
        }
    }

    // Newest first
    arsort($backups);
    $to_delete = array();
    $i = 0;
    foreach ($backups as $entry => $ts) {
        if ($i++ >= $min_keep && $ts < $cutoff) $to_delete[] = $entry;
    }
    foreach ($leftovers as $entry => $ts) {
        if ($ts < $cutoff) $to_delete[] = $entry;
    }

    foreach ($to_delete as $entry) {
        $full = "$bkpath/$entry";
        if (is_dir($full)) exec("rm -rf -- ".escapeshellarg($full));
        else unlink($full);
    }

    return $to_delete;
}



// ---------------------------------------------------------------------------
// Backup functions
// ---------------------------------------------------------------------------

function run_backup ($db, $host_data, $host_vars) {

    global $worker_id, $datestart, $host_id;

    $function=$host_vars['backup_function'] ?? '';
    if(!valid_backup_function($function)) {
        echo "$datestart - [$worker_id] Host ".$host_data['name']." - unknown backup function '$function', backup failed!\n";
        mark_backup_failed($db, $host_id);
        return;
    }

    $error = check_host_data($host_data);
    if ($error != "") {
        echo "$datestart - [$worker_id] Host ".$host_data['name']." - $error, backup failed!\n";
        mark_backup_failed($db, $host_id);
        return;
    }

    echo "$datestart - [$worker_id] Host ".$host_data['name']." - executing $function\n";
    $function($db, $host_data, $host_vars);
}



function finish_backup_ok ($db, $host_data, $host_vars) {
    global $nextbackup, $datestart, $host_id;

    $backup_period = max(1, (int)$host_vars['backup_period']);
    // "Backup now" does not shift the schedule: if the next regular backup is already planned, it is kept.
    // Previously next_try was set to NOW(), and the host was backed up again right away.
    if ($host_data['backup_now']==1)
        db_query($db, "UPDATE hosts set worker=-1, last_backup=?, status=0, last_result=0, next_try=IF(next_try > NOW(), next_try, DATE_ADD(?, INTERVAL ? HOUR)), backup_now=0 where id=?", array($datestart, $nextbackup, $backup_period, $host_id));
    else
        db_query($db, "UPDATE hosts set worker=-1, last_backup=?, status=0, last_result=0, next_try=DATE_ADD(?, INTERVAL ? HOUR), backup_now=0 where id=?", array($datestart, $nextbackup, $backup_period, $host_id));
}



function backup_server_via_ssh ($db, $host_data, $host_vars) {

        global $worker_id, $cmd_rsync, $host_id, $backup_min_keep;

        // Setting process title
        cli_set_process_title("phbackup-$worker_id [backing ".$host_data['name']."]");

        $datestamp = date("Y-m-d_H:i:s");
        $bkpath = host_backup_path($host_data);
        $processing = "$bkpath/processing-$datestamp";

        // Check if directory exists
        if (!is_dir($bkpath)) mkdir($bkpath, 0750, true);

        $rsync_opts = parse_rsync_options($host_vars['rsync_options']);
        if (!is_array($rsync_opts)) {
            echo date("Y-m-d H:i:s")." - [$worker_id] Host ".$host_data['name']." - $rsync_opts, backup failed!\n";
            mark_backup_failed($db, $host_id);
            cli_set_process_title("phbackup-$worker_id [idle]");
            return;
        }

        // Generating include/exclude files
        file_put_contents("$bkpath/exclude.txt", base64_decode($host_vars['exclude_paths']));
        file_put_contents("$bkpath/files.txt", base64_decode($host_vars['include_paths']));
        file_put_contents("$bkpath/backup.log", "");

        // Backup itself
        $cmd = escapeshellarg($cmd_rsync);
        foreach ($rsync_opts as $opt) $cmd .= " ".escapeshellarg($opt);
        $cmd .= " --partial --relative"
            ." --log-file=".escapeshellarg("$bkpath/backup.log")
            ." -e ".escapeshellarg("nice -n 19 /usr/bin/ssh ".ssh_options($host_data['port']))
            ." --delete --timeout=600 --ignore-errors"
            ." --exclude-from=".escapeshellarg("$bkpath/exclude.txt")
            ." --files-from=".escapeshellarg("$bkpath/files.txt")
            ." --link-dest=../111-Latest"
            ." ".escapeshellarg($host_data['user']."@".$host_data['ip'].":/")
            ." ".escapeshellarg("$processing/")
            ." >/dev/null 2>&1";
        $output = array();
        exec($cmd, $output, $return_code);

        $dateend = date("Y-m-d H:i:s");

        // Checking results. 23 and 24 are partial transfers (permission denied, vanished files)
        $ok = ($return_code==0 || $return_code==23 || $return_code==24);
        if ($ok && !is_dir($processing)) {
            echo "$dateend - [$worker_id] Host ".$host_data['name']." - Rsync finished with code $return_code, but backup directory is missing!\n";
            $ok = false;
        }
        if ($ok && $return_code != 0) {
            echo "$dateend - [$worker_id] Host ".$host_data['name']." - Rsync finished with code $return_code (partial transfer), see backup log\n";
        }

        if ($ok && !rename($processing, "$bkpath/$datestamp")) {
            echo "$dateend - [$worker_id] Host ".$host_data['name']." - can not rename $processing!\n";
            $ok = false;
        }

        if (!$ok) {
            echo "$dateend - [$worker_id] Host ".$host_data['name']." - Rsync finished with code $return_code!\n";
            if (is_dir($processing)) exec("rm -rf -- ".escapeshellarg($processing));
            echo "$dateend - [$worker_id] Something was wrong, backup failed!\n";
            mark_backup_failed($db, $host_id);
            cli_set_process_title("phbackup-$worker_id [idle]");
            return;
        }

        if (is_link("$bkpath/111-Latest")) unlink("$bkpath/111-Latest");
        // Relative link, so host directory can be moved (e.g. when group path is changed)
        symlink($datestamp, "$bkpath/111-Latest");
        echo "$dateend - [$worker_id] Host ".$host_data['name']." - successfully backed up!\n";

        echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaning old backups\n";
        cli_set_process_title("phbackup-$worker_id [cleaning - ".$host_data['name']."]");
        $removed = rotate_backups($bkpath, $host_vars['backup_keep_period'], $backup_min_keep ?? 3);
        echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaned old backups (".count($removed)." removed)\n";

        finish_backup_ok($db, $host_data, $host_vars);
        cli_set_process_title("phbackup-$worker_id [idle]");
}


function backup_cisco_switch_via_telnet ($db, $host_data, $host_vars) {

        global $worker_id, $host_id, $backup_min_keep;

        // Setting process title
        cli_set_process_title("phbackup-$worker_id [backing ".$host_data['name']."]");

        $datestamp = date("Y-m-d_H:i:s");
        $bkpath = host_backup_path($host_data);
        $outfile = "$bkpath/$datestamp.txt";

        // Check if directory exists
        if (!is_dir($bkpath)) mkdir($bkpath, 0750, true);

        // All data is passed via environment, so it is never interpreted by shell
        $script = '
        (
        printf "open %s %s\n" "$PHB_HOST" "$PHB_PORT"
        sleep 10
        printf "%s\n" "$PHB_USER"
        sleep 5
        printf "%s\n" "$PHB_PASS"
        sleep 5
        echo "term len 0"
        sleep 5
        echo "sh run"
        sleep 10
        echo "exit"
        ) | telnet > "$PHB_DIR/tmp.txt"
        tac "$PHB_DIR/tmp.txt" | sed "/Current configuration/Q" | sed "/show running-config/Q" | tac > "$PHB_OUT"
        sed -i "\$d" "$PHB_OUT"
        rm -f "$PHB_DIR/tmp.txt"
        ';

        $env = array_merge(getenv(), array(
            'PHB_HOST' => $host_data['ip'],
            'PHB_PORT' => (string)(int)$host_data['port'],
            'PHB_USER' => $host_data['user'],
            'PHB_PASS' => $host_data['ssh_key'],
            'PHB_DIR'  => $bkpath,
            'PHB_OUT'  => $outfile,
        ));

        // Backup itself
        $proc = proc_open(array('/bin/bash', '-c', $script), array(0 => array('file', '/dev/null', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')), $pipes, null, $env);
        if (is_resource($proc)) proc_close($proc);

        clearstatcache();
        $ok = is_file($outfile) && filesize($outfile) >= 2000;

        $dateend = date("Y-m-d H:i:s");

        // Checking results
        if (!$ok) {
            if (is_file($outfile)) rename($outfile, "$bkpath/error-$datestamp.txt");
            echo "$dateend - [$worker_id] Something was wrong, backup failed!\n";
            mark_backup_failed($db, $host_id);
            cli_set_process_title("phbackup-$worker_id [idle]");
            return;
        }

        if (is_link("$bkpath/111-Latest.txt")) unlink("$bkpath/111-Latest.txt");
        symlink("$datestamp.txt", "$bkpath/111-Latest.txt");
        echo "$dateend - [$worker_id] Host ".$host_data['name']." - successfully backed up!\n";
        echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaning old backups\n";
        cli_set_process_title("phbackup-$worker_id [cleaning - ".$host_data['name']."]");
        $removed = rotate_backups($bkpath, $host_vars['backup_keep_period'], $backup_min_keep ?? 3, '.txt');
        echo "$dateend - [$worker_id] Host ".$host_data['name']." - cleaned old backups (".count($removed)." removed)\n";

        finish_backup_ok($db, $host_data, $host_vars);
        cli_set_process_title("phbackup-$worker_id [idle]");
}





?>
