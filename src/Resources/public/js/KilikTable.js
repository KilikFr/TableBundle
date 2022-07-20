/**
 * @param string id : div id (to diplay table)
 * @param string path : path to refresh table
 * @param array : options
 */
function KilikTable(id, path, options) {
    this.id = id;
    this.path = path;
    this.rowsPerPage = 10;
    this.page = 1;
    this.totalRows = 0;
    this.totalFilteredRows = 0;
    this.sortColumn = "";
    this.sortReverse = false;
    // delay after a key pressed before reload (ms)
    this.askForReloadDelay = 250;
    this.askForReloadTimer = null;

    // apply styles on sorted columns
    this.sortColumnClassSortable = "glyphicon-sort";
    this.sortColumnClassSorted = "glyphicon-sort-by-alphabet";
    this.sortColumnClassSortedReverse = "glyphicon-sort-by-alphabet-alt";

    this.hiddenColumns = [];
    this.defaultHiddenColumns = [];
    this.skipLoadFromLocalStorage = false;
    this.skipLoadFilterFromLocalStorage = false;

    this.xhr = false;

    // small hack to prevent safari bug in private mode
    if (typeof localStorage === 'object') {
        try {
            localStorage.setItem('localStorage', 1);
            localStorage.removeItem('localStorage');
        } catch (e) {
            Storage.prototype._setItem = Storage.prototype.setItem;
            Storage.prototype.setItem = function () {
            };
        }
    }

    this.applyOptions = function (options) {
        var allowedOptions = [
            "sortColumnClassSortable",
            "sortColumnClassSorted",
            "sortColumnClassSortedReverse",
            "askForReloadDelay",
            "rowsPerPage",
            "defaultHiddenColumns",
            "skipLoadFromLocalStorage",
            "skipLoadFilterFromLocalStorage"
        ]
        for (optionKey in options) {
            if(allowedOptions.indexOf(optionKey) != -1) {
                this[optionKey] = options[optionKey];
            }
        }
    }

    this.applyOptions(options);

    this.init = function () {
        var table = this;
        var $table = $("#" + this.id);
        var $buttonCheckAll = $('#kilik_' + table.id + '_mass_check');

        $table.trigger('kilik:init:start', [table]);

        // bouton pour forcer une actualisation
        $table.find("#" + id + "_submit").click(function () {
            table.doReload();
        });

        // boutons pour la pagination
        // bouton début
        $table.find("#" + id + "_pagination_start").click(function () {
            table.page = 1;
            table.doReload();
        });
        // bouton précedent
        $table.find("#" + id + "_pagination_previous").click(function () {
            if (table.page > 1) {
                table.page--;
            }
            table.doReload();
        });
        // bouton suivant
        $table.find("#" + id + "_pagination_next").click(function () {
            table.page++;
            table.doReload();
        });
        // bouton fin
        $table.find("#" + id + "_pagination_end").click(function () {
            table.page = table.lastPage;
            table.doReload();
        });

        var $form = $("form[name='" + this.getFormName() + "']");
        // filtering
        $form.find(".refreshOnChange").change(function () {
            table.askForReload();
        });
        $form.find(".refreshOnKeyup").keyup(function () {
            // delayed reload
            table.askForReload();
        }).keydown(function (e) {
            // prevent reload on press enter (for configuration dropdown)
            if (e.keyCode == 13) {
                return false;
            }
        });

        // force reload (on click)
        $form.find(".refreshOnClick").click(function () {
            table.doReload();
        });

        // ordering binding
        $table.find(".columnSortable").click(function (event) {
            event.preventDefault();
            var a = $(this);
            var sortColumn = a.attr("data-sort-column");
            // if same column, inverse order
            if (sortColumn == table.sortColumn) {
                table.sortReverse = !table.sortReverse;
            } else {
                table.sortColumn = sortColumn;
                table.sortReverse = false;
            }
            $("input[name='" + table.getFormName() + "\[sortColumn\]']").val(table.sortColumn);
            $("input[name='" + table.getFormName() + "\[sortReverse\]']").val(table.sortReverse ? 1 : 0);
            table.applyColumnSort();
            table.doReload();
        });

        // selectable rows per page
        $("#" + id + "_rows_per_page").change(function () {
            table.rowsPerPage = this.value;
            table.doReload();
        });

        // load previous filters

        if (!table.skipLoadFromLocalStorage) {
            this.loadFromLocalStorage();
        }

        // update sorted columns
        this.applyColumnSort();

        // apply hide columns form
        this.applyHideColumnsForm();

        // on actualise maintenant
        this.doReload();

        $buttonCheckAll.on('click', function () {
            table.checkAll($(this).prop('checked'));
        });

        $table.trigger('kilik:init:end', [table]);
    };

    /**
     * Apply column sorting
     */
    this.applyColumnSort = function () {
        var table = this;
        // update icons sort order
        $("#" + this.id).find(".columnSortableIcon").each(function () {
            var pColumn = $(this);
            var pSortColumn = pColumn.parent().attr("data-sort-column");
            pColumn.removeClass(table.sortColumnClassSorted);
            pColumn.removeClass(table.sortColumnClassSortedReverse);
            pColumn.removeClass(table.sortColumnClassSortable);
            // remove sorted, but keep sortable
            if (pSortColumn != table.sortColumn) {
                pColumn.addClass(table.sortColumnClassSortable);
            } else {
                if (table.sortReverse) {
                    pColumn.addClass(table.sortColumnClassSortedReverse);
                } else {
                    pColumn.addClass(table.sortColumnClassSorted);
                }
            }
        });
    }

    /**
     * Pre - check hidden columns form
     */
    this.applyHideColumnsForm = function () {
        var table = this;
        var $table = $("#" + id);
        // check all columns
        $("*[data-setup='" + id + "']:input").prop("checked", true);
        // uncheck hidden columns
        for (key in this.hiddenColumns) {
            var hiddenColumn = this.hiddenColumns[key];
            $("*[data-column='" + hiddenColumn + "'][data-setup='" + id + "']:input").prop("checked", false);

            // hide column name and filter
            $table.find("th[data-column='" + hiddenColumn + "']").hide();
            $table.find("td[data-column='" + hiddenColumn + "']").hide();
        }
        // bind change
        $("*[data-setup='" + id + "']:input").change(function () {
            var input = $(this);
            var checked = input.prop("checked");
            var name = input.attr("data-column");

            // if hidding
            if (checked) {
                table.hiddenColumns.splice($.inArray(name, table.hiddenColumns), 1);
            } else {
                table.hiddenColumns.push(name);
                // when hidding column, disable filters on hidden columns
                $table.find("input[data-column='" + name + "']").val("");
                $table.find("select[data-column='" + name + "'] option").removeAttr("selected");
            }

            // reload (before hide or show columns names)
            table.doReload();

            // if checked, column is not hidden
            if (checked) {
                $table.find("th[data-column='" + name + "']").show();
                $table.find("td[data-column='" + name + "']").show();
            } else {
                $table.find("th[data-column='" + name + "']").hide();
                $table.find("td[data-column='" + name + "']").hide();
            }

        });
    }


    /**
     * Get form name
     *
     * @returns String
     */
    this.getFormName = function () {
        return id + "_form";
    }

    /**
     * Get Local Storage item name
     *
     * @returns String
     */
    this.getLocalStorageName = function () {
        return "kilik_table_" + id;
    }

    /**
     * Save filters and sorts to localStorage
     */
    this.saveToLocalStorage = function () {
        var options = {
            "rowsPerPage": this.rowsPerPage,
            "page": this.page,
            "sortColumn": this.sortColumn,
            "sortReverse": this.sortReverse,
            "filters": $("form[name=" + this.getFormName() + "]").serializeArray().reduce(function (obj, item) {
                obj[item.name] = item.value;
                return obj;
            }, {}),
            "hiddenColumns": this.hiddenColumns,
        };
        localStorage.setItem(this.getLocalStorageName(), JSON.stringify(options));
    }

    /**
     * Load filters and sorts from localStorage
     */
    this.loadFromLocalStorage = function () {
        var options = $.parseJSON(localStorage.getItem(this.getLocalStorageName()));
        if (options) {
            // clear all checkbox
            $("form[name='" + this.getFormName() + "']").find("checkbox").removeProp("checked");
            this.page = options.page;
            this.rowsPerPage = options.rowsPerPage;
            this.sortColumn = options.sortColumn;
            this.sortReverse = options.sortReverse;

            if (!this.skipLoadFilterFromLocalStorage) {
                $("form[name='" + this.getFormName() + "'] [name]").each(function (index, elem) {
                    var name = $(elem).attr("name")
                    if (options.filters[name] !== "") {
                        var value = options.filters[$(elem).attr("name")]
                        if ($(elem).is(":checkbox") || $(elem).is(":radio")) {
                            $("input[name='" + name + "'][value='" + value + "']").prop("checked", true)
                        } else if ($(elem).is("select")) {
                            $("select[name='" + name + "'] option").each(function () {
                                if ($(this).val() == value) {
                                    $(this).prop('selected', true)
                                }
                            }, value)
                        } else {
                            $(elem).val(value)
                        }
                    }
                });
            }

            if (typeof options.hiddenColumns === "undefined") {
                this.hiddenColumns = [];
            } else {
                this.hiddenColumns = options.hiddenColumns;
            }

        } else {
            this.hiddenColumns = this.defaultHiddenColumns;
        }
        $("#" + id + "_rows_per_page option[value='" + this.rowsPerPage + "']").prop("selected", true);
    }

    /**
     * Callback before ask for reload
     */
    this.beforeAskForReload = function () {
        // could be overridden
    }

    /**
     * Ask for reload, until timeout
     */
    this.askForReload = function () {
        var table = this;

        // callback
        table.beforeAskForReload();
        if (this.askForReloadTimer) {
            clearTimeout(table.askForReloadTimer);
        }

        // reload planned
        this.askForReloadTimer = setTimeout(function () {
            table.doReload();
        }, table.askForReloadDelay);

        // callback
        table.afterAskForReload();
    }

    /**
     * Callback after ask for reload
     */
    this.afterAskForReload = function () {
        // could be overridden
    }

    /**
     * Callback before reload
     */
    this.beforeReload = function () {
        // could be overridden
    }

    /**
     * Reload list from server side
     */
    this.doReload = function () {
        var table = this;
        var $loader = $('#'+id+'-table-loading-container');
        $loader.show();
        var postData = $("form[name=" + id + "_form]").serializeArray();
        postData.push({"name": "page", "value": table.page,});
        postData.push({"name": "rowsPerPage", "value": table.rowsPerPage,});
        for (key in table.hiddenColumns) {
            postData.push({"name": "hiddenColumns[" + table.hiddenColumns[key] + "]", "value": 1,});
        }
        //postData.push({"name": "hiddenColumns", "value": table.hiddenColumns.serializeArray(), });

        // save data to localstorage
        table.saveToLocalStorage();

        // callback
        table.beforeReload();

        if (this.xhr) {
            this.xhr.abort();
        }
        // and send the query
        this.xhr = $.post(this.path, postData,
            function (dataRaw) {
                var data = $.parseJSON(dataRaw);
                $("#" + id + "_body").html(data.tableBody);
                $("#" + id + "_foot").html(data.tableFoot);
                $("#" + id + "_stats").html(data.tableStats);
                $("#" + id + "_pagination").html(data.tablePagination);
                table.totalRows = data.totalRows;
                table.totalFilteredRows = data.filteredRows;
                table.page = data.page;
                table.lastPage = data.lastPage;

                // rebind click on pagination buttons
                $("#" + id + "_pagination .tablePaginationButton").click(function (event) {
                    event.preventDefault();
                    var button = $(this);
                    table.page = button.attr("data-table-page");
                    table.doReload();
                });
                $("form[name='" + table.getFormName() + "'] [name]").each(function (index, elem) {
                    if (!$(elem).is(":checkbox") && !$(elem).is(":radio")) {
                        $(elem).toggleClass('table-filter-filled', $(elem).val() !== '');
                    }
                });
            }
        ).done(function (dataRaw) {
            table.initMassActions();
            // callback
            table.afterReload(dataRaw);
            $loader.hide();
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $loader.hide();
        });
    };

    /**
     * Callback after reload
     *
     * @param dataRaw : request raw data
     */
    this.afterReload = function (dataRaw) {
        // could be overridden
    }

    /**
     * Initialize event for mass action
     */
    this.initMassActions = function () {
        var table = this;
        var $table = $('#' + table.id);
        var form = $('form[name=' + table.id + '_form]');
        var massActions = $('[data-mass-action]', $table);

        massActions.each(function () {
            var checkedRows = [], eventDetails, massActionName, action;

            massActionName = $(this).data('name');
            action = $(this).data('mass-action');

            $(this).on('click', function () {
                $('[name="kilik_' + table.id + '_selected[]"]').each(function () {
                    if ($(this).is(":checked")) {
                        checkedRows.push($(this).val());
                    }
                });
                if (action !== '') {
                    form.attr("action", action);
                    form.submit();
                } else {
                    eventDetails = {'checked': checkedRows, 'action': massActionName};
                    $table.trigger('kilik:massAction', [eventDetails]);
                }
            });
        });

        $('#kilik_' + table.id + '_mass_check').prop('checked', false);
    };

    /**
     * Check all items
     */
    this.checkAll = function (status) {
        var tableId = this.id;
        if (status === undefined) {
            status = true;
        }
        $('[name="kilik_' + tableId + '_selected[]"]').each(function () {
            $(this).prop('checked', status);
        });
    }
}

/**
 * KilikTable (Based on Bootstrap 4 and FontAwesome)
 *
 * @param string id : div id (to diplay table)
 * @param string path : path to refresh table
 * @param array : options
 */

function KilikTableFA(id, path, options) {
    KilikTable.call(this, id, path, options)

    this.sortColumnClassSortable = "fa-sort";
    this.sortColumnClassSorted = "fa-sort-up";
    this.sortColumnClassSortedReverse = "fa-sort-down";

    this.applyOptions(options);
}
