<?php

/**
 * Executes a command and reurns an array with exit code, stdout and stderr content
 * @param string $cmd - Command to execute
 * @param string|null $workdir - Default working directory
 * @return string[] - Array with keys: 'code' - exit code, 'out' - stdout, 'err' - stderr
 */
function execute($cmd, $workdir = null) {

    if (is_null($workdir)) {
        $workdir = __DIR__;
    }

    $descriptorspec = array(
       0 => array("pipe", "r"),  // stdin
       1 => array("pipe", "w"),  // stdout
       2 => array("pipe", "w"),  // stderr
    );

    $process = proc_open($cmd, $descriptorspec, $pipes, $workdir, null);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return [
        'code' => proc_close($process),
        'out' => trim($stdout),
        'err' => trim($stderr),
    ];
}

$res = execute('git status');
//var_dump($res);
echo "<h2>Git Status</h2><pre>";
echo $res["code"];
echo $res["out"];
echo $res["err"];
echo "</pre><h2>Last Log Messages</h2>";

//$command = 'git status -sb';
//$output = exec($command, $array_output, $ret_val);
//var_dump($ret_val);
//echo "<pre>$output</pre>";

$msg1 = `git log --oneline --all`;
echo "<pre>$msg1</pre>";

//$message = `git log -10 --pretty=%B`; // get commit message
//echo "<pre>$message</pre>";
?>
