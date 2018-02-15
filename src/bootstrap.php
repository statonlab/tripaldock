<?php

if (class_exists('\StatonLab\TripalDock\NewCommand')) {
    return;
}

require BASE_DIR . '/src/Exceptions/SystemException.php';
require BASE_DIR .'/src/System.php';
require BASE_DIR .'/src/NewCommand.php';
