    var printIssueManager = {
        tableData: null,
        currentIssue: null,
        dataToSave : {}
    };
    printIssueManager.utils = {
        getNewIssueData: function(issue_number, issue_language, callback){
            flashMessage(trans['processing']);
            $.ajax({
                type: "GET",
                url: Routing.generate('newscoop_printissuemanager_admin_getissuearticles'),
                dataType: "json",
                data: { article_number: issue_number, article_language: issue_language }
            }).done(function(res) {
                printIssueManager.tableData = res.aaData;
                printIssueManager.currentIssue = res.issue;
                var frezze = '';
                if (printIssueManager.currentIssue.freeze == true) {
                    frezze = '<span class="label label-danger">'+trans['frozen']+'</span> ';
                }
                var status = '';
                var labelType = '';
                if (res.issue.status == 'P') {
                    status = trans['published'];
                    labelType = "success";
                } else if (res.issue.status == 'S') {
                    status = trans['submitted'];
                    labelType = "warning";
                }
                $('h4.choosen-issue-name').html(frezze+ '<span class="label label-'+labelType+'">'+status+'</span> <a href="'+res.issue.url+'">'+res.issue.title+'</a> ');
                if (typeof callback === 'undefined') {
                    printIssueManager.utils.upadateTable($("table.issueArticles"), res);
                } else {
                    callback(res);
                }
            });
        },
        upadateTable: function(table, data){
            var issue = $('select.mobile_issues option:selected');
            var loadArticles = $('form.loadArticles');

            issue.data('contextId', data['contextBox']);
            $('.context_box_id', loadArticles).val(data['contextBox']);
            $('.article_number', loadArticles).val(data['issue']['number']);
            $('.article_language', loadArticles).val(data['issue']['language']);

            if (printIssueManager.currentIssue.freeze != true) {
                $("table.sortable tbody").sortable("enable");
            } else {
                $("table.sortable tbody").sortable("disable");
            }

            if (typeof relatedArticlesList != 'undefined') {
                relatedArticlesList.fnClearTable();
                relatedArticlesList.fnAddData(data.aaData);
                
                return true;
            }

            relatedArticlesList = table.dataTable({ 
                'aaData': printIssueManager.tableData,
                'sPaginationType': 'full_numbers',
                'bRetrieve': true,
                'bJQueryUI': false,
                'bAutoWidth': false,
                "bProcessing": true,
                "bFilter": false,
                "bSearchable":false,
                "bInfo":false,
                "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "iDisplayLength": -1,
                fnRowCallback: function( nRow, aData, iDisplayIndex ) {
                    for(i=0; i < aData.length; i++) {
                        if(i == 0) {
                            $('td:eq('+i+')', nRow).html('<button type="button" class="btn btn-danger btn-xs"><a href="'+Routing.generate('newscoop_printissuemanager_admin_removearticle', { id: aData[10] })+'" data-article-id="'+aData[10]+'" data-language-id="'+aData[11]+'" class="remove">'+trans['delete']+'</a></button>');
                        }

                        if(i == 5) {
                            if ($('td:eq(3)', nRow).html() != 'iPad_Ad') {
                                $('td:eq('+i+')', nRow).addClass('js-printsection').data('editable', false);
                            } else {
                                $('td:eq('+i+')', nRow).addClass('js-printsection').html('<i>disabled</i>');
                            }
                        }

                        if(i == 6) {
                            if ($('td:eq(3)', nRow).html() != 'iPad_Ad') {
                                $('td:eq('+i+')', nRow).addClass('js-printstory').data('editable', false);
                            } else {
                                $('td:eq('+i+')', nRow).addClass('js-printstory').html('<i>disabled</i>');
                            }
                        }

                        if(i == 7) {
                            $('td:eq('+i+')', nRow).html('<input type="checkbox" class="js-prominent" name="prominent" value="1" />');
                            if ($('td:eq(3)', nRow).html() != 'iPad_Ad') { 
                                if(aData[i] == true) {
                                    $('td:eq('+i+') input', nRow).attr('checked', true);
                                }
                            } else {
                                $('td:eq('+i+') input', nRow).attr('disabled', true);
                            }
                        }

                        if(i == 8) {
                            $('td:eq('+i+')', nRow).html('<button type="button" class="btn btn-primary btn-xs"><a href="'+aData[i]+'" class="preview">'+trans['preview']+'</a></button>');
                        }

                        if(i == 9) {
                            $('td:eq('+i+')', nRow).html('<button type="button" class="btn btn-success btn-xs"><a href="'+Routing.generate('newscoop_printissuemanager_admin_savearticles')+'" data-content-box-id="'+aData[10]+'" data-language-id="'+aData[11]+'" class="save-one">'+trans['save']+'</a></button>');
                        }
                    }

                    $(nRow).attr('id', aData[10]);
                    $(nRow).data('id', aData[10])
                    $(nRow).data('type', aData[3]);

                    $(nRow).width(table.width());

                    return nRow;
                },
                fnDrawCallback: function(){
                    $('.dataTables_length').hide();
                    $('.dataTables_paginate').parent().hide();
                },
                'aoColumnDefs': [ 
                    { "sClass": "center", "aTargets": [ 0, -1, -2, -3] },
                    { 'bSortable': false, 'aTargets': [ 0, 8, 9 ] }
                ]
            });
        },
        reorderArticles: function(table) {
            var issue = $('select.mobile_issues option:selected');
  
            flashMessage(trans['processing']);
            $.ajax({
                type: "POST",
                url: Routing.generate('newscoop_printissuemanager_admin_updateorder'),
                dataType: "json",
                data: {
                    'context_box_articles': $('tbody', table).sortable('toArray'),
                    'content_box_id': issue.data('contextId'),
                    'article_number': printIssueManager.currentIssue['number'],
                    'article_language': printIssueManager.currentIssue['language']
                }
            }).done(function(res) {
                try {
                printIssueManager.tableData = res.aaData;
                printIssueManager.currentIssue = res.issue;
                printIssueManager.utils.upadateTable($("table.issueArticles"), res);
                } catch (e) {}
                
                flashMessage(trans['saved']);
            });
        },
        loadArticles: function() {
            var issue = $('select.mobile_issues option:selected');
            var loadArticlesForm = $('form.loadArticles');

            flashMessage(trans['processing']);
            $.ajax({
                type: "POST",
                url: loadArticlesForm.attr('action'),
                dataType: "json",
                data: loadArticlesForm.serialize()
            }).done(function(res) {
                try {
                    printIssueManager.tableData = res.aaData;
                    printIssueManager.currentIssue = res.issue;
                    printIssueManager.utils.upadateTable($("table.issueArticles"), res);
                    flashMessage(trans['articlesloaded']);
                } catch (e) {
                    flashMessage(trans['noarticles']);
                }
                
                
            });
        },
        inlineEdit: function(element, addNewOne){
            var tableData = printIssueManager.utils.collectDataFromTable(relatedArticlesList);
            $(element).data('editable', true);

            if ($(element).hasClass('js-printsection')) {
                var printsectionOptions = [];
                var printsectionInput = $('<input id="inline-input" type="text" class="form-control input-sm" name="printsection" placeholder="'+trans['addnewone']+'" value="" />');
                var printsectionSelect = $('<select id="inline-select" class="form-control input-sm" name="printsection"><option value="">'+trans['chooseprintsection']+'</option></select>');
                var addNew = $('<div style="text-align:center"><a class="addNew" href="#">'+trans['addnew']+'</a></a>');
                var backToTheList = $('<div style="text-align:center"><a class="backToTheList" href="#">'+trans['backtolist']+'</a></a>')

                for (var i = 0; i < tableData.length; i++) {
                    if (typeof tableData[i][5] != 'undefined' && tableData[i][5] != null && tableData[i][5].length > 0) {
                        if(printsectionOptions.indexOf(tableData[i][5]) == -1 || printsectionOptions.length == 0) {
                            var selected = "";
                            if (tableData[i][5] == $(element).html()) {
                                selected = 'selected="selected"';
                            }
                            printsectionSelect.append('<option '+selected+' value="'+tableData[i][5]+'">'+tableData[i][5]+'</option>');
                            printsectionOptions.push(tableData[i][5]);
                        }
                    }
                }

                if(printsectionOptions.length == 0 || (typeof addNewOne !== 'undefined' && addNewOne == 'addNewOne')) {
                    $(element).html(printsectionInput).append(backToTheList);
                } else {
                    $(element).html(printsectionSelect).append(addNew);
                }
            } else if($(element).hasClass('js-printstory')) {
                var printstoryOptions = [];
                var printstoryInput = $('<input id="inline-input" class="form-control input-sm" type="text" name="printstory" placeholder="'+trans['addnewone']+'" value="" />');
                var printstorySelect = $('<select id="inline-select" class="form-control input-sm" name="printstory"><option value="">'+trans['chooseprintstory']+'</option></select>');
                var addNew = $('<div style="text-align:center"><a class="addNew" href="#">'+trans['addnew']+'</a></a>');
                var backToTheList = $('<div style="text-align:center"><a class="backToTheList" href="#">'+trans['backtolist']+'</a></a>')

                for (var i = 0; i < tableData.length; i++) {
                    if (typeof tableData[i][6] != 'undefined' && tableData[i][6] != null && tableData[i][6].length > 0) {
                        if(printstoryOptions.indexOf(tableData[i][6]) == -1 || printstoryOptions.length == 0) {
                            var selected = "";
                            if (tableData[i][6] == $(element).html()) {
                                selected = 'selected="selected"';
                            }
                            printstorySelect.append('<option '+selected+' value="'+tableData[i][6]+'">'+tableData[i][6]+'</option>');
                            printstoryOptions.push(tableData[i][6]);
                        }
                    }
                }

                if(printstoryOptions.length == 0 || (typeof addNewOne !== 'undefined' && addNewOne == 'addNewOne')) {
                    $(element).html(printstoryInput).append(backToTheList);
                } else {
                    $(element).html(printstorySelect).append(addNew);
                }
            }
        },
        updateTableData: function(element){
            var tableData = printIssueManager.utils.collectDataFromTable(relatedArticlesList);
            var updateForm = $('#formUpdateContainer form').clone();

            $('.js-id', updateForm).val(element.attr('id'));
            $('.js-language', updateForm).val($('a.save-one', element).data('languageId'));

            if ($('.js-printsection', element).data('editable') == true) {
                if($('.js-printsection select', element).length > 0) {
                    $('.js-printsection', updateForm).val($('.js-printsection select', element).val());
                } else {
                    $('.js-printsection', updateForm).val($('.js-printsection input', element).val());
                }
            }

            if ($('.js-printstory', element).data('editable') == true) {
                if($('.js-printstory select', element).length > 0) {
                    $('.js-printstory', updateForm).val($('.js-printstory select', element).val());
                } else {
                    $('.js-printstory', updateForm).val($('.js-printstory input', element).val());
                }
            } 

            if ($('.js-printsection', element).data('editable') != true) {
                $('.js-printsection', updateForm).val($('.js-printsection', element).html());
            }

            if ($('.js-printstory', element).data('editable') != true) {
                $('.js-printstory', updateForm).val($('.js-printstory', element).html());
            }
            
            $('.js-prominent', updateForm).val($('input.js-prominent:checked', element).val());

            if ($('input.js-prominent:checked', element).length == 0) {
                $('.js-prominent', updateForm).val('');
            }

            var rowData = updateForm.serializeArray();
            printIssueManager.dataToSave[rowData[0].value] = rowData;
        },
        hideInline: function(id) {
            var rowData = printIssueManager.dataToSave[id];
            var row = $("table.issueArticles tbody tr#"+id);
            try {
                for (var i = 0; i < rowData.length; i++) {
                if (rowData[i].name == "printsection") {
                    relatedArticlesList.fnUpdate( rowData[i].value, row[0], 5 );
                }

                if (rowData[i].name == "printstory") {
                    relatedArticlesList.fnUpdate( rowData[i].value, row[0], 6 );
                }

                if (rowData[i].name == "prominent") {
                    relatedArticlesList.fnUpdate( rowData[i].value, row[0], 7 );
                }
                }

                return true;
            } catch (e) {
                  return false;
            }
            
        },
        collectDataFromTable : function(table) {
            return relatedArticlesList.fnGetData();
        },
        updateArticle: function(element, inline) {
            flashMessage(trans['processing']);
            if (typeof inline != 'undefined') {
                var data = {data: printIssueManager.dataToSave};
                var url = element.attr('action');
            } else {
                var data = printIssueManager.dataToSave[element.data('contentBoxId')];
                var url = element.attr('href');
            }

            $.ajax({
                type: "POST",
                url: url,
                dataType: "json",
                data: data,
            }).done(function(res) {
                flashMessage(trans['articlesaved']);
            });
        },
        removeArticle: function(element) {
            flashMessage(trans['processing']);
            var url = element.attr('href');
            var issue = $('select.mobile_issues option:selected');
            var data = {
                'context_box_id' : issue.data('contextId')
            }

            $.ajax({
                type: "POST",
                url: url,
                dataType: "json",
                data: data,
            }).done(function(res) {
                flashMessage(trans['articleremoved']);
                relatedArticlesList.fnDeleteRow(element.parent().parent().parent()[0]);
            });
        }
    };

    $('table.datatable').each(function(){
        var datatable = $(this);
        var search_input = datatable.closest('.dataTables_wrapper').find('div[id$=_filter] input');
        search_input.attr('placeholder', trans['search']);
        search_input.addClass('form-control input-sm');
        var length_sel = datatable.closest('.dataTables_wrapper').find('div[id$=_length] select');
        length_sel.addClass('form-control input-sm');
    });

    $('select.mobile_issues').change(function(){
        var issue = $('select.mobile_issues option:selected');
        printIssueManager.utils.getNewIssueData(
            issue.data('number'), 
            issue.data('language')
        );
        $(".content-box button.iframe").attr('href', issue.data('contentBoxHref'));
    });

    $("table.sortable tbody").sortable({
        'axis': 'y',
        helper: function(e, tr)
        {
            var originals = tr.children();
            var helper = tr.clone();
            helper.children().each(function(index) {
                // Set helper cell sizes to match the original sizes
                $(this).width(originals.eq(index).width());
            });
            return helper;
        },
        update: function() {
            printIssueManager.utils.reorderArticles($("table.issueArticles"));
        }
    });

    $('select.mobile_issues').val($('select.mobile_issues option').first().val()).change();

    $('form.saveArticles').submit(function(e){
        printIssueManager.utils.updateArticle($(this), true);
        var data = printIssueManager.dataToSave;
        for (key in data) {
            printIssueManager.utils.hideInline(key);
            delete printIssueManager.dataToSave[key];
        }
        e.preventDefault();
    });

    $('form.loadArticles').submit(function(e){
        if (printIssueManager.currentIssue.freeze == true) {
            flashMessage(trans['isfrozen'], 'error');
            return false;
        }
        e.preventDefault();
        printIssueManager.utils.loadArticles();
    });

    $("table.issueArticles tr td a.addNew, table.issueArticles tr td a.backToTheList").live('click', function(e){
        if ($(this).hasClass('addNew')) {
            var option = 'addNewOne';
        } else {
            var option = 'backToTheList';
        }

        printIssueManager.utils.inlineEdit($(this).parent().parent(), option);
        e.preventDefault();
    });

    $("table.issueArticles tr td").live('click', function(){
        if ($(this).data('editable') == false) {
            printIssueManager.utils.inlineEdit($(this));
            printIssueManager.utils.updateTableData($(this).parent());
        }
    });

    $('.js-printsection select, .js-printstory select, .js-printsection input, .js-printstory input, input.js-prominent').live('change keydown', function(){
        printIssueManager.utils.updateTableData($(this).parent().parent());
    });

    $("table.issueArticles tr a.save-one").live('click', function(e){
        e.stopPropagation();
        e.preventDefault();

        var result = printIssueManager.utils.hideInline($(this).data('contentBoxId'));
        if (result) {
            printIssueManager.utils.updateArticle($(this));
        } else {
            flashMessage(trans['inlinediterror'], 'error');
        }
    });

    $("table.issueArticles tr a.remove").live('click', function(e){
        e.stopPropagation();
        e.preventDefault();
        printIssueManager.utils.removeArticle($(this));
    });
    

    // fancybox for popups
    $('button.iframe').each(function() {
        if (!$(this).attr('custom')) {
            $(this).fancybox({
                hideOnContentClick: false,
                width: 800,
                height: 500,
                onClosed: function(url, params) {
                    if ($.fancybox.reload) { // reload if set
                        if ($.fancybox.message) { // set message after reload
                            $.cookie('flashMessage', $.fancybox.message);
                        }
                        window.location.reload();
                    } else if ($.fancybox.error) {
                        flashMessage($.fancybox.error, 'error');
                    }
                }
            });
        }
    });

    $(".content-box button.iframe").fancybox({
        'showCloseButton' : false,
        'width': 1150,
        'height'     : 700,
        'scrolling' : 'auto',
        'onClosed'      : function() {
            var issue = $('select.mobile_issues option:selected');
            printIssueManager.utils.getNewIssueData(
                issue.data('number'), 
                issue.data('language')
            );
        }
    });