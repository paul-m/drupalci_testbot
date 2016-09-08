<?php

/**
 * @file
 * Contains \DrupalCI\Tests\DrupalCITestCase.
 */

namespace DrupalCI\Tests;

use DrupalCI\Console\Output;
use DrupalCI\Plugin\JobTypes\JobInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalCITestCase extends \PHPUnit_Framework_TestCase {

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $output;

  /**
   * @var \DrupalCI\Plugin\JobTypes\JobInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $job;

  public function setUp() {
    $this->output = $this->getMock(OutputInterface::class);
    Output::setOutput($this->output);
    $this->job = $this->getMock(JobInterface::class);
  }

}
