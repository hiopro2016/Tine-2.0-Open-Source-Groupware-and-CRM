/**
 * Tine 2.0
 * 
 * @package     Timetracker
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
 
Ext.namespace('Tine.Timetracker');

/**
 * Timetracker Edit Dialog
 */
Tine.Timetracker.TimesheetEditDialog = Ext.extend(Tine.widgets.dialog.EditDialog, {

    /**
     * @private
     */
    windowNamePrefix: 'TimesheetEditWindow_',
    appName: 'Timetracker',
    recordClass: Tine.Timetracker.Model.Timesheet,
    recordProxy: Tine.Timetracker.timesheetBackend,
    tbarItems: null,
    evalGrants: false,
    useInvoice: false,
    displayNotes: true,
    
    /**
     * overwrite update toolbars function (we don't have record grants yet)
     */
    updateToolbars: function(record) {
        this.onTimeaccountUpdate();
        Tine.Timetracker.TimesheetEditDialog.superclass.updateToolbars.call(this, record, 'timeaccount_id');
    },
    
    /**
     * this gets called when initializing and if a new timeaccount is chosen
     * 
     * @param {} field
     * @param {} timeaccount
     */
    onTimeaccountUpdate: function(field, timeaccount) {
        // check for manage_timeaccounts right
        var manageRight = Tine.Tinebase.common.hasRight('manage', 'Timetracker', 'timeaccounts');
        
        var notBillable = false;
        var notClearable = false;

        var grants = timeaccount ? timeaccount.get('account_grants') : (this.record.get('timeaccount_id') ? this.record.get('timeaccount_id').account_grants : {});
        
        if (grants) {
            var setDisabled = ! (grants.bookAllGrant || grants.adminGrant || manageRight);
            var accountField = this.getForm().findField('account_id');
            accountField.setDisabled(setDisabled);
            // set account id to the current user, if he doesn't have the right to edit other users timesheets
            if (setDisabled) {
                if (this.copyRecord && (this.record.get('account_id') != Tine.Tinebase.registry.get('currentAccount').accountId)) {
                    accountField.setValue(Tine.Tinebase.registry.get('currentAccount'));
                }
            }
            notBillable = ! (grants.manageBillableGrant || grants.adminGrant || manageRight);
            notClearable = ! (grants.adminGrant || manageRight);
            this.getForm().findField('billed_in').setDisabled(! (grants.adminGrant || manageRight));
        }

        if (timeaccount) {
            notBillable = notBillable || timeaccount.data.is_billable == "0" || this.record.get('timeaccount_id').is_billable == "0";
            
            // clearable depends on timeaccount is_billable as well (changed by ps / 2009-09-01, behaviour was inconsistent)
            notClearable = notClearable || timeaccount.data.is_billable == "0" || this.record.get('timeaccount_id').is_billable == "0";
        }
        
        this.getForm().findField('is_billable').setDisabled(notBillable);
        this.getForm().findField('is_cleared').setDisabled(notClearable);
        
        if (this.record.id == 0 && timeaccount) {
            // set is_billable for new records according to the timeaccount setting
            this.getForm().findField('is_billable').setValue(timeaccount.data.is_billable);
        }
    },
    
    /**
     * Always set is_billable if timeaccount is billable. This is needed for copied sheets where the
     * original is set to not billable
     */
    onAfterRecordLoad: function() {
        Tine.Timetracker.TimesheetEditDialog.superclass.onAfterRecordLoad.call(this);
        if (this.record.id == 0 && this.record.get('timeaccount_id') && this.record.get('timeaccount_id').is_billable) {
            this.getForm().findField('is_billable').setValue(this.record.get('timeaccount_id').is_billable);
        }
    },

    /**
     * this gets called when initializing and if cleared checkbox is changed
     *
     * @param {} field
     * @param {} newValue
     *
     * @todo    add prompt later?
     */
    onClearedUpdate: function(field, checked) {
        if (!this.useMultiple) {
            this.getForm().findField('billed_in').setDisabled(! checked);
        }
    },

    initComponent: function() {
        var salesApp = Tine.Tinebase.appMgr.get('Sales');
        this.useInvoice = Tine.Tinebase.appMgr.get('Sales')
            && salesApp.featureEnabled('invoicesModule')
            && Tine.Tinebase.common.hasRight('manage', 'Sales', 'invoices')
            && Tine.Sales.Model.Invoice;
        
        Tine.Timetracker.TimesheetEditDialog.superclass.initComponent.apply(this, arguments);
    },

    /**
     * overwrites the isValid method on multipleEdit
     */
    isMultipleValid: function() {
        var valid = true;
        var keys = ['timeaccount_id', 'description', 'account_id'];
        Ext.each(keys, function(key) {
            var field = this.getForm().findField(key);
            if(field.edited && ! field.validate()) {
                field.markInvalid();
                valid = false;
            }
        }, this);
        return valid;
    },

    /**
     * returns dialog
     * 
     * NOTE: when this method gets called, all initialization is done.
     */
    getFormItems: function() {
        var lastRow = [new Tine.Addressbook.SearchCombo({
            allowBlank: false,
            forceSelection: true,
            columnWidth: 1,
            disabled: true,
            useAccountRecord: true,
            userOnly: true,
            nameField: 'n_fileas',
            fieldLabel: this.app.i18n._('Account'),
            name: 'account_id'
        }), {
            columnWidth: .25,
            disabled: (this.useMultiple) ? false : true,
            boxLabel: this.app.i18n._('Billable'),
            name: 'is_billable',
            xtype: 'checkbox'
        }, {
            columnWidth: .25,
            disabled: (this.useMultiple) ? false : true,
            boxLabel: this.app.i18n._('Cleared'),
            name: 'is_cleared',
            xtype: 'checkbox',
            listeners: {
                scope: this,
                check: this.onClearedUpdate
            }
        }, {
                columnWidth: .5,
                disabled: true,
                fieldLabel: this.app.i18n._('Cleared In'),
                name: 'billed_in'
            }];
        
        if (this.useInvoice) {
            lastRow.push(Tine.widgets.form.RecordPickerManager.get('Sales', 'Invoice', {
                columnWidth: .5,
                disabled: true,
                fieldLabel: this.app.i18n._('Invoice'),
                name: 'invoice_id'
            }));
        }
        
        
        return {
            xtype: 'tabpanel',
            border: false,
            plain:true,
            activeTab: 0,
            plugins: [{
                ptype : 'ux.tabpanelkeyplugin'
            }],
            defaults: {
                hideMode: 'offsets'
            },
            items:[
                {
                title: this.app.i18n.ngettext('Timesheet', 'Timesheets', 1),
                autoScroll: true,
                border: false,
                frame: true,
                layout: 'border',
                items: [{
                    region: 'center',
                    xtype: 'columnform',
                    labelAlign: 'top',
                    formDefaults: {
                        xtype:'textfield',
                        anchor: '100%',
                        labelSeparator: '',
                        columnWidth: .333
                    },
                    items: [[Tine.widgets.form.RecordPickerManager.get('Timetracker', 'Timeaccount', {
                        columnWidth: 1,
                        fieldLabel: this.app.i18n.ngettext('Time Account', 'Time Accounts', 1),
                        emptyText: this.app.i18n._('Select Time Account...'),
                        allowBlank: false,
                        forceSelection: true,
                        name: 'timeaccount_id',
                        lazyInit: false,
                        listeners: {
                            scope: this,
                            render: function(field){
                                if(!this.useMultiple) {
                                    field.focus(false, 250);
                                }
                            },
                            select: this.onTimeaccountUpdate
                        }
                    })], [{
                        fieldLabel: this.app.i18n._('Duration'),
                        name: 'duration',
                        allowBlank: false,
                        xtype: 'tinedurationspinner'
                        }, {
                        fieldLabel: this.app.i18n._('Date'),
                        name: 'start_date',
                        allowBlank: false,
                        xtype: 'datefield'
                        }, {
                        fieldLabel: this.app.i18n._('Start'),
                        emptyText: this.app.i18n._('not set'),
                        name: 'start_time',
                        xtype: 'timefield'
                    }], [{
                        columnWidth: 1,
                        fieldLabel: this.app.i18n._('Description'),
                        emptyText: this.app.i18n._('Enter description...'),
                        name: 'description',
                        allowBlank: false,
                        xtype: 'textarea',
                        height: 150
                    }], lastRow] 
                }, {
                    // activities and tags
                    layout: 'accordion',
                    animate: true,
                    region: 'east',
                    width: 210,
                    split: true,
                    collapsible: true,
                    collapseMode: 'mini',
                    header: false,
                    margins: '0 5 0 5',
                    border: true,
                    items: [
                        new Tine.widgets.tags.TagPanel({
                            app: 'Timetracker',
                            border: false,
                            bodyStyle: 'border:1px solid #B5B8C8;'
                        })
                    ]
                }]
            }, new Tine.widgets.activities.ActivitiesTabPanel({
                app: this.appName,
                record_id: (! this.copyRecord) ? this.record.id : null,
                record_model: this.appName + '_Model_' + this.recordClass.getMeta('modelName')
            })]
        };
    },
    
    /**
     * show error if request fails
     * 
     * @param {} response
     * @param {} request
     * @private
     */
    onRequestFailed: function(response, request) {
        this.saving = false;
        
        if (response.code && response.code == 902) {
            // deadline exception
            Ext.MessageBox.alert(
                this.app.i18n._('Failed'), 
                String.format(this.app.i18n._('Could not save {0}.'), this.i18nRecordName) 
                    + ' ( ' + this.app.i18n._('Booking deadline for this Timeaccount has been exceeded.') /* + ' ' + response.message  */ + ')'
            );
        } else {
            // call default exception handler
            Tine.Tinebase.ExceptionHandler.handleRequestException(response);
        }
        this.loadMask.hide();
    }
});

/**
 * Timetracker Edit Popup
 */
Tine.Timetracker.TimesheetEditDialog.openWindow = function (config) {
    var id = (config.record && config.record.id) ? config.record.id : 0;
    var window = Tine.WindowFactory.getWindow({
        width: 800,
        height: 500,
        name: Tine.Timetracker.TimesheetEditDialog.prototype.windowNamePrefix + id,
        contentPanelConstructor: 'Tine.Timetracker.TimesheetEditDialog',
        contentPanelConstructorConfig: config
    });
    return window;
};
