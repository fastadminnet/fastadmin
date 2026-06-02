define(['fast', 'template', 'moment'], function (Fast, Template, Moment) {
    var Frontend = {
        api: Fast.api,
        init: function () {
            var si = {};
            //发送验证码
            $(document).on("click", ".btn-captcha", function (e) {
                var type = $(this).data("type") ? $(this).data("type") : 'mobile';
                var btn = this;
                Frontend.api.sendcaptcha = function (btn, type, data, callback) {
                    $(btn).addClass("disabled", true).text("发送中...");

                    Frontend.api.ajax({url: $(btn).data("url"), data: data}, function (data, ret) {
                        clearInterval(si[type]);
                        var seconds = 60;
                        si[type] = setInterval(function () {
                            seconds--;
                            if (seconds <= 0) {
                                clearInterval(si);
                                $(btn).removeClass("disabled").text("发送验证码");
                            } else {
                                $(btn).addClass("disabled").text(seconds + "秒后可再次发送");
                            }
                        }, 1000);
                        if (typeof callback == 'function') {
                            callback.call(this, data, ret);
                        }
                    }, function () {
                        $(btn).removeClass("disabled").text('发送验证码');
                    });
                };
                if (['mobile', 'email'].indexOf(type) > -1) {
                    var element = $(this).data("input-id") ? $("#" + $(this).data("input-id")) : $("input[name='" + type + "']", $(this).closest("form"));
                    var text = type === 'email' ? '邮箱' : '手机号码';
                    if (element.val() === "") {
                        Layer.msg(text + "不能为空！");
                        element.focus();
                        return false;
                    } else if (type === 'mobile' && !element.val().match(/^1[3-9]\d{9}$/)) {
                        Layer.msg("请输入正确的" + text + "！");
                        element.focus();
                        return false;
                    } else if (type === 'email' && !element.val().match(/^[\w\+\-]+(\.[\w\+\-]+)*@[a-z\d\-]+(\.[a-z\d\-]+)*\.([a-z]{2,4})$/)) {
                        Layer.msg("请输入正确的" + text + "！");
                        element.focus();
                        return false;
                    }
                    element.isValid(function (v) {
                        if (v) {
                            var data = {event: $(btn).data("event")};
                            data[type] = element.val();
                            Frontend.api.sendcaptcha(btn, type, data);
                        } else {
                            Layer.msg("请确认已经输入了正确的" + text + "！");
                        }
                    });
                } else {
                    var data = {event: $(btn).data("event")};
                    Frontend.api.sendcaptcha(btn, type, data, function (data, ret) {
                        Layer.open({title: false, area: ["400px", "430px"], content: "<img src='" + data.image + "' width='400' height='400' /><div class='text-center panel-title'>扫一扫关注公众号获取验证码</div>", type: 1});
                    });
                }
                return false;
            });
            //tooltip和popover
            if (!('ontouchstart' in document.documentElement)) {
                $('body').tooltip({selector: '[data-toggle="tooltip"]'});
            }
            $('body').popover({selector: '[data-toggle="popover"]'});

            // 手机端左右滑动切换菜单栏
            if ('ontouchstart' in document.documentElement) {
                var startX, startY, moveEndX, moveEndY, relativeX, relativeY, element;
                element = $('body', document);
                element.on("touchstart", function (e) {
                    startX = e.originalEvent.changedTouches[0].pageX;
                    startY = e.originalEvent.changedTouches[0].pageY;
                });
                element.on("touchend", function (e) {
                    moveEndX = e.originalEvent.changedTouches[0].pageX;
                    moveEndY = e.originalEvent.changedTouches[0].pageY;
                    relativeX = moveEndX - startX;
                    relativeY = moveEndY - startY;

                    // 判断标准
                    //右滑
                    if (relativeX > 45) {
                        if ((Math.abs(relativeX) - Math.abs(relativeY)) > 50) {
                            element.addClass("sidebar-open");
                        }
                    }
                    //左滑
                    else if (relativeX < -45) {
                        if ((Math.abs(relativeX) - Math.abs(relativeY)) > 50) {
                            element.removeClass("sidebar-open");
                        }
                    }
                });
            }
            $(document).on("click", ".sidebar-toggle", function () {
                $("body").toggleClass("sidebar-open");
            });

            $(document).on("click", "a[href*='user/logout']", function () {
                var that = this;
                $.ajax({
                    type: 'GET', dataType: 'html', url: $(that).attr("href"),
                    success: function (data, status, xhr) {
                        Fast.api.ajax({url: $(that).attr("href"), loading:false, data: {__token__: xhr.getResponseHeader('__token__')}}, function (data, ret) {
                            Layer.msg(ret.msg, {icon: 1, time: 1500}, function () {
                                location.reload();
                            });
                            return false;
                        });
                    }, error: function (xhr, type) {
                        Layer.msg(__('Network error'), {icon: 2});
                    }
                });
                return false;
            });
        }
    };
    Frontend.api = $.extend(Fast.api, Frontend.api);
    //将Template渲染至全局,以便于在子框架中调用
    window.Template = Template;
    //将Moment渲染至全局,以便于在子框架中调用
    window.Moment = Moment;
    //将Frontend渲染至全局,以便于在子框架中调用
    window.Frontend = Frontend;

    Frontend.init();
    return Frontend;
});
