<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Courses
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @version     $Id:JsonTest.php 5576 2008-11-21 17:04:48Z p.schuele@metaways.de $
 * 
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Courses_JsonTest::main');
}

/**
 * Test class for Tinebase_Group
 */
class Courses_JsonTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Courses_Frontend_Json
     */
    protected $_json = array();
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
		$suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Courses Json Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
	}

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $this->_json = new Courses_Frontend_Json();        
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {        
    }
    
    /**
     * try to add a Course
     *
     * @todo add members adn delete check
     */
    public function testAddCourse()
    {
        $course = $this->_getCourseData();
        $courseData = $this->_json->saveCourse(Zend_Json::encode($course));
        
        //print_r($courseData);
        
        // checks
        $this->assertEquals($course['description'], $courseData['description']);
        $this->assertEquals($course['type'], $courseData['type']);
        $this->assertEquals(Tinebase_Core::getUser()->getId(), $courseData['created_by']);
        $this->assertGreaterThan(0, $courseData['group_id']);
        //$this->assertGreaterThan(0, count($courseData['members']));
        
        // cleanup
        $this->_json->deleteCourses($courseData['id']);

        // check if it got deleted
        $this->setExpectedException('Tinebase_Exception_NotFound');
        Courses_Controller_Course::getInstance()->get($courseData['id']);
    }
    
    /**
     * try to get a Course
     *
     */
    public function testGetCourse()
    {
        /*
        $course = $this->_getCourse();
        $courseData = $this->_json->saveCourse(Zend_Json::encode($course->toArray()));
        $courseData = $this->_json->getCourse($courseData['id']);
        
        // checks
        $this->assertEquals($course->description, $courseData['description']);
        $this->assertEquals(Tinebase_Core::getUser()->getId(), $courseData['created_by']);
        $this->assertTrue(is_array($courseData['container_id']));
        $this->assertEquals(Tinebase_Model_Container::TYPE_SHARED, $courseData['container_id']['type']);
                        
        // cleanup
        $this->_json->deleteCourses($courseData['id']);
        */
    }

    /**
     * try to update a Course
     *
     */
    public function testUpdateCourse()
    {
        /*
        $course = $this->_getCourse();
        $courseData = $this->_json->saveCourse(Zend_Json::encode($course->toArray()));
        
        // update Course
        $courseData['description'] = "blubbblubb";
        $courseUpdated = $this->_json->saveCourse(Zend_Json::encode($courseData));
        
        // check
        $this->assertEquals($courseData['id'], $courseUpdated['id']);
        $this->assertEquals($courseData['description'], $courseUpdated['description']);
        $this->assertEquals(Tinebase_Core::getUser()->getId(), $courseUpdated['last_modified_by']);
        
        // cleanup
        $this->_json->deleteCourses($courseData['id']);
        */
    }
    
    /**
     * try to get a Course
     *
     */
    public function testSearchCourses()
    {
        /*
        // create
        $course = $this->_getCourse();
        $courseData = $this->_json->saveCourse(Zend_Json::encode($course->toArray()));
        
        // search & check
        $search = $this->_json->searchCourses(Zend_Json::encode($this->_getCourseFilter()), Zend_Json::encode($this->_getPaging()));
        $this->assertEquals($course->description, $search['results'][0]['description']);
        $this->assertEquals(1, $search['totalcount']);
        
        // cleanup
        $this->_json->deleteCourses($courseData['id']);
        */
    }
       
    /************ protected helper funcs *************/
    
    /**
     * get Course
     *
     * @return array
     * 
     * @todo add members
     */
    protected function _getCourseData()
    {
        return array(
            'name'          => Tinebase_Record_Abstract::generateUID(),
            'description'   => 'blabla',
            'type'          => Tinebase_Record_Abstract::generateUID(),
            'members'       => array()
        );
    }
        
    /**
     * get paging
     *
     * @return array
     */
    protected function _getPaging()
    {
        return array(
            'start' => 0,
            'limit' => 50,
            'sort' => 'creation_time',
            'dir' => 'ASC',
        );
    }

    /**
     * get Course filter
     *
     * @return array
     */
    protected function _getCourseFilter()
    {
        /*
        return array(
            array(
                'field' => 'description', 
                'operator' => 'contains', 
                'value' => 'blabla'
            ),     
            array(
                'field' => 'containerType', 
                'operator' => 'equals', 
                'value' => Tinebase_Model_Container::TYPE_SHARED
            ),     
        );
        */        
    }
}
