<?php
/**
 * Magento PHPUnit Bootstrap
 */

/**
 * Class Bootstrap provides default start before all tests
 */
class Bootstrap
{
    /**
     * Constructor
     */
    public function __construct()
    {
        echo "Loading Magento\n";
        
        $this->setDefines();
        
        require_once( MAGENTO_ROOT . '/app/Mage.php' );
        Mage::app();

        // Setup SQL:
        $arr = Mage::getConfig()->getXpath('global/resources/default_setup/connection');
        $config = $arr[0];
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s',
                (String) $config->host,
                (String) $config->dbname
            ),
            (String) $config->username,
            (String) $config->password
        );

        // Load an SQL file containing our test tables / testdata
        if(file_exists(dirname(__FILE__) . '/sql/setup.sql'))
        {
            echo "Resetting test tables.\n";
            $this->importTables(dirname(__FILE__) . '/sql/setup.sql');
        }

        // Define constants:
        define('ATTRIBUTE_CODE_ORGANISATION_ID',
            Mage::getModel('eav/entity_attribute')->loadByCode(1, 'happy_discount_organisation_id')->getId());

        // Insert manually:
        $sql = sprintf('INSERT INTO `test_customer_entity_int` 
            (`entity_type_id`, `attribute_id`, `entity_id`, `value`) VALUES
            (1, %1$d, 1, 0),  -- zero organisation (not set)
            (1, %1$d, 2, 1),  -- Organisation X
            (1, %1$d, 4, 2)  -- Organisation Y
               -- not set
            ;
            ', ATTRIBUTE_CODE_ORGANISATION_ID);
        $this->pdo->query($sql);
        
        // Create test products:
        $this->createTestProducts();
        
        echo "Bootstrap complete!\n\n";
    }

    /**
     * Import tables
     *
     * @param $sql_file
     */
    private function importTables($sql_file)
    {
        $allLines = file($sql_file);
        $this->pdo->query('SET foreign_key_checks = 0');
        preg_match_all("/\nCREATE TABLE(.*?)`(.*?)`/si", "\n" . file_get_contents($sql_file), $target_tables);
        foreach ($target_tables[2] as $table) {
            $this->pdo->query('DROP TABLE IF EXISTS ' . $table);
        }
        $this->pdo->query('SET foreign_key_checks = 1');
        $this->pdo->query("SET NAMES 'utf8'");
        $templine = ''; // Temporary variable, used to store current query
        foreach ($allLines as $line) { // Loop through each line
            if (substr($line, 0, 2) != '--' && $line != '') { // Skip it if it's a comment
                $templine .= $line; // Add this line to the current segment
                if (substr(trim($line), -1, 1) == ';') { // If it has a semicolon at the end, it's the end of the query
                    if(!$this->pdo->query($templine))
                    {
                        print('Error performing query \'' . $templine . "\n");
                        $info = $this->pdo->errorInfo();
                        var_dump($info);
                    }

                    $templine = ''; // Reset temp variable to empty
                }
            }
        }
    }

    /**
     * Set defines 
     */
    private function setDefines()
    {
        
    }
    
    /**
     * Small helper function to create test products.
     * Step 1 is load an empty test database with products (sql/empty_testproducts.sql)
     * Step 2 is to import the products in sql/products.php
     */
    private function createTestProducts()
    {
        // Reset test tables:
        $file = dirname(__FILE__) . '/sql/empty_testproducts.sql';
        if(file_exists($file))
        {
            // Empty test products:
            $this->importTables($file);
            $productsFile = dirname(__FILE__) . '/sql/products.php';
            if(file_exists($productsFile))
            {
                // Import products:
                $sql = $this->pdo->query('SELECT * FROM eav_attribute WHERE entity_type_id = 4;');
                $attributeIds = array();
                foreach($sql->fetchAll(PDO::FETCH_ASSOC) as $row)
                {
                    $attributeIds[$row['attribute_code']] = array(
                        'id'            => $row['attribute_id'],
                        'backend_type'  => $row['backend_type'],
                        'backend_model' => $row['backend_model']
                    );
                }
                include($productsFile);
                if(isset($products))
                {
                    // Import products:
                    foreach($products as $product)
                    {
                        if(isset($product['sku']))
                        {
                            // Only import if a sku is set:
                            // Create product:
                            $sql = sprintf(
                                'INSERT INTO `test_catalog_product_entity` 
(`entity_type_id`, `attribute_set_id`, `type_id`, `sku`, `has_options`, `required_options`, `created_at`, `updated_at`)
VALUES ( %1$d, %2$d, \'%3$s\', \'%4$s\', 0, 0, NOW(), NOW());',
                                (isset($product['entity_type_id']) ? $product['entity_type_id'] : 4), // Product
                                (isset($product['attribute_set_id']) ? $product['attribute_set_id'] : 4), // Default
                                (isset($product['type_id']) ? $product['type_id'] : 'simple'), // Product type
                                $product['sku']
                            );
                            $this->pdo->query($sql);
                            $productId = $this->pdo->lastInsertId();
                            
                            // Iterate through keys
                            foreach($product as $key => $value)
                            {
                                switch($key)
                                {
                                    case 'sku' :
                                        // Do nothing:
                                        break;
                                    default :
                                        if(isset($attributeIds[$key]))
                                        {
                                            if($attributeIds[$key]['backend_model'] == 'eav/entity_attribute_backend_array')
                                            {
                                                // This is a dropdown attribute:
                                                $sql = sprintf('SELECT aov.value_id FROM eav_attribute_option_value aov WHERE aov.option_id IN 
                                                    (SELECT ao.option_id FROM eav_attribute_option ao WHERE ao.attribute_id = %1$d) AND 
                                                    aov.value = \'%2$s\';', $attributeIds[$key]['id'], $value);
                                                $result = $this->pdo->query($sql);
                                                if($result->rowCount() == 1) {
                                                    $result = $result->fetch(PDO::FETCH_ASSOC);
                                                    $value = $result['value_id'];                                                    
                                                }
                                            } 
                                            $sql = sprintf('
                                                INSERT INTO `test_catalog_product_entity_%4$s` 
    ( `entity_type_id`, `attribute_id`, `store_id`, `entity_id`, `value`)
    VALUES ( %5$d, %3$d, 0, %1$d, \'%2$s\');
                                            ', 
                                                $productId, 
                                                $value, 
                                                $attributeIds[$key]['id'],
                                                $attributeIds[$key]['backend_type'],
                                                (isset($product['entity_type_id']) ? $product['entity_type_id'] : 4)
                                            );
                                            $this->pdo->query($sql);                                            
                                        }
                                        break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Autoload:
new Bootstrap();