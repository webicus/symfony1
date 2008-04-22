<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfSymfonyCommandApplication manages the symfony CLI.
 *
 * @package    symfony
 * @subpackage command
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfSymfonyCommandApplication extends sfCommandApplication
{
  /**
   * Configures the current symfony command application.
   *
   * @param string The symfony lib directory
   */
  public function configure()
  {
    if (!isset($this->options['symfony_lib_dir']))
    {
      throw new sfInitializationException('You must pass a "symfony_lib_dir" option.');
    }

    $configuration = new sfProjectConfiguration(getcwd());

    // application
    $this->setName('symfony');
    $this->setVersion(SYMFONY_VERSION);

    $this->loadTasks();
  }

  /**
   * Runs the current application.
   *
   * @param mixed The command line options
   */
  public function run($options = null)
  {
    $this->handleOptions($options);
    $arguments = $this->commandManager->getArgumentValues();

    if (!isset($arguments['task']))
    {
      $arguments['task'] = 'list';
      $this->commandOptions .= $arguments['task'];
    }

    $this->currentTask = $this->getTaskToExecute($arguments['task']);

    if ($this->currentTask instanceof sfCommandApplicationTask)
    {
      $this->currentTask->setCommandApplication($this);
    }

    $ret = $this->currentTask->runFromCLI($this->commandManager, $this->commandOptions);

    $this->currentTask = null;

    return $ret;
  }

  /**
   * Loads all available tasks.
   *
   * Looks for tasks in the symfony core, the current project and all project plugins.
   */
  protected function loadTasks()
  {
    $dirs = array(sfConfig::get('sf_symfony_lib_dir').'/task'); // symfony core tasks

    // only add bundled plugins that were not overloaded in the project
    $installedPlugins = sfFinder::type('dir')->maxdepth(0)->relative()->in(sfConfig::get('sf_root_dir').'/plugins');
    foreach (new DirectoryIterator(sfConfig::get('sf_symfony_lib_dir').'/plugins') as $bundledPlugin)
    {
      if ($bundledPlugin->isDot())
      {
        continue;
      }

      $path = $bundledPlugin->getRealpath().'/lib/task';
      if (!in_array($bundledPlugin->getFilename(), $installedPlugins) && is_dir($path))
      {
        $dirs[] = $path;
      }
    }

    $dirs = array_merge($dirs, array(sfConfig::get('sf_plugins_dir').'/*/lib/task',             // plugin tasks
                                     sfConfig::get('sf_lib_dir').'/task',                       // project tasks
                                     ));

    $finder = sfFinder::type('file')->name('*Task.class.php');

    foreach ($dirs as $globDir)
    {
      if (!$dirs = glob($globDir))
      {
        continue;
      }

      foreach ($finder->in($dirs) as $task)
      {
        require_once $task;
      }
    }
  }

  /**
   * @see sfCommandApplication
   */
  public function getLongVersion()
  {
    return sprintf('%s version %s (%s)', $this->getName(), $this->formatter->format($this->getVersion(), 'INFO'), sfConfig::get('sf_symfony_lib_dir'))."\n";
  }
}
