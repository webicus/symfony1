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
 * Doctrine
 * the base class of Doctrine framework
 *
 * @package     Doctrine
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision$
 */
final class Doctrine {
    /**
     * ATTRIBUTE CONSTANTS
     */

    /**
     * event listener attribute
     */
    const ATTR_LISTENER         = 1;
    /**
     * portability attribute
     */
    const ATTR_PORTABILITY      = 9;
    /**
     * quote identifier attribute
     */
    const ATTR_QUOTE_IDENTIFIER = 3;
    /**
     * field case attribute
     */
    const ATTR_FIELD_CASE       = 4;
    /**
     * index name format attribute
     */
    const ATTR_IDXNAME_FORMAT   = 5;
    /**
     * sequence name format attribute
     */
    const ATTR_SEQNAME_FORMAT   = 6;
    /**
     * sequence column name attribute
     */
    const ATTR_SEQCOL_NAME      = 7;
    /**
     * validation attribute
     */
    const ATTR_VLD              = 12;
    /**
     * collection key attribute
     */
    const ATTR_COLL_KEY         = 15;
    /**
     * query limit
     */
    const ATTR_QUERY_LIMIT      = 17;
    /**
     * automatic length validations attribute
     */
    const ATTR_AUTO_LENGTH_VLD  = 19;
    /**
     * automatic type validations attribute
     */
    const ATTR_AUTO_TYPE_VLD    = 20;
    
    
    /** TODO: REMOVE THE FOLLOWING CONSTANTS AND UPDATE THE DOCS ! */

    /**
     * fetchmode attribute
     */
    const ATTR_FETCHMODE        = 2;
    /**
     * batch size attribute
     */
    const ATTR_BATCH_SIZE       = 8;
    /**
     * locking attribute
     */
    const ATTR_LOCKMODE         = 11;
    /**
     * name prefix attribute
     */
    const ATTR_NAME_PREFIX      = 13;
    /**
     * create tables attribute
     */
    const ATTR_CREATE_TABLES    = 14;
    /**
     * collection limit attribute
     */
    const ATTR_COLL_LIMIT       = 16;
    /**
     * accessor invoking attribute
     */
    const ATTR_ACCESSORS        = 18;
    
    

    /**
     * LIMIT CONSTANTS
     */

    /**
     * constant for row limiting
     */
    const LIMIT_ROWS       = 1;
    /**
     * constant for record limiting
     */
    const LIMIT_RECORDS    = 2;

    /**
     * FETCHMODE CONSTANTS
     */

    /**
     * IMMEDIATE FETCHING
     * mode for immediate fetching
     */
    const FETCH_IMMEDIATE       = 0;
    /**
     * BATCH FETCHING
     * mode for batch fetching
     */
    const FETCH_BATCH           = 1;
    /**
     * LAZY FETCHING
     * mode for lazy fetching
     */
    const FETCH_LAZY            = 2;
    /**
     * LAZY FETCHING
     * mode for offset fetching
     */
    const FETCH_OFFSET          = 3;
    /**
     * LAZY OFFSET FETCHING
     * mode for lazy offset fetching
     */
    const FETCH_LAZY_OFFSET     = 4;

    /**
     * FETCH CONSTANTS
     */


    /**
     * FETCH VALUEHOLDER
     */
    const FETCH_VHOLDER         = 1;
    /**
     * FETCH RECORD
     *
     * Specifies that the fetch method shall return Doctrine_Record 
     * objects as the elements of the result set.
     *
     * This is the default fetchmode.
     */
    const FETCH_RECORD          = 2;
    /**
     * FETCH ARRAY                      
     */

    const FETCH_ARRAY           = 3;


    /**
     * ACCESSOR CONSTANTS
     */
    
    /**
     * constant for get accessors
     */
    const ACCESSOR_GET          = 1;
    /**
     * constant for set accessors
     */
    const ACCESSOR_SET          = 2;
    /**
     * constant for both accessors get and set
     */
    const ACCESSOR_BOTH         = 4;
    
