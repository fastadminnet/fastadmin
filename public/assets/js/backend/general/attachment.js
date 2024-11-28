define(['jquery', 'bootstrap', 'backend', 'form', 'table'], function ($, undefined, Backend, Form, Table) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'general/attachment/index',
                    add_url: 'general/attachment/add',
                    edit_url: 'general/attachment/edit',
                    del_url: 'general/attachment/del',
                    multi_url: 'general/attachment/multi',
                    table: 'attachment'
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                sortName: 'id',
                columns: [
                    [
                        {field: 'state', checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'category', title: __('Category'), operate: 'in', formatter: Table.api.formatter.label, searchList: Config.categoryList},
                        {field: 'admin_id', title: __('Admin_id'), visible: false, addClass: "selectpage", extend: "data-source='auth/admin/index' data-field='nickname'"},
                        {field: 'user_id', title: __('User_id'), visible: false, addClass: "selectpage", extend: "data-source='user/user/index' data-field='nickname'"},
                        {field: 'preview', title: __('Preview'), formatter: Controller.api.formatter.thumb, operate: false},
                        {field: 'url', title: __('Url'), formatter: Controller.api.formatter.url, visible: false},
                        {field: 'filename', title: __('Filename'), sortable: true, formatter: Controller.api.formatter.filename, operate: 'like'},
                        {
                            field: 'filesize', title: __('Filesize'), operate: 'BETWEEN', sortable: true, formatter: function (value, row, index) {
                                var size = parseFloat(value);
                                var i = Math.floor(Math.log(size) / Math.log(1024));
                                return (size / Math.pow(1024, i)).toFixed(i < 2 ? 0 : 2) * 1 + ' ' + ['B', 'KB', 'MB', 'GB', 'TB'][i];
                            }
                        },
                        {field: 'imagewidth', title: __('Imagewidth'), sortable: true},
                        {field: 'imageheight', title: __('Imageheight'), sortable: true},
                        {field: 'imagetype', title: __('Imagetype'), sortable: true, formatter: Table.api.formatter.search, operate: 'like'},
                        {field: 'storage', title: __('Storage'), formatter: Table.api.formatter.search, operate: 'like'},
                        {field: 'mimetype', title: __('Mimetype'), formatter: Controller.api.formatter.mimetype},
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            formatter: Table.api.formatter.datetime,
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            sortable: true,
                            width: 150
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ],
            });

            // 绑定过滤事件
            $('.filter-type ul li a', table.closest(".panel-intro")).on('click', function (e) {
                $(this).closest("ul").find("li").removeClass("active");
                $(this).closest("li").addClass("active");
                var field = 'mimetype';
                var value = $(this).data("value") || '';
                var object = $("[name='" + field + "']", table.closest(".bootstrap-table").find(".commonsearch-table"));
                if (object.prop('tagName') == "SELECT") {
                    $("option[value='" + value + "']", object).prop("selected", true);
                } else {
                    object.val(value);
                }
                table.trigger("uncheckbox");
                table.bootstrapTable('refresh', {pageNumber: 1});
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 附件归类
            $(document).on('click', '.btn-classify', function () {
                var ids = Table.api.selectedids(table);
                Layer.open({
                    title: __('Classify'),
                    content: Template("typetpl", {}),
                    btn: [__('OK')],
                    yes: function (index, layero) {
                        var category = $("select[name='category']", layero).val();
                        Fast.api.ajax({
                            url: "general/attachment/classify",
                            type: "post",
                            data: {category: category, ids: ids.join(',')},
                        }, function () {
                            table.bootstrapTable('refresh', {});
                            Layer.close(index);
                        });
                    },
                    success: function (layero, index) {
                    }
                });
            });

        },
        select: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'general/attachment/select',
                }
            });
            var urlArr = [];
            var multiple = Backend.api.query('multiple');
            multiple = multiple == 'true' ? true : false;

            var table = $("#table");

            table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function (e, row) {
                if (e.type == 'check' || e.type == 'uncheck') {
                    row = [row];
                } else {
                    urlArr = [];
                }
                $.each(row, function (i, j) {
                    if (e.type.indexOf("uncheck") > -1) {
                        var index = urlArr.indexOf(j.url);
                        if (index > -1) {
                            urlArr.splice(index, 1);
                        }
                    } else {
                        urlArr.indexOf(j.url) == -1 && urlArr.push(j.url);
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                sortName: 'id',
                showToggle: false,
                showExport: false,
                maintainSelected: true,
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {field: 'state', checkbox: multiple, visible: multiple, operate: false},
                        {field: 'id', title: __('Id')},
                        {field: 'category', title: __('Category'), operate: 'in', formatter: Table.api.formatter.label, searchList: Config.categoryList},
                        {field: 'admin_id', title: __('Admin_id'), formatter: Table.api.formatter.search, visible: false},
                        {field: 'user_id', title: __('User_id'), formatter: Table.api.formatter.search, visible: false},
                        {field: 'url', title: __('Preview'), formatter: Controller.api.formatter.thumb, operate: false},
                        {field: 'filename', title: __('Filename'), sortable: true, formatter: Controller.api.formatter.filename, operate: 'like'},
                        {field: 'imagewidth', title: __('Imagewidth'), operate: false, sortable: true},
                        {field: 'imageheight', title: __('Imageheight'), operate: false, sortable: true},
                        {
                            field: 'mimetype', title: __('Mimetype'), sortable: true, operate: 'LIKE %...%',
                            process: function (value, arg) {
                                return value.replace(/\*/g, '%');
                            },
                            formatter: Controller.api.formatter.mimetype
                        },
                        {field: 'createtime', title: __('Createtime'), width: 120, formatter: Table.api.formatter.datetime, datetimeFormat: 'YYYY-MM-DD', operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {
                            field: 'operate', title: __('Operate'), width: 85, events: {
                                'click .btn-chooseone': function (e, value, row, index) {
                                    Fast.api.close($.extend({multiple: multiple}, row));
                                },
                            }, formatter: function () {
                                return '<a href="javascript:;" class="btn btn-danger btn-chooseone btn-xs"><i class="fa fa-check"></i> ' + __('Choose') + '</a>';
                            }
                        }
                    ]
                ]
            });

            // 绑定过滤事件
            $('.filter-type ul li a', table.closest(".panel-intro")).on('click', function (e) {
                $(this).closest("ul").find("li").removeClass("active");
                $(this).closest("li").addClass("active");
                var field = 'mimetype';
                var value = $(this).data("value") || '';
                var object = $("[name='" + field + "']", table.closest(".bootstrap-table").find(".commonsearch-table"));
                if (object.prop('tagName') == "SELECT") {
                    $("option[value='" + value + "']", object).prop("selected", true);
                } else {
                    object.val(value);
                }
                table.trigger("uncheckbox");
                table.bootstrapTable('refresh', {pageNumber: 1});
            });

            // 选中多个
            $(document).on("click", ".btn-choose-multi", function () {
                Fast.api.close({url: urlArr.join(","), multiple: multiple});
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            require(['upload'], function (Upload) {
                $("#toolbar .faupload").data("category", function (file) {
                    var category = $("ul.nav-tabs[data-field='category'] li.active a").data("value");
                    return category;
                });
                Upload.api.upload($("#toolbar .faupload"), function () {
                    $(".btn-refresh").trigger("click");
                });
            });
        },
        add: function () {
            //上传完成后刷新父窗口
            $(".faupload").data("upload-complete", function (files) {
                setTimeout(function () {
                    window.parent.$(".btn-refresh").trigger("click");
                }, 100);
            });
            // 获取上传类别
            $("#faupload-third,#faupload-third-chunking").data("category", function (file) {
                return $("#category-third").val();
            });
            // 获取上传类别
            $("#faupload-local,#faupload-local-chunking").data("category", function (file) {
                return $("#category-local").val();
            });
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {
                thumb: function (value, row, index) {
                    var html = '';
                    if (row.mimetype.indexOf("image") > -1) {
                        html = '<a href="' + row.fullurl + '" target="_blank"><img src="' + row.fullurl + row.thumb_style + '" alt="" style="max-height:60px;max-width:120px"></a>';
                    } else {
                        html = '<a href="' + row.fullurl + '" target="_blank"><img src="' + Fast.api.fixurl("ajax/icon") + "?suffix=" + row.imagetype + '" alt="" style="max-height:90px;max-width:120px"></a>';
                    }
                    return '<div style="width:120px;margin:0 auto;text-align:center;overflow:hidden;white-space: nowrap;text-overflow: ellipsis;">' + html + '</div>';
                },
                url: function (value, row, index) {
                    return '<a href="' + row.fullurl + '" target="_blank" class="label bg-green">' + row.url + '</a>';
                },
                filename: function (value, row, index) {
                    return '<div style="width:150px;margin:0 auto;text-align:center;overflow:hidden;white-space: nowrap;text-overflow: ellipsis;">' + Table.api.formatter.search.call(this, value, row, index) + '</div>';
                },
                mimetype: function (value, row, index) {
                    return '<div style="width:80px;margin:0 auto;text-align:center;overflow:hidden;white-space: nowrap;text-overflow: ellipsis;">' + Table.api.formatter.search.call(this, value, row, index) + '</div>';
                },
            }
        }

    };
    return Controller;
});
