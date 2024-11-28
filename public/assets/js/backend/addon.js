define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'template', 'cookie'], function ($, undefined, Backend, Table, Form, Template, undefined) {
    $.cookie.prototype.defaults = {path: Config.moduleurl};

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: Config.api_url ? Config.api_url + '/addon/index' : "addon/downloaded",
                    add_url: '',
                    edit_url: '',
                    del_url: '',
                    multi_url: ''
                }
            });

            var table = $("#table");

            // 弹窗自适应宽高
            var area = Fast.config.openArea != undefined ? Fast.config.openArea : [$(window).width() > 800 ? '800px' : '95%', $(window).height() > 600 ? '600px' : '95%'];

            var switch_local = function () {
                if ($(".btn-switch.active").data("type") != "local") {
                    Layer.confirm(__('Store not available tips'), {
                        title: __('Warmtips'),
                        btn: [__('Switch to the local'), __('Try to reload')]
                    }, function (index) {
                        layer.close(index);
                        $(".panel .nav-tabs").hide();
                        $(".toolbar > *:not(:first)").hide();
                        $(".btn-switch[data-type='local']").trigger("click");
                    }, function (index) {
                        layer.close(index);
                        table.bootstrapTable('refresh');
                    });
                    return false;
                }
            };
            table.on('load-success.bs.table', function (e, json) {
                if (json && typeof json.category != 'undefined' && $(".nav-category li").length == 2) {
                    $.each(json.category, function (i, j) {
                        $("<li><a href='javascript:;' data-id='" + j.id + "'>" + j.name + "</a></li>").insertBefore($(".nav-category li:last"));
                    });
                }
                if (typeof json.rows === 'undefined' && typeof json.code != 'undefined') {
                    switch_local();
                }
            });
            table.on('load-error.bs.table', function (e, status, res) {
                console.log(e, status, res);
                switch_local();
            });
            table.on('post-body.bs.table', function (e, settings, json, xhr) {
                var parenttable = table.closest('.bootstrap-table');
                var d = $(".fixed-table-toolbar", parenttable).find(".search input");
                d.off("keyup drop blur");
                d.on("keyup", function (e) {
                    if (e.keyCode == 13) {
                        var that = this;
                        var options = table.bootstrapTable('getOptions');
                        var queryParams = options.queryParams;
                        options.pageNumber = 1;
                        options.queryParams = function (params) {
                            var params = queryParams(params);
                            params.search = $(that).val();
                            return params;
                        };
                        table.bootstrapTable('refresh', {});
                    }
                });
            });

            Template.helper("Moment", Moment);
            Template.helper("addons", Config['addons']);

            $("#faupload-addon").data("params", function (files, xhr) {
                var userinfo = Controller.api.userinfo.get();
                return {
                    uid: userinfo ? userinfo.id : '',
                    token: userinfo ? userinfo.token : '',
                    version: Config.faversion,
                    force: (files[0].force || false) ? 1 : 0
                };
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pageSize: 50,
                queryParams: function (params) {
                    var userinfo = Controller.api.userinfo.get();
                    $.extend(params, {
                        uid: userinfo ? userinfo.id : '',
                        token: userinfo ? userinfo.token : '',
                        domain: Config.domain,
                        version: Config.faversion,
                        sid: Controller.api.sid()
                    });
                    return params;
                },
                columns: [
                    [
                        {field: 'id', title: 'ID', operate: false, visible: false},
                        {
                            field: 'home',
                            title: __('Index'),
                            width: '50px',
                            formatter: Controller.api.formatter.home
                        },
                        {field: 'name', title: __('Name'), operate: false, visible: false, width: '120px'},
                        {
                            field: 'title',
                            title: __('Title'),
                            operate: 'LIKE',
                            align: 'left',
                            formatter: Controller.api.formatter.title
                        },
                        {
                            field: 'intro',
                            title: __('Intro'),
                            operate: 'LIKE',
                            align: 'left',
                            class: 'visible-lg',
                            formatter: Controller.api.formatter.intro
                        },
                        {
                            field: 'author',
                            title: __('Author'),
                            operate: 'LIKE',
                            width: '100px',
                            formatter: Controller.api.formatter.author
                        },
                        {
                            field: 'price',
                            title: __('Price'),
                            operate: 'LIKE',
                            width: '100px',
                            align: 'center',
                            formatter: Controller.api.formatter.price
                        },
                        {
                            field: 'downloads',
                            title: __('Downloads'),
                            operate: 'LIKE',
                            width: '80px',
                            align: 'center',
                            formatter: Controller.api.formatter.downloads
                        },
                        {
                            field: 'version',
                            title: __('Version'),
                            operate: 'LIKE',
                            width: '80px',
                            align: 'center',
                            formatter: Controller.api.formatter.version
                        },
                        {
                            field: 'toggle',
                            title: __('Status'),
                            width: '80px',
                            formatter: Controller.api.formatter.toggle
                        },
                        {
                            field: 'id',
                            title: __('Operate'),
                            table: table,
                            formatter: Controller.api.formatter.operate,
                            align: 'right',
                            cellStyle: function (value, row, index) {
                                return {css: {'min-width': '158px'}};
                            }
                        },
                    ]
                ],
                responseHandler: function (res) {
                    $.each(res.rows, function (i, j) {
                        j.addon = typeof Config.addons[j.name] != 'undefined' ? Config.addons[j.name] : null;
                    });
                    return res;
                },
                dataType: 'jsonp',
                templateView: false,
                clickToSelect: false,
                search: true,
                showColumns: false,
                showToggle: false,
                showExport: false,
                showSearch: false,
                commonSearch: true,
                searchFormVisible: true,
                searchFormTemplate: 'searchformtpl',
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 离线安装
            require(['upload'], function (Upload) {
                Upload.api.upload("#faupload-addon", function (data, ret, up, file) {
                    Config['addons'][data.addon.name] = data.addon;
                    var addon = data.addon;
                    var testdata = data.addon.testdata;
                    operate(data.addon.name, 'enable', false, function (data, ret) {
                        Layer.alert(__('Offline installed tips') + (testdata ? __('Testdata tips') : ""), {
                            btn: testdata ? [__('Import testdata'), __('Skip testdata')] : [__('OK')],
                            title: __('Warning'),
                            yes: function (index) {
                                if (testdata) {
                                    Fast.api.ajax({
                                        url: 'addon/testdata',
                                        data: {
                                            name: addon.name,
                                            version: addon.version,
                                            faversion: Config.faversion
                                        }
                                    }, function (data, ret) {
                                        Layer.close(index);
                                    });
                                } else {
                                    Layer.close(index);
                                }
                            },
                            icon: 1
                        });
                    });
                    return false;
                }, function (data, ret, up, file) {
                    if (ret.msg && ret.msg.match(/(login|登录)/g)) {
                        return Layer.alert(ret.msg, {
                            title: __('Warning'),
                            btn: [__('Login now')],
                            yes: function (index, layero) {
                                $(".btn-userinfo").trigger("click");
                            }
                        });
                    } else if (ret.code === -1) {
                        Layer.confirm(__('Upgrade tips', data.title), {title: __('Warmtips')}, function (index, layero) {
                            up.removeFile(file);
                            file.force = true;
                            up.uploadFile(file);
                            Layer.close(index);
                        });
                        return false;
                    } else if (ret && ret.code === -3) {
                        //插件目录发现影响全局的文件
                        Layer.open({
                            content: Template("conflicttpl", ret.data),
                            shade: 0.8,
                            area: area,
                            title: __('Warning'),
                            btn: [__('Continue install'), __('Cancel')],
                            end: function () {

                            },
                            yes: function (index) {
                                up.removeFile(file);
                                file.force = true;
                                up.uploadFile(file);
                                Layer.close(index);
                            }
                        });

                    } else {
                        Layer.alert(ret.msg, {title: __('Warning'), icon: 0});
                    }
                });

                // 检测是否登录
                $(document).on("mousedown", "#faupload-addon", function (e) {
                    var userinfo = Controller.api.userinfo.get();
                    var uid = userinfo ? userinfo.id : 0;
                    var uploadBtn = Upload.list['faupload-addon'];

                    if (parseInt(uid) === 0) {
                        uploadBtn.disable();
                        $(".btn-userinfo").trigger("click");
                        return false;
                    } else {
                        if (uploadBtn.disabled) {
                            uploadBtn.enable();
                        }
                    }
                });
            });

            // 查看插件首页
            $(document).on("click", ".btn-addonindex", function () {
                if ($(this).attr("href") == 'javascript:;') {
                    Layer.msg(__('Not installed tips'), {icon: 7});
                } else if ($(this).closest(".operate").find("a.btn-enable").length > 0) {
                    Layer.msg(__('Not enabled tips'), {icon: 7});
                    return false;
                }
            });

            // 切换
            $(document).on("click", ".btn-switch", function () {
                $(".btn-switch").removeClass("active");
                $(this).addClass("active");
                $("form.form-commonsearch input[name='type']").val($(this).data("type"));
                var method = $(this).data("type") == 'local' ? 'hideColumn' : 'showColumn';
                table.bootstrapTable(method, 'price');
                table.bootstrapTable(method, 'downloads');
                table.bootstrapTable('refresh', {url: ($(this).data("url") ? $(this).data("url") : $.fn.bootstrapTable.defaults.extend.index_url), pageNumber: 1});
                return false;
            });

            // 切换分类
            $(document).on("click", ".nav-category li a", function () {
                $(".nav-category li").removeClass("active");
                $(this).parent().addClass("active");
                $("form.form-commonsearch input[name='category_id']").val($(this).data("id"));
                table.bootstrapTable('refresh', {url: $(this).data("url"), pageNumber: 1});
                return false;
            });
            var tables = [];
            $(document).on("click", "#droptables", function () {
                if ($(this).prop("checked")) {
                    Fast.api.ajax({
                        url: "addon/get_table_list",
                        async: false,
                        data: {name: $(this).data("name")}
                    }, function (data) {
                        tables = data.tables;
                        return false;
                    });
                    var html;
                    html = tables.length > 0 ? '<div class="alert alert-warning-light droptablestips" style="max-width:480px;max-height:300px;overflow-y: auto;">' + __('The following data tables will be deleted') + '：<br>' + tables.join("<br>") + '</div>'
                        : '<div class="alert alert-warning-light droptablestips">' + __('The Addon did not create a data table') + '</div>';
                    $(html).insertAfter($(this).closest("p"));
                } else {
                    $(".droptablestips").remove();
                }
                $(window).resize();
            });

            // 会员信息
            $(document).on("click", ".btn-userinfo", function (e, name, version) {
                var that = this;
                var area = [$(window).width() > 800 ? '500px' : '95%', $(window).height() > 600 ? '400px' : '95%'];
                var userinfo = Controller.api.userinfo.get();
                if (!userinfo) {
                    Fast.api.ajax({
                        url: Config.api_url + '/user/logintpl',
                        type: 'post',
                        loading: false,
                        data: {
                            version: Config.faversion,
                            sid: Controller.api.sid()
                        }
                    }, function (tpldata, ret) {
                        Layer.open({
                            content: Template.render(tpldata, {}),
                            zIndex: 99,
                            area: area,
                            title: __('Login'),
                            resize: false,
                            btn: [__('Login')],
                            yes: function (index, layero) {
                                var data = $("form", layero).serializeArray();
                                data.push({name: "faversion", value: Config.faversion});
                                data.push({name: "sid", value: Controller.api.sid()});
                                Fast.api.ajax({
                                    url: Config.api_url + '/user/login',
                                    type: 'post',
                                    data: data
                                }, function (data, ret) {
                                    Controller.api.userinfo.set(data);
                                    Layer.closeAll();
                                    Layer.alert(ret.msg, {title: __('Warning'), icon: 1});
                                    return false;
                                }, function (data, ret) {
                                });
                            },
                            success: function (layero, index) {
                                this.checkEnterKey = function (event) {
                                    if (event.keyCode === 13) {
                                        $(".layui-layer-btn0").trigger("click");
                                        return false;
                                    }
                                };
                                $(document).on('keydown', this.checkEnterKey);
                            },
                            end: function () {
                                $(document).off('keydown', this.checkEnterKey);
                            }
                        });
                        return false;
                    });
                } else {
                    Fast.api.ajax({
                        url: Config.api_url + '/user/userinfotpl',
                        type: 'post',
                        data: {
                            uid: userinfo.id,
                            token: userinfo.token,
                            version: Config.faversion,
                            sid: Controller.api.sid()
                        }
                    }, function (tpldata, ret) {
                        Layer.open({
                            content: Template.render(tpldata, userinfo),
                            area: area,
                            title: __('Userinfo'),
                            resize: false,
                            btn: [__('Logout'), __('Close')],
                            yes: function () {
                                Fast.api.ajax({
                                    url: Config.api_url + '/user/logout',
                                    data: {
                                        uid: userinfo.id,
                                        token: userinfo.token,
                                        version: Config.faversion,
                                        sid: Controller.api.sid()
                                    }
                                }, function (data, ret) {
                                    Controller.api.userinfo.set(null);
                                    Layer.closeAll();
                                    Layer.alert(ret.msg, {title: __('Warning'), icon: 0});
                                }, function (data, ret) {
                                    Controller.api.userinfo.set(null);
                                    Layer.closeAll();
                                    Layer.alert(ret.msg, {title: __('Warning'), icon: 0});
                                });
                            }
                        });
                        return false;
                    }, function (data) {
                        Controller.api.userinfo.set(null);
                        $(that).trigger('click');
                        return false;
                    });
                }
            });

            //刷新授权
            $(document).on("click", ".btn-authorization", function () {
                var userinfo = Controller.api.userinfo.get();
                if (!userinfo) {
                    $(".btn-userinfo").trigger("click");
                    return false;
                }
                Layer.confirm(__('Are you sure you want to refresh authorization?'), {icon: 3, title: __('Warmtips')}, function () {
                    Fast.api.ajax({
                        url: 'addon/authorization',
                        data: {
                            uid: userinfo.id,
                            token: userinfo.token
                        }
                    }, function (data, ret) {
                        $(".btn-refresh").trigger("click");
                        Layer.closeAll();
                    });
                });
                return false;
            });

            var install = function (name, version, force) {
                var userinfo = Controller.api.userinfo.get();
                var uid = userinfo ? userinfo.id : 0;
                var token = userinfo ? userinfo.token : '';
                Fast.api.ajax({
                    url: 'addon/install',
                    data: {
                        name: name,
                        force: force ? 1 : 0,
                        uid: uid,
                        token: token,
                        version: version,
                        faversion: Config.faversion
                    }
                }, function (data, ret) {
                    Layer.closeAll();
                    Config['addons'][data.addon.name] = ret.data.addon;
                    operate(data.addon.name, 'enable', false, function () {
                        Layer.alert(__('Online installed tips') + (data.addon.testdata ? __('Testdata tips') : ""), {
                            btn: data.addon.testdata ? [__('Import testdata'), __('Skip testdata')] : [__('OK')],
                            title: __('Warning'),
                            yes: function (index) {
                                if (data.addon.testdata) {
                                    Fast.api.ajax({
                                        url: 'addon/testdata',
                                        data: {
                                            name: name,
                                            uid: uid,
                                            token: token,
                                            version: version,
                                            faversion: Config.faversion
                                        }
                                    }, function (data, ret) {
                                        Layer.close(index);
                                    });
                                } else {
                                    Layer.close(index);
                                }
                            },
                            icon: 1
                        });
                        Controller.api.refresh(table, name);
                    });
                }, function (data, ret) {
                    var area = Fast.config.openArea != undefined ? Fast.config.openArea : [$(window).width() > 650 ? '650px' : '95%', $(window).height() > 710 ? '710px' : '95%'];
                    if (ret && ret.code === -2) {
                        //如果登录已经超时,重新提醒登录
                        if (uid && uid != ret.data.uid) {
                            Controller.api.userinfo.set(null);
                            $(".operate[data-name='" + name + "'] .btn-install:first").trigger("click");
                            return;
                        }
                        top.Fast.api.open(ret.data.payurl, __('Pay now'), {
                            area: area,
                            end: function () {
                                Fast.api.ajax({
                                    url: 'addon/isbuy',
                                    data: {
                                        name: name,
                                        force: force ? 1 : 0,
                                        uid: uid,
                                        token: token,
                                        version: version,
                                        faversion: Config.faversion
                                    }
                                }, function () {
                                    top.Layer.alert(__('Pay successful tips'), {
                                        btn: [__('Continue installation')],
                                        title: __('Warning'),
                                        icon: 1,
                                        yes: function (index) {
                                            top.Layer.close(index);
                                            install(name, version);
                                        }
                                    });
                                    return false;
                                }, function () {
                                    console.log(__('Canceled'));
                                    return false;
                                });
                            }
                        });
                    } else if (ret && ret.code === -3) {
                        //插件目录发现影响全局的文件
                        Layer.open({
                            content: Template("conflicttpl", ret.data),
                            shade: 0.8,
                            area: area,
                            title: __('Warning'),
                            btn: [__('Continue install'), __('Cancel')],
                            end: function () {

                            },
                            yes: function () {
                                install(name, version, true);
                            }
                        });

                    } else {
                        Layer.alert(ret.msg, {title: __('Warning'), icon: 0});
                    }
                    return false;
                });
            };

            var uninstall = function (name, force, droptables) {
                Fast.api.ajax({
                    url: 'addon/uninstall',
                    data: {name: name, force: force ? 1 : 0, droptables: droptables ? 1 : 0}
                }, function (data, ret) {
                    delete Config['addons'][name];
                    Layer.closeAll();
                    Controller.api.refresh(table, name);
                }, function (data, ret) {
                    if (ret && ret.code === -3) {
                        //插件目录发现影响全局的文件
                        Layer.open({
                            content: Template("conflicttpl", ret.data),
                            shade: 0.8,
                            area: area,
                            title: __('Warning'),
                            btn: [__('Continue uninstall'), __('Cancel')],
                            end: function () {

                            },
                            yes: function () {
                                uninstall(name, true, droptables);
                            }
                        });

                    } else {
                        Layer.alert(ret.msg, {title: __('Warning'), icon: 0});
                    }
                    return false;
                });
            };

            var operate = function (name, action, force, success) {
                Fast.api.ajax({
                    url: 'addon/state',
                    data: {name: name, action: action, force: force ? 1 : 0}
                }, function (data, ret) {
                    var addon = Config['addons'][name];
                    addon.state = action === 'enable' ? 1 : 0;
                    Layer.closeAll();
                    if (typeof success === 'function') {
                        success(data, ret);
                    }
                    Controller.api.refresh(table, name);
                }, function (data, ret) {
                    if (ret && ret.code === -3) {
                        //插件目录发现影响全局的文件
                        Layer.open({
                            content: Template("conflicttpl", ret.data),
                            shade: 0.8,
                            area: area,
                            title: __('Warning'),
                            btn: [__('Continue operate'), __('Cancel')],
                            end: function () {

                            },
                            yes: function () {
                                operate(name, action, true, success);
                            }
                        });

                    } else {
                        Layer.alert(ret.msg, {title: __('Warning'), icon: 0});
                    }
                    return false;
                });
            };

            var upgrade = function (name, version) {
                var userinfo = Controller.api.userinfo.get();
                var uid = userinfo ? userinfo.id : 0;
                var token = userinfo ? userinfo.token : '';
                Fast.api.ajax({
                    url: 'addon/upgrade',
                    data: {name: name, uid: uid, token: token, version: version, faversion: Config.faversion}
                }, function (data, ret) {
                    Config['addons'][name] = data.addon;
                    Layer.closeAll();
                    Controller.api.refresh(table, name);
                }, function (data, ret) {
                    Layer.alert(ret.msg, {title: __('Warning')});
                    return false;
                });
            };

            // 点击安装
            $(document).on("click", ".btn-install", function () {
                var that = this;
                var name = $(this).closest(".operate").data("name");
                var version = $(this).data("version");

                var userinfo = Controller.api.userinfo.get();
                var uid = userinfo ? userinfo.id : 0;

                if (parseInt(uid) === 0) {
                    $(".btn-userinfo").trigger("click", name, version);
                    return false;
                }
                install(name, version, false);
            });

            // 点击卸载
            $(document).on("click", ".btn-uninstall", function () {
                var name = $(this).closest(".operate").data('name');
                if (Config['addons'][name].state == 1) {
                    Layer.alert(__('Please disable the add before trying to uninstall'), {icon: 7});
                    return false;
                }
                Template.helper("__", __);
                tables = [];
                Layer.confirm(Template("uninstalltpl", {addon: Config['addons'][name]}), {focusBtn: false, title: __("Warning")}, function (index, layero) {
                    uninstall(name, false, $("input[name='droptables']", layero).prop("checked"));
                });
            });

            // 点击配置
            $(document).on("click", ".btn-config", function () {
                var name = $(this).closest(".operate").data("name");
                Fast.api.open("addon/config?name=" + name, __('Setting'));
            });

            // 点击启用/禁用
            $(document).on("click", ".btn-enable,.btn-disable", function () {
                var name = $(this).data("name");
                var action = $(this).data("action");
                operate(name, action, false);
            });

            // 点击升级
            $(document).on("click", ".btn-upgrade", function () {
                var name = $(this).closest(".operate").data('name');
                if (Config['addons'][name].state == 1) {
                    Layer.alert(__('Please disable the add before trying to upgrade'), {icon: 7});
                    return false;
                }
                var version = $(this).data("version");

                Layer.confirm(__('Upgrade tips', Config['addons'][name].title), {title: __('Warmtips')}, function (index, layero) {
                    upgrade(name, version);
                });
            });

            $(document).on("click", ".operate .btn-group .dropdown-toggle", function () {
                $(this).closest(".btn-group").toggleClass("dropup", $(document).height() - $(this).offset().top <= 200);
            });

            $(document).on("click", ".view-screenshots", function () {
                var row = Table.api.getrowbyindex(table, parseInt($(this).data("index")));
                var data = [];
                $.each(row.screenshots, function (i, j) {
                    data.push({
                        "src": j
                    });
                });
                var json = {
                    "title": row.title,
                    "data": data
                };
                top.Layer.photos(top.JSON.parse(JSON.stringify({photos: json})));
            });
        },
        add: function () {
            Controller.api.bindevent();
        },
        config: function () {
            $(document).on("click", ".nav-group li a[data-toggle='tab']", function () {
                if ($(this).attr("href") == "#all") {
                    $(".tab-pane").addClass("active in");
                }
                return;
                var type = $(this).attr("href").substring(1);
                if (type == 'all') {
                    $(".table-config tr").show();
                } else {
                    $(".table-config tr").hide();
                    $(".table-config tr[data-group='" + type + "']").show();
                }
            });

            Controller.api.bindevent();
        },
        api: {
            formatter: {
                title: function (value, row, index) {
                    if ($(".btn-switch.active").data("type") == "local") {
                        // return value;
                    }
                    var title = '<a class="title" href="' + row.url + '" data-toggle="tooltip" title="' + __('View addon home page') + '" target="_blank"><span class="' + Fast.api.escape(row.color) + '">' + value + '</span></a>';
                    if (row.screenshots && row.screenshots.length > 0) {
                        title += ' <a href="javascript:;" data-index="' + index + '" class="view-screenshots text-success" title="' + __('View addon screenshots') + '" data-toggle="tooltip"><i class="fa fa-image"></i></a>';
                    }
                    return title;
                },
                intro: function (value, row, index) {
                    return row.intro + (row.extend ? "<a href='" + Fast.api.escape(row.extend[1]) + "' class='" + Fast.api.escape(row.extend[2]) + "'>" + Fast.api.escape(row.extend[0]) + "</a>" : "");
                },
                operate: function (value, row, index) {
                    return Template("operatetpl", {item: row, index: index});
                },
                toggle: function (value, row, index) {
                    if (!row.addon) {
                        return '';
                    }
                    return '<a href="javascript:;" data-toggle="tooltip" title="' + __('Click to toggle status') + '" class="btn btn-toggle btn-' + (row.addon.state == 1 ? "disable" : "enable") + '" data-action="' + (row.addon.state == 1 ? "disable" : "enable") + '" data-name="' + row.name + '"><i class="fa ' + (row.addon.state == 0 ? 'fa-toggle-on fa-rotate-180 text-gray' : 'fa-toggle-on text-success') + ' fa-2x"></i></a>';
                },
                author: function (value, row, index) {
                    var url = 'javascript:';
                    if (typeof row.homepage !== 'undefined') {
                        url = row.homepage;
                    } else if (typeof row.qq !== 'undefined' && row.qq) {
                        url = 'https://wpa.qq.com/msgrd?v=3&uin=' + row.qq + '&site=&menu=yes';
                    }
                    return '<a href="' + url + '" target="_blank" data-toggle="tooltip" class="text-primary">' + value + '</a>';
                },
                price: function (value, row, index) {
                    if (isNaN(value)) {
                        return value;
                    }
                    return parseFloat(value) == 0 ? '<span class="text-success">' + __('Free') + '</span>' : '<span class="text-danger">￥' + value + '</span>';
                },
                downloads: function (value, row, index) {
                    return value;
                },
                version: function (value, row, index) {
                    return row.addon && row.addon.version != row.version ? '<a href="' + row.url + '?version=' + row.version + '" target="_blank"><span class="releasetips text-primary" data-toggle="tooltip" title="' + __('New version tips', row.version) + '">' + row.addon.version + '<i></i></span></a>' : row.version;
                },
                home: function (value, row, index) {
                    return row.addon && parseInt(row.addon.state) > 0 ? '<a href="' + row.addon.url + '" data-toggle="tooltip" title="' + __('View addon index page') + '" target="_blank"><i class="fa fa-home text-primary"></i></a>' : '<a href="javascript:;"><i class="fa fa-home text-gray"></i></a>';
                },
            },
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            userinfo: {
                get: function () {
                    if (typeof $.cookie !== 'undefined') {
                        var userinfo = $.cookie('fastadmin_userinfo');
                    } else {
                        var userinfo = sessionStorage.getItem("fastadmin_userinfo");
                    }
                    return userinfo ? JSON.parse(userinfo) : null;
                },
                set: function (data) {
                    if (typeof $.cookie !== 'undefined') {
                        if (data) {
                            $.cookie("fastadmin_userinfo", JSON.stringify(data));
                        } else {
                            $.removeCookie("fastadmin_userinfo");
                        }
                    } else {
                        if (data) {
                            sessionStorage.setItem("fastadmin_userinfo", JSON.stringify(data));
                        } else {
                            sessionStorage.removeItem("fastadmin_userinfo");
                        }
                    }
                }
            },
            sid: function () {
                var sid = $.cookie('fastadmin_sid');
                if (!sid) {
                    sid = Math.random().toString(20).substr(2, 12);
                    $.cookie('fastadmin_sid', sid);
                }
                return sid;
            },
            refresh: function (table, name) {
                //刷新左侧边栏
                Fast.api.refreshmenu();

                //刷新行数据
                if ($(".operate[data-name='" + name + "']").length > 0) {
                    var tr = $(".operate[data-name='" + name + "']").closest("tr[data-index]");
                    var index = tr.data("index");
                    var row = Table.api.getrowbyindex(table, index);
                    row.addon = typeof Config['addons'][name] !== 'undefined' ? Config['addons'][name] : undefined;
                    table.bootstrapTable("updateRow", {index: index, row: row});
                } else if ($(".btn-switch.active").data("type") == "local") {
                    $(".btn-refresh").trigger("click");
                }
            }
        }
    };
    return Controller;
});
