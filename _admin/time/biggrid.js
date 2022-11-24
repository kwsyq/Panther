/* 
    _admin/time/biggrid.js

    JM 2019-02-11:
   This appears to have been Martin's work in progress late December 2018/early January 2019.
   Someone will have to decide whether to continue work from here or to start over.
   Note the related file _admin/time/biggrid2.php. No indication of how we decide what's 
    in which, since this is mainly Javascript as well. SEE THAT FILE FOR FURTHER DISCUSSION
    OF THE STATUS OF THIS WORK.

   >>>00015 Largely undocumented code, needs study and documentation.
   Committed as-is 2019-02-11, some comments added (as well as a lot of semicolons to make JavaScript clearer) 2019-05-28.
   
   >>>00001 As of 2019-05-28, there is some very clearly unfinished work here: e.g. a buttonClick method that does nothing but
   write "wow" to the console, or a clickEdit method that similarly writes "hey". It looks like Martin was still simply trying
   to get the framework hooked up when he let go of this.
*/    
/*
[Begin Martin notes]

files needed.


add files

ajax/rateamount.php
ajax/salaryamount.php
ajax/copayamount.php
time/biggrid_week.php
ajax/dayhoursamount.php
ajax/weekotamount.php

_admin/time/biggrid.php
_admin/time/biggrid2.php
_admin/time/biggrid.js     (dont forget to change links back to biggrid.php)
_admin/time/summary.php
_admin/time/time.php

_admin/employee/employee.php
_admin/employee/menu.php
_admin/employee/index.php

ajax/autocomplete_person.php

inc/classes/User.class.php
inc/classes/Customer.class.php
[end Martin notes]
*/

// Describing demo-grid in _admin/time/biggrid2.php 
Vue.component('demo-grid', {
    template: '#grid-template',
    props: {
        data: Array,
        columns: Array,
        filterKey: String,
        gridtitle: String,
        nextdate: String,
        prevdate: String,
        prevtext: String,
        nexttext: String    
    },
    data: function () {
        var sortOrders = {};
        this.columns.forEach(function (key) {
            sortOrders[key] = 1;
        })
        return {
            sortKey: '',
            sortOrders: sortOrders
        };
    },
    computed: {    
        filteredData: function () {
            var sortKey = this.sortKey;
            var filterKey = this.filterKey && this.filterKey.toLowerCase();
            var order = this.sortOrders[sortKey] || 1;
            var data = this.data;
            if (filterKey) {
                data = data.filter(function (row) {
                    return Object.keys(row).some(function (key) {
                        return String(row[key]).toLowerCase().indexOf(filterKey) > -1;
                    })
                })
            }
            if (sortKey) {
                data = data.slice().sort(function (a, b) {
                    a = a[sortKey];
                    b = b[sortKey];
                    return (a === b ? 0 : a > b ? 1 : -1) * order;
                })
            }
            return data;
        }
    },
    filters: {
        capitalize: function (str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    },    
    events: {
    },    
    methods: {
        // >>>00026 obviously just a placeholder
        clickEdit: function() {        
            alert('hey');
        },
        
        displayThis: function(key) {        
            if ((key != "firstName") && (key != "lastName")){
                return true;
            } else {
                return false;
            }
        },
        
        sortBy: function (key) {
            this.sortKey = key;
            this.sortOrders[key] = this.sortOrders[key] * -1;
        },
        
        // >>>00026 obviously just a placeholder
        buttonClick: function() {
            console.log("wow");
        },
        
        clickBegin: function(begin) {
            var self = this;
        
            // >>>00001: NOTE that uses the new AXIOS version of getemployees, not the usual one in inc/functions.php
            // One clear advantage of that (at least as of 2019-05) is that this will give us employees on the relevant
            // date, rather than the current date when we run this.
            axios.get('../axios/getemployees.php?begin=' + escape(begin)).then(function(response){
                demo.gridData = response.data.employees; 
                demo.gridtitle = response.data.title;  
                demo.nexttext = response.data.nexttext;  
                demo.prevtext = response.data.prevtext; 
                demo.prevdate = response.data.prevdate;  
                demo.nextdate = response.data.nextdate;  
            });        
        }
    }
})

// Martin note: bootstrap the demo
var demo = new Vue({
    el: '#demo',
    data: {
        gridtitle: '',
        prevtext: '',
        prevdate: '',
        nexttext: '',
        nextdate: '',
        
        searchQuery: '',
        gridColumns: ['firstName', 'lastName', 'rateDisp', 'iraDisp', 'iraType'],
        gridData: [
        ]
    },
    
    mounted : function() {    
        var self = this;
        
        axios.get('../axios/getemployees.php?begin=' + escape(begin)).then(function(response){
            self.gridData = response.data.employees; 
            self.gridtitle = response.data.title;  
            self.nexttext = response.data.nexttext;  
            self.prevtext = response.data.prevtext; 
            self.prevdate = response.data.prevdate;  
            self.nextdate = response.data.nextdate;  
        });
        /*
        // BEGIN COMMENTED OUT BY MARTIN BEFORE 2019
        this.gridData = [
        { firstName: 'Chuck Norris', lastName: Infinity },
        { firstName: 'Bruce Lee', lastName: 9000 },
        { firstName: 'Jackie Chan', lastName: 7000 },
        { firstName: 'Jet Li', lastName: 8000 }
        ];
        // END COMMENTED OUT BY MARTIN BEFORE 2019
        */
        
    }
})