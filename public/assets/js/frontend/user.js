define(['jquery', 'bootstrap', 'frontend', 'form', 'template'], function ($, undefined, Frontend, Form, Template) {
    var validatoroptions = {
        invalid: function (form, errors) {
            $.each(errors, function (i, j) {
                Layer.msg(j);
            });
        }
    };
    var AttachmentTable = null;
    var Controller = {
        login: function () {

            //本地验证未通过时提示
            $("#login-form").data("validator-options", validatoroptions);

            //为表单绑定事件
            Form.api.bindevent($("#login-form"), function (data, ret) {
                setTimeout(function () {
                    location.href = ret.url ? ret.url : "/";
                }, 1000);
            });

            //忘记密码
            $(document).on("click", ".btn-forgot", function () {
                var id = "resetpwdtpl";
                var content = Template(id, {});
                Layer.open({
                    type: 1,
                    title: __('Reset password'),
                    area: [$(window).width() < 450 ? ($(window).width() - 10) + "px" : "450px", "355px"],
                    content: content,
                    success: function (layero) {
                        var rule = $("#resetpwd-form input[name='captcha']").data("rule");
                        Form.api.bindevent($("#resetpwd-form", layero), function (data) {
                            Layer.closeAll();
                        });
                        $(layero).on("change", "input[name=type]", function () {
                            var type = $(this).val();
                            $("div.form-group[data-type]").addClass("hide");
                            $("div.form-group[data-type='" + type + "']").removeClass("hide");
                            $('#resetpwd-form').validator("setField", {
                                captcha: rule.replace(/remote\((.*)\)/, "remote(" + $(this).data("check-url") + ", event=resetpwd, " + type + ":#" + type + ")")
                            });
                            $(".btn-captcha").data("url", $(this).data("send-url")).data("type", type);
                        });
                    }
                });
            });
        },
        register: function () {
            //本地验证未通过时提示
            $("#register-form").data("validator-options", validatoroptions);

            //为表单绑定事件
            Form.api.bindevent($("#register-form"), function (data, ret) {
                setTimeout(function () {
                    location.href = ret.url ? ret.url : "/";
                }, 1000);
            }, function (data) {
                $("input[name=captcha]").next(".input-group-btn").find("img").trigger("click");
            });
        },
        changepwd: function () {
            //本地验证未通过时提示
            $("#changepwd-form").data("validator-options", validatoroptions);

            //为表单绑定事件
            Form.api.bindevent($("#changepwd-form"), function (data, ret) {
                setTimeout(function () {
                    location.href = ret.url ? ret.url : "/";
                }, 1000);
            });
        },
        profile: function () {
            // 给上传按钮添加上传成功事件
            $("#faupload-avatar").data("upload-success", function (data) {
                var url = Fast.api.cdnurl(data.url);
                $(".profile-user-img").prop("src", url);
                Toastr.success(__('Uploaded successful'));
            });
            Form.api.bindevent($("#profile-form"));
            $(document).on("click", ".btn-change", function () {
                var that = this;
                var id = $(this).data("type") + "tpl";
                var content = Template(id, {});
                Layer.open({
                    type: 1,
                    title: "修改",
                    area: [$(window).width() < 450 ? ($(window).width() - 10) + "px" : "450px", "355px"],
                    content: content,
                    success: function (layero) {
                        var form = $("form", layero);
                        Form.api.bindevent(form, function (data) {
                            location.reload();
                            Layer.closeAll();
                        });
                    }
                });
            });
        },
        attachment: function () {
            require(['table'], function (Table) {
                AttachmentTable = Table;

                // 初始化表格参数配置
                Table.api.init({
                    extend: {
                        index_url: 'user/attachment',
                    }
                });
                var urlArr = [];
                var multiple = Fast.api.query('multiple');
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
                    fixedColumns: true,
                    fixedRightNumber: 1,
                    columns: [
                        [
                            {field: 'state', checkbox: multiple, visible: multiple, operate: false},
                            {field: 'id', title: __('Id'), operate: false},
                            {
                                field: 'url', title: __('Preview'), formatter: Controller.api.formatter.thumb
                            },
                            {
                                field: 'filename', title: __('Filename'), formatter: Controller.api.formatter.filename, operate: 'like'
                            },
                            {field: 'imagewidth', title: __('Imagewidth'), operate: false, formatter: Controller.api.formatter.text},
                            {field: 'imageheight', title: __('Imageheight'), operate: false, formatter: Controller.api.formatter.text},
                            {field: 'mimetype', title: __('Mimetype'), formatter: Controller.api.formatter.mimetype},
                            {field: 'createtime', title: __('Createtime'), width: 120, formatter: Table.api.formatter.datetime, datetimeFormat: 'YYYY-MM-DD', operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                            {
                                field: 'operate', title: __('Operate'), width: 85, events: {
                                    'click .btn-chooseone': function (e, value, row, index) {
                                        Fast.api.close({url: row.url, multiple: multiple});
                                    },
                                }, formatter: function () {
                                    return '<a href="javascript:;" class="btn btn-danger btn-chooseone btn-xs"><i class="fa fa-check"></i> ' + __('Choose') + '</a>';
                                }
                            }
                        ]
                    ]
                });

                // 选中多个
                $(document).on("click", ".btn-choose-multi", function () {
                    Fast.api.close({url: urlArr.join(","), multiple: multiple});
                });

                // 为表格绑定事件
                Table.api.bindevent(table);
                require(['upload'], function (Upload) {
                    Upload.api.upload($("#toolbar .faupload"), function () {
                        $(".btn-refresh").trigger("click");
                    });
                });

            });
        },
        api: {
            safeurl: function (value) {
                value = value == null ? '' : String(value);
                if (/[\x00-\x1f\x7f]/.test(value)) {
                    return '';
                }
                value = value.trim();
                if (/^[a-z][a-z0-9+.-]*:/i.test(value) && !/^https?:\/\//i.test(value)) {
                    return '';
                }
                return Fast.api.escape(value);
            },
            formatter: {
                text: function (value) {
                    return Fast.api.escape(value == null ? '' : String(value));
                },
                legacytext: function (value) {
                    value = value == null ? '' : String(value);
                    value = value.replace(/&(amp|lt|gt|quot|#0*39|#x0*27);/gi, function (match, entity) {
                        var entities = {
                            amp: '&', lt: '<', gt: '>', quot: '"',
                            '#39': "'", '#x27': "'"
                        };
                        entity = entity.toLowerCase().replace(/^#0+/, '#').replace(/^#x0+/, '#x');
                        return entities[entity] || match;
                    });
                    return Controller.api.formatter.text(value);
                },
                thumb: function (value, row, index) {
                    var html = '';
                    var fullurl = Controller.api.safeurl(row.fullurl);
                    if (String(row.mimetype || '').indexOf("image") > -1) {
                        var thumburl = Controller.api.safeurl((row.fullurl || '') + (row.thumb_style || ''));
                        html = '<a href="' + fullurl + '" target="_blank"><img src="' + thumburl + '" alt="" style="max-height:60px;max-width:120px"></a>';
                    } else {
                        var iconurl = Controller.api.safeurl(Fast.api.fixurl("ajax/icon") + "?suffix=" + encodeURIComponent(row.imagetype || ''));
                        html = '<a href="' + fullurl + '" target="_blank"><img src="' + iconurl + '" alt="" style="max-height:90px;max-width:120px"></a>';
                    }
                    return '<div style="width:120px;margin:0 auto;text-align:center;overflow:hidden;white-space: nowrap;text-overflow: ellipsis;">' + html + '</div>';
                },
                filename: function (value, row, index) {
                    if (row && typeof row.filename !== 'undefined') {
                        value = row.filename;
                    }
                    value = Controller.api.formatter.legacytext(value);
                    return '<div style="width:150px;margin:0 auto;text-align:center;overflow:hidden;white-space: nowrap;text-overflow: ellipsis;">' + AttachmentTable.api.formatter.search.call(this, value, row, index) + '</div>';
                },
                mimetype: function (value, row, index) {
                    if (row && typeof row.mimetype !== 'undefined') {
                        value = row.mimetype;
                    }
                    value = Controller.api.formatter.text(value);
                    return AttachmentTable.api.formatter.search.call(this, value, row, index);
                }
            }
        }
    };
    return Controller;
});
