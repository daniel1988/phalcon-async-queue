<?php
use Phalcon\Loader;
$loader = new Loader();

$loader->registerDirs(array(
    '../apps/controllers/',
    '../apps/models/'
));

$loader->register();