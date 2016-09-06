<?php

/**
 * Uses "Phpmd" to detect "messy" PHP code.
 */
final class ArcanistPhpmdLinter extends ArcanistExternalLinter {

  private $ruleset;

  public function getInfoName() {
    return 'PHP Mess Detector';
  }

  public function getInfoURI() {
    return 'https://github.com/phpmd/phpmd';
  }

  public function getInfoDescription() {
    return pht(
      'PHPMD is a spin-off project of PHP Depend and aims '.
      'to be a PHP equivalent of the well known Java tool PMD.');
  }

  public function getLinterName() {
    return 'PHPMD';
  }

  public function getLinterConfigurationName() {
    return 'phpmd';
  }

  public function getInstallInstructions() {
    return pht('See https://phpmd.org/download/index.html');
  }

  public function getLinterConfigurationOptions() {

    $options = array(
      'ruleset' => array(
          'type' => 'optional string',
          'help' => pht('The name or path of the ruleset to use')
            )
        );

        return $options + parent::getLinterConfigurationOptions();
    }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'ruleset':
        $this->ruleset = $value;
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  protected function getMandatoryFlags() {
    $options = array('xml');

    if ($this->ruleset) {
      $options[] = $this->ruleset;
    } else {
        $options[] = 'codesize';
    }

    return $options;
  }

  public function getDefaultBinary() {
    return 'phpmd';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^PHPMD (?P<version>\d+\.\d+\.\d+)\b/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {

    if (!$err) {
      return array();
    }

    $report_dom = new DOMDocument();
    $ok = @$report_dom->loadXML($stdout);
    if (!$ok) {
      return false;
    }

    $files = $report_dom->getElementsByTagName('file');
    $messages = array();
    foreach ($files as $file) {
      foreach ($file->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
          continue;
        }

          foreach ($file->childNodes as $violation) {

              if ($violation instanceof DOMText) {
                  continue;
              }

              $message = id(new ArcanistLintMessage())
                  ->setPath($path)
                  ->setName($violation->getAttribute('ruleset'))
                  ->setLine($violation->getAttribute('beginline'))
                  ->setChar(1)
                  ->setCode('PHPMD' . $child->getAttribute('priority'))
                  ->setDescription($violation->nodeValue)
                  ->setSeverity('error');

              $messages[] = $message;
          }
      }
    }

    return $messages;
  }

  protected function buildFutures(array $paths) {
    $executable = $this->getExecutableCommand();

    $futures = array();
    foreach ($paths as $path) {
      $disk_path = $this->getEngine()->getFilePathOnDisk($path);
      $path_argument = $this->getPathArgumentForLinterFuture($disk_path);

      $future = new ExecFuture('%C %C %Ls', $executable, $path_argument, $this->getCommandFlags());

      $future->setCWD($this->getProjectRoot());
      $futures[$path] = $future;
    }

    return $futures;
  }


  protected function getDefaultMessageSeverity($code) {
      return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
      return $code;
  }

}
