<?php
namespace StatonLab\TripalDock;

use StatonLab\TripalDock\Exceptions\SystemException;

class System
{
    /**
     * Execute a system call.
     *
     * @param string $cmd Shell command
     * @throws \StatonLab\TripalDock\Exceptions\SystemException
     */
    public function exec($cmd) {
        $call = system($cmd, $return);

        if($call === FALSE) {
            throw new SystemException("Unable to execute the following command\n$cmd");
        }

        return $return;
    }
}
