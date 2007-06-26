<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.com>.
 */

/**
 * Doctrine_Import_Builder
 * Import builder is responsible of building Doctrine ActiveRecord classes
 * based on a database schema.
 *
 * @package     Doctrine
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @since       1.0
 * @version     $Revision$
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Jukka Hassinen <Jukka.Hassinen@BrainAlliance.com>
 */
class Doctrine_Import_Builder
{
    /**
     * @var string $path    the path where imported files are being generated
     */
    private $path = '';

    private $suffix = '.php';

    private static $tpl;

    public function __construct()
    {
        if ( ! isset(self::$tpl)) {
            self::$tpl = file_get_contents(Doctrine::getPath()
                       . DIRECTORY_SEPARATOR . 'Doctrine'
                       . DIRECTORY_SEPARATOR . 'Import'
                       . DIRECTORY_SEPARATOR . 'Builder'
                       . DIRECTORY_SEPARATOR . 'Record.tpl');
        }
    }

    /**
     * setTargetPath
     *
     * @param string path   the path where imported files are being generated
     * @return
     */
    public function setTargetPath($path)
    {
        if ( ! file_exists($path)) {
            mkdir($path, 0777);
        }

        $this->path = $path;
    }
    /**
     * getTargetPath
     *
     * @return string       the path where imported files are being generated
     */
    public function getTargetPath()
    {
        return $this->path;
    }

    public function buildRecord($table, $tableColumns, $className='', $fileName='')
    {
        if (empty($this->path)) {
            throw new Doctrine_Import_Builder_Exception('No build target directory set.');
        }
        if (is_writable($this->path) === false) {
            throw new Doctrine_Import_Builder_Exception('Build target directory ' . $this->path . ' is not writable.');
        }
        $created   = date('l dS \of F Y h:i:s A');
        
        if (empty($className)) {
            $className = Doctrine::classify($table);
        }
        
        if (empty($fileName)) {
            $fileName  = $this->path . DIRECTORY_SEPARATOR . $className . $this->suffix;
        }
        
        $columns   = array();
        $i = 0;

        foreach ($tableColumns as $name => $column) {
            $columns[$i] = '        $this->hasColumn(\'' . $name . '\', \'' . $column['ptype'][0] . '\'';
            if ($column['length']) {
                $columns[$i] .= ', ' . $column['length'];
            } else {
                $columns[$i] .= ', null';
            }

            $a = array();

            if (isset($column['default']) && $column['default']) {
                $a[] = '\'default\' => ' . var_export($column['default'], true);
            }
            if (isset($column['notnull']) && $column['notnull']) {
                $a[] = '\'notnull\' => true';
            }
            if (isset($column['primary']) && $column['primary']) {
                $a[] = '\'primary\' => true';
            }
            if (isset($column['autoinc']) && $column['autoinc']) {
                $a[] = '\'autoincrement\' => true';
            }
            if (isset($column['unique']) && $column['unique']) {
                $a[] = '\'unique\' => true';
            }
            if (isset($column['unsigned']) && $column['unsigned']) {
                $a[] = '\'unsigned\' => true';
            }
            if ($column['ptype'][0] == 'enum' && isset($column['values']) && $column['values']) {
                $a[] = '\'values\' => array(' . implode(',', $column['values']) . ')';
            }

            if ( ! empty($a)) {
                $columns[$i] .= ', ' . 'array(';
                $length = strlen($columns[$i]);
                $columns[$i] .= implode(', 
' . str_repeat(' ', $length), $a) . ')';
            }
            $columns[$i] .= ');';

            if ($i < (count($table) - 1)) {
                $columns[$i] .= '
';
            }
            $i++;
        }

        $content = sprintf(self::$tpl, $created, $className, implode("\n", $columns));

        $bytes   = file_put_contents($fileName, $content);

        if ($bytes === false) {
            throw new Doctrine_Import_Builder_Exception("Couldn't write file " . $fileName);
        }
    }
    /**
     *
     * @param Doctrine_Schema_Object $schema
     * @throws Doctrine_Import_Exception
     * @return void
     */
    public function build(Doctrine_Schema_Object $schema)
    {
        foreach ($schema->getDatabases() as $database){
            foreach ($database->getTables() as $table){
                $this->buildRecord($table);
            }
        }
    }

}