    /**
     * PORTABILITY CONSTANTS
     */


    /**
     * Portability: turn off all portability features.
     * @see Doctrine::ATTR_PORTABILITY
     */
    const PORTABILITY_NONE      = 0;

    /**
     * Portability: convert names of tables and fields to case defined in the
     * "field_case" option when using the query*(), fetch*() methods.
     * @see Doctrine::ATTR_PORTABILITY
     */
    const PORTABILITY_FIX_CASE      = 1;

    /**
     * Portability: right trim the data output by query*() and fetch*().
     * @see Doctrine::ATTR_PORTABILITY
     */
    const PORTABILITY_RTRIM         = 2;

    /**
     * Portability: force reporting the number of rows deleted.
     * @see Doctrine::ATTR_PORTABILITY
     */
    const PORTABILITY_DELETE_COUNT  = 4;
    /**
     * Portability: convert empty values to null strings in data output by
     * query*() and fetch*().
     * @see Doctrine::ATTR_PORTABILITY
     */
    const PORTABILITY_EMPTY_TO_NULL = 8;
    /**
     * Portability: removes database/table qualifiers from associative indexes
     * @see Doctrine::ATTR_PORTABILITY
     */
    const PORTABILITY_FIX_ASSOC_FIELD_NAMES = 16;

    /**
     * Portability: turn on all portability features.
     * @see Doctrine::ATTR_PORTABILITY
     */
    const PORTABILITY_ALL           = 17;

    /**
     * LOCKMODE CONSTANTS
     */

    /**
     * mode for optimistic locking
     */
    const LOCK_OPTIMISTIC       = 0;
    /**
     * mode for pessimistic locking
     */
    const LOCK_PESSIMISTIC      = 1;
    /**
     * constructor
     */
    public function __construct() {
        throw new Doctrine_Exception('Doctrine is static class. No instances can be created.');
    }
    /**
     * @var string $path            doctrine root directory
     */
    private static $path;
    /**
     * getPath
     * returns the doctrine root
     *
     * @return string
     */
    public static function getPath() {
        if(! self::$path)
            self::$path = dirname(__FILE__);

        return self::$path;
    }
    /**
     * loadAll
     * loads all runtime classes
     *
     * @return void
     */
    public static function loadAll() {
        $classes = Doctrine_Compiler::getRuntimeClasses();

        foreach($classes as $class) {
            Doctrine::autoload($class);
        }
    }
    /**
     * import
     * method for importing existing schema to Doctrine_Record classes
     *
     * @param string $directory
     */
    public static function import($directory) {

    }
    /**
     * export
     * method for exporting Doctrine_Record classes to a schema
     *
     * @param string $directory
     */
    public static function export($directory) {
                                              	
    }                                          	
    /**
     * compile
     * method for making a single file of most used doctrine runtime components
     * including the compiled file instead of multiple files (in worst
     * cases dozens of files) can improve performance by an order of magnitude
     *
     * @throws Doctrine_Exception
     * @return void
     */
    public static function compile() {
        Doctrine_Compiler::compile();
    }
    /**
     * simple autoload function
     * returns true if the class was loaded, otherwise false
     *
     * @param string $classname
     * @return boolean
     */
    public static function autoload($classname) {
        if(! self::$path)
            self::$path = dirname(__FILE__);

        if(class_exists($classname))
            return false;

        $class = self::$path.DIRECTORY_SEPARATOR.str_replace("_",DIRECTORY_SEPARATOR,$classname).".php";

        if( ! file_exists($class))
            return false;


        require_once($class);
        return true;
    }
    /**
     * returns table name from class name
     *
     * @param string $classname
     * @return string
     */
    public static function tableize($classname) {
         return strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1', $classname));
    }
    /**
     * returns class name from table name
     *
     * @param string $tablename
     * @return string
     */
    public static function classify($tablename) {
        return preg_replace('~(_?)(_)([\w])~e', '"$1".strtoupper("$3")', ucfirst($tablename));
    }
}
?>
