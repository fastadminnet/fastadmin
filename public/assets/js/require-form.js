define(['jquery', 'bootstrap', 'upload', 'validator', 'validator-lang'], function ($, undefined, Upload, Validator, undefined) {
    var Form = {
        config: {
            fieldlisttpl: '<dd class="form-inline"><input type="text" name="<%=name%>[<%=index%>][key]" class="form-control" value="<%=key%>" placeholder="<%=options.keyPlaceholder||\'\'%>" size="10" /> <input type="text" name="<%=name%>[<%=index%>][value]" class="form-control" value="<%=value%>" placeholder="<%=options.valuePlaceholder||\'\'%>" /> <span class="btn btn-sm btn-danger btn-remove"><i class="fa fa-times"></i></span> <span class="btn btn-sm btn-primary btn-dragsort"><i class="fa fa-arrows"></i></span></dd>'
        },
        events: {
            validator: function (form, success, error, submit) {
                if (!form.is("form"))
                    return;
                //绑定表单事件
                form.validator($.extend({
                    rules: {
                        username: [/^\w{3,30}$/, __('Username must be 3 to 30 characters')],
                        password: [/^[\S]{6,30}$/, __('Password must be 6 to 30 characters')]
                    },
                    validClass: 'has-success',
                    invalidClass: 'has-error',
                    bindClassTo: '.form-group',
                    formClass: 'n-default n-bootstrap',
                    msgClass: 'n-right',
                    stopOnError: true,
                    display: function (elem) {
                        return $(elem).closest('.form-group').find(".control-label").text().replace(/\:/, '');
                    },
                    dataFilter: function (data) {
                        if (data.code === 1) {
                            return data.msg ? {"ok": data.msg} : '';
                        } else {
                            return data.msg;
                        }
                    },
                    target: function (input) {
                        var target = $(input).data("target");
                        if (target && $(target).length > 0) {
                            return $(target);
                        }
                        var $formitem = $(input).closest('.form-group'),
                            $msgbox = $formitem.find('span.msg-box');
                        if (!$msgbox.length) {
                            return [];
                        }
                        return $msgbox;
                    },
                    valid: function (ret) {
                        var that = this, submitBtn = $(".layer-footer [type=submit]", form);
                        that.holdSubmit(true);
                        submitBtn.addClass("disabled");
                        //验证通过提交表单
                        var submitResult = Form.api.submit($(ret), function (data, ret) {
                            that.holdSubmit(false);
                            submitBtn.removeClass("disabled");
                            if (false === $(this).triggerHandler("success.form", [data, ret])) {
                                return false;
                            }
                            if (typeof success === 'function') {
                                if (false === success.call($(this), data, ret)) {
                                    return false;
                                }
                            }
                            //提示及关闭当前窗口
                            var msg = ret.hasOwnProperty("msg") && ret.msg !== "" ? ret.msg : __('Operation completed');
                            parent.Toastr.success(msg);
                            parent.$(".btn-refresh").trigger("click");
                            if (window.name) {
                                var index = parent.Layer.getFrameIndex(window.name);
                                parent.Layer.close(index);
                            }
                            return false;
                        }, function (data, ret) {
                            that.holdSubmit(false);
                            if (false === $(this).triggerHandler("error.form", [data, ret])) {
                                return false;
                            }
                            submitBtn.removeClass("disabled");
                            if (typeof error === 'function') {
                                if (false === error.call($(this), data, ret)) {
                                    return false;
                                }
                            }
                        }, submit);
                        //如果提交失败则释放锁定
                        if (!submitResult) {
                            that.holdSubmit(false);
                            submitBtn.removeClass("disabled");
                        }
                        return false;
                    }
                }, form.data("validator-options") || {}));

                //移除提交按钮的disabled类
                $(".layer-footer [type=submit],.fixed-footer [type=submit],.normal-footer [type=submit]", form).removeClass("disabled");
                //自定义关闭按钮事件
                form.on("click", ".layer-close", function () {
                    if (window.name) {
                        var index = parent.Layer.getFrameIndex(window.name);
                        parent.Layer.close(index);
                    }
                    return false;
                });
            },
            selectpicker: function (form) {
                //绑定select元素事件
                if ($(".selectpicker", form).length > 0) {
                    require(['bootstrap-select', 'bootstrap-select-lang'], function () {
                        $('.selectpicker', form).selectpicker();
                        $(form).on("reset", function () {
                            setTimeout(function () {
                                $('.selectpicker').selectpicker('refresh').trigger("change");
                            }, 1);
                        });
                    });
                }
            },
            selectpage: function (form) {
                //绑定selectpage元素事件
                if ($(".selectpage", form).length > 0) {
                    require(['selectpage'], function () {
                        $('.selectpage', form).selectPage({
                            eAjaxSuccess: function (data) {
                                data.list = typeof data.rows !== 'undefined' ? data.rows : (typeof data.list !== 'undefined' ? data.list : []);
                                data.totalRow = typeof data.total !== 'undefined' ? data.total : (typeof data.totalRow !== 'undefined' ? data.totalRow : data.list.length);
                                return data;
                            }
                        });
                    });
                    //给隐藏的元素添加上validate验证触发事件
                    $(document).on("change", ".sp_hidden", function () {
                        $(this).trigger("validate");
                    });
                    $(document).on("change", ".sp_input", function () {
                        $(this).closest(".sp_container").find(".sp_hidden").trigger("change");
                    });
                    $(form).on("reset", function () {
                        setTimeout(function () {
                            $(".selectpage", form).each(function () {
                                var selectpage = $(this).data("selectPageObject");
                                selectpage.elem.hidden.val($(this).val());
                                $(this).selectPageRefresh();
                            });
                        }, 1);
                    });
                }
            },
            cxselect: function (form) {
                //绑定cxselect元素事件
                if ($("[data-toggle='cxselect']", form).length > 0) {
                    require(['cxselect'], function () {
                        $.cxSelect.defaults.jsonName = 'name';
                        $.cxSelect.defaults.jsonValue = 'value';
                        $.cxSelect.defaults.jsonSpace = 'data';
                        $("[data-toggle='cxselect']", form).cxSelect();
                    });
                }
            },
            citypicker: function (form) {
                //绑定城市远程插件
                if ($("[data-toggle='city-picker']", form).length > 0) {
                    require(['citypicker'], function () {
                        $(form).on("reset", function () {
                            setTimeout(function () {
                                $("[data-toggle='city-picker']").citypicker('refresh');
                            }, 1);
                        });
                    });
                }
            },
            datetimepicker: function (form) {
                //绑定日期时间元素事件
                if ($(".datetimepicker", form).length > 0) {
                    require(['bootstrap-datetimepicker'], function () {
                        var options = {
                            format: 'YYYY-MM-DD HH:mm:ss',
                            icons: {
                                time: 'fa fa-clock-o',
                                date: 'fa fa-calendar',
                                up: 'fa fa-chevron-up',
                                down: 'fa fa-chevron-down',
                                previous: 'fa fa-chevron-left',
                                next: 'fa fa-chevron-right',
                                today: 'fa fa-history',
                                clear: 'fa fa-trash',
                                close: 'fa fa-remove'
                            },
                            showTodayButton: true,
                            showClose: true
                        };
                        $('.datetimepicker', form).parent().css('position', 'relative');
                        $('.datetimepicker', form).datetimepicker(options).on('dp.change', function (e) {
                            $(this, document).trigger("changed");
                        });
                    });
                }
            },
            daterangepicker: function (form) {
                //绑定日期时间元素事件
                if ($(".datetimerange", form).length > 0) {
                    require(['bootstrap-daterangepicker'], function () {
                        var ranges = {};
                        ranges[__('Today')] = [Moment().startOf('day'), Moment().endOf('day')];
                        ranges[__('Yesterday')] = [Moment().subtract(1, 'days').startOf('day'), Moment().subtract(1, 'days').endOf('day')];
                        ranges[__('Last 7 Days')] = [Moment().subtract(6, 'days').startOf('day'), Moment().endOf('day')];
                        ranges[__('Last 30 Days')] = [Moment().subtract(29, 'days').startOf('day'), Moment().endOf('day')];
                        ranges[__('This Month')] = [Moment().startOf('month'), Moment().endOf('month')];
                        ranges[__('Last Month')] = [Moment().subtract(1, 'month').startOf('month'), Moment().subtract(1, 'month').endOf('month')];
                        var options = {
                            timePicker: false,
                            autoUpdateInput: false,
                            timePickerSeconds: true,
                            timePicker24Hour: true,
                            autoApply: true,
                            locale: {
                                format: 'YYYY-MM-DD HH:mm:ss',
                                customRangeLabel: __("Custom Range"),
                                applyLabel: __("Apply"),
                                cancelLabel: __("Clear"),
                            },
                            ranges: ranges,
                        };
                        var origincallback = function (start, end) {
                            $(this.element).val(start.format(this.locale.format) + " - " + end.format(this.locale.format));
                            $(this.element).trigger('change');
                        };
                        $(".datetimerange", form).each(function () {
                            var callback = typeof $(this).data('callback') == 'function' ? $(this).data('callback') : origincallback;
                            $(this).on('apply.daterangepicker', function (ev, picker) {
                                callback.call(picker, picker.startDate, picker.endDate);
                            });
                            $(this).on('cancel.daterangepicker', function (ev, picker) {
                                $(this).val('').trigger('change');
                            });
                            $(this).daterangepicker($.extend(true, {}, options, $(this).data() || {}, $(this).data("daterangepicker-options") || {}));
                        });
                    });
                }
            },
            /**
             * 绑定上传事件
             * @param form
             * @deprecated Use faupload instead.
             */
            plupload: function (form) {
                Form.events.faupload(form);
            },
            /**
             * 绑定上传事件
             * @param form
             */
            faupload: function (form) {
                //绑定上传元素事件
                if ($(".plupload,.faupload", form).length > 0) {
                    Upload.api.upload($(".plupload,.faupload", form));
                }
            },
            faselect: function (form) {
                //绑定fachoose选择附件事件
                if ($(".faselect,.fachoose", form).length > 0) {
                    $(".faselect,.fachoose", form).off('click').on('click', function () {
                        var that = this;
                        var multiple = $(this).data("multiple") ? $(this).data("multiple") : false;
                        var mimetype = $(this).data("mimetype") ? $(this).data("mimetype") : '';
                        var admin_id = $(this).data("admin-id") ? $(this).data("admin-id") : '';
                        var user_id = $(this).data("user-id") ? $(this).data("user-id") : '';
                        mimetype = mimetype.replace(/\/\*/ig, '/');
                        var url = $(this).data("url") ? $(this).data("url") : (typeof Backend !== 'undefined' ? "general/attachment/select" : "user/attachment");
                        parent.Fast.api.open(url + "?element_id=" + $(this).attr("id") + "&multiple=" + multiple + "&mimetype=" + mimetype + "&admin_id=" + admin_id + "&user_id=" + user_id, __('Choose'), {
                            callback: function (data) {
                                var button = $(that);
                                var maxcount = $(button).data("maxcount");
                                var input_id = $(button).data("input-id") ? $(button).data("input-id") : "";
                                maxcount = typeof maxcount !== "undefined" ? maxcount : 0;
                                if (input_id && data.multiple) {
                                    var urlArr = [];
                                    var inputObj = $("#" + input_id);
                                    var value = $.trim(inputObj.val());
                                    if (value !== "") {
                                        urlArr.push(inputObj.val());
                                    }
                                    var nums = value === '' ? 0 : value.split(/\,/).length;
                                    var files = data.url !== "" ? data.url.split(/\,/) : [];
                                    $.each(files, function (i, j) {
                                        var url = Config.upload.fullmode ? Fast.api.cdnurl(j) : j;
                                        urlArr.push(url);
                                    });
                                    if (maxcount > 0) {
                                        var remains = maxcount - nums;
                                        if (files.length > remains) {
                                            Toastr.error(__('You can choose up to %d file%s', remains));
                                            return false;
                                        }
                                    }
                                    var result = urlArr.join(",");
                                    inputObj.val(result).trigger("change").trigger("validate");
                                } else if (input_id) {
                                    var url = Config.upload.fullmode ? Fast.api.cdnurl(data.url) : data.url;
                                    $("#" + input_id).val(url).trigger("change").trigger("validate");
                                }
                            }
                        });
                        return false;
                    });
                }
            },
            fieldlist: function (form) {
                //绑定fieldlist
                if ($(".fieldlist", form).length > 0) {
                    require(['dragsort', 'template'], function (undefined, Template) {
                        //刷新隐藏textarea的值
                        var refresh = function (container) {
                            var data = {};
                            var name = container.data("name");
                            var textarea = $("textarea[name='" + name + "']", form);
                            var template = container.data("template");
                            $.each($("input,select,textarea", container).serializeArray(), function (i, j) {
                                var reg = /\[(\w+)\]\[(\w+)\]$/g;
                                var match = reg.exec(j.name);
                                if (!match)
                                    return true;
                                match[1] = "x" + parseInt(match[1]);
                                if (typeof data[match[1]] == 'undefined') {
                                    data[match[1]] = {};
                                }
                                data[match[1]][match[2]] = j.value;
                            });
                            //使用数组保存
                            var usearray = container.data("usearray") || false;
                            //保留空数据
                            var keepempty = container.data("keepempty") || false;

                            var result = template || usearray ? [] : {};
                            var keys = Object.keys(Object.values(data)[0] || {});

                            var isassociative = !usearray && keys.indexOf("value") > -1 && (keys.length === 1 || (keys.length === 2 && keys.indexOf("key") > -1));
                            if(isassociative && keys.length ===2){
                                result = {};
                            }

                            $.each(data, function (i, j) {
                                if (j) {
                                    if (isassociative) {
                                        if (keys.length === 2) {
                                            if (j.key != '' || keepempty) {
                                                result['__PLACEHOLDKEY__' + j.key] = j.value;
                                            }
                                        } else {
                                            //一维数组
                                            result.push(j.value);
                                        }
                                    } else {
                                        result.push(j);
                                    }
                                }
                            });
                            textarea.val(JSON.stringify(result).replace(/__PLACEHOLDKEY__/g, ''));
                        };
                        //追加一行数据
                        var append = function (container, row, initial) {
                            var tagName = container.data("tag") || (container.is("table") ? "tr" : "dd");
                            var index = container.data("index");
                            var name = container.data("name");
                            var template = container.data("template");
                            var data = container.data();
                            index = index ? parseInt(index) : 0;
                            container.data("index", index + 1);
                            row = row ? row : {};
                            row = typeof row.key === 'undefined' || typeof row.value === 'undefined' ? {key: '', value: row} : row;
                            var options = container.data("fieldlist-options") || {};
                            var vars = {index: index, name: name, data: data, options: options, key: row.key, value: row.value, row: row.value};
                            var html = template ? Template(template, vars) : Template.render(Form.config.fieldlisttpl, vars);
                            var obj = $(html);
                            if ((options.deleteBtn === false || options.removeBtn === false) && initial)
                                obj.find(".btn-remove").remove();
                            if (options.dragsortBtn === false && initial)
                                obj.find(".btn-dragsort").remove();
                            if ((options.readonlyKey === true || options.disableKey === true) && initial) {
                                obj.find("input[name$='[key]']").prop("readonly", true);
                            }
                            obj.attr("fieldlist-item", true);
                            obj.insertAfter($(tagName + "[fieldlist-item]", container).length > 0 ? $(tagName + "[fieldlist-item]:last", container) : $(tagName + ":first", container));
                            if ($(".btn-append,.append", container).length > 0) {
                                //兼容旧版本事件
                                $(".btn-append,.append", container).trigger("fa.event.appendfieldlist", obj);
                            } else {
                                //新版本事件
                                container.trigger("fa.event.appendfieldlist", obj);
                            }
                            return obj;
                        };
                        var fieldlist = $(".fieldlist", form);
                        //表单重置
                        form.on("reset", function () {
                            setTimeout(function () {
                                fieldlist.trigger("fa.event.refreshfieldlist");
                            });
                        });
                        //监听文本框改变事件
                        $(document).on('change keyup changed', ".fieldlist input,.fieldlist textarea,.fieldlist select", function () {
                            var container = $(this).closest(".fieldlist");
                            refresh(container);
                        });
                        //追加控制(点击按钮)
                        fieldlist.on("click", ".btn-append,.append", function (e, row) {
                            var container = $(this).closest(".fieldlist");
                            append(container, row);
                            refresh(container);
                        });
                        //移除控制(点击按钮)
                        fieldlist.on("click", ".btn-remove", function () {
                            var container = $(this).closest(".fieldlist");
                            var tagName = container.data("tag") || (container.is("table") ? "tr" : "dd");
                            $(this).closest(tagName).remove();
                            refresh(container);
                        });
                        //追加控制(通过事件)
                        fieldlist.on("fa.event.appendtofieldlist", function (e, row) {
                            var container = $(this);
                            append(container, row);
                            refresh(container);
                        });
                        //根据textarea内容重新渲染
                        fieldlist.on("fa.event.refreshfieldlist", function () {
                            var container = $(this);
                            var textarea = $("textarea[name='" + container.data("name") + "']", form);
                            //先清空已有的数据
                            $("[fieldlist-item]", container).remove();
                            var json = {};
                            try {
                                var val = textarea.val().replace(/"(\d+)"\:/g, "\"__PLACEHOLDERKEY__$1\":");
                                json = JSON.parse(val);
                            } catch (e) {
                            }
                            $.each(json, function (i, j) {
                                append(container, {key: i.toString().replace("__PLACEHOLDERKEY__", ""), value: j}, true);
                            });
                        });
                        //拖拽排序
                        fieldlist.each(function () {
                            var container = $(this);
                            var tagName = container.data("tag") || (container.is("table") ? "tr" : "dd");
                            container.dragsort({
                                itemSelector: tagName,
                                dragSelector: ".btn-dragsort",
                                dragEnd: function () {
                                    refresh(container);
                                },
                                placeHolderTemplate: $("<" + tagName + "/>")
                            });
                            if (typeof container.data("options") === 'object' && container.data("options").appendBtn === false) {
                                $(".btn-append,.append", container).hide();
                            }
                            $("textarea[name='" + container.data("name") + "']", form).on("fa.event.refreshfieldlist", function () {
                                //兼容旧版本事件
                                $(this).closest(".fieldlist").trigger("fa.event.refreshfieldlist");
                            });
                        });
                        fieldlist.trigger("fa.event.refreshfieldlist");
                    });
                }
            },
            switcher: function (form) {
                form.on("click", "[data-toggle='switcher']", function () {
                    if ($(this).hasClass("disabled")) {
                        return false;
                    }
                    var switcher = $.proxy(function () {
                        var input = $(this).prev("input");
                        input = $(this).data("input-id") ? $("#" + $(this).data("input-id")) : input;
                        if (input.length > 0) {
                            var yes = $(this).data("yes");
                            var no = $(this).data("no");
                            if (input.val() == yes) {
                                input.val(no);
                                $("i", this).addClass("fa-flip-horizontal text-gray");
                            } else {
                                input.val(yes);
                                $("i", this).removeClass("fa-flip-horizontal text-gray");
                            }
                            input.trigger('change');
                        }
                    }, this);
                    if (typeof $(this).data("confirm") !== 'undefined') {
                        Layer.confirm($(this).data("confirm"), function (index) {
                            switcher();
                            Layer.close(index);
                        });
                    } else {
                        switcher();
                    }

                    return false;
                });
            },
            bindevent: function (form) {

            },
            slider: function (form) {
                if ($("[data-role='slider'],input.slider", form).length > 0) {
                    require(['bootstrap-slider'], function () {
                        $("[data-role='slider'],input.slider").removeClass('hidden').css('width', function (index, value) {
                            return $(this).parents('.form-control').width();
                        }).slider().on('slide', function (ev) {
                            var data = $(this).data();
                            if (typeof data.unit !== 'undefined') {
                                $(this).parents('.form-control').siblings('.value').text(ev.value + data.unit);
                            }
                        });
                    });
                }
            },
            tagsinput: function (form) {
                if ($("[data-role='tagsinput']", form).length > 0) {
                    require(['tagsinput', 'autocomplete'], function () {
                        $("[data-role='tagsinput']").tagsinput();
                        form.on("reset", function () {
                            setTimeout(function () {
                                $("[data-role='tagsinput']").tagsinput('reset');
                            }, 0);
                        });
                    });
                }
            },
            autocomplete: function (form) {
                if ($("[data-role='autocomplete']", form).length > 0) {
                    require(['autocomplete'], function () {
                        $("[data-role='autocomplete']").autocomplete({
                            onSelect: function () {
                                $(this).trigger('change').trigger('validate');
                            }
                        });
                    });
                }
            },
            favisible: function (form) {
                if ($("[data-favisible]", form).length == 0) {
                    return;
                }
                var checkCondition = function (condition) {
                    var conditionArr = condition.split(/&&/);
                    var success = 0;
                    var baseregex = /^([a-z0-9\_]+)([>|<|=|\!]=?)(.*)$/i, strregex = /^('|")(.*)('|")$/, regregex = /^regex:(.*)$/;
                    // @formatter:off
                    var operator_result = {
                        '>': function (a, b) {
                            return a > b;
                        },
                        '>=': function (a, b) {
                            return a >= b;
                        },
                        '<': function (a, b) {
                            return a < b;
                        },
                        '<=': function (a, b) {
                            return a <= b;
                        },
                        '==': function (a, b) {
                            return a == b;
                        },
                        '!=': function (a, b) {
                            return a != b;
                        },
                        'in': function (a, b) {
                            return b.split(/\,/).indexOf(a) > -1;
                        },
                        'regex': function (a, b) {
                            var regParts = b.match(/^\/(.*?)\/([gim]*)$/);
                            var regexp = regParts ? new RegExp(regParts[1], regParts[2]) : new RegExp(b);
                            return regexp.test(a);
                        }
                    };
                    // @formatter:on
                    var dataArr = form.serializeArray(), dataObj = {}, fieldName, fieldValue;
                    $(dataArr).each(function (i, field) {
                        fieldName = field.name;
                        fieldValue = field.value;
                        fieldName = fieldName.substr(-2) === '[]' ? fieldName.substr(0, fieldName.length - 2) : fieldName;
                        dataObj[fieldName] = typeof dataObj[fieldName] !== 'undefined' ? [dataObj[fieldName], fieldValue].join(',') : fieldValue;
                    });

                    $.each(conditionArr, function (i, item) {
                        var basematches = baseregex.exec(item);
                        if (basematches) {
                            var name = basematches[1], operator = basematches[2], value = basematches[3].toString();
                            if (operator === '=') {
                                var strmatches = strregex.exec(value);
                                operator = strmatches ? '==' : 'in';
                                value = strmatches ? strmatches[2] : value;
                            }
                            var regmatches = regregex.exec(value);
                            if (regmatches) {
                                operator = 'regex';
                                value = regmatches[1];
                            }
                            var chkname = "row[" + name + "]";
                            if (typeof dataObj[chkname] === 'undefined') {
                                return false;
                            }
                            var objvalue = dataObj[chkname];
                            if ($.isArray(objvalue)) {
                                objvalue = dataObj[chkname].join(",");
                            }
                            if (['>', '>=', '<', '<='].indexOf(operator) > -1) {
                                objvalue = parseFloat(objvalue);
                                value = parseFloat(value);
                            }
                            var result = operator_result[operator](objvalue, value);
                            success += (result ? 1 : 0);
                        }
                    });
                    return success === conditionArr.length;
                };
                form.on("keyup change click configchange", "input,textarea,select", function () {
                    $("[data-favisible][data-favisible!='']", form).each(function () {
                        var visible = $(this).data("favisible");
                        var groupArr = visible ? visible.toString().split(/\|\|/) : [];
                        var success = 0;
                        $.each(groupArr, function (i, j) {
                            if (checkCondition(j)) {
                                success++;
                            }
                        });
                        if (success > 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                });

                //追加上忽略元素
                setTimeout(function () {
                    var validator = form.data('validator');
                    if (validator) {
                        validator.options.ignore += ((validator.options.ignore ? ',' : '') + '.hidden[data-favisible] :hidden,.hidden[data-favisible]:hidden');
                    }
                }, 0);

                $("input,textarea,select", form).trigger("configchange");
            }
        },
        api: {
            submit: function (form, success, error, submit) {
                if (form.length === 0) {
                    Toastr.error("表单未初始化完成,无法提交");
                    return false;
                }
                if (typeof submit === 'function') {
                    if (false === submit.call(form, success, error)) {
                        return false;
                    }
                }
                var type = form.attr("method") ? form.attr("method").toUpperCase() : 'GET';
                type = type && (type === 'GET' || type === 'POST') ? type : 'GET';
                url = form.attr("action");
                url = url ? url : location.href;
                //修复当存在多选项元素时提交的BUG
                var params = {};
                var multipleList = $("[name$='[]']", form);
                if (multipleList.length > 0) {
                    var postFields = form.serializeArray().map(function (obj) {
                        return $(obj).prop("name");
                    });
                    $.each(multipleList, function (i, j) {
                        if (postFields.indexOf($(this).prop("name")) < 0) {
                            params[$(this).prop("name")] = '';
                        }
                    });
                }
                //调用Ajax请求方法
                Fast.api.ajax({
                    type: type,
                    url: url,
                    data: form.serialize() + (Object.keys(params).length > 0 ? '&' + $.param(params) : ''),
                    dataType: 'json',
                    complete: function (xhr) {
                        var token = xhr.getResponseHeader('__token__');
                        if (token) {
                            $("input[name='__token__']").val(token);
                        }
                    }
                }, function (data, ret) {
                    $('.form-group', form).removeClass('has-feedback has-success has-error');
                    if (data && typeof data === 'object') {
                        //刷新客户端token
                        if (typeof data.token !== 'undefined') {
                            $("input[name='__token__']").val(data.token);
                        }
                        //调用客户端事件
                        if (typeof data.callback !== 'undefined' && typeof data.callback === 'function') {
                            data.callback.call(form, data);
                        }
                    }
                    if (typeof success === 'function') {
                        if (false === success.call(form, data, ret)) {
                            return false;
                        }
                    }
                }, function (data, ret) {
                    if (data && typeof data === 'object' && typeof data.token !== 'undefined') {
                        $("input[name='__token__']").val(data.token);
                    }
                    if (typeof error === 'function') {
                        if (false === error.call(form, data, ret)) {
                            return false;
                        }
                    }
                });
                return true;
            },
            bindevent: function (form, success, error, submit) {

                form = typeof form === 'object' ? form : $(form);

                var events = Form.events;

                events.bindevent(form);

                events.validator(form, success, error, submit);

                events.selectpicker(form);

                events.daterangepicker(form);

                events.selectpage(form);

                events.cxselect(form);

                events.citypicker(form);

                events.datetimepicker(form);

                events.faupload(form);

                events.faselect(form);

                events.fieldlist(form);

                events.slider(form);

                events.switcher(form);

                events.tagsinput(form);

                events.autocomplete(form);

                events.favisible(form);
            },
            custom: {}
        },
    };
    return Form;
});
