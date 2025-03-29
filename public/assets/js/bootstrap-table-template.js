/**
 * 将BootstrapTable的行使用自定义的模板来渲染
 * 
 * @author: karson
 * @version: v0.0.1
 *
 * @update 2017-06-24 <http://github.com/fastadminnet/fastadmin>
 */

!function ($) {
    'use strict';

    $.extend($.fn.bootstrapTable.defaults, {
        //是否启用模板渲染
        templateView: false,
        //数据格式化的模板ID或格式函数
        templateFormatter: "itemtpl",
        //添加的父类的class
        templateParentClass: "row row-flex",
        //向table添加的class
        templateTableClass: "table-template",

    });

    var BootstrapTable = $.fn.bootstrapTable.Constructor,
            _initContainer = BootstrapTable.prototype.initContainer,
            _initBody = BootstrapTable.prototype.initBody,
            _initRow = BootstrapTable.prototype.initRow;

    BootstrapTable.prototype.initContainer = function () {
        _initContainer.apply(this, Array.prototype.slice.apply(arguments));
        var that = this;
        if (!that.options.templateView) {
            return;
        }
        that.options.cardView = true;

    };

    BootstrapTable.prototype.initBody = function () {
        var that = this;
        $.extend(that.options, {
            showHeader: !that.options.templateView ? $.fn.bootstrapTable.defaults.showHeader : false,
            showFooter: !that.options.templateView ? $.fn.bootstrapTable.defaults.showFooter : false,
        });
        $(that.$el).toggleClass(that.options.templateTableClass, that.options.templateView);

        _initBody.apply(this, Array.prototype.slice.apply(arguments));

        if (!that.options.templateView) {
            return;
        } else {
            //由于Bootstrap是基于Table的，添加一个父类容器
            $("> *:not(.no-records-found)", that.$body).wrapAll($("<div />").addClass(that.options.templateParentClass));
        }
    };

    BootstrapTable.prototype.initRow = function (item, i, data, parentDom) {
        var that = this;
        //如果未启用则使用原生的initRow方法
        if (!that.options.templateView) {
            return _initRow.apply(that, Array.prototype.slice.apply(arguments));
        }
        var $content = '';
        if (typeof that.options.templateFormatter === 'function') {
            $content = that.options.templateFormatter.call(that, item, i, data);
        } else {
            var Template = require('template');
            $content = Template(that.options.templateFormatter, {item: item, i: i, data: data});
        }
        return $content;
    };

}(jQuery);
