<?php

namespace DrupalCI\Tests\Console\Command;

use Docker\Exception\ImageNotFoundException;
use DrupalCI\Tests\Console\Command\CommandTestBase;
use Symfony\Component\Console\Tester\CommandTester;

class PullCommandTest extends CommandTestBase {

  /**
   * @todo: This test relies on a timeout of the docker image service. Fix that.
   * @group docker
   *
   * @coversNothing
   */
  public function testPull() {
    // Mark this test skipped if we're not in the proper environment.
    $wrappers = stream_get_wrappers();
    if (!in_array('unix', $wrappers)) {
      $this->markTestSkipped('This test needs a fixture environment.');
    }

    $c = $this->getConsoleApp();
    $command = $c->find('pull');
    $commandTester = new CommandTester($command);
    try {
      $commandTester->execute([
        'command' => $command->getName(),
        'container_name' => ['foof'],
      ]);
    }
    catch (ImageNotFoundException $e) {
      $display = $commandTester->getDisplay(TRUE);
      $this->assertRegExp('`Executing pull ...`', $display);
      $this->assertRegExp('`Pulling foof:latest container`', $display);
      return;
    }
    $this->fail('Run command did not throw exception or display status info.');
  }

}
