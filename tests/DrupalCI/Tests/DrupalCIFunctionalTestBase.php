<?php

namespace DrupalCI\Tests;

use DrupalCI\Console\Helpers\ConfigHelper;
use DrupalCI\Providers\DrupalCIServiceProvider;
use DrupalCI\Tests\DrupalCITestCase;
use Pimple\Container;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Framework for test-controlled runs of drupalci.
 *
 * This test base class will always use config:load to load the blank configset.
 * You then specify DCI_* config values by overriding self::$dciConfig.
 */
abstract class DrupalCIFunctionalTestBase extends \PHPUnit_Framework_TestCase {

  /**
   * DCI_* configuration for this test run.
   *
   * These values will be initialized using the config:set command.
   *
   * Override this array with your own config sets and settings.
   *
   * @code
   * [
   *   'DCI_JobType=simpletest',
   *   'DCI_CoreBranch=8.1.x',
   * ]
   * @endcode
   *
   * @var string[]
   */
  protected $dciConfig;

  /**
   * The service container.
   *
   * @var \Pimple\Container
   */
  protected $container;

  /**
   * @return \Pimple\Container
   */
  protected function getContainer() {
    if (empty($this->container)) {
      $this->container = new Container();
      $this->container->register(new DrupalCIServiceProvider());
    }
    return $this->container;
  }

  /**
   * @return \DrupalCI\Console\DrupalCIConsoleApp
   */
  protected function getConsoleApp() {
    $container = $this->getContainer();
    return $container['console'];
  }

  /**
   * Find a console command.
   *
   * @param string $name
   *
   * @return \Symfony\Component\Console\Command\Command
   *   The command you seek.
   *
   * @throws \InvalidArgumentException When command name is incorrect or
   *   ambiguous.
   */
  protected function getCommand($name) {
    $app = $this->getConsoleApp();
    return $app->find($name);
  }

  /**
   * Activate a configset.
   *
   * We use the config helper here because the config:load command requires
   * interaction.
   *
   * @param string $config_name
   */
  protected function configLoad($config_name) {
    $config_helper = new ConfigHelper();
    if (!$config_helper->activateConfig($config_name)) {
      throw new \PHPUnit_Framework_Exception('Unable to load the configset: ' . $config_name);
    }
  }

  /**
   * Set configuration values.
   *
   * This method calls the config:set method with the values to set.
   *
   * @param string[] $config
   */
  protected function configSet($config) {
    // Get the command.
    $config_set = $this->getCommand('config:set');

    // Set up the app.
    $app = $this->getConsoleApp();
    $app->setAutoExit(TRUE);
    $app->setCatchExceptions(FALSE);
    $options = ['interactive' => FALSE];

    // Add all our configs. Use CommandTester for convenience.
    $command_tester = new CommandTester($config_set);
    $command_tester->execute([
      'command' => $config_set->getName(),
      'assignment' => $config,
    ], $options);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Complain if there is no config.
    if (empty($this->dciConfig)) {
      throw new \PHPUnit_Framework_Exception('You must provide ' . get_class($this) . '::$dciConfig.');
    }
    // Skip a test that can't load 'blank.' We likely don't have the proper test
    // environment for an application test, but that's not a failure.
    try {
      $this->configLoad('blank');
      $this->configSet($this->dciConfig);
    }
    catch (\PHPUnit_Framework_Exception $e) {
      $this->markTestSkipped('This test needs a fixture environment.');
    }
    $app = $this->getConsoleApp();
    $app->setAutoExit(FALSE);
  }

}
