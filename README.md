# ResLock
#### Advisory resource lock for files and other resources (similar to flock but works on all platforms).

Mainly used to protect a file from being written-to by two or more processes at the same time.  This
is similar to the PHP flock() function, the differences being that this is dependable (even on Windows)
and that the lock applies to any resource - not just files.

It works by checking for the absence/presence of a directory to determine whether an exclusive lock has
been achieved.  A directory is used in preference to a file because the mkdir() function will, in a single
platform-independent operation, return whether or not a directory exists and create it if it doesn't.

On Windows systems, at least, this method is more reliable than standard flock() which requires two operating
system operations - check whether the lock-file exists, and create it if it doesn't.  Since there are 2
operations, it is possible for several processes to be checking at the same time; each one concluding that the
lock-file doesn't exist, each one creating the file, and each one believing it has an exclusive lock.

####Requirements
ResLock requires the following:

* PHP 5.3.3+

####Installation
ResLock is installed via Composer. To add a dependency to ResLock in your project, either

Run the following to use the latest stable version

    composer require acet/reslock
or if you want the latest master version

    composer require acet/reslock:dev-master
You can also manually edit your composer.json file

{
    "require": {
       "acet/reslock": "v0.1.*"
    }
}

####Example - Update a file which may have other interested parties
```
use acet\ResLock;

$contentious_file = 'contentious.file';

$reslock = new ResLock();

// Provide a name for the resource (any string will do - can be a file name, or the name of your cat)
if ($reslock->lock('tiddles')) {

    // resource successfully locked

    $file_contents = file_get_contents($contentious_file);
    $string = "Do something";
    file_put_contents($contentious_file, $string);

    $reslock->unlock();
}

else {
    throw new exception("Unable to lock the resource");
}
```
