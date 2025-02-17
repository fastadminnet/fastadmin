define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/rule/index',
                    add_url: 'user/rule/add',
                    edit_url: 'user/rule/edit',
                    del_url: 'user/rule/del',
                    multi_url: 'user/rule/multi',
                    table: 'user_rule',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                escape: true,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'pid', title: __('Pid'), visible: false},
                        {field: 'title', title: __('Title'), align: 'left', formatter: Controller.api.formatter.title},
                        {field: 'name', title: __('Name'), align: 'left', formatter: Controller.api.formatter.name},
                        {field: 'remark', title: __('Remark')},
                        // {field: 'ismenu', title: __('Ismenu'), formatter: Table.api.formatter.toggle},
                        {field: 'createtime', title: __('Createtime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true, visible: false},
                        {field: 'updatetime', title: __('Updatetime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true, visible: false},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
            },
            bindevent: function () {
                $(document).on('click', "input[name='row[ismenu]']", function () {
                    var name = $("input[name='row[name]']");
                    name.prop("placeholder", $(this).val() == 1 ? name.data("placeholder-menu") : name.data("placeholder-node"));
                });
                $("input[name='row[ismenu]']:checked").trigger("click");
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
