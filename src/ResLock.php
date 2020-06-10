<?php

namespace acet\reslock;

/**
*  Advisory lock for files and other resources (similar to flock, but dependable on all platforms).
 *
 *  Mainly used to protect a file from being written-to by two or more processes at the same time.  This
 *  is similar to the PHP flock() function, the differences being that this is dependable (even on Windows)
 *  and that the lock applies to a resource, rather than just a file.
 *
 *  It works by checking for the absence/presence of a directory to determine whether an exclusive lock has
 *  been achieved.  A directory is used (instead of a file) because the mkdir() function will, in a single
 *  platform-independent operation, return whether or not a directory exists and create it if it doesn't.
 *
 *  This is more reliable than standard flock() which requires 2 operations - check whether the lock-file
 *  exists, and then create it if it doesn't.  Having 2 operations, it is possible for several processes to
 *  be checking at the same time; each one concluding that the lock-file doesn't exist, and each one creating
 *  the file.
 *
 *  Usage example:
 *
 *      use acet\ResLock;
 *
 *      // Update a file which may have other interested parties
 *
 *      $contentious_file = 'contentious.file';
 *
 *      $reslock = new ResLock();
 *
 *      $handle = $reslock->lock('My contentious resource');
 *      if ($handle) {
 *          // resource successfully locked
 *
 *          $file_contents = file_get_contents($contentious_file);
 *          $string = "Do something";
 *          file_put_contents($contentious_file, $string);
 *
 *          $reslock->unlock($handle);
 *      } else {
 *          throw new exception("Unable to lock the resource");
 *      }
 *
 * @package acet\ResLock
 * @author  Andy Taylor <andy.ce.taylor@gmail.com>
 */
class ResLock
{
    // Maximum length of pause when waiting for a resource to be released.
    const MAX_MILLISECOND_PAUSE  = 10000; // (YOU CAN CHANGE THIS) - 10000 = 10 milliseconds = 000.00001 seconds

    // Maximum number of attempts to lock a resource.
    const MAX_LOCK_ATTEMPTS  = 10; // (YOU CAN CHANGE THIS)
    
    // Maximum number of seconds that a resource can be locked.
    const MAX_BLOCK_SECONDS = 6; // (YOU CAN CHANGE THIS)

    // Don't change anything below this line

    /** @var string */
    private $locks_path;

    /** @var array */
    private $lockDirs = [];

    /**
     * @param string $locks_path    - Path to a folder for storing locks.
     */
    public function __construct($locks_path=null)
    {
        if (is_null($locks_path)) {
            $locks_path = sys_get_temp_dir().'/reslock';
        }

        $locks_path = str_replace(["\\", '/'], DIRECTORY_SEPARATOR, rtrim($locks_path, "\\ ./") . '/');

        // create the locks directory if it doesn't already exist
        if (!file_exists($locks_path)) {
            @mkdir($locks_path, 0700, true);
        }

        $this->locks_path = $locks_path;
    }

    /**
     * Attempt to create an advisory lock on the named resource (such as a file or table).
     *
     * Note: Cooperating processes must all agree on the $resource_name.
     *
     * @param string $resource_name - The name of a resource such as a file or folder.
     *
     * @return int|false            - Resource handle if the resource was successfully locked.
     *                              - FALSE if the resource couldn't be locked after trying MAX_LOCK_ATTEMPTS times.
     */
    public function lock($resource_name)
    {
        // form a suitable flock name
        $hash = crc32($resource_name);
        $ld = $this->locks_path . $hash;

        // find the creation time of the existing lock dir (if it exists)
        $lockDir_ctime = file_exists($ld) ? filectime($ld) : 0;

        // find the maximum amount of time (microseconds) in which flocking can be attempted
        $timeout = self::MAX_MILLISECOND_PAUSE * self::MAX_LOCK_ATTEMPTS;

        $min_millisecond_pause = self::MAX_MILLISECOND_PAUSE / 10;
        $maintenance_done = false;

        // wait until the lock dir ceases to exist
        do {

            $time = time();

            // remove the lock dir if it already exists and is past it's use-by date
            if (file_exists($ld) && $time - $lockDir_ctime >= self::MAX_BLOCK_SECONDS) {
                @rmdir($ld);
            }

            // attempt the lock
            if (@mkdir($ld, 0700)) {
                $this->lockDirs[$hash] = ['file' => $ld, 'ctime' => filectime($ld)];
                return $hash;
            }

            // Lock failed!  Sleep before retrying

            // by randomizing the length of pause, the risk of follow-the-leader situations are minimized
            usleep($pause = mt_rand($min_millisecond_pause, self::MAX_MILLISECOND_PAUSE));

            // might as well do some maintenance while we're waiting
            if (!$maintenance_done) {

                // remove any forgotten locks
                $dir_refs = ['.', '..'];

                foreach (scandir($this->locks_path) as $file) {
                    if (!in_array($file, $dir_refs)) {
                        $file = $this->locks_path . $file;

                        if ($time - filectime($file) >= self::MAX_BLOCK_SECONDS) {
                            @rmdir($file);
                        }
                    }
                }

                $maintenance_done = true;
            }

            $timeout -= $pause;

        } while ($timeout > 0);

        return false;
    }

    /**
     * Automatically release any remaining locks when the ResLock object is destroyed.
     */
    public function __destruct()
    {
        foreach (array_keys($this->lockDirs) as $handle) {
            $this->unlock($handle);
        }
    }

    /**
     * Unlocks a previously locked resource.
     */
    public function unlock($handle)
    {
        if (array_key_exists($handle, $this->lockDirs)) {
            $ld = $this->lockDirs[$handle]['file'];
            $ld_ctime = $this->lockDirs[$handle]['ctime'];

            if (file_exists($ld) && filectime($ld) == $ld_ctime) {
                @rmdir($ld);
            }
        }
    }
}
