<?php
namespace Amp;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A brief console interaction which asks the user to enter
 * a value for a config "parameter". Displays the name,
 * description, example, default, etc., in a standard format.
 */
class ConfigPrompt {
  /**
   * @var callable
   */
  private $ask;

  /**
   * @var ConfigRepository
   */
  private $config;

  /**
   * @var ContainerInterface
   */
  private $container;

  /**
   * @var string
   */
  private $description;

  /**
   * @var string
   */
  private $example;

  /**
   * @var string
   */
  private $parameter;

  public function __construct(ContainerInterface $container, ConfigRepository $config, $parameter) {
    $this->container = $container;
    $this->config = $config;
    $this->parameter = $parameter;
    if ($config && $parameter) {
      $this->description = $this->config->getDescription($parameter);
      $this->example = $this->config->getExample($parameter);
    }
  }

  public function setParameter($parameter) {
    $this->parameter = $parameter;
    return $this;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function setExample($example) {
    $this->example = $example;
    return $this;
  }

  public function setAsk($ask) {
    $this->ask = $ask;
    return $this;
  }

  public function execute(InputInterface $input, OutputInterface $output, DialogHelper $dialog) {
    $default = $this->getContainer()->getParameter($this->parameter);

    $output->writeln("");
    $output->writeln("<comment>Parameter</comment>: {$this->parameter}");
    if ($this->description) {
      $output->writeln("<comment>Description</comment>: {$this->description}");
    }
    if ($this->example) {
      $output->writeln("<comment>Example</comment>: {$this->example}");
    }
    $output->writeln("<comment>Default</comment>: {$default}");

    if ($this->ask) {
      $value = call_user_func($this->ask, $default, $input, $output, $dialog);
    }
    else {
      $value = $dialog->ask($output, '>', $default);
    }

    $this->config->setParameter($this->parameter, $value);
    $this->getContainer()->setParameter($this->parameter, $value);
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  public function setContainer($container) {
    $this->container = $container;
  }

  /**
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   */
  public function getContainer() {
    return $this->container;
  }

}