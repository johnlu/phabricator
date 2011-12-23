<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorRepositoryGitCommitDiscoveryDaemonTestCase
  extends PhabricatorTestCase {

  public function testVerifySameGitOrigin() {
    $cases = array(
      array(
        'ssh://user@domain.com/path.git',
        'ssh://user@domain.com/path.git',
        true,
        'Identical paths should pass.',
      ),
      array(
        'ssh://user@domain.com/path.git',
        'https://user@domain.com/path.git',
        true,
        'Protocol changes should pass.',
      ),
      array(
        'ssh://user@domain.com/path.git',
        'git@domain.com:path.git',
        true,
        'Git implicit SSH should pass.',
      ),
      array(
        'ssh://user@gitserv001.com/path.git',
        'ssh://user@gitserv002.com/path.git',
        true,
        'Domain changes should pass.',
      ),
      array(
        'ssh://alincoln@domain.com/path.git',
        'ssh://htaft@domain.com/path.git',
        true,
        'User/auth changes should pass.',
      ),
      array(
        'ssh://user@domain.com/apples.git',
        'ssh://user@domain.com/bananas.git',
        false,
        'Path changes should fail.',
      ),
      array(
        'ssh://user@domain.com/apples.git',
        'git@domain.com:bananas.git',
        false,
        'Git implicit SSH path changes should fail.',
      ),
    );

    foreach ($cases as $case) {
      list($remote, $config, $expect, $message) = $case;

      $ex = null;
      try {
        PhabricatorRepositoryGitCommitDiscoveryDaemon::verifySameGitOrigin(
          $remote,
          $config,
          '(a test case)');
      } catch (Exception $exception) {
        $ex = $exception;
      }

      $this->assertEqual(
        $expect,
        !$ex,
        "Verification that '{$remote}' and '{$config}' are the same origin ".
        "had a different outcome than expected: {$message}");
    }
  }

}