# FastAdmin + ThinkPHP 8（FastAdmin TP8）

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![ThinkPHP](https://img.shields.io/badge/ThinkPHP-8.1-2D9CDB.svg)](https://www.thinkphp.cn/)
[![Composer](https://img.shields.io/badge/Composer-2.x-885630.svg?logo=composer&logoColor=white)](https://getcomposer.org/)
[![License](https://img.shields.io/badge/License-Apache--2.0-blue.svg)](./LICENSE)

**FastAdmin TP8** 是基于 FastAdmin 原版进行升级适配的 ThinkPHP 8 后台开发框架。本分支将原有 ThinkPHP 5.x 版本迁移至 **ThinkPHP 8.1**，在尽可能保留 FastAdmin 原有功能和使用方式的基础上，适配较新的 PHP 运行环境。

如果你正在搜索 **FastAdmin + TP8**、**FastAdmin + ThinkPHP 8**、**FastAdmin ThinkPHP8 版本** 或 **FastAdmin PHP 8 后台框架**，本项目就是面向这一技术组合的适配版本。

> **项目说明：** 本仓库中的 `tp8-adaptation` 分支是基于 FastAdmin 原版进行的 ThinkPHP 8 适配工作。它与原有 ThinkPHP 5.x 分支在框架依赖、目录配置及部分机制上存在差异。该适配分支不代表 FastAdmin 官方已经发布或正式支持 ThinkPHP 8；请以本仓库实际代码、测试结果及后续维护说明为准。

## 项目简介

FastAdmin 是一款基于 ThinkPHP 和 Bootstrap 的快速后台开发框架，提供权限管理、CRUD 代码生成、插件扩展、多语言及常用后台组件等能力。

本项目重点是将 FastAdmin 从 ThinkPHP 5.x 迁移适配到 ThinkPHP 8.1，为希望使用 **FastAdmin + ThinkPHP 8（TP8）** 的开发者提供代码基础。

## 主要特性

### 权限管理

- 基于 `Auth` 的后台权限管理系统。
- 支持无限级父子级权限继承。
- 支持单个管理员拥有多个角色。
- 支持按管理组配置子级数据或个人数据权限。

### 快速开发与 CRUD

- 一键生成 CRUD，包括控制器、模型、视图、JS、语言包、菜单及回收站等。
- 一键生成控制器菜单和权限规则。
- 支持生成 API 接口文档。
- 支持 JS、CSS 压缩打包及 CDN 静态资源部署。

### 前端组件

- 基于 AdminLTE 二次开发。
- 基于 Bootstrap，适配手机、平板和 PC。
- 使用 RequireJS 管理 JS 模块，支持按需加载。
- 使用 Less 进行样式开发。
- 支持表格固定列、固定表头、跨页选择、Excel 导出及模板渲染。

### 插件与业务扩展

- 支持插件扩展及在线安装、卸载、升级（具体插件需验证 ThinkPHP 8 兼容性）。
- 提供通用会员模块和 API 模块。
- Web 会员中心与 API 接口可使用共用账号体系进行权限验证。
- 支持二级域名部署及域名绑定应用插件。
- 支持服务端和客户端多语言。

### 常用功能集成

- 支持大文件分片上传、剪贴板粘贴上传、拖拽上传及上传进度显示。
- 支持图片上传前压缩。
- 整合阿里云、腾讯云短信接口。
- 支持七牛云、阿里云 OSS、腾讯云存储、又拍云等云存储服务。
- 支持 Summernote、百度编辑器等富文本编辑器。
- 整合 QQ、微信、微博等第三方登录。
- 整合微信、支付宝等第三方支付（具体接口请按实际环境验证）。

## 技术栈与环境要求

| 依赖 | 版本 / 要求 |
| --- | --- |
| PHP | `>= 8.2`，推荐 PHP 8.3 |
| ThinkPHP | `8.1` |
| Composer | `2.x` |
| MySQL | `5.7+` |
| MariaDB | `10.1+` |
| Web 服务器 | Apache / Nginx，网站运行目录指向 `public` |

PHP 扩展：

`pdo`、`pdo_mysql`、`mbstring`、`curl`、`bcmath`、`gd`、`fileinfo`、`openssl`、`zip`、`json`。

## 安装与运行

### 1. 获取项目代码

```bash
git clone <你的仓库地址>
cd <项目目录>
```

如需使用 TP8 适配分支，请切换到：

```bash
git checkout tp8-adaptation
```

### 2. 安装 Composer 依赖

```bash
composer install
```

### 3. 配置环境变量

复制环境配置模板：

```bash
cp .env.sample .env
```

根据实际环境填写数据库等配置信息。

### 4. 配置 Web 运行目录

将 Apache 或 Nginx 站点根目录指向项目的 `public` 目录。

### 5. 执行安装

浏览器访问站点并按照安装向导完成安装；也可以尝试使用命令行安装：

```bash
php think install
```

安装完成后，安装程序会自动删除 `public/install.php`，并将 `public/admin.php` 重命名为随机入口文件。请妥善保存新的后台入口地址。

> 安装前请备份数据库及相关文件，并确认当前分支中的安装流程适用于你的部署环境。

## 分支说明

| 分支 | 框架 | PHP |
| --- | --- | --- |
| `tp8-adaptation` | ThinkPHP 8.1 | `>= 8.2` |
| `1.6.x-dev` | ThinkPHP 5.0 | `7.4` / `8.0` |

`tp8-adaptation` 是 ThinkPHP 8 适配分支。由于 ThinkPHP 5.x 与 ThinkPHP 8 在依赖、目录结构、配置方式及事件机制等方面存在差异，两个分支不建议直接互相合并。

## 已知问题与兼容性说明

当前 TP8 适配分支已知存在以下限制：

- 邮件组件尚未完成 ThinkPHP 8 适配，`Tx\Mailer` 依赖已移除，邮件发送暂不可用。
- 部分第三方插件仍按 ThinkPHP 5 的方式编写，安装或接入前需要进行 ThinkPHP 8 兼容性验证。
- `route/*.php` 中返回数组的路由定义不会被 ThinkPHP 8 路由加载；当前适配版本依赖默认控制器路由。

如果你发现新的兼容性问题，欢迎提交 Issue，并尽量附上 PHP / ThinkPHP 版本、复现步骤、错误信息及相关日志（请先移除密码、Token 等敏感内容）。

## 文档与演示

- FastAdmin 文档：https://doc.fastadmin.net
- 在线演示：https://demo.fastadmin.net
- 演示账号：`admin`
- 演示密码：`123456`

演示站数据无法修改。建议下载代码并在本地或测试服务器安装，以验证实际功能。

## 问题反馈与贡献

- 问答社区：https://ask.fastadmin.net
- FastAdmin 官方 GitHub：https://github.com/fastadminnet/fastadmin
- FastAdmin 官方 Gitee：https://gitee.com/fastadminnet/fastadmin

欢迎针对 ThinkPHP 8 适配问题提交 Issue 或 Pull Request。

提交代码前，请尽量：

1. 说明修改原因及影响范围。
2. 提供可复现的问题描述或测试步骤。
3. 确认没有提交 `.env`、密钥、Token、数据库密码、运行缓存及个人业务数据。
4. 对可能影响原有 FastAdmin 功能或第三方插件兼容性的修改作出说明。

## 特别鸣谢

感谢 FastAdmin 及以下开源项目和社区：

- ThinkPHP：https://www.thinkphp.cn
- AdminLTE：https://adminlte.io
- Bootstrap：https://getbootstrap.com
- jQuery：https://jquery.com
- Bootstrap Table：https://github.com/wenzhixin/bootstrap-table
- Nice Validator：https://validator.niceue.com
- SelectPage：https://github.com/TerryZ/SelectPage
- Layer：https://layui.com/layer/
- DropzoneJS：https://www.dropzonejs.com

## 版权信息

FastAdmin 遵循 Apache License 2.0 开源协议发布。项目中包含的第三方源码及二进制文件，其版权信息和许可协议以各自声明为准。

版权所有 Copyright © 2017-2026 FastAdmin  
https://www.fastadmin.net

---

**关键词：** FastAdmin TP8、FastAdmin + TP8、FastAdmin ThinkPHP 8、FastAdmin + ThinkPHP 8、FastAdmin ThinkPHP8 版本、ThinkPHP 8 后台框架、PHP 8 Admin Framework。
