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
  public function isMounted();

  /**
   * @void
   * @throws Exception
   */
  public function mount();

  // FIXME: Why is there no unmount()?

}
