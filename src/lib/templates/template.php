<?php
/**
* HTTPFS API
*/
class HTTPFS {

  /**
  * Get attributes
  */
  public static function httpfs_getattr($data) {
    $fields = unpack('a*path', $data);
    $s = lstat($fields['path']);
    if ($s) {
      printf('%c', OK);
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
      printf('%c', ENTRY_NOT_FOUND);
    }
  }

  /**
  * Get directory content
  */
  public static function httpfs_readdir($data) {
    $fields = unpack('a*path', $data);
    $d = scandir($fields['path']);
    if ($d) {
      printf('%c', OK);
      foreach ($d as $entry) {
        echo "$entry\x00";
      }
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Read file
  */
  public static function httpfs_read($data) {
    $fields = unpack('Nsize/Noffset/a*path', $data);
    $f = fopen($fields['path'], 'r');
    if ($f) {
      printf('%c', OK);
      fseek($f, $fields['offset']);
      echo fread($f, $fields['size']);
      fclose($f);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Write file
  */
  public static function httpfs_write($data) {
    $fields = unpack('Nsize/Noffset', $data);
    list($path, $writeData) = explode("\x00", substr($data, 8), 2);
    $f = fopen($path, 'a');
    if ($f) {
      printf('%c', OK);
      fseek($f, $fields['offset']);
      $writeSize = fwrite($f, $writeData, $fields['size']);
      fclose($f);
      echo pack('N', $writeSize);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Truncate file
  */
  public static function httpfs_truncate($data) {
    $fields = unpack('Noffset/a*path', $data);
    $f = fopen($fields['path'], 'r+');
    if ($f) {
      printf('%c', OK);
      ftruncate($f, $fields['offset']);
      fclose($f);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Create file
  */
  public static function httpfs_create($data) {
    $fields = unpack('Nmode/a*path', $data);
    $f = fopen($fields['path'], 'w');
    if ($f) {
      printf('%c', OK);
      fclose($f);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Delete file
  */
  public static function httpfs_unlink($data) {
    $fields = unpack('a*path', $data);
    $u = unlink($fields['path']);
    if ($u) {
      printf('%c', OK);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Create directory
  */
  public static function httpfs_mkdir($data) {
    $fields = unpack('Nmode/a*path', $data);
    $m = mkdir( $fields[ 'path' ] , $fields[ 'mode' ] );
    if ($m) {
      printf('%c', OK);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Delete directory
  */
  public static function httpfs_rmdir($data) {
    $fields = unpack('a*path', $data);
    $u = rmdir($fields['path']);
    if ($u) {
      printf('%c', OK);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Rename path
  */
  public static function httpfs_rename($data) {
    list($path, $newpath) = explode("\x00", $data, 2);
    $r = rename($path, $newpath);
    if ($r) {
      printf('%c', OK);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Create hard link
  */
  public static function httpfs_link($data) {
    list($path, $newpath) = explode("\x00", $data, 2);
    $r = link($path, $newpath);
    if ($r) {
      printf('%c', OK);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Read original path for symbolic link
  */
  public static function httpfs_readlink($data) {
    $fields = unpack('a*path', $data);
    $r = readlink($fields['path']);
    if ($r) {
      printf('%c', OK);
      echo $r;
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Create symbolic link
  */
  public static function httpfs_symlink($data) {
    list($path, $newpath) = explode("\x00", $data, 2);
    $s = symlink($path, $newpath);
    if ($s) {
      printf('%c', OK);
    } else {
      printf('%c', CANNOT_ACCESS);
    }
  }

  /**
  * Change path access modes
  */
  public static function httpfs_chmod($data) {
    $fields = unpack('Nmode/a*path', $data);
    $c = chmod($fields['path'], $fields['mode']);
    if ($c) {
      printf('%c', OK);
    } else {
      printf('%c', NOT_PERMITTED);
    }
  }

  /**
  * Change path owner
  */
  public static function httpfs_chown($data) {
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
      printf('%c', OK);
    } else {
      printf('%c', NOT_PERMITTED);
    }
  }
}

/**
* HTTPFS PHP Server 
*/
class server {

  /**
  * Contains the operation codes/httpfs-api
  * @var array
  */
  protected $opCodeNames = array();

  /**
  * Initialize httpfs object
  */
  public function __construct($opCodeNames) {  
    error_reporting(0);
    if (VERBOSE) {
      error_reporting(E_ALL);
    }
    $this->opCodeNames = $opCodeNames;
  }

  /**
  * Perform operation
  */
  public function perform($post) {
    $opcode = ord($post);
    forward_static_call(array('HTTPFS', $this->opCodeNames[$opcode]), substr($post, 1));
  }
}

// initialize and run operation
$server = new server($HTTPFS_OPCODE_NAMES);
$server->perform(file_get_contents('php://input'));