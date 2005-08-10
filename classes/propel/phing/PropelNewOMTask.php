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
 * and is licensed under the LGPL. For more information please see
 * <http://propel.phpdb.org>.
 */
 
require_once 'propel/phing/AbstractPropelDataModelTask.php';
include_once 'propel/engine/builder/om/ClassTools.php';
require_once 'propel/engine/builder/DataModelBuilder.php';

/**
 * This is a temporary task that creates the OM classes based on the XML schema file using NEW builder framework.
 * 
 * @author Hans Lellelid <hans@xmpl.org>
 * @package propel.phing
 */
class PropelNewOMTask extends AbstractPropelDataModelTask {

	/**
	 * The platform (php4, php5, etc.) for which the om is being built.
	 * @var string
	 */
	private $targetPlatform;
	
	/**
	 * Sets the platform (php4, php5, etc.) for which the om is being built.
	 * @param string $v
	 */
	public function setTargetPlatform($v) {
		$this->targetPlatform = $v;
	}
	
	/**
	 * Gets the platform (php4, php5, etc.) for which the om is being built.
	 * @return string
	 */
	public function getTargetPlatform() {
		return $this->targetPlatform;
	}
	
	/**
	 * Utility method to create directory for package if it doesn't already exist.
	 * @param string $path The [relative] package path.
	 * @throws BuildException - if there is an error creating directories
	 */
	protected function ensureDirExists($path)
	{
		$f = new PhingFile($this->getOutputDirectory(), $path);
		if (!$f->exists()) {
			if (!$f->mkdirs()) {
				throw new BuildException("Error creating directories: ". $f->getPath());
			}
		}
	}
	
