/**
 * @param string id : id de la div où afficher la table
 * @param string path : cheming pour actualiser la table
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

    // for each option
    for (optionKey in options) {
        switch (optionKey) {
            case "sortColumnClassSortable":
                this.sortColumnClassSortable = options[optionKey];
                break;
            case "sortColumnClassSorted":
                this.sortColumnClassSorted = options[optionKey];
                break;
            case "sortColumnClassSortedReverse":
                this.sortColumnClassSortedReverse = options[optionKey];
                break;
            case "askForReloadDelay":
                this.askForReloadDelay = options[optionKey];
                break;
        }
    }

    this.init = function () {
        var table = this;

        // bouton pour forcer une actualisation
        $("#" + id + "_submit, #" + this.getFormName()).click(function () {
            table.doReload();
        });

        // boutons pour la pagination
        // bouton début
        $("#" + id + "_pagination_start, #" + this.getFormName()).click(function () {
            table.page = 1;
            table.doReload();
        });
        // bouton précedent
        $("#" + id + "_pagination_previous, #" + this.getFormName()).click(function () {
            if (table.page > 1) {
                table.page--;
            }
            table.doReload();
        });
        // bouton suivant
        $("#" + id + "_pagination_next, #" + this.getFormName()).click(function () {
            table.page++;
            table.doReload();
        });
        // bouton fin
        $("#" + id + "_pagination_end, #" + this.getFormName()).click(function () {
            table.page = table.lastPage;
            table.doReload();
        });

        // filtering
        $(".refreshOnChange, #" + this.getFormName()).change(function () {
            table.doReload();
        });
        $(".refreshOnKeyup, #" + this.getFormName()).keyup(function () {
            // delayed reload
            table.askForReload();
        });

        // force reload (on click)
        $(".refreshOnClick, #" + this.getFormName()).click(function () {
            table.doReload();
        });

        // ordering binding
        $(".columnSortable, #" + this.getFormName()).click(function () {
            var a = $(this);
            var sortColumn = a.attr("data-sort-column");
            // if same column, inverse order
            if (sortColumn == table.sortColumn) {
                table.sortReverse = !table.sortReverse;
            } else {
                table.sortColumn = sortColumn;
                table.sortReverse = false;
            }
            table.applyColumnSort();
            table.doReload();
        });

        // selectable rows per page
        $("#" + id + "_rows_per_page").change(function () {
            table.rowsPerPage = this.value;
            table.doReload();
        });

        // load previous filters
        this.loadFromLocalStorage();

        // update sorted columns
        this.applyColumnSort();

        // on actualise maintenant
        this.doReload();
    };

    /**
     * Apply column sorting
     */
    this.applyColumnSort = function () {
        var table = this;
        // update icons sort order
        $(".columnSortableIcon, #" + table.getFormName()).each(function () {
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
            $(":checkbox, #" + this.getFormName()).removeProp("checked");

            this.page = options.page;
            this.rowsPerPage = options.rowsPerPage;
            this.sortColumn = options.sortColumn;
            this.sortReverse = options.sortReverse;
            for (var key in options.filters) {
                $("[name='" + key + "']").val(options.filters[key]);

                // for checkbox (@todo: do this only for checkbox)
                if (options.filters[key] == 1) {
                    $("input[name='" + key + "']").prop("checked", true);
                }
            }
            $("#" + id + "_rows_per_page option[value='" + this.rowsPerPage + "'").prop("selected", true);
        }
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
        var postData = $("form[name=" + id + "_form]").serializeArray();
        postData.push({"name": "page", "value": table.page, });
        postData.push({"name": "rowsPerPage", "value": table.rowsPerPage, });
        postData.push({"name": "sortColumn", "value": table.sortColumn, });
        postData.push({"name": "sortReverse", "value": table.sortReverse ? 1 : 0, });

        // save data to localstorage
        table.saveToLocalStorage();

        // callback
        table.beforeReload();

        // and send the query
        $.post(this.path, postData,
                function (dataRaw) {
                    var data = $.parseJSON(dataRaw);
                    $("#" + id + "_body").html(data.tableBody);
                    //$("#" + id + "_foot").html(data.tableFoot);
                    $("#" + id + "_stats").html(data.tableStats);
                    $("#" + id + "_pagination").html(data.tablePagination);
                    table.totalRows = data.totalRows;
                    table.totalFilteredRows = data.totalFilteredRows;
                    table.page = data.page;
                    table.lastPage = data.lastPage;

                    // rebind click on pagination buttons
                    $(".tablePaginationButton", "#" + id + "_pagination").click(function () {
                        var button = $(this);
                        table.page = button.attr("data-table-page");
                        table.doReload();
                    });

                }
        ).done(function (data) {
            // callback
            table.afterReload();
        });
    };

    /**
     * Callback after reload
     */
    this.afterReload = function () {
        // could be overridden
    }

}
