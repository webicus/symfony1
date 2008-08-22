<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfWebDebugPanelLogs adds a panel to the web debug toolbar with log messages.
 *
 * @package    symfony
 * @subpackage debug
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfWebDebugPanelLogs extends sfWebDebugPanel
{
  public function getLinkText()
  {
    return image_tag(sfConfig::get('sf_web_debug_web_dir').'/images/comment.png').' logs &amp; msgs';
  }

  public function getPanelContent()
  {
    $logs = '<table class="sfWebDebugLogs">
      <tr>
        <th>#</th>
        <th>type</th>
        <th>message</th>
      </tr>'."\n";
    $line_nb = 0;
    foreach ($this->webDebug->getLogger()->getLogs() as $logEntry)
    {
      $log = $logEntry['message'];

      $priority = $this->webDebug->getPriority($logEntry['priority']);

      if (strpos($type = $logEntry['type'], 'sf') === 0)
      {
        $type = substr($type, 2);
      }

      // xdebug information
      $debug_info = '';
      if (count($logEntry['debug_stack']))
      {
        $debug_info .= '&nbsp;<a href="#" onclick="sfWebDebugToggle(\'debug_'.$line_nb.'\'); return false;">'.image_tag(sfConfig::get('sf_web_debug_web_dir').'/images/toggle.gif').'</a><div class="sfWebDebugDebugInfo" id="debug_'.$line_nb.'" style="display:none">';
        foreach ($logEntry['debug_stack'] as $i => $logLine)
        {
          $debug_info .= '#'.$i.' &raquo; '.$this->formatLogLine($logLine).'<br/>';
        }
        $debug_info .= "</div>\n";
      }

      // format log
      $log = $this->formatLogLine($log);

      ++$line_nb;
      $logs .= sprintf("<tr class='sfWebDebugLogLine sfWebDebug%s %s'><td class=\"sfWebDebugLogNumber\">%s</td><td class=\"sfWebDebugLogType\">%s&nbsp;%s</td><td>%s%s</td></tr>\n",
        ucfirst($priority),
        $logEntry['type'],
        $line_nb,
        image_tag(sfConfig::get('sf_web_debug_web_dir').'/images/'.$priority.'.png'),
        $type,
        $log,
        $debug_info
      );
    }
    $logs .= '</table>';

    $types = array();
    foreach ($this->webDebug->getLogger()->getTypes() as $type)
    {
      $types[] = '<a href="#" onclick="sfWebDebugToggleMessages(\''.$type.'\'); return false;">'.$type.'</a>';
    }

    return '
      <ul id="sfWebDebugLogMenu">
        <li><a href="#" onclick="sfWebDebugToggleAllLogLines(true, \'sfWebDebugLogLine\'); return false;">[all]</a></li>
        <li><a href="#" onclick="sfWebDebugToggleAllLogLines(false, \'sfWebDebugLogLine\'); return false;">[none]</a></li>
        <li><a href="#" onclick="sfWebDebugShowOnlyLogLines(\'info\'); return false;">'.image_tag(sfConfig::get('sf_web_debug_web_dir').'/images/info.png').'</a></li>
        <li><a href="#" onclick="sfWebDebugShowOnlyLogLines(\'warning\'); return false;">'.image_tag(sfConfig::get('sf_web_debug_web_dir').'/images/warning.png').'</a></li>
        <li><a href="#" onclick="sfWebDebugShowOnlyLogLines(\'error\'); return false;">'.image_tag(sfConfig::get('sf_web_debug_web_dir').'/images/error.png').'</a></li>
        <li>'.implode("</li>\n<li>", $types).'</li>
      </ul>
      <div id="sfWebDebugLogLines">'.$logs.'</div>
    ';
  }

  public function getTitle()
  {
    return 'Log and debug messages';
  }

  /**
   * Formats a log line.
   *
   * @param string $logLine The log line to format
   *
   * @return string The formatted log lin
   */
  protected function formatLogLine($logLine)
  {
    static $constants;

    if (!$constants)
    {
      foreach (array('sf_app_dir', 'sf_root_dir', 'sf_symfony_lib_dir') as $constant)
      {
        $constants[realpath(sfConfig::get($constant)).DIRECTORY_SEPARATOR] = $constant.DIRECTORY_SEPARATOR;
      }
    }

    // escape HTML
    $logLine = htmlspecialchars($logLine, ENT_QUOTES, sfConfig::get('sf_charset'));

    // replace constants value with constant name
    $logLine = str_replace(array_keys($constants), array_values($constants), $logLine);

    $logLine = sfToolkit::pregtr($logLine, array('/&quot;(.+?)&quot;/s' => '"<span class="sfWebDebugLogInfo">\\1</span>"',
                                                   '/^(.+?)\(\)\:/S'      => '<span class="sfWebDebugLogInfo">\\1()</span>:',
                                                   '/line (\d+)$/'        => 'line <span class="sfWebDebugLogInfo">\\1</span>'));

    // special formatting for SQL lines
    $logLine = preg_replace('/\b(SELECT|FROM|AS|LIMIT|ASC|COUNT|DESC|WHERE|LEFT JOIN|INNER JOIN|RIGHT JOIN|ORDER BY|GROUP BY|IN|LIKE|DISTINCT|DELETE|INSERT|INTO|VALUES)\b/', '<span class="sfWebDebugLogInfo">\\1</span>', $logLine);

    // remove username/password from DSN
    if (strpos($logLine, 'DSN') !== false)
    {
      $logLine = preg_replace("/=&gt;\s+'?[^'\s,]+'?/", "=&gt; '****'", $logLine);
    }

    return $logLine;
  }
}