	public function main()
	{		
		// check to make sure task received all correct params
		$this->validate();		
		
		$basepath = $this->getOutputDirectory();		
		
		// Get new Capsule context
		$generator = $this->createContext();
		$generator->put("basepath", $basepath); // make available to other templates
		
		$targetPlatform = $this->getTargetPlatform(); // convenience for embedding in strings below
				
		// we need some values that were loaded into the template context
		$basePrefix = $generator->get('basePrefix');
		$project = $generator->get('project');
		
		foreach ($this->getDataModels() as $dataModel) {
			$this->log("Processing Datamodel : " . $dataModel->getName());
			
			foreach ($dataModel->getDatabases() as $database) {
				
				$this->log("  - processing database : " . $database->getName());
				$generator->put("platform", $database->getPlatform());
				
							
				foreach ($database->getTables() as $table) {					
				
					if (!$table->isForReferenceOnly()) {
					
						DataModelBuilder::setBuildProperties($this->getPropelProperties());
						
						$this->log("\t+ " . $table->getName());
						
						$targets = array('peer', 'object', 'peerstub', 'objectstub', 'mapbuilder');
						
						// -----------------------------------------------------------------------------------------
						// Create Peer, Object, and MapBuilder classes
						// -----------------------------------------------------------------------------------------
						
						// these files are always created / overwrite any existing files
						foreach(array('peer', 'object', 'mapbuilder') as $target) {
						
							$builder = DataModelBuilder::builderFactory($table, $target);
							$this->ensureDirExists($builder->getPackagePath());
							
							$this->log("\t\t-> " . $builder->getClassname());
							$path = $builder->getClassFilePath();
							
							$script = $builder->build();
							
							$_f = new PhingFile($basepath, $path);
							file_put_contents($_f->getAbsolutePath(), $script);
							
						}
						
						// -----------------------------------------------------------------------------------------
						// Create [empty] stub Peer and Object classes if they don't exist
						// -----------------------------------------------------------------------------------------
						
						// these classes are only generated if they don't already exist
						foreach(array('peerstub', 'objectstub') as $target) {
							
							$builder = DataModelBuilder::builderFactory($table, $target);
							$this->ensureDirExists($builder->getPackagePath());
							
							$_f = new PhingFile($basepath, $path);
							if (!$_f->exists()) {
								$this->log("\t\t-> " . $builder->getClassname());
								$script = $builder->build();							
								$_f = new PhingFile($basepath, $path);
								file_put_contents($_f->getAbsolutePath(), $script);
							} else {
								$this->log("\t\t-> (exists) " . $builder->getClassname());
							}
							
						}
						
						
						// -----------------------------------------------------------------------------------------
						// Create [empty] stub child Object classes if they don't exist
						// -----------------------------------------------------------------------------------------
						
						// If table has enumerated children (uses inheritance) then create the empty child stub classes if they don't already exist.
						if ($table->getChildrenColumn()) {
							$col = $table->getChildrenColumn();
							if ($col->isEnumeratedClasses()) {
								foreach ($col->getChildren() as $child) {
									
									$builder = DataModelBuilder::builderFactory($table, 'objectmultiextend');
									$builder->setChild($child);
									
									// Create the Base Peer class
									$this->log("\t\t-> " . $builder->getClassname());
									$path = $builder->getClassFilePath();
									
									$_f = new PhingFile($basepath, $path);
									if (!$_f->exists()) {
										$this->log("\t\t-> " . $builder->getClassname());
										$script = $builder->build();							
										$_f = new PhingFile($basepath, $path);
										file_put_contents($_f->getAbsolutePath(), $script);
									} else {
										$this->log("\t\t-> (exists) " . $builder->getClassname());
									}
									
								} // foreach
							} // if col->is enumerated
						} // if tbl->getChildrenCol
						
						
						// -----------------------------------------------------------------------------------------
						// Create [empty] Interface if it doesn't exist
						// -----------------------------------------------------------------------------------------
						
						// Create [empty] interface if it does not already exist
						if ($table->getInterface()) {
						
							$builder = DataModelBuilder::builderFactory($table, 'interface');

							$path = $builder->getClassFilePath();
							$this->ensureDirExists(dirname($path));
							
							$_f = new PhingFile($basepath, $path);
							
							if (!$_f->exists()) {
								$this->log("\t\t-> " . $table->getInterface());
								$script = $builder->build();
								file_put_contents($_f->getAbsolutePath(), $script);
							} else {
								$this->log("\t\t-> (exists) " . $table->getInterface());
							}
							
						}
						
						// -----------------------------------------------------------------------------------------
						// Create tree Node classes
						// -----------------------------------------------------------------------------------------
						
						if ($table->isTree()) {
							$this->log("\t\t-> TREE CLASSES NOT YET SUPPORTED BY NEW OM (skipping generation of Node/NodePeer for " . $table->getPhpName() . ")");
						}
						
						/*
						if ($table->isTree()) {
							// Create [empty] stub Node Peer class if it does not already exist
							$path = ClassTools::getFilePath($package, $table->getPhpName() . "NodePeer");
							$_f = new PhingFile($basepath, $path);
							if (!$_f->exists()) {
								$this->log("\t\t-> " . $table->getPhpName() . "NodePeer");
								$generator->parse("om/$targetPlatform/ExtensionNodePeer.tpl", $path);
							} else {
								$this->log("\t\t-> (exists) " . $table->getPhpName() . "NodePeer");
							}

							// Create [empty] stub Node class if it does not already exist
							$path = ClassTools::getFilePath($package, $table->getPhpName() . "Node");
							$_f = new PhingFile($basepath, $path);
							if (!$_f->exists()) {
								$this->log("\t\t-> " . $table->getPhpName() . "Node");
								$generator->parse("om/$targetPlatform/ExtensionNode.tpl", $path);
							} else {
								$this->log("\t\t-> (exists) " . $table->getPhpName() . "Node");
							}
						}
						*/
						

						
						
					} // if !$table->isForReferenceOnly()										
					
				} // foreach table	
		
			} // foreach database
		
		} // foreach dataModel

	
	} // main()
}