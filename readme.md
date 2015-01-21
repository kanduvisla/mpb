# Magento PHPUnit Bootstrap

---

## What is it?

The Magento PHPUnit Bootstrap is a way of writing unit tests for your Magento project that are self-contained,
small and clean.
The whole idea is that a test should be able to run on itself and work with it's own predefined testdata. It
provides a bootstrap for a testsuite that is able to create test tables and manipulate configuration settings
prior before the test.

## What is it not?

It's not a replacement or extension on PHPUnit (like EcomDev PHPUnit for example). It only provides a way of
running your native Magento code against testdata.
 
## How does it work?

As you might know, (almost) everything in Magento is configuration-based. This configuration can be change on
the fly. MPB provides a simple way to set your configuration to certain conditions before running a testsuite.
The configuration is only changed during the current execution and not saved so no original data is overwritten.

The same trick can be applied to testtables. As you might know, Magento gets the names of the tables from the
configuration (by using `Mage::getSingleton('core/resource')->getTableName('module/entity_name')`). Since this
is also in the configuration, you can change these values on the fly, effectively make Magento read from other
tables.