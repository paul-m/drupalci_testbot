<?php
/**
 * @file
 * Contains \DrupalCI\Plugin\BuildSteps\publish\DrupalCIResults
 *
 * Processes "publish: drupalci_server:" instructions from within a job
 * definition. Gathers the resulting job artifacts and pushes them to a
 * DrupalCI Results server.
 */

namespace DrupalCI\Plugin\BuildSteps\publish;
use DrupalCI\Plugin\JobTypes\JobInterface;
use DrupalCI\Plugin\PluginBase;
use DrupalCIResultsApi\Api;
use Symfony\Component\Yaml\Yaml;
use SQLite3;
use DOMDocument;

/**
 * @PluginID("junit_xmlformat")
 */
class JunitXMLFormat extends PluginBase {

  /**
   * {@inheritdoc}
   */
  public function run(JobInterface $job, $data) {
    $arguments = $job->getBuildVars();
    if (!empty($arguments['DCI_JunitXml'])) {
      $output_dir = $arguments['DCI_JunitXml'];
    } else {
      $output_dir = ''; // TODO: this should utterly fail.
    }

    // Connect to the database and query for the tests and reformat them in a sane manner.
    // If there is a sqlite results database

    // $data format:
    // i) array('config' => '<configuration filename>'),
    // ii) array('host' => '...', 'username' => '...', 'password' => '...')
    // or a mixed array of the above
    // iii) array(array(...), array(...))
    // Normalize data to the third format, if necessary
    $data = (count($data) == count($data, COUNT_RECURSIVE)) ? [$data] : $data;

    // Get the groups from run-tests.sh
    $test_list = [];
    $tests = [];
    $group = 'nogroup';

    # TODO: find a better way to uncouple this from the other command dependency.
    $test_list = file('/var/lib/drupalci/artifacts/testgroups.txt', FILE_IGNORE_NEW_LINES);
    // Get rid of the first four lines
    $test_list = array_slice($test_list, 4);

    foreach ($test_list as $output_line) {
      if (substr($output_line, 0, 3) == ' - ') {
        // This is a class
        $class = substr($output_line, 3);
        $test_groups[$class] = $group;
      }
      else {
        // This is a group
        $group = ucwords($output_line);
      }
    }


    # TODO: get the sqlite dir from config.
    $db = new SQLite3('/var/lib/drupalci/artifacts/simpletest.sqlite');
    // Crack open the sqlite database.

    // query for simpletest results
    $results_map = array(
      'pass' => 'Pass',
      'fail' => 'Fail',
      'exception' => 'Exception',
      'debug' => 'Debug',
    );

    $statement = $db->prepare('SELECT * FROM simpletest ORDER BY test_id, test_class, message_id;');
    $q_result = $statement->execute();

    $results = array();

    $cases = 0;
    $errors = 0;
    $failures = 0;
    while ($result = $q_result->fetchArray(SQLITE3_ASSOC)) {

      if (isset($results_map[$result['status']])) {
        // Set the group from the lookup table
        $test_group = $test_groups[$result['test_class']];

        // Set the test class
        if (isset($result['test_class'])) {
          $test_class = $result['test_class'];
        }
        // Jenkins likes to see the groups and classnames together. -
        // This might need to be re-addressed when we look at the tests.
        $classname = $test_groups[$test_class] . '.' . $test_class;

        // Cleanup the class, and the parens from the test method name
        $test_method = substr($result['function'], strpos($result['function'], '>') + 1);
        $test_method = substr($test_method, 0, strlen($test_method) - 2);

        //$classes[$test_group][$test_class][$test_method]['classname'] = $classname;
        $result['file'] = substr($result['file'],14); // Trim off /var/www/html
        $classes[$test_group][$test_class][$test_method][] = array(
          'status' => $result['status'],
          'type' => $result['message_group'],
          'message' => strip_tags($result['message']),
          'line' => $result['line'],
          'file' => $result['file'],
        );
      }
    }
    $this->_build_xml($classes, $output_dir);
  }


