<?php

use DrupalCI\Plugin\Preprocess\definition\JunitXml;
use DrupalCI\Tests\Plugin\Preprocess\definition\DefinitionPreprocessorTestBase;

/**
 * @file
 * contains \DrupalCI\Tests\Plugin\Preprocess\definition\JUnitXMLDefinitionPreprocessorTest
 *
 * @group Plugin
 * @group DefinitionPreprocessor
 */

class JUnitXmlDefinitionPreprocessorTest extends DefinitionPreprocessorTestBase
{
  public function testJUnitXmlDefinitionPreprocessor() {
    $definition = $this->getDefinitionTemplate();
    $target_directory = 'my/target/directory';
    $dci_variables = [];

    $plugin = new JUnitXml();
    $plugin->process($definition, $target_directory, $dci_variables);
    $this->assertEquals($target_directory, $definition['publish']['junit_xmlformat']);
  }

}