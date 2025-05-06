define(['jquery', 'bootstrap', 'toastr', 'layer', 'lang'], function ($, undefined, Toastr, Layer, Lang) {
    var Fast = {
        config: {
            //toastr默认配置
            toastr: {
                "closeButton": true,
                "debug": false,
                "newestOnTop": false,
                "progressBar": false,
                "positionClass": "toast-top-center",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            }
        },
        events: {
            //请求成功的回调
            onAjaxSuccess: function (ret, onAjaxSuccess) {
                var data = typeof ret.data !== 'undefined' ? ret.data : null;
                var msg = typeof ret.msg !== 'undefined' && ret.msg ? ret.msg : __('Operation completed');

                if (typeof onAjaxSuccess === 'function') {
                    var result = onAjaxSuccess.call(this, data, ret);
                    if (result === false)
                        return;
                }
                Toastr.success(msg);
            },
            //请求错误的回调
            onAjaxError: function (ret, onAjaxError) {
                var data = typeof ret.data !== 'undefined' ? ret.data : null;
                if (typeof onAjaxError === 'function') {
                    var result = onAjaxError.call(this, data, ret);
                    if (result === false) {
                        return;
                    }
                }
                Toastr.error(ret.msg);
            },
            //服务器响应数据后
            onAjaxResponse: function (response) {
                try {
                    var ret = typeof response === 'object' ? response : JSON.parse(response);
                    if (!ret.hasOwnProperty('code')) {
                        $.extend(ret, {code: -2, msg: response, data: null});
                    }
                } catch (e) {
                    var ret = {code: -1, msg: e.message, data: null};
                }
                return ret;
            }
        },
        api: {
            //发送验证码
            sendcaptcha: function (btn, type, data, success, error) {
                $(btn).addClass("disabled", true).text("发送中...");
                var si = {};
                Frontend.api.ajax({url: $(btn).data("url"), data: data}, function (data, ret) {
                    clearInterval(si[type]);
                    var seconds = 60;
                    si[type] = setInterval(function () {
                        seconds--;
                        if (seconds <= 0) {
                            clearInterval(si[type]);
                            $(btn).removeClass("disabled").text("发送验证码");
                        } else {
                            $(btn).addClass("disabled").text(seconds + "秒后可再次发送");
                        }
                    }, 1000);
                    if (typeof success == 'function') {
                        success.call(this, data, ret);
                    }
                }, function (data, ret) {
                    $(btn).removeClass("disabled").text('发送验证码');

                    if (typeof error == 'function') {
                        error.call(this, data, ret);
                    }
                });
            },
            //准备验证码
            preparecaptcha: function (btn, type, data) {
                require(['form'], function (Form) {
                    Layer.open({
                        title: '请完成验证',
                        type: 1,
                        content: Template("captchatpl", {}),
                        offset: "130px",
                        btnAlign: 'c',
                        success: function (layero, index) {
                            var form = $("form", layero);
                            $("input[name=captcha]", form).focus();
                            form.data("validator-options", {
                                valid: function (ret) {
                                    data.captcha = $("input[name=captcha]", form).val();
                                    if(data.captcha.length < 4){
                                        Toastr.error("验证码不正确");
                                        $("input[name=captcha]", form).focus();
                                        return false;
                                    }
                                    Frontend.api.sendcaptcha(btn, type, data, function (data, ret) {
                                        Layer.close(index);
                                        $(btn).closest("form").find("input[name='captcha']").focus();
                                    }, function (data, ret) {
                                        $("img.captcha-img", form).trigger("click");
                                    });
                                    return true;
                                }
                            })
                            Form.api.bindevent(form);
                        }
                    });
                });
            },
            //发送Ajax请求
            ajax: function (options, success, error) {
                options = typeof options === 'string' ? {url: options} : options;
                var index;
                if (typeof options.loading === 'undefined' || options.loading) {
                    index = Layer.load(options.loading || 0);
                }
                options = $.extend({
                    type: "POST",
                    dataType: "json",
                    xhrFields: {
                        withCredentials: true
                    },
                    success: function (ret) {
                        index && Layer.close(index);
                        ret = Fast.events.onAjaxResponse(ret);
                        if (ret.code === 1) {
                            Fast.events.onAjaxSuccess(ret, success);
                        } else {
                            Fast.events.onAjaxError(ret, error);
                        }
                    },
                    error: function (xhr) {
                        index && Layer.close(index);
                        var ret = {code: xhr.status, msg: xhr.statusText, data: null};
                        Fast.events.onAjaxError(ret, error);
                    }
                }, options);
                return $.ajax(options);
            },
            //修复URL
            fixurl: function (url) {
                if (url.substr(0, 1) !== "/") {
                    var r = new RegExp('^(?:[a-z]+:)?//', 'i');
                    if (!r.test(url)) {
                        url = Config.moduleurl + "/" + url;
                    }
                } else if (url.substr(0, 8) === "/addons/") {
                    url = Config.__PUBLIC__.replace(/(\/*$)/g, "") + url;
                }
                return url;
            },
            //获取修复后可访问的cdn链接
            cdnurl: function (url, domain) {
                var rule = new RegExp("^((?:[a-z]+:)?\\/\\/|data:image\\/)", "i");
                var cdnurl = Config.upload.cdnurl;
                if (typeof domain === 'undefined' || domain === true || cdnurl.indexOf("/") === 0) {
                    url = rule.test(url) || (cdnurl && url.indexOf(cdnurl) === 0) ? url : cdnurl + url;
                }
                if (domain && !rule.test(url)) {
                    domain = typeof domain === 'string' ? domain : location.origin;
                    url = domain + url;
                }
                return url;
            },
            //查询Url参数
            query: function (name, url) {
                if (!url) {
                    url = window.location.href;
                }
                if (!name)
                    return '';
                name = name.replace(/[\[\]]/g, "\\$&");
                var regex = new RegExp("[?&/]" + name + "([=/]([^&#/?]*)|&|#|$)"),
                    results = regex.exec(url);
                if (!results)
                    return null;
                if (!results[2])
                    return '';
                return decodeURIComponent(results[2].replace(/\+/g, " "));
            },
            //打开一个弹出窗口
            open: function (url, title, options) {
                title = options && options.title ? options.title : (title ? title : "");
                url = Fast.api.fixurl(url);
                url = url + (url.indexOf("?") > -1 ? "&" : "?") + "dialog=1";
                var area = Fast.config.openArea != undefined ? Fast.config.openArea : [$(window).width() > 800 ? '800px' : '95%', $(window).height() > 600 ? '600px' : '95%'];
                var success = options && typeof options.success === 'function' ? options.success : $.noop;
                if (options && typeof options.success === 'function') {
                    delete options.success;
                }
                options = $.extend({
                    type: 2,
                    title: title,
                    shadeClose: true,
                    shade: false,
                    maxmin: true,
                    moveOut: true,
                    area: area,
                    content: url,
                    zIndex: Layer.zIndex,
                    success: function (layero, index) {
                        var that = this;
                        //存储callback事件
                        $(layero).data("callback", that.callback);
                        //$(layero).removeClass("layui-layer-border");
                        Layer.setTop(layero);
                        try {
                            var frame = Layer.getChildFrame('html', index);
                            var layerfooter = frame.find(".layer-footer");
                            Fast.api.layerfooter(layero, index, that);

                            //绑定事件
                            if (layerfooter.length > 0) {
                                // 监听窗口内的元素及属性变化
                                // Firefox和Chrome早期版本中带有前缀
                                var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;
                                if (MutationObserver) {
                                    // 选择目标节点
                                    var target = layerfooter[0];
                                    // 创建观察者对象
                                    var observer = new MutationObserver(function (mutations) {
                                        Fast.api.layerfooter(layero, index, that);
                                        mutations.forEach(function (mutation) {
                                        });
                                    });
                                    // 配置观察选项:
                                    var config = {attributes: true, childList: true, characterData: true, subtree: true}
                                    // 传入目标节点和观察选项
                                    observer.observe(target, config);
                                    // 随后,你还可以停止观察
                                    // observer.disconnect();
                                }
                            }
                        } catch (e) {

                        }
                        if ($(layero).height() > $(window).height()) {
                            //当弹出窗口大于浏览器可视高度时,重定位
                            Layer.style(index, {
                                top: 0,
                                height: $(window).height()
                            });
                        }
                        success.call(this, layero, index);
                    }
                }, options ? options : {});
                if ($(window).width() < 480 || (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream && top.$(".tab-pane.active").length > 0)) {
                    if (top.$(".tab-pane.active").length > 0) {
                        options.area = [top.$(".tab-pane.active").width() + "px", top.$(".tab-pane.active").height() + "px"];
                        options.offset = [top.$(".tab-pane.active").scrollTop() + "px", "0px"];
                    } else {
                        options.area = [$(window).width() + "px", $(window).height() + "px"];
                        options.offset = ["0px", "0px"];
                    }
                }
                return Layer.open(options);
            },
            //关闭窗口并回传数据
            close: function (data) {
                var index = parent.Layer.getFrameIndex(window.name);
                var callback = parent.$("#layui-layer" + index).data("callback");
                //再执行关闭
                parent.Layer.close(index);
                //再调用回传函数
                if (typeof callback === 'function') {
                    callback.call(undefined, data);
                }
            },
            layerfooter: function (layero, index, that) {
                var frame = Layer.getChildFrame('html', index);
                var layerfooter = frame.find(".layer-footer");
                if (layerfooter.length > 0) {
                    $(".layui-layer-footer", layero).remove();
                    var footer = $("<div />").addClass('layui-layer-btn layui-layer-footer');
                    footer.html(layerfooter.html());
                    if ($(".row", footer).length === 0) {
                        $(">", footer).wrapAll("<div class='row'></div>");
                    }
                    footer.insertAfter(layero.find('.layui-layer-content'));
                    //绑定事件
                    footer.on("click", ".btn", function () {
                        if ($(this).hasClass("disabled") || $(this).parent().hasClass("disabled")) {
                            return;
                        }
                        var index = footer.find('.btn').index(this);
                        $(".btn:eq(" + index + ")", layerfooter).trigger("click");
                    });

                    var titHeight = layero.find('.layui-layer-title').outerHeight() || 0;
                    var btnHeight = layero.find('.layui-layer-btn').outerHeight() || 0;
                    //重设iframe高度
                    $("iframe", layero).height(layero.height() - titHeight - btnHeight);
                }
                //修复iOS下弹出窗口的高度和iOS下iframe无法滚动的BUG
                if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
                    var titHeight = layero.find('.layui-layer-title').outerHeight() || 0;
                    var btnHeight = layero.find('.layui-layer-btn').outerHeight() || 0;
                    $("iframe", layero).parent().css("height", layero.height() - titHeight - btnHeight);
                    $("iframe", layero).css("height", "100%");
                }
            },
            success: function (options, callback) {
                var type = typeof options === 'function';
                if (type) {
                    callback = options;
                }
                return Layer.msg(__('Operation completed'), $.extend({
                    offset: 0, icon: 1
                }, type ? {} : options), callback);
            },
            error: function (options, callback) {
                var type = typeof options === 'function';
                if (type) {
                    callback = options;
                }
                return Layer.msg(__('Operation failed'), $.extend({
                    offset: 0, icon: 2
                }, type ? {} : options), callback);
            },
            msg: function (message, url) {
                var callback = typeof url === 'function' ? url : function () {
                    if (typeof url !== 'undefined' && url) {
                        location.href = url;
                    }
                };
                Layer.msg(message, {
                    time: 2000
                }, callback);
            },
            escape: function (text) {
                if (typeof text === 'string') {
                    return text
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;')
                        .replace(/`/g, '&#x60;');
                }
                return text;
            },
            toastr: Toastr,
            layer: Layer
        },
        lang: function () {
            var args = arguments,
                string = args[0],
                i = 1;
            string = string.toLowerCase();
            //string = typeof Lang[string] != 'undefined' ? Lang[string] : string;
            if (typeof Lang !== 'undefined' && typeof Lang[string] !== 'undefined') {
                if (typeof Lang[string] == 'object')
                    return Lang[string];
                string = Lang[string];
            } else if (string.indexOf('.') !== -1 && false) {
                var arr = string.split('.');
                var current = Lang[arr[0]];
                for (var i = 1; i < arr.length; i++) {
                    current = typeof current[arr[i]] != 'undefined' ? current[arr[i]] : '';
                    if (typeof current != 'object')
                        break;
                }
                if (typeof current == 'object')
                    return current;
                string = current;
            } else {
                string = args[0];
            }
            return string.replace(/%((%)|s|d)/g, function (m) {
                // m is the matched format, e.g. %s, %d
                var val = null;
                if (m[2]) {
                    val = m[2];
                } else {
                    val = args[i];
                    // A switch statement so that the formatter can be extended. Default is %s
                    switch (m) {
                        case '%d':
                            val = parseFloat(val);
                            if (isNaN(val)) {
                                val = 0;
                            }
                            break;
                    }
                    i++;
                }
                return val;
            });
        },
        init: function () {
            // jQuery兼容处理
            $.fn.extend({
                size: function () {
                    return $(this).length;
                }
            });
            // 对相对地址进行处理
            $.ajaxSetup({
                beforeSend: function (xhr, setting) {
                    setting.url = Fast.api.fixurl(setting.url);
                }
            });
            Layer.config({
                skin: 'layui-layer-fast'
            });
            // 绑定ESC关闭窗口事件
            $(window).keyup(function (e) {
                if (e.keyCode == 27) {
                    if ($(".layui-layer").length > 0) {
                        var index = 0;
                        $(".layui-layer").each(function () {
                            index = Math.max(index, parseInt($(this).attr("times")));
                        });
                        if (index) {
                            Layer.close(index);
                        }
                    }
                }
            });

            //公共代码
            //配置Toastr的参数
            Toastr.options = Fast.config.toastr;
        }
    };
    //将Layer暴露到全局中去
    window.Layer = Layer;
    //将Toastr暴露到全局中去
    window.Toastr = Toastr;
    //将语言方法暴露到全局中去
    window.__ = Fast.lang;
    //将Fast渲染至全局
    window.Fast = Fast;

    //默认初始化执行的代码
    Fast.init();
    return Fast;
});
