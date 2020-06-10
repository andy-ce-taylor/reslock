<?php

require_once __DIR__ . '/../vendor/autoload.php';

use acet\ResLock;

// Update a file which may have other interested parties

$contentious_file = 'contentious.file';

$reslock = new ResLock();

if ($reslock->lock('My contentious resource')) {
    // resource successfully locked

    $file_contents = file_get_contents($contentious_file);
    $string = "Do something";
    file_put_contents($contentious_file, $string);

    $reslock->unlock();
}

else {
    throw new exception("Unable to lock the resource");
}
