<?php foreach ($table->getIndices() as $index) { ?> 
CREATE <?php if($index->getUnique()) { ?>UNIQUE<?php } ?> INDEX <?php echo $index->getName() ?> ON <?php echo $table->getName() ?> (<?php echo $index->getColumnList()?>);
<?php } ?> 

