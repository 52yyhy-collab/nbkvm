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
- 修改密码
- 用户管理
- 基础角色权限（admin / operator / readonly）
- 环境自检
- 审计日志
- SQLite / MySQL 双后端配置支持
- noVNC 链接入口、本地代理脚本与控制入口
- 部署脚本与部署文档
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
│   ├── init_db.php
│   └── start_novnc_proxy.sh
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
  php-cli php-sqlite3 php8.3-libvirt-php php-mysql \
  qemu-utils qemu-system-x86 libvirt-clients libvirt-daemon-system \
  bridge-utils virtinst cloud-image-utils sqlite3 \
  mariadb-server novnc websockify
```
如果安装完 `php8.3-libvirt-php` 后 PHP 里没看到 `libvirt` 模块，可以手动启用：
```bash
echo 'extension=libvirt-php.so' | sudo tee /etc/php/8.3/mods-available/libvirt.ini
sudo phpenmod libvirt
php -m | grep libvirt
```
## 初始化
### SQLite
```bash
cd nbkvm
php bin/init_db.php
```
### MySQL
```bash
export NBKVM_DB_DRIVER=mysql
export NBKVM_DB_HOST=127.0.0.1
export NBKVM_DB_PORT=3306
export NBKVM_DB_NAME=nbkvm
export NBKVM_DB_USER=nbkvm
export NBKVM_DB_PASS=nbkvm
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
## 角色权限
- `admin`
  - 用户管理
  - 镜像/模板/虚拟机/快照全部操作
- `operator`
  - 镜像/模板/虚拟机/快照写操作
  - 不能管理用户
- `readonly`
  - 仅查看
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
面板会显示 VNC display，并支持生成 noVNC 链接和代理命令。
如果你已经有 noVNC / websockify 服务，可设置：
```bash
export NBKVM_NOVNC_BASE_URL='http://your-host:6080'
```
如果你想临时给某台虚拟机起代理：
```bash
bash bin/start_novnc_proxy.sh test-vm-01 6080
```
然后打开：
```text
http://你的主机IP:6080/vnc.html
```
## 删除与清理
当删除虚拟机并勾选“同时删磁盘”时，会清理：
- 系统磁盘
- domain XML
- cloud-init ISO
- user-data
- meta-data
- 虚拟机目录
## 当前已验证的链路
我已经实际验证过以下流程：
1. 初始化数据库（SQLite / MySQL）
2. 自动创建管理员用户
3. 登录 / 修改密码
4. 创建镜像记录
5. 创建模板
6. 创建虚拟机磁盘和 XML
7. define 到 libvirt
8. 启动虚拟机
9. 生成 cloud-init ISO
10. 创建 / 回滚 / 删除快照
11. noVNC / websockify 环境检测
## 适合下一步继续增强的方向
- 更完整的 RBAC
- 真正的 noVNC 代理管理页
- IP 获取增强（guest agent / DHCP lease / 多重回退）
- 任务队列
- 更细的审计日志
- 模板市场 / 批量部署
## 部署
- 快速部署脚本：`bin/deploy_local.sh`
- 详细说明：`DEPLOY.md`
## 许可证
MIT
