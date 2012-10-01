<?php

/**
 * This class has functions which handels OS-specific functions.
 */
class knj_os
{
    /**
     * Returns runnning processes.
     */
    static function getProcs($args = null)
    {
        if (is_array($args) && $args["grep"]) {
            $grep = $args["grep"];
            $command = "ps aux | " . $grep;
        } elseif (is_string($args) && strlen($args) > 0) {
            $grep = "grep " .knj_strings::UnixSafe($args);
            $command = "ps aux | " . $grep;
        } else {
            $command = "ps aux";
        }
        $command .= " | grep -vir grep";

        $psaux = knj_os::shellCMD($command);
        $procs = explode("\n", $psaux["result"]);
        $return = array();

        foreach ($procs as $proc) {
            $proc = trim($proc);

            if (strlen($proc) > 0 && substr($proc, 0, 4) != "USER") {
                if (preg_match("/^(\S+)\s+([0-9]+)\s+([0-9.]+)\s+([0-9.]+)\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+ ([\s\S]+)$/", $proc, $match)) {
                    $cmd = $match[5];

                    if ($cmd != $command && $cmd != $grep && $cmd != "sh -c " . $command) {
                        $user = $match[1];
                        $pid = $match[2];
                        $cpu = $match[3];
                        $ram = $match[4];

                        $return[] = array(
                            "user" => $user,
                            "pid" => $pid,
                            "cpu" => $cpu,
                            "ram" => $ram,
                            "cmd" => $cmd
                        );
                    }
                } else {
                    echo 'One of the processes wasnt not read: "' .$proc ."\".\n";
                }
            }
        }

        return $return;
    }

    static function getHomeDir()
    {
        if ($_SERVER["HOME"]) { //linux
            return $_SERVER["HOME"];
        } elseif ($_SERVER["USERPROFILE"]) { //windows
            return $_SERVER["USERPROFILE"];
        }

        $os = knj_os::getOS();
        if ($os == "linux") {
            $res = knj_os::shellCMD('echo $HOME');
            return trim($res["result"]);
        }

        throw new Exception("Could not find out home-dir.");
    }

    /**
     * Returns the type of running OS ("windows", "linux"...).
     */
    static function getOS()
    {
        global $knj_getos;

        if (!$knj_getos) {
            if (array_key_exists("OS", $_SERVER) && strpos(strtolower($_SERVER["OS"]), "windows") !== false) {
                $knj_getos["os"] = "windows";
            } else {
                $knj_getos["os"] = "linux";
            }
        }

        return $knj_getos;
    }
}

