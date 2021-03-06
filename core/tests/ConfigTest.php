<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
 * 
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0  
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * For more information : contact@centreon.com
 * 
 */


namespace Test\Centreon;

use Centreon\Internal\Config;
use Centreon\Internal\Cache;
use Centreon\Internal\Di;

class ConfigTest extends \PHPUnit_Extensions_Database_TestCase
{
    private $conn = null;
    private $datadir = null;

    public function setUp()
    {
        $this->datadir = CENTREON_PATH . '/core/tests/data';
        new Di();
        parent::setUp();
    }

    public function tearDown()
    {
        Di::reset();
        parent::tearDown();
    }

    public function getConnection()
    {
        if (is_null($this->conn)) {
            $dbconn = new \Centreon\Internal\Db('sqlite::memory:');
            $dbconn->exec(
                "CREATE TABLE IF NOT EXISTS `cfg_options` (
                `group` VARCHAR(255) NOT NULL DEFAULT 'default',
                `key` VARCHAR(255) NULL,
                `value` VARCHAR(255) NULL
                )"
            );
            \Centreon\Internal\Di::getDefault()->setShared('db_centreon', $dbconn);
            $this->conn = $this->createDefaultDBConnection($dbconn, ':memory:');
        }
        return $this->conn;
    }

    public function getDataSet()
    {
        return $this->createFlatXMLDataSet($this->datadir . '/test-config.xml');
    }

    public function testFileGet()
    {
        $filename = $this->datadir . '/test-config.ini';
        $config = new Config($filename);
        $this->assertEquals('user1', $config->get('db_centreon', 'username'));
        $this->assertEquals(null, $config->get('db_centreon', 'novar'));
        $this->assertEquals('default', $config->get('nosection', 'novar', 'default'));
    }

    public function testGetGroup()
    {
        $filename = $this->datadir . '/test-config.ini';
        $config = new Config($filename);
        $values = array(
            'dsn' => 'sqlite::memory:',
            'username' => 'user1'
        );
        $this->assertEquals($values, $config->getGroup('db_centreon'));
        $this->assertEquals(array(), $config->getGroup('no_section'));
    }

    public function testDbGet()
    {
        $filename = $this->datadir . '/test-config.ini';
        $config = new Config($filename);
        Di::getDefault()->setShared('cache', Cache::load($config));
        $config->loadFromDb();
        $this->assertEquals('value1', $config->get('default', 'variable1'));
        $this->assertEquals('value2', $config->get('default', 'variable2'));
    }

    public function testDbSet()
    {
        $filename = $this->datadir . '/test-config.ini';
        $config = new Config($filename);
        Di::getDefault()->setShared('cache', Cache::load($config));
        $config->loadFromDb();
        $config->set('default', 'variable2', 'test');
        $this->assertEquals('test', $config->get('default', 'variable2'));
        $datasetDb = $this->getConnection()->createQueryTable('cfg_options', 'SELECT * FROM cfg_options');
        $datasetTest = $this->createFlatXmlDataSet($this->datadir . '/test-config-set.xml')->getTable('cfg_options');
        $this->assertTablesEqual($datasetTest, $datasetDb);
    }

    public function testConstructFileNotExists()
    {
        $this->setExpectedException('\Centreon\Internal\Exception', "The configuration file is not readable.");
        new Config('nofile.ini');
    }

    public function testConstructFileHasErrors()
    {
        $this->setExpectedException('\Centreon\Internal\Exception', "Error when parsing configuration file.");
        new Config($this->datadir . '/test-config-errors.ini');
    }

    public function testSetExceptionBadGroup()
    {
        $filename = $this->datadir . '/test-config.ini';
        $config = new Config($filename);
        Di::getDefault()->setShared('cache', Cache::load($config));
        $config->loadFromDb();
        $this->setExpectedException('\Centreon\Internal\Exception', "This configuration group is not permit.");
        $config->set('cache', 'test', 'test');
    }

    public function testSetExceptionBadVariable()
    {
        $filename = $this->datadir . '/test-config.ini';
        $config = new Config($filename);
        Di::getDefault()->setShared('cache', Cache::load($config));
        $config->loadFromDb();
        $this->setExpectedException(
            '\Centreon\Internal\Exception',
            "This configuration default - novariable does not exists into database."
        );
        $config->set('default', 'novariable', 'test');
    }
}
