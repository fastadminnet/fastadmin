/**
 * FastAdmin通用搜索
 *
 * @author: pppscn <35696959@qq.com>
 * @update 2017-05-07 <https://gitee.com/pp/fastadmin>
 *
 * @author: Karson <karson@fastadmin.net>
 * @update 2018-04-05 <https://gitee.com/karson/fastadmin>
 */

!function ($) {
    'use strict';

    var ColumnsForSearch = [];

    var sprintf = $.fn.bootstrapTable.utils.sprintf;

    var initCommonSearch = function (pColumns, that) {
        var vFormCommon = createFormCommon(pColumns, that);

        var vModal = sprintf("<div class=\"commonsearch-table %s\">", that.options.searchFormVisible ? "" : "hidden");
        vModal += vFormCommon;
        vModal += "</div>";
        that.$container.prepend($(vModal));
        that.$commonsearch = $(".commonsearch-table", that.$container);
        var form = $("form.form-commonsearch", that.$commonsearch);

        require(['form'], function (Form) {
            Form.api.bindevent(form);
            form.validator("destroy");
        });

        // 表单提交
        form.on("submit", function (event) {
            event.preventDefault();
            that.onCommonSearch();
            return false;
        });

        // 重置搜索
        form.on("click", "button[type=reset]", function (event) {
            form[0].reset();

            setTimeout(function () {
                that.onCommonSearch();
            }, 1);
        });

    };

    var createFormCommon = function (pColumns, that) {
        // 如果有使用模板则直接返回模板的内容
        if (that.options.searchFormTemplate) {
            return Template(that.options.searchFormTemplate, {columns: pColumns, table: that});
        }
        var htmlForm = [];
        htmlForm.push(sprintf('<form class="form-horizontal form-commonsearch" novalidate method="post" action="%s" >', that.options.actionForm));
        htmlForm.push('<fieldset>');
        if (that.options.titleForm.length > 0)
            htmlForm.push(sprintf("<legend>%s</legend>", that.options.titleForm));
        htmlForm.push('<div class="row">');
        for (var i in pColumns) {
            var vObjCol = pColumns[i];
            if (!vObjCol.checkbox && !vObjCol.radio && vObjCol.field && vObjCol.field !== 'operate' && vObjCol.searchable && vObjCol.operate !== false) {
                var query = Fast.api.query(vObjCol.field);
                var operate = Fast.api.query(vObjCol.field + "-operate");

                var renderDefault = that.options.renderDefault && (typeof vObjCol.renderDefault == 'undefined' || vObjCol.renderDefault);
                vObjCol.defaultValue = renderDefault && query ? query : (typeof vObjCol.defaultValue === 'undefined' ? '' : vObjCol.defaultValue);
                vObjCol.operate = renderDefault && operate ? operate : (typeof vObjCol.operate === 'undefined' ? '=' : vObjCol.operate);
                ColumnsForSearch.push(vObjCol);

                htmlForm.push('<div class="form-group col-xs-12 col-sm-6 col-md-4 col-lg-3">');
                htmlForm.push(sprintf('<label for="%s" class="control-label col-xs-4">%s</label>', vObjCol.field, vObjCol.title));
                htmlForm.push('<div class="col-xs-8">');

                vObjCol.operate = vObjCol.operate ? vObjCol.operate.toUpperCase() : '=';
                htmlForm.push(sprintf('<input type="hidden" class="form-control operate" name="%s-operate" data-name="%s" value="%s" readonly>', vObjCol.field, vObjCol.field, vObjCol.operate));

                var addClass = typeof vObjCol.addClass === 'undefined' ? (typeof vObjCol.addclass === 'undefined' ? 'form-control' : 'form-control ' + vObjCol.addclass) : 'form-control ' + vObjCol.addClass;
                var extend = typeof vObjCol.extend === 'undefined' ? '' : vObjCol.extend;
                var style = typeof vObjCol.style === 'undefined' ? '' : sprintf('style="%s"', vObjCol.style);
                extend = typeof vObjCol.data !== 'undefined' && extend == '' ? vObjCol.data : extend;
                extend = typeof vObjCol.autocomplete !== 'undefined' ? extend + ' autocomplete="' + (vObjCol.autocomplete === false || vObjCol.autocomplete === 'off' ? 'off' : 'on') + '"' : extend;
                if (vObjCol.searchList) {
                    if (typeof vObjCol.searchList === 'function') {
                        htmlForm.push(vObjCol.searchList.call(this, vObjCol));
                    } else {
                        var optionList = [sprintf('<option value="">%s</option>', that.options.formatCommonChoose())];
                        if (typeof vObjCol.searchList === 'object' && typeof vObjCol.searchList.then === 'function') {
                            (function (vObjCol, that) {
                                $.when(vObjCol.searchList).done(function (ret) {
                                    var searchList = [];
                                    if (ret.data && ret.data.searchlist && $.isArray(ret.data.searchlist)) {
                                        searchList = ret.data.searchlist;
                                    } else if (ret.constructor === Array || ret.constructor === Object) {
                                        searchList = ret;
                                    }
                                    var optionList = createOptionList(searchList, vObjCol, that);
                                    $("form.form-commonsearch select[name='" + vObjCol.field + "']", that.$container).html(optionList.join('')).trigger("change");
                                });
                            })(vObjCol, that);
                        } else {
                            optionList = createOptionList(vObjCol.searchList, vObjCol, that);
                        }
                        htmlForm.push(sprintf('<select class="%s" name="%s" %s %s>%s</select>', addClass, vObjCol.field, style, extend, optionList.join('')));
                    }
                } else {
                    var placeholder = typeof vObjCol.placeholder === 'undefined' ? vObjCol.title : vObjCol.placeholder;
                    var type = typeof vObjCol.type === 'undefined' ? 'text' : vObjCol.type;
                    var defaultValue = typeof vObjCol.defaultValue === 'undefined' ? '' : vObjCol.defaultValue;
                    if (/BETWEEN$/.test(vObjCol.operate)) {
                        var defaultValueArr = defaultValue.toString().match(/\|/) ? defaultValue.split('|') : ['', ''];
                        var placeholderArr = placeholder.toString().match(/\|/) ? placeholder.split('|') : [placeholder, placeholder];
                        htmlForm.push('<div class="row row-between">');
                        htmlForm.push(sprintf('<div class="col-xs-6"><input type="%s" class="%s" name="%s" value="%s" placeholder="%s" id="%s-min" data-index="%s" %s %s></div>', type, addClass, vObjCol.field, defaultValueArr[0], placeholderArr[0], vObjCol.field, i, style, extend));
                        htmlForm.push(sprintf('<div class="col-xs-6"><input type="%s" class="%s" name="%s" value="%s" placeholder="%s" id="%s-max" data-index="%s" %s %s></div>', type, addClass, vObjCol.field, defaultValueArr[1], placeholderArr[1], vObjCol.field, i, style, extend));
                        htmlForm.push('</div>');
                    } else {
                        htmlForm.push(sprintf('<input type="%s" class="%s" name="%s" value="%s" placeholder="%s" id="%s" data-index="%s" %s %s>', type, addClass, vObjCol.field, defaultValue, placeholder, vObjCol.field, i, style, extend));
                    }
                }

                htmlForm.push('</div>');
                htmlForm.push('</div>');
            }
        }
        htmlForm.push('<div class="form-group col-xs-12 col-sm-6 col-md-4 col-lg-3">');
        htmlForm.push(createFormBtn(that).join(''));
        htmlForm.push('</div>');
        htmlForm.push('</div>');
        htmlForm.push('</fieldset>');
        htmlForm.push('</form>');

        return htmlForm.join('');
    };

    var createFormBtn = function (that) {
        var htmlBtn = [];
        var searchSubmit = that.options.formatCommonSubmitButton();
        var searchReset = that.options.formatCommonResetButton();
        htmlBtn.push('<div class="col-sm-8 col-xs-offset-4">');
        htmlBtn.push(sprintf('<button type="submit" class="btn btn-success" formnovalidate>%s</button> ', searchSubmit));
        htmlBtn.push(sprintf('<button type="reset" class="btn btn-default" >%s</button> ', searchReset));
        htmlBtn.push('</div>');
        return htmlBtn;
    };

    var createOptionList = function (searchList, vObjCol, that) {
        var isArray = searchList.constructor === Array;
        var optionList = [];
        optionList.push(sprintf('<option value="">%s</option>', that.options.formatCommonChoose()));
        $.each(searchList, function (key, value) {
            if (value.constructor === Object) {
                key = value.id;
                value = value.name;
            } else {
                key = isArray ? value : key;
            }
            optionList.push(sprintf("<option value='" + Fast.api.escape(key) + "' %s>" + Fast.api.escape(value) + "</option>", key == vObjCol.defaultValue ? 'selected' : ''));
        });
        return optionList;
    };

    var isSearchAvailble = function (that) {

        //只支持服务端搜索
        if (!that.options.commonSearch || that.options.sidePagination != 'server' || !that.options.url) {
            return false;
        }

        return true;
    };

    var getSearchQuery = function (that, removeempty) {
        var op = {};
        var filter = {};
        var value = '';
        $("form.form-commonsearch .operate", that.$commonsearch).each(function (i) {
            var name = $(this).data("name");
            var sym = $(this).is("select") ? $("option:selected", this).val() : $(this).val().toUpperCase();
            var obj = $("[name='" + name + "']", that.$commonsearch);
            if (obj.length == 0)
                return true;
            var vObjCol = ColumnsForSearch[i];
            var process = !that.options.searchFormTemplate && vObjCol && typeof vObjCol.process == 'function' ? vObjCol.process : null;
            if (obj.length > 1) {
                if (/BETWEEN$/.test(sym)) {
                    var value_begin = $.trim($("[name='" + name + "']:first", that.$commonsearch).val()),
                        value_end = $.trim($("[name='" + name + "']:last", that.$commonsearch).val());
                    if (value_begin.length || value_end.length) {
                        if (process) {
                            value_begin = process(value_begin, 'begin');
                            value_end = process(value_end, 'end');
                        }
                        value = value_begin + ',' + value_end;
                    } else {
                        value = '';
                    }
                    //如果是时间筛选，将operate置为RANGE
                    if ($("[name='" + name + "']:first", that.$commonsearch).hasClass("datetimepicker")) {
                        sym = 'RANGE';
                    }
                } else {
                    value = $("[name='" + name + "']:checked", that.$commonsearch).val();
                    value = process ? process(value) : value;
                }
            } else {
                value = process ? process(obj.val()) : obj.val();
            }
            if (removeempty && (value === '' || value == null || ($.isArray(value) && value.length === 0)) && !sym.match(/null/i)) {
                return true;
            }

            op[name] = sym;
            filter[name] = value;
        });
        return {op: op, filter: filter};
    };

    var getQueryParams = function (params, searchQuery, removeempty) {
        params.filter = typeof params.filter === 'Object' ? params.filter : (params.filter ? JSON.parse(params.filter) : {});
        params.op = typeof params.op === 'Object' ? params.op : (params.op ? JSON.parse(params.op) : {});

        params.filter = $.extend({}, params.filter, searchQuery.filter);
        params.op = $.extend({}, params.op, searchQuery.op);
        //移除empty的值
        if (removeempty) {
            $.each(params.filter, function (i, j) {
                if ((j == '' || j == null || ($.isArray(j) && j.length == 0)) && !params.op[i].match(/null/i)) {
                    delete params.filter[i];
                    delete params.op[i];
                }
            });
        }
        params.filter = JSON.stringify(params.filter);
        params.op = JSON.stringify(params.op);
        return params;
    };

    $.extend($.fn.bootstrapTable.defaults, {
        commonSearch: false,
        titleForm: "Common search",
        actionForm: "",
        searchFormTemplate: "",
        searchFormVisible: true,
        searchClass: 'searchit',
        showSearch: true,
        renderDefault: true,
        onCommonSearch: function (field, text) {
            return false;
        },
        onPostCommonSearch: function (table) {
            return false;
        }
    });

    $.extend($.fn.bootstrapTable.defaults.icons, {
        commonSearchIcon: 'glyphicon-search'
    });

    $.extend($.fn.bootstrapTable.Constructor.EVENTS, {
        'common-search.bs.table': 'onCommonSearch',
        'post-common-search.bs.table': 'onPostCommonSearch'
    });
    $.extend($.fn.bootstrapTable.locales[$.fn.bootstrapTable.defaults.locale], {
        formatCommonSearch: function () {
            return "Common search";
        },
        formatCommonSubmitButton: function () {
            return "Search";
        },
        formatCommonResetButton: function () {
            return "Reset";
        },
        formatCommonCloseButton: function () {
            return "Close";
        },
        formatCommonChoose: function () {
            return "Choose";
        }
    });

    $.extend($.fn.bootstrapTable.defaults, $.fn.bootstrapTable.locales);

    var BootstrapTable = $.fn.bootstrapTable.Constructor,
        _initHeader = BootstrapTable.prototype.initHeader,
        _initToolbar = BootstrapTable.prototype.initToolbar,
        _load = BootstrapTable.prototype.load,
        _initSearch = BootstrapTable.prototype.initSearch;

    BootstrapTable.prototype.initHeader = function () {
        _initHeader.apply(this, Array.prototype.slice.apply(arguments));
        this.$header.find('th[data-field]').each(function (i) {
            var column = $(this).data();
            if (typeof column['width'] !== 'undefined' && column['width'].toString().indexOf("%") === -1) {
                $(".th-inner", this).outerWidth(column['width']);
                $(this).css("max-width", column['width']);
            }
        });
        this.options.stateField = this.header.stateField;
    };
    BootstrapTable.prototype.initToolbar = function () {
        _initToolbar.apply(this, Array.prototype.slice.apply(arguments));

        if (!isSearchAvailble(this)) {
            return;
        }

        var that = this,
            html = [];
        if (that.options.showSearch) {
            html.push(sprintf('<div class="columns-%s pull-%s" style="margin-top:10px;margin-bottom:10px;">', this.options.buttonsAlign, this.options.buttonsAlign));
            html.push(sprintf('<button class="btn btn-default%s' + '" type="button" name="commonSearch" title="%s">', that.options.iconSize === undefined ? '' : ' btn-' + that.options.iconSize, that.options.formatCommonSearch()));
            html.push(sprintf('<i class="%s %s"></i>', that.options.iconsPrefix, that.options.icons.commonSearchIcon))
            html.push('</button></div>');
        }
        if (that.$toolbar.find(".pull-right").length > 0) {
            $(html.join('')).insertBefore(that.$toolbar.find(".pull-right:first"));
        } else {
            that.$toolbar.append(html.join(''));
        }

        initCommonSearch(that.columns, that);

        that.$toolbar.find('button[name="commonSearch"]')
            .off('click').on('click', function () {
            that.$commonsearch.toggleClass("hidden");
            return;
        });

        that.$container.on("click", "." + that.options.searchClass, function () {
            var value = $(this).data("value");
            var field = $(this).data("field");
            var ul = that.$container.closest(".panel-intro").find("ul[data-field='" + field + "']");
            if (ul.length > 0) {
                $('li a[data-value="' + value + '"][data-toggle="tab"]', ul).trigger('click');
                return;
            }
            var obj = $("form [name='" + field + "']", that.$commonsearch);
            if (obj.length > 0) {
                if (obj.is("select")) {
                    $("option[value='" + value + "']", obj).prop("selected", true);
                } else if (obj.length > 1) {
                    $("form [name='" + field + "'][value='" + value + "']", that.$commonsearch).prop("checked", true);
                } else {
                    obj.val(value + "");
                }
                obj.trigger("change");
                $("form", that.$commonsearch).trigger("submit");
            }
        });
        var queryParams = that.options.queryParams;
        //匹配默认搜索值
        this.options.queryParams = function (params) {
            return queryParams(getQueryParams(params, getSearchQuery(that, true)));
        };
        this.trigger('post-common-search', that);

    };

    BootstrapTable.prototype.onCommonSearch = function () {
        var searchQuery = getSearchQuery(this);
        this.trigger('common-search', this, searchQuery);
        this.options.pageNumber = 1;
        //this.options.pageSize = $.fn.bootstrapTable.defaults.pageSize;
        this.refresh({});
    };

    BootstrapTable.prototype.load = function (data) {
        _load.apply(this, Array.prototype.slice.apply(arguments));

        if (!isSearchAvailble(this)) {
            return;
        }
    };

    BootstrapTable.prototype.initSearch = function () {
        _initSearch.apply(this, Array.prototype.slice.apply(arguments));

        if (!isSearchAvailble(this)) {
            return;
        }

        var that = this;
        var fp = $.isEmptyObject(this.filterColumnsPartial) ? null : this.filterColumnsPartial;
        this.data = fp ? $.grep(this.data, function (item, i) {
            for (var key in fp) {
                var fval = fp[key].toLowerCase();
                var value = item[key];
                value = $.fn.bootstrapTable.utils.calculateObjectValue(that.header,
                    that.header.formatters[$.inArray(key, that.header.fields)],
                    [value, item, i], value);

                if (!($.inArray(key, that.header.fields) !== -1 &&
                    (typeof value === 'string' || typeof value === 'number') &&
                    (value + '').toLowerCase().indexOf(fval) !== -1)) {
                    return false;
                }
            }
            return true;
        }) : this.data;
    };
}(jQuery);
