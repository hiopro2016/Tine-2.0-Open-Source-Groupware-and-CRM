/*
 * Tine 2.0
 * 
 * @package     Timetracker
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.namespace('Tine.Timetracker');

/**
 * Timeaccount grid panel
 * 
 * @namespace   Tine.Timetracker
 * @class       Tine.Timetracker.TimeaccountGridPanel
 * @extends     Tine.widgets.grid.GridPanel
 * 
 * <p>Timeaccount Grid Panel</p>
 * <p><pre>
 * TODO         copy action needs to copy the acl too
 * </pre></p>
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @param       {Object} config
 * @constructor
 * Create a new Tine.Timetracker.TimeaccountGridPanel
 */
Tine.Timetracker.TimeaccountGridPanel = Ext.extend(Tine.widgets.grid.GridPanel, {
    // model generics
    recordClass: Tine.Timetracker.Model.Timeaccount,
    
    // grid specific
    defaultSortInfo: {field: 'creation_time', direction: 'DESC'},
    gridConfig: {
        autoExpandColumn: 'title'
    },
    copyEditAction: true,
    defaultFilters: [{
        field: 'query',
        operator: 'contains',
        value: ''
    }, {
        field: 'is_open',
        operator: 'equals',
        value: true
    }],
    
    initComponent: function() {
        this.recordProxy = Tine.Timetracker.timeaccountBackend;
        
        this.actionToolbarItems = this.getToolbarItems();
        this.gridConfig.cm = this.getColumnModel();
        
        Tine.Timetracker.TimeaccountGridPanel.superclass.initComponent.call(this);
        
        this.action_addInNewWindow.setDisabled(! Tine.Tinebase.common.hasRight('manage', 'Timetracker', 'timeaccounts'));
        this.action_editInNewWindow.requiredGrant = 'editGrant';
    },
    
    /**
     * returns cm
     * 
     * @return Ext.grid.ColumnModel
     * @private
     */
    getColumnModel: function(){
        return new Ext.grid.ColumnModel({
            defaults: {
                sortable: true,
                resizable: true
            },
            columns: [
            {   id: 'tags', header: this.app.i18n._('Tags'), width: 50,  dataIndex: 'tags', sortable: false, renderer: Tine.Tinebase.common.tagsRenderer },
            {
                id: 'number',
                header: this.app.i18n._("Number"),
                width: 100,
                dataIndex: 'number'
            },{
                id: 'title',
                header: this.app.i18n._("Title"),
                width: 350,
                dataIndex: 'title'
            },{
                id: 'status',
                header: this.app.i18n._("Billed"),
                width: 150,
                dataIndex: 'status',
                renderer: this.statusRenderer.createDelegate(this)
            },{
                id: 'budget',
                header: this.app.i18n._("Budget"),
                width: 100,
                dataIndex: 'budget'
            }, {
                id: 'billed_in',
                hidden: true,
                header: this.app.i18n._("Cleared in"),
                width: 150,
                dataIndex: 'billed_in'
            }, { 
                id: 'invoice_id',
                header: this.app.i18n._("Invoice"),
                width: 150,
                dataIndex: 'invoice_id',
                hidden: true,
                renderer: function(value, row, record) {
                    if (! value) {
                        return '';
                    }
                    var i = record.get('invoice_id');
                    
                    return (i.number ? i.number + ' - ' : '') + i.description;
                  }
            }, {
                id: 'deadline',
                hidden: true,
                header: this.app.i18n._("Booking deadline"),
                width: 100,
                dataIndex: 'deadline'
            },{
                id: 'cleared_at',
                header: this.app.i18n._("Cleared at"),
                dataIndex: 'cleared_at',
                renderer: Tine.Tinebase.common.dateRenderer
            },{
                id: 'is_open',
                header: this.app.i18n._("Status"),
                width: 150,
                dataIndex: 'is_open',
                renderer: function(value) {
                    if(value) return this.app.i18n._('open');
                    return this.app.i18n._('closed');
                },
                scope: this,
                hidden: true
            }]
        });
    },
    
    /**
     * status column renderer
     * @param {string} value
     * @return {string}
     */
    statusRenderer: function(value) {
        return this.app.i18n._hidden(value);
    },
    
    /**
     * return additional tb items
     */
    getToolbarItems: function(){
        this.exportButton = new Ext.Action({
            text: i18n._('Export'),
            iconCls: 'action_export',
            scope: this,
            requiredGrant: 'readGrant',
            disabled: true,
            allowMultiple: true,
            menu: {
                items: [
                    new Tine.widgets.grid.ExportButton({
                        text: this.app.i18n._('Export as ODS'),
                        format: 'ods',
                        exportFunction: 'Timetracker.exportTimeaccounts',
                        gridPanel: this
                    })
                    /*,
                    new Tine.widgets.grid.ExportButton({
                        text: this.app.i18n._('Export as CSV'),
                        format: 'csv',
                        exportFunction: 'Timetracker.exportTimesheets',
                        gridPanel: this
                    })
                    */
                ]
            }
        });
        
        return [
            Ext.apply(new Ext.Button(this.exportButton), {
                scale: 'medium',
                rowspan: 2,
                iconAlign: 'top'
            })
        ];
    },

    /**
     * add custom items to context menu
     *
     * @return {Array}
     */
    getContextMenuItems: function() {
        var items = [
            '-', {
                text: Tine.Tinebase.appMgr.get('Timetracker').i18n._('Close Timeaccount'),
                iconCls: 'action_edit',
                scope: this,
                disabled: !Tine.Tinebase.common.hasRight('manage', 'Timetracker', 'timeaccounts'),
                itemId: 'closeAccount',
                handler: this.onCloseTimeaccount.createDelegate(this)
            }
        ];

        return items;
    },

    /**
     * Closes selected timeaccount
     */
    onCloseTimeaccount: function () {
        var grid = this,
            recordProxy = this.recordProxy,
            selectionModel = grid.selectionModel;

        Ext.each(selectionModel.getSelections(), function (record) {
            recordProxy.loadRecord(record, {
                success: function (record) {
                    record.set('is_open', false);
                    recordProxy.saveRecord(record, {
                        success: function () {
                            grid.store.reload();
                            grid.store.remove(record);
                        }
                    });
                }
            });
        });
    }
});
