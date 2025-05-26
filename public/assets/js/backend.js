define(['fast', 'template', 'moment'], function (Fast, Template, Moment) {
    var Backend = {
        api: {
            sidebar: function (params) {
                colorArr = ['red', 'green', 'yellow', 'blue', 'teal', 'orange', 'purple'];
                $colorNums = colorArr.length;
                badgeList = {};
                $.each(params, function (k, v) {
                    $url = Fast.api.fixurl(k);

                    if ($.isArray(v)) {
                        $nums = typeof v[0] !== 'undefined' ? v[0] : 0;
                        $color = typeof v[1] !== 'undefined' ? v[1] : colorArr[(!isNaN($nums) ? $nums : $nums.length) % $colorNums];
                        $class = typeof v[2] !== 'undefined' ? v[2] : 'label';
                    } else {
                        $nums = v;
                        $color = colorArr[(!isNaN($nums) ? $nums : $nums.length) % $colorNums];
                        $class = 'label';
                    }
                    //必须nums大于0才显示
                    badgeList[$url] = $nums > 0 ? '<small class="' + $class + ' pull-right bg-' + $color + '">' + $nums + '</small>' : '';
                });
                $.each(badgeList, function (k, v) {
                    var anchor = top.window.$("li a[addtabs][url='" + k + "']");
                    if (anchor) {
                        top.window.$(".pull-right-container", anchor).html(v);
                        top.window.$(".nav-addtabs li a[node-id='" + anchor.attr("addtabs") + "'] .pull-right-container").html(v);
                    }
                });
            },
            addtabs: function (url, title, icon) {
                var dom = "a[url='{url}']"
                var leftlink = top.window.$(dom.replace(/\{url\}/, url));
                if (leftlink.length > 0) {
                    leftlink.trigger("click");
                } else {
                    url = Fast.api.fixurl(url);
                    leftlink = top.window.$(dom.replace(/\{url\}/, url));
                    if (leftlink.length > 0) {
                        var event = leftlink.parent().hasClass("active") ? "dblclick" : "click";
                        leftlink.trigger(event);
                    } else {
                        var baseurl = url.substr(0, url.indexOf("?") > -1 ? url.indexOf("?") : url.length);
                        leftlink = top.window.$(dom.replace(/\{url\}/, baseurl));
                        //能找到相对地址
                        if (leftlink.length > 0) {
                            icon = typeof icon !== 'undefined' ? icon : leftlink.find("i").attr("class");
                            title = typeof title !== 'undefined' ? title : leftlink.find("span:first").text();
                            leftlink.trigger("fa.event.toggleitem");
                        }
                        var navnode = top.window.$(".nav-tabs ul li a[node-url='" + url + "']");
                        if (navnode.length > 0) {
                            navnode.trigger("click");
                        } else {
                            //追加新的tab
                            var id = Math.floor(new Date().valueOf() * Math.random());
                            icon = typeof icon !== 'undefined' ? icon : 'fa fa-circle-o';
                            title = typeof title !== 'undefined' ? title : '';
                            var newAnchor = top.window.$("<a />").prop('href', url).attr('url', url).append('<i class="' + icon + '"></i> <span>' + title + '</span>').addClass("hide").appendTo(top.window.document.body);
                            if (window.self === window.top && Config.controllername + '/' + Config.actionname !== 'index/index') {
                                newAnchor.prop('target', '_blank').get(0).click();
                                newAnchor.remove();
                            } else {
                                newAnchor.attr('addtabs', id).trigger("click");
                            }
                        }
                    }
                }
            },
            closetabs: function (url) {
                if (typeof url === 'undefined') {
                    top.window.$("ul.nav-addtabs li.active .close-tab").trigger("click");
                } else {
                    var dom = "a[url='{url}']"
                    var navlink = top.window.$(dom.replace(/\{url\}/, url));
                    if (navlink.length === 0) {
                        url = Fast.api.fixurl(url);
                        navlink = top.window.$(dom.replace(/\{url\}/, url));
                        if (navlink.length === 0) {
                        } else {
                            var baseurl = url.substr(0, url.indexOf("?") > -1 ? url.indexOf("?") : url.length);
                            navlink = top.window.$(dom.replace(/\{url\}/, baseurl));
                            //能找到相对地址
                            if (navlink.length === 0) {
                                navlink = top.window.$(".nav-tabs ul li a[node-url='" + url + "']");
                            }
                        }
                    }
                    if (navlink.length > 0 && navlink.attr('addtabs')) {
                        top.window.$("ul.nav-addtabs li#tab_" + navlink.attr('addtabs') + " .close-tab").trigger("click");
                    }
                }
            },
            replaceids: function (elem, url) {
                //如果有需要替换ids的
                if (url.indexOf("{ids}") > -1) {
                    var ids = 0;
                    var tableId = $(elem).data("table-id");
                    if (tableId && $("#" + tableId).length > 0 && $("#" + tableId).data("bootstrap.table")) {
                        var Table = require("table");
                        ids = Table.api.selectedids($("#" + tableId)).join(",");
                    }
                    url = url.replace(/\{ids\}/g, ids);
                }
                return url;
            },
            refreshmenu: function () {
                top.window.$(".sidebar-menu").trigger("refresh");
            },
            gettablecolumnbutton: function (options) {
                if (typeof options.tableId !== 'undefined' && typeof options.fieldIndex !== 'undefined' && typeof options.buttonIndex !== 'undefined') {
                    var tableOptions = $("#" + options.tableId).bootstrapTable('getOptions');
                    if (tableOptions) {
                        var columnObj = null;
                        $.each(tableOptions.columns, function (i, columns) {
                            $.each(columns, function (j, column) {
                                if (typeof column.fieldIndex !== 'undefined' && column.fieldIndex === options.fieldIndex) {
                                    columnObj = column;
                                    return false;
                                }
                            });
                            if (columnObj) {
                                return false;
                            }
                        });
                        if (columnObj) {
                            return columnObj['buttons'][options.buttonIndex];
                        }
                    }
                }
                return null;
            },
        },
        init: function () {
            //公共代码
            //添加ios-fix兼容iOS下的iframe
            if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
                $("html").addClass("ios-fix");
            }
            //配置Toastr的参数
            Toastr.options.positionClass = Config.controllername === 'index' ? "toast-top-right-index" : "toast-top-right";
            //点击包含.btn-dialog的元素时弹出dialog
            $(document).on('click', '.btn-dialog,.dialogit', function (e) {
                var that = this;
                var options = $.extend({}, $(that).data() || {});
                var url = Backend.api.replaceids(that, $(that).data("url") || $(that).attr('href'));
                var title = $(that).attr("title") || $(that).data("title") || $(that).data('original-title');
                var button = Backend.api.gettablecolumnbutton(options);
                if (button && typeof button.callback === 'function') {
                    options.callback = button.callback;
                }
                if (typeof options.confirm !== 'undefined') {
                    Layer.confirm(options.confirm, function (index) {
                        Backend.api.open(url, title, options);
                        Layer.close(index);
                    });
                } else {
                    window[$(that).data("window") || 'self'].Backend.api.open(url, title, options);
                }
                return false;
            });
            //点击包含.btn-addtabs的元素时新增选项卡
            $(document).on('click', '.btn-addtabs,.addtabsit', function (e) {
                var that = this;
                var options = $.extend({}, $(that).data() || {});
                var url = Backend.api.replaceids(that, $(that).data("url") || $(that).attr('href'));
                var title = $(that).attr("title") || $(that).data("title") || $(that).data('original-title');
                var icon = $(that).attr("icon") || $(that).data("icon");
                if (typeof options.confirm !== 'undefined') {
                    Layer.confirm(options.confirm, function (index) {
                        Backend.api.addtabs(url, title, icon);
                        Layer.close(index);
                    });
                } else {
                    Backend.api.addtabs(url, title, icon);
                }
                return false;
            });
            //点击包含.btn-ajax的元素时发送Ajax请求
            $(document).on('click', '.btn-ajax,.ajaxit', function (e) {
                var that = this;
                var options = $.extend({}, $(that).data() || {});
                if (typeof options.url === 'undefined' && $(that).attr("href")) {
                    options.url = $(that).attr("href");
                }
                options.url = Backend.api.replaceids(this, options.url);
                var success = typeof options.success === 'function' ? options.success : null;
                var error = typeof options.error === 'function' ? options.error : null;
                delete options.success;
                delete options.error;
                var button = Backend.api.gettablecolumnbutton(options);
                if (button) {
                    if (typeof button.success === 'function') {
                        success = button.success;
                    }
                    if (typeof button.error === 'function') {
                        error = button.error;
                    }
                }
                //如果未设备成功的回调,设定了自动刷新的情况下自动进行刷新
                if (!success && typeof options.tableId !== 'undefined' && typeof options.refresh !== 'undefined' && options.refresh) {
                    success = function () {
                        $("#" + options.tableId).bootstrapTable('refresh');
                    }
                }
                if (typeof options.confirm !== 'undefined') {
                    Layer.confirm(options.confirm, function (index) {
                        Backend.api.ajax(options, success, error);
                        Layer.close(index);
                    });
                } else {
                    Backend.api.ajax(options, success, error);
                }
                return false;
            });
            $(document).on('click', '.btn-click,.clickit', function (e) {
                var that = this;
                var options = $.extend({}, $(that).data() || {});
                var row = {};
                if (typeof options.tableId !== 'undefined') {
                    var index = parseInt(options.rowIndex);
                    var data = $("#" + options.tableId).bootstrapTable('getData');
                    row = typeof data[index] !== 'undefined' ? data[index] : {};
                }
                var button = Backend.api.gettablecolumnbutton(options);
                var click = typeof button.click === 'function' ? button.click : $.noop;

                if (typeof options.confirm !== 'undefined') {
                    Layer.confirm(options.confirm, function (index) {
                        click.apply(that, [options, row, button]);
                        Layer.close(index);
                    });
                } else {
                    click.apply(that, [options, row, button]);
                }
                return false;
            });
            //修复含有fixed-footer类的body边距
            if ($(".fixed-footer").length > 0) {
                $(document.body).css("padding-bottom", $(".fixed-footer").outerHeight());
            }
            //修复不在iframe时layer-footer隐藏的问题
            if ($(".layer-footer").length > 0 && self === top) {
                $(".layer-footer").show();
            }
            //tooltip和popover
            if (!('ontouchstart' in document.documentElement)) {
                $('body').tooltip({selector: '[data-toggle="tooltip"]', trigger: 'hover'});
            }
            $('body').popover({selector: '[data-toggle="popover"]'});
        }
    };
    Backend.api = $.extend(Fast.api, Backend.api);
    //将Template渲染至全局,以便于在子框架中调用
    window.Template = Template;
    //将Moment渲染至全局,以便于在子框架中调用
    window.Moment = Moment;
    //将Backend渲染至全局,以便于在子框架中调用
    window.Backend = Backend;

    Backend.init();
    return Backend;
});
