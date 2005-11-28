<?php

/*
 * This file is part of the symfony package.
 * (c) 2004, 2005 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfViewConfigHandler allows you to configure views.
 *
 * @package    symfony
 * @subpackage config
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfViewConfigHandler extends sfYamlConfigHandler
{    
  /**
   * Execute this configuration handler.
   *
   * @param string An absolute filesystem path to a configuration file.
   *
   * @return string Data to be written to a cache file.
   *
   * @throws <b>sfConfigurationException</b> If a requested configuration file does not exist or is not readable.
   * @throws <b>sfParseException</b> If a requested configuration file is improperly formatted.
   * @throws <b>sfInitializationException</b> If a view.yml key check fails.
   */
  public function & execute ($configFile, $param = array())
  {
    // set our required categories list and initialize our handler
    $categories = array('required_categories' => array());
    $this->initialize($categories);

    // parse the yaml
    $this->config = $this->parseYaml($configFile);

    // init our data array
    $data = array();

    // get default configuration
    $this->defaultConfig = array();
    $defaultConfigFile = SF_APP_CONFIG_DIR.'/'.basename($configFile);
    if (is_readable($defaultConfigFile))
    {
      $categories = array('required_categories' => array('default'));
      $this->initialize($categories);

      $this->defaultConfig = $this->parseYaml($defaultConfigFile);
    }

    // iterate through all view names
    $first = true;
    foreach ($this->config as $viewName => $values)
    {
      if ($viewName == 'all')
      {
        continue;
      }

      $data[] = ($first ? '' : 'else ')."if (\$this->viewName == '$viewName')\n".
                "{\n";

      // template name
      $templateName = $this->getConfigValue('template', $viewName);
      if ($templateName)
      {
        $data[] = "  \$templateName = \$action->getTemplate() ? \$action->getTemplate() : '$templateName';\n";
      }
      else
      {
        $data[] = "  \$templateName = \$action->getTemplate() ? \$action->getTemplate() : \$this->getContext()->getActionName();\n";
      }

      $data[] = "  if (!SF_SAFE_SLOT || (SF_SAFE_SLOT && !\$actionStackEntry->isSlot()))\n";
      $data[] = "  {\n";

      $data[] = $this->addLayout($viewName);
      $data[] = $this->addSlots($viewName);
      $data[] = $this->addHtmlHead($viewName);

      $data[] = "  }\n";

      $data[] = $this->addHtmlAsset($viewName);

      $data[] = "}\n";

      $first = false;
    }

    // general view configuration
    $data[] = ($first ? '' : "else\n{")."\n";
    $templateName = $this->getConfigValue('template', 'all');
    if ($templateName)
    {
      $data[] = "  \$templateName = \$action->getTemplate() ? \$action->getTemplate() : '$templateName';\n";
    }
    else
    {
      $data[] = "  \$templateName = \$action->getTemplate() ? \$action->getTemplate() : \$this->getContext()->getActionName();\n";
    }

    $data[] = "  if (!SF_SAFE_SLOT || (SF_SAFE_SLOT && !\$actionStackEntry->isSlot()))\n";
    $data[] = "  {\n";

    $data[] = $this->addLayout();
    $data[] = $this->addSlots();
    $data[] = $this->addHtmlHead();

    $data[] = "  }\n";

    $data[] = $this->addHtmlAsset();
    $data[] = ($first ? '' : "}")."\n";

    // compile data
    $retval = "<?php\n".
              "// auth-generated by sfViewConfigHandler\n".
              "// date: %s\n%s\n?>";
    $retval = sprintf($retval, date('m/d/Y H:i:s'), implode('', $data));

    return $retval;
  }

  private function addSlots($viewName = '')
  {
    $data = '';

    $slots = $this->mergeConfigValue('slots', $viewName);
    if (is_array($slots))
    {
      foreach ($slots as $name => $slot)
      {
        if (count($slot) > 1)
        {
          $data .= "    \$this->setSlot('$name', '{$slot[0]}', '{$slot[1]}');\n";
          $data .= "    if (SF_LOGGING_ACTIVE) \$context->getLogger()->info('{sfRenderView} set slot \"$name\" ({$slot[0]}/{$slot[1]})');\n";
        }
      }
    }

    return $data;
  }

  private function addLayout($viewName = '')
  {
    $data = '';

    $has_layout = $this->getConfigValue('has_layout', $viewName);
    if ($has_layout)
    {
      $layout = $this->getconfigValue('layout', $viewName);
      $data .= "    \$this->setDecoratorDirectory(SF_APP_TEMPLATE_DIR);\n".
               "    \$this->setDecoratorTemplate('$layout.php');\n";
    }

    return $data;
  }

  private function addHtmlHead($viewName = '')
  {
    $data = array();

    $tmp = "    \$action->addHttpMeta('%s', '%s', false);";
    foreach ($this->mergeConfigValue('http_metas', $viewName) as $httpequiv => $content)
    {
      $data[] = sprintf($tmp, $httpequiv, $content);
    }

    $tmp = "    \$action->addMeta('%s', '%s', false);";
    foreach ($this->mergeConfigValue('metas', $viewName) as $name => $content)
    {
      $data[] = sprintf($tmp, $name, $content);
    }

    return implode("\n", $data)."\n";
  }

  private function addHtmlAsset($viewName = '')
  {
    $data = array();

    $tmp = "  \$action->addStylesheet('%s');";
    $stylesheets = $this->mergeConfigValue('stylesheets', $viewName);
    if (is_array($stylesheets))
    {
      foreach ($stylesheets as $css)
      {
        $data[] = sprintf($tmp, $css);
      }
    }

    $tmp = "  \$action->addJavascript('%s');";
    $javascripts = $this->mergeConfigValue('javascripts', $viewName);
    if (is_array($javascripts))
    {
      foreach ($javascripts as $js)
      {
        $data[] = sprintf($tmp, $js);
      }
    }

    return implode("\n", $data)."\n";
  }
}

?>