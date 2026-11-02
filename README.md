FastAdmin是一款基于 ThinkPHP 8 + Bootstrap 的极速后台开发框架。


## 主要特性

* 基于`Auth`验证的权限管理系统
    * 支持无限级父子级权限继承，父级的管理员可任意增删改子级管理员及权限设置
    * 支持单管理员多角色
    * 支持管理子级数据或个人数据
* 强大的一键生成功能
    * 一键生成CRUD,包括控制器、模型、视图、JS、语言包、菜单、回收站等
    * 一键压缩打包JS和CSS文件，一键CDN静态资源部署
    * 一键生成控制器菜单和规则
    * 一键生成API接口文档
* 完善的前端功能组件开发
    * 基于`AdminLTE`二次开发
    * 基于`Bootstrap`开发，自适应手机、平板、PC
    * 基于`RequireJS`进行JS模块管理，按需加载
    * 基于`Less`进行样式开发
* 强大的插件扩展功能，在线安装卸载升级插件
* 通用的会员模块和API模块
* 共用同一账号体系的Web端会员中心权限验证和API接口会员权限验证
* 二级域名部署支持，同时域名支持绑定到应用插件
* 多语言支持，服务端及客户端支持
* 支持大文件分片上传、剪切板粘贴上传、拖拽上传，进度条显示，图片上传前压缩
* 支持表格固定列、固定表头、跨页选择、Excel导出、模板渲染等功能
* 强大的第三方应用模块支持([CMS](https://www.fastadmin.net/store/cms.html)、[CRM](https://www.fastadmin.net/store/facrm.html)、[企业网站管理系统](https://www.fastadmin.net/store/ldcms.html)、[知识库文档系统](https://www.fastadmin.net/store/knowbase.html)、[在线投票系统](https://www.fastadmin.net/store/vote.html)、[B2C商城](https://www.fastadmin.net/store/shopro.html)、[B2B2C商城](https://www.fastadmin.net/store/wanlshop.html))
* 整合第三方短信接口(阿里云、腾讯云短信)
* 无缝整合第三方云存储(七牛云、阿里云OSS、腾讯云存储、又拍云)功能，支持云储存分片上传
* 第三方富文本编辑器支持(Summernote、百度编辑器)
* 第三方登录(QQ、微信、微博)整合
* 第三方支付(微信、支付宝)无缝整合，微信支持PC端扫码支付
* 丰富的插件应用市场

## 环境要求

| 依赖 | 版本 |
| --- | --- |
| PHP | `>= 8.2`（推荐 8.3） |
| ThinkPHP | `8.1` |
| Composer | `2.x` |
| MySQL / MariaDB | MySQL `5.7+` / MariaDB `10.1+` |
| Web 服务器 | Apache / Nginx，运行目录指向 `public` |

PHP 扩展：`pdo`、`pdo_mysql`、`mbstring`、`curl`、`bcmath`、`gd`、`fileinfo`、`openssl`、`zip`、`json`。

## 安装使用

1. 安装依赖：

   ```bash
   composer install
   ```

2. 复制环境配置文件并填写数据库信息：

   ```bash
   cp .env.sample .env
   ```

3. 将 Web 服务器的运行目录设置为 `public`，浏览器访问站点完成安装；也可以使用命令行安装：

   ```bash
   php think install
   ```

4. 安装完成后，安装程序会自动删除 `public/install.php`，并将 `public/admin.php` 重命名为随机入口文件，请妥善保存新的后台入口。

更多文档：https://doc.fastadmin.net

## 分支说明

| 分支 | 框架 | PHP |
| --- | --- | --- |
| `tp8-adaptation` | ThinkPHP 8.1 | `>= 8.2` |
| `1.6.x-dev` | ThinkPHP 5.0 | `7.4` / `8.0` |

`tp8-adaptation` 分支是 ThinkPHP 8 的适配版本，目录结构、配置和事件机制与 TP5 版本不同；两个分支不能直接互相合并。

### 已知问题

* 邮件组件尚未完成 TP8 适配（`Tx\Mailer` 依赖已移除），邮件发送暂不可用。
* 部分第三方插件仍按 ThinkPHP 5 的方式编写，接入前需要先做兼容性验证。
* `route/*.php` 中返回数组的路由定义不会被 TP8 路由加载，目前依赖默认控制器路由。

## 在线演示

https://demo.fastadmin.net

用户名：admin

密　码：123456

提　示：演示站数据无法进行修改，请下载源码安装体验全部功能

## 界面截图
![控制台](https://images.gitee.com/uploads/images/2020/0929/202947_8db2d281_10933.gif "控制台")

## 问题反馈

在使用中有任何问题，请使用以下联系方式联系我们

问答社区: https://ask.fastadmin.net

Github: https://github.com/fastadminnet/fastadmin

Gitee: https://gitee.com/fastadminnet/fastadmin

## 特别鸣谢

感谢以下的项目,排名不分先后

ThinkPHP：http://www.thinkphp.cn

AdminLTE：https://adminlte.io

Bootstrap：http://getbootstrap.com

jQuery：http://jquery.com

Bootstrap-table：https://github.com/wenzhixin/bootstrap-table

Nice-validator: https://validator.niceue.com

SelectPage: https://github.com/TerryZ/SelectPage

Layer: https://layuion.com/layer/

DropzoneJS: https://www.dropzonejs.com


## 版权信息

FastAdmin遵循Apache2开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2017-2026 by FastAdmin (https://www.fastadmin.net)

All rights reserved。
