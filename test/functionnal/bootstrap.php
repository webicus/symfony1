<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('SF_ROOT_DIR',    realpath(dirname(__FILE__).'/fixtures/project'));
define('SF_APP',         $app);
define('SF_ENVIRONMENT', 'test');
define('SF_DEBUG',       true);

$sf_symfony_lib_dir = realpath(dirname(__FILE__).'/../../lib');
$sf_symfony_data_dir = realpath(dirname(__FILE__).'/../../data');

// initialize symfony
require_once(SF_ROOT_DIR.DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.SF_APP.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.php');

// initialize database manager
$databaseManager = new sfDatabaseManager();
$databaseManager->initialize();

// cleanup database
$db = sfConfig::get('sf_data_dir').DIRECTORY_SEPARATOR.'/database.sqlite';
if (file_exists($db))
{
  unlink($db);
}

// initialize database
$sql = file_get_contents(sfConfig::get('sf_data_dir').DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'lib.model.schema.sql');
$sql = preg_replace('/^\s*\-\-.+$/m', '', $sql);
$sql = preg_replace('/^\s*DROP TABLE .+?$/m', '', $sql);
$con = Propel::getConnection();
$con->executeQuery($sql);

// load fixtures
$data = new sfPropelData();
$data->loadDataFromArray($fixtures);
