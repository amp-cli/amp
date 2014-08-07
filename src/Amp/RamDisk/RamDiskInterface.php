<?php
namespace Amp\RamDisk;

interface RamDiskInterface {

  /**
   * @return string
   */
  public function getPath();

  /**
   * @return bool
   */
  function isMounted();

  /**
   * @void
   * @throws Exception
   */
  function mount();

  // FIXME: Why is there no unmount()?
}