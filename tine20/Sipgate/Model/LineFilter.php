<?php
/**
 * Tine 2.0
 *
 * @package     Sipgate
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <alex@stintzing.net>
 * @copyright   Copyright (c) 2010-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Sipgate_Model_LineFilter
 *
 * @package     Sipgate
 * @subpackage  Filter
 */
class Sipgate_Model_LineFilter extends Tinebase_Model_Filter_FilterGroup
{
    /**
     * @var string class name of this filter group
     *      this is needed to overcome the static late binding
     *      limitation in php < 5.3
     */
    protected $_className = 'Sipgate_Model_LineFilter';

    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Sipgate';

    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'Sipgate_Model_Line';

    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'          => array('filter' => 'Tinebase_Model_Filter_Id', 'options' => array('controller' => 'Sipgate_Controller_Line', 'modelName' => 'Sipgate_Model_Line')),
        'query'       => array('filter' => 'Tinebase_Model_Filter_Query', 'options' => array('fields' => array('uri_alias', 'e164_in', 'e164_out'))),
        'user_id'     => array('filter' => 'Tinebase_Model_Filter_User'),
        'sip_uri'     => array('filter' => 'Tinebase_Model_Filter_Text'),
        'entry_id'     => array('filter' => 'Tinebase_Model_Filter_Text'),
        'account_id'  => array('filter' => 'Tinebase_Model_Filter_ForeignId',
            'options' => array(
                'filtergroup'       => 'Sipgate_Model_AccountFilter', 
                'controller'        => 'Sipgate_Controller_Account',
                'modelName'         => 'Sipgate_Model_Account'
           )
        ),
        'tos'         => array('filter' => 'Tinebase_Model_Filter_Text'),
        'last_sync'   => array('filter' => 'Tinebase_Model_Filter_DateTime'),
        'creation_time'        => array('filter' => 'Tinebase_Model_Filter_Date'),
    );
}
