<?php
namespace Amp\Permission;

interface PermissionInterface {
  /**
   * Mark a directory as web-writable
   */
  const WEB_WRITE = 'write';

  /**
   * Set the permissions for a directory
   *
   * @param mixed $perm eg PermissionInterface::WEB_WRITE
   * @param string $dir the directory whose permissions should change
   */
  public function applyDirPermission($perm, $dir);
}