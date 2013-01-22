<?php
/**
* HTTPFS PHP Server 
*/
class httpfs {

  /**
  * PHP Error message will be set in this variable if VERBOSE flag is set
  * @var string
  */
  protected $errorMessage = '';

  /**
  * Contains the operation codes/httpfs-api
  * @var array
  */
  protected $opCodeNames = array();

  /**
  * Initialize httpfs object
  */
  public function __construct() {  
    error_reporting(0);
    if (VERBOSE) {
      set_error_handler(array($this, 'storeError'));
    }
  }

  /**
  * Perform operation
  */
  public function do() {
    $post = file_get_contents('php://input');
    $opcode = ord($post);
    call_user_func(
      array(
        $this,
        $this->camelToUnderline(
          $this->opCodeNames[$opcode]
        )
      ),
      substr($post, 1)
    );
  }

  /* UTILITY STUFF */

  /**
  * Convert camelcase notation to underline notation
  */
  protected function camelToUnderline($str) {
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
  }

  /**
  * Store php error message in $this->errorMessage for debugging purpose
  */
  protected function storeError($errno, $error) {
    $this->errorMessage = $error;
  }

  /**
  * Output OK message
  */
  protected function dumpOk() {
    printf('%c', OK);
  }

  /**
  * Output error message
  */
  protected function dumpError($error, $customErrorMessage = NULL) {
    printf('%c', $error);
    $message = $customErrorMessage ? $customErrorMessage : $this->errorMessage;
    if ($message) {
      echo $message;
    }
  }

  /* FUSE API STUFF */

  /**
  * Get attributes
  */
  protected function httpfsGetattr($data) {
    $fields = unpack('a*path', $data);
    $s = lstat($fields['path']);
    if ($s) {
      $this->dumpOk();
      echo pack(
        'NNNNNNNNNNNNN',
        $s['dev'],
        $s['ino'],
        $s['mode'],
        $s['nlink'] ,
        $s['uid'],
        $s['gid'],
        $s['rdev'],
        $s['size'],
        $s['atime'],
        $s['mtime'],
        $s['ctime'],
        $s['blksize'],
        $s['blocks']
      );
    } else {
      $this->dumpError(ENTRY_NOT_FOUND);
    }
  }

  /**
  * Get directory content
  */
  protected function httpfsReaddir($data) {
    $fields = unpack('a*path', $data);
    $d = scandir($fields['path']);
    if ($d) {
      $this->dumpOk();
      foreach ($d as $entry) {
        echo "$entry\x00";
      }
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Read file
  */
  protected function httpfsRead($data) {
    $fields = unpack('Nsize/Noffset/a*path', $data);
    $f = fopen($fields['path'], 'r');
    if ($f) {
      $this->dumpOk();
      fseek($f, $fields['offset']);
      echo fread($f, $fields['size']);
      fclose($f);
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Write file
  */
  protected function httpfsWrite($data) {
    $fields = unpack('Nsize/Noffset', $data);
    list($path, $writeData) = explode("\x00", substr($data, 8), 2);
    $f = fopen($path, 'a');
    if ($f) {
      $this->dumpOk();
      fseek($f, $fields['offset']);
      $writeSize = fwrite($f, $writeData, $fields['size']);
      fclose($f);
      echo pack('N', $writeSize);
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Truncate file
  */
  protected function httpfsTruncate($data) {
    $fields = unpack('Noffset/a*path', $data);
    $f = fopen($fields['path'], 'r+');
    if ($f) {
      $this->dumpOk();
      ftruncate($f, $fields['offset']);
      fclose($f);
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Create file
  */
  protected function httpfsCreate($data) {
    $fields = unpack('Nmode/a*path', $data);
    $f = fopen($fields['path'], 'w');
    if ($f) {
      $this->dumpOk();
      fclose($f);
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Delete file
  */
  protected function httpfsUnlink($data) {
    $fields = unpack('a*path', $data);
    $u = unlink($fields['path']);
    if ($u) {
      $this->dumpOk();
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Create directory
  */
  protected function httpfsMkdir($data) {
    $fields = unpack('Nmode/a*path', $data);
    $m = mkdir( $fields[ 'path' ] , $fields[ 'mode' ] );
    if ($m) {
      $this->dumpOk();
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Delete directory
  */
  protected function httpfsRmdir($data) {
    $fields = unpack('a*path', $data);
    $u = rmdir($fields['path']);
    if ($u) {
      $this->dumpOk();
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Rename path
  */
  protected function httpfsRename($data) {
    list($path, $newpath) = explode("\x00", $data, 2);
    $r = rename($path, $newpath);
    if ($r) {
      $this->dumpOk();
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Create hard link
  */
  protected function httpfsLink($data) {
    list($path, $newpath) = explode("\x00", $data, 2);
    $r = link($path, $newpath);
    if ($r) {
      $this->dumpOk();
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Read original path for symbolic link
  */
  protected function httpfsReadlink($data) {
    $fields = unpack('a*path', $data);
    $r = readlink($fields['path']);
    if ($r) {
      $this->dumpOk();
      echo $r;
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Create symbolic link
  */
  protected function httpfsSymlink($data) {
    list($path, $newpath) = explode("\x00", $data, 2);
    $s = symlink($path, $newpath);
    if ($s) {
      $this->dumpOk();
    } else {
      $this->dumpError(CANNOT_ACCESS);
    }
  }

  /**
  * Change path access modes
  */
  protected function httpfsChmod($data) {
    $fields = unpack('Nmode/a*path', $data);
    $c = chmod($fields['path'], $fields['mode']);
    if ($c) {
      $this->dumpOk();
    } else {
      $this->dumpError(NOT_PERMITTED);
    }
  }

  /**
  * Change path owner
  */
  protected function httpfsChown($data) {
    $fields = unpack('Nuid/Ngid/a*path', $data);
    if ($fields['uid'] != 0xffffffff) {
      $u = chown($fields['path'], $fields['uid']);
      $g = TRUE;
    }
    if ($fields['gid'] != 0xffffffff) {
      $g = chgrp($fields['path'], $fields['gid']);
      $u = TRUE;
    }
    if ($u && $g) {
      $this->dumpOk();
    } else {
      $this->dumpError(NOT_PERMITTED);
    }
  }
}

// initialize and run operation
$httpfs = new httpfs($HTTPFS_OPCODE_NAMES);
$httpfs->do();