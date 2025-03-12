#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const minimatch = require('minimatch');

// bower.json和bower_components目录路径
const bowerJsonPath = path.resolve(__dirname, './bower.json');
const bowerDir = path.resolve(__dirname, './public/assets/libs');

console.log('Bower postinstall: 开始清理依赖包...');

// 检查bower.json是否存在
if (!fs.existsSync(bowerJsonPath)) {
    console.error('未找到bower.json文件');
    process.exit(1);
}

// 读取bower.json配置
let bowerConfig;
try {
    bowerConfig = JSON.parse(fs.readFileSync(bowerJsonPath, 'utf8'));
} catch (err) {
    console.error('读取bower.json文件失败:', err);
    process.exit(1);
}

// 递归删除文件夹
function deleteFolderRecursive(folderPath) {
    if (fs.existsSync(folderPath)) {
        fs.readdirSync(folderPath).forEach(file => {
            const curPath = path.join(folderPath, file);
            if (fs.lstatSync(curPath).isDirectory()) {
                // 递归删除子文件夹
                deleteFolderRecursive(curPath);
            } else {
                // 删除文件
                fs.unlinkSync(curPath);
            }
        });
        // 删除空文件夹
        fs.rmdirSync(folderPath);
    }
}

// 获取要删除的文件列表（支持gitignore语法）
function getFilesToRemove(packagePath, patterns) {
    const result = [];

    // 递归查找匹配的文件和文件夹
    function findMatches(dir, relativePath = '') {
        if (!fs.existsSync(dir)) return;

        const files = fs.readdirSync(dir);

        for (const file of files) {
            const fullPath = path.join(dir, file);
            const relPath = relativePath ? path.join(relativePath, file) : file;
            const stats = fs.statSync(fullPath);

            let matched = false;

            // 检查是否匹配任何模式
            for (const pattern of patterns) {
                // 处理目录特定模式（以/结尾）
                if (pattern.endsWith('/') && stats.isDirectory()) {
                    if (minimatch(relPath, pattern.slice(0, -1)) ||
                        minimatch(relPath + '/', pattern)) {
                        matched = true;
                        break;
                    }
                }
                // 普通模式匹配
                else if (minimatch(relPath, pattern)) {
                    matched = true;
                    break;
                }
            }

            if (matched) {
                result.push(fullPath);
            }
            // 如果是目录且未匹配，则递归查找
            else if (stats.isDirectory()) {
                findMatches(fullPath, relPath);
            }
        }
    }

    findMatches(packagePath);
    return result;
}

// 获取ignores配置
const ignores = bowerConfig.ignores || {};

// 处理每个包的ignores配置
Object.keys(ignores).forEach(packageName => {
    const packagePath = path.join(bowerDir, packageName);

    // 检查包是否存在
    if (!fs.existsSync(packagePath)) {
        console.log(`包 ${packageName} 不存在，跳过`);
        return;
    }

    console.log(`处理包: ${packageName}`);

    // 获取要删除的文件/文件夹模式列表
    const patterns = ignores[packageName] || [];

    // 如果没有模式，跳过
    if (patterns.length === 0) {
        console.log(`包 ${packageName} 没有配置忽略模式，跳过`);
        return;
    }

    // 获取匹配的文件和文件夹
    const filesToRemove = getFilesToRemove(packagePath, patterns);

    // 按照路径长度排序，确保先删除深层文件
    filesToRemove.sort((a, b) => b.length - a.length);

    // 删除匹配的文件和文件夹
    filesToRemove.forEach(itemPath => {
        if (fs.existsSync(itemPath)) {
            const stats = fs.statSync(itemPath);

            if (stats.isDirectory()) {
                console.log(`删除冗余文件夹: ${itemPath}`);
                deleteFolderRecursive(itemPath);
            } else {
                console.log(`删除冗余文件: ${itemPath}`);
                fs.unlinkSync(itemPath);
            }
        }
    });
});

console.log('Bower postinstall清理完成');