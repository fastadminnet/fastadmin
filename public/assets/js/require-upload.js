define(['jquery', 'bootstrap', 'dropzone', 'template'], function ($, undefined, Dropzone, Template) {
    var Upload = {
            list: {},
            options: {},
            config: {
                container: document.body,
                classname: '.plupload:not([initialized]),.faupload:not([initialized])',
                previewtpl: '<li class="col-xs-3"><a href="<%=fullurl%>" data-url="<%=url%>" target="_blank" class="thumbnail"><img src="<%=fullurl%>" onerror="this.src=\'' + Fast.api.fixurl("ajax/icon") + '?suffix=<%=suffix%>\';this.onerror=null;" class="img-responsive"></a><a href="javascript:;" class="btn btn-danger btn-xs btn-trash"><i class="fa fa-trash"></i></a></li>',
            },
            events: {
                //初始化
                onInit: function () {

                },
                //上传成功的回调
                onUploadSuccess: function (up, ret, file) {
                    var button = up.element;
                    var onUploadSuccess = up.options.onUploadSuccess;
                    var data = typeof ret.data !== 'undefined' ? ret.data : null;
                    //上传成功后回调
                    if (button) {
                        //如果有文本框则填充
                        var input_id = $(button).data("input-id") ? $(button).data("input-id") : "";
                        if (input_id) {
                            var urlArr = [];
                            var inputObj = $("#" + input_id);
                            if ($(button).data("multiple") && inputObj.val() !== "") {
                                urlArr.push(inputObj.val());
                            }
                            var url = Config.upload.fullmode ? (data.fullurl ? data.fullurl : Fast.api.cdnurl(data.url)) : data.url;
                            urlArr.push(url);
                            inputObj.val(urlArr.join(",")).trigger("change", [data]).trigger("validate");
                        }
                        //如果有回调函数
                        var onDomUploadSuccess = $(button).data("upload-success");
                        if (onDomUploadSuccess) {
                            if (typeof onDomUploadSuccess !== 'function' && typeof Upload.api.custom[onDomUploadSuccess] === 'function') {
                                onDomUploadSuccess = Upload.api.custom[onDomUploadSuccess];
                            }
                            if (typeof onDomUploadSuccess === 'function') {
                                var result = onDomUploadSuccess.call(button, data, ret, up, file);
                                if (result === false)
                                    return;
                            }
                        }
                    }

                    if (typeof onUploadSuccess === 'function') {
                        var result = onUploadSuccess.call(button, data, ret, up, file);
                        if (result === false)
                            return;
                    }
                },
                //上传错误的回调
                onUploadError: function (up, ret, file) {
                    var button = up.element;
                    var onUploadError = up.options.onUploadError;
                    var data = typeof ret.data !== 'undefined' ? ret.data : null;
                    if (button) {
                        var onDomUploadError = $(button).data("upload-error");
                        if (onDomUploadError) {
                            if (typeof onDomUploadError !== 'function' && typeof Upload.api.custom[onDomUploadError] === 'function') {
                                onDomUploadError = Upload.api.custom[onDomUploadError];
                            }
                            if (typeof onDomUploadError === 'function') {
                                var result = onDomUploadError.call(button, data, ret, up, file);
                                if (result === false)
                                    return;
                            }
                        }
                    }

                    if (typeof onUploadError === 'function') {
                        var result = onUploadError.call(button, data, ret, up, file);
                        if (result === false) {
                            return;
                        }
                    }
                    Toastr.error(ret.msg.toString().replace(/(<([^>]+)>)/gi, "") + "(code:" + ret.code + ")");
                },
                //服务器响应数据后
                onUploadResponse: function (response, up, file) {
                    try {
                        var ret = typeof response === 'object' ? response : JSON.parse(response);
                        if (!ret.hasOwnProperty('code')) {
                            $.extend(ret, {code: -2, msg: response, data: null});
                        }
                    } catch (e) {
                        var ret = {code: -1, msg: e.message, data: null};
                    }
                    return ret;
                },
                //上传全部结束后
                onUploadComplete: function (up, files) {
                    var button = up.element;
                    var onUploadComplete = up.options.onUploadComplete;
                    if (button) {
                        var onDomUploadComplete = $(button).data("upload-complete");
                        if (onDomUploadComplete) {
                            if (typeof onDomUploadComplete !== 'function' && typeof Upload.api.custom[onDomUploadComplete] === 'function') {
                                onDomUploadComplete = Upload.api.custom[onDomUploadComplete];
                            }
                            if (typeof onDomUploadComplete === 'function') {
                                var result = onDomUploadComplete.call(button, files);
                                if (result === false)
                                    return;
                            }
                        }
                    }

                    if (typeof onUploadComplete === 'function') {
                        var result = onUploadComplete.call(button, files);
                        if (result === false) {
                            return;
                        }
                    }
                }
            },
            api: {
                //上传接口
                upload: function (element, onUploadSuccess, onUploadError, onUploadComplete) {
                    element = typeof element === 'undefined' ? Upload.config.classname : element;
                    $(element, Upload.config.container).each(function () {
                        if ($(this).attr("initialized")) {
                            return true;
                        }
                        $(this).attr("initialized", true);
                        var that = this;
                        var id = $(this).prop("id") || $(this).prop("name") || Dropzone.uuidv4();
                        var url = $(this).data("url");
                        var maxsize = $(this).data("maxsize");
                        var maxcount = $(this).data("maxcount");
                        var mimetype = $(this).data("mimetype");
                        var multipart = $(this).data("multipart");
                        var multiple = $(this).data("multiple");

                        //填充ID
                        var input_id = $(that).data("input-id") ? $(that).data("input-id") : "";
                        //预览ID
                        var preview_id = $(that).data("preview-id") ? $(that).data("preview-id") : "";

                        //上传URL
                        url = url ? url : Config.upload.uploadurl;
                        url = Fast.api.fixurl(url);
                        var chunking = false, chunkSize = Config.upload.chunksize || 2097152, timeout = Config.upload.timeout || 600000;

                        //最大可上传文件大小
                        maxsize = typeof maxsize !== "undefined" ? maxsize : Config.upload.maxsize;
                        //文件类型
                        mimetype = typeof mimetype !== "undefined" ? mimetype : Config.upload.mimetype;
                        //请求的表单参数
                        multipart = typeof multipart !== "undefined" ? multipart : Config.upload.multipart;
                        //是否支持批量上传
                        multiple = typeof multiple !== "undefined" ? multiple : Config.upload.multiple;
                        //后缀特殊处理
                        mimetype = mimetype.split(",").map(function (k) {
                            return k.indexOf("/") > -1 ? k : (!k || k === "*" || k.charAt(0) === "." ? k : "." + k);
                        }).join(",");
                        mimetype = mimetype === '*' ? null : mimetype;

                        //最大文件限制转换成mb
                        var maxFilesize = (function (maxsize) {
                            var matches = maxsize.toString().match(/^([0-9\.]+)(\w+)$/);
                            var size = matches ? parseFloat(matches[1]) : parseFloat(maxsize),
                                unit = matches ? matches[2].toLowerCase() : 'b';
                            var unitDict = {'b': 0, 'k': 1, 'kb': 1, 'm': 2, 'mb': 2, 'gb': 3, 'g': 3, 'tb': 4, 't': 4};
                            var y = typeof unitDict[unit] !== 'undefined' ? unitDict[unit] : 0;
                            var bytes = size * Math.pow(1024, y);
                            return bytes / Math.pow(1024, 2);
                        }(maxsize));

                        var options = $(this).data() || {};
                        options = $.extend(true, {}, options, $(this).data("upload-options") || {});
                        delete options.success;
                        delete options.url;
                        multipart = $.isArray(multipart) ? {} : multipart;
                        var params = $(this).data("params") || {};
                        var category = typeof params.category !== 'undefined' ? params.category : ($(this).data("category") || '');
                        if (category) {
                            // multipart.category = category;
                        }

                        Upload.list[id] = new Dropzone(this, $.extend({
                            url: url,
                            params: function (files, xhr, chunk) {
                                var params = multipart;
                                if (chunk) {
                                    return $.extend({}, params, {
                                        filesize: chunk.file.size,
                                        filename: chunk.file.name,
                                        chunkid: chunk.file.upload.uuid,
                                        chunkindex: chunk.index,
                                        chunkcount: chunk.file.upload.totalChunkCount,
                                        chunksize: this.options.chunkSize,
                                        chunkfilesize: chunk.dataBlock.data.size,
                                        width: chunk.file.width || 0,
                                        height: chunk.file.height || 0,
                                        type: chunk.file.type,
                                    });
                                }
                                return params;
                            },
                            chunking: chunking,
                            chunkSize: chunkSize,
                            maxFilesize: maxFilesize,
                            acceptedFiles: mimetype,
                            maxFiles: (maxcount && parseInt(maxcount) > 1 ? maxcount : (multiple ? null : 1)),
                            timeout: timeout,
                            parallelUploads: 1,
                            previewsContainer: false,
                            dictDefaultMessage: __("Drop files here to upload"),
                            dictFallbackMessage: __("Your browser does not support drag'n'drop file uploads"),
                            dictFallbackText: __("Please use the fallback form below to upload your files like in the olden days"),
                            dictFileTooBig: __("File is too big (%sMiB), Max filesize: %sMiB", "{{filesize}}", "{{maxFilesize}}"),
                            dictInvalidFileType: __("You can't upload files of this type"),
                            dictResponseError: __("Server responded with %s code.", "{{statusCode}}"),
                            dictCancelUpload: __("Cancel upload"),
                            dictUploadCanceled: __("Upload canceled"),
                            dictCancelUploadConfirmation: __("Are you sure you want to cancel this upload?"),
                            dictRemoveFile: __("Remove file"),
                            dictMaxFilesExceeded: __("You can only upload a maximum of %s files", "{{maxFiles}}"),
                            init: function () {
                                Upload.events.onInit.call(this);
                                //必须添加dz-message，否则点击icon无法唤起上传窗口
                                $(">i", this.element).addClass("dz-message");
                                this.options.elementHtml = $(this.element).html();
                            },
                            sending: function (file, xhr, formData) {
                                if (typeof file.category !== 'undefined') {
                                    formData.append('category', file.category);
                                }
                            },
                            addedfile: function (file) {
                                var params = $(this.element).data("params") || {};
                                var category = typeof params.category !== 'undefined' ? params.category : ($(this.element).data("category") || '');
                                file.category = typeof category === 'function' ? category.call(this, file) : category;
                            },
                            addedfiles: function (files) {
                                if (this.options.maxFiles && (!this.options.maxFiles || this.options.maxFiles > 1) && this.options.inputId) {
                                    var inputObj = $("#" + this.options.inputId);
                                    if (inputObj.length > 0) {
                                        var value = $.trim(inputObj.val());
                                        var nums = value === '' ? 0 : value.split(/\,/).length;
                                        var remain = this.options.maxFiles - nums;
                                        if (remain === 0 || files.length > remain) {
                                            files = Array.prototype.slice.call(files, remain);
                                            for (var i = 0; i < files.length; i++) {
                                                this.removeFile(files[i]);
                                            }
                                            Toastr.error(__("You can only upload a maximum of %s files", this.options.maxFiles));
                                        }
                                    }
                                }
                            },
                            success: function (file, response) {
                                var ret = Upload.events.onUploadResponse(response, this, file);
                                file.ret = ret;
                                if (ret.code === 1) {
                                    Upload.events.onUploadSuccess(this, ret, file);
                                } else {
                                    Upload.events.onUploadError(this, ret, file);
                                }
                            },
                            error: function (file, response, xhr) {
                                var responseObj = $("<div>" + (xhr && typeof xhr.responseText !== 'undefined' ? xhr.responseText : response) + "</div>");
                                responseObj.find("style, title, script").remove();
                                var msg = responseObj.text() || __('Network error');
                                var ret = {code: 0, data: null, msg: msg};
                                Upload.events.onUploadError(this, ret, file);
                            },
                            uploadprogress: function (file, progress, bytesSent) {
                                if (file.upload.chunked) {
                                    $(this.element).prop("disabled", true).html("<i class='fa fa-upload'></i> " + __('Upload') + Math.floor((file.upload.bytesSent / file.size) * 100) + "%");
                                }
                            },
                            totaluploadprogress: function (progress, bytesSent) {
                                if (this.getActiveFiles().length > 0 && !this.options.chunking) {
                                    $(this.element).prop("disabled", true).html("<i class='fa fa-upload'></i> " + __('Upload') + Math.floor(progress) + "%");
                                }
                            },
                            queuecomplete: function () {
                                Upload.events.onUploadComplete(this, this.files);
                                this.removeAllFiles(true);
                                $(this.element).prop("disabled", false).html(this.options.elementHtml);
                            },
                            chunkSuccess: function (chunk, file, response) {
                            },
                            chunksUploaded: function (file, done) {
                                var that = this;
                                Fast.api.ajax({
                                    url: this.options.url,
                                    data: $.extend({}, multipart, {
                                        action: 'merge',
                                        filesize: file.size,
                                        filename: file.name,
                                        chunkid: file.upload.uuid,
                                        chunkcount: file.upload.totalChunkCount,
                                    })
                                }, function (data, ret) {
                                    done(JSON.stringify(ret));
                                    return false;
                                }, function (data, ret) {
                                    file.accepted = false;
                                    that._errorProcessing([file], ret.msg);
                                });
                            },
                            onUploadSuccess: onUploadSuccess,
                            onUploadError: onUploadError,
                            onUploadComplete: onUploadComplete,
                        }, Upload.options, options));

                        //拖动排序
                        if (preview_id && multiple) {
                            require(['dragsort'], function () {
                                $("#" + preview_id).dragsort({
                                    dragSelector: "li a:not(.btn-trash)",
                                    dragEnd: function () {
                                        $("#" + preview_id).trigger("fa.preview.change");
                                    },
                                    placeHolderTemplate: '<li class="col-xs-3"></li>'
                                });
                            });
                        }
                        //刷新隐藏textarea的值
                        var refresh = function (name) {
                            var data = {};
                            var textarea = $("textarea[name='" + name + "']");
                            var container = textarea.prev("ul");
                            $.each($("input,select,textarea", container).serializeArray(), function (i, j) {
                                var reg = /\[?(\w+)\]?\[(\w+)\]$/g;
                                var match = reg.exec(j.name);
                                if (!match)
                                    return true;
                                if (!isNaN(match[2])) {
                                    data[i] = j.value;
                                } else {
                                    match[1] = "x" + parseInt(match[1]);
                                    if (typeof data[match[1]] === 'undefined') {
                                        data[match[1]] = {};
                                    }
                                    data[match[1]][match[2]] = j.value;
                                }
                            });
                            var result = [];
                            $.each(data, function (i, j) {
                                result.push(j);
                            });
                            textarea.val(JSON.stringify(result));
                        };
                        if (preview_id && input_id) {
                            $(document.body).on("keyup change", "#" + input_id, function (e, data) {
                                var inputStr = $("#" + input_id).val();
                                var inputArr = inputStr.split(/\,/);
                                var previewObj = $("#" + preview_id);
                                previewObj.empty();
                                var tpl = previewObj.data("template") ? previewObj.data("template") : "";
                                var extend = previewObj.next().is("textarea") ? previewObj.next("textarea").val() : "{}";
                                var json = {};
                                try {
                                    json = JSON.parse(extend);
                                } catch (e) {
                                }
                                $.each(inputArr, function (i, j) {
                                    if (!j) {
                                        return true;
                                    }
                                    var suffix = /[\.]?([a-zA-Z0-9]+)$/.exec(j);
                                    suffix = suffix ? suffix[1] : 'file';
                                    var btnData = $(that).data();
                                    var fullurl = typeof btnData.cdnurl !== 'undefined' ? Fast.api.cdnurl(j, btnData.cdnurl) : Fast.api.cdnurl(j);
                                    j = Config.upload.fullmode ? fullurl : j;
                                    var value = (json && typeof json[i] !== 'undefined' ? json[i] : null);
                                    // 只有最后一项才是透传的附件相关信息
                                    var renderData = $.extend(true, data && typeof inputArr[i + 1] === 'undefined' ? data : {}, {url: j, fullurl: fullurl, data: btnData, key: i, index: i, value: value, row: value, suffix: suffix});
                                    var html = tpl ? Template(tpl, renderData) : Template.render(Upload.config.previewtpl, renderData);
                                    previewObj.append(html);
                                });
                                refresh(previewObj.data("name"));
                            });
                            $("#" + input_id).trigger("change");
                        }
                        if (preview_id) {
                            var previewObj = $("#" + preview_id);
                            var mode = previewObj.data("mode") ? previewObj.data("mode") : "blank";
                            // 如果是相册预览模式
                            if (mode === 'photo') {
                                previewObj.on('click', "a.thumbnail", function () {
                                    var photoData = [];
                                    $("[data-url]", previewObj).each(function (i, j) {
                                        photoData.push({"src": $(j).attr("href")});
                                    });
                                    Layer.photos({
                                        photos: {start: previewObj.find("a.thumbnail").index(this), data: photoData}
                                    });
                                    return false;
                                });
                            }
                            //监听文本框改变事件
                            previewObj.on('change keyup', "input,textarea,select", function () {
                                refresh($(this).closest("ul").data("name"));
                            });
                            // 监听事件
                            $(document.body).on("fa.preview.change", "#" + preview_id, function () {
                                var urlArr = [];
                                $("[data-url]", previewObj).each(function (i, j) {
                                    urlArr.push($(this).data("url"));
                                });
                                if (input_id) {
                                    $("#" + input_id).val(urlArr.join(","));
                                }
                                refresh(previewObj.data("name"));
                            });
                            // 移除按钮事件
                            $(document.body).on("click", "#" + preview_id + " .btn-trash", function () {
                                $(this).closest("li").remove();
                                previewObj.trigger("fa.preview.change");
                            });
                        }
                        if (input_id) {
                            $("#" + input_id).closest("form").on("reset", function () {
                                setTimeout($.proxy(function () {
                                    $("#" + input_id, this).trigger("change");
                                }, this), 0);
                            });
                            //粘贴上传、拖拽上传
                            $("body").on('paste drop', "#" + input_id, function (event) {
                                var originEvent = event.originalEvent;
                                var button = $(".plupload[data-input-id='" + $(this).attr("id") + "'],.faupload[data-input-id='" + $(this).attr("id") + "']");
                                if (event.type === 'paste' && originEvent.clipboardData && originEvent.clipboardData.items) {
                                    var items = originEvent.clipboardData.items;
                                    if ((items.length === 1 && items[0].type.indexOf("text") > -1) || (items.length === 2 && items[1].type.indexOf("text") > -1)) {

                                    } else {
                                        Upload.list[button.attr("id")].paste(originEvent);
                                        return false;
                                    }
                                }
                                if (event.type === 'drop' && originEvent.dataTransfer && originEvent.dataTransfer.files) {
                                    Upload.list[button.attr("id")].drop(originEvent);
                                    return false;
                                }
                            });
                        }
                    });
                },
                /**
                 * @deprecated Use upload instead.
                 */
                plupload: function (element, onUploadSuccess, onUploadError, onUploadComplete) {
                    return Upload.api.upload(element, onUploadSuccess, onUploadError, onUploadComplete);
                },
                /**
                 * @deprecated Use upload instead.
                 */
                faupload: function (element, onUploadSuccess, onUploadError, onUploadComplete) {
                    return Upload.api.upload(element, onUploadSuccess, onUploadError, onUploadComplete);
                },
                // AJAX异步上传
                send: function (file, onUploadSuccess, onUploadError, onUploadComplete, options) {
                    var index = Layer.msg(__('Uploading'), {offset: 't', time: 0});
                    var id = "dropzone-" + Dropzone.uuidv4();
                    var button = $('<button type="button" id="' + id + '" class="btn btn-danger hidden faupload" />');
                    if (options && typeof options === 'object') {
                        button.data(options);
                    }
                    button.appendTo("body");
                    $("#" + id).data("upload-complete", function (files) {
                        Layer.close(index);
                        Upload.list[id].removeAllFiles(true);
                    });
                    Upload.api.upload("#" + id, onUploadSuccess, onUploadError, onUploadComplete);
                    setTimeout(function () {
                        Upload.list[id].addFile(file);
                    }, 1);
                },
                custom: {
                    //自定义上传完成回调
                    afteruploadcallback: function (response) {
                        console.log(this, response);
                        alert("Custom Callback,Response URL:" + response.url);
                    },
                }
            }
        }
    ;

    return Upload;
});
