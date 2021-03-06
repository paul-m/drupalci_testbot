<?php
/**
 * @file
 * Contains \DrupalCI\Plugin\BuildSteps\setup\Checkout
 *
 * Processes "setup: checkout:" instructions from within a job definition.
 */

namespace DrupalCI\Plugin\BuildSteps\setup;

use DrupalCI\Console\Output;
use DrupalCI\Plugin\JobTypes\JobInterface;

/**
 * @PluginID("checkout")
 */
class Checkout extends SetupBase {

  /**
   * {@inheritdoc}
   */
  public function run(JobInterface $job, $data) {
    // Data format:
    // i) array('protocol' => 'local', 'srcdir' => '/tmp/drupal', 'checkout_dir' => '/tmp/checkout')
    // checkout_dir is optional.
    // or
    // ii) array('protocol' => 'git', 'repo' => 'git://code.drupal.org/drupal.git', 'branch' => '8.0.x', 'depth' => 1)
    // depth is optional
    // or
    // iii) array(array(...), array(...))
    // Normalize data to the third format, if necessary
    $data = (count($data) == count($data, COUNT_RECURSIVE)) ? [$data] : $data;

    Output::writeLn("<info>Populating container codebase data volume.</info>");
    foreach ($data as $details ) {
      // TODO: Ensure $details contains all required parameters
      $details += ['protocol' => 'git'];
      switch ($details['protocol']) {
        case 'local':
          $this->setupCheckoutLocal($job, $details);
          break;
        case 'git':
          $this->setupCheckoutGit($job, $details);
          break;
      }
      // Break out of loop if we've encountered any errors
      if ($job->getErrorState() !== FALSE) {
        break;
      }
    }
    return;
  }

  protected function setupCheckoutLocal(JobInterface $job, $details) {
    $source_dir = isset($details['source_dir']) ? $details['source_dir'] : './';
    $checkout_dir = isset($details['checkout_dir']) ? $details['checkout_dir'] : $job->getJobCodebase()->getWorkingDir();
    // TODO: Ensure we don't end up with double slashes
    // Validate source directory
    if (!is_dir($source_dir)) {
      Output::error("Directory error", "The source directory <info>$source_dir</info> does not exist.");
      $job->error();
      return;
    }
    // Validate target directory.  Must be within workingdir.
    if (!($directory = $this->validateDirectory($job, $checkout_dir))) {
      // Invalidate checkout directory
      Output::error("Directory error", "The checkout directory <info>$directory</info> is invalid.");
      $job->error();
      return;
    }
    Output::writeln("<comment>Copying files from <options=bold>$source_dir</options=bold> to the local checkout directory <options=bold>$directory</options=bold> ... </comment>");
    // TODO: Make sure target directory is empty
#    $this->exec("cp -r $source_dir/. $directory", $cmdoutput, $result);
    $exclude_var = isset($details['DCI_EXCLUDE']) ? '--exclude="' . $details['DCI_EXCLUDE'] . '"' : "";
    $this->exec("rsync -a $exclude_var  $source_dir/. $directory", $cmdoutput, $result);
    if ($result !== 0) {
      Output::error("Copy error", "Error encountered while attempting to copy code to the local checkout directory.");
      $job->error();
      return;
    }
    Output::writeLn("<comment>DONE</comment>");
  }

  protected function setupCheckoutGit(JobInterface $job, $details) {
    Output::writeLn("<info>Entering setup_checkout_git().</info>");
    $repo = isset($details['repo']) ? $details['repo'] : 'git://drupalcode.org/project/drupal.git';
    $git_branch = isset($details['branch']) ? $details['branch'] : 'master';
    $checkout_directory = isset($details['checkout_dir']) ? $details['checkout_dir'] : $job->getJobCodebase()->getWorkingDir();
    // TODO: Ensure we don't end up with double slashes
    // Validate target directory.  Must be within workingdir.
    if (!($directory = $this->validateDirectory($job, $checkout_directory))) {
      // Invalid checkout directory
      Output::error("Directory Error", "The checkout directory <info>$directory</info> is invalid.");
      $job->error();
      return;
    }
    Output::writeLn("<comment>Performing git checkout of $repo $git_branch branch to $directory.</comment>");
    // TODO: Make sure target directory is empty
    $git_depth = '';
    if (isset($details['depth']) && empty($details['commit_hash'])) {
      $git_depth = '--depth ' . $details['depth'];
    }
    $cmd = "git clone -b $git_branch $git_depth $repo '$directory'";
    Output::writeLn("Git Command: $cmd");
    $this->exec($cmd, $cmdoutput, $result);
    if ($result !==0) {
      // Git threw an error.
      Output::error("Checkout Error", "The git checkout returned an error.  Error Code: $result");
      $job->error();
      return;
    }

    if (!empty($details['commit_hash'])) {
      $cmd =  "cd " . $directory . " && git reset -q --hard " . $details['commit_hash'] . " ";
      Output::writeLn("Git Command: $cmd");
      $this->exec($cmd, $cmdoutput, $result);
    }
    if ($result !==0) {
      // Git threw an error.
      $job->errorOutput("Checkout failed", "The git checkout returned an error.");
      // TODO: Pass on the actual return value for the git checkout
      return;
    }

    $cmd = "cd '$directory' && git log --oneline -n 1 --decorate";
    $this->exec($cmd, $cmdoutput, $result);
    Output::writeLn("<comment>Git commit info:</comment>");
    Output::writeLn("<comment>\t" . implode($cmdoutput));

    Output::writeLn("<comment>Checkout complete.</comment>");
  }

}
