<?php

class basicSQLTest extends PHPUnit_Extensions_Database_TestCase
{
    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection()
    {
        $pdo = new PDO('mysql::memory:');
        return $this->createDefaultDBConnection($pdo, ':memory:');
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        $dataSet = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataSet->addTable('users', dirname(__FILE__)."/_files/users.csv");
        return $dataSet;
    }


    public function testConstruction()
    {
        $db = new thedarkwinter\basicSQL;
        $this->assertInstanceOf('thedarkwinter\basicSQL', $db);
    }
/*
    public function testCheckConnect() // TODO
    {
        $this->db = new thedarkwinter\basicSQL;
        $this->assertEquals($this->db->Connect(), true);
    }

    public function testAddError() // TODO
    {
        $this->db = new thedarkwinter\basicSQL;
        $this->assertEquals($this->db->addError('connect', '', '123', 'Failed to connect'), true);
    }

    public function testLastError()
    {
        $this->db = new thedarkwinter\basicSQL;
        print_r($this->db->lastError());
        $this->assertEquals($this->db->lastError(), array());
    }
    */
}
