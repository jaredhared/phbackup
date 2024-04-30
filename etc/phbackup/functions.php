<?php 

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
//        echo "Max version $max_ver_text is higher then current $script_ver_text. \nUpgrade path is $upgrade_path\n";
        return $upgrade_versions;
    }


}


function run_upgrade($db, $upgrade_versions) {

    $script_vars = get_script_vars($db);
    isset($script_vars['version']) ? $script_ver = $script_vars['version'] : $script_ver = 1;
    isset($script_vars['version_text']) ? $script_ver_text = $script_vars['version_text'] : $script_ver_text = "pre-1.5.0";
    $max_ver=$script_ver;
    $max_ver_text=$script_ver_text;

    ksort($upgrade_versions,SORT_NUMERIC);

    $ok=1;
    foreach ($upgrade_versions as $short => $version )
    {
        if ( $short > $script_ver && $ok>0 ) {

            $max_ver = $short;
            $max_ver_text = $version;
            $function = "upgrade_".$short."_".str_replace(".","_",$version);
            if(function_exists($function)) {
              $function($db);
            }
        }
    }

//    upgrade_150_1_5_0($db);

}





function upgrade_150_1_5_0($db) {
    $sql="select * from host_vars where host=10000;";
    $res = $db->query($sql);
    while ($row = $res->fetch_array()) {
        $script_vars[$row['var']] = $row['value'];
    }

    echo "Upgrading to 1.5.0... ";

    $ok=0;
    if (!isset($script_vars['version'])) {$sql="INSERT INTO host_vars (host,var,value) VALUES (10000, 'version', 150) "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }
    if (!isset($script_vars['version_text'])) {$sql="INSERT INTO host_vars (host,var,value) VALUES (10000, 'version_text', '1.5.0') "; $db->query($sql) ? $ok++ : printf("Error message: %s\n", $mysqli->error); }

    if ($ok==2) { echo "Done!\n"; return true; } 
    else { echo "Error!\n"; return false; }
};


function upgrade_158_1_5_8($db) {
};

function upgrade_152_1_5_2($db) {
};

function upgrade_153_1_5_3($db) {
};





?>