  private function _build_xml($test_result_data, $output_dir) {
    // Maps statuses to their xml element for each testcase.
    $element_map = array(
      'pass' => 'system-out',
      'fail' => 'failure',
      'exception' => 'error',
      'debug' => 'system-err',
    );
    // Create an xml file per group?

    $test_group_id = 0;
    $doc = new DomDocument('1.0');
    $test_suites = $doc->createElement('testsuites');

    // TODO: get test name data from the job.
    $test_suites->setAttribute('name', "TODO SET");
    $test_suites->setAttribute('time', "TODO SET");


    // Go through the groups, and create a testsuite for each.
    foreach ($test_result_data as $groupname => $group_classes) {
      $test_suite = $doc->createElement('testsuite');
      $test_suite->setAttribute('id', $test_group_id);
      $test_suite->setAttribute('name', $groupname);
      $test_suite->setAttribute('timestamp', date('c'));
      $test_suite->setAttribute('hostname', "TODO: Set Hostname");
      $test_suite->setAttribute('package', "TODO: Set Package");
      // TODO: time test runs. $test_group->setAttribute('time', $test_group_id);
      // TODO: add in the properties of the job into the test run.


      // Loop through the classes in each group
      foreach ($group_classes as $class_name => $class_methods) {
        foreach ($class_methods as $test_method => $method_results) {
          $test_case = $doc->createElement('testcase');
          $test_case->setAttribute('classname', $groupname . "." . $class_name);
          $test_case->setAttribute('name', $test_method);
          $test_case_status = 'pass';
          $test_case_assertions = 0;
          $test_output = '';
          foreach ($method_results as $assertion) {


            if (!isset($assertion_counter[$assertion['status']])) {
              $assertion_counter[$assertion['status']] = 0;
            }
            $assertion_counter[$assertion['status']]++;

            if ($assertion['status'] == 'exception' || $assertion['status'] == 'fail') {
              $element = $doc->createElement($element_map[$assertion['status']]);
              $element->setAttribute('message', $method_results['message']);
              $element->setAttribute('type', $assertion['status']);
              // Assume that exceptions and fails are failed tests.
              $test_case_status = 'failed';
              $test_case->appendChild($element);
            }
            elseif (($assertion['status'] == 'pass') || ($assertion['status'] == 'debug')) {
             $test_output .= $assertion['status'] . ": [" . $assertion['type'] . "] Line " . $assertion['line'] . " of " . $assertion['file'] . ":\n" . $assertion['message'] . "\n\n";
            }

            $test_case_assertions++;

          }
          $std_out = $doc->createElement('system-out');
          $output = $doc->createCDATASection($test_output);
          $std_out->appendChild($output);
          $test_case->appendChild($std_out);

          // TODO: Errors and Failures need to be set per test Case.
          $test_case->setAttribute('status', $test_case_status);
          $test_case->setAttribute('assertions', $test_case_assertions);
          $test_case->setAttribute('time', "TODO: track time");

          $test_suite->appendChild($test_case);

        }
      }

      // Should this count the tests as part of the loop, or just array_count?
      $test_suite->setAttribute('tests', $test_group_id);
      $test_suite->setAttribute('failures', $test_group_id);
      $test_suite->setAttribute('errors', $test_group_id);
      /* TODO: Someday simpletest will disable or skip tests based on environment
      $test_group->setAttribute('disabled', $test_group_id);
      $test_group->setAttribute('skipped', $test_group_id);
      */
      $test_suites->appendChild($test_suite);
      $test_group_id++;
    }
    $test_suites->setAttribute('tests', "TODO SET");
    $test_suites->setAttribute('failures', "TODO SET");
    $test_suites->setAttribute('disabled', "TODO SET");
    $test_suites->setAttribute('errors', "TODO SET");
    $doc->appendChild($test_suites);
    file_put_contents($output_dir . '/testresults.xml', $doc->saveXML());

  }

}
