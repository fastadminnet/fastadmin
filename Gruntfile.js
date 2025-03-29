module.exports = function (grunt) {

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        copy: {
            main: {
                files: []
            }
        }
    });

    var build = function (module, type, callback) {
        var config = {
            compile: {
                options: type === 'js' ? {
                    optimizeCss: "standard",
                    optimize: "uglify",   //可使用uglify|closure|none
                    preserveLicenseComments: true,
                    removeCombined: false,
                    baseUrl: "./public/assets/js/",    //JS文件所在的基础目录
                    name: "require-" + module, //来源文件,不包含后缀
                    out: "./public/assets/js/require-" + module + ".min.js"  //目标文件
                } : {
                    optimizeCss: "default",
                    optimize: "uglify",   //可使用uglify|closure|none
                    cssIn: "./public/assets/css/" + module + ".css",    //CSS文件所在的基础目录
                    out: "./public/assets/css/" + module + ".min.css"  //目标文件
                }
            }
        };


        var content = grunt.file.read("./public/assets/js/require-" + module + ".js"),
            pattern = /^require\.config\(\{[\r\n]?[\n]?(.*?)[\r\n]?[\n]?}\);/is;

        var matches = content.match(pattern);
        if (matches) {
            if (type === 'js') {
                var data = matches[1].replaceAll(/(urlArgs|baseUrl):(.*)\n/gi, '');
                const parse = require('parse-config-file'), fs = require('fs');
                require('jsonminify');

                data = JSON.minify("{\n" + data + "\n}");
                let options = parse(data);
                options.paths.tableexport = "empty:";
                Object.assign(config.compile.options, options);
            }
            let requirejs = require("./application/admin/command/Min/r");

            try {
                requirejs.optimize(config.compile.options, function (buildResponse) {
                    // var contents = require('fs').readFileSync(config.compile.options.out, 'utf8');
                    callback();
                }, function (err) {
                    console.error(err);
                    callback();
                });
            } catch (err) {
                console.error(err);
                callback();
            }
        }
    };

    // 加载 "copy" 插件
    grunt.loadNpmTasks('grunt-contrib-copy');

    grunt.registerTask('frontend:js', 'build frontend js', function () {
        var done = this.async();
        build('frontend', 'js', done);
    });

    grunt.registerTask('backend:js', 'build backend js', function () {
        var done = this.async();
        build('backend', 'js', done);
    });

    grunt.registerTask('frontend:css', 'build frontend css', function () {
        var done = this.async();
        build('frontend', 'css', done);
    });

    grunt.registerTask('backend:css', 'build frontend css', function () {
        var done = this.async();
        build('backend', 'css', done);
    });

    // 注册部署JS和CSS任务
    grunt.registerTask('deploy', 'deploy', function () {
        const fs = require('fs');
        const path = require("path")
        const nodeModulesDir = path.resolve(__dirname, "./node_modules");

        const getAllFiles = function (dirPath, arrayOfFiles) {
            files = fs.readdirSync(dirPath)

            arrayOfFiles = arrayOfFiles || []

            files.forEach(function (file) {
                if (fs.statSync(dirPath + "/" + file).isDirectory()) {
                    arrayOfFiles = getAllFiles(dirPath + "/" + file, arrayOfFiles)
                } else {
                    arrayOfFiles.push(path.join(__dirname, dirPath, "/", file))
                }
            });

            return arrayOfFiles
        };
        const mainPackage = grunt.config.get('pkg');
        let dists = mainPackage.dists || [];
        let files = [];

        // 兼容旧版本bower使用的目录
        let specialKey = {
            'fastadmin-bootstraptable': 'bootstrap-table',
            'sortablejs': 'Sortable',
            'tableexport.jquery.plugin': 'tableExport.jquery.plugin',
        };
        Object.keys(dists).forEach(key => {
            let src = ["**/*LICENSE*", "**/*license*"];
            src = src.concat(Array.isArray(dists[key]) ? dists[key] : [dists[key]]);
            files.push({expand: true, cwd: nodeModulesDir + "/" + key, src: src, dest: 'public/assets/libs/' + (specialKey[key] || key) + "/"});
        });

        // 兼容bower历史路径文件
        files = [...files,
            {src: nodeModulesDir + "/toastr/build/toastr.min.css", dest: "public/assets/libs/toastr/toastr.min.css"},
            {src: nodeModulesDir + "/bootstrap-slider/dist/css/bootstrap-slider.css", dest: "public/assets/libs/bootstrap-slider/slider.css"},
            {expand: true, cwd: nodeModulesDir + "/bootstrap-slider/dist", src: ["*.js"], dest: "public/assets/libs/bootstrap-slider/"}
        ]

        grunt.config.set('copy.main.files', files);
        grunt.task.run("copy:main");
    });

    // 注册默认任务
    grunt.registerTask('default', ['deploy', 'frontend:js', 'backend:js', 'frontend:css', 'backend:css']);

};
