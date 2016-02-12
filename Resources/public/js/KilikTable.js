/**
 * @param string id : id de la div où afficher la table
 * @param string path : cheming pour actualiser la table
 */
function KilikTable(id, path) {
    this.id = id;
    this.path = path;
    this.rowsPerPage = 10;
    this.page = 1;
    this.totalRows = 0;
    this.totalFilteredRows = 0;

    this.init = function () {
        var table = this;

        // bouton pour forcer une actualisation
        $("#" + id + "_submit").click(function () {
            table.doReload();
        });

        // boutons pour la pagination
        // bouton début
        $("#" + id + "_pagination_start").click(function () {
            table.page = 1;
            table.doReload();
        });
        // bouton précedent
        $("#" + id + "_pagination_previous").click(function () {
            if (table.page > 1) {
                table.page--;
            }
            table.doReload();
        });
        // bouton suivant
        $("#" + id + "_pagination_next").click(function () {
            table.page++;
            table.doReload();
        });
        // bouton fin
        $("#" + id + "_pagination_end").click(function () {
            table.page = table.lastPage;
            table.doReload();
        });

        // filtres
        $(".refreshOnChange").change(function () {
            table.doReload();
        });            
        $(".refreshOnKeyup").keyup(function () {
            table.doReload();
        });            

        // on actualise maintenant
        this.doReload();
    };

    this.doReload = function () {
        var table = this;
        var postData = $("form[name=" + id + "_form]").serializeArray();
        postData.push({"name": "page", "value": table.page, });
        postData.push({"name": "rowsPerPage", "value": table.rowsPerPage, });
        console.log("#" + id + "_form");
        $.post(this.path, postData,
                function (dataRaw) {
                    var data = $.parseJSON(dataRaw);
                    $("#" + id + "_body").html(data.tableBody);
                    $("#" + id + "_foot").html(data.tableFoot);
                    table.totalRows = data.totalRows;
                    table.totalFilteredRows = data.totalFilteredRows;
                    table.page = data.page;
                    table.lastPage = data.lastPage;
                    console.log("ajax load done on " + id);
                }
        ).done(function (data) {
            // todo ...
        });
    };
}
