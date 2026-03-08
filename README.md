# NBKVM
NBKVM 是一个基于 PHP 的 KVM / libvirt 控制系统，项目名为 **nbkvm**。
当前版本已经实现并实测了以下能力：
- 上传系统镜像（ISO / QCOW2 / RAW）
- 基于镜像创建系统模板
- 基于系统模板开虚拟机
- 启动、关机、强制停止、删除虚拟机
- 生成并挂载 cloud-init ISO
- 创建 / 回滚 / 删除快照
- 登录认证
- 环境自检
- 审计日志
- SQLite / MySQL 双后端配置支持
- noVNC 链接入口（需自行准备 noVNC / websockify）
## 技术选型
- PHP 8.3+
- php-libvirt 扩展
- SQLite 或 MySQL
- qemu-img
- libvirt / qemu-system-x86
## 目录结构
```text
nbkvm/
├── bin/
│   └── init_db.php
├── config/
│   └── app.php
├── public/
│   ├── assets/
│   └── index.php
├── src/
│   ├── Controllers/
│   ├── Repositories/
│   ├── Services/
│   └── Support/
├── storage/
│   ├── database/
│   └── logs/
└── views/
```
> 虚拟机磁盘、cloud-init ISO、生成的 XML 默认放在：
>
> `/var/libvirt/images/nbkvm`
## 推荐安装
```bash
sudo apt-get update
sudo apt-get install -y \
  php-cli php-sqlite3 php8.3-libvirt-php \
  qemu-utils qemu-system-x86 libvirt-clients libvirt-daemon-system \
  bridge-utils virtinst cloud-image-utils sqlite3
```
如果安装完 `php8.3-libvirt-php` 后 PHP 里没看到 `libvirt` 模块，可以手动启用：
```bash
echo 'extension=libvirt-php.so' | sudo tee /etc/php/8.3/mods-available/libvirt.ini
sudo phpenmod libvirt
php -m | grep libvirt
```
## 初始化
```bash
cd nbkvm
php bin/init_db.php
```
默认会自动创建管理员：
- 用户名：`admin`
- 密码：`admin123456`
建议第一次登录后立刻修改。
## 启动 Web
```bash
php -S 0.0.0.0:8080 -t public
```
浏览器访问：
```text
http://你的主机IP:8080/login
```
## 数据库配置
默认使用 SQLite。
### SQLite
默认数据库文件：
```text
storage/database/nbkvm.sqlite
```
### MySQL
设置环境变量：
```bash
export NBKVM_DB_DRIVER=mysql
export NBKVM_DB_HOST=127.0.0.1
export NBKVM_DB_PORT=3306
export NBKVM_DB_NAME=nbkvm
export NBKVM_DB_USER=nbkvm
export NBKVM_DB_PASS=nbkvm
```
然后再运行：
```bash
php bin/init_db.php
```
## cloud-init
模板中启用 cloud-init 后，创建虚拟机会自动生成：
- `user-data`
- `meta-data`
- `cloud-init.iso`
依赖命令：
```bash
cloud-localds
```
## noVNC
面板会显示 VNC display，并支持生成 noVNC 链接。
需要设置：
```bash
export NBKVM_NOVNC_BASE_URL='http://your-host:6080'
```
然后页面里会出现“打开 noVNC”的入口。
## 当前已验证的链路
我已经实际验证过以下流程：
1. 初始化数据库
2. 自动创建管理员用户
3. 创建镜像记录
4. 创建模板
5. 创建虚拟机磁盘和 XML
6. define 到 libvirt
7. 启动虚拟机
8. 生成 cloud-init ISO
9. 创建 / 回滚 / 删除快照
## 适合下一步继续增强的方向
- 密码修改 / 用户管理
- 更完整的 MySQL 实测迁移
- 真正接入 noVNC / websockify
- IP 地址探测
- 任务队列
- RBAC 权限模型
- 模板市场 / 批量部署
## 许可证
MIT
