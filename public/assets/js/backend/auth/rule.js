define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'template'], function ($, undefined, Backend, Table, Form, Template) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    "index_url": "auth/rule/index",
                    "add_url": "auth/rule/add",
                    "edit_url": "auth/rule/edit",
                    "del_url": "auth/rule/del",
                    "multi_url": "auth/rule/multi",
                    "table": "auth_rule"
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                sortName: '',
                escape: true,
                columns: [
                    [
                        {field: 'state', checkbox: true,},
                        {field: 'id', title: 'ID'},
                        {field: 'title', title: __('Title'), align: 'left', formatter: Controller.api.formatter.title, clickToSelect: !false},
                        {field: 'icon', title: __('Icon'), formatter: Controller.api.formatter.icon},
                        {field: 'name', title: __('Name'), align: 'left', formatter: Controller.api.formatter.name},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status},
                        {
                            field: 'ismenu',
                            title: __('Ismenu'),
                            align: 'center',
                            table: table,
                            formatter: Table.api.formatter.toggle
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
                pagination: false,
                search: false,
                commonSearch: false,
                rowAttributes: function (row, index) {
                    return row.pid == 0 ? {} : {style: "display:none"};
                }
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            var btnSuccessEvent = function (data, ret) {
                if ($(this).hasClass("btn-change")) {
                    var index = $(this).data("index");
                    var row = Table.api.getrowbyindex(table, index);
                    row.ismenu = $("i.fa.text-gray", this).length > 0 ? 1 : 0;
                    table.bootstrapTable("updateRow", {index: index, row: row});
                } else if ($(this).hasClass("btn-delone")) {
                    if ($(this).closest("tr[data-index]").find("a.btn-node-sub.disabled").length > 0) {
                        $(this).closest("tr[data-index]").remove();
                    } else {
                        table.bootstrapTable('refresh');
                    }
                } else if ($(this).hasClass("btn-dragsort")) {
                    table.bootstrapTable('refresh');
                }
                Fast.api.refreshmenu();
                return false;
            };

            //表格内容渲染前
            table.on('pre-body.bs.table', function (e, data) {
                var options = table.bootstrapTable("getOptions");
                options.escape = true;
            });

            //当内容渲染完成后
            table.on('post-body.bs.table', function (e, data) {
                var options = table.bootstrapTable("getOptions");
                options.escape = false;

                //点击切换/排序/删除操作后刷新左侧菜单
                $(".btn-change[data-id],.btn-delone,.btn-dragsort").data("success", btnSuccessEvent);

            });

            table.on('post-body.bs.table', function (e, settings, json, xhr) {
                //显示隐藏子节点
                $(">tbody>tr[data-index] > td", this).on('click', "a.btn-node-sub", function () {
                    var status = $(this).data("shown") ? true : false;
                    $("a[data-pid='" + $(this).data("id") + "']").each(function () {
                        $(this).closest("tr").toggle(!status);
                    });
                    if (status) {
                        $("a[data-pid='" + $(this).data("id") + "']").trigger("collapse");
                    }
                    $(this).data("shown", !status);
                    $("i", this).toggleClass("fa-caret-down").toggleClass("fa-caret-right");
                    return false;
                });
            });

            //隐藏子节点
            $(document).on("collapse", ".btn-node-sub", function () {
                if ($("i", this).length > 0) {
                    $("a[data-pid='" + $(this).data("id") + "']").trigger("collapse");
                }
                $("i", this).removeClass("fa-caret-down").addClass("fa-caret-right");
                $(this).data("shown", false);
                $(this).closest("tr").toggle(false);
            });

            //批量删除后的回调
            $(".toolbar > .btn-del,.toolbar .btn-more~ul>li>a").data("success", function (e) {
                Fast.api.refreshmenu();
            });

            //展开隐藏一级
            $(document.body).on("click", ".btn-toggle", function (e) {
                $("a[data-id][data-pid][data-pid!=0].disabled").closest("tr").hide();
                var that = this;
                var show = $("i", that).hasClass("fa-chevron-down");
                $("i", that).toggleClass("fa-chevron-down", !show).toggleClass("fa-chevron-up", show);
                $("a[data-id][data-pid][data-pid!=0]").not('.disabled').closest("tr").toggle(show);
                $(".btn-node-sub[data-pid=0]").data("shown", show);
            });

            //展开隐藏全部
            $(document.body).on("click", ".btn-toggle-all", function (e) {
                var that = this;
                var show = $("i", that).hasClass("fa-plus");
                $("i", that).toggleClass("fa-plus", !show).toggleClass("fa-minus", show);
                $(".btn-node-sub:not([data-pid=0])").closest("tr").toggle(show);
                $(".btn-node-sub").data("shown", show);
                $(".btn-node-sub > i").toggleClass("fa-caret-down", show).toggleClass("fa-caret-right", !show);
            });
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            formatter: {
                title: function (value, row, index) {
                    value = value.toString().replace(/(&|&amp;)nbsp;/g, '&nbsp;');
                    var caret = row.haschild == 1 || row.ismenu == 1 ? '<i class="fa fa-caret-right"></i>' : '';
                    value = value.indexOf("&nbsp;") > -1 ? value.replace(/(.*)&nbsp;/, "$1" + caret) : caret + value;

                    value = !row.ismenu || row.status == 'hidden' ? "<span class='text-muted'>" + value + "</span>" : value;
                    return '<a href="javascript:;" data-id="' + row.id + '" data-pid="' + row.pid + '" class="'
                        + (row.haschild == 1 || row.ismenu == 1 ? 'text-primary' : 'disabled') + ' btn-node-sub">' + value + '</a>';
                },
                name: function (value, row, index) {
                    return !row.ismenu || row.status == 'hidden' ? "<span class='text-muted'>" + value + "</span>" : value;
                },
                icon: function (value, row, index) {
                    return '<span class="' + (!row.ismenu || row.status == 'hidden' ? 'text-muted' : '') + '"><i class="' + value + '"></i></span>';
                }
            },
            bindevent: function () {
                $(document).on('click', "input[name='row[ismenu]']", function () {
                    var name = $("input[name='row[name]']");
                    var ismenu = $(this).val() == 1;
                    name.prop("placeholder", ismenu ? name.data("placeholder-menu") : name.data("placeholder-node"));
                    $('div[data-type="menu"]').toggleClass("hidden", !ismenu);
                });
                $("input[name='row[ismenu]']:checked").trigger("click");

                var iconlist = [];
                var iconfunc = function () {
                    Layer.open({
                        type: 1,
                        area: ['80%', '80%'], //宽高
                        content: Template('chooseicontpl', {iconlist: iconlist})
                    });
                };
                Form.api.bindevent($("form[role=form]"), function (data) {
                    Fast.api.refreshmenu();
                });
                $(document).on('change keyup', "#icon", function () {
                    $(this).prev().find("i").prop("class", $(this).val());
                });
                $(document).on('click', ".btn-search-icon", function () {
                    if (iconlist.length == 0) {
                        $.get(Config.site.cdnurl + "/assets/libs/font-awesome/css/font-awesome.css", function (ret) {
                            var exp = /fa-(.*):before/ig;
                            var result;
                            while ((result = exp.exec(ret)) != null) {
                                iconlist.push(result[1]);
                            }
                            iconfunc();
                        });
                    } else {
                        iconfunc();
                    }
                });
                $(document).on('click', '#chooseicon ul li', function () {
                    $("input[name='row[icon]']").val('fa fa-' + $(this).data("font")).trigger("change");
                    Layer.closeAll();
                });
                $(document).on('keyup', 'input.js-icon-search', function () {
                    $("#chooseicon ul li").show();
                    if ($(this).val() != '') {
                        $("#chooseicon ul li:not([data-font*='" + $(this).val() + "'])").hide();
                    }
                });
            }
        }
    };
    return Controller;
});
